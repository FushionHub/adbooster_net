<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/site_setting_and_config.php';
require_once __DIR__ . '/../../modules/payments.php';
require_once __DIR__ . '/../../modules/referral_service.php';

/**
 * Integration tests: the referral programme.
 *
 * Financial logic: target coverage 100%.
 *
 * Two payout paths exist and MUST agree:
 *   • ReferralService::rewardForPayment() — automatic, fired by
 *     PaymentProcessor::completeTransaction() on a verified payment
 *   • ReferralService::payReward()        — manual, fired by
 *     Admin → Referrals
 *
 * Both share one idempotency guard: the pending → rewarded flip.
 *
 * Recreate the test DB with: php -d extension=php_pdo_mysql.dll tests/make-test-db.php
 * Then apply the schema repair:  php database/apply_update_any_error.php
 */
#[CoversClass(ReferralService::class)]
#[RequiresPhpExtension('pdo_mysql')]
final class ReferralServiceTest extends TestCase
{
    private int $referrerId;
    private int $referredId;

    /** @var array<string,string> Original setting values restored in tearDown */
    private array $originalSettings = [];

    protected function setUp(): void
    {
        // Email deliberately fails filter_var() so NotificationService never
        // attempts a real SMTP delivery during the suite.
        $this->referrerId = $this->createUser('referrer');
        $this->referredId = $this->createUser('referred');

        foreach (['referral_reward_credits', 'referral_referred_bonus', 'referral_auto_reward'] as $key) {
            $row = db()->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
            $this->originalSettings[$key] = $row['setting_value'] ?? null;
            db()->query(
                "DELETE FROM site_settings WHERE setting_key = ?",
                [$key]
            );
        }
        $this->refreshSettingsCache();
    }

    protected function tearDown(): void
    {
        db()->delete('referrals', 'referrer_id = ? OR referred_id = ?', [$this->referrerId, $this->referredId]);
        db()->delete('referrals', 'referred_user_id = ?', [$this->referredId]);
        db()->delete('referral_rewards', 'referrer_id = ? OR referred_user_id = ?', [$this->referrerId, $this->referredId]);
        db()->delete('transactions', 'user_id = ?', [$this->referredId]);
        db()->delete('notifications', 'user_id IN (?, ?)', [$this->referrerId, $this->referredId]);

        foreach ($this->originalSettings as $key => $value) {
            db()->query("DELETE FROM site_settings WHERE setting_key = ?", [$key]);
            if ($value !== null) {
                db()->insert('site_settings', [
                    'setting_key'   => $key,
                    'setting_value' => $value,
                    'setting_group' => 'referrals',
                ]);
            }
        }
        $this->refreshSettingsCache();

        db()->delete('users', 'id IN (?, ?)', [$this->referrerId, $this->referredId]);
    }

    private function createUser(string $prefix): int
    {
        return db()->insert('users', [
            'email'      => $prefix . '_' . bin2hex(random_bytes(6)) . 'no-at-sign',
            'full_name'  => ucfirst($prefix),
            'status'     => 'active',
            'ad_credits' => 0,
            'ai_credits' => 0,
        ]);
    }

    /** get_setting() memoises per request — clear it after touching settings. */
    private function refreshSettingsCache(): void
    {
        $GLOBALS['settingsCache'] = null;
    }

    private function setSetting(string $key, string $value): void
    {
        $exists = db()->fetchOne("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
        if ($exists !== null) {
            db()->update('site_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            db()->insert('site_settings', [
                'setting_key'   => $key,
                'setting_value' => $value,
                'setting_group' => 'referrals',
            ]);
        }
        $this->refreshSettingsCache();
    }

    private function makePendingTransaction(int $userId, string $type = 'ad_credit_topup', float $amount = 50.0): int
    {
        return db()->insert('transactions', [
            'user_id'   => $userId,
            'type'      => $type,
            'amount'    => $amount,
            'currency'  => 'USD',
            'status'    => 'pending',
            'metadata'  => json_encode(['amount_usd' => $amount]),
        ]);
    }

    // ---------------------------------------------------------------
    // record() — the link created at registration
    // ---------------------------------------------------------------

    public function testRecordCreatesPendingReferralWithDefaultReward(): void
    {
        $id = ReferralService::record($this->referrerId, $this->referredId);

        $this->assertGreaterThan(0, $id);

        $row = db()->fetchOne("SELECT * FROM referrals WHERE id = ?", [$id]);
        $this->assertSame($this->referrerId, (int)$row['referrer_id']);
        $this->assertSame($this->referredId, (int)$row['referred_id']);
        $this->assertSame($this->referredId, (int)$row['referred_user_id'], 'legacy mirror must be populated');
        $this->assertSame('pending', $row['status']);
        $this->assertSame(10.00, (float)$row['reward_credits'], 'default reward is 10 ad credits');
    }

    public function testRecordMirrorsTheLinkOntoTheReferredUser(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);

        $user = db()->fetchOne("SELECT referred_by FROM users WHERE id = ?", [$this->referredId]);
        $this->assertSame($this->referrerId, (int)$user['referred_by']);
    }

    public function testRecordIsIdempotentForTheSameReferredUser(): void
    {
        $first  = ReferralService::record($this->referrerId, $this->referredId);
        $second = ReferralService::record($this->referrerId, $this->referredId);

        $this->assertSame($first, $second);
        $this->assertSame(1, db()->count('referrals', 'referred_id = ?', [$this->referredId]));
    }

    public function testRecordRejectsSelfReferral(): void
    {
        $this->assertSame(0, ReferralService::record($this->referrerId, $this->referrerId));
        $this->assertSame(0, ReferralService::record(0, $this->referredId));
        $this->assertSame(0, ReferralService::record($this->referrerId, 0));
    }

    public function testRecordHonoursAdminConfiguredRewardAmount(): void
    {
        $this->setSetting('referral_reward_credits', '25.50');

        ReferralService::record($this->referrerId, $this->referredId);

        $row = db()->fetchOne(
            "SELECT reward_credits FROM referrals WHERE referred_id = ?",
            [$this->referredId]
        );
        $this->assertSame(25.50, (float)$row['reward_credits']);
    }

    // ---------------------------------------------------------------
    // rewardForPayment() — the AUTOMATIC payout
    // ---------------------------------------------------------------

    public function testRewardForPaymentPaysBothParties(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);

        $result = ReferralService::rewardForPayment($this->referredId);

        $this->assertTrue($result['rewarded']);
        $this->assertTrue($result['credited']);
        $this->assertSame($this->referrerId, $result['referrer_id']);
        $this->assertSame(10.00, $result['amount']);

        $referrer = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referrerId]);
        $referred = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referredId]);

        $this->assertSame(10.00, (float)$referrer['ad_credits'], 'referrer earns the reward');
        $this->assertSame(5.00, (float)$referred['ad_credits'], 'referred account earns the welcome bonus');

        $row = db()->fetchOne("SELECT status, credited_at FROM referrals WHERE referred_id = ?", [$this->referredId]);
        $this->assertSame('rewarded', $row['status']);
        $this->assertNotNull($row['credited_at']);
    }

    public function testRewardForPaymentWritesLedgerRow(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);

        ReferralService::rewardForPayment($this->referredId);

        $ledger = db()->fetchOne(
            "SELECT * FROM referral_rewards WHERE referred_user_id = ?",
            [$this->referredId]
        );
        $this->assertNotNull($ledger, 'a payout ledger row must be written');
        $this->assertSame(10.00, (float)$ledger['referrer_reward']);
        $this->assertSame(5.00, (float)$ledger['referred_reward']);
    }

    public function testRewardForPaymentIsIdempotent(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);

        $first  = ReferralService::rewardForPayment($this->referredId);
        $second = ReferralService::rewardForPayment($this->referredId);

        $this->assertTrue($first['rewarded']);
        $this->assertFalse($second['rewarded'], 'the second pass must not claim the referral again');
        $this->assertSame('Referral was already rewarded.', $second['message']);

        $referrer = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referrerId]);
        $this->assertSame(10.00, (float)$referrer['ad_credits'], 'credits must be paid exactly once');
    }

    public function testRewardForPaymentSkipsWhenNoPendingReferral(): void
    {
        $result = ReferralService::rewardForPayment($this->referredId);

        $this->assertFalse($result['rewarded']);
        $this->assertSame('No pending referral for this account.', $result['message']);
    }

    public function testRewardForPaymentSkipsWhenAutoRewardDisabled(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);
        $this->setSetting('referral_auto_reward', '0');

        $result = ReferralService::rewardForPayment($this->referredId);

        $this->assertFalse($result['rewarded']);
        $this->assertSame('Automatic referral rewards are disabled.', $result['message']);

        $row = db()->fetchOne("SELECT status FROM referrals WHERE referred_id = ?", [$this->referredId]);
        $this->assertSame('pending', $row['status'], 'the referral stays payable manually');
    }

    public function testRewardForPaymentIsCaseInsensitiveOnAutoRewardFlag(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);
        $this->setSetting('referral_auto_reward', 'OFF');

        $result = ReferralService::rewardForPayment($this->referredId);
        $this->assertFalse($result['rewarded']);
    }

    // ---------------------------------------------------------------
    // payReward() — the MANUAL/admin payout
    // ---------------------------------------------------------------

    public function testPayRewardPaysReferrerManually(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);
        $referralId = (int)db()->fetchOne(
            "SELECT id FROM referrals WHERE referred_id = ?",
            [$this->referredId]
        )['id'];

        $result = ReferralService::payReward($referralId, 'Manual admin payout');

        $this->assertTrue($result['rewarded']);
        $this->assertSame($this->referrerId, $result['referrer_id']);
        $this->assertSame(10.00, $result['amount']);

        $referrer = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referrerId]);
        $this->assertSame(10.00, (float)$referrer['ad_credits']);
    }

    public function testPayRewardRefusesToPayTwice(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);
        $referralId = (int)db()->fetchOne(
            "SELECT id FROM referrals WHERE referred_id = ?",
            [$this->referredId]
        )['id'];

        $first  = ReferralService::payReward($referralId);
        $second = ReferralService::payReward($referralId);

        $this->assertTrue($first['rewarded']);
        $this->assertFalse($second['rewarded']);

        $referrer = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referrerId]);
        $this->assertSame(10.00, (float)$referrer['ad_credits'], 'a manual payout must never double-pay');
    }

    public function testPayRewardHonoursAdjustedAmount(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);
        $referralId = (int)db()->fetchOne(
            "SELECT id FROM referrals WHERE referred_id = ?",
            [$this->referredId]
        )['id'];

        db()->update('referrals', ['reward_credits' => 42.00], 'id = ?', [$referralId]);

        $result = ReferralService::payReward($referralId);

        $this->assertSame(42.00, $result['amount']);
        $referrer = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referrerId]);
        $this->assertSame(42.00, (float)$referrer['ad_credits']);
    }

    public function testPayRewardRejectsUnknownId(): void
    {
        $result = ReferralService::payReward(999999999);
        $this->assertFalse($result['rewarded']);
        $this->assertSame('Referral not found.', $result['message']);
    }

    public function testPayRewardRejectsZeroId(): void
    {
        $result = ReferralService::payReward(0);
        $this->assertFalse($result['rewarded']);
        $this->assertSame('Invalid referral id.', $result['message']);
    }

    // ---------------------------------------------------------------
    // reset / reject — admin corrections
    // ---------------------------------------------------------------

    public function testResetToPendingAllowsASecondPayout(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);
        $referralId = (int)db()->fetchOne(
            "SELECT id FROM referrals WHERE referred_id = ?",
            [$this->referredId]
        )['id'];

        ReferralService::payReward($referralId);
        $reset = ReferralService::resetToPending($referralId);

        $this->assertTrue($reset['ok']);

        $row = db()->fetchOne("SELECT status, credited_at FROM referrals WHERE id = ?", [$referralId]);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['credited_at']);
    }

    public function testRejectNeverPaysAndBlocksManualPayout(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);
        $referralId = (int)db()->fetchOne(
            "SELECT id FROM referrals WHERE referred_id = ?",
            [$this->referredId]
        )['id'];

        $rejected = ReferralService::reject($referralId);
        $this->assertTrue($rejected['ok']);

        $result = ReferralService::payReward($referralId);
        $this->assertFalse($result['rewarded'], 'a rejected referral must not be payable');

        $referrer = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referrerId]);
        $this->assertSame(0.00, (float)$referrer['ad_credits']);
    }

    public function testRejectCannotOverwriteAnAlreadyRewardedReferral(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);
        $referralId = (int)db()->fetchOne(
            "SELECT id FROM referrals WHERE referred_id = ?",
            [$this->referredId]
        )['id'];

        ReferralService::payReward($referralId);
        $result = ReferralService::reject($referralId);

        $this->assertFalse($result['ok']);
        $row = db()->fetchOne("SELECT status FROM referrals WHERE id = ?", [$referralId]);
        $this->assertSame('rewarded', $row['status']);
    }

    // ---------------------------------------------------------------
    // Full payment → reward chain
    // ---------------------------------------------------------------

    public function testCompleteTransactionAutomaticallyRewardsTheReferral(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);

        $txnId = $this->makePendingTransaction($this->referredId, 'ad_credit_topup', 20.00);
        $this->assertTrue(PaymentProcessor::completeTransaction($txnId));

        $referrer = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referrerId]);
        $referred = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referredId]);

        // 20.00 top-up + 5.00 referral welcome bonus
        $this->assertSame(25.00, (float)$referred['ad_credits']);
        // 10.00 referral reward
        $this->assertSame(10.00, (float)$referrer['ad_credits']);

        $row = db()->fetchOne("SELECT status FROM referrals WHERE referred_id = ?", [$this->referredId]);
        $this->assertSame('rewarded', $row['status']);
    }

    public function testReCompletingTheSameTransactionDoesNotDoubleReward(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);

        $txnId = $this->makePendingTransaction($this->referredId, 'ad_credit_topup', 20.00);
        PaymentProcessor::completeTransaction($txnId);
        // Second call short-circuits on status, but the guard must hold anyway.
        ReferralService::rewardForPayment($this->referredId);
        ReferralService::payReward((int)db()->fetchOne(
            "SELECT id FROM referrals WHERE referred_id = ?",
            [$this->referredId]
        )['id']);

        $referrer = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->referrerId]);
        $this->assertSame(10.00, (float)$referrer['ad_credits'], 'the referrer is paid exactly once');

        $ledgerCount = db()->count('referral_rewards', 'referred_user_id = ?', [$this->referredId]);
        $this->assertSame(1, $ledgerCount, 'exactly one ledger row per referred account');
    }

    // ---------------------------------------------------------------
    // stats()
    // ---------------------------------------------------------------

    public function testStatsReflectTheLifecycle(): void
    {
        ReferralService::record($this->referrerId, $this->referredId);

        $before = ReferralService::stats($this->referrerId);
        $this->assertSame(1, $before['total']);
        $this->assertSame(1, $before['pending']);
        $this->assertSame(0, $before['rewarded']);
        $this->assertSame(10.00, $before['pending_amount']);
        $this->assertSame(0.00, $before['earned']);

        ReferralService::rewardForPayment($this->referredId);

        $after = ReferralService::stats($this->referrerId);
        $this->assertSame(1, $after['rewarded']);
        $this->assertSame(0, $after['pending']);
        $this->assertSame(10.00, $after['earned']);
        $this->assertSame(0.00, $after['pending_amount']);
    }
}
