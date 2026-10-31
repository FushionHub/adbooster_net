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

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="text-white fw-bold mb-1"><i class="ph ph-star text-warning me-2"></i> Client Reviews &amp; Testimonials</h4>
        <p class="text-muted font-size-13 mb-0">Approve, feature, and moderate user ratings and testimonials displayed on the homepage.</p>
    </div>
    <div>
        <a href="<?= url('reviews') ?>" target="_blank" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="ph ph-arrow-square-out me-1"></i> Public Reviews Page
        </a>
    </div>
</div>

<div class="card bg-card border-card rounded-4 p-0 overflow-hidden" style="background:#111827; border:1px solid #1e293b;">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead style="background:#0a0f1a; border-bottom:1px solid #1e293b;">
                <tr>
                    <th class="ps-4">User</th>
                    <th>Rating</th>
                    <th>Headline</th>
                    <th>Review Body</th>
                    <th>Plan / Role</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
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
                            <td class="ps-4">
                                <span class="text-white fw-bold d-block"><?= e($r['author_name'] ?? 'User') ?></span>
                                <span class="text-white-50 font-size-12"><?= e($r['author_email'] ?? '') ?></span>
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
                                <span class="text-white-50 font-size-12 line-clamp-2"><?= e($r['review_text'] ?? '') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary font-size-11"><?= ucfirst(e($r['plan_type'] ?? 'free')) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= ($r['status'] ?? '') === 'approved' ? 'bg-success' : 'bg-warning text-dark' ?> font-size-11">
                                    <?= ucfirst(e($r['status'] ?? 'pending')) ?>
                                </span>
                                <?php if (!empty($r['is_featured'])): ?>
                                    <span class="badge bg-info font-size-10 ms-1">Featured</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end pe-4">
                                <form method="POST" class="d-inline-flex gap-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">

                                    <?php if ($r['status'] !== 'approved'): ?>
                                        <button type="submit" name="act" value="approve" class="btn btn-sm btn-outline-success rounded-pill" title="Approve">
                                            <i class="ph ph-check"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="act" value="reject" class="btn btn-sm btn-outline-warning rounded-pill" title="Reject">
                                            <i class="ph ph-x"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button type="submit" name="act" value="toggle_featured" class="btn btn-sm btn-outline-info rounded-pill" title="Toggle Featured on Homepage">
                                        <i class="ph ph-star"></i>
                                    </button>

                                    <button type="submit" name="act" value="delete" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Delete this review permanently?')" title="Delete">
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
