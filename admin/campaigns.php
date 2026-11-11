<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/ThirdPartyGateway.php';

$pageTitle = 'Campaign Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act   = $_POST['act'] ?? '';
        $cId   = (int)($_POST['campaign_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($cId > 0) {
            $validStatuses = ['active', 'paused', 'rejected', 'pending', 'completed'];
            if (in_array($act, $validStatuses)) {
                $camp = db()->fetchOne("SELECT user_id, zernio_campaign_id, status FROM ad_campaigns WHERE id = ?", [$cId]);

                if (!empty($camp['zernio_campaign_id']) && in_array($act, ['active', 'paused'], true)) {
                    // Pause/Resume MUST be confirmed by the Zernio Ad
                    // Engine — enqueue for the worker; the local status is
                    // persisted only when the gateway confirms it.
                    if (!class_exists('AsyncQueue')) {
                        require_once __DIR__ . '/../modules/AsyncQueue.php';
                    }
                    $jobId = AsyncQueue::push(
                        (int)($camp['user_id'] ?? 0),
                        'zernio_toggle_status',
                        [
                            'internal_campaign_id' => $cId,
                            'zernio_campaign_id'   => (string)$camp['zernio_campaign_id'],
                            'target_status'        => $act,
                        ],
                        null,
                        'campaign-toggle:' . $cId
                    );

                    if ($jobId > 0 && $act !== ($camp['status'] ?? '')) {
                        db()->insert('campaign_run_logs', [
                            'campaign_id' => $cId,
                            'action'      => 'admin_' . $act . '_queued',
                            'details'     => $notes ?: ("{$act} request queued for Zernio (job #{$jobId}); admin confirmed it will apply once the gateway verifies it."),
                        ]);
                        set_flash('success', "Campaign #{$cId} {$act} request queued for Zernio (job #{$jobId}) — status is applied on gateway confirmation.");
                        redirect(url('admin/campaigns'));
                    }

                    if ($act === ($camp['status'] ?? '')) {
                        set_flash('success', "Campaign #{$cId} is already {$act}.");
                        redirect(url('admin/campaigns'));
                    }

                    if ($jobId <= 0) {
                        set_flash('error', "Background queue temporarily unavailable — #{$cId} status left unchanged.");
                        redirect(url('admin/campaigns'));
                    }
                }

                db()->update('ad_campaigns', ['status' => $act], 'id=?', [$cId]);
                db()->insert('campaign_run_logs', [
                    'campaign_id' => $cId,
                    'action' => 'admin_' . $act,
                    'details' => $notes ?: "Status changed to {$act} by admin",
                ]);
                set_flash('success', "Campaign #{$cId} status updated to {$act}.");
            }
        }
    }
    redirect(url('admin/campaigns'));
}

$filterStatus = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if ($filterStatus && in_array($filterStatus, ['pending','active','paused','completed','rejected'])) {
    $where .= " AND c.status=?";
    $params[] = $filterStatus;
}

$campaigns = db()->fetchAll(
    "SELECT c.*, u.full_name, u.email 
     FROM ad_campaigns c 
     LEFT JOIN users u ON u.id = c.user_id 
     WHERE {$where} 
     ORDER BY c.created_at DESC LIMIT 100",
    $params
);

$statuses = ['pending', 'active', 'paused', 'completed', 'rejected'];
$statusCounts = [];
foreach ($statuses as $s) {
    $statusCounts[$s] = db()->count('ad_campaigns', "status=?", [$s]);
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="?status=" class="btn-admin <?= !$filterStatus ? 'btn-admin-green' : 'btn-admin-outline' ?>">All (<?= $totalAll = array_sum($statusCounts) ?>)</a>
    <?php foreach ($statuses as $s): ?>
        <a href="?status=<?= $s ?>" class="btn-admin <?= $filterStatus === $s ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <?= ucfirst($s) ?> (<?= $statusCounts[$s] ?>)
        </a>
    <?php endforeach; ?>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Campaign</th>
                    <th>Platform</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td class="text-white fw-bold">#<?= $c['id'] ?></td>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($c['full_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($c['email'] ?? '') ?></div>
                    </td>
                    <td class="text-white" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($c['campaign_name'] ?? 'Untitled') ?>
                    </td>
                    <td><span class="badge-status"><?= $c['campaign_type'] ?></span></td>
                    <td class="text-white"><?= price_fmt((float)$c['budget_amount']) ?></td>
                    <td><span class="badge-status <?= $c['status'] ?>"><?= $c['status'] ?></span></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= dt($c['created_at'], 'M d, Y') ?></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if ($c['status'] === 'pending'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Approve this campaign?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="active">
                                    <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                                    <button class="btn-admin btn-admin-green" title="Approve"><i class="ph-bold ph-check"></i></button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Reject this campaign?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="rejected">
                                    <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                                    <button class="btn-admin btn-admin-red" title="Reject"><i class="ph-bold ph-x"></i></button>
                                </form>
                            <?php elseif ($c['status'] === 'active'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Pause this campaign?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="paused">
                                    <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                                    <button class="btn-admin btn-admin-yellow" title="Pause"><i class="ph-bold ph-pause"></i></button>
                                </form>
                            <?php elseif ($c['status'] === 'paused'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Resume this campaign?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="active">
                                    <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                                    <button class="btn-admin btn-admin-green" title="Resume"><i class="ph-bold ph-play"></i></button>
                                </form>
                            <?php endif; ?>
                            <button class="btn-admin btn-admin-outline" title="Details"
                                    data-bs-toggle="modal" data-bs-target="#campaignModal<?= $c['id'] ?>">
                                <i class="ph-bold ph-info"></i>
                            </button>
                        </div>
                    </td>
                </tr>

                <div class="modal fade" id="campaignModal<?= $c['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content" style="background:var(--admin-card);border:1px solid var(--admin-border);">
                            <div class="modal-header" style="border-bottom:1px solid var(--admin-border);">
                                <h6 class="text-white fw-bold">Campaign #<?= $c['id'] ?> Details</h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-label-admin">Name</div>
                                        <div class="text-white"><?= htmlspecialchars($c['campaign_name']) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-label-admin">Objective</div>
                                        <div class="text-white"><?= htmlspecialchars($c['objective'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-label-admin">Type</div>
                                        <div class="text-white"><?= $c['campaign_type'] ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-label-admin">Budget Type</div>
                                        <div class="text-white"><?= $c['budget_type'] ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-label-admin">Budget</div>
                                        <div class="text-white"><?= price_fmt((float)$c['budget_amount']) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-label-admin">Autopilot</div>
                                        <div class="text-white"><?= $c['autopilot_enabled'] ? 'Yes' : 'No' ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-label-admin">Zernio Ad Reference</div>
                                        <div class="text-white font-monospace small"><?= htmlspecialchars($c['zernio_campaign_id'] ?? 'Not synced') ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-label-admin">Headline</div>
                                        <div class="text-white"><?= htmlspecialchars($c['headline'] ?? '') ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-label-admin">Primary Text</div>
                                        <div class="text-white" style="white-space:pre-wrap;"><?= htmlspecialchars($c['primary_text'] ?? '') ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-label-admin">Destination URL</div>
                                        <div class="text-white"><?= htmlspecialchars($c['destination_url'] ?? '') ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-label-admin">Creative</div>
                                        <?php if (!empty($c['creative_media_url'])): ?>
                                            <img src="<?= htmlspecialchars($c['creative_media_url']) ?>" style="max-width:300px;border-radius:8px;border:1px solid var(--admin-border);">
                                        <?php else: ?>
                                            <div class="text-muted">No creative uploaded</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($campaigns)): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No campaigns found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
