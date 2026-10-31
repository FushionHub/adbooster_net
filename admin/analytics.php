<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Platform Analytics';

$totalUsers       = db()->count('users');
$newUsersWeek     = db()->count('users', "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$newUsersMonth    = db()->count('users', "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$totalCampaigns   = db()->count('ad_campaigns');
$activeCampaigns  = db()->count('ad_campaigns', "status='active'");
$totalRevenue     = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE status='successful'")['t'] ?? 0;
$revenueThisMonth = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE status='successful' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")['t'] ?? 0;
$revenueLastMonth = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE status='successful' AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH),'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(),'%Y-%m-01')")['t'] ?? 0;
$totalTickets     = db()->count('support_tickets');
$openTickets      = db()->count('support_tickets', "status IN ('open','in_progress')");
$totalFraudFlags  = db()->count('fraud_flags', "status='flagged'");
$completedTx      = db()->count('transactions', "status='successful'");
$failedTx         = db()->count('transactions', "status='failed'");

$userGrowth = db()->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
     FROM users GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month DESC LIMIT 12"
);

$revenueByMonth = db()->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total, COUNT(*) as tx_count
     FROM transactions WHERE status='successful' 
     GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month DESC LIMIT 12"
);

$campaignByPlatform = db()->fetchAll(
    "SELECT campaign_type, COUNT(*) as count, SUM(budget_amount) as total_budget
     FROM ad_campaigns GROUP BY campaign_type ORDER BY count DESC"
);

$campaignByStatus = db()->fetchAll(
    "SELECT status, COUNT(*) as count FROM ad_campaigns GROUP BY status ORDER BY count DESC"
);

$topUsers = db()->fetchAll(
    "SELECT u.id, u.full_name, u.email, u.ad_credits, 
            COUNT(DISTINCT c.id) as campaign_count,
            COALESCE(SUM(t.amount),0) as total_spent
     FROM users u
     LEFT JOIN ad_campaigns c ON c.user_id = u.id
     LEFT JOIN transactions t ON t.user_id = u.id AND t.status='successful'
     WHERE u.role='user'
     GROUP BY u.id ORDER BY total_spent DESC LIMIT 10"
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
                <div style="font-size:0.72rem;color:var(--admin-text-muted);">+<?= $newUsersWeek ?> this week</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-currency-dollar"></i></div>
            <div>
                <div class="card-title">Total Revenue</div>
                <div class="card-value">$<?= number_format((float)$totalRevenue, 2) ?></div>
                <div style="font-size:0.72rem;color:var(--admin-text-muted);">$<?= number_format((float)$revenueThisMonth, 2) ?> this month</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-megaphone"></i></div>
            <div>
                <div class="card-title">Active Campaigns</div>
                <div class="card-value"><?= $activeCampaigns ?></div>
                <div style="font-size:0.72rem;color:var(--admin-text-muted);"><?= number_format($totalCampaigns) ?> total</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon red"><i class="ph-bold ph-warning-circle"></i></div>
            <div>
                <div class="card-title">Fraud Flags</div>
                <div class="card-value"><?= $totalFraudFlags ?></div>
                <div style="font-size:0.72rem;color:var(--admin-text-muted);"><?= $openTickets ?> open tickets</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-title">New Users (30d)</div>
            <div class="card-value" style="color:#3b82f6;"><?= number_format($newUsersMonth) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-title">Successful Transactions</div>
            <div class="card-value" style="color:#22c55e;"><?= number_format($completedTx) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-title">Failed Transactions</div>
            <div class="card-value" style="color:#ef4444;"><?= number_format($failedTx) ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">Revenue by Month</h6>
            <?php if (empty($revenueByMonth)): ?>
                <p class="text-muted">No revenue data.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>Month</th><th>Revenue</th><th>Transactions</th></tr></thead>
                    <tbody>
                    <?php foreach ($revenueByMonth as $r): ?>
                        <tr>
                            <td class="text-white"><?= $r['month'] ?></td>
                            <td class="text-white fw-bold">$<?= number_format((float)$r['total'], 2) ?></td>
                            <td class="text-muted"><?= $r['tx_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">Campaigns by Platform</h6>
            <?php foreach ($campaignByPlatform as $cp): ?>
                <div class="d-flex align-items-center justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--admin-border);">
                    <div class="text-white text-capitalize fw-medium" style="font-size:0.85rem;"><?= $cp['campaign_type'] ?></div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted" style="font-size:0.78rem;"><?= $cp['count'] ?> campaigns</span>
                        <span class="text-white fw-bold" style="font-size:0.85rem;">$<?= number_format((float)$cp['total_budget'], 2) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($campaignByPlatform)): ?>
                <p class="text-muted">No campaigns.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">User Growth</h6>
            <?php if (empty($userGrowth)): ?>
                <p class="text-muted">No data.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>Month</th><th>New Users</th></tr></thead>
                    <tbody>
                    <?php foreach ($userGrowth as $ug): ?>
                        <tr>
                            <td class="text-white"><?= $ug['month'] ?></td>
                            <td class="text-white fw-bold"><?= $ug['count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">Campaigns by Status</h6>
            <?php foreach ($campaignByStatus as $cs): ?>
                <div class="d-flex align-items-center justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--admin-border);">
                    <span class="badge-status <?= $cs['status'] ?>"><?= $cs['status'] ?></span>
                    <span class="text-white fw-bold"><?= $cs['count'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="text-white fw-bold mb-3">Top Users by Spend</h6>
    <div class="table-responsive">
        <table class="admin-table">
            <thead><tr><th>User</th><th>Campaigns</th><th>Total Spent</th><th>Credits</th></tr></thead>
            <tbody>
            <?php foreach ($topUsers as $tu): ?>
                <tr>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($tu['full_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($tu['email']) ?></div>
                    </td>
                    <td class="text-muted"><?= $tu['campaign_count'] ?></td>
                    <td class="text-white fw-bold">$<?= number_format((float)$tu['total_spent'], 2) ?></td>
                    <td class="text-white"><?= number_format((float)$tu['ad_credits']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($topUsers)): ?>
                <tr><td colspan="4" class="text-muted text-center">No user data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
