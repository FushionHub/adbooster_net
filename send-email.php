<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/email_service.php';

$userId   = current_user_id();
$userData = current_user();

$pageTitle = 'Email Outreach & Messaging Studio — ' . get_setting('site_name', 'Adsbooster.net');

$errors  = [];
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $recipient = trim(strtolower((string)($_POST['recipient'] ?? '')));
    $subject   = trim((string)($_POST['subject'] ?? ''));
    $message   = trim((string)($_POST['message'] ?? ''));
    $replyTo   = trim((string)($_POST['reply_to'] ?? ($userData['email'] ?? '')));

    // ── Abuse control: this endpoint is an outbound mail relay. Without a
    //    cap it becomes a spam/phishing vector that can blacklist the domain
    //    and create CAN-SPAM / GDPR liability. ──
    // 1. Per-IP, per-account rate limit: 5 messages / 10 minutes.
    if (!check_rate_limit('send_email_' . (int)$userId, 5, 600)) {
        $errors[] = 'You have hit the sending limit. Please wait a few minutes before sending more email.';
    }
    // 2. Daily cap of 25 per account.
    $sentToday = db()->count('email_logs', 'sender_id = ? AND DATE(sent_at) = CURDATE()', [(int)$userId]);
    if ($sentToday >= 25) {
        $errors[] = 'You have reached the daily limit of 25 messages. Your quota resets at midnight UTC.';
    }
    // 3. Verified account only.
    if (($userData['status'] ?? '') !== 'active') {
        $errors[] = 'Your account must be active to send email.';
    }
    // 4. Block obviously bogus / role-based abuse targets & own-domain loops.
    if ($recipient !== '' && str_ends_with($recipient, '@' . parse_url(BASE_URL, PHP_URL_HOST))) {
        $errors[] = 'Please use an external recipient address.';
    }

    if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid recipient email address.';
    }
    if (empty($subject)) {
        $errors[] = 'Subject line is required.';
    }
    if (empty($message)) {
        $errors[] = 'Email message body cannot be empty.';
    }
    if (mb_strlen($subject) > 200) {
        $errors[] = 'Subject line is too long (200 characters max).';
    }
    if (mb_strlen($message) > 20000) {
        $errors[] = 'Message body is too long (20,000 characters max).';
    }

    if (empty($errors)) {
        $res = EmailService::sendDirectEmail(
            $recipient,
            $subject,
            $message,
            $userId,
            $replyTo
        );

        if ($res['success']) {
            $success = "Email successfully dispatched to {$recipient}!";
        } else {
            $errors[] = "Delivery notice: " . $res['message'];
        }
    }
}

// Fetch user's recent email logs
$emailLogs = db()->fetchAll(
    "SELECT * FROM email_logs WHERE sender_id = ? ORDER BY sent_at DESC LIMIT 20",
    [$userId]
);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Email Outreach Studio</h1>
            <p class="page-subtitle">Compose and send professional, branded HTML emails directly to clients, partners, and contacts.</p>
        </div>
        <div>
            <a href="<?= url('dashboard') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="padding:14px 20px;border-radius:12px;margin-bottom:24px;">
            <i class="ph ph-check-circle me-1"></i> <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="padding:14px 20px;border-radius:12px;margin-bottom:24px;">
            <?php foreach ($errors as $err): ?>
                <div><i class="ph ph-warning-circle me-1"></i> <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Email Composer Form (Left) -->
        <div class="col-xl-8 col-lg-7">
            <div class="card-dark" style="padding:28px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                    <div style="width:42px;height:42px;border-radius:10px;background:rgba(200,255,0,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                        <i class="ph ph-envelope-simple" style="font-size:22px;"></i>
                    </div>
                    <div>
                        <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">Compose Branded HTML Email</h4>
                        <span style="font-size:12px;color:var(--fg3);">Powered by PHPMailer HTML Engine</span>
                    </div>
                </div>

                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="recipient" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Recipient Email Address</label>
                            <input type="email" id="recipient" name="recipient" class="form-control" required placeholder="client@example.com" value="<?= e($_POST['recipient'] ?? '') ?>" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px 14px;">
                        </div>
                        <div class="col-md-5">
                            <label for="reply_to" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Reply-To Address</label>
                            <input type="email" id="reply_to" name="reply_to" class="form-control" value="<?= e($userData['email']) ?>" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px 14px;">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Email Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" required placeholder="e.g. Partnership Proposal &amp; Campaign Overview" value="<?= e($_POST['subject'] ?? '') ?>" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px 14px;">
                    </div>

                    <!-- CKEditor 5 Free Message Area -->
                    <div class="mb-4">
                        <label for="message" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Message Content (Rich HTML)</label>
                        <textarea id="message" name="message" rows="8" class="form-control" placeholder="Write your email proposal, update, or message here..."><?= e($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-accent" style="padding:12px 28px;font-weight:700;">
                        <i class="ph ph-paper-plane-tilt me-1"></i> Send HTML Email
                    </button>
                </form>
            </div>
        </div>

        <!-- Info & Tips Column (Right) -->
        <div class="col-xl-4 col-lg-5">
            <div class="card-dark" style="padding:24px;margin-bottom:24px;">
                <h5 style="color:#fff;font-size:15px;font-weight:700;margin-bottom:12px;">Email-First Platform Features</h5>
                <ul style="color:var(--fg2);font-size:13px;line-height:1.7;padding-left:18px;margin:0;">
                    <li>Automated HTML notifications for all campaign &amp; ad status updates</li>
                    <li>Instant 2FA verification codes sent straight to your inbox</li>
                    <li>P2P messaging &amp; dating match alerts dispatched in real time</li>
                    <li>Outbound emails wrapped in Adsbooster dark luxury HTML templates</li>
                    <li>Full delivery audit logging for security compliance</li>
                </ul>
            </div>

            <!-- Email Delivery Logs -->
            <div class="card-dark" style="padding:24px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <h5 style="color:#fff;font-size:15px;font-weight:700;margin:0;">Recent Sent Emails</h5>
                    <span class="badge-status badge-active" style="font-size:11px;"><?= count($emailLogs) ?> Logged</span>
                </div>

                <?php if (empty($emailLogs)): ?>
                    <p style="color:var(--fg3);font-size:12.5px;margin:0;">No emails sent yet from this account.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($emailLogs as $log): ?>
                            <div style="background:#080c14;border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px 14px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
                                    <span style="font-weight:600;color:#fff;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">
                                        <?= htmlspecialchars($log['recipient']) ?>
                                    </span>
                                    <span class="badge-status <?= $log['status'] === 'sent' ? 'badge-active' : 'badge-inactive' ?>" style="font-size:10px;">
                                        <?= ucfirst($log['status']) ?>
                                    </span>
                                </div>
                                <div style="font-size:12px;color:var(--fg2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($log['subject']) ?>
                                </div>
                                <span style="font-size:10.5px;color:var(--fg3);display:block;margin-top:4px;">
                                    <?= date('M d, Y g:i A', strtotime($log['sent_at'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const msgEl = document.querySelector('#message');
    if (msgEl && typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(msgEl, {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
            })
            .catch(err => console.warn('CKEditor email init:', err));
    }
});
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
