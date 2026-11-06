<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Jobs & Talent Bounties Moderation';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $jobId = (int)($_POST['job_id'] ?? 0);

        if ($jobId > 0) {
            $job = db()->fetchOne("SELECT * FROM jobs WHERE id = ?", [$jobId]);
            if ($job) {
                switch ($act) {
                    case 'toggle_status':
                        $newStatus = ($job['status'] === 'open') ? 'closed' : 'open';
                        db()->update('jobs', ['status' => $newStatus], 'id = ?', [$jobId]);
                        set_flash('success', "Job #{$jobId} status updated to {$newStatus}.");
                        break;
                    case 'toggle_featured':
                        $newFeat = empty($job['featured']) ? 1 : 0;
                        db()->update('jobs', ['featured' => $newFeat], 'id = ?', [$jobId]);
                        set_flash('success', "Job #{$jobId} featured status updated.");
                        break;
                    case 'delete':
                        db()->delete('jobs', 'id = ?', [$jobId]);
                        set_flash('success', "Job #{$jobId} deleted.");
                        break;
                }
            }
        }
    }
    redirect(url('admin/jobs'));
}

$jobs = db()->fetchAll(
    "SELECT j.*, u.full_name as author_name, u.email as author_email 
     FROM jobs j 
     JOIN users u ON u.id = j.user_id 
     ORDER BY j.created_at DESC"
);

$jobStats = [
    'total'    => count($jobs),
    'open'     => 0,
    'closed'   => 0,
    'featured' => 0,
];
foreach ($jobs as $j) {
    if (($j['status'] ?? '') === 'open') { $jobStats['open']++; }
    else { $jobStats['closed']++; }
    if (!empty($j['featured'])) { $jobStats['featured']++; }
}

require_once __DIR__ . '/sidebar.php';
?>

<!-- KPI tiles -->
<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat">
            <div class="card-icon cyan"><i class="ph-bold ph-briefcase"></i></div>
            <div class="stat-meta">
                <div class="stat-label">Total Jobs</div>
                <div class="stat-value"><?= number_format($jobStats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat">
            <div class="card-icon green"><i class="ph-bold ph-door-open"></i></div>
            <div class="stat-meta">
                <div class="stat-label">Open roles</div>
                <div class="stat-value"><?= number_format($jobStats['open']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat">
            <div class="card-icon yellow"><i class="ph-bold ph-star"></i></div>
            <div class="stat-meta">
                <div class="stat-label">Featured</div>
                <div class="stat-value"><?= number_format($jobStats['featured']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat">
            <div class="card-icon red"><i class="ph-bold ph-door-closed"></i></div>
            <div class="stat-meta">
                <div class="stat-label">Closed</div>
                <div class="stat-value"><?= number_format($jobStats['closed']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Moderation table -->
<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Employer</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Metrics</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No jobs posted yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td>
                                <a href="<?= url('job-details', ['id' => $j['id']]) ?>" target="_blank" class="text-white fw-bold d-block text-decoration-none">
                                    <?= e($j['title']) ?>
                                </a>
                                <span class="text-muted font-size-12"><?= ucfirst(str_replace('-', ' ', $j['job_type'])) ?> &bull; <?= e($j['location_type']) ?></span>
                            </td>
                            <td><span class="badge-status"><?= e($j['category']) ?></span></td>
                            <td>
                                <span class="text-white fw-semibold d-block"><?= e($j['author_name']) ?></span>
                                <span class="text-muted font-size-12"><?= e($j['author_email']) ?></span>
                            </td>
                            <td>
                                <span class="text-success fw-bold font-size-13">
                                    <?php if (!empty($j['salary_min'])): ?>
                                        <?= price_fmt((float)$j['salary_min']) ?> - <?= price_fmt((float)$j['salary_max']) ?>
                                    <?php else: ?>
                                        Negotiable
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?= $j['status'] ?>"><?= ucfirst($j['status']) ?></span>
                                <?php if (!empty($j['featured'])): ?>
                                    <span class="badge-status pending" style="margin-left:4px;">Featured</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-muted font-size-12"><i class="ph ph-eye me-1"></i><?= (int)$j['views_count'] ?> views</span>
                            </td>
                            <td class="text-end">
                                <form method="POST" class="d-inline-flex gap-1 justify-content-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="job_id" value="<?= $j['id'] ?>">

                                    <button type="submit" name="act" value="toggle_status" class="btn-admin <?= $j['status'] === 'open' ? 'btn-admin-blue' : 'btn-admin-green' ?>" title="Toggle Open/Closed">
                                        <i class="ph <?= $j['status'] === 'open' ? 'ph-pause' : 'ph-play' ?>"></i>
                                    </button>

                                    <button type="submit" name="act" value="toggle_featured" class="btn-admin btn-admin-outline" title="Toggle Featured">
                                        <i class="ph ph-star"></i>
                                    </button>

                                    <button type="submit" name="act" value="delete" class="btn-admin btn-admin-red" onclick="return confirm('Delete this job opportunity permanently?')" title="Delete">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
