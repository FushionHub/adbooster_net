<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$slug = trim($_GET['slug'] ?? $_GET['id'] ?? '');
if (empty($slug)) {
    redirect(url('blog'));
}

$post = db()->fetchOne(
    "SELECT b.*, u.full_name as author_name, u.avatar as author_avatar, u.role as author_role
     FROM blog_posts b
     LEFT JOIN users u ON b.author_id = u.id
     WHERE b.slug = ? OR b.id = ? 
     LIMIT 1",
    [$slug, is_numeric($slug) ? (int)$slug : 0]
);

if (!$post) {
    redirect(url('blog'));
}

// Increment views counter
db()->query("UPDATE blog_posts SET views_count = views_count + 1 WHERE id = ?", [(int)$post['id']]);
$post['views_count'] = (int)($post['views_count'] ?? 0) + 1;

$pageTitle = e($post['title']) . ' — ' . get_setting('site_name', 'Adsbooster.net');

// Related & Recent Posts
$relatedPosts = db()->fetchAll(
    "SELECT * FROM blog_posts 
     WHERE id != ? AND status = 'published' AND category = ? 
     ORDER BY published_at DESC LIMIT 3",
    [(int)$post['id'], $post['category'] ?? 'Advertising']
);

if (empty($relatedPosts)) {
    $relatedPosts = db()->fetchAll(
        "SELECT * FROM blog_posts 
         WHERE id != ? AND status = 'published' 
         ORDER BY published_at DESC LIMIT 3",
        [(int)$post['id']]
    );
}

$categories = db()->fetchAll(
    "SELECT category, COUNT(*) as cnt FROM blog_posts WHERE status = 'published' GROUP BY category ORDER BY cnt DESC"
);

$currentUserId = is_logged_in() ? current_user_id() : 0;
$isAdmin = is_logged_in() && (current_user()['role'] ?? '') === 'admin';
$canEdit = is_logged_in() && ($isAdmin || (int)$post['author_id'] === $currentUserId);

$img = !empty($post['featured_image']) ? $post['featured_image'] : 'images/thumbs/blog-page-img1.png';
$featuredImgSrc = str_starts_with($img, 'http') ? $img : ASSETS_URL . '/' . $img;

require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 140px !important; padding-bottom: 80px; min-height: 85vh;">
    <div class="container max-w-1200-px">

        <!-- Breadcrumb & Category Header -->
        <div class="mb-4">
            <div class="d-flex align-items-center gap-2 text-white-50 font-size-13 mb-3">
                <a href="<?= url('index') ?>" class="text-white-50 text-decoration-none hover-text-main-600">Home</a>
                <span>/</span>
                <a href="<?= url('blog') ?>" class="text-white-50 text-decoration-none hover-text-main-600">Blog</a>
                <span>/</span>
                <span class="text-main-600"><?= e($post['category'] ?? 'Insights') ?></span>
            </div>

            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <h1 class="h2 text-white fw-bold max-w-800-px mb-2" style="line-height:1.35;">
                    <?= e($post['title']) ?>
                </h1>
                <?php if ($canEdit): ?>
                    <a href="<?= url('blog-edit') . '?id=' . $post['id'] ?>" class="btn btn-outline-warning rounded-pill px-3 font-size-13 d-flex align-items-center gap-1">
                        <i class="ph ph-pencil-simple"></i> Edit Article
                    </a>
                <?php endif; ?>
            </div>

            <!-- Meta details -->
            <div class="d-flex align-items-center gap-4 text-white-50 font-size-13 mt-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-main-600 rounded-circle text-black fw-bold font-size-12 d-flex align-items-center justify-content-center" style="width:28px; height:28px;">
                        <?= strtoupper(substr($post['author_name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <span class="text-white fw-semibold"><?= e($post['author_name'] ?? 'Adsbooster Editorial') ?></span>
                </div>
                <span><i class="ph ph-calendar-blank me-1 text-main-600"></i> <?= date('M d, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?></span>
                <span><i class="ph ph-eye me-1 text-main-600"></i> <?= number_format((int)$post['views_count']) ?> views</span>
                <span class="badge bg-main-600 text-black px-2 py-1 rounded-pill font-size-11"><?= e($post['category'] ?? 'Guide') ?></span>
            </div>
        </div>

        <div class="row g-5">
            <!-- Article Body -->
            <div class="col-lg-8">
                <!-- Featured Cover Image -->
                <?php if (!empty($post['featured_image'])): ?>
                    <div class="rounded-4 overflow-hidden mb-5 border border-neutral-700" style="max-height:460px;">
                        <img src="<?= e($featuredImgSrc) ?>" alt="<?= e($post['title']) ?>" class="w-100 h-100 object-fit-cover">
                    </div>
                <?php endif; ?>

                <!-- Excerpt quote if present -->
                <?php if (!empty($post['excerpt'])): ?>
                    <div class="p-4 rounded-4 bg-white-06 border-start border-4 border-main-600 mb-5 text-white fs-6 fst-italic">
                        "<?= e($post['excerpt']) ?>"
                    </div>
                <?php endif; ?>

                <!-- Full Body content -->
                <div class="article-content text-white-50 font-size-16" style="line-height: 1.8;">
                    <?= sanitize_html((string)($post['body'] ?? $post['content'] ?? '<p>No content available.</p>')) ?>
                </div>

                <!-- Tags list -->
                <?php if (!empty($post['tags'])): ?>
                    <div class="mt-5 pt-4 border-top border-neutral-800 d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-white fw-semibold font-size-13 me-2"><i class="ph ph-tag text-main-600"></i> Tags:</span>
                        <?php foreach (explode(',', $post['tags']) as $t): 
                            $cleanTag = trim($t);
                            if (empty($cleanTag)) continue;
                        ?>
                            <a href="<?= url('blog') . '?q=' . urlencode($cleanTag) ?> ?>" class="badge bg-white-06 text-white border border-neutral-700 px-3 py-2 rounded-pill font-size-12 text-decoration-none hover-border-main-600">
                                #<?= e($cleanTag) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Author Bio Card -->
                <div class="card bg-white-06 border border-neutral-700 rounded-4 p-4 mt-5 d-flex flex-row align-items-center gap-4 flex-wrap">
                    <div class="bg-main-600 rounded-circle text-black fw-bold font-size-24 d-flex align-items-center justify-content-center" style="width:64px; height:64px; flex-shrink:0;">
                        <?= strtoupper(substr($post['author_name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div style="flex:1; min-width:240px;">
                        <span class="text-main-600 font-size-12 fw-bold text-uppercase">Written by</span>
                        <h5 class="text-white fw-bold mb-1"><?= e($post['author_name'] ?? 'Adsbooster Growth Strategist') ?></h5>
                        <p class="text-white-50 font-size-13 mb-0">Contributing author and performance marketer at <?= e(get_setting('site_name', 'Adsbooster.net')) ?> specializing in omnichannel advertising and growth automation.</p>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="sticky-top" style="top:100px;">
                    <!-- Categories Box -->
                    <div class="card bg-white-06 border border-neutral-700 rounded-4 p-4 mb-4">
                        <h6 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="ph ph-folders text-main-600"></i> Categories
                        </h6>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($categories as $cat): ?>
                                <a href="<?= url('blog') . '?category=' . urlencode($cat['category']) ?> ?>" 
                                   class="d-flex align-items-center justify-content-between text-decoration-none py-2 px-3 rounded-3 bg-dark border border-neutral-800 text-white-50 hover-text-white transition">
                                    <span class="font-size-13"><?= e($cat['category']) ?></span>
                                    <span class="badge bg-main-600 text-black font-size-10 rounded-pill"><?= $cat['cnt'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Related Posts Box -->
                    <div class="card bg-white-06 border border-neutral-700 rounded-4 p-4 mb-4">
                        <h6 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="ph ph-sparkle text-main-600"></i> Related Insights
                        </h6>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($relatedPosts as $r): 
                                $rImg = !empty($r['featured_image']) ? $r['featured_image'] : 'images/thumbs/blog-page-img1.png';
                                $rImgSrc = str_starts_with($rImg, 'http') ? $rImg : ASSETS_URL . '/' . $rImg;
                            ?>
                                <a href="<?= url('blog-details', ['slug' => $r['slug']]) ?>" class="d-flex gap-3 text-decoration-none group">
                                    <img src="<?= e($rImgSrc) ?>" class="rounded-3" style="width:70px; height:54px; object-fit:cover; flex-shrink:0;">
                                    <div style="min-width:0;">
                                        <h6 class="text-white font-size-13 fw-semibold mb-1 line-clamp-2"><?= e($r['title']) ?></h6>
                                        <span class="text-white-50 font-size-11"><?= date('M d, Y', strtotime($r['published_at'] ?? $r['created_at'])) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Callout Banner -->
                    <div class="card bg-main-600 bg-opacity-10 border border-main-600 border-opacity-30 rounded-4 p-4 text-center">
                        <i class="ph-bold ph-rocket text-main-600 fs-1 mb-2 d-inline-block"></i>
                        <h5 class="text-white fw-bold mb-2">Automate Your Ads</h5>
                        <p class="text-white-50 font-size-13 mb-3">Launch cross-platform campaigns on Meta, TikTok, and Google Ads with one unified budget.</p>
                        <a href="<?= url('create-ad') ?>" class="btn btn-main rounded-pill w-100 fw-bold font-size-13">Start Scaling &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
