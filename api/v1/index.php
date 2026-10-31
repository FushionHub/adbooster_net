<?php declare(strict_types=1);

/**
 * API Router/Dispatcher
 * Adsbooster.net
 *
 * Central entry point for all v1 API requests.
 * Validates API key from Authorization header and routes to the appropriate endpoint.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../../config/db.php';

function jsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function authenticateApiKey(): array
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader)) {
        jsonResponse(401, ['error' => 'Missing Authorization header.']);
    }

    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $apiKey = trim($matches[1]);
    } else {
        jsonResponse(401, ['error' => 'Invalid Authorization format. Use: Bearer <api_key>']);
    }

    $keyHash = hash('sha256', $apiKey);

    $keyRecord = db()->fetchOne(
        "SELECT id, user_id, scopes, last_used_at
         FROM api_keys
         WHERE key_hash = ?",
        [$keyHash]
    );

    if ($keyRecord === null) {
        jsonResponse(401, ['error' => 'Invalid API key.']);
    }

    $user = db()->fetchOne(
        "SELECT id, full_name, email, status FROM users WHERE id = ?",
        [$keyRecord['user_id']]
    );

    if ($user === null || $user['status'] !== 'active') {
        jsonResponse(403, ['error' => 'Account is inactive or suspended.']);
    }

    db()->update('api_keys', [
        'last_used_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$keyRecord['id']]);

    $scopes = json_decode($keyRecord['scopes'] ?? '[]', true) ?: [];

    return [
        'user'   => $user,
        'scopes' => $scopes,
        'key_id' => $keyRecord['id'],
    ];
}

function getRequestBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(400, ['error' => 'Invalid JSON body.']);
    }

    return $decoded;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$uri = rtrim($uri, '/');

$baseApiPath = '/api/v1';
if (strpos($uri, $baseApiPath) === 0) {
    $uri = substr($uri, strlen($baseApiPath));
}

$uri = '/' . ltrim($uri, '/');

$segments = array_values(array_filter(explode('/', $uri)));
$resource = $segments[0] ?? '';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$auth = authenticateApiKey();

switch ($resource) {
    case 'campaigns':
        require __DIR__ . '/campaigns.php';
        break;
    case 'posts':
        require __DIR__ . '/posts.php';
        break;
    case 'credits':
        require __DIR__ . '/credits.php';
        break;
    case 'webhooks':
        require __DIR__ . '/webhooks.php';
        break;
    default:
        jsonResponse(404, [
            'error' => 'Endpoint not found.',
            'available_endpoints' => [
                'GET/POST /campaigns',
                'PUT /campaigns/{id}',
                'GET/POST /posts',
                'DELETE /posts/{id}',
                'GET /credits',
            ],
        ]);
}
