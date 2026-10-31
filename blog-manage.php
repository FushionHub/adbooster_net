<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId = current_user_id();
$user = current_user();
$isAdmin = ($user['role'] ?? 'user') === 'admin';

// Handle Delete Action
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $deleteId = (int)($_POST['post_id'] ?? 0);

    if ($action === 'delete_post' && $deleteId > 0) {
        $target = db()->fetchOne("SELECT * FROM blog_posts WHERE id = ?", [$deleteId]);
        if ($target && ($isAdmin || (int)$target['author_id'] === $userId)) {
            db()->delete('blog_posts', 'id = ?', [$deleteId]);
            set_flash('success', "Article '{$target['title']}' deleted successfully.");
        } else {
            set_flash('error', 'Unable to delete article or unauthorized.');
        }
        redirect(url('blog-manage'));
    }
}

// Fetch articles: admins can view all or filter by own
$scope = $_GET['scope'] ?? ($isAdmin ? 'all' : 'mine');
if ($scope === 'all' && $isAdmin) {
    $posts = db()->fetchAll(
        "SELECT b.*, u.full_name as author_name, u.email as author_email 
         FROM blog_posts b 
         LEFT JOIN users u ON b.author_id = u.id 
         ORDER BY b.created_at DESC"
    );
} else {
    $posts = db()->fetchAll(
        "SELECT b.*, u.full_name as author_name, u.email as author_email 
         FROM blog_posts b 
         LEFT JOIN users u ON b.author_id = u.id 
         WHERE b.author_id = ? 
         ORDER BY b.created_at DESC",
        [$userId]
    );
}

// Calculate summary stats
$totalPosts = count($posts);
$publishedCount = count(array_filter($posts, fn($p) => ($p['status'] ?? '') === 'published'));
$draftCount = $totalPosts - $publishedCount;
$totalViews = array_sum(array_column($posts, 'views_count'));

$pageTitle = 'Manage Articles & Blog Posts — ' . get_setting('site_name', 'Adsbooster.net');
$_appPage = 'blog-manage';
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="container-fluid px-0">
    <!-- Top Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-title text-white fw-bold mb-1">
                <i class="ph ph-article text-accent me-2"></i> Blog &amp; Article Manager
            </h1>
            <p class="text-muted mb-0 font-size-14">Publish content, generate SEO traffic, and manage platform thought leadership.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($isAdmin): ?>
                <a href="<?= BASE_URL ?>/admin/blogs" class="btn btn-outline-warning rounded-pill px-3 font-size-13">
                    <i class="ph ph-shield-check me-1"></i> Admin Moderation
                </a>
            <?php endif; ?>
            <a href="<?= url('blog') ?>" target="_blank" class="btn btn-outline-light rounded-pill px-3 font-size-13">
                <i class="ph ph-arrow-square-out me-1"></i> Public Blog
            </a>
            <a href="<?= url('blog-create') ?>" class="btn btn-accent rounded-pill px-4 fw-bold text-dark font-size-14 d-flex align-items-center gap-1">
                <i class="ph-bold ph-plus"></i> Write Article
            </a>
        </div>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card bg-card border-card p-3 rounded-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted font-size-13">Total Articles</span>
                    <i class="ph ph-files text-accent fs-5"></i>
                </div>
                <div class="h3 text-white fw-bold mb-0"><?= number_format($totalPosts) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-card border-card p-3 rounded-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted font-size-13">Published</span>
                    <i class="ph ph-check-circle text-success fs-5"></i>
                </div>
                <div class="h3 text-white fw-bold mb-0 text-success"><?= number_format($publishedCount) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-card border-card p-3 rounded-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted font-size-13">Drafts</span>
                    <i class="ph ph-note text-warning fs-5"></i>
                </div>
                <div class="h3 text-white fw-bold mb-0 text-warning"><?= number_format($draftCount) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-card border-card p-3 rounded-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted font-size-13">Total Readers</span>
                    <i class="ph ph-eye text-info fs-5"></i>
                </div>
                <div class="h3 text-white fw-bold mb-0 text-info"><?= number_format($totalViews) ?></div>
            </div>
        </div>
    </div>

    <!-- Filter Nav (if admin) -->
    <?php if ($isAdmin): ?>
        <div class="d-flex gap-2 mb-3">
            <a href="?scope=all" class="btn btn-sm <?= $scope === 'all' ? 'btn-accent text-dark fw-bold' : 'btn-outline-secondary text-white' ?> rounded-pill px-3">
                All Platform Articles (<?= $totalPosts ?>)
            </a>
            <a href="?scope=mine" class="btn btn-sm <?= $scope === 'mine' ? 'btn-accent text-dark fw-bold' : 'btn-outline-secondary text-white' ?> rounded-pill px-3">
                My Articles Only
            </a>
        </div>
    <?php endif; ?>

    <!-- Articles Table Card -->
    <div class="card bg-card border-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="border-secondary text-muted font-size-12 text-uppercase">
                        <th class="ps-4" style="width:340px;">Article</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Date</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($posts)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-3"><i class="ph ph-article-ny-times fs-1 text-muted"></i></div>
                                <h5 class="text-white fw-bold mb-1">No articles found</h5>
                                <p class="text-muted font-size-13 mb-3">Share your marketing tips, agency strategies, or brand updates today.</p>
                                <a href="<?= url('blog-create') ?>" class="btn btn-accent rounded-pill px-4 fw-bold text-dark font-size-13">
                                    <i class="ph-bold ph-plus me-1"></i> Write First Article
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($posts as $p): 
                            $isPub = ($p['status'] ?? '') === 'published';
                            $imgSrc = !empty($p['featured_image']) 
                                ? (str_starts_with($p['featured_image'], 'http') ? $p['featured_image'] : ASSETS_URL . '/' . $p['featured_image'])
                                : ASSETS_URL . '/images/thumbs/blog-page-img1.png';
                        ?>
                            <tr class="border-secondary">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e($imgSrc) ?>" alt="Cover" class="rounded-3" style="width:54px; height:40px; object-fit:cover; flex-shrink:0;">
                                        <div style="min-width:0;">
                                            <a href="<?= url('blog-details') ?>?slug=<?= e($p['slug']) ?>" target="_blank" class="text-white fw-semibold font-size-14 text-truncate d-block" title="<?= e($p['title']) ?>">
                                                <?= e($p['title']) ?>
                                            </a>
                                            <span class="text-muted font-size-12">/blog-details.php?slug=<?= e($p['slug']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-white font-size-11 px-2 py-1 rounded-pill">
                                        <?= e($p['category'] ?? 'General') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-size-13 text-white"><?= e($p['author_name'] ?? 'User #' . $p['author_id']) ?></div>
                                    <?php if ($isAdmin && !empty($p['author_email'])): ?>
                                        <small class="text-muted font-size-11"><?= e($p['author_email']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isPub): ?>
                                        <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-30 rounded-pill px-2 py-1 font-size-11">
                                            ● Published
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30 rounded-pill px-2 py-1 font-size-11">
                                            ● Draft
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-white font-size-13">
                                    <?= number_format((int)($p['views_count'] ?? 0)) ?>
                                </td>
                                <td class="text-muted font-size-13">
                                    <?= date('M d, Y', strtotime($p['created_at'])) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?= url('blog-details') ?>?slug=<?= e($p['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-light rounded-pill px-2" title="View Article Live">
                                            <i class="ph ph-eye"></i>
                                        </a>
                                        <a href="<?= url('blog-edit') ?>?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Edit Article">
                                            <i class="ph ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this article?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete Article">
                                                <i class="ph ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
