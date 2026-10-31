<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId = current_user_id();
$user = current_user();

$errors = [];
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $title           = trim($_POST['title'] ?? '');
    $category        = trim($_POST['category'] ?? 'Media Buying & Ads Scaling');
    $jobType         = in_array($_POST['job_type'] ?? '', ['contract', 'freelance', 'bounty', 'full-time', 'part-time'], true) ? $_POST['job_type'] : 'contract';
    $locationType    = in_array($_POST['location_type'] ?? '', ['remote', 'hybrid', 'on-site'], true) ? $_POST['location_type'] : 'remote';
    $location        = trim($_POST['location'] ?? 'Worldwide / Remote');
    $salaryMin       = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
    $salaryMax       = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;
    $salaryPeriod    = in_array($_POST['salary_period'] ?? '', ['hourly', 'project', 'monthly', 'yearly'], true) ? $_POST['salary_period'] : 'project';
    $description     = trim($_POST['description'] ?? '');
    $requirements    = trim($_POST['requirements'] ?? '');
    $skillsRequired  = trim($_POST['skills_required'] ?? '');

    if ($title === '') {
        $errors[] = 'Please provide a descriptive opportunity title.';
    }
    if ($description === '') {
        $errors[] = 'Project scope / job description is required.';
    }

    if (empty($errors)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        $slug .= '-' . time();

        $jobId = db()->insert('jobs', [
            'user_id'            => $userId,
            'title'              => $title,
            'slug'               => $slug,
            'category'           => $category,
            'job_type'           => $jobType,
            'location_type'      => $locationType,
            'location'           => $location,
            'salary_min'         => $salaryMin,
            'salary_max'         => $salaryMax,
            'salary_currency'    => 'USD',
            'salary_period'      => $salaryPeriod,
            'description'        => $description,
            'requirements'       => $requirements,
            'skills_required'    => $skillsRequired,
            'status'             => 'open',
            'featured'           => 1,
            'views_count'        => 0,
            'applications_count' => 0,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        set_flash('success', "Opportunity '{$title}' posted successfully!");
        redirect(url('job-details', ['id' => $jobId]));
    }
}

$pageTitle = 'Post Job, Bounty or Creative Project â€” ' . get_setting('site_name', 'Adsbooster.net');
$_appPage = 'jobs';
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-title text-white fw-bold mb-1">
                <i class="ph ph-briefcase text-accent me-2"></i> Post an Opportunity or Bounty
            </h1>
            <p class="text-muted mb-0 font-size-14">Recruit top-tier media buyers, UGC video editors, copywriters, or growth experts.</p>
        </div>
        <div>
            <a href="<?= url('jobs') ?>" class="btn btn-outline-light rounded-pill px-3 font-size-13">
                <i class="ph ph-arrow-left me-1"></i> Back to Marketplace
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger rounded-4 p-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card-dark rounded-4 p-4 p-md-5 max-w-900-px" style="background: #0d1524 !important; border: 1px solid rgba(255,255,255,0.08) !important; color: #fff;">
        <form method="POST">
            <?= csrf_field() ?>

            <!-- Title -->
            <div class="mb-4">
                <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Job / Project Title <span class="text-danger">*</span></label>
                <input type="text" name="title" required class="form-control" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;" placeholder="e.g. Senior Meta & TikTok Media Buyer for D2C Brand ($100k/mo spend)" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <!-- Category & Contract Type -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;">
                        <option value="Media Buying & Ads Scaling">Media Buying &amp; Ads Scaling</option>
                        <option value="UGC Video & Reel Creation">UGC Video &amp; Reel Creation</option>
                        <option value="Ad Copywriting & Scripts">Ad Copywriting &amp; Scripts</option>
                        <option value="TikTok & Meta Optimization">TikTok &amp; Meta Optimization</option>
                        <option value="SEO & Content Marketing">SEO &amp; Content Marketing</option>
                        <option value="Creative & Motion Graphics">Creative &amp; Motion Graphics</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Engagement Type</label>
                    <select name="job_type" class="form-select" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;">
                        <option value="contract">Fixed Contract</option>
                        <option value="freelance">Freelance Retainer</option>
                        <option value="bounty">Project Bounty / Milestone</option>
                        <option value="full-time">Full-Time Placement</option>
                    </select>
                </div>
            </div>

            <!-- Budget & Period -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Min Budget ($ USD)</label>
                    <input type="number" step="1" name="salary_min" class="form-control" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;" placeholder="1000" value="<?= htmlspecialchars($_POST['salary_min'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Max Budget ($ USD)</label>
                    <input type="number" step="1" name="salary_max" class="form-control" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;" placeholder="3500" value="<?= htmlspecialchars($_POST['salary_max'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Billing Frequency</label>
                    <select name="salary_period" class="form-select" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;">
                        <option value="project">Per Project</option>
                        <option value="monthly">Monthly Retainer</option>
                        <option value="hourly">Hourly Rate</option>
                    </select>
                </div>
            </div>

            <!-- Location & Remote -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Workplace Mode</label>
                    <select name="location_type" class="form-select" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;">
                        <option value="remote">100% Remote</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="on-site">On-Site</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Location / Timezone</label>
                    <input type="text" name="location" class="form-control" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;" placeholder="e.g. US / EU Timezones or Worldwide" value="<?= htmlspecialchars($_POST['location'] ?? 'Worldwide / Remote') ?>">
                </div>
            </div>

            <!-- Description -->
            <div class="mb-4">
                <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Scope of Work &amp; Project Description <span class="text-danger">*</span></label>
                <textarea name="description" rows="5" required class="form-control" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;" placeholder="Explain the project goals, deliverables, current ROAS/spend, and expected outcomes..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- Requirements -->
            <div class="mb-4">
                <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Specific Requirements / Portfolio Prerequisites</label>
                <textarea name="requirements" rows="3" class="form-control" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;" placeholder="e.g. Proven track record managing >$30k/mo ad spend, sample UGC videos, case studies..."><?= htmlspecialchars($_POST['requirements'] ?? '') ?></textarea>
            </div>

            <!-- Skills -->
            <div class="mb-5">
                <label class="form-label fw-semibold" style="color:#e2e8f0 !important;">Required Skills &amp; Tools (comma-separated)</label>
                <input type="text" name="skills_required" class="form-control" style="background:#080c14 !important; border:1px solid rgba(255,255,255,0.15) !important; color:#fff !important; padding:12px;" placeholder="e.g. Meta Ads, TikTok Ads Manager, CapCut, Premiere, ROAS Scaling, UGC" value="<?= htmlspecialchars($_POST['skills_required'] ?? '') ?>">
            </div>

            <div class="d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-accent rounded-pill px-5 py-2 fw-bold text-dark font-size-15">
                    <i class="ph-bold ph-paper-plane-tilt me-1"></i> Publish Opportunity
                </button>
                <a href="<?= url('jobs') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-14">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
