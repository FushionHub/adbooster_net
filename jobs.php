<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Advertising & Creative Talent Marketplace — ' . get_setting('site_name', 'Adsbooster.net');
$pageDescription = 'Discover high-paying advertising contracts, media buying roles, UGC video editing gigs, and freelance bounties.';

$currentUserId = is_logged_in() ? current_user_id() : 0;

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$search   = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$jobType  = trim($_GET['type'] ?? '');
$location = trim($_GET['location'] ?? '');

$where = ["j.status = 'open'"];
$params = [];

if ($search !== '') {
    $where[] = "(j.title LIKE ? OR j.description LIKE ? OR j.skills_required LIKE ? OR j.category LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($category !== '' && $category !== 'all') {
    $where[] = "j.category = ?";
    $params[] = $category;
}

if ($jobType !== '' && $jobType !== 'all') {
    $where[] = "j.job_type = ?";
    $params[] = $jobType;
}

if ($location === 'remote') {
    $where[] = "j.location_type = 'remote'";
}

$whereClause = implode(' AND ', $where);

// Safe Count
$totalRows = (int)(db()->fetchOne(
    "SELECT COUNT(*) as cnt 
     FROM jobs j 
     JOIN users u ON u.id = j.user_id 
     WHERE {$whereClause}",
    $params
)['cnt'] ?? 0);

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Main Query with unambiguous column references (j.status, etc.)
$jobs = db()->fetchAll(
    "SELECT j.*, u.full_name as author_name, u.avatar as author_avatar, u.email as author_email, u.phone as author_phone
     FROM jobs j
     JOIN users u ON u.id = j.user_id
     WHERE {$whereClause}
     ORDER BY j.featured DESC, j.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$categories = [
    'All Categories',
    'Media Buying & Ads Scaling',
    'UGC Video & Reel Creation',
    'Ad Copywriting & Scripts',
    'TikTok & Meta Optimization',
    'SEO & Content Marketing',
    'Creative & Motion Graphics',
];

require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 140px !important; padding-bottom: 90px; min-height: 85vh; background: #080c14;">
    <div class="container max-w-1300-px">
        
        <!-- Header Banner & Action CTA -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-4 mb-5 pb-3 border-bottom border-secondary border-opacity-10">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-30 rounded-pill px-3 py-1 font-size-12 fw-bold text-uppercase">
                        <i class="ph ph-briefcase me-1"></i> Talent &amp; Bounties
                    </span>
                    <span class="text-white-50 font-size-13"><?= number_format($totalRows) ?> Active Opportunities</span>
                </div>
                <h1 class="h2 text-white fw-bold mb-2">Advertising, Video &amp; Growth Jobs</h1>
                <p class="text-muted mb-0 font-size-15 max-w-700-px">
                    Hire verified media buyers, UGC creators, and ad copywriters — or apply to performance marketing contracts.
                </p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="<?= url('p2p-chat') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-14 d-flex align-items-center gap-2">
                    <i class="ph ph-chat-circle-dots"></i> Talent Chat
                </a>
                <a href="<?= url('create-job') ?>" class="btn btn-main-600 text-dark fw-bold rounded-pill px-4 py-2 font-size-14 d-flex align-items-center gap-2">
                    <i class="ph-bold ph-plus-circle"></i> Post Job or Bounty
                </a>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card rounded-4 p-3 mb-5 shadow-sm" style="background: #0d1524 !important; border: 1px solid rgba(255,255,255,0.08) !important;">
            <form method="GET" action="<?= url('jobs') ?>" class="row g-3 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text border-secondary border-opacity-25" style="background:#141f33 !important; color:#cbd5e1 !important;">
                            <i class="ph ph-magnifying-glass"></i>
                        </span>
                        <input type="text" name="q" value="<?= e($search) ?>" class="form-control text-white" style="background:#080c14 !important; border: 1px solid rgba(255,255,255,0.15) !important;" placeholder="Role, skill (e.g. TikTok, Meta, ROAS)...">
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <select name="category" class="form-select text-white" style="background:#080c14 !important; border: 1px solid rgba(255,255,255,0.15) !important;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): if ($cat === 'All Categories') continue; ?>
                            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <select name="type" class="form-select text-white" style="background:#080c14 !important; border: 1px solid rgba(255,255,255,0.15) !important;">
                        <option value="">Any Contract Type</option>
                        <option value="contract" <?= $jobType === 'contract' ? 'selected' : '' ?>>Contract</option>
                        <option value="freelance" <?= $jobType === 'freelance' ? 'selected' : '' ?>>Freelance</option>
                        <option value="bounty" <?= $jobType === 'bounty' ? 'selected' : '' ?>>Project Bounty</option>
                        <option value="full-time" <?= $jobType === 'full-time' ? 'selected' : '' ?>>Full-Time</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <select name="location" class="form-select text-white" style="background:#080c14 !important; border: 1px solid rgba(255,255,255,0.15) !important;">
                        <option value="">Any Location</option>
                        <option value="remote" <?= $location === 'remote' ? 'selected' : '' ?>>Remote Only</option>
                    </select>
                </div>

                <div class="col-lg-1 col-md-4 d-grid">
                    <button type="submit" class="btn btn-main-600 text-dark fw-bold rounded-pill">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Jobs Grid List -->
        <?php if (empty($jobs)): ?>
            <div class="text-center py-5 my-5 card rounded-4 p-5" style="background: #0d1524 !important; border: 1px solid rgba(255,255,255,0.08) !important;">
                <i class="ph ph-briefcase text-main-600 mb-3" style="font-size: 48px;"></i>
                <h3 class="text-white fw-bold mb-2">No Matching Opportunities Found</h3>
                <p class="max-w-500-px mx-auto mb-4" style="color: #cbd5e1;">
                    Try adjusting your search criteria or be the first to post a new job opening or project bounty on the platform.
                </p>
                <div>
                    <a href="<?= url('create-job') ?>" class="btn btn-main-600 text-dark fw-bold rounded-pill px-4 py-2 font-size-14">
                        Post a Job Opening
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4 mb-5">
                <?php foreach ($jobs as $job): ?>
                    <div class="col-lg-6">
                        <div class="card rounded-4 p-4 h-100 d-flex flex-column justify-content-between transition-all hover-translate-y" style="background: #0d1524 !important; border: 1px solid rgba(255,255,255,0.08) !important;">
                            <div>
                                <!-- Top Badges & Meta -->
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill px-2 py-1 font-size-11 fw-bold">
                                            <?= e($job['category']) ?>
                                        </span>
                                        <span class="badge border border-secondary border-opacity-25 rounded-pill px-2 py-1 font-size-11" style="background: #080c14; color: #cbd5e1;">
                                            <i class="ph ph-clock me-1"></i><?= ucfirst(str_replace('-', ' ', $job['job_type'])) ?>
                                        </span>
                                        <?php if ($job['location_type'] === 'remote'): ?>
                                            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 rounded-pill px-2 py-1 font-size-11">
                                                <i class="ph ph-globe me-1"></i>Remote
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($job['featured'])): ?>
                                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30 rounded-pill px-2 py-1 font-size-11 fw-bold">
                                            <i class="ph-bold ph-star me-1"></i>Featured Bounty
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Job Title & Summary -->
                                <h3 class="h4 text-white fw-bold mb-2">
                                    <a href="<?= url('job-details', ['id' => $job['id']]) ?>" class="text-white text-decoration-none hover-text-main-600">
                                        <?= e($job['title']) ?>
                                    </a>
                                </h3>
                                <p class="font-size-14 line-clamp-3 mb-3" style="color: #cbd5e1 !important; line-height: 1.6;">
                                    <?= e(mb_substr(strip_tags($job['description']), 0, 200)) ?>...
                                </p>

                                <!-- Skills Tags -->
                                <?php if (!empty($job['skills_required'])): 
                                    $skills = array_filter(array_map('trim', explode(',', $job['skills_required'])));
                                ?>
                                    <div class="d-flex flex-wrap gap-1 mb-4">
                                        <?php foreach (array_slice($skills, 0, 4) as $skill): ?>
                                            <span class="font-size-11 px-2 py-1 bg-dark border border-secondary border-opacity-15 text-light rounded-pill">
                                                #<?= e($skill) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Footer: Budget, Author, & Actions -->
                            <div class="pt-3 border-top border-secondary border-opacity-10 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <span class="text-muted font-size-11 d-block text-uppercase fw-semibold">Estimated Budget</span>
                                    <span class="text-main-600 fw-bold font-size-16">
                                        <?php if (!empty($job['salary_min'])): ?>
                                            $<?= number_format((float)$job['salary_min']) ?>
                                            <?= !empty($job['salary_max']) ? ' - $' . number_format((float)$job['salary_max']) : '' ?>
                                            <span class="text-white-50 font-size-12 fw-normal">/ <?= e($job['salary_period'] ?? 'project') ?></span>
                                        <?php else: ?>
                                            Negotiable Bounty
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <a href="<?= url('p2p-chat') . '?user=' . $job['user_id'] . '&ref_job=' . $job['id'] ?>" class="btn btn-outline-light rounded-pill px-3 py-1 font-size-13 d-flex align-items-center gap-1" title="Chat directly with employer">
                                        <i class="ph ph-chat-teardrop-dots"></i> Inquire
                                    </a>
                                    <a href="<?= url('job-details', ['id' => $job['id']]) ?>" class="btn btn-main-600 text-dark fw-bold rounded-pill px-3 py-1 font-size-13">
                                        View Details <i class="ph ph-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="d-flex justify-content-center">
                    <ul class="pagination pagination-dark gap-2">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link rounded-circle px-3 py-2 <?= $i === $page ? 'bg-main-600 text-dark fw-bold' : 'bg-card text-white border-secondary border-opacity-25' ?>" 
                                   href="?page=<?= $i ?>&category=<?= urlencode($category) ?>&type=<?= urlencode($jobType) ?>&location=<?= urlencode($location) ?>&q=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
