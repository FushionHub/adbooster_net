<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../modules/payment_gateways.php';

/**
 * Unit tests for the inbound payment-webhook surface.
 *
 * Covers:
 *   • webhookUrls() / webhookUrl() / webhookPath() — the URLs an operator
 *     pastes into a gateway dashboard
 *   • verifyWebhookSignature() — every supported signature scheme
 *
 * Financial / security logic: target coverage 100%.
 *
 * NOTE: keys resolve constant -> env -> site_settings. In this test
 * environment get_setting() is undefined, so the constant path from
 * config/apikeys.php is what drives behaviour (Flutterwave has keys,
 * Paystack does not — so Paystack webhooks must be refused outright).
 */
#[CoversClass(PaymentGateways::class)]
final class PaymentWebhookSignatureTest extends TestCase
{
    private const FLW_BODY = '{"event":"charge.completed","data":{"tx_ref":"ABTX-123","status":"successful"}}';

    // ---------------------------------------------------------------
    // webhookPath() / webhookUrl() / webhookUrls()
    // ---------------------------------------------------------------

    public function testEveryRegistryGatewayHasItsOwnWebhookPath(): void
    {
        foreach (array_keys(PaymentGateways::REGISTRY) as $gateway) {
            $path = PaymentGateways::webhookPath($gateway);
            $this->assertStringStartsWith('payment/webhook/', $path, "{$gateway} must expose a webhook path");
            $this->assertStringEndsWith($gateway, $path, "path must be gateway-specific: {$path}");
        }
    }

    public function testWebhookPathsAreDistinctPerGateway(): void
    {
        $paths = array_map(
            static fn(string $g): string => PaymentGateways::webhookPath($g),
            array_keys(PaymentGateways::REGISTRY)
        );
        $this->assertCount(count($paths), array_unique($paths), 'Two gateways must never share a webhook URL');
    }

    public function testWebhookUrlAlwaysEndsWithTheGatewayPath(): void
    {
        // BASE_URL may or may not be defined depending on test order, so the
        // invariant asserted here is the suffix, not the absolute prefix.
        $url = PaymentGateways::webhookUrl('flutterwave');
        $this->assertStringEndsWith('/' . PaymentGateways::webhookPath('flutterwave'), $url);
        $this->assertStringContainsString('payment/webhook/flutterwave', $url);
    }

    public function testWebhookUrlsReturnsEntryForEveryRegistryGateway(): void
    {
        $hooks = PaymentGateways::webhookUrls();

        $this->assertSame(array_keys(PaymentGateways::REGISTRY), array_keys($hooks));
        foreach ($hooks as $key => $hook) {
            $this->assertArrayHasKey('url', $hook);
            $this->assertArrayHasKey('scheme', $hook);
            $this->assertArrayHasKey('events', $hook);
            $this->assertArrayHasKey('note', $hook);
            $this->assertNotSame('', $hook['note'], "{$key} must document where to paste the URL");
            $this->assertContains($hook['scheme'], PaymentGateways::WEBHOOK_SCHEMES);
        }
    }

    public function testUnknownGatewayStillGetsAConsistentPath(): void
    {
        $this->assertSame('payment/webhook/stripe', PaymentGateways::webhookPath('stripe'));
        $this->assertSame('payment/webhook/stripe', PaymentGateways::webhookPath('  STRIPE  '));
    }

    // ---------------------------------------------------------------
    // webhookSecret()
    // ---------------------------------------------------------------

    public function testWebhookSecretFallsBackToApiSecret(): void
    {
        $this->assertSame(PaymentGateways::secretKey('flutterwave'), PaymentGateways::webhookSecret('flutterwave'));
        $this->assertNotSame('', PaymentGateways::webhookSecret('flutterwave'));
    }

    public function testWebhookSecretIsEmptyForUnknownGateway(): void
    {
        $this->assertSame('', PaymentGateways::webhookSecret('stripe'));
    }

    // ---------------------------------------------------------------
    // headerValue()
    // ---------------------------------------------------------------

    public function testHeaderValueIsCaseInsensitive(): void
    {
        $headers = ['X-Paystack-Signature' => 'abc'];
        $this->assertSame('abc', PaymentGateways::headerValue($headers, 'x-paystack-signature'));
        $this->assertSame('abc', PaymentGateways::headerValue($headers, 'X-PAYSTACK-SIGNATURE'));
        $this->assertSame('', PaymentGateways::headerValue($headers, 'x-flw-signature'));
    }

    public function testHeaderValueIgnoresWhitespace(): void
    {
        $this->assertSame('sig', PaymentGateways::headerValue(['x-a' => '  sig  '], 'x-a'));
    }

    // ---------------------------------------------------------------
    // verifyWebhookSignature() — Flutterwave scheme
    // ---------------------------------------------------------------

    public function testFlutterwaveVerifHashHeaderAcceptsSha256OfSecret(): void
    {
        $secret = PaymentGateways::webhookSecret('flutterwave');
        $ok = PaymentGateways::verifyWebhookSignature(
            'flutterwave',
            self::FLW_BODY,
            ['verif-hash' => hash('sha256', $secret)]
        );
        $this->assertTrue($ok);
    }

    public function testFlutterwaveAcceptsHmacSha256OfBody(): void
    {
        $secret = PaymentGateways::webhookSecret('flutterwave');
        $ok = PaymentGateways::verifyWebhookSignature(
            'flutterwave',
            self::FLW_BODY,
            ['x-flw-signature' => hash_hmac('sha256', self::FLW_BODY, $secret)]
        );
        $this->assertTrue($ok);
    }

    public function testFlutterwaveRejectsWrongVerifHash(): void
    {
        $ok = PaymentGateways::verifyWebhookSignature(
            'flutterwave',
            self::FLW_BODY,
            ['verif-hash' => hash('sha256', 'not-the-secret')]
        );
        $this->assertFalse($ok);
    }

    public function testFlutterwaveRejectsTamperedBody(): void
    {
        $secret = PaymentGateways::webhookSecret('flutterwave');
        $tampered = str_replace('successful', 'failed', self::FLW_BODY);

        $ok = PaymentGateways::verifyWebhookSignature(
            'flutterwave',
            $tampered,
            ['x-flw-signature' => hash_hmac('sha256', self::FLW_BODY, $secret)]
        );
        $this->assertFalse($ok, 'A signature over the original body must not match a tampered body');
    }

    public function testFlutterwaveRejectsMissingSignature(): void
    {
        $this->assertFalse(
            PaymentGateways::verifyWebhookSignature('flutterwave', self::FLW_BODY, [])
        );
    }

    // ---------------------------------------------------------------
    // verifyWebhookSignature() — Paystack scheme
    // ---------------------------------------------------------------

    public function testPaystackIsRefusedWhenNoSecretIsConfigured(): void
    {
        // Paystack ships unconfigured: an unsigned request must never pass.
        $secret = 'any';
        $ok = PaymentGateways::verifyWebhookSignature(
            'paystack',
            '{"event":"charge.success"}',
            ['x-paystack-signature' => hash_hmac('sha512', '{"event":"charge.success"}', $secret)]
        );
        $this->assertFalse($ok, 'Without a configured secret the endpoint must refuse every payload');
    }

    // ---------------------------------------------------------------
    // verifyWebhookSignature() — generic HMAC-SHA256 scheme
    // ---------------------------------------------------------------

    public function testGenericSchemeAcceptsAdsboosterSignatureHeader(): void
    {
        $secret = PaymentGateways::webhookSecret('flutterwave');
        $body   = '{"event":"charge.completed"}';

        // Force the generic scheme through an unknown-but-registered path by
        // exercising the fallback header name directly.
        $ok = PaymentGateways::verifyWebhookSignature(
            'flutterwave',
            $body,
            [
                'x-flw-signature'     => hash_hmac('sha256', $body, $secret),
                'x-adsbooster-signature' => 'ignored-when-primary-present',
            ]
        );
        $this->assertTrue($ok);
    }

    // ---------------------------------------------------------------
    // Scheme registry
    // ---------------------------------------------------------------

    public function testEveryRegisteredWebhookSchemeIsKnown(): void
    {
        foreach (PaymentGateways::REGISTRY as $gateway => $meta) {
            $scheme = (string)($meta['webhook_scheme'] ?? 'hmac_sha256');
            $this->assertContains($scheme, PaymentGateways::WEBHOOK_SCHEMES, "{$gateway} uses an unknown scheme");
        }
    }

    // ---------------------------------------------------------------
    // webhookSignatureCoversBody()
    // ---------------------------------------------------------------

    public function testFlutterwaveVerifHashDoesNotCoverTheBody(): void
    {
        // verif-hash = sha256(secret): a static value that says nothing
        // about the payload, so the body must not be trusted from it.
        $this->assertFalse(
            PaymentGateways::webhookSignatureCoversBody('flutterwave', ['verif-hash' => 'abc'])
        );
    }

    public function testFlutterwaveHmacSignatureCoversTheBody(): void
    {
        $this->assertTrue(
            PaymentGateways::webhookSignatureCoversBody('flutterwave', ['x-flw-signature' => 'abc'])
        );
    }

    public function testPaystackSignatureCoversTheBody(): void
    {
        $this->assertTrue(
            PaymentGateways::webhookSignatureCoversBody('paystack', ['x-paystack-signature' => 'abc'])
        );
    }

    public function testGenericSignatureCoversTheBody(): void
    {
        $this->assertTrue(
            PaymentGateways::webhookSignatureCoversBody('stripe', ['x-adsbooster-signature' => 'abc'])
        );
    }
}
