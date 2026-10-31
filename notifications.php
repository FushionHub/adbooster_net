<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';
$pageTitle = 'Notifications â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';

$userId = current_user_id();

if (isset($_GET['action']) && $_GET['action'] === 'mark_all') {
    db()->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 'user_id = ? AND is_read = 0', [$userId]);
    set_flash('success', 'All notifications marked as read.');
    redirect(url('notifications'));
}

$filter = $_GET['filter'] ?? 'all';
$notifs = [];
try {
    $where = 'WHERE user_id = ?';
    $params = [$userId];
    if ($filter === 'unread') { $where .= ' AND is_read = 0'; }
    elseif ($filter === 'read') { $where .= ' AND is_read = 1'; }
    $notifs = db()->fetchAll("SELECT id, type, title, body, link, is_read, created_at, read_at FROM notifications $where ORDER BY created_at DESC LIMIT 100", $params);
} catch (Throwable $e) {}

$unreadCount = 0;
try {
    $n = db()->fetchOne("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
    $unreadCount = (int)($n['cnt'] ?? 0);
} catch (Throwable $e) {}
?>

<h1 class="page-title">Notifications</h1>
<p class="page-subtitle">Stay on top of your campaigns, messages, and account activity.</p>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
    <div class="tabs-row">
        <a href="?filter=all" class="tab <?= $filter==='all'?'active':'' ?>">All</a>
        <a href="?filter=unread" class="tab <?= $filter==='unread'?'active':'' ?>">Unread <?= $unreadCount > 0 ? "($unreadCount)" : '' ?></a>
        <a href="?filter=read" class="tab <?= $filter==='read'?'active':'' ?>">Read</a>
    </div>
    <?php if ($unreadCount > 0): ?>
    <a href="?action=mark_all" class="btn-outline-dark btn-sm" onclick="return confirm('Mark all notifications as read?')">
        <i class="ph ph-checks"></i> Mark all read
    </a>
    <?php endif; ?>
</div>

<?php if (empty($notifs)): ?>
<div class="card-dark" style="text-align:center;padding:48px 24px">
    <i class="ph ph-bell-slash" style="font-size:48px;color:var(--fg3);display:block;margin-bottom:12px;opacity:.4"></i>
    <p style="font-size:15px;color:var(--fg2);margin:0">No notifications to show.</p>
    <p style="font-size:13px;color:var(--fg3);margin:4px 0 0">You're all caught up!</p>
</div>
<?php else: ?>
<div class="card-dark" style="padding:0;overflow:hidden">
    <?php foreach ($notifs as $i => $n):
        $_nType = match($n['type'] ?? 'info') { 'success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'payment' => 'success', 'campaign' => 'info', 'referral' => 'purple', default => 'info' };
        $_nIcon = match($n['type'] ?? 'info') { 'success' => 'ph-check-circle', 'warning' => 'ph-warning', 'error' => 'ph-x-circle', 'payment' => 'ph-currency-dollar', 'campaign' => 'ph-megaphone', 'referral' => 'ph-share-network', default => 'ph-bell' };
        $_timeAgo = (function_exists('time_ago')) ? time_ago($n['created_at']) : date('M d, Y \a\t g:i A', strtotime($n['created_at']));
        $isLast = ($i === count($notifs) - 1);
    ?>
    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" data-id="<?= $n['id'] ?>" onclick="markNotifReadPage(<?= $n['id'] ?>, this)" style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;gap:14px;cursor:pointer;transition:background .15s;<?= $n['is_read'] ? '' : 'background:rgba(200,255,0,.03);' ?><?= $isLast ? 'border-bottom:none;' : '' ?>">
        <div class="notif-icon <?= $_nType ?>" style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px">
            <i class="ph <?= $_nIcon ?>"></i>
        </div>
        <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div>
                    <p style="font-size:14px;font-weight:600;color:var(--fg);margin:0 0 4px"><?= e($n['title'] ?? 'Notification') ?></p>
                    <p style="font-size:13px;color:var(--fg2);margin:0;line-height:1.5"><?= e($n['body'] ?? '') ?></p>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                    <?php if (!$n['is_read']): ?>
                    <span style="width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0"></span>
                    <?php endif; ?>
                    <span style="font-size:12px;color:var(--fg3);white-space:nowrap"><?= $_timeAgo ?></span>
                </div>
            </div>
            <?php if (!empty($n['link'])): ?>
            <a href="<?= e($n['link']) ?>" style="font-size:12px;font-weight:500;color:var(--accent);display:inline-block;margin-top:6px" onclick="event.stopPropagation()">View details â†’</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function markNotifReadPage(id, el){
    if(el) el.style.background = 'transparent';
    fetch('<?= BASE_URL ?>/modules/mark_notifications_read.php?id=' + id, {method:'POST'})
    .then(function(r){return r.json();})
    .then(function(d){
        if(d.success && el){
            var dot = el.querySelector('span[style*="background:var(--accent)"]');
            if(dot) dot.remove();
        }
    }).catch(function(){});
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
