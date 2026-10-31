<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'AI & UGC Creative Studio';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $itemId = (int)($_POST['item_id'] ?? 0);

        if ($act === 'delete_video' && $itemId > 0) {
            db()->delete('ai_video_creatives', 'id=?', [$itemId]);
            set_flash('success', "AI Video Creative #{$itemId} deleted successfully.");
        } elseif ($act === 'delete_creative' && $itemId > 0) {
            db()->delete('ai_creatives', 'id=?', [$itemId]);
            set_flash('success', "Creative asset #{$itemId} removed.");
        } elseif ($act === 'update_video_status' && $itemId > 0) {
            $newStatus = $_POST['status'] ?? 'completed';
            if (in_array($newStatus, ['generating', 'completed', 'failed'])) {
                db()->update('ai_video_creatives', ['status' => $newStatus], 'id=?', [$itemId]);
                set_flash('success', "Video #{$itemId} status set to {$newStatus}.");
            }
        }
    }
    redirect(url('admin/ugc-studio'));
}

// Metrics
$totalVideos   = db()->count('ai_video_creatives');
$totalScripts  = db()->count('ai_creatives');
$completedVids = db()->count('ai_video_creatives', "status = 'completed'");
$totalCredits  = (int)(db()->fetchOne("SELECT (COALESCE((SELECT SUM(credits_used) FROM ai_video_creatives),0) + COALESCE((SELECT SUM(credits_used) FROM ai_creatives),0)) as total")['total'] ?? 0);
$successRate   = $totalVideos > 0 ? round(($completedVids / $totalVideos) * 100, 1) : 100;

$tab = $_GET['tab'] ?? 'videos';
$filterStatus = $_GET['status'] ?? '';

// Video query
$vidWhere = '1=1';
$vidParams = [];
if ($filterStatus && in_array($filterStatus, ['generating', 'completed', 'failed'])) {
    $vidWhere .= " AND v.status = ?";
    $vidParams[] = $filterStatus;
}

$videos = db()->fetchAll(
    "SELECT v.*, u.full_name, u.email 
     FROM ai_video_creatives v
     LEFT JOIN users u ON u.id = v.user_id
     WHERE {$vidWhere}
     ORDER BY v.created_at DESC LIMIT 60",
    $vidParams
);

// Creatives query
$creatives = db()->fetchAll(
    "SELECT c.*, u.full_name, u.email 
     FROM ai_creatives c
     LEFT JOIN users u ON u.id = c.user_id
     ORDER BY c.created_at DESC LIMIT 60"
);

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon purple"><i class="ph-bold ph-video-camera"></i></div>
            <div>
                <div class="card-title">AI Video Renders</div>
                <div class="card-value"><?= number_format($totalVideos) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-magic-wand"></i></div>
            <div>
                <div class="card-title">AI Copy & Scripts</div>
                <div class="card-value"><?= number_format($totalScripts) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-sparkle"></i></div>
            <div>
                <div class="card-title">AI Credits Used</div>
                <div class="card-value"><?= number_format($totalCredits) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon green"><i class="ph-bold ph-check-circle"></i></div>
            <div>
                <div class="card-title">Video Success Rate</div>
                <div class="card-value"><?= $successRate ?>%</div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex gap-2">
        <a href="?tab=videos" class="btn-admin <?= $tab === 'videos' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-film-strip"></i> UGC Video Creatives (<?= $totalVideos ?>)
        </a>
        <a href="?tab=creatives" class="btn-admin <?= $tab === 'creatives' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-pencil-line"></i> Text & Image Creatives (<?= $totalScripts ?>)
        </a>
    </div>
</div>

<?php if ($tab === 'videos'): ?>
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h6 class="text-white fw-bold mb-0">UGC Video Generation Studio Queue</h6>
            <div class="d-flex gap-2">
                <a href="?tab=videos" class="btn-admin btn-admin-outline <?= !$filterStatus ? 'active' : '' ?>">All Statuses</a>
                <a href="?tab=videos&status=completed" class="btn-admin btn-admin-outline <?= $filterStatus === 'completed' ? 'active' : '' ?>">Completed</a>
                <a href="?tab=videos&status=generating" class="btn-admin btn-admin-outline <?= $filterStatus === 'generating' ? 'active' : '' ?>">Generating</a>
                <a href="?tab=videos&status=failed" class="btn-admin btn-admin-outline <?= $filterStatus === 'failed' ? 'active' : '' ?>">Failed</a>
            </div>
        </div>

        <?php if (empty($videos)): ?>
            <p class="text-muted py-4 text-center">No video creative generations found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Creative / Hook</th>
                            <th>Creator / User</th>
                            <th>Specs</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $v): ?>
                            <tr>
                                <td style="max-width: 280px;">
                                    <div class="fw-semibold text-white text-truncate" title="<?= htmlspecialchars($v['hook_text'] ?? '') ?>">
                                        "<?= htmlspecialchars($v['hook_text'] ?? 'Untitled Hook') ?>"
                                    </div>
                                    <div class="text-muted small text-truncate">
                                        <?= htmlspecialchars(substr($v['script_body'] ?? '', 0, 70)) ?>...
                                    </div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($v['full_name'] ?? 'System') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($v['email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-light small">
                                        <?= htmlspecialchars($v['aspect_ratio'] ?? '9:16') ?> | <?= htmlspecialchars($v['avatar_id'] ?? 'Avatar') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusMap = [
                                        'completed' => 'active',
                                        'generating' => 'in_progress',
                                        'failed' => 'rejected'
                                    ];
                                    $cls = $statusMap[$v['status']] ?? 'pending';
                                    ?>
                                    <span class="badge-status <?= $cls ?>"><?= ucfirst($v['status']) ?></span>
                                </td>
                                <td class="text-white small">
                                    <?= $v['duration_seconds'] ? "{$v['duration_seconds']}s" : 'N/A' ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M j, Y', strtotime($v['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php if (!empty($v['video_url'])): ?>
                                            <a href="<?= htmlspecialchars($v['video_url']) ?>" target="_blank" class="btn-admin btn-admin-blue" title="Watch Video">
                                                <i class="ph-bold ph-play"></i>
                                            </a>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this video creative permanently?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_video">
                                            <input type="hidden" name="item_id" value="<?= $v['id'] ?>">
                                            <button type="submit" class="btn-admin btn-admin-red" title="Delete">
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
        <h6 class="text-white fw-bold mb-3">AI Copy, Hooks & Text Prompts</h6>
        <?php if (empty($creatives)): ?>
            <p class="text-muted py-4 text-center">No AI creative text assets found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Prompt</th>
                            <th>Generated Output</th>
                            <th>User</th>
                            <th>Credits</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($creatives as $c): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark border border-primary text-primary">
                                        <?= strtoupper($c['type'] ?? 'TEXT') ?>
                                    </span>
                                </td>
                                <td style="max-width: 250px;">
                                    <div class="small text-white text-truncate" title="<?= htmlspecialchars($c['prompt'] ?? '') ?>">
                                        <?= htmlspecialchars(substr($c['prompt'] ?? '', 0, 80)) ?>...
                                    </div>
                                </td>
                                <td style="max-width: 320px;">
                                    <div class="small text-muted text-truncate" title="<?= htmlspecialchars($c['result_output'] ?? '') ?>">
                                        <?= htmlspecialchars(substr($c['result_output'] ?? '', 0, 100)) ?>...
                                    </div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($c['full_name'] ?? 'System') ?></div>
                                </td>
                                <td class="text-warning small fw-bold">
                                    <?= $c['credits_used'] ?? 1 ?> cr
                                </td>
                                <td class="text-muted small">
                                    <?= date('M j, Y', strtotime($c['created_at'])) ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this creative?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="delete_creative">
                                        <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn-admin btn-admin-red" title="Delete">
                                            <i class="ph-bold ph-trash"></i>
                                        </button>
                                    </form>
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
