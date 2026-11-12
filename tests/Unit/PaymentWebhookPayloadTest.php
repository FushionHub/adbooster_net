<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../modules/payment_webhook.php';

/**
 * Unit tests for the inbound webhook decision logic.
 *
 * Covers:
 *   • payload_reports_success() — when the signed payload may settle an
 *     order without a live gateway confirmation
 *   • payload_matches_order()   — the replay guard (amount + currency)
 *   • local_reference()          — reference extraction across gateways
 *   • gateway_transaction_id()   — gateway-side id extraction
 *
 * Financial / security logic: target coverage 100%.
 */
#[CoversClass(PaymentWebhook::class)]
final class PaymentWebhookPayloadTest extends TestCase
{
    // ---------------------------------------------------------------
    // payload_reports_success()
    // ---------------------------------------------------------------

    public function testFlutterwaveSuccessfulStatusIsAccepted(): void
    {
        $payload = ['event' => 'charge.completed', 'data' => ['status' => 'successful']];
        $this->assertTrue(PaymentWebhook::payload_reports_success($payload));
    }

    public function testPaystackSuccessStatusIsAccepted(): void
    {
        $payload = ['event' => 'charge.success', 'data' => ['status' => 'success']];
        $this->assertTrue(PaymentWebhook::payload_reports_success($payload));
    }

    public function testTopLevelStatusIsAccepted(): void
    {
        $this->assertTrue(PaymentWebhook::payload_reports_success(['status' => 'completed']));
        $this->assertTrue(PaymentWebhook::payload_reports_success(['status' => 'paid']));
    }

    public function testSuccessEventNameIsAcceptedWithoutStatus(): void
    {
        $this->assertTrue(PaymentWebhook::payload_reports_success(['event' => 'charge.success']));
        $this->assertTrue(PaymentWebhook::payload_reports_success(['event' => 'payment.completed']));
        $this->assertTrue(PaymentWebhook::payload_reports_success(['event_type' => 'transaction_successful']));
        $this->assertTrue(PaymentWebhook::payload_reports_success(['event' => 'charge-completed']));
    }

    public function testFailedStatusIsRejected(): void
    {
        $payload = ['event' => 'charge.failed', 'data' => ['status' => 'failed']];
        $this->assertFalse(PaymentWebhook::payload_reports_success($payload));
    }

    public function testPendingStatusIsRejected(): void
    {
        $payload = ['event' => 'charge.pending', 'data' => ['status' => 'pending']];
        $this->assertFalse(PaymentWebhook::payload_reports_success($payload));
    }

    public function testEmptyPayloadIsRejected(): void
    {
        $this->assertFalse(PaymentWebhook::payload_reports_success([]));
    }

    public function testStatusIsCaseInsensitive(): void
    {
        $this->assertTrue(PaymentWebhook::payload_reports_success(['data' => ['status' => 'SUCCESSFUL']]));
        $this->assertTrue(PaymentWebhook::payload_reports_success(['event' => 'Charge.Completed']));
    }

    public function testFailedEventNameDoesNotSneakThrough(): void
    {
        // "unsuccessful" must not match the success vocabulary.
        $this->assertFalse(PaymentWebhook::payload_reports_success(['event' => 'payment.unsuccessful']));
        $this->assertFalse(PaymentWebhook::payload_reports_success(['event' => 'charge.failed']));
        $this->assertFalse(PaymentWebhook::payload_reports_success(['event' => 'transaction_not_successful']));
        $this->assertFalse(PaymentWebhook::payload_reports_success(['event' => 'payment_reversed']));
        $this->assertFalse(PaymentWebhook::payload_reports_success(['event' => 'charge_refunded']));
    }

    // ---------------------------------------------------------------
    // payload_matches_order()
    // ---------------------------------------------------------------

    public function testMatchingAmountIsAccepted(): void
    {
        $payload = ['data' => ['amount' => '50.00', 'currency' => 'USD']];
        $txn     = ['amount' => 50.00, 'currency' => 'USD'];

        $this->assertTrue(PaymentWebhook::payload_matches_order($payload, $txn));
    }

    public function testAmountWithinOneCentIsAccepted(): void
    {
        $payload = ['data' => ['amount' => '50.01']];
        $txn     = ['amount' => 50.00];

        $this->assertTrue(PaymentWebhook::payload_matches_order($payload, $txn));
    }

    public function testDifferentAmountIsRejected(): void
    {
        // The replay case: a captured body for a $10 order replayed
        // against a $500 order.
        $payload = ['data' => ['amount' => '10.00']];
        $txn     = ['amount' => 500.00];

        $this->assertFalse(PaymentWebhook::payload_matches_order($payload, $txn));
    }

    public function testZeroLocalAmountRejectsAnyPayloadAmount(): void
    {
        $payload = ['data' => ['amount' => '10.00']];
        $txn     = ['amount' => 0];

        $this->assertFalse(PaymentWebhook::payload_matches_order($payload, $txn));
    }

    public function testMismatchedCurrencyIsRejected(): void
    {
        $payload = ['data' => ['amount' => '50.00', 'currency' => 'NGN']];
        $txn     = ['amount' => 50.00, 'currency' => 'USD'];

        $this->assertFalse(PaymentWebhook::payload_matches_order($payload, $txn));
    }

    public function testCurrencyComparisonIsCaseInsensitive(): void
    {
        $payload = ['data' => ['currency' => 'usd']];
        $txn     = ['currency' => 'USD'];

        $this->assertTrue(PaymentWebhook::payload_matches_order($payload, $txn));
    }

    public function testPayloadWithoutAmountIsAccepted(): void
    {
        // No amount to compare — the unique reference still ties the
        // payload to exactly one order.
        $payload = ['data' => ['status' => 'successful']];
        $txn     = ['amount' => 50.00, 'currency' => 'USD'];

        $this->assertTrue(PaymentWebhook::payload_matches_order($payload, $txn));
    }

    public function testChargedAmountFieldIsAlsoChecked(): void
    {
        $payload = ['data' => ['charged_amount' => '10.00']];
        $txn     = ['amount' => 50.00];

        $this->assertFalse(PaymentWebhook::payload_matches_order($payload, $txn));
    }

    // ---------------------------------------------------------------
    // local_reference()
    // ---------------------------------------------------------------

    public function testLocalReferencePrefersTxRef(): void
    {
        $payload = ['data' => ['tx_ref' => 'ABTX-1', 'id' => 12345]];
        $this->assertSame('ABTX-1', PaymentWebhook::local_reference($payload));
    }

    public function testLocalReferenceFallsBackToReference(): void
    {
        $this->assertSame('REF-9', PaymentWebhook::local_reference(['data' => ['reference' => 'REF-9']]));
    }

    public function testLocalReferenceFallsBackToTopLevel(): void
    {
        $this->assertSame('TOP-3', PaymentWebhook::local_reference(['tx_ref' => 'TOP-3']));
    }

    public function testLocalReferenceIsEmptyWhenAbsent(): void
    {
        $this->assertSame('', PaymentWebhook::local_reference(['data' => ['status' => 'successful']]));
        $this->assertSame('', PaymentWebhook::local_reference([]));
    }

    public function testLocalReferenceIgnoresBlankValues(): void
    {
        $payload = ['data' => ['tx_ref' => '   ', 'reference' => 'REAL-1']];
        $this->assertSame('REAL-1', PaymentWebhook::local_reference($payload));
    }

    // ---------------------------------------------------------------
    // gateway_transaction_id()
    // ---------------------------------------------------------------

    public function testFlutterwaveTransactionIdIsExtracted(): void
    {
        $payload = ['data' => ['flw_ref' => '1234567', 'tx_ref' => 'ABTX-1']];
        $this->assertSame('1234567', PaymentWebhook::gateway_transaction_id($payload, 'flutterwave'));
    }

    public function testNonNumericFlutterwaveIdIsIgnored(): void
    {
        // /transactions/{id}/verify needs a numeric id; a tx_ref there
        // would only produce a confusing 400.
        $payload = ['data' => ['id' => 'ABTX-1']];
        $this->assertSame('', PaymentWebhook::gateway_transaction_id($payload, 'flutterwave'));
    }

    public function testGenericGatewayKeepsNonNumericId(): void
    {
        $payload = ['data' => ['id' => 'stripe-abc']];
        $this->assertSame('stripe-abc', PaymentWebhook::gateway_transaction_id($payload, 'stripe'));
    }

    public function testGatewayTransactionIdIsEmptyWhenAbsent(): void
    {
        $this->assertSame('', PaymentWebhook::gateway_transaction_id(['data' => ['tx_ref' => 'ABTX-1']], 'flutterwave'));
    }
}
