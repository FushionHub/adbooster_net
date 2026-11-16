<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Admin Dashboard';

// ── headline metrics ─────────────────────────────────────────────────────
$totalUsers      = db()->count('users');
$totalCampaigns  = db()->count('ad_campaigns');
$activeCampaigns = db()->count('ad_campaigns', "status='active'");
$totalRevenue    = (float)(db()->fetchOne("SELECT COALESCE(SUM(" . txn_amount_usd_sql() . "),0) as total FROM transactions WHERE status='successful'")['total'] ?? 0);
$openTickets     = db()->count('support_tickets', "status IN ('open','in_progress')");
$pendingTx       = db()->count('transactions', "status='pending'");
$totalFlagged    = db()->count('fraud_flags', "status='flagged'");
$newUsersToday   = db()->count('users', "DATE(created_at) = CURDATE()");
$activeListings  = db()->count('marketplace_items', "status='active'");
$openJobs        = db()->count('jobs', "status='open'");

// ── period comparison (last 30 days vs the 30 before that) ──────────────
function period_count(string $table, string $where, string $from, string $to): int
{
    try {
        return (int)(db()->fetchOne(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE {$where} AND created_at BETWEEN ? AND ?",
            [$from, $to]
        )['c'] ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

$now        = time();
$last30From = date('Y-m-d H:i:s', $now - 30 * 86400);
$last30To   = date('Y-m-d H:i:s', $now);
$prev30From = date('Y-m-d H:i:s', $now - 60 * 86400);
$prev30To   = date('Y-m-d H:i:s', $now - 30 * 86400);

$users30     = period_count('users', '1=1', $last30From, $last30To);
$usersPrev30 = period_count('users', '1=1', $prev30From, $prev30To);
$camp30      = period_count('ad_campaigns', '1=1', $last30From, $last30To);
$campPrev30  = period_count('ad_campaigns', '1=1', $prev30From, $prev30To);

/**
 * Percentage change between two numbers, guarded against divide-by-zero.
 */
function trend(int $current, int $previous): array
{
    if ($previous <= 0) {
        return ['text' => $current > 0 ? 'new' : '—', 'class' => $current > 0 ? 'up' : 'flat'];
    }
    $pct = (($current - $previous) / $previous) * 100;
    $sign = $pct > 0 ? '+' : '';
    return [
        'text'  => $sign . number_format($pct, 1) . '% vs prev 30d',
        'class' => $pct > 0.5 ? 'up' : ($pct < -0.5 ? 'down' : 'flat'),
    ];
}

$userTrend   = trend($users30, $usersPrev30);
$campTrend   = trend($camp30, $campPrev30);

// ── lists ────────────────────────────────────────────────────────────────
$recentTransactions = db()->fetchAll(
    "SELECT t.id, t.user_id, t.type, t.amount, t.currency, t.gateway, t.status, t.created_at, u.full_name
     FROM transactions t
     LEFT JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC
     LIMIT 8"
);

$recentUsers = db()->fetchAll(
    "SELECT id, full_name, email, role, status, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 6"
);

$monthlyRevenue = db()->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(" . txn_amount_usd_sql() . ") AS total
     FROM transactions
     WHERE status='successful'
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month DESC
     LIMIT 6"
);

// Fill missing months so the chart never shows a gap.
$revenueChart = [];
if (!empty($monthlyRevenue)) {
    $byMonth = [];
    foreach ($monthlyRevenue as $row) {
        $byMonth[(string)$row['month']] = (float)$row['total'];
    }
    for ($i = 5; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-{$i} months"));
        $revenueChart[] = [
            'label'  => date('M', strtotime($key . '-01')),
            'value'  => $byMonth[$key] ?? 0.0,
            'month'  => $key,
        ];
    }
}
$chartMax = 0.0;
$chartPeakIndex = -1;
foreach ($revenueChart as $i => $r) {
    if ($r['value'] > $chartMax) {
        $chartMax = $r['value'];
        $chartPeakIndex = $i;
    }
}
if ($chartMax <= 0) {
    $chartMax = 1.0;
    $chartPeakIndex = -1;
}

$recentActivity = db()->fetchAll(
    "SELECT 'campaign' AS type, campaign_name AS detail, status, created_at FROM ad_campaigns
     UNION ALL
     SELECT 'user' AS type, full_name AS detail, status, created_at FROM users
     ORDER BY created_at DESC
     LIMIT 10"
);

require_once __DIR__ . '/sidebar.php';
?>

<!-- ── headline ticker: one strip, hairline-divided cells ─────────────── -->
<section class="kpi-band" aria-label="Headline metrics">
    <div class="kpi-cell">
        <div class="kpi-label">Total users</div>
        <div class="kpi-value"><?= number_format($totalUsers) ?></div>
        <div class="kpi-delta <?= $userTrend['class'] ?>">
            <i class="ph-bold ph-trend-<?= $userTrend['class'] === 'down' ? 'down' : 'up' ?>"></i>
            <?= htmlspecialchars($userTrend['text']) ?>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-label">Campaigns</div>
        <div class="kpi-value"><?= number_format($totalCampaigns) ?></div>
        <div class="kpi-delta <?= $campTrend['class'] ?>">
            <i class="ph-bold ph-trend-<?= $campTrend['class'] === 'down' ? 'down' : 'up' ?>"></i>
            <?= htmlspecialchars($campTrend['text']) ?>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-label">Revenue</div>
        <div class="kpi-value money"><?= price_fmt((float)$totalRevenue) ?></div>
        <div class="kpi-delta flat">
            <?= number_format($activeCampaigns) ?> active campaign<?= $activeCampaigns === 1 ? '' : 's' ?>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-label">Open tickets</div>
        <div class="kpi-value"><?= number_format($openTickets) ?></div>
        <div class="kpi-delta flat">
            <?= number_format($pendingTx) ?> pending transaction<?= $pendingTx === 1 ? '' : 's' ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/fraud-watch" class="kpi-cell">
        <div class="kpi-label">Flagged (fraud)</div>
        <div class="kpi-value <?= $totalFlagged > 0 ? 'alert' : '' ?>"><?= number_format($totalFlagged) ?></div>
        <div class="kpi-delta flat">Needs review</div>
    </a>
    <a href="<?= BASE_URL ?>/admin/users" class="kpi-cell">
        <div class="kpi-label">New users today</div>
        <div class="kpi-value"><?= number_format($newUsersToday) ?></div>
        <div class="kpi-delta flat"><?= number_format($users30) ?> in the last 30 days</div>
    </a>
    <a href="<?= BASE_URL ?>/admin/listings" class="kpi-cell">
        <div class="kpi-label">Active listings</div>
        <div class="kpi-value"><?= number_format($activeListings) ?></div>
        <div class="kpi-delta flat">Marketplace &amp; directory</div>
    </a>
    <a href="<?= BASE_URL ?>/admin/jobs" class="kpi-cell">
        <div class="kpi-label">Open jobs</div>
        <div class="kpi-value"><?= number_format($openJobs) ?></div>
        <div class="kpi-delta flat">Listed on the careers board</div>
    </a>
</section>

<div class="row g-3 mb-3">
    <!-- ── revenue chart ─────────────────────────────────────────────── -->
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="text-white fw-bold mb-1">Revenue — last 6 months</h6>
                    <div class="text-muted" style="font-size:.76rem;">Successful transactions only</div>
                </div>
                <a href="<?= BASE_URL ?>/admin/transactions" class="btn-admin btn-admin-outline">
                    <i class="ph-bold ph-receipt"></i> Transactions
                </a>
            </div>

            <?php if (empty($revenueChart)): ?>
                <div class="admin-empty">
                    <i class="ph-bold ph-chart-bar"></i>
                    <div class="empty-title">No revenue yet</div>
                    <div class="empty-text">Completed payments will chart here automatically.</div>
                </div>
            <?php else: ?>
                <div class="admin-barchart">
                    <?php foreach ($revenueChart as $i => $point): ?>
                        <?php
                        $pct  = $chartMax > 0 ? ($point['value'] / $chartMax) * 100 : 0;
                        $pct  = max($pct, $point['value'] > 0 ? 3 : 2);
                        $peak = ($i === $chartPeakIndex);
                        ?>
                        <div class="bar-col">
                            <div class="bar-value<?= $peak ? ' peak' : '' ?>"><?= price_fmt((float)$point['value']) ?></div>
                            <div class="bar-track">
                                <div class="bar-fill<?= $peak ? ' peak' : ($point['value'] <= 0 ? ' empty' : '') ?>" style="height:<?= number_format($pct, 1) ?>%;"></div>
                            </div>
                            <div class="bar-label<?= $peak ? ' peak' : '' ?>"><?= htmlspecialchars($point['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── activity feed ─────────────────────────────────────────────── -->
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="text-white fw-bold mb-0">Recent activity</h6>
                <span class="badge-status draft">Live</span>
            </div>

            <?php if (empty($recentActivity)): ?>
                <div class="admin-empty">
                    <i class="ph-bold ph-pulse"></i>
                    <div class="empty-title">Nothing happening yet</div>
                    <div class="empty-text">New sign-ups and campaigns appear here in real time.</div>
                </div>
            <?php else: ?>
                <div class="admin-feed">
                    <?php foreach ($recentActivity as $act): ?>
                        <?php
                        $ts   = strtotime((string)$act['created_at']);
                        $when = $ts ? date('M d, H:i', $ts) : '—';
                        $kind = $act['type'] === 'campaign' ? 'is-campaign' : 'is-user';
                        ?>
                        <div class="feed-item <?= $kind ?>">
                            <div class="feed-body">
                                <div class="feed-title"><?= htmlspecialchars((string)$act['detail']) ?></div>
                                <div class="feed-meta"><?= htmlspecialchars(ucfirst((string)$act['type'])) ?></div>
                            </div>
                            <span class="feed-time"><?= htmlspecialchars($when) ?></span>
                            <span class="badge-status <?= htmlspecialchars((string)$act['status']) ?>">
                                <?= htmlspecialchars((string)$act['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ── transactions ──────────────────────────────────────────────── -->
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="text-white fw-bold mb-0">Recent transactions</h6>
                <a href="<?= BASE_URL ?>/admin/transactions" class="btn-admin btn-admin-outline">
                    View all <i class="ph-bold ph-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($recentTransactions)): ?>
                <div class="admin-empty">
                    <i class="ph-bold ph-receipt"></i>
                    <div class="empty-title">No transactions yet</div>
                    <div class="empty-text">Payments, top-ups and refunds will be listed here.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th><th>Type</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentTransactions as $tx): ?>
                            <?php $ts = strtotime((string)$tx['created_at']); ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="avatar"><?= strtoupper(substr((string)($tx['full_name'] ?? 'A'), 0, 1)) ?></div>
                                        <div style="min-width:0;">
                                            <div class="text-white fw-medium text-truncate" style="max-width:170px;">
                                                <?= htmlspecialchars((string)($tx['full_name'] ?? 'Unknown')) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.68rem;font-family:var(--admin-font-mono);">T-<?= str_pad((string)(int)$tx['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars(str_replace('_', ' ', (string)$tx['type'])) ?></td>
                                <td class="num money"><?= txn_money((float)$tx['amount'], $tx['currency'] ?? null) ?></td>
                                <td><?= htmlspecialchars((string)$tx['gateway']) ?></td>
                                <td><span class="badge-status <?= htmlspecialchars((string)$tx['status']) ?>"><?= htmlspecialchars((string)$tx['status']) ?></span></td>
                                <td class="num mono-dim">
                                    <?= $ts ? date('M d, Y', $ts) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── registrations ─────────────────────────────────────────────── -->
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="text-white fw-bold mb-0">Recent registrations</h6>
                <a href="<?= BASE_URL ?>/admin/users" class="btn-admin btn-admin-outline">
                    View all <i class="ph-bold ph-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($recentUsers)): ?>
                <div class="admin-empty">
                    <i class="ph-bold ph-user-plus"></i>
                    <div class="empty-title">No users yet</div>
                </div>
            <?php else: ?>
                <div class="admin-feed">
                    <?php foreach ($recentUsers as $u): ?>
                        <div class="feed-item">
                            <div class="feed-body">
                                <div class="feed-title"><?= htmlspecialchars((string)($u['full_name'] ?? 'N/A')) ?></div>
                                <div class="feed-meta text-truncate"><?= htmlspecialchars((string)$u['email']) ?></div>
                            </div>
                            <span class="badge-status <?= htmlspecialchars((string)$u['status']) ?>">
                                <?= htmlspecialchars((string)$u['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
