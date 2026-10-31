<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Blog & Content Moderation';

// Handle Moderation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $postId = (int)($_POST['post_id'] ?? 0);

        if ($postId > 0) {
            $post = db()->fetchOne("SELECT * FROM blog_posts WHERE id = ?", [$postId]);
            if (!$post) {
                set_flash('error', 'Article not found.');
            } else {
                switch ($act) {
                    case 'toggle_status':
                        $newStatus = ($post['status'] === 'published') ? 'draft' : 'published';
                        $update = ['status' => $newStatus];
                        if ($newStatus === 'published' && empty($post['published_at'])) {
                            $update['published_at'] = date('Y-m-d H:i:s');
                        }
                        db()->update('blog_posts', $update, 'id = ?', [$postId]);
                        set_flash('success', "Article '{$post['title']}' status updated to {$newStatus}.");
                        break;

                    case 'toggle_featured':
                        $newVal = empty($post['is_featured']) ? 1 : 0;
                        db()->update('blog_posts', ['is_featured' => $newVal], 'id = ?', [$postId]);
                        set_flash('success', "Article '{$post['title']}' featured status toggled.");
                        break;

                    case 'delete':
                        db()->delete('blog_posts', 'id = ?', [$postId]);
                        set_flash('success', "Article '{$post['title']}' deleted.");
                        break;
                }
            }
        }
    }
    redirect(url('admin/blogs'));
}

// Fetch posts with author info
$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT b.*, u.full_name as author_name, u.email as author_email 
        FROM blog_posts b 
        LEFT JOIN users u ON b.author_id = u.id";
$params = [];

if (in_array($statusFilter, ['published', 'draft', 'pending'], true)) {
    $sql .= " WHERE b.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY b.created_at DESC";

$posts = db()->fetchAll($sql, $params);

// Stats
$totalCount = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM blog_posts")['cnt'] ?? 0);
$pubCount = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM blog_posts WHERE status = 'published'")['cnt'] ?? 0);
$draftCount = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM blog_posts WHERE status = 'draft'")['cnt'] ?? 0);
$totalViews = (int)(db()->fetchOne("SELECT SUM(views_count) as cnt FROM blog_posts")['cnt'] ?? 0);

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="text-white fw-bold mb-1">
            <i class="ph-bold ph-article text-admin-green me-2"></i> Blog &amp; Article Moderation
        </h5>
        <p class="text-muted mb-0 font-size-13">Moderate community thought leadership, articles, and marketing guides.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('blog') ?>" target="_blank" class="btn-admin btn-admin-outline">
            <i class="ph-bold ph-arrow-square-out"></i> Public Blog
        </a>
        <a href="<?= url('blog-create') ?>" class="btn-admin btn-admin-green">
            <i class="ph-bold ph-plus"></i> Write Article
        </a>
    </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Total Articles</div>
            <div class="h3 text-white fw-bold mb-0"><?= number_format($totalCount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Published Live</div>
            <div class="h3 text-success fw-bold mb-0"><?= number_format($pubCount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Drafts / In Review</div>
            <div class="h3 text-warning fw-bold mb-0"><?= number_format($draftCount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Total Reader Impressions</div>
            <div class="h3 text-info fw-bold mb-0"><?= number_format($totalViews) ?></div>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="admin-card">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex gap-2">
            <a href="?status=all" class="btn-admin <?= $statusFilter === 'all' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">All (<?= $totalCount ?>)</a>
            <a href="?status=published" class="btn-admin <?= $statusFilter === 'published' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">Published (<?= $pubCount ?>)</a>
            <a href="?status=draft" class="btn-admin <?= $statusFilter === 'draft' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">Drafts (<?= $draftCount ?>)</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:320px;">Article Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Views</th>
                    <th>Published At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No blog posts found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($posts as $p): 
                        $isPub = ($p['status'] === 'published');
                        $isFeat = !empty($p['is_featured']);
                    ?>
                        <tr>
                            <td>
                                <div class="text-white fw-medium font-size-14 text-truncate" style="max-width:320px;" title="<?= htmlspecialchars($p['title']) ?>">
                                    <?= htmlspecialchars($p['title']) ?>
                                </div>
                                <div class="text-muted font-size-11">/blog-details.php?slug=<?= htmlspecialchars($p['slug']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary text-white font-size-11"><?= htmlspecialchars($p['category'] ?? 'General') ?></span>
                            </td>
                            <td>
                                <div class="text-white font-size-13"><?= htmlspecialchars($p['author_name'] ?? 'User #' . $p['author_id']) ?></div>
                                <div class="text-muted font-size-11"><?= htmlspecialchars($p['author_email'] ?? '') ?></div>
                            </td>
                            <td>
                                <?php if ($isPub): ?>
                                    <span class="badge-status active">Published</span>
                                <?php else: ?>
                                    <span class="badge-status inactive">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-white"><?= number_format((int)($p['views_count'] ?? 0)) ?></td>
                            <td class="text-muted font-size-12">
                                <?= $p['published_at'] ? date('M d, Y', strtotime($p['published_at'])) : 'Not published' ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= url('blog-details', ['slug' => $p['slug']]) ?>" target="_blank" class="btn-admin btn-admin-blue" title="View Article Live">
                                        <i class="ph-bold ph-eye"></i>
                                    </a>
                                    <a href="<?= url('blog-edit') ?>?id=<?= (int)$p['id'] ?>" class="btn-admin btn-admin-outline" title="Edit Article">
                                        <i class="ph-bold ph-pencil-simple"></i>
                                    </a>

                                    <!-- Toggle Publish Status Form -->
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="toggle_status">
                                        <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-admin <?= $isPub ? 'btn-admin-outline' : 'btn-admin-green' ?>" title="<?= $isPub ? 'Unpublish to Draft' : 'Publish Article' ?>">
                                            <i class="ph-bold <?= $isPub ? 'ph-pause' : 'ph-play' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Delete Form -->
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this article permanently?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="delete">
                                        <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-admin btn-admin-red" title="Delete Article">
                                            <i class="ph-bold ph-trash"></i>
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

<?php require_once __DIR__ . '/footer.php'; ?>
