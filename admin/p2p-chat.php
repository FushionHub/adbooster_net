<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'P2P Chat (Admin)';

$adminId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $message    = trim($_POST['message'] ?? '');
        if ($receiverId > 0 && $message !== '') {
            db()->insert('p2p_messages', [
                'sender_id' => $adminId,
                'receiver_id' => $receiverId,
                'message' => $message,
                'is_read' => 0,
            ]);
            set_flash('success', 'Message sent.');
        }
    }
    redirect(url('admin/p2p-chat') . '?user=' . ($_POST['receiver_id'] ?? ''));
}

$chatUsers = db()->fetchAll(
    "SELECT u.id, u.full_name, u.email,
            (SELECT COUNT(*) FROM p2p_messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
            (SELECT created_at FROM p2p_messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg_at
     FROM users u
     WHERE u.id != ? AND u.role != 'admin'
     ORDER BY last_msg_at DESC",
    [$adminId, $adminId, $adminId, $adminId]
);

$selectedUser = null;
$messages = [];
if (!empty($_GET['user'])) {
    $uid = (int)$_GET['user'];
    $selectedUser = db()->fetchOne("SELECT id, full_name, email FROM users WHERE id=?", [$uid]);
    if ($selectedUser) {
        db()->query("UPDATE p2p_messages SET is_read=1 WHERE sender_id=? AND receiver_id=?", [$uid, $adminId]);
        $messages = db()->fetchAll(
            "SELECT * FROM p2p_messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC",
            [$adminId, $uid, $uid, $adminId]
        );
    }
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3" style="height:calc(100vh - 160px);">
    <div class="col-md-4">
        <div class="admin-card h-100" style="overflow-y:auto;">
            <h6 class="text-white fw-bold mb-3">Users</h6>
            <?php foreach ($chatUsers as $cu): ?>
                <a href="?user=<?= $cu['id'] ?>" 
                   class="d-flex align-items-center gap-2 p-2 rounded mb-1 text-decoration-none <?= (int)($_GET['user'] ?? 0) === (int)$cu['id'] ? 'bg-opacity-10' : '' ?>"
                   style="background:<?= (int)($_GET['user'] ?? 0) === (int)$cu['id'] ? 'var(--admin-green-dim)' : 'transparent' ?>;">
                    <div class="avatar"><?= strtoupper(substr($cu['full_name']??'U',0,1)) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="text-white fw-medium" style="font-size:0.85rem;"><?= htmlspecialchars($cu['full_name']) ?></div>
                        <div class="text-muted" style="font-size:0.7rem;"><?= $cu['last_msg_at'] ? date('M d, H:i', strtotime($cu['last_msg_at'])) : 'No messages' ?></div>
                    </div>
                    <?php if ($cu['unread_count'] > 0): ?>
                        <span class="badge rounded-pill" style="background:var(--admin-green);color:#000;font-size:0.7rem;padding:2px 8px;"><?= $cu['unread_count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if (empty($chatUsers)): ?>
                <p class="text-muted">No users to chat with.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-8">
        <?php if ($selectedUser): ?>
            <div class="admin-card d-flex flex-column" style="height:calc(100vh - 160px);">
                <div class="d-flex align-items-center gap-2 pb-3 mb-3" style="border-bottom:1px solid var(--admin-border);">
                    <div class="avatar"><?= strtoupper(substr($selectedUser['full_name']??'U',0,1)) ?></div>
                    <div>
                        <div class="text-white fw-bold"><?= htmlspecialchars($selectedUser['full_name']) ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($selectedUser['email']) ?></div>
                    </div>
                </div>
                <div class="flex-grow-1 overflow-auto mb-3" id="chatMessages" style="max-height:400px;">
                    <?php if (empty($messages)): ?>
                        <p class="text-muted text-center mt-4">No messages yet. Start the conversation.</p>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="d-flex mb-2 <?= $msg['sender_id'] == $adminId ? 'justify-content-end' : 'justify-content-start' ?>">
                                <div class="px-3 py-2 rounded-3" style="max-width:70%;background:<?= $msg['sender_id'] == $adminId ? 'var(--admin-green-dim)' : 'var(--admin-hover)' ?>;color:var(--admin-text);font-size:0.85rem;">
                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    <div class="text-muted mt-1" style="font-size:0.65rem;"><?= date('M d, H:i', strtotime($msg['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form method="POST" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="send_message" value="1">
                    <input type="hidden" name="receiver_id" value="<?= $selectedUser['id'] ?>">
                    <input type="text" name="message" class="form-control-admin flex-grow-1" placeholder="Type a message..." required autocomplete="off">
                    <button type="submit" class="btn-admin btn-admin-green"><i class="ph-bold ph-paper-plane-tilt"></i> Send</button>
                </form>
            </div>
            <script>document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;</script>
        <?php else: ?>
            <div class="admin-card h-100 d-flex align-items-center justify-content-center">
                <p class="text-muted">Select a user to start chatting.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
