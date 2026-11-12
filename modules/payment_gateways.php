<?php declare(strict_types=1);

/**
 * Payment Gateways — the single registry that decides which payment
 * methods exist on this deployment.
 *
 * A gateway is only shown to a user when it is fully configured. If a
 * gateway's API key is missing it is *auto-deactivated*: it disappears
 * from the checkout UI and the payment processor refuses it server-side,
 * so a customer can never reach a dead payment screen.
 *
 * Paystack is intentionally left unconfigured on this deployment — it
 * stays hidden until real keys are entered.
 */

require_once __DIR__ . '/../config/apikeys.php';

class PaymentGateways
{
    /**
     * Every gateway the platform knows about.
     *
     * `requires` lists the credential groups that must all be present
     * for the gateway to be considered configured.
     */
    public const REGISTRY = [
        'paystack' => [
            'label'  => 'Paystack',
            'blurb'  => 'Cards, transfers & mobile money',
            'icon'   => 'ph-bank',
            'colour' => 'var(--accent)',
            'requires' => ['public', 'secret'],
            // Inbound webhook endpoint (paste this URL into the gateway dashboard).
            'webhook_path'   => 'payment/webhook/paystack',
            'webhook_scheme' => 'paystack',
            'webhook_events' => ['charge.success', 'transfer.success'],
            'webhook_note'   => 'Paystack Dashboard → Settings → API Keys & Webhooks → "URL" (set as a secret key).',
        ],
        'flutterwave' => [
            'label'  => 'Flutterwave',
            'blurb'  => 'Cards, USSD & bank transfers',
            'icon'   => 'ph-wallet',
            'colour' => 'var(--blue)',
            'requires' => ['public', 'secret'],
            'webhook_path'   => 'payment/webhook/flutterwave',
            'webhook_scheme' => 'flutterwave',
            'webhook_events' => ['charge.completed', 'transfer.completed'],
            'webhook_note'   => 'Flutterwave Dashboard → Settings → Webhooks → "Webhook URL" (use the public key as the secret hash).',
        ],
    ];

    /**
     * Signature schemes understood by verifyWebhookSignature().
     *
     *  • paystack     — `x-paystack-signature` = HMAC-SHA512(raw body, secret key)
     *  • flutterwave  — `verif-hash` = sha256(secret key) OR
     *                  `x-flw-signature` = HMAC-SHA256(raw body, secret key)
     *  • hmac_sha256  — `x-adsbooster-signature` / `x-webhook-signature`
     *                  = HMAC-SHA256(raw body, secret) — the generic scheme any
     *                  newly registered gateway inherits.
     */
    public const WEBHOOK_SCHEMES = ['paystack', 'flutterwave', 'hmac_sha256'];

    /**
     * Resolve one credential for a gateway.
     *
     * Order: PHP constant -> environment variable -> site_settings row.
     */
    public static function credential(string $gateway, string $slot): string
    {
        switch ($gateway) {
            case 'paystack':
                return match ($slot) {
                    'public' => api_key('PAYSTACK_PUBLIC_KEY', 'paystack_public_key', ''),
                    'secret' => (string)(api_key('PAYSTACK_SECRET_KEY', 'paystack_secret_key', '')
                        ?: api_key('PAYSTACK_LIVE_KEY', 'paystack_live_key', '')),
                    'test'   => api_key('PAYSTACK_TEST_KEY', 'paystack_test_key', ''),
                    'webhook' => (string)(api_key('PAYSTACK_WEBHOOK_SECRET', 'paystack_webhook_secret', '')
                        ?: api_key('PAYSTACK_SECRET_KEY', 'paystack_secret_key', '')),
                    default  => '',
                };
            case 'flutterwave':
                return match ($slot) {
                    'public'     => api_key('FLUTTERWAVE_PUBLIC_KEY', 'flutterwave_public_key', ''),
                    'secret'     => (string)(api_key('FLUTTERWAVE_SECRET_KEY', 'flutterwave_secret_key', '')
                        ?: api_key('FLUTTERWAVE_LIVE_KEY', 'flutterwave_live_key', '')),
                    'encryption' => api_key('FLUTTERWAVE_ENCRYPTION_KEY', 'flutterwave_encryption_key', ''),
                    'test'       => api_key('FLUTTERWAVE_TEST_KEY', 'flutterwave_test_key', ''),
                    'webhook'    => (string)(api_key('FLUTTERWAVE_WEBHOOK_SECRET', 'flutterwave_webhook_secret', '')
                        ?: api_key('FLUTTERWAVE_SECRET_KEY', 'flutterwave_secret_key', '')),
                    default      => '',
                };
            default:
                return '';
        }
    }

    /**
     * Full configuration snapshot for a gateway.
     *
     * @return array{key:string,label:string,blurb:string,icon:string,colour:string,
     *               configured:bool,missing:string[],public_key:string,secret_key:string,
     *               encryption_key:string,mode:string}
     */
    public static function config(string $gateway): array
    {
        $meta = self::REGISTRY[$gateway] ?? [
            'label'    => ucfirst($gateway),
            'blurb'    => '',
            'icon'     => 'ph-credit-card',
            'colour'   => 'var(--accent)',
            'requires' => ['public', 'secret'],
        ];

        $public     = self::credential($gateway, 'public');
        $secret     = self::credential($gateway, 'secret');
        $encryption = $gateway === 'flutterwave' ? self::credential($gateway, 'encryption') : '';

        $missing = [];
        foreach ($meta['requires'] as $slot) {
            if (trim(self::credential($gateway, (string)$slot)) === '') {
                $missing[] = (string)$slot;
            }
        }

        // Flutterwave can additionally encrypt the request payload. That is
        // only possible with a genuine 32-character encryption key, so the
        // Bearer flow is used otherwise.
        $mode = 'bearer';
        if ($gateway === 'flutterwave' && $encryption !== '') {
            $mode = strlen($encryption) === 32 ? 'encrypted' : 'bearer';
        }

        return [
            'key'            => $gateway,
            'label'          => $meta['label'],
            'blurb'          => $meta['blurb'],
            'icon'           => $meta['icon'],
            'colour'         => $meta['colour'],
            'configured'     => $missing === [],
            'missing'        => $missing,
            'public_key'     => $public,
            'secret_key'     => $secret,
            'encryption_key' => $encryption,
            'mode'           => $mode,
        ];
    }

    /**
     * All known gateways with their configuration.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::REGISTRY) as $gateway) {
            $out[$gateway] = self::config($gateway);
        }
        return $out;
    }

    /**
     * Gateways that are fully configured and therefore safe to show.
     *
     * @return string[]
     */
    public static function enabled(): array
    {
        $enabled = [];
        foreach (self::REGISTRY as $gateway => $_meta) {
            if (self::config($gateway)['configured']) {
                $enabled[] = $gateway;
            }
        }
        return $enabled;
    }

    /**
     * Gateways that exist but are switched off (missing credentials).
     *
     * @return string[]
     */
    public static function disabled(): array
    {
        $disabled = [];
        foreach (self::REGISTRY as $gateway => $_meta) {
            if (!self::config($gateway)['configured']) {
                $disabled[] = $gateway;
            }
        }
        return $disabled;
    }

    /**
     * The gateway used when the user has not chosen one.
     */
    public static function defaultGateway(): ?string
    {
        $enabled = self::enabled();
        return $enabled[0] ?? null;
    }

    /**
     * Is this gateway safe to offer to a customer right now?
     */
    public static function isEnabled(string $gateway): bool
    {
        $gateway = strtolower(trim($gateway));
        if (!isset(self::REGISTRY[$gateway])) {
            return false;
        }
        return self::config($gateway)['configured'];
    }

    /**
     * The secret key used to sign API calls.
     */
    public static function secretKey(string $gateway): string
    {
        return (string)(self::config(strtolower(trim($gateway)))['secret_key'] ?? '');
    }

    /**
     * The public key handed to the browser SDK.
     */
    public static function publicKey(string $gateway): string
    {
        return (string)(self::config(strtolower(trim($gateway)))['public_key'] ?? '');
    }

    /**
     * Human-readable reason a gateway is unavailable.
     */
    public static function unavailableReason(string $gateway): string
    {
        $cfg = self::config(strtolower(trim($gateway)));
        if ($cfg['configured']) {
            return '';
        }
        $labels = [
            'public'     => 'public key',
            'secret'     => 'secret key',
            'encryption' => 'encryption key',
        ];
        $parts = array_map(
            static fn(string $slot): string => $labels[$slot] ?? $slot,
            $cfg['missing']
        );
        return 'Missing ' . implode(', ', $parts) . '.';
    }

    // ================================================================
    //  Webhooks — one inbound URL per gateway
    // ================================================================

    /**
     * The clean path a gateway should call, e.g. `payment/webhook/flutterwave`.
     */
    public static function webhookPath(string $gateway): string
    {
        $gateway = strtolower(trim($gateway));
        $meta    = self::REGISTRY[$gateway] ?? [];
        return (string)($meta['webhook_path'] ?? ('payment/webhook/' . $gateway));
    }

    /**
     * The absolute webhook URL to paste into a gateway dashboard.
     */
    public static function webhookUrl(string $gateway): string
    {
        $base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
        return $base . '/' . self::webhookPath($gateway);
    }

    /**
     * Every webhook URL on the platform, keyed by gateway.
     *
     * @return array<string,array{url:string,path:string,scheme:string,events:string[],note:string,label:string}>
     */
    public static function webhookUrls(): array
    {
        $out = [];
        foreach (self::REGISTRY as $key => $meta) {
            $out[$key] = [
                'label'  => (string)$meta['label'],
                'url'    => self::webhookUrl($key),
                'path'   => self::webhookPath($key),
                'scheme' => (string)($meta['webhook_scheme'] ?? 'hmac_sha256'),
                'events' => (array)($meta['webhook_events'] ?? []),
                'note'   => (string)($meta['webhook_note'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * The secret used to sign/verify this gateway's webhooks.
     */
    public static function webhookSecret(string $gateway): string
    {
        return trim(self::credential(strtolower(trim($gateway)), 'webhook'));
    }

    /**
     * Read a request header regardless of SAPI.
     */
    public static function headerValue(array $headers, string $name): string
    {
        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === $name) {
                return trim((string)$value);
            }
        }
        return '';
    }

    /**
     * Verify an inbound webhook payload.
     *
     * @param string $gateway  Gateway key
     * @param string $rawBody  Exact raw request body
     * @param array  $headers  Request headers (any case)
     * @return bool True when the payload is authentic
     */
    public static function verifyWebhookSignature(string $gateway, string $rawBody, array $headers): bool
    {
        $gateway = strtolower(trim($gateway));
        $meta    = self::REGISTRY[$gateway] ?? [];
        $scheme  = (string)($meta['webhook_scheme'] ?? 'hmac_sha256');
        $secret  = self::webhookSecret($gateway);

        if ($secret === '') {
            // Without a configured secret nothing can be verified — refuse.
            error_log("Payment webhook rejected: no secret configured for {$gateway}");
            return false;
        }

        if ($scheme === 'paystack') {
            $signature = self::headerValue($headers, 'x-paystack-signature');
            if ($signature === '') {
                return false;
            }
            return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
        }

        if ($scheme === 'flutterwave') {
            $verifHash = self::headerValue($headers, 'verif-hash');
            if ($verifHash !== '' && hash_equals(hash('sha256', $secret), $verifHash)) {
                return true;
            }
            $signature = self::headerValue($headers, 'x-flw-signature');
            if ($signature !== '') {
                return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
            }
            return false;
        }

        // Generic HMAC-SHA256 scheme — inherited by any new gateway.
        $signature = self::headerValue($headers, 'x-adsbooster-signature');
        if ($signature === '') {
            $signature = self::headerValue($headers, 'x-webhook-signature');
        }
        if ($signature === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /**
     * Collect the incoming request headers in a SAPI-independent array.
     */
    public static function requestHeaders(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $raw = getallheaders();
            if (is_array($raw)) {
                $headers = $raw;
            }
        }
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string)$key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr((string)$key, 5)))));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', (string)$key))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}
