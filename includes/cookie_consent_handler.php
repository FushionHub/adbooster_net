<?php declare(strict_types=1);

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/site_setting_and_config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'retrieve' || ($action === '' && !empty($_GET['consent_uuid']))) {
    handleRetrieve();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if ($action !== 'save') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit;
}

$consentUuid = $_GET['consent_uuid'] ?? $_POST['consent_uuid'] ?? '';
if (empty($consentUuid)) {
    $consentUuid = bin2hex(random_bytes(16));
}

$consentUuid = filter_var($consentUuid, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$necessary = (int)($_GET['necessary'] ?? $_POST['necessary'] ?? 1);
$preferences = (int)($_GET['preferences'] ?? $_POST['preferences'] ?? 0);
$analytics = (int)($_GET['analytics'] ?? $_POST['analytics'] ?? 0);
$marketing = (int)($_GET['marketing'] ?? $_POST['marketing'] ?? 0);

$ip = '0.0.0.0';
foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
    if (!empty($_SERVER[$key])) {
        $ip = trim(explode(',', $_SERVER[$key])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) break;
        $ip = '0.0.0.0';
    }
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$currentPage = $_SERVER['HTTP_REFERER'] ?? $_SERVER['REQUEST_URI'] ?? '';
$createdAt = date('Y-m-d H:i:s');

try {
    $existing = db()->fetchOne(
        "SELECT id FROM cookie_consents WHERE consent_uuid = ?",
        [$consentUuid]
    );

    if ($existing) {
        db()->update(
            'cookie_consents',
            [
                'necessary'   => $necessary,
                'preferences' => $preferences,
                'analytics'   => $analytics,
                'marketing'   => $marketing,
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
                'current_page'=> $currentPage,
                'updated_at'  => $createdAt,
            ],
            'consent_uuid = ?',
            [$consentUuid]
        );
    } else {
        db()->insert('cookie_consents', [
            'consent_uuid' => $consentUuid,
            'necessary'    => $necessary,
            'preferences'  => $preferences,
            'analytics'    => $analytics,
            'marketing'    => $marketing,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'current_page' => $currentPage,
            'created_at'   => $createdAt,
            'updated_at'   => $createdAt,
        ]);
    }

    echo json_encode([
        'success'      => true,
        'consent_uuid' => $consentUuid,
        'message'      => 'Consent recorded successfully.',
    ]);
    exit;

} catch (\Throwable $e) {
    error_log("Cookie consent save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error.']);
    exit;
}

function handleRetrieve(): void
{
    $consentUuid = $_GET['consent_uuid'] ?? $_POST['consent_uuid'] ?? '';

    if (empty($consentUuid)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'consent_uuid is required.']);
        exit;
    }

    $consentUuid = filter_var($consentUuid, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    try {
        $record = db()->fetchOne(
            "SELECT consent_uuid, necessary, preferences, analytics, marketing, created_at, updated_at FROM cookie_consents WHERE consent_uuid = ?",
            [$consentUuid]
        );

        if (!$record) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No consent found.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'consent' => [
                'consent_uuid' => $record['consent_uuid'],
                'necessary'    => (int) $record['necessary'],
                'preferences'  => (int) $record['preferences'],
                'analytics'    => (int) $record['analytics'],
                'marketing'    => (int) $record['marketing'],
                'created_at'   => $record['created_at'],
                'updated_at'   => $record['updated_at'],
            ],
        ]);
        exit;

    } catch (\Throwable $e) {
        error_log("Cookie consent retrieve error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error.']);
        exit;
    }
}
