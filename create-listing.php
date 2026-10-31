<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId   = current_user_id();
$userData = current_user();
$adCredits = (float)($userData['ad_credits'] ?? 0);
$planId   = (int)($userData['plan_id'] ?? 0);

// Check if user has an active paid plan
$userPlan = null;
if ($planId > 0) {
    $userPlan = db()->fetchOne("SELECT * FROM pricing_plans WHERE id = ?", [$planId]);
}
$isPlanSubscribed = ($userPlan !== null && (float)($userPlan['price'] ?? 0) > 0);
$standardListingFee = 5.00;
$actualFee = $isPlanSubscribed ? 0.00 : $standardListingFee;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $itemType     = trim($_POST['item_type'] ?? 'business');
        $businessType = trim($_POST['business_type'] ?? '');
        $title        = trim($_POST['title'] ?? '');
        $category     = trim($_POST['category'] ?? 'General');
        $price        = !empty($_POST['price']) ? (float)$_POST['price'] : 0.00;
        $description  = trim($_POST['description'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $whatsapp     = trim($_POST['whatsapp'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? $userData['email']);
        $websiteUrl   = trim($_POST['website_url'] ?? '');
        $mapsUrl      = trim($_POST['google_maps_url'] ?? '');

        if ($title === '') $errors[] = 'Title or Business name is required.';
        if ($description === '') $errors[] = 'Description is required.';

        if ($actualFee > 0 && $adCredits < $actualFee) {
            $errors[] = "Listing fee is \${$standardListingFee} for free accounts, but you have \${$adCredits} credits. Please upgrade to a plan or top up your balance.";
        }

        // Handle Image Upload
        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Invalid image format. Supported formats: JPG, PNG, WebP, GIF.';
            } else {
                $uploadDir = ROOT_PATH . '/uploads/marketplace/' . date('Y/m');
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fname = bin2hex(random_bytes(10)) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . '/' . $fname);
                $imagePath = 'uploads/marketplace/' . date('Y/m') . '/' . $fname;
            }
        }

        // Generate embed from address or Google Maps URL if provided
        $embedCode = '';
        if ($mapsUrl !== '') {
            if (str_contains($mapsUrl, '<iframe')) {
                $embedCode = $mapsUrl;
            } else {
                $embedCode = 'https://maps.google.com/maps?q=' . urlencode($mapsUrl) . '&t=&z=15&ie=UTF8&iwloc=&output=embed';
            }
        } elseif ($address !== '') {
            $embedCode = 'https://maps.google.com/maps?q=' . urlencode($address) . '&t=&z=15&ie=UTF8&iwloc=&output=embed';
        }

        if (empty($errors)) {
            try {
                db()->query("BEGIN");

                if ($actualFee > 0) {
                    $newBalance = $adCredits - $actualFee;
                    db()->update('users', ['ad_credits' => $newBalance], 'id = ?', [$userId]);
                    db()->insert('transactions', [
                        'user_id'  => $userId,
                        'gateway'  => 'wallet',
                        'type'     => 'marketplace_listing_fee',
                        'amount'   => $actualFee,
                        'currency' => 'USD',
                        'status'   => 'successful',
                    ]);
                }

                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . bin2hex(random_bytes(3));

                $listingId = db()->insert('marketplace_items', [
                    'user_id'           => $userId,
                    'item_type'         => $itemType,
                    'business_type'     => $businessType,
                    'title'             => $title,
                    'slug'              => $slug,
                    'category'          => $category,
                    'price'             => $price,
                    'description'       => $description,
                    'address'           => $address,
                    'phone'             => $phone,
                    'whatsapp'          => $whatsapp,
                    'contact_email'     => $contactEmail,
                    'website_url'       => $websiteUrl,
                    'google_maps_url'   => $mapsUrl,
                    'google_maps_embed' => $embedCode,
                    'image_url'         => $imagePath,
                    'featured_image'    => $imagePath,
                    'listing_fee'       => $actualFee,
                    'status'            => 'active',
                    'seller_verified'   => $isPlanSubscribed ? 1 : 0,
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);

                db()->query("COMMIT");

                set_flash('success', 'Your listing has been published successfully!');
                redirect(url('listing-details', ['id' => $listingId]));

            } catch (\Throwable $e) {
                db()->query("ROLLBACK");
                $errors[] = 'Failed to publish listing: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Publish Listing or Place â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Publish Business, Place or Product</h1>
            <p class="page-subtitle">List your business, hotels, hospitals, churches, products, or services for high-intent visitors.</p>
        </div>
        <div>
            <a href="<?= url('marketplace') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-arrow-left"></i> Back to Marketplace
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="padding:16px 20px;border-radius:12px;margin-bottom:24px;">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Plan & Fee Badge -->
    <div style="background:rgba(200,255,0,0.06);border:1px solid rgba(200,255,0,0.25);border-radius:14px;padding:18px 24px;margin-bottom:30px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(200,255,0,0.15);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                <i class="ph ph-tag" style="font-size:22px;"></i>
            </div>
            <div>
                <h6 style="color:#fff;font-weight:700;margin:0 0 2px;">Listing Fee &amp; Membership Perks</h6>
                <p style="color:var(--fg2);font-size:13px;margin:0;">
                    <?php if ($isPlanSubscribed): ?>
                        <span class="text-success fw-bold"><i class="ph ph-check-circle me-1"></i>Included in your <?= htmlspecialchars($userPlan['name']) ?> subscription! Fee: $0.00</span>
                    <?php else: ?>
                        Standard fee: <b>$<?= number_format($standardListingFee, 2) ?></b> (or <a href="<?= url('pricing-plan') ?>" style="color:var(--accent);text-decoration:underline;">upgrade to a plan</a> to list for free). Balance: $<?= number_format($adCredits, 2) ?>.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <span class="badge-status badge-active" style="font-size:12px;padding:6px 14px;">
            <?= $isPlanSubscribed ? 'Fee Waived ($0.00)' : '$' . number_format($standardListingFee, 2) . ' per listing' ?>
        </span>
    </div>

    <div class="card-dark" style="padding:32px;">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Listing Classification -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Listing Classification</label>
                    <select name="item_type" id="item_type" class="form-select" onchange="toggleBusinessType(this.value)" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
                        <option value="business">Business / Place / Location</option>
                        <option value="product">Product / Merchandise</option>
                        <option value="service">Professional Service</option>
                    </select>
                </div>
                <div class="col-md-6" id="businessTypeWrapper">
                    <label class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Place / Business Category</label>
                    <select name="business_type" id="business_type" class="form-select" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
                        <option value="hotel">Hotel / Resort / Accommodation</option>
                        <option value="hospital">Hospital / Medical Center / Clinic</option>
                        <option value="church">Church / Religious Institution</option>
                        <option value="restaurant">Restaurant / Cafe / Lounge</option>
                        <option value="real_estate">Real Estate &amp; Properties</option>
                        <option value="school">School / Academy / University</option>
                        <option value="retail">Retail Store / Supermarket</option>
                        <option value="fitness">Gym / Fitness Center</option>
                        <option value="agency">Marketing / Tech Agency</option>
                        <option value="other">Other Business / Public Landmark</option>
                    </select>
                </div>
            </div>

            <!-- Title & Price -->
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label for="title" class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Title / Business Name</label>
                    <input type="text" id="title" name="title" class="form-control" required placeholder="e.g. Grand Horizon Luxury Hotel &amp; Suites" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
                </div>
                <div class="col-md-4">
                    <label for="price" class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Starting Price ($ USD, Optional)</label>
                    <input type="number" id="price" name="price" step="0.01" class="form-control" placeholder="0.00" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
                </div>
            </div>

            <!-- Category -->
            <div class="mb-4">
                <label for="category" class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Marketplace Category Tag</label>
                <input type="text" id="category" name="category" class="form-control" placeholder="e.g. Hospitality, Healthcare, Worship, Dining" value="Hospitality" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
            </div>

            <!-- Description with CKEditor 5 Free -->
            <div class="mb-4">
                <label for="description" class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Detailed Description &amp; Offer</label>
                <textarea id="description" name="description" rows="6" class="form-control" placeholder="Describe facilities, hours, amenities, products, or service offerings..."></textarea>
            </div>

            <!-- Google Maps & Physical Address -->
            <div style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:24px;margin-bottom:28px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <i class="ph ph-map-pin" style="font-size:22px;color:var(--accent);"></i>
                    <h5 style="color:#fff;font-size:15px;font-weight:700;margin:0;">Location &amp; Google Maps Integration</h5>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="address" class="form-label" style="font-size:13px;color:var(--fg);">Physical Street Address / City</label>
                        <input type="text" id="address" name="address" class="form-control" placeholder="e.g. 104 Ocean View Boulevard, Victoria Island, Lagos" style="background:#0d1524;border:1px solid rgba(255,255,255,0.1);color:#fff;padding:12px;">
                    </div>
                    <div class="col-md-6">
                        <label for="google_maps_url" class="form-label" style="font-size:13px;color:var(--fg);">Google Maps Share Link or Embed Code</label>
                        <input type="text" id="google_maps_url" name="google_maps_url" class="form-control" placeholder="https://maps.app.goo.gl/... or address query" style="background:#0d1524;border:1px solid rgba(255,255,255,0.1);color:#fff;padding:12px;">
                    </div>
                </div>
            </div>

            <!-- Direct Contact Methods -->
            <div style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:24px;margin-bottom:28px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <i class="ph ph-phone-call" style="font-size:22px;color:var(--green);"></i>
                    <h5 style="color:#fff;font-size:15px;font-weight:700;margin:0;">Direct Contact Methods (Customers reach you directly)</h5>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="whatsapp" class="form-label" style="font-size:13px;color:var(--fg);"><i class="ph ph-whatsapp-logo text-success me-1"></i>WhatsApp Number</label>
                        <input type="text" id="whatsapp" name="whatsapp" class="form-control" placeholder="e.g. +1234567890 (International format)" style="background:#0d1524;border:1px solid rgba(255,255,255,0.1);color:#fff;padding:12px;">
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label" style="font-size:13px;color:var(--fg);"><i class="ph ph-phone me-1"></i>Direct Phone Call</label>
                        <input type="text" id="phone" name="phone" class="form-control" placeholder="e.g. +1 (555) 019-2831" style="background:#0d1524;border:1px solid rgba(255,255,255,0.1);color:#fff;padding:12px;">
                    </div>
                    <div class="col-md-4">
                        <label for="contact_email" class="form-label" style="font-size:13px;color:var(--fg);"><i class="ph ph-envelope me-1"></i>Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" class="form-control" value="<?= e($userData['email']) ?>" style="background:#0d1524;border:1px solid rgba(255,255,255,0.1);color:#fff;padding:12px;">
                    </div>
                    <div class="col-12">
                        <label for="website_url" class="form-label" style="font-size:13px;color:var(--fg);"><i class="ph ph-globe me-1"></i>Official Website URL</label>
                        <input type="url" id="website_url" name="website_url" class="form-control" placeholder="https://mybusiness.com" style="background:#0d1524;border:1px solid rgba(255,255,255,0.1);color:#fff;padding:12px;">
                    </div>
                </div>
            </div>

            <!-- Featured Image Upload with Instant Preview -->
            <div class="mb-4">
                <label class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Storefront / Featured Image Photo</label>
                <div style="border:2px dashed rgba(255,255,255,0.15);border-radius:12px;padding:24px;text-align:center;background:#080c14;cursor:pointer;position:relative;" onclick="document.getElementById('image').click()">
                    <div id="imagePreviewContainer" style="display:none;margin-bottom:12px;">
                        <img id="imagePreview" src="" alt="Preview" style="max-height:220px;border-radius:10px;object-fit:cover;">
                    </div>
                    <i class="ph ph-camera" style="font-size:32px;color:var(--accent);margin-bottom:6px;"></i>
                    <div style="font-weight:600;color:#fff;font-size:14px;">Select Image to Upload</div>
                    <span style="font-size:12px;color:var(--fg3);">JPG, PNG, WebP (High resolution recommended)</span>
                    <input type="file" id="image" name="image" accept="image/*" style="display:none;" onchange="previewListingImage(this)">
                </div>
            </div>

            <button type="submit" class="btn-accent w-100" style="padding:14px;font-size:16px;font-weight:700;border-radius:12px;">
                <i class="ph ph-check-circle me-1"></i> Publish Listing Now
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const descEl = document.querySelector('#description');
    if (descEl && typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(descEl, {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
            })
            .catch(err => console.warn('CKEditor desc init:', err));
    }
});

function toggleBusinessType(val) {
    const wrap = document.getElementById('businessTypeWrapper');
    if (wrap) {
        wrap.style.display = (val === 'business') ? 'block' : 'none';
    }
}

function previewListingImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            const container = document.getElementById('imagePreviewContainer');
            preview.src = e.target.result;
            container.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
