<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Support Tickets';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act = $_POST['act'] ?? '';

        if ($act === 'reply') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $message  = trim($_POST['message'] ?? '');
            $attachmentPath = '';
            if (!empty($_FILES['attachment']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','pdf','zip'])) {
                    $filename = 'ticket_' . $ticketId . '_' . time() . '.' . $ext;
                    $uploadDir = __DIR__ . '/../uploads/tickets/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename);
                    $attachmentPath = '/uploads/tickets/' . $filename;
                }
            }
            if ($ticketId > 0 && $message !== '') {
                db()->insert('ticket_messages', [
                    'ticket_id' => $ticketId,
                    'sender_id' => current_user_id(),
                    'message' => $message,
                    'attachment_path' => $attachmentPath ?: null,
                ]);
                db()->update('support_tickets', ['updated_at' => date('Y-m-d H:i:s')], 'id=?', [$ticketId]);
                set_flash('success', 'Reply sent.');
            }
        } elseif ($act === 'update_status') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $status   = $_POST['new_status'] ?? '';
            if ($ticketId > 0 && in_array($status, ['open','in_progress','resolved','closed'])) {
                db()->update('support_tickets', ['status' => $status], 'id=?', [$ticketId]);
                set_flash('success', 'Ticket status updated.');
            }
        } elseif ($act === 'update_priority') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $priority = $_POST['new_priority'] ?? '';
            if ($ticketId > 0 && in_array($priority, ['low','medium','high','urgent'])) {
                db()->update('support_tickets', ['priority' => $priority], 'id=?', [$ticketId]);
                set_flash('success', 'Priority updated.');
            }
        }
    }
    redirect(BASE_URL . '/admin/tickets.php' . (isset($_POST['ticket_id']) ? '?view=' . $_POST['ticket_id'] : ''));
}

$filterStatus = $_GET['status'] ?? '';
$filterType   = $_GET['type'] ?? '';
$where = '1=1';
$params = [];
if ($filterStatus && in_array($filterStatus, ['open','in_progress','resolved','closed'])) {
    $where .= " AND t.status=?";
    $params[] = $filterStatus;
}
if ($filterType && in_array($filterType, ['support','bug_report','billing'])) {
    $where .= " AND t.ticket_type=?";
    $params[] = $filterType;
}

$tickets = db()->fetchAll(
    "SELECT t.*, u.full_name, u.email 
     FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id 
     WHERE {$where} ORDER BY 
        CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
        t.created_at DESC LIMIT 100",
    $params
);

$viewTicket = null;
$viewMessages = [];
if (!empty($_GET['view'])) {
    $vtId = (int)$_GET['view'];
    $viewTicket = db()->fetchOne(
        "SELECT t.*, u.full_name, u.email FROM support_tickets t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?",
        [$vtId]
    );
    if ($viewTicket) {
        $viewMessages = db()->fetchAll(
            "SELECT m.*, u.full_name as sender_name FROM ticket_messages m LEFT JOIN users u ON u.id=m.sender_id WHERE m.ticket_id=? ORDER BY m.created_at ASC",
            [$vtId]
        );
    }
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="?" class="btn-admin <?= !$filterStatus && !$filterType ? 'btn-admin-green' : 'btn-admin-outline' ?>">All</a>
    <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
        <a href="?status=<?= $s ?>" class="btn-admin <?= $filterStatus === $s ? 'btn-admin-green' : 'btn-admin-outline' ?>"><?= ucfirst(str_replace('_',' ',$s)) ?></a>
    <?php endforeach; ?>
    <span class="text-muted" style="font-size:0.85rem;line-height:2;">|</span>
    <?php foreach (['support','bug_report','billing'] as $t): ?>
        <a href="?type=<?= $t ?>" class="btn-admin <?= $filterType === $t ? 'btn-admin-green' : 'btn-admin-outline' ?>"><?= ucfirst(str_replace('_',' ',$t)) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($viewTicket): ?>
    <div class="admin-card mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h6 class="text-white fw-bold mb-1">Ticket #<?= $viewTicket['id'] ?> — <?= htmlspecialchars($viewTicket['subject']) ?></h6>
                <div class="d-flex gap-3 flex-wrap" style="font-size:0.8rem;">
                    <span class="text-muted">User: <?= htmlspecialchars($viewTicket['full_name'] ?? 'N/A') ?></span>
                    <span class="text-muted">Type: <?= $viewTicket['ticket_type'] ?></span>
                    <span class="badge-status <?= $viewTicket['priority'] ?>"><?= $viewTicket['priority'] ?></span>
                    <span class="badge-status <?= $viewTicket['status'] ?>"><?= $viewTicket['status'] ?></span>
                </div>
            </div>
            <a href="?" class="btn-admin btn-admin-outline">Back to List</a>
        </div>

        <div class="d-flex gap-2 mb-3 flex-wrap">
            <form method="POST" class="d-flex gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="update_status">
                <input type="hidden" name="ticket_id" value="<?= $viewTicket['id'] ?>">
                <select name="new_status" class="form-select-admin">
                    <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $viewTicket['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-admin btn-admin-blue">Update Status</button>
            </form>
            <form method="POST" class="d-flex gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="update_priority">
                <input type="hidden" name="ticket_id" value="<?= $viewTicket['id'] ?>">
                <select name="new_priority" class="form-select-admin">
                    <?php foreach (['low','medium','high','urgent'] as $p): ?>
                        <option value="<?= $p ?>" <?= $viewTicket['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-admin btn-admin-yellow">Update Priority</button>
            </form>
        </div>

        <div style="max-height:400px;overflow-y:auto;" class="mb-3">
            <?php foreach ($viewMessages as $msg): ?>
                <div class="p-3 rounded mb-2" style="background:<?= $msg['sender_id'] == $viewTicket['user_id'] ? 'var(--admin-hover)' : 'var(--admin-green-dim)' ?>;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-white fw-medium" style="font-size:0.85rem;"><?= htmlspecialchars($msg['sender_name'] ?? 'Unknown') ?></span>
                        <span class="text-muted" style="font-size:0.7rem;"><?= date('M d, Y H:i', strtotime($msg['created_at'])) ?></span>
                    </div>
                    <div style="font-size:0.85rem;white-space:pre-wrap;"><?= htmlspecialchars($msg['message']) ?></div>
                    <?php if ($msg['attachment_path']): ?>
                        <div class="mt-1"><a href="<?= BASE_URL . $msg['attachment_path'] ?>" target="_blank" class="text-main-600" style="font-size:0.78rem;color:var(--admin-green);"><i class="ph-bold ph-paperclip"></i> Attachment</a></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($viewMessages)): ?>
                <p class="text-muted">No messages yet.</p>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="reply">
            <input type="hidden" name="ticket_id" value="<?= $viewTicket['id'] ?>">
            <div class="mb-2">
                <textarea name="message" class="form-control-admin" rows="3" placeholder="Type your reply..." required></textarea>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="btn-admin btn-admin-outline" style="cursor:pointer;">
                    <i class="ph-bold ph-paperclip"></i> Attach
                    <input type="file" name="attachment" class="d-none" accept=".jpg,.jpeg,.png,.gif,.pdf,.zip">
                </label>
                <button type="submit" class="btn-admin btn-admin-green ms-auto"><i class="ph-bold ph-paper-plane-tilt"></i> Send Reply</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>User</th><th>Subject</th><th>Type</th><th>Priority</th><th>Status</th><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td class="text-white fw-bold">#<?= $t['id'] ?></td>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($t['full_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($t['email'] ?? '') ?></div>
                    </td>
                    <td class="text-white" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($t['subject']) ?></td>
                    <td class="text-capitalize"><?= str_replace('_',' ',$t['ticket_type']) ?></td>
                    <td><span class="badge-status <?= $t['priority'] ?>"><?= $t['priority'] ?></span></td>
                    <td><span class="badge-status <?= $t['status'] ?>"><?= str_replace('_',' ',$t['status']) ?></span></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                    <td>
                        <a href="?view=<?= $t['id'] ?>" class="btn-admin btn-admin-blue"><i class="ph-bold ph-chat-circle"></i> View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No tickets found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
