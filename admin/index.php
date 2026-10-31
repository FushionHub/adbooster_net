<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Admin Dashboard';

$totalUsers       = db()->count('users');
$totalCampaigns   = db()->count('ad_campaigns');
$totalRevenue     = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE status='successful'")['total'] ?? 0;
$activeCampaigns  = db()->count('ad_campaigns', "status='active'");
$openTickets      = db()->count('support_tickets', "status IN ('open','in_progress')");
$pendingTx        = db()->count('transactions', "status='pending'");
$totalFlagged     = db()->count('fraud_flags', "status='flagged'");
$newUsersToday    = db()->count('users', "DATE(created_at) = CURDATE()");

$recentTransactions = db()->fetchAll(
    "SELECT t.*, u.full_name FROM transactions t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC LIMIT 8"
);

$recentUsers = db()->fetchAll(
    "SELECT id, full_name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 6"
);

$monthlyRevenue = db()->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total 
     FROM transactions WHERE status='successful' 
     GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
     ORDER BY month DESC LIMIT 6"
);

$recentActivity = db()->fetchAll(
    "SELECT 'campaign' as type, campaign_name as detail, status, created_at FROM ad_campaigns 
     UNION ALL 
     SELECT 'user' as type, full_name as detail, status, created_at FROM users 
     ORDER BY created_at DESC LIMIT 10"
);

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon green"><i class="ph-bold ph-users-three"></i></div>
            <div>
                <div class="card-title">Total Users</div>
                <div class="card-value"><?= number_format($totalUsers) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-megaphone"></i></div>
            <div>
                <div class="card-title">Total Campaigns</div>
                <div class="card-value"><?= number_format($totalCampaigns) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-currency-dollar"></i></div>
            <div>
                <div class="card-title">Total Revenue</div>
                <div class="card-value">$<?= number_format((float)$totalRevenue, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon purple"><i class="ph-bold ph-megaphone"></i></div>
            <div>
                <div class="card-title">Active Campaigns</div>
                <div class="card-value"><?= number_format($activeCampaigns) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="card-title">Open Tickets</div>
            <div class="card-value text-warning"><?= $openTickets ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="card-title">Pending Transactions</div>
            <div class="card-value" style="color:#3b82f6"><?= $pendingTx ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="card-title">Flagged (Fraud)</div>
            <div class="card-value" style="color:#ef4444"><?= $totalFlagged ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="card-title">New Users Today</div>
            <div class="card-value" style="color:#22c55e"><?= $newUsersToday ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="text-white fw-bold mb-0">Revenue (Last 6 Months)</h6>
            </div>
            <?php if (empty($monthlyRevenue)): ?>
                <p class="text-muted">No revenue data yet.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>Month</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($monthlyRevenue as $row): ?>
                        <tr>
                            <td class="text-white"><?= $row['month'] ?></td>
                            <td class="text-white fw-bold">$<?= number_format((float)$row['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="text-white fw-bold mb-0">Recent Activity</h6>
            </div>
            <?php if (empty($recentActivity)): ?>
                <p class="text-muted">No activity yet.</p>
            <?php else: ?>
                <div style="max-height:280px;overflow-y:auto;">
                <?php foreach ($recentActivity as $act): ?>
                    <div class="d-flex align-items-center gap-2 mb-2 pb-2" style="border-bottom:1px solid var(--admin-border);">
                        <div class="avatar" style="width:28px;height:28px;font-size:0.65rem;">
                            <?= strtoupper(substr($act['type'],0,1)) ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="text-white" style="font-size:0.8rem;"><?= htmlspecialchars($act['detail']) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?= $act['type'] ?> &middot; <?= date('M d, H:i', strtotime($act['created_at'])) ?></div>
                        </div>
                        <span class="badge-status <?= $act['status'] ?>"><?= $act['status'] ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="text-white fw-bold mb-0">Recent Transactions</h6>
                <a href="<?= BASE_URL ?>/admin/transactions" class="btn-admin btn-admin-outline">View All</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead><tr><th>User</th><th>Type</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTransactions as $tx): ?>
                        <tr>
                            <td class="text-white"><?= htmlspecialchars($tx['full_name'] ?? 'N/A') ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars($tx['type']) ?></td>
                            <td class="text-white fw-bold">$<?= number_format((float)$tx['amount'], 2) ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars($tx['gateway']) ?></td>
                            <td><span class="badge-status <?= $tx['status'] ?>"><?= $tx['status'] ?></span></td>
                            <td class="text-muted" style="font-size:0.78rem;"><?= date('M d, Y', strtotime($tx['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="text-white fw-bold mb-0">Recent Registrations</h6>
                <a href="<?= BASE_URL ?>/admin/users" class="btn-admin btn-admin-outline">View All</a>
            </div>
            <?php foreach ($recentUsers as $u): ?>
                <div class="d-flex align-items-center gap-2 mb-2 pb-2" style="border-bottom:1px solid var(--admin-border);">
                    <div class="avatar"><?= strtoupper(substr($u['full_name']??'A',0,1)) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="text-white fw-medium" style="font-size:0.85rem;"><?= htmlspecialchars($u['full_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                    <span class="badge-status <?= $u['status'] ?>"><?= $u['status'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
