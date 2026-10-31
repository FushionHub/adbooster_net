<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Marketplace & Business Directory — ' . get_setting('site_name', 'Adsbooster.net');

require_once __DIR__ . '/includes/app_header.php';

$currentTab = $_GET['tab'] ?? 'business';
$search     = trim($_GET['q'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;
$offset     = ($page - 1) * $perPage;

$userId = current_user_id();

$tabWhere = match($currentTab) {
    'products' => "item_type = 'product'",
    'services' => "item_type = 'service'",
    default    => "item_type IN ('business', 'place')",
};

$countParams = [];
$countSql = "SELECT COUNT(*) as cnt FROM marketplace_items WHERE {$tabWhere} AND status = 'active'";
if ($search !== '') {
    $countSql .= " AND (title LIKE ? OR description LIKE ? OR category LIKE ? OR address LIKE ?)";
    $like = "%{$search}%";
    $countParams = array_merge($countParams, [$like, $like, $like, $like]);
}
if ($filterType !== '') {
    $countSql .= " AND business_type = ?";
    $countParams[] = $filterType;
}

$totalRows = (int)(db()->fetchOne($countSql, $countParams)['cnt'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT l.*, u.full_name as seller_name
        FROM marketplace_items l
        JOIN users u ON u.id = l.user_id
        WHERE {$tabWhere} AND l.status = 'active'";
$params = [];
if ($search !== '') {
    $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.category LIKE ? OR l.address LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($filterType !== '') {
    $sql .= " AND l.business_type = ?";
    $params[] = $filterType;
}

$sql .= " ORDER BY l.is_boosted DESC, l.featured DESC, l.created_at DESC LIMIT {$perPage} OFFSET {$offset}";

$listings = db()->fetchAll($sql, $params);

$ownListingIds = [];
if ($userId) {
    $ownRows = db()->fetchAll("SELECT id FROM marketplace_items WHERE user_id = ?", [$userId]);
    $ownListingIds = array_column($ownRows, 'id');
}
?>

<div class="w-100">
    <!-- Header -->
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Marketplace &amp; Verified Directory</h1>
            <p class="page-subtitle">Discover businesses, hotels, hospitals, churches, products, and services from the community.</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="<?= url('create-listing') ?>" class="btn-accent">
                <i class="ph ph-plus-circle me-1"></i> List Business, Place or Product
            </a>
            <a href="<?= url('dashboard') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Filter & Category Navigation -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px;">
        <ul class="nav nav-pills" style="display:flex;gap:10px;">
            <?php
            $tabs = [
                ['key' => 'business', 'label' => 'Businesses & Places', 'icon' => 'ph-buildings'],
                ['key' => 'products', 'label' => 'Products',            'icon' => 'ph-package'],
                ['key' => 'services', 'label' => 'Services',            'icon' => 'ph-wrench'],
            ];
            foreach ($tabs as $t): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentTab === $t['key'] ? 'tab active' : 'tab' ?>" href="?tab=<?= $t['key'] ?>">
                        <i class="ph <?= $t['icon'] ?> me-1"></i> <?= $t['label'] ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="GET" action="<?= url('marketplace') ?>" style="display:flex;align-items:center;gap:8px;">
            <input type="hidden" name="tab" value="<?= e($currentTab) ?>">
            <?php if ($filterType !== ''): ?>
                <input type="hidden" name="type" value="<?= e($filterType) ?>">
            <?php endif; ?>
            <div style="position:relative;">
                <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search places, hotels, churches, products..." style="width:300px;padding-right:40px;background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:100px;font-size:13px;">
                <button type="submit" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--fg2);border:0;background:transparent;cursor:pointer;">
                    <i class="ph ph-magnifying-glass"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Quick Sub-Type Filter for Businesses & Places -->
    <?php if ($currentTab === 'business'): ?>
        <div style="display:flex;gap:8px;margin-bottom:28px;overflow-x:auto;padding-bottom:6px;">
            <a href="?tab=business" class="btn-outline-dark btn-sm <?= $filterType === '' ? 'active' : '' ?>" style="<?= $filterType === '' ? 'border-color:var(--accent);color:var(--accent);' : '' ?>">
                All Places
            </a>
            <a href="?tab=business&type=hotel" class="btn-outline-dark btn-sm <?= $filterType === 'hotel' ? 'active' : '' ?>" style="<?= $filterType === 'hotel' ? 'border-color:var(--accent);color:var(--accent);' : '' ?>">
                🏨 Hotels &amp; Resorts
            </a>
            <a href="?tab=business&type=hospital" class="btn-outline-dark btn-sm <?= $filterType === 'hospital' ? 'active' : '' ?>" style="<?= $filterType === 'hospital' ? 'border-color:var(--accent);color:var(--accent);' : '' ?>">
                🏥 Hospitals &amp; Clinics
            </a>
            <a href="?tab=business&type=church" class="btn-outline-dark btn-sm <?= $filterType === 'church' ? 'active' : '' ?>" style="<?= $filterType === 'church' ? 'border-color:var(--accent);color:var(--accent);' : '' ?>">
                ⛪ Churches &amp; Worship
            </a>
            <a href="?tab=business&type=restaurant" class="btn-outline-dark btn-sm <?= $filterType === 'restaurant' ? 'active' : '' ?>" style="<?= $filterType === 'restaurant' ? 'border-color:var(--accent);color:var(--accent);' : '' ?>">
                🍽️ Restaurants &amp; Cafes
            </a>
            <a href="?tab=business&type=real_estate" class="btn-outline-dark btn-sm <?= $filterType === 'real_estate' ? 'active' : '' ?>" style="<?= $filterType === 'real_estate' ? 'border-color:var(--accent);color:var(--accent);' : '' ?>">
                🏡 Real Estate
            </a>
            <a href="?tab=business&type=agency" class="btn-outline-dark btn-sm <?= $filterType === 'agency' ? 'active' : '' ?>" style="<?= $filterType === 'agency' ? 'border-color:var(--accent);color:var(--accent);' : '' ?>">
                💼 Agencies &amp; Offices
            </a>
        </div>
    <?php endif; ?>

    <!-- Listings Grid -->
    <?php if (empty($listings)): ?>
        <div class="card-dark" style="text-align:center;padding:72px 20px;">
            <div style="width:64px;height:64px;background:rgba(200,255,0,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:var(--accent);">
                <i class="ph ph-buildings" style="font-size:28px;"></i>
            </div>
            <h4 style="color:#fff;font-weight:700;margin-bottom:6px;">No listings found</h4>
            <p style="color:var(--fg2);font-size:14px;max-width:400px;margin:0 auto 20px;">
                Be the first to list a business, place, hotel, hospital, or church in this directory!
            </p>
            <a href="<?= url('create-listing') ?>" class="btn-accent">
                <i class="ph ph-plus-circle me-1"></i> Post the First Listing
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($listings as $listing): 
                $img = !empty($listing['featured_image']) ? $listing['featured_image'] : ($listing['image_url'] ?? '');
                $whatsapp = !empty($listing['whatsapp']) ? preg_replace('/[^0-9]/', '', $listing['whatsapp']) : '';
                $phone = !empty($listing['phone']) ? $listing['phone'] : '';
            ?>
                <div class="col-xl-4 col-md-6">
                    <div class="card-dark" style="padding:0;overflow:hidden;height:100%;display:flex;flex-direction:column;border:1px solid rgba(255,255,255,0.08);transition:transform 0.2s, border-color 0.2s;">
                        <!-- Thumbnail -->
                        <div style="height:190px;background:#080c14;overflow:hidden;position:relative;">
                            <?php if ($img): 
                                $cardImgSrc = str_starts_with($img, 'http') ? $img : BASE_URL . '/' . $img;
                            ?>
                                <img src="<?= e($cardImgSrc) ?>" alt="<?= e($listing['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--fg3);background:#0d1524;">
                                    <i class="ph ph-image" style="font-size:36px;"></i>
                                </div>
                            <?php endif; ?>

                            <div style="position:absolute;top:12px;left:12px;display:flex;gap:6px;">
                                <span class="badge-accent text-uppercase" style="font-size:10.5px;">
                                    <?= e($listing['item_type']) ?>
                                </span>
                                <?php if (!empty($listing['business_type'])): ?>
                                    <span class="badge-platform" style="text-transform:capitalize;font-size:10.5px;background:rgba(8,12,20,0.85);backdrop-filter:blur(4px);">
                                        <?= e(str_replace('_', ' ', $listing['business_type'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($listing['price']) && (float)$listing['price'] > 0): ?>
                                <div style="position:absolute;bottom:12px;right:12px;background:rgba(8,12,20,0.9);color:var(--accent);font-weight:700;font-size:13px;padding:4px 10px;border-radius:8px;backdrop-filter:blur(4px);border:1px solid rgba(200,255,0,0.3);">
                                    $<?= number_format((float)$listing['price'], 2) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Body -->
                        <div style="padding:22px;flex:1;display:flex;flex-direction:column;">
                            <h5 style="color:#fff;font-weight:700;font-size:16px;margin-bottom:8px;line-height:1.4;">
                                <a href="<?= url('listing-details', ['id' => $listing['id']]) ?>" style="color:#fff;text-decoration:none;">
                                    <?= htmlspecialchars($listing['title']) ?>
                                </a>
                            </h5>

                            <?php if (!empty($listing['address'])): ?>
                                <div style="font-size:12px;color:var(--fg3);display:flex;align-items:center;gap:6px;margin-bottom:12px;">
                                    <i class="ph ph-map-pin" style="color:var(--accent);"></i>
                                    <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($listing['address']) ?></span>
                                </div>
                            <?php endif; ?>

                            <p style="font-size:13px;color:var(--fg2);line-height:1.5;margin-bottom:18px;flex:1;">
                                <?= htmlspecialchars(mb_substr(strip_tags($listing['description']), 0, 95)) ?><?= mb_strlen(strip_tags($listing['description'])) > 95 ? '...' : '' ?>
                            </p>

                            <!-- Direct Contact & Details Buttons -->
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;border-top:1px solid rgba(255,255,255,0.06);padding-top:14px;margin-top:auto;">
                                <div style="display:flex;gap:6px;">
                                    <?php if ($whatsapp): ?>
                                        <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" class="btn-accent btn-sm" style="background:#25D366;color:#000;border:0;padding:6px 10px;" title="Chat on WhatsApp">
                                            <i class="ph ph-whatsapp-logo"></i> WhatsApp
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($phone): ?>
                                        <a href="tel:<?= e($phone) ?>" class="btn-outline-dark btn-sm" style="padding:6px 10px;" title="Call direct">
                                            <i class="ph ph-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <a href="<?= url('listing-details', ['id' => $listing['id']]) ?>" class="btn-outline-dark btn-sm">
                                    View Details <i class="ph ph-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="margin-top:36px;display:flex;justify-content:center;gap:8px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?tab=<?= e($currentTab) ?>&type=<?= e($filterType) ?>&q=<?= e($search) ?>&page=<?= $i ?>" class="btn-sm <?= $i === $page ? 'btn-accent' : 'btn-outline-dark' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
