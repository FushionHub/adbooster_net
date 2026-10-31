<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/auto_buyer_engine.php';

$pageTitle = 'AI Media Buyer & Automation';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $ruleId = (int)($_POST['rule_id'] ?? 0);

        if ($act === 'toggle_rule' && $ruleId > 0) {
            $rule = db()->fetchOne("SELECT is_active FROM ai_media_buyer_rules WHERE id=?", [$ruleId]);
            if ($rule) {
                $newStatus = $rule['is_active'] ? 0 : 1;
                db()->update('ai_media_buyer_rules', ['is_active' => $newStatus], 'id=?', [$ruleId]);
                set_flash('success', "Rule #{$ruleId} " . ($newStatus ? 'activated' : 'paused') . " successfully.");
            }
        } elseif ($act === 'delete_rule' && $ruleId > 0) {
            db()->delete('ai_media_buyer_rules', 'id=?', [$ruleId]);
            set_flash('success', "Rule #{$ruleId} has been deleted.");
        } elseif ($act === 'run_global_optimization') {
            // Trigger auto buyer evaluation cycle
            try {
                $result = run_auto_buyer_cycle();
                $processed = $result['processed'] ?? 0;
                $actions = $result['actions'] ?? 0;
                set_flash('success', "AI Media Buyer cycle completed. Evaluated {$processed} campaigns, executed {$actions} optimization actions.");
            } catch (Throwable $e) {
                set_flash('error', 'Optimization cycle error: ' . $e->getMessage());
            }
        }
    }
    redirect(url('admin/ai-media-buyer'));
}

// KPI Metrics
$totalRules    = db()->count('ai_media_buyer_rules');
$activeRules   = db()->count('ai_media_buyer_rules', 'is_active = 1');
$totalLogs     = db()->count('ai_media_buyer_logs');
$actionsToday  = db()->count('ai_media_buyer_logs', 'DATE(created_at) = CURDATE()');

// Filter
$tab = $_GET['tab'] ?? 'rules';
$filterMetric = $_GET['metric'] ?? '';

$ruleWhere = '1=1';
$ruleParams = [];
if ($filterMetric) {
    $ruleWhere .= ' AND condition_metric = ?';
    $ruleParams[] = $filterMetric;
}

$rules = db()->fetchAll(
    "SELECT r.*, u.full_name, u.email, c.campaign_name 
     FROM ai_media_buyer_rules r
     LEFT JOIN users u ON u.id = r.user_id
     LEFT JOIN ad_campaigns c ON c.id = r.campaign_id
     WHERE {$ruleWhere}
     ORDER BY r.created_at DESC LIMIT 100",
    $ruleParams
);

$logs = db()->fetchAll(
    "SELECT l.*, u.full_name, c.campaign_name, r.rule_name 
     FROM ai_media_buyer_logs l
     LEFT JOIN users u ON u.id = l.user_id
     LEFT JOIN ad_campaigns c ON c.id = l.campaign_id
     LEFT JOIN ai_media_buyer_rules r ON r.id = l.rule_id
     ORDER BY l.created_at DESC LIMIT 50"
);

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon green"><i class="ph-bold ph-cpu"></i></div>
            <div>
                <div class="card-title">Total Rules</div>
                <div class="card-value"><?= number_format($totalRules) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-play-circle"></i></div>
            <div>
                <div class="card-title">Active Rules</div>
                <div class="card-value"><?= number_format($activeRules) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon purple"><i class="ph-bold ph-lightning"></i></div>
            <div>
                <div class="card-title">All-Time Actions</div>
                <div class="card-value"><?= number_format($totalLogs) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-clock-counter-clockwise"></i></div>
            <div>
                <div class="card-title">Actions Today</div>
                <div class="card-value"><?= number_format($actionsToday) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex gap-2">
        <a href="?tab=rules" class="btn-admin <?= $tab === 'rules' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-sliders"></i> Automation Rules (<?= $totalRules ?>)
        </a>
        <a href="?tab=logs" class="btn-admin <?= $tab === 'logs' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-list-dashes"></i> Execution Audit Logs (<?= $totalLogs ?>)
        </a>
    </div>
    <form method="POST" onsubmit="return confirm('Run global AI media buyer evaluation on all active campaigns right now?');">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="run_global_optimization">
        <button type="submit" class="btn-admin btn-admin-green">
            <i class="ph-bold ph-lightning"></i> Run Global Optimization Cycle
        </button>
    </form>
</div>

<?php if ($tab === 'rules'): ?>
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h6 class="text-white fw-bold mb-0">Configured Optimization Rules</h6>
            <div class="d-flex gap-2">
                <a href="?tab=rules" class="btn-admin btn-admin-outline <?= !$filterMetric ? 'active' : '' ?>">All Metrics</a>
                <a href="?tab=rules&metric=roas" class="btn-admin btn-admin-outline <?= $filterMetric === 'roas' ? 'active' : '' ?>">ROAS</a>
                <a href="?tab=rules&metric=cpa" class="btn-admin btn-admin-outline <?= $filterMetric === 'cpa' ? 'active' : '' ?>">CPA</a>
                <a href="?tab=rules&metric=ctr" class="btn-admin btn-admin-outline <?= $filterMetric === 'ctr' ? 'active' : '' ?>">CTR</a>
                <a href="?tab=rules&metric=spend_without_conv" class="btn-admin btn-admin-outline <?= $filterMetric === 'spend_without_conv' ? 'active' : '' ?>">Zero Conv Spend</a>
            </div>
        </div>

        <?php if (empty($rules)): ?>
            <p class="text-muted py-4 text-center">No media buyer automation rules found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Rule & Campaign</th>
                            <th>Advertiser</th>
                            <th>Trigger Condition</th>
                            <th>Action Type</th>
                            <th>Status</th>
                            <th>Last Triggered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-white"><?= htmlspecialchars($r['rule_name']) ?></div>
                                    <div class="text-muted small">Campaign: <?= htmlspecialchars($r['campaign_name'] ?? 'All / Global') ?></div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($r['full_name'] ?? 'System') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($r['email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-info">
                                        <?= strtoupper($r['condition_metric']) ?> <?= $r['condition_operator'] ?> <?= $r['condition_value'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $actionBadges = [
                                        'pause_campaign' => 'badge-status rejected',
                                        'scale_budget_pct' => 'badge-status active',
                                        'reduce_budget_pct' => 'badge-status warning',
                                        'trigger_creative_refresh' => 'badge-status in_progress',
                                        'notify_only' => 'badge-status pending',
                                    ];
                                    $cls = $actionBadges[$r['action_type']] ?? 'badge-status pending';
                                    ?>
                                    <span class="<?= $cls ?>">
                                        <?= ucwords(str_replace('_', ' ', $r['action_type'])) ?>
                                        <?= $r['action_value'] > 0 ? "({$r['action_value']}%)" : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?= $r['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $r['is_active'] ? 'Active' : 'Paused' ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= $r['last_triggered_at'] ? date('M j, Y H:i', strtotime($r['last_triggered_at'])) : 'Never' ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="toggle_rule">
                                            <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn-admin <?= $r['is_active'] ? 'btn-admin-yellow' : 'btn-admin-green' ?>" title="Toggle Status">
                                                <i class="ph-bold <?= $r['is_active'] ? 'ph-pause' : 'ph-play' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this rule permanently?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_rule">
                                            <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn-admin btn-admin-red" title="Delete Rule">
                                                <i class="ph-bold ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="admin-card">
        <h6 class="text-white fw-bold mb-3">Live Execution Audit Trail</h6>
        <?php if (empty($logs)): ?>
            <p class="text-muted py-4 text-center">No execution log entries recorded yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Campaign & User</th>
                            <th>Action Taken</th>
                            <th>Details & Metrics Snapshot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="text-nowrap small text-muted">
                                    <i class="ph-bold ph-clock"></i> <?= date('M j, Y H:i:s', strtotime($l['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($l['campaign_name'] ?? "Campaign #{$l['campaign_id']}") ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($l['full_name'] ?? 'Advertiser') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-success text-success fw-bold">
                                        <?= htmlspecialchars($l['action_taken']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-white small mb-1"><?= htmlspecialchars($l['details']) ?></div>
                                    <?php if (!empty($l['metrics_snapshot'])): ?>
                                        <code class="small text-muted" style="font-size: 11px;">
                                            <?= htmlspecialchars(is_string($l['metrics_snapshot']) ? $l['metrics_snapshot'] : json_encode($l['metrics_snapshot'])) ?>
                                        </code>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
