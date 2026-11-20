<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/site_setting_and_config.php';
require_once __DIR__ . '/../../modules/ThirdPartyGateway.php';

/**
 * Unit tests: actionable gateway failure diagnostics.
 *
 * The connect screen used to collapse EVERY failure into one sentence:
 * "Bundle.social team could not be resolved (invalid gateway key or network
 * failure)". That sends the operator to the wrong place — a DNS outage is not
 * a key problem, and an HTTP 401 is not a network problem.
 *
 * describeFailure() is a pure function over the gateway envelope, so each
 * failure mode can be asserted without touching the network.
 */
#[CoversClass(ThirdPartyGateway::class)]
final class GatewayFailureDiagnosticsTest extends TestCase
{
    public function testDnsFailureIsNamedAsDns(): void
    {
        $message = ThirdPartyGateway::describeFailure('bundle_social', [
            'success'     => false,
            'status_code' => 0,
            'error'       => 'Could not resolve host: api.bundle.social',
            'curl_errno'  => CURLE_COULDNT_RESOLVE_HOST,
        ]);

        $this->assertStringContainsString('DNS', $message);
        $this->assertStringContainsString('not an API-key problem', $message);
    }

    public function testTimeoutIsNamedAsTimeout(): void
    {
        $message = ThirdPartyGateway::describeFailure('bundle_social', [
            'success'     => false,
            'status_code' => 0,
            'error'       => 'Operation timed out after 45001 milliseconds',
            'curl_errno'  => CURLE_OPERATION_TIMEDOUT,
        ]);

        $this->assertStringContainsString('timeout', $message);
        $this->assertStringContainsString('firewall', $message);
        $this->assertStringContainsString('not an API-key problem', $message);
    }

    public function testConnectionRefusedIsNamed(): void
    {
        $message = ThirdPartyGateway::describeFailure('zernio', [
            'success'     => false,
            'status_code' => 0,
            'error'       => 'Connection refused',
            'curl_errno'  => CURLE_COULDNT_CONNECT,
        ]);

        $this->assertStringContainsString('refused', $message);
        $this->assertStringContainsString('Zernio', $message);
    }

    public function testTlsFailureIsNamed(): void
    {
        $message = ThirdPartyGateway::describeFailure('bundle_social', [
            'success'     => false,
            'status_code' => 0,
            'error'       => 'SSL: certificate subject name does not match',
            'curl_errno'  => CURLE_SSL_CONNECT_ERROR,
        ]);

        $this->assertStringContainsString('TLS', $message);
        $this->assertStringContainsString('certificate', $message);
    }

    public function testRejectedKeyIsNamed(): void
    {
        $message = ThirdPartyGateway::describeFailure('bundle_social', [
            'success'     => false,
            'status_code' => 401,
            'raw'         => '{"error":"Invalid API key"}',
        ]);

        $this->assertStringContainsString('rejected the API key', $message);
        $this->assertStringContainsString('401', $message);
        $this->assertStringContainsString('rotate', $message);
    }

    public function testForbiddenIsTreatedAsARejectedCredential(): void
    {
        $message = ThirdPartyGateway::describeFailure('zernio', [
            'success'     => false,
            'status_code' => 403,
        ]);

        $this->assertStringContainsString('rejected the API key', $message);
        $this->assertStringContainsString('403', $message);
    }

    public function testPaymentRequiredKeepsTheGatewayReason(): void
    {
        // Zernio answers 402 PAYMENT_REQUIRED when the workspace is paused —
        // that is neither a key problem nor a network problem.
        $message = ThirdPartyGateway::describeFailure('zernio', [
            'success'     => false,
            'status_code' => 402,
            'raw'         => '{"error":"Account paused: payment required","code":"PAYMENT_REQUIRED"}',
        ]);

        $this->assertStringContainsString('402', $message);
        $this->assertStringContainsString('Account paused', $message);
        $this->assertStringNotContainsString('API key', $message);
    }

    public function testNotFoundNamesTheEndpoint(): void
    {
        $message = ThirdPartyGateway::describeFailure('zernio', [
            'success'     => false,
            'status_code' => 404,
        ]);

        $this->assertStringContainsString('404', $message);
        $this->assertStringContainsString('base URL', $message);
    }

    public function testRateLimitIsNamed(): void
    {
        $message = ThirdPartyGateway::describeFailure('bundle_social', [
            'success'     => false,
            'status_code' => 429,
        ]);

        $this->assertStringContainsString('rate-limited', $message);
    }

    public function testProviderOutageIsNamed(): void
    {
        $message = ThirdPartyGateway::describeFailure('bundle_social', [
            'success'     => false,
            'status_code' => 503,
        ]);

        $this->assertStringContainsString('unavailable', $message);
        $this->assertStringContainsString('provider-side', $message);
    }

    public function testUnknownNetworkErrorStillNamesTheProvider(): void
    {
        $message = ThirdPartyGateway::describeFailure('bundle_social', [
            'success'     => false,
            'status_code' => 0,
            'error'       => 'something odd happened',
        ]);

        $this->assertStringContainsString('Bundle.social', $message);
        $this->assertStringContainsString('something odd happened', $message);
    }
}
