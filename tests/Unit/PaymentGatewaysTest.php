<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../modules/payment_gateways.php';

/**
 * Unit tests for PaymentGateways — the payment method registry.
 *
 * Financial logic: target coverage 100%.
 *
 * NOTE: keys are resolved constant -> env -> site_settings. In the test
 * environment get_setting() is undefined, so DB fallback is skipped and
 * behaviour is driven by config/apikeys.php constants only. Paystack is
 * intentionally unconfigured; Flutterwave ships with credentials.
 */
#[CoversClass(PaymentGateways::class)]
final class PaymentGatewaysTest extends TestCase
{
    // ---------------------------------------------------------------
    // isEnabled()
    // ---------------------------------------------------------------

    public function testPaystackIsDisabledWithoutCredentials(): void
    {
        $this->assertFalse(PaymentGateways::isEnabled('paystack'));
    }

    public function testFlutterwaveIsEnabledWithCredentials(): void
    {
        $this->assertTrue(PaymentGateways::isEnabled('flutterwave'));
    }

    public function testUnknownGatewayIsNeverEnabled(): void
    {
        $this->assertFalse(PaymentGateways::isEnabled('stripe'));
        $this->assertFalse(PaymentGateways::isEnabled(''));
        $this->assertFalse(PaymentGateways::isEnabled('  '));
    }

    public function testIsEnabledIsCaseAndWhitespaceInsensitive(): void
    {
        $this->assertSame(PaymentGateways::isEnabled('flutterwave'), PaymentGateways::isEnabled('  FLUTTERWAVE '));
    }

    // ---------------------------------------------------------------
    // enabled() / disabled() / defaultGateway()
    // ---------------------------------------------------------------

    public function testEnabledListsOnlyConfiguredGateways(): void
    {
        $enabled = PaymentGateways::enabled();
        $this->assertContains('flutterwave', $enabled);
        $this->assertNotContains('paystack', $enabled);
    }

    public function testDisabledListsUnconfiguredGateways(): void
    {
        $disabled = PaymentGateways::disabled();
        $this->assertContains('paystack', $disabled);
        $this->assertNotContains('flutterwave', $disabled);
    }

    public function testEnabledAndDisabledPartitionRegistry(): void
    {
        $all = array_merge(PaymentGateways::enabled(), PaymentGateways::disabled());
        sort($all);
        $expected = array_keys(PaymentGateways::REGISTRY);
        sort($expected);
        $this->assertSame($expected, $all, 'Every registry gateway must be either enabled or disabled, never both');
    }

    public function testDefaultGatewayReturnsFirstEnabledOrNull(): void
    {
        $default = PaymentGateways::defaultGateway();
        $enabled = PaymentGateways::enabled();
        $this->assertSame($enabled[0] ?? null, $default);
    }

    // ---------------------------------------------------------------
    // config()
    // ---------------------------------------------------------------

    public function testConfigForUnknownGatewayFallsBackToGenericMeta(): void
    {
        $cfg = PaymentGateways::config('nonexistent');
        $this->assertSame('Nonexistent', $cfg['label']);
        $this->assertFalse($cfg['configured']);
        $this->assertSame('bearer', $cfg['mode']);
    }

    public function testFlutterwaveConfigReportsMissingEncryptionSlots(): void
    {
        $cfg = PaymentGateways::config('flutterwave');
        $this->assertSame('flutterwave', $cfg['key']);
        $this->assertTrue($cfg['configured']);
        // Encryption key from apikeys.php is NOT 32 chars -> Bearer flow
        $this->assertSame('bearer', $cfg['mode']);
        $this->assertNotSame('', $cfg['secret_key']);
    }

    // ---------------------------------------------------------------
    // credential()
    // ---------------------------------------------------------------

    public function testCredentialForUnknownGatewayIsEmpty(): void
    {
        $this->assertSame('', PaymentGateways::credential('stripe', 'public'));
        $this->assertSame('', PaymentGateways::credential('flutterwave', 'unknown-slot'));
    }

    public function testPaystackCredentialFallsBackToEmptyString(): void
    {
        $this->assertSame('', PaymentGateways::credential('paystack', 'public'));
        $this->assertSame('', PaymentGateways::credential('paystack', 'secret'));
    }

    // ---------------------------------------------------------------
    // unavailableReason()
    // ---------------------------------------------------------------

    public function testUnavailableReasonEmptyForConfiguredGateway(): void
    {
        $this->assertSame('', PaymentGateways::unavailableReason('flutterwave'));
    }

    public function testUnavailableReasonNamesMissingPaystackKeys(): void
    {
        $reason = PaymentGateways::unavailableReason('paystack');
        $this->assertStringContainsString('Missing', $reason);
        $this->assertStringContainsString('public key', $reason);
        $this->assertStringContainsString('secret key', $reason);
    }

    // ---------------------------------------------------------------
    // publicKey() / secretKey()
    // ---------------------------------------------------------------

    public function testKeyGettersNeverThrowForUnknownGateway(): void
    {
        $this->assertSame('', PaymentGateways::publicKey('stripe'));
        $this->assertSame('', PaymentGateways::secretKey('stripe'));
        $this->assertNotSame('', PaymentGateways::secretKey('flutterwave'));
    }

    // ---------------------------------------------------------------
    // all()
    // ---------------------------------------------------------------

    public function testAllCoversEveryRegistryEntry(): void
    {
        $all = PaymentGateways::all();
        $this->assertSame(array_keys(PaymentGateways::REGISTRY), array_keys($all));
        foreach ($all as $cfg) {
            $this->assertArrayHasKey('configured', $cfg);
            $this->assertIsBool($cfg['configured']);
        }
    }
}
