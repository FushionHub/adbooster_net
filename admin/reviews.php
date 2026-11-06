<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Site Reviews Moderation';

// Handle Actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $reviewId = (int)($_POST['review_id'] ?? 0);

        if ($reviewId > 0) {
            $rev = db()->fetchOne("SELECT * FROM site_reviews WHERE id = ?", [$reviewId]);
            if ($rev) {
                switch ($act) {
                    case 'approve':
                        db()->update('site_reviews', ['status' => 'approved'], 'id = ?', [$reviewId]);
                        set_flash('success', "Review #{$reviewId} approved.");
                        break;
                    case 'reject':
                        db()->update('site_reviews', ['status' => 'rejected'], 'id = ?', [$reviewId]);
                        set_flash('success', "Review #{$reviewId} rejected.");
                        break;
                    case 'toggle_featured':
                        $newFeat = empty($rev['is_featured']) ? 1 : 0;
                        db()->update('site_reviews', ['is_featured' => $newFeat], 'id = ?', [$reviewId]);
                        set_flash('success', "Review #{$reviewId} featured status updated.");
                        break;
                    case 'delete':
                        db()->delete('site_reviews', 'id = ?', [$reviewId]);
                        set_flash('success', "Review #{$reviewId} deleted.");
                        break;
                }
            }
        }
    }
    redirect(BASE_URL . '/admin/reviews');
}

$reviews = db()->fetchAll(
    "SELECT r.*, r.review_title as title, u.full_name as author_name, u.email as author_email 
     FROM site_reviews r 
     JOIN users u ON u.id = r.user_id 
     ORDER BY r.created_at DESC"
);

$reviewStats = ['total' => count($reviews), 'approved' => 0, 'pending' => 0, 'featured' => 0, 'avg_rating' => 0.0];
$ratingSum = 0;
foreach ($reviews as $r) {
    $st = $r['status'] ?? 'pending';
    if ($st === 'approved') { $reviewStats['approved']++; }
    else { $reviewStats['pending']++; }
    if (!empty($r['is_featured'])) { $reviewStats['featured']++; }
    $ratingSum += (int)($r['rating'] ?? 0);
}
if ($reviewStats['total'] > 0) {
    $reviewStats['avg_rating'] = round($ratingSum / $reviewStats['total'], 1);
}

require_once __DIR__ . '/sidebar.php';
?>

<!-- KPI tiles -->
<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat">
            <div class="card-icon yellow"><i class="ph-bold ph-star"></i></div>
            <div class="stat-meta">
                <div class="stat-label">Total Reviews</div>
                <div class="stat-value"><?= number_format($reviewStats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat">
            <div class="card-icon green"><i class="ph-bold ph-check-circle"></i></div>
            <div class="stat-meta">
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?= number_format($reviewStats['approved']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat">
            <div class="card-icon blue"><i class="ph-bold ph-hourglass-medium"></i></div>
            <div class="stat-meta">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= number_format($reviewStats['pending']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat">
            <div class="card-icon purple"><i class="ph-bold ph-gauge"></i></div>
            <div class="stat-meta">
                <div class="stat-label">Average rating</div>
                <div class="stat-value"><?= number_format($reviewStats['avg_rating'], 1) ?> &#9733;</div>
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
                    <th>User</th>
                    <th>Rating</th>
                    <th>Headline</th>
                    <th>Review Body</th>
                    <th>Plan / Role</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No reviews submitted yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td>
                                <span class="text-white fw-bold d-block"><?= e($r['author_name'] ?? 'User') ?></span>
                                <span class="text-muted font-size-12"><?= e($r['author_email'] ?? '') ?></span>
                            </td>
                            <td>
                                <span class="text-warning font-size-13 fw-bold">
                                    <?= (int)$r['rating'] ?> <i class="ph-fill ph-star"></i>
                                </span>
                            </td>
                            <td>
                                <span class="text-white fw-semibold"><?= e($r['review_title'] ?? $r['title'] ?? 'Verified Review') ?></span>
                            </td>
                            <td style="max-width:280px;">
                                <span class="text-muted font-size-12 line-clamp-2"><?= e($r['review_text'] ?? '') ?></span>
                            </td>
                            <td><span class="badge-status"><?= ucfirst(e($r['plan_type'] ?? 'free')) ?></span></td>
                            <td>
                                <span class="badge-status <?= ($r['status'] ?? '') === 'approved' ? 'approved' : 'pending' ?>">
                                    <?= ucfirst(e($r['status'] ?? 'pending')) ?>
                                </span>
                                <?php if (!empty($r['is_featured'])): ?>
                                    <span class="badge-status resolved" style="margin-left:4px;">Featured</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <form method="POST" class="d-inline-flex gap-1 justify-content-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">

                                    <?php if ($r['status'] !== 'approved'): ?>
                                        <button type="submit" name="act" value="approve" class="btn-admin btn-admin-green" title="Approve">
                                            <i class="ph ph-check"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="act" value="reject" class="btn-admin btn-admin-outline" title="Reject">
                                            <i class="ph ph-x"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button type="submit" name="act" value="toggle_featured" class="btn-admin btn-admin-blue" title="Toggle Featured on Homepage">
                                        <i class="ph ph-star"></i>
                                    </button>

                                    <button type="submit" name="act" value="delete" class="btn-admin btn-admin-red" onclick="return confirm('Delete this review permanently?')" title="Delete">
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
