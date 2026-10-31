<?php declare(strict_types=1);

require_once __DIR__ . '/../config/site_setting_and_config.php';

header('Content-Type: application/json');

$userId = current_user_id();
if ($userId <= 0) {
    echo json_encode(['messages' => []]);
    exit;
}

$convId = (int)($_GET['conv_id'] ?? 0);
if ($convId <= 0) {
    echo json_encode(['messages' => []]);
    exit;
}

// Verify user is part of the conversation
$conv = db()->fetchOne("SELECT id FROM p2p_conversations WHERE id = ? AND (user_a = ? OR user_b = ?)", [$convId, $userId, $userId]);
if (!$conv) {
    echo json_encode(['messages' => []]);
    exit;
}

// Fetch unread incoming messages for this user in this conversation
$messages = db()->fetchAll(
    "SELECT pm.*, u.full_name as sender_name 
     FROM p2p_messages pm 
     JOIN users u ON pm.sender_id = u.id 
     WHERE pm.conversation_id = ? AND pm.receiver_id = ? AND pm.is_read = 0 
     ORDER BY pm.created_at ASC",
    [$convId, $userId]
);

if (!empty($messages)) {
    db()->update('p2p_messages', ['is_read' => 1], 'conversation_id = ? AND receiver_id = ?', [$convId, $userId]);
}

echo json_encode(['messages' => $messages]);
