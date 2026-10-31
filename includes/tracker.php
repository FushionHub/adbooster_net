<?php
declare(strict_types=1);

/**
 * Visitor/Device Telemetry Logger
 * Adsbooster.net
 * 
 * Logs IP, User-Agent, browser, OS, device type on every page load.
 * Silently fails on DB error - never blocks page render.
 */

if (php_sapi_name() === 'cli') {
    return;
}

try {
    // Resolve IP through proxy chain
    $ip = $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';

    // Clean IP (take first if comma-separated)
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

    // Parse browser
    $browser = 'Unknown';
    $ua = strtolower($userAgent);
    if (strpos($ua, 'edg/') !== false || strpos($ua, 'edge/') !== false) {
        $browser = 'Edge';
    } elseif (strpos($ua, 'opr/') !== false || strpos($ua, 'opera/') !== false) {
        $browser = 'Opera';
    } elseif (strpos($ua, 'chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($ua, 'firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($ua, 'safari/') !== false) {
        $browser = 'Safari';
    }

    // Parse OS
    $os = 'Unknown';
    if (strpos($ua, 'windows nt 10') !== false) {
        $os = 'Windows 10/11';
    } elseif (strpos($ua, 'windows nt 6.3') !== false) {
        $os = 'Windows 8.1';
    } elseif (strpos($ua, 'windows nt 6.2') !== false) {
        $os = 'Windows 8';
    } elseif (strpos($ua, 'windows') !== false) {
        $os = 'Windows';
    } elseif (strpos($ua, 'mac os x') !== false) {
        $os = 'macOS';
    } elseif (strpos($ua, 'linux') !== false && strpos($ua, 'android') === false) {
        $os = 'Linux';
    } elseif (strpos($ua, 'android') !== false) {
        $os = 'Android';
    } elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) {
        $os = 'iOS';
    }

    // Device type
    $deviceType = preg_match('/mobile|android|iphone|ipad|ipod/i', $userAgent) ? 'mobile' : 'desktop';

    // User ID if logged in
    $userId = $_SESSION['user_id'] ?? null;

    // Insert silently
    db()->insert('visitor_logs', [
        'user_id'     => $userId,
        'ip'          => $ip,
        'user_agent'  => $userAgent,
        'browser'     => $browser,
        'os'          => $os,
        'device_type' => $deviceType,
        'request_uri' => $requestUri,
        'country'     => '', // Can be populated by geo-IP service later
    ]);
} catch (Throwable $e) {
    // Silently fail - never block page render
    error_log("Tracker error: " . $e->getMessage());
}
