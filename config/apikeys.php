<?php declare(strict_types=1);

/**
 * Central API Keys Store — Adsbooster.net
 *
 * Single source of truth for ALL live integration credentials:
 * dual gateways (Bundle.social / Zernio), Google (sign-in identity),
 * AI models (Groq / Gemini / NVIDIA NIM), payments, email/SMTP, scraper.
 *
 * Priority order for every key (see api_key() below):
 *   1. PHP constant defined here (deploy-time override)
 *   2. Environment variable (production-safe)
 *   3. `site_settings` DB row (editable from Admin CMS)
 *
 * To go live: fill the empty '' values below OR set env vars
 * OR update the matching site_settings rows from Admin → Settings.
 */

// ── Unified Third-Party Gateways (Bundle.social & Zernio) ──
defined('BUNDLE_SOCIAL_API_KEY')  or define('BUNDLE_SOCIAL_API_KEY', '719de7d1-1630-4af3-9096-2104628480f2');
defined('BUNDLE_SOCIAL_BASE_URL') or define('BUNDLE_SOCIAL_BASE_URL', 'https://api.bundle.social');
defined('ZERNIO_API_KEY')         or define('ZERNIO_API_KEY', 'sk_7efbae19e9fb589fa98827420d2d6ba833ad3712a192c15df05735645fae6403');
defined('ZERNIO_BASE_URL')        or define('ZERNIO_BASE_URL', 'https://zernio.com/api/v1');

// ── Local development key overrides ──
// `config/keys.local.php` is never web-served (see .htaccess) and is
// git-ignored. It is loaded FIRST so it can fill any blanks below.
$__localKeys = __DIR__ . '/keys.local.php';
if (is_readable($__localKeys)) {
    /** @var array<string,string> $__local */
    $__local = (static function () use ($__localKeys): array {
        $v = (static function () use ($__localKeys) { return require $__localKeys; })();
        return is_array($v) ? $v : [];
    })();
    foreach ($__local as $__k => $__v) {
        if (is_string($__v) && $__v !== '' && !defined($__k)) {
            define($__k, $__v);
        }
    }
    unset($__local, $__k, $__v);
}
unset($__localKeys);

// REMOVED: direct platform OAuth app credentials (META_APP_ID,
// META_APP_SECRET, TIKTOK_CLIENT_KEY/SECRET, SNAPCHAT_CLIENT_ID/SECRET,
// TWITTER_CLIENT_ID/SECRET, LINKEDIN_CLIENT_ID/SECRET). No traditional
// platform OAuth flow exists anymore — 100% of external platform traffic
// flows through the two unified gateways above, and no direct platform
// token is ever stored or exchanged.

// ── Google: login identity only ──
// Google Calendar / Business connections flow through the Bundle.social
// gateway. This client pair is used exclusively for the
// "Sign in with Google" identity flow (users.google_id).
defined('GOOGLE_CLIENT_ID')     or define('GOOGLE_CLIENT_ID', '');
defined('GOOGLE_CLIENT_SECRET') or define('GOOGLE_CLIENT_SECRET', '');

// ── AI Models ──
// SECURITY: never commit live credentials. Leave these empty and supply the
// real values through one of the three supported channels:
//   1. Environment variables (recommended in production)
//   2. `config/keys.local.php` (development only — blocked by .htaccess + .gitignore)
//   3. `site_settings` rows, editable from Admin → Settings
defined('GROQ_API_KEY')       or define('GROQ_API_KEY', '');
defined('GEMINI_API_KEY')     or define('GEMINI_API_KEY', '');
defined('NVIDIA_API_KEY')     or define('NVIDIA_API_KEY', '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');
defined('NVIDIA_BASE_URL')    or define('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1');
defined('NVIDIA_IMAGE_URL')   or define('NVIDIA_IMAGE_URL', 'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-schnell');
defined('NVIDIA_IMAGE_TIMEOUT') or define('NVIDIA_IMAGE_TIMEOUT', 75);

// ── AI Video renderers (optional — the built-in Storyboard Studio needs none) ──
defined('REPLICATE_API_TOKEN') or define('REPLICATE_API_TOKEN', 'r8_Flrivuomj0cbzIKotRZbk0giQltmoQD2iJenM');
defined('FAL_KEY')             or define('FAL_KEY', 'fal_sk_81d1d904797044e09e2c770c49d5ebf9:19ed46ac13f8972d383de492e14fb31d');
defined('RUNWAY_API_KEY')      or define('RUNWAY_API_KEY', '');
// Luma removed — replaced by the FREE keyless Pollinations render route (no constant needed).

// ── Scraper ──
defined('FIRECRAWL_API_KEY') or define('FIRECRAWL_API_KEY', 'fc-13aec1b670b34fc387a45f014ee4666b');

// ── Payments: Paystack (intentionally unconfigured — stays hidden) ──
// PaymentGateways treats a gateway with missing credentials as switched
// off: it disappears from the checkout UI and the payment processor
// refuses it server-side. Enter real keys here (or in Admin → Settings)
// and it activates automatically.
defined('PAYSTACK_PUBLIC_KEY') or define('PAYSTACK_PUBLIC_KEY', '');
defined('PAYSTACK_SECRET_KEY') or define('PAYSTACK_SECRET_KEY', '');
defined('PAYSTACK_TEST_KEY')   or define('PAYSTACK_TEST_KEY', '');
defined('PAYSTACK_LIVE_KEY')   or define('PAYSTACK_LIVE_KEY', '');
// Optional dedicated webhook signing secret. When empty the API secret
// key is used to verify the `x-paystack-signature` HMAC-SHA512 header.
defined('PAYSTACK_WEBHOOK_SECRET') or define('PAYSTACK_WEBHOOK_SECRET', '');

// ── Payments: Flutterwave ──
defined('FLUTTERWAVE_PUBLIC_KEY') or define('FLUTTERWAVE_PUBLIC_KEY', 'FLWPUBK-ddc6f50bb7c5587ffe46326dc4299484-X');
defined('FLUTTERWAVE_SECRET_KEY') or define('FLUTTERWAVE_SECRET_KEY', 'FLWSECK-becf82df5cc2313d31fb26fc3ec0b9db-1a1a5ef151bvt-X');
defined('FLUTTERWAVE_ENCRYPTION_KEY') or define('FLUTTERWAVE_ENCRYPTION_KEY', 'becf82df5cc253e3a267fb3e');
defined('FLUTTERWAVE_TEST_KEY')   or define('FLUTTERWAVE_TEST_KEY', '');
defined('FLUTTERWAVE_LIVE_KEY')   or define('FLUTTERWAVE_LIVE_KEY', '');
// Optional dedicated webhook signing secret. When empty the API secret
// key is used to verify the `verif-hash` / `x-flw-signature` headers.
defined('FLUTTERWAVE_WEBHOOK_SECRET') or define('FLUTTERWAVE_WEBHOOK_SECRET', '');

// ── Email / SMTP (Email-first architecture) ──
defined('SMTP_HOST')  or define('SMTP_HOST', 'mail.adsbooster.net');
defined('SMTP_PORT')  or define('SMTP_PORT', '465');
defined('SMTP_USER')  or define('SMTP_USER', 'support@adsbooster.net');
defined('SMTP_PASS')  or define('SMTP_PASS', 'Ikeotuonye@0');
defined('FROM_EMAIL') or define('FROM_EMAIL', 'support@adsbooster.net');
defined('FROM_NAME')  or define('FROM_NAME', 'Adsbooster.net');

if (!function_exists('api_key')) {
    /**
     * Resolve an integration key with constant → env → DB fallback.
     *
     * @param string $constant  PHP constant name, e.g. 'GROQ_API_KEY'
     * @param string $dbKey     site_settings key, e.g. 'groq_api_key'
     * @param string $default   fallback when nothing is configured
     */
    function api_key(string $constant, string $dbKey = '', string $default = ''): string
    {
        if (defined($constant) && (string)constant($constant) !== '') {
            return (string)constant($constant);
        }
        $env = getenv($constant);
        if (is_string($env) && $env !== '') {
            return $env;
        }
        if ($dbKey !== '' && function_exists('get_setting')) {
            try {
                $v = get_setting($dbKey, '');
                if ($v !== '') return $v;
            } catch (\Throwable $e) { /* DB unavailable — fall through */ }
        }
        return $default;
    }
}

if (!function_exists('integration_status')) {
    /**
     * Live-readiness summary for every integration.
     * Used by ai-settings.php, social hub, billing, admin.
     * @return array<string,array{configured:bool,label:string}>
     */
    function integration_status(): array
    {
        $live = function (string $c, string $db): bool {
            return api_key($c, $db, '') !== '';
        };
        $bundleLive = $live('BUNDLE_SOCIAL_API_KEY', 'bundle_social_api_key');
        $zernioLive = $live('ZERNIO_API_KEY', 'zernio_api_key');
        return [
            // ── Dual gateway architecture: all external platform traffic ──
            'bundle_social'   => ['configured' => $bundleLive, 'label' => 'Bundle.social (Organic Gateway)'],
            'zernio'          => ['configured' => $zernioLive, 'label' => 'Zernio (Ads Gateway)'],
            // Organic channels — provisioned via Bundle.social
            'meta'            => ['configured' => $bundleLive, 'label' => 'Meta / Facebook (via Bundle.social)'],
            'instagram'       => ['configured' => $bundleLive, 'label' => 'Instagram (via Bundle.social)'],
            'tiktok'          => ['configured' => $bundleLive, 'label' => 'TikTok (via Bundle.social)'],
            'google_calendar' => ['configured' => $zernioLive, 'label' => 'Google Calendar (via Zernio)'],
            'google_business' => ['configured' => $bundleLive, 'label' => 'Google Business (via Bundle.social)'],
            'twitter'         => ['configured' => $bundleLive, 'label' => 'Twitter / X (via Bundle.social)'],
            'linkedin'        => ['configured' => $bundleLive, 'label' => 'LinkedIn (via Bundle.social)'],
            // Paid advertising — provisioned via Zernio
            'google_ads'      => ['configured' => $zernioLive, 'label' => 'Google Ads (via Zernio)'],
            'pinterest_ads'   => ['configured' => $zernioLive, 'label' => 'Pinterest Ads (via Zernio)'],
            'groq'            => ['configured' => $live('GROQ_API_KEY', 'groq_api_key'),          'label' => 'Groq (AI text)'],
            'gemini'          => ['configured' => $live('GEMINI_API_KEY', 'gemini_api_key'),      'label' => 'Gemini (AI image)'],
            'nvidia'          => ['configured' => $live('NVIDIA_API_KEY', 'nvidia_api_key'),      'label' => 'NVIDIA NIM (AI fallback)'],
            'paystack'        => ['configured' => $live('PAYSTACK_SECRET_KEY', 'paystack_secret_key') || $live('PAYSTACK_LIVE_KEY', 'paystack_live_key'), 'label' => 'Paystack'],
            'flutterwave'     => ['configured' => $live('FLUTTERWAVE_SECRET_KEY', 'flutterwave_secret_key') || $live('FLUTTERWAVE_LIVE_KEY', 'flutterwave_live_key'), 'label' => 'Flutterwave'],
        ];
    }
}

// ── DEPRECATED REMOVED: oauth_auth_url() ──
// The direct per-platform OAuth authorization URL builder
// (Meta/TikTok/Google/Snapchat/Twitter/LinkedIn) has been removed. Account
// connect journeys route exclusively through ThirdPartyGateway:
//   ThirdPartyGateway::getBundleConnectUrl()   -> organic + calendar
//   ThirdPartyGateway::getZernioAdConnectUrl() -> paid ad accounts

