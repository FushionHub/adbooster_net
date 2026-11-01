<?php declare(strict_types=1);

/**
 * Public Analytics API (aggregates only, no authentication)
 * Adsbooster.net — GET /api/v1/public-analytics.php
 *
 * Serves the SAME aggregate analytics payload the homepage achievements
 * modal renders: totals, performance ratios, 14-day trend, platform mix and
 * campaign status mix. ZERO user-level data: no emails, names, ids, tokens,
 * credentials or per-user rows ever leave the server through this endpoint.
 *
 * Deliberately NOT routed through api/v1/bootstrap.php — that file runs
 * authenticateApiKey() at include time. Instead this endpoint enforces its
 * own IP-keyed sliding-window rate limit (30 requests/minute/IP).
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: public, max-age=30');
header('Vary: Accept-Encoding');

// ── CORS: public, read-only GET — allow every origin for parses to work,
//    restricted to GET so no credential-bearing writes are ever possible.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Accept, Content-Type');
header('Access-Control-Max-Age: 600');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'GET') {
    http_response_code(405);
    header('Allow: GET, OPTIONS');
    echo json_encode(['success' => false, 'error' => 'MethodNotAllowed', 'message' => 'This endpoint accepts GET only.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/public_stats.php';

const PUBLIC_ANALYTICS_RATE_LIMIT = 30;

/** JSON out + exit */
function paJson(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── IP-keyed sliding-window rate limit (shared api_rate_limits table,
//    key_id = 0 so anonymous traffic never collides with API-key traffic).
try {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $ip = trim(explode(',', (string)$ip)[0]) ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $ip = substr((string)$ip, 0, 45);

    $windowMinute = (int)floor(time() / 60);
    db()->query(
        "INSERT INTO api_rate_limits (key_id, ip_address, window_minute, request_count, updated_at)
         VALUES (0, ?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE request_count = request_count + 1",
        [$ip, $windowMinute]
    );
    $row = db()->fetchOne(
        "SELECT request_count FROM api_rate_limits WHERE key_id = 0 AND ip_address = ? AND window_minute = ?",
        [$ip, $windowMinute]
    );
    $count = (int)($row['request_count'] ?? 1);

    header('X-RateLimit-Limit: ' . (string)PUBLIC_ANALYTICS_RATE_LIMIT);
    header('X-RateLimit-Remaining: ' . (string)max(0, PUBLIC_ANALYTICS_RATE_LIMIT - $count));

    if ($count > PUBLIC_ANALYTICS_RATE_LIMIT) {
        $reset = ($windowMinute + 1) * 60;
        header('Retry-After: ' . (string)max(1, $reset - time()));
        paJson(429, [
            'success'    => false,
            'error'      => 'RateLimitExceeded',
            'message'    => 'Public analytics limit of 30 requests/minute exceeded. Retry shortly.',
            'retry_after'=> max(1, $reset - time()),
        ]);
    }
} catch (\Throwable $e) {
    // Rate limiting is best-effort protection; DB failure must not take the
    // public payload down with it.
}

// ── Payload: aggregates only ──
try {
    $analytics = PublicStats::analytics();
    $homepage  = PublicStats::homepage();
} catch (\Throwable $e) {
    error_log('public-analytics: stats failure: ' . $e->getMessage());
    paJson(503, [
        'success' => false,
        'error'   => 'StatsUnavailable',
        'message' => 'Live statistics are temporarily unavailable. Please retry shortly.',
    ]);
}

paJson(200, [
    'success'   => true,
    'endpoint'  => 'public-analytics',
    'version'   => '1.0',
    'generated' => $analytics['generated_at'],
    'homepage'  => [
        'campaigns'    => $homepage['campaigns'],
        'users'        => $homepage['users'],
        'platforms'    => $homepage['platforms'],
        'success_rate' => $homepage['success_rate'],
        'monitoring'   => $homepage['monitoring'],
    ],
    'analytics' => $analytics,
] + ['_meta' => ['note' => $analytics['note']]]);
