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
        ],
        'flutterwave' => [
            'label'  => 'Flutterwave',
            'blurb'  => 'Cards, USSD & bank transfers',
            'icon'   => 'ph-wallet',
            'colour' => 'var(--blue)',
            'requires' => ['public', 'secret'],
        ],
    ];

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
                    default  => '',
                };
            case 'flutterwave':
                return match ($slot) {
                    'public'     => api_key('FLUTTERWAVE_PUBLIC_KEY', 'flutterwave_public_key', ''),
                    'secret'     => (string)(api_key('FLUTTERWAVE_SECRET_KEY', 'flutterwave_secret_key', '')
                        ?: api_key('FLUTTERWAVE_LIVE_KEY', 'flutterwave_live_key', '')),
                    'encryption' => api_key('FLUTTERWAVE_ENCRYPTION_KEY', 'flutterwave_encryption_key', ''),
                    'test'       => api_key('FLUTTERWAVE_TEST_KEY', 'flutterwave_test_key', ''),
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
}
