<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Search — ' . get_setting('site_name', 'Adsbooster.net');

require_once __DIR__ . '/includes/auth_guard.php';

$query = trim($_GET['q'] ?? '');
$blogPosts = [];
$marketplaceItems = [];

if (mb_strlen($query) >= 2) {
    $searchTerm = '%' . $query . '%';

    try {
        $blogPosts = db()->fetchAll(
            "SELECT id, title, slug, excerpt, featured_image, published_at
             FROM blog_posts
             WHERE status = 'published'
               AND (title LIKE ? OR excerpt LIKE ?)
             ORDER BY published_at DESC
             LIMIT 12",
            [$searchTerm, $searchTerm]
        );
    } catch (\Throwable $e) { $blogPosts = []; }

    try {
        $marketplaceItems = db()->fetchAll(
            "SELECT id, title, slug, item_type, category, price, featured_image
             FROM marketplace_items
             WHERE status = 'active'
               AND (title LIKE ? OR description LIKE ? OR category LIKE ?)
             ORDER BY created_at DESC
             LIMIT 12",
            [$searchTerm, $searchTerm, $searchTerm]
        );
    } catch (\Throwable $e) { $marketplaceItems = []; }
}

$totalResults = count($blogPosts) + count($marketplaceItems);

require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-140">
    <div class="container max-w-1200-px">

        <div class="text-center tw-mb-10">
            <h1 class="h2 text-white fw-bold">Search Results</h1>
            <?php if ($query !== ''): ?>
                <p class="text-white-50 tw-mt-2">
                    <?= $totalResults ?> result<?= $totalResults !== 1 ? 's' : '' ?> for "<strong class="text-white"><?= e($query) ?></strong>"
                </p>
            <?php endif; ?>
        </div>

        <?php if ($query === ''): ?>
            <div class="text-center tw-py-10">
                <p class="text-white-50">Type something in the search bar to get started.</p>
            </div>
        <?php elseif ($totalResults === 0): ?>
            <div class="text-center tw-py-10">
                <div class="tw-w-16 tw-h-16 bg-white-06 rounded-circle d-inline-flex align-items-center justify-content-center tw-mb-4">
                    <i class="ph-bold ph-magnifying-glass tw-text-2xl text-white-50"></i>
                </div>
                <p class="text-white-50">No results found for "<strong class="text-white"><?= e($query) ?></strong>". Try different keywords.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($blogPosts)): ?>
        <div class="tw-mb-10">
            <h3 class="h4 text-white fw-bold tw-mb-6">Blog Posts</h3>
            <div class="row g-4">
                <?php foreach ($blogPosts as $post): ?>
                    <div class="col-md-4">
                        <a href="<?= url('blog-details') ?>?slug=<?= e($post['slug']) ?>"
                           class="bg-white-06 border border-neutral-700 tw-rounded-xl overflow-hidden d-block text-decoration-none hover-border-main-600 tw-transition h-100">
                            <?php if (!empty($post['featured_image'])): ?>
                                <div style="height:180px;">
                                    <img src="<?= ASSETS_URL . '/' . e($post['featured_image']) ?>" alt="" class="w-100 h-100" style="object-fit:cover;">
                                </div>
                            <?php endif; ?>
                            <div class="tw-p-5">
                                <h5 class="text-white fw-bold tw-mb-2"><?= e($post['title']) ?></h5>
                                <p class="text-white-50 tw-text-sm tw-mb-0 line-clamp-2"><?= e($post['excerpt'] ?? '') ?></p>
                                <?php if ($post['published_at']): ?>
                                    <small class="text-white-50 tw-mt-2 d-block"><?= date('M d, Y', strtotime($post['published_at'])) ?></small>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($marketplaceItems)): ?>
        <div>
            <h3 class="h4 text-white fw-bold tw-mb-6">Marketplace</h3>
            <div class="row g-4">
                <?php foreach ($marketplaceItems as $item): ?>
                    <div class="col-md-4">
                        <a href="<?= url('marketplace') ?>?tab=<?= e($item['item_type'] ?? 'products') ?>"
                           class="bg-white-06 border border-neutral-700 tw-rounded-xl overflow-hidden d-block text-decoration-none hover-border-main-600 tw-transition h-100">
                            <?php if (!empty($item['featured_image'])): ?>
                                <div style="height:180px;">
                                    <img src="<?= ASSETS_URL . '/' . e($item['featured_image']) ?>" alt="" class="w-100 h-100" style="object-fit:cover;">
                                </div>
                            <?php endif; ?>
                            <div class="tw-p-5">
                                <h5 class="text-white fw-bold tw-mb-2"><?= e($item['title']) ?></h5>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="badge bg-white-06 text-white rounded-pill tw-px-3 tw-py-1 tw-text-xs text-capitalize"><?= e($item['item_type'] ?? '') ?></span>
                                    <?php if (!empty($item['price'])): ?>
                                        <span class="text-main-600 fw-bold">$<?= number_format((float)$item['price'], 2) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
