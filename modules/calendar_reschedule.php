<?php declare(strict_types=1);

/**
 * AJAX Endpoint: Calendar Reschedule
 * Adsbooster.net
 *
 * Drag-to-reschedule endpoint. Updates scheduled_posts.scheduled_time.
 * Requires user session.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit;
}

$userId = current_user_id();

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body.']);
    exit;
}

$postId = isset($body['post_id']) ? (int) $body['post_id'] : 0;
$newTime = $body['new_time'] ?? '';

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'post_id is required.']);
    exit;
}

if (empty($newTime)) {
    http_response_code(400);
    echo json_encode(['error' => 'new_time is required.']);
    exit;
}

$newTimestamp = strtotime($newTime);
if ($newTimestamp === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format for new_time.']);
    exit;
}

if ($newTimestamp <= time()) {
    http_response_code(400);
    echo json_encode(['error' => 'New time must be in the future.']);
    exit;
}

$post = db()->fetchOne(
    "SELECT id, status FROM scheduled_posts WHERE id = ? AND user_id = ?",
    [$postId, $userId]
);

if ($post === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found.']);
    exit;
}

if (!in_array($post['status'], ['scheduled', 'draft', 'failed'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot reschedule a post with status: ' . $post['status']]);
    exit;
}

db()->update('scheduled_posts', [
    'scheduled_time' => date('Y-m-d H:i:s', $newTimestamp),
    'status'         => 'scheduled',
    'updated_at'     => date('Y-m-d H:i:s'),
], 'id = ?', [$postId]);

echo json_encode([
    'message'       => 'Post rescheduled successfully.',
    'post_id'       => $postId,
    'scheduled_time' => date('Y-m-d H:i:s', $newTimestamp),
], JSON_UNESCAPED_UNICODE);
