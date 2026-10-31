<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Marketplace & Directory Moderation';

// Handle Moderation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $itemId = (int)($_POST['item_id'] ?? 0);

        if ($itemId > 0) {
            $item = db()->fetchOne("SELECT * FROM marketplace_items WHERE id = ?", [$itemId]);
            if (!$item) {
                set_flash('error', 'Listing not found.');
            } else {
                switch ($act) {
                    case 'approve':
                        db()->update('marketplace_items', ['status' => 'active'], 'id = ?', [$itemId]);
                        set_flash('success', "Listing '{$item['title']}' approved and activated.");
                        break;

                    case 'suspend':
                        db()->update('marketplace_items', ['status' => 'inactive'], 'id = ?', [$itemId]);
                        set_flash('success', "Listing '{$item['title']}' suspended.");
                        break;

                    case 'toggle_verify':
                        $newVal = empty($item['seller_verified']) ? 1 : 0;
                        db()->update('marketplace_items', ['seller_verified' => $newVal], 'id = ?', [$itemId]);
                        set_flash('success', "Verified status toggled for listing #{$itemId}.");
                        break;

                    case 'toggle_boost':
                        $newVal = empty($item['is_boosted']) ? 1 : 0;
                        db()->update('marketplace_items', [
                            'is_boosted' => $newVal,
                            'boost_expires_at' => $newVal ? date('Y-m-d H:i:s', time() + (86400 * 30)) : null
                        ], 'id = ?', [$itemId]);
                        set_flash('success', "Boost status toggled for listing #{$itemId}.");
                        break;

                    case 'delete':
                        db()->delete('marketplace_items', 'id = ?', [$itemId]);
                        set_flash('success', "Listing #{$itemId} deleted permanently.");
                        break;
                }
            }
        }
    }
    redirect(url('admin/listings'));
}

$statusFilter = $_GET['status'] ?? 'all';
$typeFilter   = $_GET['type'] ?? 'all';

$sql = "SELECT m.*, u.full_name as owner_name, u.email as owner_email 
        FROM marketplace_items m 
        LEFT JOIN users u ON m.user_id = u.id WHERE 1=1";
$params = [];

if (in_array($statusFilter, ['active', 'pending', 'inactive'], true)) {
    $sql .= " AND m.status = ?";
    $params[] = $statusFilter;
}
if (!empty($typeFilter) && $typeFilter !== 'all') {
    $sql .= " AND m.item_type = ?";
    $params[] = $typeFilter;
}
$sql .= " ORDER BY m.created_at DESC";

$listings = db()->fetchAll($sql, $params);

// Stats
$totalCount = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM marketplace_items")['cnt'] ?? 0);
$activeCount = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM marketplace_items WHERE status = 'active'")['cnt'] ?? 0);
$pendingCount = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM marketplace_items WHERE status = 'pending'")['cnt'] ?? 0);
$boostedCount = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM marketplace_items WHERE is_boosted = 1")['cnt'] ?? 0);

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="text-white fw-bold mb-1">
            <i class="ph-bold ph-storefront text-admin-green me-2"></i> Marketplace &amp; Directory Moderation
        </h5>
        <p class="text-muted mb-0 font-size-13">Moderate verified businesses, places, products, and partner listings.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('marketplace') ?>" target="_blank" class="btn-admin btn-admin-outline">
            <i class="ph-bold ph-arrow-square-out"></i> View Directory
        </a>
        <a href="<?= url('create-listing') ?>" target="_blank" class="btn-admin btn-admin-green">
            <i class="ph-bold ph-plus"></i> Post Listing
        </a>
    </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Total Directory Listings</div>
            <div class="h3 text-white fw-bold mb-0"><?= number_format($totalCount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Active / Verified Live</div>
            <div class="h3 text-success fw-bold mb-0"><?= number_format($activeCount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Pending Approval</div>
            <div class="h3 text-warning fw-bold mb-0"><?= number_format($pendingCount) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Boosted / Promoted</div>
            <div class="h3 text-info fw-bold mb-0"><?= number_format($boostedCount) ?></div>
        </div>
    </div>
</div>

<!-- Filter and Listings Table -->
<div class="admin-card">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex gap-2">
            <a href="?status=all" class="btn-admin <?= $statusFilter === 'all' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">All (<?= $totalCount ?>)</a>
            <a href="?status=active" class="btn-admin <?= $statusFilter === 'active' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">Active (<?= $activeCount ?>)</a>
            <a href="?status=pending" class="btn-admin <?= $statusFilter === 'pending' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">Pending (<?= $pendingCount ?>)</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:280px;">Listing</th>
                    <th>Type</th>
                    <th>Price / Fee</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Badges</th>
                    <th>Direct Contact</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($listings)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No listings matching criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($listings as $l): 
                        $img = !empty($l['featured_image']) ? $l['featured_image'] : ($l['image_url'] ?? '');
                        $imgSrc = !empty($img) ? (str_starts_with($img, 'http') ? $img : ASSETS_URL . '/' . $img) : ASSETS_URL . '/images/thumbs/featured-product-img1.png';
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="rounded-2" style="width:44px; height:34px; object-fit:cover; flex-shrink:0;">
                                    <div style="min-width:0;">
                                        <a href="<?= url('listing-details', ['id' => $l['id']]) ?>" target="_blank" class="text-white fw-medium font-size-13 text-truncate d-block" title="<?= htmlspecialchars($l['title']) ?>">
                                            <?= htmlspecialchars($l['title']) ?>
                                        </a>
                                        <span class="text-muted font-size-11"><?= htmlspecialchars($l['category'] ?? 'General') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary text-white font-size-11 text-capitalize"><?= htmlspecialchars($l['item_type'] ?? 'Listing') ?></span>
                            </td>
                            <td class="text-white font-size-13">
                                $<?= number_format((float)($l['price'] ?? 0), 2) ?>
                            </td>
                            <td>
                                <div class="text-white font-size-13"><?= htmlspecialchars($l['owner_name'] ?? 'User #' . $l['user_id']) ?></div>
                                <div class="text-muted font-size-11"><?= htmlspecialchars($l['owner_email'] ?? '') ?></div>
                            </td>
                            <td>
                                <?php if ($l['status'] === 'active'): ?>
                                    <span class="badge-status active">Active</span>
                                <?php elseif ($l['status'] === 'pending'): ?>
                                    <span class="badge-status pending">Pending</span>
                                <?php else: ?>
                                    <span class="badge-status inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['seller_verified'])): ?>
                                    <span class="badge bg-success font-size-10 px-2 py-1"><i class="ph-bold ph-check"></i> Verified</span>
                                <?php endif; ?>
                                <?php if (!empty($l['is_boosted'])): ?>
                                    <span class="badge bg-warning text-dark font-size-10 px-2 py-1"><i class="ph-bold ph-rocket"></i> Boosted</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-size-12">
                                <?php if (!empty($l['whatsapp'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/\D/', '', $l['whatsapp']) ?>" target="_blank" class="text-success me-2" title="WhatsApp"><i class="ph-bold ph-whatsapp-logo"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($l['phone'])): ?>
                                    <span class="text-white"><?= htmlspecialchars($l['phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= url('listing-details', ['id' => $l['id']]) ?>" target="_blank" class="btn-admin btn-admin-blue" title="View Listing Live">
                                        <i class="ph-bold ph-eye"></i>
                                    </a>

                                    <!-- Approve / Suspend -->
                                    <?php if ($l['status'] !== 'active'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="approve">
                                            <input type="hidden" name="item_id" value="<?= $l['id'] ?>">
                                            <button type="submit" class="btn-admin btn-admin-green" title="Approve Listing">
                                                <i class="ph-bold ph-check"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="suspend">
                                            <input type="hidden" name="item_id" value="<?= $l['id'] ?>">
                                            <button type="submit" class="btn-admin btn-admin-outline" title="Suspend Listing">
                                                <i class="ph-bold ph-pause"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Toggle Verified Badge -->
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="toggle_verify">
                                        <input type="hidden" name="item_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn-admin btn-admin-outline" title="Toggle Verified Badge">
                                            <i class="ph-bold ph-seal-check"></i>
                                        </button>
                                    </form>

                                    <!-- Delete -->
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this listing permanently?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="delete">
                                        <input type="hidden" name="item_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn-admin btn-admin-red" title="Delete Listing">
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
