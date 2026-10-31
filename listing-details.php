<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$listingId = (int)($_GET['id'] ?? 0);
if ($listingId <= 0) {
    redirect(url('marketplace'));
}

$listing = db()->fetchOne(
    "SELECT l.*, u.full_name as seller_name, u.email as seller_email, u.phone as user_phone
     FROM marketplace_items l
     JOIN users u ON u.id = l.user_id
     WHERE l.id = ?",
    [$listingId]
);

if (!$listing) {
    set_flash('error', 'Listing not found.');
    redirect(url('marketplace'));
}

// Increment views count
try {
    db()->query("UPDATE marketplace_items SET views_count = views_count + 1 WHERE id = ?", [$listingId]);
} catch (\Throwable $e) {}

$phone = !empty($listing['phone']) ? $listing['phone'] : ($listing['user_phone'] ?? '');
$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
$whatsapp = !empty($listing['whatsapp']) ? $listing['whatsapp'] : $phone;
$cleanWhatsapp = preg_replace('/[^0-9]/', '', $whatsapp);

$pageTitle = e($listing['title']) . ' â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div style="margin-bottom:24px;">
        <a href="<?= url('marketplace') ?>" class="btn-outline-dark btn-sm">
            <i class="ph ph-arrow-left"></i> Back to Marketplace
        </a>
    </div>

    <div class="row g-4">
        <!-- Main Content Column (Left) -->
        <div class="col-lg-8">
            <div class="card-dark" style="padding:0;overflow:hidden;margin-bottom:28px;">
                <!-- Main Image -->
                <?php 
                    $mainImg = !empty($listing['featured_image']) ? $listing['featured_image'] : ($listing['image_url'] ?? '');
                    $mainImgSrc = !empty($mainImg) ? (str_starts_with($mainImg, 'http') ? $mainImg : BASE_URL . '/' . $mainImg) : '';
                ?>
                <?php if (!empty($mainImgSrc)): ?>
                    <div style="height:380px;background:#080c14;overflow:hidden;">
                        <img src="<?= e($mainImgSrc) ?>" alt="<?= e($listing['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                <?php else: ?>
                    <div style="height:240px;background:#0d1524;display:flex;align-items:center;justify-content:center;color:var(--fg3);">
                        <i class="ph ph-buildings" style="font-size:48px;"></i>
                    </div>
                <?php endif; ?>

                <div style="padding:32px;">
                    <!-- Badge row -->
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                        <span class="badge-accent text-uppercase" style="font-size:12px;">
                            <?= e($listing['item_type'] ?? 'Business') ?>
                        </span>
                        <?php if (!empty($listing['business_type'])): ?>
                            <span class="badge-platform" style="text-transform:capitalize;font-size:12px;">
                                <i class="ph ph-map-pin me-1"></i> <?= e(str_replace('_', ' ', $listing['business_type'])) ?>
                            </span>
                        <?php endif; ?>
                        <span class="badge-platform" style="font-size:12px;">
                            <?= e($listing['category'] ?? 'General') ?>
                        </span>
                        <?php if (!empty($listing['seller_verified'])): ?>
                            <span class="badge-status badge-active" style="font-size:11px;">
                                <i class="ph ph-seal-check me-1"></i> Verified Partner
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1 style="color:#fff;font-size:26px;font-weight:800;margin-bottom:12px;"><?= e($listing['title']) ?></h1>

                    <?php if (!empty($listing['price']) && (float)$listing['price'] > 0): ?>
                        <div style="color:var(--accent);font-size:24px;font-weight:800;margin-bottom:20px;">
                            $<?= number_format((float)$listing['price'], 2) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($listing['address'])): ?>
                        <div style="display:flex;align-items:center;gap:8px;color:var(--fg2);font-size:14px;margin-bottom:24px;">
                            <i class="ph ph-map-pin" style="color:var(--accent);font-size:18px;"></i>
                            <span><?= e($listing['address']) ?></span>
                        </div>
                    <?php endif; ?>

                    <hr style="border-color:rgba(255,255,255,0.08);margin:24px 0;">

                    <h4 style="color:#fff;font-size:18px;font-weight:700;margin-bottom:14px;">About This Listing</h4>
                    <div style="color:var(--fg);font-size:14.5px;line-height:1.7;" class="ck-content">
                        <?= sanitize_html((string)($listing['description'] ?? '')) ?>
                    </div>

                    <!-- Embedded Google Maps -->
                    <?php
                    // Only ever embed a whitelisted Google Maps URL â€” the raw
                    // column is user-controlled and would otherwise allow an
                        // arbitrary third-party iframe (clickjacking / phishing).
                        $mapSrc = '';
                        $storedMap = trim((string)($listing['google_maps_embed'] ?? ''));
                        if ($storedMap !== ''
                            && preg_match('#^https://(www\.)?google\.com/maps/[^\s"\'<>]+$#i', $storedMap)
                            && !str_contains($storedMap, 'javascript:')
                        ) {
                            $mapSrc = $storedMap;
                        } elseif (!empty($listing['address'])) {
                            $mapSrc = 'https://maps.google.com/maps?q=' . urlencode((string)$listing['address']) . '&t=&z=15&ie=UTF8&iwloc=&output=embed';
                        }
                    ?>
                        <?php if ($mapSrc !== ''): ?>
                        <hr style="border-color:rgba(255,255,255,0.08);margin:32px 0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                            <h4 style="color:#fff;font-size:18px;font-weight:700;margin:0;"><i class="ph ph-map-trifold me-2 text-warning"></i>Google Maps Location</h4>
                            <?php if (!empty($listing['address'])): ?>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($listing['address']) ?>" target="_blank" class="btn-outline-dark btn-sm">
                                    Open in Google Maps <i class="ph ph-arrow-square-out"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div style="border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);height:320px;background:#080c14;">
                            <iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="<?= e($mapSrc) ?>" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Direct Contact Actions (Right) -->
        <div class="col-lg-4">
            <!-- Direct Contact Card -->
            <div class="card-dark" style="padding:28px;margin-bottom:24px;position:sticky;top:90px;">
                <h4 style="color:#fff;font-size:17px;font-weight:700;margin-bottom:6px;">Contact Directly</h4>
                <p style="color:var(--fg2);font-size:13px;margin-bottom:20px;">Get in touch immediately through verified channels:</p>

                <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;">
                    <!-- WhatsApp Direct Button -->
                    <?php if (!empty($cleanWhatsapp)): ?>
                        <a href="https://wa.me/<?= e($cleanWhatsapp) ?>?text=<?= urlencode('Hello! I am inquiring about your listing "' . $listing['title'] . '" on Adsbooster.net.') ?>" target="_blank" class="btn-accent" style="display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;background:#25D366;color:#000;border:0;font-weight:700;">
                            <i class="ph ph-whatsapp-logo" style="font-size:20px;"></i> Chat on WhatsApp
                        </a>
                    <?php endif; ?>

                    <!-- Direct Phone Call Button -->
                    <?php if (!empty($phone)): ?>
                        <a href="tel:<?= e($phone) ?>" class="btn-outline-dark" style="display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;font-weight:600;">
                            <i class="ph ph-phone-call" style="font-size:18px;color:var(--green);"></i> Call: <?= e($phone) ?>
                        </a>
                    <?php endif; ?>

                    <!-- P2P In-App Chat -->
                    <?php if ((int)$listing['user_id'] !== current_user_id()): ?>
                        <a href="<?= url('p2p-chat') ?>?user=<?= (int)$listing['user_id'] ?>" class="btn-outline-dark" style="display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;font-weight:600;">
                            <i class="ph ph-chats-circle" style="font-size:18px;color:var(--accent);"></i> Send In-App P2P Message
                        </a>
                    <?php endif; ?>

                    <!-- Email Seller -->
                    <?php if (!empty($listing['contact_email'] ?? $listing['seller_email'])): ?>
                        <a href="mailto:<?= e($listing['contact_email'] ?? $listing['seller_email']) ?>?subject=<?= urlencode('Inquiry: ' . $listing['title']) ?>" class="btn-outline-dark" style="display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;font-weight:600;">
                            <i class="ph ph-envelope" style="font-size:18px;"></i> Email Seller
                        </a>
                    <?php endif; ?>

                    <!-- Official Website -->
                    <?php if (!empty($listing['website_url'])): ?>
                        <a href="<?= e($listing['website_url']) ?>" target="_blank" class="btn-outline-dark" style="display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;font-weight:600;">
                            <i class="ph ph-globe" style="font-size:18px;"></i> Visit Website
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Seller Profile Mini-Card -->
                <div style="background:#080c14;border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:16px;">
                    <div style="font-size:11px;color:var(--fg3);text-transform:uppercase;margin-bottom:8px;">Listed By:</div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--accent), #10b981);display:flex;align-items:center;justify-content:center;color:#080c14;font-weight:700;">
                            <?= strtoupper(substr($listing['seller_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:700;color:#fff;font-size:14px;"><?= htmlspecialchars($listing['seller_name'] ?? 'Community Member') ?></div>
                            <span style="font-size:11px;color:var(--fg3);">Member since <?= date('Y', strtotime($listing['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
