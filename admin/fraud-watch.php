<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Fraud Watch';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act = $_POST['act'] ?? '';
        $flagId = (int)($_POST['flag_id'] ?? 0);

        if ($flagId > 0) {
            if ($act === 'review') {
                db()->update('fraud_flags', ['status' => 'reviewed', 'reviewed_at' => date('Y-m-d H:i:s')], 'id=?', [$flagId]);
                set_flash('success', "Flag #{$flagId} marked as reviewed.");
            } elseif ($act === 'dismiss') {
                db()->update('fraud_flags', ['status' => 'dismissed', 'reviewed_at' => date('Y-m-d H:i:s')], 'id=?', [$flagId]);
                set_flash('success', "Flag #{$flagId} dismissed.");
            } elseif ($act === 'suspend_user') {
                $flag = db()->fetchOne("SELECT user_id FROM fraud_flags WHERE id=?", [$flagId]);
                if ($flag) {
                    db()->update('users', ['status' => 'suspended'], 'id=?', [$flag['user_id']]);
                    db()->update('fraud_flags', ['status' => 'reviewed', 'reviewed_at' => date('Y-m-d H:i:s')], 'id=?', [$flagId]);
                    set_flash('success', "User #{$flag['user_id']} suspended due to fraud flag.");
                }
            }
        }
    }
    redirect(url('admin/fraud-watch'));
}

$filterStatus = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if ($filterStatus && in_array($filterStatus, ['flagged','reviewed','dismissed'])) {
    $where .= " AND f.status=?";
    $params[] = $filterStatus;
}

$flags = db()->fetchAll(
    "SELECT f.*, u.full_name, u.email, u.status as user_status
     FROM fraud_flags f LEFT JOIN users u ON u.id = f.user_id
     WHERE {$where} ORDER BY 
        CASE f.severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        f.created_at DESC LIMIT 100",
    $params
);

$statusCounts = [
    'flagged'  => db()->count('fraud_flags', "status='flagged'"),
    'reviewed' => db()->count('fraud_flags', "status='reviewed'"),
    'dismissed' => db()->count('fraud_flags', "status='dismissed'"),
];

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="?" class="btn-admin <?= !$filterStatus ? 'btn-admin-green' : 'btn-admin-outline' ?>">All</a>
    <a href="?status=flagged" class="btn-admin <?= $filterStatus === 'flagged' ? 'btn-admin-green' : 'btn-admin-outline' ?>">Flagged (<?= $statusCounts['flagged'] ?>)</a>
    <a href="?status=reviewed" class="btn-admin <?= $filterStatus === 'reviewed' ? 'btn-admin-green' : 'btn-admin-outline' ?>">Reviewed (<?= $statusCounts['reviewed'] ?>)</a>
    <a href="?status=dismissed" class="btn-admin <?= $filterStatus === 'dismissed' ? 'btn-admin-green' : 'btn-admin-outline' ?>">Dismissed (<?= $statusCounts['dismissed'] ?>)</a>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>User</th><th>Type</th><th>Description</th><th>Severity</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($flags as $f): ?>
                <tr>
                    <td class="text-white fw-bold">#<?= $f['id'] ?></td>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($f['full_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($f['email'] ?? '') ?></div>
                        <span class="badge-status <?= $f['user_status'] ?>" style="font-size:0.65rem;"><?= $f['user_status'] ?></span>
                    </td>
                    <td class="text-white fw-medium"><?= htmlspecialchars($f['flag_type']) ?></td>
                    <td class="text-muted" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.8rem;"><?= htmlspecialchars($f['description'] ?? '') ?></td>
                    <td>
                        <?php
                        $sevClass = match($f['severity'] ?? '') {
                            'high' => 'rejected',
                            'medium' => 'pending',
                            default => '',
                        };
                        ?>
                        <span class="badge-status <?= $sevClass ?>"><?= $f['severity'] ?></span>
                    </td>
                    <td><span class="badge-status <?= $f['status'] ?>"><?= $f['status'] ?></span></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= date('M d, Y', strtotime($f['created_at'])) ?></td>
                    <td>
                        <?php if ($f['status'] === 'flagged'): ?>
                            <div class="d-flex gap-1 flex-wrap">
                                <form method="POST" class="d-inline" onsubmit="return confirm('Mark as reviewed?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="review">
                                    <input type="hidden" name="flag_id" value="<?= $f['id'] ?>">
                                    <button class="btn-admin btn-admin-blue" title="Review"><i class="ph-bold ph-check"></i></button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Dismiss this flag?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="dismiss">
                                    <input type="hidden" name="flag_id" value="<?= $f['id'] ?>">
                                    <button class="btn-admin btn-admin-outline" title="Dismiss"><i class="ph-bold ph-x"></i></button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Suspend this user? This action is serious.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="suspend_user">
                                    <input type="hidden" name="flag_id" value="<?= $f['id'] ?>">
                                    <button class="btn-admin btn-admin-red" title="Suspend User"><i class="ph-bold ph-gavel"></i></button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:0.78rem;"><?= $f['reviewed_at'] ? date('M d, H:i', strtotime($f['reviewed_at'])) : '-' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($flags)): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No fraud flags found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
