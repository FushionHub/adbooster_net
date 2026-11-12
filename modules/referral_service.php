<?php declare(strict_types=1);

/**
 * Referral Service — Adsbooster.net
 *
 * Single source of truth for the referral programme:
 *
 *   • `record()`      — links a new account to the user who referred it.
 *   • `rewardForPayment()` — AUTOMATIC reward. Called the moment a referred
 *                        user's payment is verified, it pays the referrer and
 *                        the referred user without any admin action.
 *   • `payReward()`   — MANUAL/admin reward. The admin can still trigger the
 *                        exact same payout for a referral that could not be
 *                        auto-rewarded (gateway outage, manual top-up, …).
 *
 * Both paths share one idempotency guard: the `pending → rewarded` status
 * flip is a conditional UPDATE, so a referral can only ever be paid once no
 * matter how many times verification or a cron run happens.
 *
 * The reward amounts are admin-tunable through `site_settings`:
 *   • `referral_reward_credits`     — ad credits paid to the referrer
 *   • `referral_referred_bonus`     — ad credits paid to the new account
 *   • `referral_auto_reward`        — "1" enables the automatic payout
 */

require_once __DIR__ . '/../config/db.php';

class ReferralService
{
    /** Fallback amounts used when no site setting is present. */
    public const DEFAULT_REFERRER_REWARD = 10.00;
    public const DEFAULT_REFERRED_BONUS  = 5.00;

    /** Statuses used by the referral ledger. */
    public const STATUS_PENDING  = 'pending';
    public const STATUS_REWARDED = 'rewarded';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Resolve the tunable reward amounts.
     *
     * @return array{referrer_reward:float,referred_bonus:float,auto_reward:bool}
     */
    public static function settings(): array
    {
        $referrerReward = (float)get_setting(
            'referral_reward_credits',
            (string)self::DEFAULT_REFERRER_REWARD
        );
        $referredBonus = (float)get_setting(
            'referral_referred_bonus',
            (string)self::DEFAULT_REFERRED_BONUS
        );
        $autoReward = get_setting('referral_auto_reward', '1');

        return [
            'referrer_reward' => $referrerReward > 0 ? $referrerReward : self::DEFAULT_REFERRER_REWARD,
            'referred_bonus'  => $referredBonus >= 0 ? $referredBonus : self::DEFAULT_REFERRED_BONUS,
            'auto_reward'     => !in_array(strtolower(trim($autoReward)), ['0', 'off', 'false', 'no'], true),
        ];
    }

    /**
     * Link a brand-new account to the user who referred it.
     *
     * The link is created with `pending` status: no credits are paid yet.
     * The payout happens automatically on the referred user's first
     * successful payment (or manually from Admin → Referrals).
     *
     * @return int The referral row id (0 when the link could not be created)
     */
    public static function record(int $referrerId, int $referredId, ?string $code = null): int
    {
        if ($referrerId <= 0 || $referredId <= 0 || $referrerId === $referredId) {
            return 0;
        }

        try {
            $db = db();

            // A user can only ever be referred once.
            $existing = $db->fetchOne(
                "SELECT id FROM referrals WHERE referred_id = ? LIMIT 1",
                [$referredId]
            );
            if ($existing !== null) {
                return (int)$existing['id'];
            }

            $reward = self::settings()['referrer_reward'];

            $id = $db->insert('referrals', [
                'referrer_id'       => $referrerId,
                'referred_id'       => $referredId,
                'referred_user_id'  => $referredId,   // legacy mirror column
                'code'              => $code !== null && $code !== '' ? $code : null,
                'reward_credits'    => $reward,
                'status'            => self::STATUS_PENDING,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);

            // Mirror the link onto the user row for fast "who referred me" reads.
            try {
                $db->update('users', ['referred_by' => $referrerId], 'id = ?', [$referredId]);
            } catch (\Throwable $e) {
                error_log('ReferralService::record could not mirror referred_by: ' . $e->getMessage());
            }

            return $id;
        } catch (\Throwable $e) {
            error_log('ReferralService::record error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * AUTOMATIC reward — pay the referral that belongs to a paying user.
     *
     * Called from PaymentProcessor::completeTransaction() the moment a
     * transaction is verified. Safe to call repeatedly: the referral is only
     * paid when the conditional `pending → rewarded` UPDATE actually flips a
     * row.
     *
     * @param int   $userId      The user who just paid
     * @param array $transaction The completed transaction row (context only)
     * @return array{credited:bool,rewarded:bool,referral_id:int,referrer_id:int,
     *               amount:float,referred_bonus:float,message:string}
     */
    public static function rewardForPayment(int $userId, array $transaction = []): array
    {
        $result = [
            'credited'       => false,
            'rewarded'       => false,
            'referral_id'    => 0,
            'referrer_id'    => 0,
            'amount'         => 0.0,
            'referred_bonus' => 0.0,
            'message'        => '',
        ];

        if ($userId <= 0) {
            return $result;
        }

        $settings = self::settings();
        if (!$settings['auto_reward']) {
            $result['message'] = 'Automatic referral rewards are disabled.';
            return $result;
        }

        try {
            $db = db();

            // `referred_id` is canonical; `referred_user_id` is the legacy
            // mirror column kept in sync by the schema migration.
            $referral = $db->fetchOne(
                "SELECT id, referrer_id, referred_id, referred_user_id, reward_credits, status
                 FROM referrals
                 WHERE (referred_id = ? OR (referred_id IS NULL AND referred_user_id = ?))
                   AND status = ?
                 ORDER BY id ASC LIMIT 1",
                [$userId, $userId, self::STATUS_PENDING]
            );

            if ($referral === null) {
                // Distinguish "nothing to do" from "already handled" so the
                // admin sees an accurate reason instead of a generic miss.
                $already = $db->fetchOne(
                    "SELECT id, status FROM referrals
                      WHERE (referred_id = ? OR (referred_id IS NULL AND referred_user_id = ?))
                        AND status <> ?
                      ORDER BY id ASC LIMIT 1",
                    [$userId, $userId, self::STATUS_PENDING]
                );
                if ($already !== null) {
                    $result['referral_id'] = (int)$already['id'];
                    $result['message'] = $already['status'] === self::STATUS_REWARDED
                        ? 'Referral was already rewarded.'
                        : 'Referral is ' . $already['status'] . ' and will not be paid automatically.';
                } else {
                    $result['message'] = 'No pending referral for this account.';
                }
                return $result;
            }

            $referrerId = (int)$referral['referrer_id'];
            if ($referrerId <= 0 || $referrerId === $userId) {
                $result['message'] = 'Referral row has no valid referrer.';
                return $result;
            }

            // Reward amount: the value stored on the row (admin may have
            // adjusted it), falling back to the current default.
            $amount = (float)($referral['reward_credits'] ?? 0);
            if ($amount <= 0) {
                $amount = $settings['referrer_reward'];
            }
            $referredBonus = $settings['referred_bonus'];

            // ── Idempotency guard ────────────────────────────────────
            // Only the caller whose UPDATE actually changes a row may pay.
            $claimed = $db->query(
                "UPDATE referrals
                    SET status = ?, credited_at = NOW(), updated_at = NOW(),
                        reward_credits = ?
                  WHERE id = ? AND status = ?",
                [self::STATUS_REWARDED, $amount, (int)$referral['id'], self::STATUS_PENDING]
            )->rowCount();

            if ($claimed !== 1) {
                $result['referral_id'] = (int)$referral['id'];
                $result['message']     = 'Referral was already rewarded.';
                return $result;
            }

            // ── Pay the referrer ──────────────────────────────────────
            if ($amount > 0) {
                $db->query(
                    "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?",
                    [$amount, $referrerId]
                );
            }

            // ── Pay the referred account (welcome bonus) ──────────────
            if ($referredBonus > 0) {
                $db->query(
                    "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?",
                    [$referredBonus, $userId]
                );
            }

            // ── Ledger entry (idempotent via the unique key) ─────────
            try {
                $db->query(
                    "INSERT INTO referral_rewards
                        (referrer_id, referred_user_id, referrer_reward, referred_reward, created_at)
                     VALUES (?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE
                        referrer_reward = VALUES(referrer_reward),
                        referred_reward = VALUES(referred_reward)",
                    [$referrerId, $userId, $amount, $referredBonus]
                );
            } catch (\Throwable $ledgerErr) {
                error_log('ReferralService::rewardForPayment ledger write failed: ' . $ledgerErr->getMessage());
            }

            $result['credited']       = $amount > 0;
            $result['rewarded']       = true;
            $result['referral_id']    = (int)$referral['id'];
            $result['referrer_id']    = $referrerId;
            $result['amount']         = $amount;
            $result['referred_bonus'] = $referredBonus;
            $result['message']        = 'Referral rewarded automatically.';

            self::notify(
                $referrerId,
                'referral_reward',
                'Referral Bonus Earned!',
                'Your referral made their first payment! You received '
                    . number_format($amount, 2) . ' ad credits.',
                '/referrals.php'
            );
            self::notify(
                $userId,
                'referral_bonus',
                'Welcome Bonus Credited',
                'Your first payment unlocked ' . number_format($referredBonus, 2) . ' ad credits from your referral.',
                '/dashboard.php'
            );

            return $result;
        } catch (\Throwable $e) {
            error_log('ReferralService::rewardForPayment error: ' . $e->getMessage());
            $result['message'] = 'Referral reward failed: ' . $e->getMessage();
            return $result;
        }
    }

    /**
     * MANUAL/ADMIN reward — pay a referral that was not auto-rewarded.
     *
     * Uses the exact same payout path as the automatic reward, so an admin
     * payout and an automatic payout can never disagree.
     *
     * @param int    $referralId The referral row to pay
     * @param string $note       Optional audit note
     * @return array{credited:bool,rewarded:bool,referrer_id:int,amount:float,message:string}
     */
    public static function payReward(int $referralId, string $note = ''): array
    {
        $result = [
            'credited'    => false,
            'rewarded'    => false,
            'referrer_id' => 0,
            'amount'      => 0.0,
            'message'     => '',
        ];

        if ($referralId <= 0) {
            $result['message'] = 'Invalid referral id.';
            return $result;
        }

        try {
            $db = db();

            $referral = $db->fetchOne(
                "SELECT id, referrer_id, referred_id, reward_credits, status
                 FROM referrals WHERE id = ? LIMIT 1",
                [$referralId]
            );
            if ($referral === null) {
                $result['message'] = 'Referral not found.';
                return $result;
            }

            if ($referral['status'] === self::STATUS_REWARDED) {
                $result['referral_id'] = (int)$referral['id'];
                $result['message']     = 'Referral #' . $referralId . ' was already rewarded.';
                return $result;
            }

            // Only a PENDING referral is payable. A rejected referral must
            // never be released by a stray click.
            if ($referral['status'] !== self::STATUS_PENDING) {
                $result['referral_id'] = (int)$referral['id'];
                $result['message']     = 'Referral #' . $referralId . ' is '
                    . $referral['status'] . ' and cannot be paid.';
                return $result;
            }

            $referrerId = (int)$referral['referrer_id'];
            $referredId = (int)($referral['referred_id'] ?? 0);
            $settings   = self::settings();

            $amount = (float)($referral['reward_credits'] ?? 0);
            if ($amount <= 0) {
                $amount = $settings['referrer_reward'];
            }
            $referredBonus = $settings['referred_bonus'];

            $claimed = $db->query(
                "UPDATE referrals
                    SET status = ?, credited_at = NOW(), updated_at = NOW(), reward_credits = ?
                  WHERE id = ? AND status = ?",
                [self::STATUS_REWARDED, $amount, $referralId, self::STATUS_PENDING]
            )->rowCount();

            if ($claimed !== 1) {
                $result['referral_id'] = $referralId;
                $result['message']     = 'Referral #' . $referralId . ' was already rewarded.';
                return $result;
            }

            if ($amount > 0 && $referrerId > 0) {
                $db->query(
                    "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?",
                    [$amount, $referrerId]
                );
            }
            if ($referredBonus > 0 && $referredId > 0) {
                $db->query(
                    "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?",
                    [$referredBonus, $referredId]
                );
            }

            try {
                $db->query(
                    "INSERT INTO referral_rewards
                        (referrer_id, referred_user_id, referrer_reward, referred_reward, created_at)
                     VALUES (?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE
                        referrer_reward = VALUES(referrer_reward),
                        referred_reward = VALUES(referred_reward)",
                    [$referrerId, $referredId, $amount, $referredBonus]
                );
            } catch (\Throwable $ledgerErr) {
                error_log('ReferralService::payReward ledger write failed: ' . $ledgerErr->getMessage());
            }

            $result['credited']    = $amount > 0;
            $result['rewarded']    = true;
            $result['referral_id'] = $referralId;
            $result['referrer_id'] = $referrerId;
            $result['amount']      = $amount;
            $result['message']     = 'Referral #' . $referralId . ' rewarded: '
                . number_format($amount, 2) . ' ad credits to the referrer.'
                . ($note !== '' ? ' (' . $note . ')' : '');

            if ($referrerId > 0) {
                self::notify(
                    $referrerId,
                    'referral_reward',
                    'Referral Bonus Earned!',
                    'You received ' . number_format($amount, 2) . ' ad credits from a referral.',
                    '/referrals.php'
                );
            }

            return $result;
        } catch (\Throwable $e) {
            error_log('ReferralService::payReward error: ' . $e->getMessage());
            $result['message'] = 'Referral payout failed: ' . $e->getMessage();
            return $result;
        }
    }

    /**
     * Reset a rewarded referral back to pending (admin correction).
     *
     * The already-paid credits are NOT clawed back automatically — the
     * admin decides whether to reverse them from Admin → Users.
     */
    public static function resetToPending(int $referralId): array
    {
        if ($referralId <= 0) {
            return ['ok' => false, 'message' => 'Invalid referral id.'];
        }
        try {
            $rows = db()->query(
                "UPDATE referrals
                    SET status = ?, credited_at = NULL, updated_at = NOW()
                  WHERE id = ? AND status <> ?",
                [self::STATUS_PENDING, $referralId, self::STATUS_PENDING]
            )->rowCount();
            return [
                'ok'      => $rows > 0,
                'message' => $rows > 0
                    ? 'Referral #' . $referralId . ' reset to pending.'
                    : 'Referral #' . $referralId . ' was already pending.',
            ];
        } catch (\Throwable $e) {
            error_log('ReferralService::resetToPending error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Reset failed: ' . $e->getMessage()];
        }
    }

    /**
     * Reject a referral (e.g. fraud / self-referral) without paying it.
     */
    public static function reject(int $referralId): array
    {
        if ($referralId <= 0) {
            return ['ok' => false, 'message' => 'Invalid referral id.'];
        }
        try {
            $rows = db()->query(
                "UPDATE referrals
                    SET status = ?, credited_at = NULL, updated_at = NOW()
                  WHERE id = ? AND status <> ?",
                [self::STATUS_REJECTED, $referralId, self::STATUS_REWARDED]
            )->rowCount();
            return [
                'ok'      => $rows > 0,
                'message' => $rows > 0
                    ? 'Referral #' . $referralId . ' rejected.'
                    : 'Referral #' . $referralId . ' was already rewarded or rejected.',
            ];
        } catch (\Throwable $e) {
            error_log('ReferralService::reject error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Reject failed: ' . $e->getMessage()];
        }
    }

    /**
     * Referral ledger summary for one user.
     *
     * @return array{total:int,pending:int,rewarded:int,rejected:int,earned:float,pending_amount:float}
     */
    public static function stats(int $userId): array
    {
        $row = db()->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'rewarded' THEN 1 ELSE 0 END) AS rewarded,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                COALESCE(SUM(CASE WHEN status = 'rewarded' THEN reward_credits ELSE 0 END), 0) AS earned,
                COALESCE(SUM(CASE WHEN status = 'pending'  THEN reward_credits ELSE 0 END), 0) AS pending_amount
             FROM referrals WHERE referrer_id = ?",
            [$userId]
        ) ?: [];

        return [
            'total'           => (int)($row['total'] ?? 0),
            'pending'         => (int)($row['pending'] ?? 0),
            'rewarded'        => (int)($row['rewarded'] ?? 0),
            'rejected'        => (int)($row['rejected'] ?? 0),
            'earned'          => (float)($row['earned'] ?? 0),
            'pending_amount'  => (float)($row['pending_amount'] ?? 0),
        ];
    }

    /**
     * Best-effort notification — never breaks a payout.
     */
    private static function notify(int $userId, string $type, string $title, string $body, string $link): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            if (!class_exists('NotificationService')) {
                require_once __DIR__ . '/notification_service.php';
            }
            NotificationService::create($userId, $type, $title, $body, $link);
        } catch (\Throwable $e) {
            error_log('ReferralService notification failed: ' . $e->getMessage());
        }
    }
}
