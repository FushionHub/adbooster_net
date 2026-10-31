<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Blog & Marketing Knowledge Base — ' . get_setting('site_name', 'Adsbooster.net');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$searchCategory = trim($_GET['category'] ?? '');
$searchQuery    = trim($_GET['q'] ?? '');

$where = ["b.status = 'published'"];
$params = [];

if ($searchCategory !== '' && $searchCategory !== 'all') {
    $where[] = "b.category = ?";
    $params[] = $searchCategory;
}

if ($searchQuery !== '') {
    $where[] = "(b.title LIKE ? OR b.excerpt LIKE ? OR b.tags LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereClause = implode(' AND ', $where);

$totalRows = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM blog_posts b WHERE {$whereClause}", $params)['cnt'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$posts = db()->fetchAll(
    "SELECT b.*, u.full_name as author_name, u.avatar as author_avatar
     FROM blog_posts b
     LEFT JOIN users u ON u.id = b.author_id
     WHERE {$whereClause}
     ORDER BY b.is_featured DESC, b.published_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$allCategories = db()->fetchAll(
    "SELECT category, COUNT(*) as cnt FROM blog_posts WHERE status = 'published' GROUP BY category ORDER BY cnt DESC"
);

require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 140px !important; padding-bottom: 80px; min-height: 85vh;">
    <div class="container max-w-1200-px">

        <!-- Top Header & Write CTA -->
        <div class="d-flex align-items-center justify-content-between mb-5 flex-wrap gap-3">
            <div>
                <span class="badge bg-main-600 bg-opacity-10 text-main-600 border border-main-600 border-opacity-25 rounded-pill px-3 py-1 font-size-12 fw-bold text-uppercase mb-2 d-inline-block">
                    Adsbooster Insights
                </span>
                <h1 class="h2 text-white fw-bold mb-2">Growth &amp; Advertising Guides</h1>
                <p class="text-white-50 mb-0 font-size-15">Actionable playbooks on Meta ads, TikTok scaling, Google Ads, and local business directory growth.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (is_logged_in()): ?>
                    <a href="<?= url('blog-create') ?>" class="btn btn-main rounded-pill px-4 fw-bold d-flex align-items-center gap-2">
                        <i class="ph-bold ph-plus"></i> Write Article
                    </a>
                    <a href="<?= url('blog-manage') ?>" class="btn bg-white-06 border border-neutral-700 text-white rounded-pill px-3">
                        <i class="ph ph-folder-simple me-1"></i> My Articles
                    </a>
                <?php else: ?>
                    <a href="<?= url('login') ?>" class="btn btn-main rounded-pill px-4 fw-bold d-flex align-items-center gap-2">
                        <i class="ph-bold ph-pencil-simple"></i> Submit an Article
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter bar & Search -->
        <div class="card bg-white-06 border border-neutral-700 rounded-4 p-3 mb-5">
            <div class="row g-3 align-items-center">
                <!-- Category Pills -->
                <div class="col-lg-8">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="?category=all<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" 
                           class="btn btn-sm <?= ($searchCategory === '' || $searchCategory === 'all') ? 'bg-main-600 text-black fw-bold' : 'bg-white-06 text-white border-neutral-700' ?> rounded-pill px-3">
                            All Articles
                        </a>
                        <?php foreach ($allCategories as $c): ?>
                            <a href="?category=<?= urlencode($c['category']) ?><?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" 
                               class="btn btn-sm <?= ($searchCategory === $c['category']) ? 'bg-main-600 text-black fw-bold' : 'bg-white-06 text-white border-neutral-700' ?> rounded-pill px-3">
                                <?= e($c['category']) ?> <span class="opacity-75 font-size-11">(<?= $c['cnt'] ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Search Input -->
                <div class="col-lg-4">
                    <form method="GET" action="<?= url('blog') ?>" class="position-relative">
                        <?php if ($searchCategory): ?>
                            <input type="hidden" name="category" value="<?= e($searchCategory) ?>">
                        <?php endif; ?>
                        <input type="text" name="q" placeholder="Search insights..." value="<?= e($searchQuery) ?>" 
                               class="form-control bg-dark border-secondary text-white rounded-pill pe-5 ps-3 font-size-13 py-2">
                        <button type="submit" class="btn position-absolute top-50 end-0 translate-middle-y text-main-600 pe-3 border-0 bg-transparent">
                            <i class="ph ph-magnifying-glass fs-6"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Posts Grid -->
        <?php if (empty($posts)): ?>
            <div class="text-center py-5">
                <i class="ph ph-article-ny-times fs-1 text-white-50 mb-3 d-block"></i>
                <h4 class="text-white fw-bold mb-2">No articles match your search</h4>
                <p class="text-white-50 font-size-14 mb-4">Try different keywords or browse all categories.</p>
                <a href="<?= url('blog') ?>" class="btn btn-main rounded-pill px-4 fw-bold">Clear Filters</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($posts as $post): 
                    $img = !empty($post['featured_image']) ? $post['featured_image'] : 'images/thumbs/blog-page-img1.png';
                    $imgSrc = (str_starts_with($img, 'http')) ? $img : ASSETS_URL . '/' . $img;
                ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="bg-white-06 border border-neutral-700 rounded-4 overflow-hidden d-flex flex-column h-100 hover-border-main-600 transition">
                            <!-- Image with category tag -->
                            <div class="position-relative" style="height:210px; overflow:hidden;">
                                <a href="<?= url('blog-details', ['slug' => $post['slug']]) ?>">
                                    <img src="<?= e($imgSrc) ?>" alt="<?= e($post['title']) ?>" class="w-100 h-100 object-fit-cover">
                                </a>
                                <span class="badge bg-main-600 text-black position-absolute top-0 start-0 m-3 px-3 py-1 rounded-pill font-size-11 fw-bold">
                                    <?= e($post['category'] ?? 'Advertising') ?>
                                </span>
                                <?php if (!empty($post['is_featured'])): ?>
                                    <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-3 px-2 py-1 rounded-pill font-size-10 fw-bold">
                                        ★ Featured
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Card Body -->
                            <div class="p-4 d-flex flex-column flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between text-white-50 font-size-12 mb-2">
                                    <span><?= date('M d, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?></span>
                                    <span><i class="ph ph-eye me-1"></i><?= number_format((int)($post['views_count'] ?? 0)) ?> views</span>
                                </div>

                                <h5 class="text-white fw-bold mb-2 line-clamp-2" style="font-size: 1.1rem; line-height: 1.4;">
                                    <a href="<?= url('blog-details', ['slug' => $post['slug']]) ?>" class="text-white text-decoration-none hover-text-main-600">
                                        <?= e($post['title']) ?>
                                    </a>
                                </h5>

                                <p class="text-white-50 font-size-13 mb-4 line-clamp-3 flex-grow-1">
                                    <?= e($post['excerpt'] ?? '') ?>
                                </p>

                                <div class="d-flex align-items-center justify-content-between pt-3 border-top border-neutral-800">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-main-600 rounded-circle text-black fw-bold font-size-12 d-flex align-items-center justify-content-center" style="width:30px; height:30px;">
                                            <?= strtoupper(substr($post['author_name'] ?? 'A', 0, 1)) ?>
                                        </div>
                                        <span class="text-white font-size-13 fw-medium"><?= e($post['author_name'] ?? 'Adsbooster Team') ?></span>
                                    </div>
                                    <a href="<?= url('blog-details', ['slug' => $post['slug']]) ?>" class="text-main-600 font-size-13 fw-semibold text-decoration-none d-flex align-items-center gap-1">
                                        Read <i class="ph ph-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-center align-items-center mt-5 gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $searchCategory ? '&category=' . urlencode($searchCategory) : '' ?><?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" 
                           class="btn bg-white-06 border border-neutral-700 text-white rounded-pill px-4 py-2 font-size-13">
                            &larr; Previous
                        </a>
                    <?php endif; ?>

                    <span class="text-white-50 px-3 font-size-13">Page <?= $page ?> of <?= $totalPages ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $searchCategory ? '&category=' . urlencode($searchCategory) : '' ?><?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" 
                           class="btn bg-white-06 border border-neutral-700 text-white rounded-pill px-4 py-2 font-size-13">
                            Next &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
