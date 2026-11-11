<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/ecommerce_sync.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access E-Commerce 1-Click Sync.');
    redirect(url('login'));
}

$pageTitle = 'Shopify & WooCommerce 1-Click Sync — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

$latestPack = null;

// Handle Store Connection and Auto-Pack Generation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('ecommerce-sync'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'connect_store') {
        $sType = $_POST['store_type'] ?? 'shopify';
        $sName = trim($_POST['store_name'] ?? 'My Store');
        $sUrl = trim($_POST['store_url'] ?? '');

        if ($sUrl === '' || !preg_match('#^https?://#i', $sUrl)) {
            set_flash('error', 'Enter a valid public store URL (https://...).');
            redirect(url('ecommerce-sync'));
        }

        try {
            [$storeId, $imported, $syncMsg] = EcommerceSyncEngine::connectAndSyncStore($userId, $sType, $sName, $sUrl);
            set_flash('success', $syncMsg);
        } catch (Throwable $e) {
            error_log('ecommerce sync failed: ' . $e->getMessage());
            set_flash('error', 'Store connected but product scraping failed. Check your Firecrawl API key in Settings, or try again.');
        }
        redirect(url('ecommerce-sync'));
    }

    if ($action === 'generate_autopack') {
        $prodId = (int)($_POST['product_id'] ?? 0);
        try {
            $latestPack = EcommerceSyncEngine::generateAutoPack($userId, $prodId);
            set_flash('success', "1-Click Campaign Auto-Pack generated for {$latestPack['product']['product_title']}!");
        } catch (Exception $e) {
            set_flash('error', $e->getMessage());
        }
    }
}

// Fetch user stores and products
$stores = db()->fetchAll("SELECT * FROM ecommerce_stores WHERE user_id = ? ORDER BY id DESC", [$userId]);
$products = db()->fetchAll("SELECT p.*, s.store_name, s.store_type FROM ecommerce_products p JOIN ecommerce_stores s ON p.store_id = s.id WHERE p.user_id = ? ORDER BY p.id DESC", [$userId]);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">

    <!-- Page head -->
    <div class="fl-stagehead">
      <div>
        <div class="pill-row mb-2">
          <span class="pill good"><i class="ph ph-shopping-cart"></i> E-Commerce Bridge V3.0</span>
          <span class="pill on"><i class="ph ph-sparkle"></i> Live Firecrawl product scrape</span>
        </div>
        <h2 class="h3 fw-bold mb-1">Shopify &amp; WooCommerce 1-Click Sync</h2>
        <p class="text-secondary mb-0" style="max-width:78ch">Paste your store URL — Firecrawl scrapes your real product catalog (titles, prices, images, reviews), then synthesizes 3 UGC creatives, 3 ad angles and a 14-day posting calendar in a single click.</p>
      </div>
      <div class="pill-row">
        <button type="button" class="fl-act pri" data-bs-toggle="modal" data-bs-target="#modal-connect-store">
          <i class="ph ph-plugs-connected"></i> Connect new store
        </button>
      </div>
    </div>

    <?php if (!empty($stores)): ?>
    <!-- Connected stores strip -->
    <div class="fl-stats">
      <?php foreach (array_slice($stores, 0, 5) as $st): ?>
      <div class="fl-stat">
        <div class="v" style="font-size:14px;padding-top:5px;line-height:1.3">
          <i class="ph <?= $st['store_type'] === 'shopify' ? 'ph-bag' : ($st['store_type'] === 'woocommerce' ? 'ph-wordpress-logo' : 'ph-globe') ?>" style="color:var(--accent,#c8ff00)"></i>
          <?= e($st['store_name']) ?>
        </div>
        <div class="k" style="text-transform:capitalize"><?= e($st['store_type']) ?> &bull; connected</div>
      </div>
      <?php endforeach; ?>
      <div class="fl-stat accent" style="display:flex;align-items:center;justify-content:center;cursor:pointer" data-bs-toggle="modal" data-bs-target="#modal-connect-store">
        <div class="v" style="font-size:15px"><i class="ph ph-plus"></i> Connect another</div>
        <div class="k">Shopify, Woo or custom</div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (empty($stores)): ?>
    <!-- Empty state -->
    <div class="fl-panel" style="max-height:none;display:flex;align-items:center;justify-content:center;min-height:380px">
      <div class="fl-empty" style="max-width:460px">
        <i class="ph ph-storefront"></i>
        <h4>Connect your online store</h4>
        <p>Sync your Shopify or WooCommerce catalog to automate the entire advertising and social-posting workflow in seconds.</p>
        <div class="pill-row" style="justify-content:center;margin-top:18px">
          <span class="pill"><i class="ph ph-package"></i> Catalog import</span>
          <span class="pill"><i class="ph ph-star"></i> Reviews</span>
          <span class="pill"><i class="ph ph-images"></i> Product images</span>
        </div>
        <button type="button" class="fl-go" style="max-width:280px;margin:20px auto 0" data-bs-toggle="modal" data-bs-target="#modal-connect-store">
          <i class="ph ph-plugs-connected"></i> Connect Shopify / WooCommerce
        </button>
      </div>
    </div>
    <?php else: ?>

    <!-- Product catalog -->
    <div class="fl-stagehead" style="margin-top:22px">
      <div class="t"><i class="ph ph-package" style="color:var(--accent,#c8ff00)"></i> Imported product catalog</div>
      <div class="m"><?= count($products) ?> products &bull; <?= count($stores) ?> store<?= count($stores) === 1 ? '' : 's' ?></div>
    </div>

    <?php if (empty($products)): ?>
    <div class="fl-empty">
      <i class="ph ph-package"></i>
      <h4>No products imported yet</h4>
      <p>Your connected store hasn't returned any products. Reconnect it from the button above to re-ingest the catalog.</p>
    </div>
    <?php else: ?>
    <div class="fl-lib">
      <?php foreach ($products as $prod): ?>
      <div class="fl-card" style="min-height:280px">
        <div style="position:relative">
          <img src="<?= BASE_URL ?>/<?= e($prod['image_url'] ?? 'assets/images/thumbs/about-five-img1.png') ?>" alt="" style="height:172px">
          <span class="pill" style="position:absolute;top:9px;left:9px;font-size:10.5px;background:rgba(5,8,15,.78)">
            <i class="ph ph-storefront"></i> <?= e($prod['store_name']) ?>
          </span>
          <span class="pill on" style="position:absolute;top:9px;right:9px;font-size:11px;font-family:'JetBrains Mono',monospace">
            <?= price_fmt((float)$prod['price']) ?>
          </span>
        </div>
        <div class="bd">
          <div class="pill-row" style="margin-bottom:8px">
            <span class="pill warn" style="font-size:10.5px"><i class="ph-fill ph-star"></i> <?= (float)$prod['rating'] ?></span>
            <span class="pill" style="font-size:10.5px"><?= (int)$prod['reviews_count'] ?> reviews</span>
          </div>
          <h5 style="margin:0 0 6px;font-size:13.5px;font-weight:700;color:var(--fg,#fff);line-height:1.4"><?= e($prod['product_title']) ?></h5>
          <p style="margin:0;color:var(--fg2,#94a3b8);font-size:12px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= e($prod['description']) ?></p>
        </div>
        <div class="ft" style="flex-direction:column;align-items:stretch;gap:6px">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate_autopack">
            <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
            <button type="submit" class="pri" style="width:100%;text-align:center;padding:8px">
              <i class="ph ph-sparkle"></i> 1-Click campaign auto-pack
            </button>
          </form>
          <a href="<?= url('ugc-video-studio') ?>" style="text-align:center"><i class="ph ph-video-camera"></i> Generate AI video ad</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ── Generated auto-pack ──────────────────────────────── -->
    <?php if ($latestPack): ?>
    <div style="margin-top:26px">
      <div class="fl-stagehead">
        <div class="t"><i class="ph ph-lightning" style="color:var(--accent,#c8ff00)"></i> Auto-pack: <?= e($latestPack['product']['product_title']) ?></div>
        <div class="pill-row">
          <a href="<?= url('ugc-video-studio') ?>" class="fl-act"><i class="ph ph-video-camera"></i> Create video ad</a>
          <a href="<?= url('post-creator') ?>" class="fl-act pri"><i class="ph ph-calendar-plus"></i> Schedule all 14 posts</a>
        </div>
      </div>

      <div class="fl-shell" style="grid-template-columns:1fr">
        <!-- Step 1 -->
        <div class="fl-panel" style="max-height:none">
          <div class="fl-lbl"><span class="step">1</span> AI UGC visual assets</div>
          <div class="fl-lib">
            <?php foreach ($latestPack['ugc_images'] as $img): ?>
            <div class="fl-card">
              <img src="<?= BASE_URL ?>/<?= e($img['url']) ?>" alt="<?= e($img['title']) ?>">
              <div class="bd" style="padding:11px 12px">
                <h5 style="margin:0 0 6px;font-size:12.5px"><?= e($img['title']) ?></h5>
                <span class="pill on" style="font-size:10.5px"><?= e($img['badge']) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="fl-panel" style="max-height:none">
          <div class="fl-lbl"><span class="step">2</span> Psychological ad angles</div>
          <div class="fl-lib">
            <?php foreach ($latestPack['copy_variations'] as $cp): ?>
            <div class="fl-card" style="min-height:200px">
              <div class="bd">
                <span class="pill info" style="font-size:10.5px;margin-bottom:8px"><?= e($cp['angle']) ?></span>
                <h5 style="margin:0 0 7px;font-size:13.5px;font-weight:800;color:var(--fg,#fff);line-height:1.4"><?= e($cp['headline']) ?></h5>
                <p style="margin:0;color:var(--fg2,#94a3b8);font-size:12.5px;line-height:1.55"><?= e($cp['primary_text']) ?></p>
              </div>
              <div class="ft">
                <a href="<?= url('create-ad', ['headline' => $cp['headline'], 'text' => $cp['primary_text']]) ?>" class="pri">
                  <i class="ph ph-rocket-launch"></i> Launch ad angle
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="fl-panel" style="max-height:none">
          <div class="fl-lbl"><span class="step">3</span> 14-day social posting calendar</div>
          <div class="tl">
            <?php foreach ($latestPack['posting_calendar_14d'] as $cal): ?>
            <div class="tl-item">
              <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                <span class="tl-h"><?= e($cal['day']) ?></span>
                <span class="pill" style="font-size:10.5px;font-family:'JetBrains Mono',monospace"><?= date('M d, H:i', strtotime($cal['date'])) ?></span>
                <span class="pill info" style="font-size:10.5px;text-transform:capitalize"><?= e($cal['platform']) ?></span>
                <span class="pill" style="font-size:10.5px"><?= e($cal['post_type']) ?></span>
                <a href="<?= url('post-creator', ['caption' => $cal['caption'], 'platform' => $cal['platform']]) ?>" class="fl-act" style="margin-left:auto;padding:4px 11px;font-size:11px"><i class="ph ph-pencil-simple"></i> Queue in Studio</a>
              </div>
              <div class="tl-b"><?= e($cal['caption']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Modal: Connect Store -->
<div class="modal fade" id="modal-connect-store" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:var(--surface-card,#0d1524);border:1px solid var(--border,rgba(255,255,255,.1));border-radius:18px;color:var(--fg,#fff)">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="connect_store">
        <div style="padding:18px 20px 0">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
            <div style="font-weight:800;font-size:15px;display:flex;align-items:center;gap:8px">
              <i class="ph ph-plugs-connected" style="color:var(--accent,#c8ff00)"></i> Connect online store
            </div>
            <button type="button" class="cv-tbtn" data-bs-dismiss="modal" aria-label="Close"><i class="ph ph-x"></i></button>
          </div>
          <div class="fl-help">Credentials are stored against your workspace only. Products are pulled once per connect.</div>
        </div>
        <div style="padding:14px 20px;display:grid;gap:13px">
          <div>
            <label class="cv-label" for="st-type">Store platform</label>
            <select name="store_type" id="st-type" class="fl-in">
              <option value="shopify">Shopify</option>
              <option value="woocommerce">WooCommerce (WordPress)</option>
              <option value="custom">Custom e-commerce domain</option>
            </select>
          </div>
          <div>
            <label class="cv-label" for="st-name">Store name</label>
            <input type="text" name="store_name" id="st-name" class="fl-in" placeholder="e.g. Lumina Glow Cosmetics" required>
          </div>
          <div>
            <label class="cv-label" for="st-url">Store URL</label>
            <input type="url" name="store_url" id="st-url" class="fl-in" placeholder="https://yourstore.myshopify.com" required>
          </div>
        </div>
        <div style="padding:0 20px 18px;display:flex;gap:9px;justify-content:flex-end">
          <button type="button" class="fl-act" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="fl-act pri"><i class="ph ph-arrows-clockwise"></i> Sync &amp; ingest products</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
