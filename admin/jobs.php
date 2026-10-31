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

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="text-white fw-bold mb-1"><i class="ph ph-briefcase text-success me-2"></i> Jobs &amp; Opportunities Moderation</h4>
        <p class="text-muted font-size-13 mb-0">Review, feature, open, or close marketplace opportunities, contracts, and creative bounties.</p>
    </div>
    <div>
        <a href="<?= url('jobs') ?>" target="_blank" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="ph ph-arrow-square-out me-1"></i> Public Jobs Board
        </a>
    </div>
</div>

<div class="card bg-card border-card rounded-4 p-0 overflow-hidden" style="background:#111827; border:1px solid #1e293b;">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead style="background:#0a0f1a; border-bottom:1px solid #1e293b;">
                <tr>
                    <th class="ps-4">Title</th>
                    <th>Category</th>
                    <th>Employer</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Metrics</th>
                    <th class="text-end pe-4">Actions</th>
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
                            <td class="ps-4">
                                <a href="<?= url('job-details', ['id' => $j['id']]) ?>" target="_blank" class="text-white fw-bold d-block text-decoration-none hover-text-main-600">
                                    <?= e($j['title']) ?>
                                </a>
                                <span class="text-white-50 font-size-12"><?= ucfirst(str_replace('-', ' ', $j['job_type'])) ?> &bull; <?= e($j['location_type']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary border-opacity-25 text-white-75 font-size-11">
                                    <?= e($j['category']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-white fw-semibold d-block"><?= e($j['author_name']) ?></span>
                                <span class="text-white-50 font-size-12"><?= e($j['author_email']) ?></span>
                            </td>
                            <td>
                                <span class="text-success fw-bold font-size-13">
                                    <?php if (!empty($j['salary_min'])): ?>
                                        $<?= number_format((float)$j['salary_min']) ?> - $<?= number_format((float)$j['salary_max']) ?>
                                    <?php else: ?>
                                        Negotiable
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $j['status'] === 'open' ? 'bg-success' : 'bg-secondary' ?> font-size-11">
                                    <?= ucfirst($j['status']) ?>
                                </span>
                                <?php if (!empty($j['featured'])): ?>
                                    <span class="badge bg-warning text-dark font-size-10 ms-1">Featured</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-size-12 text-white-50"><i class="ph ph-eye me-1"></i><?= $j['views_count'] ?> views</span>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" class="d-inline-flex gap-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="job_id" value="<?= $j['id'] ?>">

                                    <button type="submit" name="act" value="toggle_status" class="btn btn-sm <?= $j['status'] === 'open' ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-pill" title="Toggle Open/Closed">
                                        <i class="ph <?= $j['status'] === 'open' ? 'ph-pause' : 'ph-play' ?>"></i>
                                    </button>

                                    <button type="submit" name="act" value="toggle_featured" class="btn btn-sm btn-outline-info rounded-pill" title="Toggle Featured">
                                        <i class="ph ph-star"></i>
                                    </button>

                                    <button type="submit" name="act" value="delete" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Delete this job opportunity permanently?')" title="Delete">
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
