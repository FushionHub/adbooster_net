<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../modules/payments.php';

/**
 * Unit tests for PaymentProcessor input-validation contract.
 *
 * Financial logic: target coverage 100%.
 *
 * These tests exercise every validation branch that runs BEFORE any
 * database or gateway network call, so they run without MySQL and
 * without touching the payment providers:
 *   - amount <= 0 rejected
 *   - gateway not in the registry rejected
 *   - gateway in registry but unconfigured refused with reason
 */
#[CoversClass(PaymentProcessor::class)]
final class PaymentProcessorValidationTest extends TestCase
{
    /**
     * @return array<string, array{0: float}>
     */
    public static function invalidAmountProvider(): array
    {
        return [
            'zero amount'     => [0.0],
            'negative amount' => [-1.0],
            'large negative'  => [-99999.99],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('invalidAmountProvider')]
    public function testInitiatePaymentRejectsNonPositiveAmount(float $amount): void
    {
        $result = PaymentProcessor::initiatePayment(1, 'wallet_fund', $amount, 'USD', 'flutterwave');

        $this->assertFalse($result['success']);
        $this->assertNull($result['transaction_id']);
        $this->assertNull($result['redirect_url']);
        $this->assertStringContainsStringIgnoringCase('amount', $result['message']);
    }

    public function testInitiatePaymentRejectsUnknownGatewayBeforeAnyDbCall(): void
    {
        $result = PaymentProcessor::initiatePayment(1, 'wallet_fund', 100.0, 'USD', 'stripe');

        $this->assertFalse($result['success']);
        $this->assertNull($result['transaction_id']);
        $this->assertNull($result['redirect_url']);
        $this->assertStringContainsStringIgnoringCase('gateway', $result['message']);
    }

    public function testInitiatePaymentRefusesConfiguredButUnconfiguredRegistryGateway(): void
    {
        // Paystack is in the registry but intentionally unconfigured:
        // the processor must refuse it server-side (never reachable in UI).
        $result = PaymentProcessor::initiatePayment(1, 'wallet_fund', 100.0, 'USD', 'paystack');

        $this->assertFalse($result['success']);
        $this->assertNull($result['transaction_id']);
        $this->assertNull($result['redirect_url']);
        $this->assertStringContainsString('Paystack', $result['message']);
        $this->assertStringContainsStringIgnoringCase('not available', $result['message']);
    }

    public function testResultContractHasExactlyTheDocumentedKeys(): void
    {
        $result = PaymentProcessor::initiatePayment(1, 'wallet_fund', -5.0, 'USD', 'flutterwave');

        $this->assertSame(
            ['success', 'transaction_id', 'redirect_url', 'message'],
            array_keys($result),
            'Callers rely on the exact result shape documented in the docblock'
        );
    }
}
