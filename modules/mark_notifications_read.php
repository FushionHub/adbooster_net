<?php declare(strict_types=1);
require_once __DIR__ . '/../config/site_setting_and_config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = current_user_id();

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    db()->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 'id = ? AND user_id = ?', [$id, $userId]);
    echo json_encode(['success' => true]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 'user_id = ? AND is_read = 0', [$userId]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
