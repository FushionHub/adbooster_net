<?php declare(strict_types=1);

/**
 * API v1 Directory / Discovery Endpoint
 * Adsbooster.net
 *
 * History note: this file used to be a router, but it was unreachable for
 * every resource it named — .htaccess rule 3 internally rewrites
 * /api/v1/<resource> to the existing <resource>.php (each one authenticates
 * itself through bootstrap.php), and requiring webhooks.php — a sender
 * class library, not an endpoint — produced an empty 200. It is now the
 * bare /api/v1 discovery directory listing the real, matching files.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function jsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

jsonResponse(200, [
    'name'    => 'Adsbooster REST API v1',
    'auth'    => 'X-API-Key header (or Authorization: Bearer) — keys in api-settings',
    'limits'  => '60 requests/minute per key, sliding window (X-RateLimit-* headers)',
    // Direct files live beside this index; extensionless /api/v1/<resource>
    // requests are rewritten to them by .htaccess.
    'base'    => '/api/v1/',
    'sdk'     => 'https://adsbooster.net/api/v1/sdk/adsbooster.js',
    'docs'    => 'https://adsbooster.net/developer-docs',
    'resources' => [
        'GET/POST   /campaigns.php   (PUT /campaigns.php?id=N, pause/resume payloads)',
        'GET/POST   /posts.php       (DELETE /posts.php?id=N)',
        'GET/POST   /ugc.php         (limit ≤ 50, AI UGC image generation)',
        'GET        /credits.php     (balance)',
        'GET/POST   /directory.php   (≤ 50, marketplace listings)',
        'GET/POST   /reviews.php     (≤ 50, approved)',
        'GET/POST   /capi-bridge.php (server-side conversion tracking; ?js=1 drop-in script)',
        'GET        /webhooks.php    (list endpoints; ?ping=<registered https url> test ping)',
        'GET        /public-analytics.php (aggregate, no auth, 30 req/min/IP)',
    ],
]);
