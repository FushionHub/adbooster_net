<?php declare(strict_types=1);

/**
 * LEGACY COMPATIBILITY SHIM — Direct Platform OAuth Is Retired.
 * Adsbooster.net
 *
 * Adsbooster no longer exchanges authorization codes with Meta, TikTok,
 * Twitter/X, LinkedIn, Google Ads, Snapchat, or any other ad network, and it
 * no longer stores any direct platform access tokens. 100% of external
 * platform traffic is routed through two unified third-party gateways:
 *
 *   1. Bundle.social API  -> organic profiles, UGC posting, calendar sync
 *   2. Zernio API         -> paid ad accounts (Meta, TikTok, Google, Snapchat)
 *
 * Any remaining "/oauth-callback.php?platform=X" connect request is
 * transparently translated to the correct gateway connect URL. Real gateway
 * callbacks land on dashboard.php (action=bundle_connected / zernio_connected)
 * or social-connect.php — the two surfaces that own account persistence.
 */

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ThirdPartyGateway.php';

$userId = current_user_id();

// Sanitised platform hint (accepts ?platform= and legacy OAuth state values).
$platform = strtolower(trim((string)($_GET['platform'] ?? ($_GET['state'] ?? ''))));
$platform = preg_replace('~[^a-z0-9_\-]~', '', $platform) ?: '';

$hubUrl = url('social-connect');

// Organic platforms are owned by the Bundle.social gateway.
$organicMap = [
    'meta'            => 'facebook',
    'facebook'        => 'facebook',
    'instagram'       => 'instagram',
    'tiktok'          => 'tiktok',
    'twitter'         => 'twitter',
    'x'               => 'twitter',
    'linkedin'        => 'linkedin',
    'pinterest'       => 'pinterest',
    'youtube'         => 'youtube',
    'google_calendar' => 'google_calendar',
    'google_business' => 'google_business',
];

// Paid advertising platforms are owned by the Zernio gateway.
$adMap = [
    'meta_ads'  => 'meta',
    'snapchat'  => 'snapchat',
    'google_ads' => 'google',
    'google'    => 'google',
];

try {
    if ($platform !== '' && isset($organicMap[$platform])) {
        // Route the connect journey out to Bundle.social.
        $connectUrl = ThirdPartyGateway::getBundleConnectUrl($userId, $organicMap[$platform]);
        if (filter_var($connectUrl, FILTER_VALIDATE_URL)) {
            header('Location: ' . $connectUrl);
            exit;
        }
        set_flash('error', 'Bundle.social gateway returned an invalid connect URL. Please verify the gateway configuration.');
    } elseif ($platform !== '' && isset($adMap[$platform])) {
        // Route the ad-account connect journey out to Zernio.
        $connectUrl = ThirdPartyGateway::getZernioAdConnectUrl($userId, $adMap[$platform]);
        if (filter_var($connectUrl, FILTER_VALIDATE_URL)) {
            header('Location: ' . $connectUrl);
            exit;
        }
        set_flash('error', 'Zernio gateway returned an invalid connect URL. Please verify the gateway configuration.');
    } elseif (isset($_GET['code'], $_GET['state'])) {
        // A stale platform carried through an old developer-app registration.
        set_flash('error', 'Direct social platform authorization is no longer supported. Account connections now flow exclusively through the Bundle.social (organic) and Zernio (ads) gateways — reconnect your channels from the Channel Connections page.');
    }
} catch (\Throwable $e) {
    error_log('oauth-callback shim: ' . $e->getMessage());
    set_flash('error', 'Gateway connection is temporarily unavailable: ' . $e->getMessage());
}

redirect($hubUrl);
