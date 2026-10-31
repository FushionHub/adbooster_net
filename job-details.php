<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$jobId = (int)($_GET['id'] ?? 0);
if ($jobId <= 0) {
    redirect(url('jobs'));
}

$job = db()->fetchOne(
    "SELECT j.*, u.full_name as author_name, u.avatar as author_avatar, u.email as author_email, u.phone as author_phone
     FROM jobs j
     JOIN users u ON u.id = j.user_id
     WHERE j.id = ?",
    [$jobId]
);

if (!$job) {
    set_flash('error', 'Opportunity not found or expired.');
    redirect(url('jobs'));
}

// Increment views
try {
    db()->query("UPDATE jobs SET views_count = views_count + 1 WHERE id = ?", [$jobId]);
} catch (\Throwable $e) {}

$pageTitle = e($job['title']) . ' â€” Advertising & Growth Jobs â€” ' . get_setting('site_name', 'Adsbooster.net');
$pageDescription = e(mb_substr(strip_tags($job['description']), 0, 160));

$currentUserId = is_logged_in() ? current_user_id() : 0;
$isOwner = $currentUserId === (int)$job['user_id'];

// Fetch other jobs in same category
$relatedJobs = db()->fetchAll(
    "SELECT id, title, category, job_type, salary_min, salary_max, salary_currency, salary_period 
     FROM jobs 
     WHERE category = ? AND id != ? AND status = 'open' 
     ORDER BY created_at DESC 
     LIMIT 4",
    [$job['category'], $jobId]
);

require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 140px !important; padding-bottom: 90px; min-height: 85vh; background: #080c14;">
    <div class="container max-w-1200-px">

        <!-- Breadcrumbs -->
        <div class="d-flex align-items-center gap-2 text-white-50 font-size-13 mb-4">
            <a href="<?= url('index') ?>" class="text-white-50 text-decoration-none hover-text-main-600">Home</a>
            <span>/</span>
            <a href="<?= url('jobs') ?>" class="text-white-50 text-decoration-none hover-text-main-600">Jobs &amp; Bounties</a>
            <span>/</span>
            <span class="text-main-600"><?= e($job['category']) ?></span>
        </div>

        <div class="row g-4">
            <!-- Left Main Column (Details) -->
            <div class="col-lg-8">
                <div class="card bg-card border border-secondary border-opacity-15 rounded-4 p-4 p-md-5 mb-4" style="background: #0d1524;">
                    <!-- Header Meta -->
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                        <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-30 rounded-pill px-3 py-1 font-size-12 fw-bold">
                            <?= e($job['category']) ?>
                        </span>
                        <span class="badge bg-dark border border-secondary border-opacity-25 text-white-50 rounded-pill px-3 py-1 font-size-12">
                            <i class="ph ph-briefcase me-1"></i> <?= ucfirst(str_replace('-', ' ', $job['job_type'])) ?>
                        </span>
                        <?php if ($job['location_type'] === 'remote'): ?>
                            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-30 rounded-pill px-3 py-1 font-size-12">
                                <i class="ph ph-globe me-1"></i> 100% Remote
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1 class="h2 text-white fw-bold mb-3" style="line-height: 1.3;">
                        <?= e($job['title']) ?>
                    </h1>

                    <div class="d-flex align-items-center gap-4 text-white-50 font-size-13 pb-4 mb-4 border-bottom border-secondary border-opacity-10 flex-wrap">
                        <div><i class="ph ph-calendar-blank text-main-600 me-1"></i> Posted <?= date('M d, Y', strtotime($job['created_at'])) ?></div>
                        <div><i class="ph ph-eye text-main-600 me-1"></i> <?= number_format((int)$job['views_count']) ?> views</div>
                        <div><i class="ph ph-map-pin text-main-600 me-1"></i> <?= e($job['location'] ?: 'Worldwide / Remote') ?></div>
                    </div>

                    <!-- Description -->
                    <div class="mb-5 font-size-15" style="color: #cbd5e1 !important; line-height: 1.8;">
                        <h4 class="text-white fw-bold h5 mb-3">About The Role &amp; Project</h4>
                        <div style="color: #cbd5e1 !important; font-size: 15px; line-height: 1.8;">
                            <?= nl2br(e($job['description'])) ?>
                        </div>
                    </div>

                    <!-- Requirements if present -->
                    <?php if (!empty($job['requirements'])): ?>
                        <div class="mb-5 font-size-15" style="color: #cbd5e1 !important; line-height: 1.8;">
                            <h4 class="text-white fw-bold h5 mb-3">Key Requirements &amp; Deliverables</h4>
                            <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.04) !important; border: 1px solid rgba(255,255,255,0.12) !important; color: #e2e8f0 !important; font-size: 14px; line-height: 1.7;">
                                <?= nl2br(e($job['requirements'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Skills Required -->
                    <?php if (!empty($job['skills_required'])): 
                        $skills = array_filter(array_map('trim', explode(',', $job['skills_required'])));
                    ?>
                        <div class="mb-4">
                            <h4 class="text-white fw-bold h5 mb-3">Required Competencies &amp; Tools</h4>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($skills as $skill): ?>
                                    <span class="badge bg-dark border border-main-600 border-opacity-25 text-main-600 rounded-pill px-3 py-2 font-size-12">
                                        #<?= e($skill) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column (Sidebar CTA & Employer Box) -->
            <div class="col-lg-4">
                <!-- Compensation Card -->
                <div class="card bg-card border border-secondary border-opacity-15 rounded-4 p-4 mb-4 text-center" style="background: #0d1524;">
                    <span class="text-muted font-size-12 text-uppercase fw-bold d-block mb-1">Target Compensation</span>
                    <div class="h2 text-main-600 fw-bold mb-2">
                        <?php if (!empty($job['salary_min'])): ?>
                            $<?= number_format((float)$job['salary_min']) ?>
                            <?= !empty($job['salary_max']) ? ' - $' . number_format((float)$job['salary_max']) : '' ?>
                        <?php else: ?>
                            Negotiable Bounty
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-dark border border-secondary border-opacity-25 text-white-50 px-3 py-1 rounded-pill mb-4 font-size-12">
                        Billing: <?= ucfirst(e($job['salary_period'] ?? 'project')) ?>
                    </span>

                    <div class="d-grid gap-2">
                        <a href="<?= url('p2p-chat') ?>?user=<?= $job['user_id'] ?>&ref_job=<?= $job['id'] ?>" class="btn btn-main-600 text-dark fw-bold rounded-pill py-2 font-size-15 d-flex align-items-center justify-content-center gap-2">
                            <i class="ph-bold ph-chat-circle-dots"></i> Message &amp; Apply Directly
                        </a>
                        <?php if (!empty($job['author_phone'])): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $job['author_phone']) ?>?text=<?= urlencode("Hello, I saw your job listing '{$job['title']}' on Adsbooster.net and would like to apply!") ?>" target="_blank" class="btn btn-outline-success rounded-pill py-2 font-size-14 d-flex align-items-center justify-content-center gap-2">
                                <i class="ph ph-whatsapp-logo"></i> Quick Chat on WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Employer / Lister Card -->
                <div class="card bg-card border border-secondary border-opacity-15 rounded-4 p-4 mb-4" style="background: #0d1524;">
                    <h5 class="text-white fw-bold mb-3 font-size-15">Listed By Employer</h5>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle overflow-hidden bg-main-600 bg-opacity-20 d-flex align-items-center justify-content-center text-main-600 fw-bold font-size-16" style="width: 50px; height: 50px;">
                            <?php if (!empty($job['author_avatar'])): ?>
                                <img src="<?= e($job['author_avatar']) ?>" alt="Avatar" class="w-100 h-100 object-fit-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($job['author_name'], 0, 2)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="text-white fw-bold"><?= e($job['author_name']) ?></div>
                            <span class="badge bg-success bg-opacity-15 text-success font-size-11 border border-success border-opacity-25 rounded-pill px-2 py-0">Verified Client</span>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="<?= url('profile') ?>?u=<?= urlencode($job['author_email']) ?>" class="btn btn-outline-light rounded-pill font-size-13 py-2">
                            <i class="ph ph-user me-1"></i> View Public Portfolio &amp; vCard
                        </a>
                    </div>
                </div>

                <!-- Related Opportunities -->
                <?php if (!empty($relatedJobs)): ?>
                    <div class="card bg-card border border-secondary border-opacity-15 rounded-4 p-4" style="background: #0d1524;">
                        <h5 class="text-white fw-bold mb-3 font-size-15">Related Opportunities</h5>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($relatedJobs as $rel): ?>
                                <div class="p-2 border-bottom border-secondary border-opacity-10 pb-2">
                                    <a href="<?= url('job-details') ?>?id=<?= $rel['id'] ?>" class="text-white text-decoration-none hover-text-main-600 fw-semibold font-size-13 d-block mb-1">
                                        <?= e($rel['title']) ?>
                                    </a>
                                    <div class="d-flex align-items-center justify-content-between font-size-11 text-white-50">
                                        <span><?= e($rel['category']) ?></span>
                                        <span class="text-main-600 fw-bold">
                                            <?= !empty($rel['salary_min']) ? '$' . number_format((float)$rel['salary_min']) : 'Negotiable' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
