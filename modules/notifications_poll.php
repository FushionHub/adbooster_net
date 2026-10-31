<?php declare(strict_types=1);

/**
 * AJAX Endpoint: Notification Polling
 * Adsbooster.net
 *
 * Returns JSON with unread_count and latest notifications.
 * Requires user session.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit;
}

$userId = current_user_id();

$unreadCount = db()->count(
    'notifications',
    'user_id = ? AND is_read = 0',
    [$userId]
);

$latestNotifications = db()->fetchAll(
    "SELECT id, type, title, body, link, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 20",
    [$userId]
);

echo json_encode([
    'unread_count'   => $unreadCount,
    'notifications'  => $latestNotifications,
], JSON_UNESCAPED_UNICODE);
