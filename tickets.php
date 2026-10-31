<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ticket_system.php';

$userId  = (int)current_user_id();
$errors  = [];
$success = '';
$viewTicket = null;
$ticketMessages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $type     = $_POST['ticket_type'] ?? 'general';
            $priority = $_POST['priority'] ?? 'medium';
            $subject  = trim($_POST['subject'] ?? '');
            $message  = trim($_POST['message'] ?? '');
            $attachment = null;

            if (!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'zip'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/zip', 'application/x-zip-compressed'];
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if ($file['size'] <= 5 * 1024 * 1024 && in_array($ext, $allowedExts, true) && in_array($mime, $allowedMimes, true)) {
                    $uploadDir = __DIR__ . '/uploads/tickets';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $filename = 'ticket_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                        $attachment = '/uploads/tickets/' . $filename;
                    }
                } else {
                    $errors[] = 'Attachment must be a valid image, PDF, TXT or ZIP under 5MB.';
                }
            }

            if (empty($errors)) {
                try {
                    $ticketId = TicketSystem::createTicket($userId, $type, $priority, $subject, $message, $attachment);
                    $success = 'Ticket created successfully!';
                } catch (\Throwable $e) {
                    $errors[] = 'Failed to create ticket: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'reply') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $message  = trim($_POST['message'] ?? '');
            $attachment = null;

            if (!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'zip'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/zip', 'application/x-zip-compressed'];
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if ($file['size'] <= 55 * 1024 * 1024 && in_array($ext, $allowedExts, true) && in_array($mime, $allowedMimes, true)) {
                    $uploadDir = __DIR__ . '/uploads/tickets';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $filename = 'reply_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                        $attachment = '/uploads/tickets/' . $filename;
                    }
                } else {
                    $errors[] = 'Attachment must be a valid image, PDF, TXT or ZIP under 55MB.';
                }
            }

            if ($message !== '' && $ticketId > 0) {
                try {
                    TicketSystem::addMessage($ticketId, $userId, $message, $attachment);
                    $success = 'Reply sent.';
                } catch (\Throwable $e) {
                    $errors[] = 'Failed to send reply: ' . $e->getMessage();
                }
            }
        }
    }
}

$viewTicketId = (int)($_GET['view'] ?? 0);
if ($viewTicketId > 0) {
    $viewTicket = db()->fetchOne(
        "SELECT * FROM tickets WHERE id = ? AND user_id = ?",
        [$viewTicketId, $userId]
    );
    if ($viewTicket) {
        $ticketMessages = TicketSystem::getTicketMessages($viewTicketId);
    }
}

$tickets = TicketSystem::getTickets($userId);

$pageTitle = 'Support Tickets — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<style>
    .ticket-section { background: linear-gradient(135deg, #0d0d0d 0%, #1a1a2e 100%); }
    .ticket-card { background: #1a1a1a; border: 1px solid #2a2a2a; transition: border-color .25s; cursor: pointer; }
    .ticket-card:hover { border-color: #1db954; }
    .status-open { background: rgba(29,185,84,.15); color: #1db954; }
    .status-in_progress { background: rgba(59,130,246,.15); color: #3b82f6; }
    .status-waiting { background: rgba(245,158,11,.15); color: #f59e0b; }
    .status-resolved { background: rgba(16,185,129,.15); color: #10b981; }
    .status-closed { background: rgba(107,114,128,.15); color: #6b7280; }
    .priority-low { border-left: 3px solid #6b7280; }
    .priority-medium { border-left: 3px solid #f59e0b; }
    .priority-high { border-left: 3px solid #ef4444; }
    .priority-urgent { border-left: 3px solid #dc2626; }
    .chat-bubble-customer { background: #1db954; color: #000; border-radius: 1rem 1rem .25rem 1rem; }
    .chat-bubble-agent { background: #2a2a2a; color: #fff; border-radius: 1rem 1rem 1rem .25rem; }
</style>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Support Tickets</h1>
            <p class="page-subtitle">Get direct help, open tickets, and track resolution progress.</p>
        </div>
        <div>
            <a href="<?= url('dashboard') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

        <?php if ($success): ?>
            <div class="alert alert-success bg-success-100 text-success-700 border-0 tw-p-4 rounded-lg mb-4"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger bg-danger-100 text-danger-700 border-0 tw-p-4 rounded-lg mb-4">
                <?php foreach ($errors as $err): ?>
                    <p class="mb-0"><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($viewTicket): ?>
            <!-- Ticket Thread View -->
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <a href="<?= url('tickets') ?>" class="text-main-600 small text-decoration-none">
                        <i class="ph-bold ph-arrow-left me-1"></i>Back to Tickets
                    </a>
                    <h4 class="text-white fw-bold mb-0 mt-1"><?= e($viewTicket['subject']) ?></h4>
                    <div class="d-flex gap-2 mt-1">
                        <span class="badge status-<?= e($viewTicket['status']) ?> rounded-pill px-2 py-1"><?= e(ucfirst($viewTicket['status'])) ?></span>
                        <span class="text-white-50 small">Ticket #<?= e($viewTicket['ticket_number']) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-dark-900 border border-neutral-800 rounded-3 p-4 mb-4" style="max-height:500px;overflow-y:auto;">
                <?php foreach ($ticketMessages as $msg): ?>
                    <div class="mb-4 <?= $msg['sender_role'] === 'customer' ? 'text-end' : '' ?>">
                        <div class="d-inline-block <?= $msg['sender_role'] === 'customer' ? 'chat-bubble-customer' : 'chat-bubble-agent' ?> p-3" style="max-width:75%;">
                            <div class="fw-semibold small mb-1" style="opacity:.7;"><?= e($msg['sender_name'] ?? 'Unknown') ?></div>
                            <div><?= nl2br(e($msg['message'])) ?></div>
                            <?php if (!empty($msg['attachment'])): ?>
                                <div class="mt-2">
                                    <a href="<?= e($msg['attachment']) ?>" target="_blank" class="text-decoration-underline small">
                                        <i class="ph-bold ph-paperclip me-1"></i>Attachment
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="small mt-1" style="opacity:.5;"><?= date('M d, Y g:i A', strtotime($msg['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!in_array($viewTicket['status'], ['resolved', 'closed'], true)): ?>
                <div class="bg-dark-900 border border-neutral-800 rounded-3 p-4">
                    <h6 class="text-white fw-bold mb-3">Reply</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="ticket_id" value="<?= (int)$viewTicket['id'] ?>">
                        <div class="mb-3">
                            <textarea id="ticketReplyMessage" name="message" class="form-control bg-dark-800 text-white border-neutral-700" rows="3" placeholder="Type your reply..."></textarea>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <input type="file" name="attachment" class="form-control bg-dark-800 text-white border-neutral-700" style="max-width:250px;" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.zip" onchange="previewFile(this, 'replyPreview')">
                            <div id="replyPreview" class="mt-2"></div>
                            <button type="submit" class="btn btn-main rounded-pill fw-semibold">
                                <i class="ph-bold ph-paper-plane-tilt me-1"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Create Ticket Form -->
            <div class="bg-dark-900 border border-neutral-800 rounded-3 p-4 mb-5">
                <h5 class="text-white fw-bold mb-4"><i class="ph-bold ph-ticket me-2 text-main-600"></i>Create New Ticket</h5>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small">Type</label>
                            <select name="ticket_type" class="form-select bg-dark-800 text-white border-neutral-700" required>
                                <option value="general">General Support</option>
                                <option value="billing">Billing</option>
                                <option value="bug_report">Bug Report</option>
                                <option value="technical">Technical</option>
                                <option value="feature_request">Feature Request</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small">Priority</label>
                            <select name="priority" class="form-select bg-dark-800 text-white border-neutral-700" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white-50 small">Attachment</label>
                            <input type="file" name="attachment" class="form-control bg-dark-800 text-white border-neutral-700" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.zip" onchange="previewFile(this, 'ticketPreview')">
                            <div id="ticketPreview" class="mt-2"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-white-50 small">Subject</label>
                            <input type="text" name="subject" class="form-control bg-dark-800 text-white border-neutral-700" placeholder="Brief description of your issue" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-white-50 small">Message (Rich Text)</label>
                            <textarea id="ticketMessageText" name="message" class="form-control bg-dark-800 text-white border-neutral-700" rows="5" placeholder="Describe your issue in detail..."></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-main rounded-pill fw-semibold px-4">
                                <i class="ph-bold ph-paper-plane-tilt me-1"></i> Submit Ticket
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tickets List -->
            <div class="bg-dark-900 border border-neutral-800 rounded-3 p-4">
                <h5 class="text-white fw-bold mb-4"><i class="ph-bold ph-list-bullets me-2 text-main-600"></i>My Tickets</h5>
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-4">
                        <i class="ph-bold ph-ticket text-white-50" style="font-size:2.5rem;"></i>
                        <p class="text-white-50 mt-2">No tickets yet. Create one above to get help.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <a href="<?= url('tickets') ?>?view=<?= (int)$t['id'] ?>" class="text-decoration-none">
                            <div class="ticket-card rounded-3 p-3 mb-3 priority-<?= e($t['priority']) ?>">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="text-white fw-bold mb-1"><?= e($t['subject']) ?></h6>
                                        <div class="d-flex gap-2 align-items-center">
                                            <span class="badge status-<?= e($t['status']) ?> rounded-pill px-2 py-1 small"><?= e(ucfirst($t['status'])) ?></span>
                                            <span class="text-white-50 small">#<?= e($t['ticket_number']) ?></span>
                                            <span class="text-white-50 small"><?= $t['message_count'] ?? 0 ?> message(s)</span>
                                        </div>
                                    </div>
                                    <span class="text-white-50 small"><?= date('M d', strtotime($t['created_at'])) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ClassicEditor !== 'undefined') {
        const msgEl = document.querySelector('#ticketMessageText');
        if (msgEl) {
            ClassicEditor.create(msgEl, {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
            }).catch(e => console.warn('CKEditor:', e));
        }
        const replyEl = document.querySelector('#ticketReplyMessage');
        if (replyEl) {
            ClassicEditor.create(replyEl, {
                toolbar: ['bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
            }).catch(e => console.warn('CKEditor:', e));
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
