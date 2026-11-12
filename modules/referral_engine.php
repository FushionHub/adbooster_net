<?php declare(strict_types=1);

/**
 * Referral & Affiliate Engine — Adsbooster.net
 *
 * Compatibility facade over `ReferralService`, which is the canonical
 * implementation of the referral programme.
 *
 * IMPORTANT: this class used to write to the legacy `referred_user_id`
 * column and to use a `completed` status that the rest of the platform
 * (checkout, admin, payout ledger) never understood. All writes now go
 * through ReferralService, which uses the canonical `referred_id` column
 * and the `pending → rewarded` lifecycle, so the automatic reward on a
 * verified payment and the admin payout can never disagree.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/referral_service.php';

class ReferralEngine
{
    /**
     * Generate a unique 8-character referral code for a user
     *
     * @param int $userId The user ID
     * @return string The generated referral code
     * @throws Exception On database error or if code already exists
     */
    public static function generateCode(int $userId): string
    {
        try {
            $db = db();

            $stmt = $db->prepare("SELECT referral_code FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && !empty($existing['referral_code'])) {
                return (string)$existing['referral_code'];
            }

            $maxAttempts = 10;
            $attempts    = 0;

            do {
                $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));

                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE referral_code = ?");
                $stmt->execute([$code]);
                $exists = $stmt->fetchColumn() > 0;

                $attempts++;
            } while ($exists && $attempts < $maxAttempts);

            if ($attempts >= $maxAttempts) {
                throw new Exception("Unable to generate unique referral code after {$maxAttempts} attempts");
            }

            $stmt = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $stmt->execute([$code, $userId]);

            return $code;
        } catch (Exception $e) {
            error_log("ReferralEngine::generateCode Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Link a newly registered account to its referrer.
     *
     * No credits are paid here — the reward is released automatically when
     * the referred account's first payment is verified (or manually from
     * Admin → Referrals).
     *
     * @param string $code      The referral code
     * @param int    $newUserId The new user being referred
     * @return bool True if referral processed successfully
     */
    public static function processReferral(string $code, int $newUserId): bool
    {
        try {
            $db = db();

            $stmt = $db->prepare("SELECT id, username FROM users WHERE referral_code = ?");
            $stmt->execute([$code]);
            $referrer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$referrer) {
                return false;
            }

            if ((int)$referrer['id'] === $newUserId) {
                return false;   // no self-referral
            }

            $referralId = ReferralService::record((int)$referrer['id'], $newUserId, $code);

            return $referralId > 0;
        } catch (Exception $e) {
            error_log("ReferralEngine::processReferral Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get referral statistics for a user
     *
     * @param int $userId The user ID
     * @return array Statistics including count, pending, and earned amounts
     */
    public static function getReferralStats(int $userId): array
    {
        try {
            $stats = ReferralService::stats($userId);

            return [
                'total_referrals' => $stats['total'],
                'completed'       => $stats['rewarded'],
                'pending'         => $stats['pending'],
                'total_earned'    => $stats['earned'],
                'pending_earned'  => $stats['pending_amount'],
            ];
        } catch (Exception $e) {
            error_log("ReferralEngine::getReferralStats Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get list of users referred by a specific user
     *
     * @param int $userId The referrer's user ID
     * @return array Array of referred user information
     */
    public static function getReferredUsers(int $userId): array
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT
                    r.id as referral_id,
                    u.id as user_id,
                    u.username,
                    u.email,
                    r.status,
                    r.reward_credits as referrer_reward,
                    r.created_at as referred_at,
                    r.credited_at
                 FROM referrals r
                 LEFT JOIN users u ON u.id = COALESCE(r.referred_id, r.referred_user_id)
                 WHERE r.referrer_id = ?
                 ORDER BY r.created_at DESC"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ReferralEngine::getReferredUsers Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Release the referral reward for a paying account.
     *
     * Thin wrapper over ReferralService::rewardForPayment().
     */
    public static function rewardForPayment(int $userId): array
    {
        return ReferralService::rewardForPayment($userId);
    }
}
