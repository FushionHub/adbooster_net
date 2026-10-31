<?php declare(strict_types=1);

/**
 * REST API v1 Bootstrap, Auth Handler & Rate Limiter
 * Adsbooster.net
 *
 * Unified single Master API Key security, sliding-window rate limiting,
 * and rate-limit headers (X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset).
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ── CORS ──
// The API authenticates with a bearer/header key, never cookies, so this is
// a true public API. Still, only echo an Origin we actually recognise so
// browser-side credential phishing is not facilitated by a blanket `*`.
$apiAllowedOrigins = array_filter(array_map('trim', explode(',', (string)(getenv('API_ALLOWED_ORIGINS') ?: ''))));
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && in_array($origin, $apiAllowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} elseif ($origin === '' ) {
    // Non-browser client (curl, SDK) — no CORS headers required.
} else {
    // Unrecognised origin: no CORS headers means the browser blocks the read.
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

const API_RATE_LIMIT_PER_MINUTE = 60;

function jsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getRequestBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $_POST ?: [];
    }

    return array_merge($_POST, $decoded);
}

/**
 * Authenticate API Key and enforce sliding window rate limiting.
 */
function authenticateApiKey(): array
{
    // 1. Extract API Key from headers or query string
    $apiKey = '';
    
    // Check X-API-Key header
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = trim($_SERVER['HTTP_X_API_KEY']);
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $apiKey = trim($matches[1]);
        }
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (!empty($headers['X-API-Key'])) {
            $apiKey = trim($headers['X-API-Key']);
        } elseif (!empty($headers['Authorization']) && preg_match('/^Bearer\s+(.+)$/i', $headers['Authorization'], $m)) {
            $apiKey = trim($m[1]);
        }
    }

    // Legacy query-string key. Deprecated: keys in URLs leak into access
    // logs, browser history and Referer headers. Accepted only over TLS and
    // only for pre-existing integrations — new clients must use a header.
    if (empty($apiKey) && !empty($_GET['api_key'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if (!$https) {
            jsonResponse(400, [
                'success' => false,
                'error'   => 'InsecureTransport',
                'message' => 'API keys in the query string are only accepted over HTTPS. Send the key in the "X-API-Key" header instead.',
            ]);
        }
        $apiKey = trim((string)$_GET['api_key']);
    }

    if (empty($apiKey)) {
        jsonResponse(401, [
            'success' => false,
            'error'   => 'Unauthorized',
            'message' => 'Missing API Key. Supply "X-API-Key: <key>" or "Authorization: Bearer <key>".'
        ]);
    }

    // 2. Validate API Key against Database
    $shaHash = hash('sha256', $apiKey);
    $keyRecord = db()->fetchOne("SELECT * FROM api_keys WHERE key_hash = ?", [$shaHash]);

    // Backward compatibility prefix check
    if (!$keyRecord) {
        $prefix = substr($apiKey, 0, 10);
        $candidates = db()->fetchAll("SELECT * FROM api_keys WHERE key_prefix = ?", [$prefix]);
        foreach ($candidates as $cand) {
            if (password_verify($apiKey, $cand['key_hash'])) {
                $keyRecord = $cand;
                break;
            }
        }
    }

    if (!$keyRecord || (isset($keyRecord['status']) && $keyRecord['status'] !== 'active')) {
        jsonResponse(401, [
            'success' => false,
            'error'   => 'Unauthorized',
            'message' => 'The provided API key is invalid, inactive, or has been revoked.'
        ]);
    }

    $keyId = (int)$keyRecord['id'];
    $userId = (int)$keyRecord['user_id'];

    // 3. User account validation
    $user = db()->fetchOne(
        "SELECT id, full_name, email, role, status, ad_credits, ai_credits FROM users WHERE id = ?",
        [$userId]
    );

    if (!$user || $user['status'] !== 'active') {
        jsonResponse(403, [
            'success' => false,
            'error'   => 'Forbidden',
            'message' => 'The account associated with this API key is inactive or suspended.'
        ]);
    }

    // 4. IP Allowlist Protection (if configured)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($keyRecord['allowed_ips'])) {
        $allowed = array_filter(array_map('trim', explode(',', $keyRecord['allowed_ips'])));
        if (!empty($allowed) && !in_array($clientIp, $allowed, true)) {
            jsonResponse(403, [
                'success' => false,
                'error'   => 'IP_Forbidden',
                'message' => "Client IP {$clientIp} is not authorized for this API key."
            ]);
        }
    }

    // 5. Rate Limiting Protection (60 requests per minute sliding window)
    $currentMinute = (int)floor(time() / 60);
    $reqCount = 1;

    try {
        db()->query(
            "INSERT INTO api_rate_limits (key_id, ip_address, window_minute, request_count, updated_at) 
             VALUES (?, ?, ?, 1, NOW()) 
             ON DUPLICATE KEY UPDATE request_count = request_count + 1",
            [$keyId, $clientIp, $currentMinute]
        );

        $limitRow = db()->fetchOne(
            "SELECT request_count FROM api_rate_limits WHERE key_id = ? AND window_minute = ?",
            [$keyId, $currentMinute]
        );
        $reqCount = (int)($limitRow['request_count'] ?? 1);
    } catch (\Throwable $e) {}

    $limit = API_RATE_LIMIT_PER_MINUTE;
    $remaining = max(0, $limit - $reqCount);
    $resetTime = ($currentMinute + 1) * 60;

    header("X-RateLimit-Limit: {$limit}");
    header("X-RateLimit-Remaining: {$remaining}");
    header("X-RateLimit-Reset: {$resetTime}");

    if ($reqCount > $limit) {
        $retryAfter = max(1, $resetTime - time());
        header("Retry-After: {$retryAfter}");
        jsonResponse(429, [
            'success'     => false,
            'error'       => 'RateLimitExceeded',
            'message'     => "API rate limit of {$limit} requests/minute exceeded. Please retry in {$retryAfter} seconds.",
            'retry_after' => $retryAfter,
            'limit'       => $limit,
        ]);
    }

    // Touch last used timestamp
    try {
        db()->update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$keyId]);
    } catch (\Throwable $e) {}

    // Master unified scopes: grant all feature capabilities
    $scopes = ['read', 'write', 'ads', 'ai', 'social', 'directory', 'wallet'];

    return [
        'user'      => $user,
        'scopes'    => $scopes,
        'key_id'    => $keyId,
        'rate_info' => [
            'limit'     => $limit,
            'remaining' => $remaining,
            'reset'     => $resetTime,
        ]
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$auth = authenticateApiKey();
