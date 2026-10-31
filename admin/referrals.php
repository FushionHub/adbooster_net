<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Referral Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act = $_POST['act'] ?? '';

        if ($act === 'update_reward') {
            $refId = (int)($_POST['referral_id'] ?? 0);
            $reward = (float)($_POST['reward_credits'] ?? 0);
            if ($refId > 0) {
                db()->update('referrals', ['reward_credits' => $reward], 'id=?', [$refId]);
                set_flash('success', "Referral #{$refId} reward updated to {$reward}.");
            }
        } elseif ($act === 'mark_rewarded') {
            $refId = (int)($_POST['referral_id'] ?? 0);
            if ($refId > 0) {
                db()->update('referrals', ['status' => 'rewarded'], 'id=?', [$refId]);
                set_flash('success', "Referral #{$refId} marked as rewarded.");
            }
        }
    }
    redirect(url('admin/referrals'));
}

$referrals = db()->fetchAll(
    "SELECT r.*, 
            u1.full_name as referrer_name, u1.email as referrer_email,
            u2.full_name as referred_name, u2.email as referred_email
     FROM referrals r
     LEFT JOIN users u1 ON u1.id = r.referrer_id
     LEFT JOIN users u2 ON u2.id = r.referred_id
     ORDER BY r.created_at DESC LIMIT 100"
);

$totalReferrals = count($referrals);
$pendingCount = 0;
$rewardedCount = 0;
$totalRewards = 0;
foreach ($referrals as $r) {
    if ($r['status'] === 'pending') $pendingCount++;
    if ($r['status'] === 'rewarded') $rewardedCount++;
    $totalRewards += (float)$r['reward_credits'];
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-title">Total Referrals</div>
            <div class="card-value"><?= number_format($totalReferrals) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-title">Pending Rewards</div>
            <div class="card-value" style="color:#eab308;"><?= $pendingCount ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-title">Total Rewards Paid</div>
            <div class="card-value" style="color:#22c55e;"><?= number_format($totalRewards) ?></div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>Referrer</th><th>Referred</th><th>Status</th><th>Reward</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($referrals as $r): ?>
                <tr>
                    <td class="text-white fw-bold">#<?= $r['id'] ?></td>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($r['referrer_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($r['referrer_email'] ?? '') ?></div>
                    </td>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($r['referred_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($r['referred_email'] ?? '') ?></div>
                    </td>
                    <td><span class="badge-status <?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                    <td class="text-white fw-bold"><?= number_format((float)$r['reward_credits']) ?></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="POST" class="d-flex gap-1 align-items-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="update_reward">
                                <input type="hidden" name="referral_id" value="<?= $r['id'] ?>">
                                <input type="number" name="reward_credits" class="form-control-admin" style="width:80px;" value="<?= $r['reward_credits'] ?>" min="0">
                                <button class="btn-admin btn-admin-blue" title="Update"><i class="ph-bold ph-check"></i></button>
                            </form>
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="POST" onsubmit="return confirm('Mark as rewarded?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="mark_rewarded">
                                    <input type="hidden" name="referral_id" value="<?= $r['id'] ?>">
                                    <button class="btn-admin btn-admin-green" title="Mark Rewarded"><i class="ph-bold ph-seal-check"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($referrals)): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">No referrals found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
