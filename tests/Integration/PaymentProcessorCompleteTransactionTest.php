<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/site_setting_and_config.php'; // price_fmt()
require_once __DIR__ . '/../../modules/payments.php';

/**
 * Integration tests: PaymentProcessor::completeTransaction() financial math.
 *
 * Financial logic: target coverage 100%.
 *
 * Note: initiatePayment()'s happy path performs a live gateway HTTP call,
 * which is out of scope for tests; only its user-not-found branch is
 * covered here. The wallet crediting math runs through completeTransaction().
 *
 * Recreate the test DB with: php -d extension=php_pdo_mysql.dll tests/make-test-db.php
 */
#[CoversClass(PaymentProcessor::class)]
#[RequiresPhpExtension('pdo_mysql')]
final class PaymentProcessorCompleteTransactionTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        $this->userId = db()->insert('users', [
            'email'     => 'payer_' . bin2hex(random_bytes(6)) . '@test.local',
            'full_name' => 'Payer',
            'status'    => 'active',
            'ad_credits' => 0,
            'ai_credits' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        db()->delete('transactions', 'user_id = ?', [$this->userId]);
        db()->delete('users', 'id = ?', [$this->userId]);
    }

    private function makeTransaction(string $type, float $amount, string $currency, array $meta = []): int
    {
        return db()->insert('transactions', [
            'user_id'   => $this->userId,
            'type'      => $type,
            'amount'    => $amount,
            'currency'  => $currency,
            'status'    => 'pending',
            'metadata'  => json_encode($meta),
        ]);
    }

    // ---------------------------------------------------------------
    // Guards
    // ---------------------------------------------------------------

    public function testCompleteTransactionFailsForUnknownId(): void
    {
        $this->assertFalse(PaymentProcessor::completeTransaction(999999999));
    }

    public function testCompleteTransactionIsIdempotentForAlreadySuccessful(): void
    {
        $txnId = $this->makeTransaction('ad_credit_topup', 50.0, 'USD');
        db()->update('transactions', ['status' => 'successful'], 'id = ?', [$txnId]);

        $this->assertTrue(PaymentProcessor::completeTransaction($txnId));
    }

    public function testCompleteTransactionFailsForUnexpectedStatus(): void
    {
        $txnId = $this->makeTransaction('ad_credit_topup', 50.0, 'USD');
        db()->update('transactions', ['status' => 'failed'], 'id = ?', [$txnId]);

        $this->assertFalse(PaymentProcessor::completeTransaction($txnId));
    }

    // ---------------------------------------------------------------
    // Wallet crediting math
    // ---------------------------------------------------------------

    public function testAdCreditTopupCreditsUsdAmount(): void
    {
        // A legacy USD-denominated charge ($25.50) keeps its dollar meaning:
        // the wallet is naira-denominated, so the dollar value is converted
        // once at the wallet boundary — $25.50 is worth ₦39,525 of budget.
        $txnId = $this->makeTransaction('ad_credit_topup', 25.50, 'USD');

        $this->assertTrue(PaymentProcessor::completeTransaction($txnId));

        $user = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->userId]);
        $this->assertSame(credit_units_to_ngn(25.50), (float)$user['ad_credits']);

        $txn = db()->fetchOne("SELECT status FROM transactions WHERE id = ?", [$txnId]);
        $this->assertSame('successful', $txn['status']);
    }

    public function testAiCreditTopupGrantsHundredCreditsPerDollar(): void
    {
        $txnId = $this->makeTransaction('ai_credit_topup', 10.0, 'USD');

        $this->assertTrue(PaymentProcessor::completeTransaction($txnId));

        $user = db()->fetchOne("SELECT ai_credits FROM users WHERE id = ?", [$this->userId]);
        $this->assertSame(1000, (int)$user['ai_credits'], '100 AI credits per $1');
    }

    public function testNgnChargeCreditsWalletOneToOneInNaira(): void
    {
        // Merchant initiated 15,500 NGN. The ad-credit wallet is naira-
        // denominated (₦1 credit = ₦1 of ad budget), so the wallet receives
        // the full charged naira — the pinned USD value is the ledger twin,
        // NOT the wallet amount.
        $txnId = $this->makeTransaction('ad_credit_topup', 15500.0, 'NGN', ['amount_usd' => 10.00]);

        $this->assertTrue(PaymentProcessor::completeTransaction($txnId));

        $user = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$this->userId]);
        $this->assertSame(15500.0, (float)$user['ad_credits'], 'NGN charge credits the naira wallet 1:1');
    }

    public function testNgnChargeUsesPinnedUsdForAiCreditMath(): void
    {
        // 15,500 NGN with a pinned USD twin of 10.00 → 1,000 AI credits
        // (100 per $1). The pinned value wins over the live rate.
        $txnId = $this->makeTransaction('ai_credit_topup', 15500.0, 'NGN', ['amount_usd' => 10.00]);

        $this->assertTrue(PaymentProcessor::completeTransaction($txnId));

        $user = db()->fetchOne("SELECT ai_credits FROM users WHERE id = ?", [$this->userId]);
        $this->assertSame(1000, (int)$user['ai_credits'], 'Pinned amount_usd wins over the live rate');
    }

    public function testNgnChargeWithoutPinnedRateFallsBackToFallbackRate(): void
    {
        // 7,750 NGN at the 1550 fallback rate = 5.00 USD
        $txnId = $this->makeTransaction('ai_credit_topup', 7750.0, 'NGN');

        $this->assertTrue(PaymentProcessor::completeTransaction($txnId));

        $user = db()->fetchOne("SELECT ai_credits FROM users WHERE id = ?", [$this->userId]);
        $this->assertSame(500, (int)$user['ai_credits'], '5.00 USD * 100 credits');
    }

    public function testCompleteTransactionCreditsUserNotFoundPathOnInitiate(): void
    {
        // initiatePayment() with a nonexistent user must fail before any
        // gateway HTTP call is made.
        $result = PaymentProcessor::initiatePayment(999999999, 'wallet_fund', 100.0, 'USD', 'flutterwave');

        $this->assertFalse($result['success']);
        $this->assertNull($result['transaction_id']);
        $this->assertNull($result['redirect_url']);
        $this->assertSame('User not found.', $result['message']);
    }
}
