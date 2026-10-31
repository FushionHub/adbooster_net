<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ai_engine.php';
require_once __DIR__ . '/modules/social_post_engine.php';

$userId    = current_user_id();
$userData  = current_user();
$aiCredits = (int)($userData['ai_credits'] ?? 0);

$pageTitle = 'AI Studio — Create Images & Videos — ' . get_setting('site_name', 'Adsbooster.net');
$_appPage  = 'ugc-generator';

$errors       = [];
$success      = '';
$generatedUgc = null;

$activeTab = $_GET['tab'] ?? 'generate_image';
if (!in_array($activeTab, ['generate_image', 'image_editor', 'creative_gallery'], true)) {
    $activeTab = 'generate_image';
}

// Handle UGC Image Generation or Regeneration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['generate_ugc_image', 'regenerate_variation'], true)) {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        $productName    = trim($_POST['product_name'] ?? '');
        $ugcStyle       = trim($_POST['ugc_style'] ?? 'selfie_hold');
        $creatorPersona = trim($_POST['creator_persona'] ?? 'gen_z');
        $aspectRatio    = trim($_POST['aspect_ratio'] ?? '1:1');
        $targetAudience = trim($_POST['target_audience'] ?? '');
        $headline       = trim($_POST['headline'] ?? '');
        $badgeText      = trim($_POST['badge_text'] ?? '⭐⭐⭐⭐⭐ Top Rated');

        // If regenerating, use a new random seed
        $seed = ($_POST['action'] === 'regenerate_variation') ? random_int(100000, 999999) : (int)($_POST['seed'] ?? random_int(100000, 999999));

        if ($productName === '') {
            $errors[] = 'Type your product name first — that is all we need to start.';
        } else {
            $res = AIEngine::generateUGCImage([
                'product_name'    => $productName,
                'ugc_style'       => $ugcStyle,
                'creator_persona' => $creatorPersona,
                'aspect_ratio'    => $aspectRatio,
                'target_audience' => $targetAudience,
                'headline'        => $headline,
                'badge_text'      => $badgeText,
                'seed'            => $seed,
            ], $userId);

            if ($res['success']) {
                $generatedUgc = $res['data'];
                $aiCredits    = $res['credits_remaining'];
                $activeTab    = 'generate_image';
                $success      = ($_POST['action'] === 'regenerate_variation')
                    ? 'Here is a fresh variation of your creative.'
                    : ($res['message'] ?? 'Your image is ready.');
            } else {
                $errors[]  = $res['message'];
                $aiCredits = $res['credits_remaining'];
            }
        }
    }
}

// Fetch user's recent UGC image creatives
$recentCreatives = db()->fetchAll(
    "SELECT id, prompt, result_output, credits_used, created_at
     FROM ai_creatives
     WHERE user_id = ? AND type = 'image'
     ORDER BY created_at DESC LIMIT 12",
    [$userId]
);

require_once __DIR__ . '/includes/app_header.php';

// Shared option metadata — used by the chip controls below.
$styleOptions = [
    'selfie_hold'       => ['Selfie with product', 'A real person holding it, facing the camera. Highest click rate.'],
    'unboxing'          => ['Unboxing shot', 'The product opened on a table, packaging in view.'],
    'lifestyle_kitchen' => ['At home', 'A candid moment in a kitchen or living room.'],
    'influencer_desk'   => ['Creator desk', 'A content creator reviewing it at their setup.'],
    'before_after'      => ['Before & after', 'A side-by-side transformation. Great for results claims.'],
    'flatlay'           => ['Flat lay', 'Top-down editorial layout on a clean background.'],
];
$personaOptions = [
    'gen_z'           => ['Lexi', 'Gen-Z, energetic, relatable'],
    'saas_founder'    => ['Marcus', 'Founder, professional'],
    'fitness_athlete' => ['Jordan', 'Fitness & wellness'],
    'homemaker'       => ['Maya', 'Busy parent, everyday life'],
    'tech_reviewer'   => ['Alex', 'Tech & gadgets'],
    'luxury_model'    => ['Elena', 'High-end, editorial'],
];
$aspectOptions = [
    '1:1'  => ['Square', 'Feed post'],
    '9:16' => ['Vertical', 'Reels & TikTok'],
    '16:9' => ['Wide', 'YouTube & banners'],
];
$badgeOptions = [
    '⭐⭐⭐⭐⭐ 4.9/5 Rating (1,240+ Reviews)',
    '🔥 50% OFF TODAY ONLY',
    '✅ 100% Verified Customer',
    '🏆 #1 Bestseller in 2026',
    '🚚 Free Express Shipping Worldwide',
];

$curStyle    = (string)($_POST['ugc_style'] ?? ($generatedUgc['ugc_style'] ?? 'selfie_hold'));
$curPersona  = (string)($_POST['creator_persona'] ?? ($generatedUgc['creator_persona'] ?? 'gen_z'));
$curAspect   = (string)($_POST['aspect_ratio'] ?? ($generatedUgc['aspect_ratio'] ?? '1:1'));
$curBadge    = (string)($_POST['badge_text'] ?? ($generatedUgc['badge_text'] ?? $badgeOptions[0]));
$curProduct  = (string)($_POST['product_name'] ?? ($generatedUgc['product_name'] ?? ''));
$curAudience = (string)($_POST['target_audience'] ?? '');
$curHeadline = (string)($_POST['headline'] ?? ($generatedUgc['hook_headline'] ?? ''));

// Editor entry point. The image is fetched through media-proxy so the canvas
// stays untainted — cross-origin sources would otherwise make the Download
// button throw a SecurityError on toDataURL().
$editorSrc      = (string)($_GET['img'] ?? '');
$editorHeadline = (string)($_GET['headline'] ?? '');
$editorBadge    = (string)($_GET['badge'] ?? '');
if ($editorSrc === '' && $generatedUgc) {
    $editorSrc      = (string)($generatedUgc['image_url'] ?? '');
    $editorHeadline = (string)($generatedUgc['hook_headline'] ?? $editorHeadline);
    $editorBadge    = (string)($generatedUgc['badge_text'] ?? $editorBadge);
}
$editorProxy = $editorSrc !== '' ? url('media-proxy', ['url' => $editorSrc]) : '';
?>

<?php if ($activeTab === 'image_editor'): ?>
<style>.page { padding-top: 14px; }</style>

<div class="w-100">
  <div class="cv-editor" id="cvEditor"
       data-csrf="<?= e(csrf_token()) ?>"
       data-seed-headline="<?= e($editorHeadline) ?>"
       data-seed-badge="<?= e($editorBadge) ?>">

    <!-- ── Top toolbar ─────────────────────────────────────── -->
    <div class="cv-topbar">
      <a class="cv-tbtn" href="<?= url('ugc-generator', ['tab' => 'creative_gallery']) ?>" title="Back to my images">
        <i class="ph ph-arrow-left"></i>
      </a>
      <span class="brand"><i class="ph ph-paint-brush-broad"></i> Design editor</span>
      <span class="sep"></span>

      <button type="button" class="cv-tbtn" id="cvUndo" title="Undo (Ctrl+Z)" disabled><i class="ph ph-arrow-counter-clockwise"></i></button>
      <button type="button" class="cv-tbtn" id="cvRedo" title="Redo (Ctrl+Shift+Z)" disabled><i class="ph ph-arrow-clockwise"></i></button>
      <span class="sep"></span>

      <button type="button" class="cv-tbtn" id="cvZoomOut" title="Zoom out"><i class="ph ph-minus"></i></button>
      <span class="zoomlbl" id="cvZoomLbl">100%</span>
      <button type="button" class="cv-tbtn" id="cvZoomIn" title="Zoom in"><i class="ph ph-plus"></i></button>
      <button type="button" class="cv-tbtn" id="cvZoomFit" title="Fit to screen"><i class="ph ph-corners-out"></i></button>

      <span class="grow"></span>

      <button type="button" class="cv-tbtn" id="cvDuplicate" title="Duplicate (Ctrl+D)" disabled><i class="ph ph-copy"></i></button>
      <button type="button" class="cv-tbtn" id="cvDelete" title="Delete (Del)" disabled><i class="ph ph-trash"></i></button>
      <span class="sep"></span>
      <button type="button" class="cv-tbtn pri" id="cvDownload"><i class="ph ph-download-simple"></i> Download PNG</button>
    </div>

    <!-- ── Left tool rail ──────────────────────────────────── -->
    <div class="cv-rail" id="cvRail">
      <button type="button" class="cv-tool on" data-tool="templates"><i class="ph ph-layout"></i> Templates</button>
      <button type="button" class="cv-tool" data-tool="text"><i class="ph ph-text-aa"></i> Text</button>
      <button type="button" class="cv-tool" data-tool="elements"><i class="ph ph-shapes"></i> Elements</button>
      <button type="button" class="cv-tool" data-tool="adjust"><i class="ph ph-sliders-horizontal"></i> Adjust</button>
      <button type="button" class="cv-tool" data-tool="photo"><i class="ph ph-image"></i> Photo</button>
      <button type="button" class="cv-tool" data-tool="layers"><i class="ph ph-stack"></i> Layers</button>
    </div>

    <!-- ── Left drawer (tool panel) ────────────────────────── -->
    <div class="cv-drawer on" id="cvDrawer"></div>

    <!-- ── Artboard ────────────────────────────────────────── -->
    <div class="cv-viewport" id="cvViewport">
      <div class="cv-artboard" id="cvArtboard">
        <?php if ($editorProxy !== ''): ?>
          <img class="cv-bg" id="cvBg" src="<?= e($editorProxy) ?>" alt="Creative being edited"
               crossorigin="anonymous" referrerpolicy="no-referrer">
        <?php endif; ?>
      </div>
      <div class="fl-overlay" id="cvOverlay">
        <div class="spin"></div>
        <div class="t" id="cvOverlayT">Rendering…</div>
        <div class="s" id="cvOverlayS">Drawing your design at full resolution.</div>
        <div class="fl-progress" style="width:220px"><i id="cvOverlayBar" style="width:10%"></i></div>
      </div>
    </div>

    <!-- ── Right inspector ─────────────────────────────────── -->
    <div class="cv-inspector" id="cvInspector"></div>
  </div>

  <?php if ($editorProxy === ''): ?>
    <div class="fl-alert warn" style="margin-top:16px">
      <i class="ph ph-warning-circle"></i>
      <div>
        <b>No image loaded yet.</b>
        <a href="<?= url('ugc-generator', ['tab' => 'generate_image']) ?>">Generate a creative</a>
        or open one from <a href="<?= url('ugc-generator', ['tab' => 'creative_gallery']) ?>">My images</a>
        and press <b>Edit</b>. The canvas is ready for text and shapes either way.
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
<?= file_get_contents(__DIR__ . '/assets/js/image-editor.js') ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; exit; ?>
<?php endif; ?>

<style>
/* The generate / gallery surfaces reuse the shared studio design system in
   assets/css/studio-ui.css. Only page-specific tweaks live here. */
.page { padding-bottom: 40px; }
</style>

<div class="w-100">

  <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
      <h1 class="page-title mb-1"><i class="ph-bold ph-sparkle text-accent me-2"></i>AI Studio</h1>
      <p class="page-subtitle mb-0">Describe your product, pick a look, and get ad-ready images and videos in one place.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="pill"><i class="ph ph-coins"></i> Credits <b style="color:var(--accent);font-size:14px"><?= number_format($aiCredits) ?></b></span>
      <a href="<?= url('ugc-video-studio') ?>" class="fl-act"><i class="ph ph-film"></i> Video Studio</a>
      <a href="<?= url('post-creator') ?>" class="fl-act"><i class="ph ph-share-network"></i> Post</a>
      <a href="<?= url('create-ad') ?>" class="fl-act pri"><i class="ph ph-megaphone"></i> Launch Ad</a>
    </div>
  </div>

  <div class="fl-shell fl-3">

    <!-- ── Left rail: where am I? ─────────────────────────── -->
    <nav class="fl-rail">
      <div class="fl-lbl">Studio</div>
      <div class="fl-nav">
        <a href="?tab=generate_image" class="<?= $activeTab === 'generate_image' ? 'on' : '' ?>">
          <i class="ph ph-image-square"></i> Create image
        </a>
        <a href="?tab=creative_gallery" class="<?= $activeTab === 'creative_gallery' ? 'on' : '' ?>">
          <i class="ph ph-squares-four"></i> My images
        </a>
        <a href="<?= url('ugc-video-studio') ?>">
          <i class="ph ph-film"></i> Create video
        </a>
      </div>
      <div class="fl-lbl">Try an example</div>
      <div class="fl-nav" id="flowInspirationChips">
        <a href="#" onclick="applyFlowPreset('serum');return false;"><i class="ph ph-drop"></i> Skincare serum</a>
        <a href="#" onclick="applyFlowPreset('earbuds');return false;"><i class="ph ph-headphones"></i> Wireless earbuds</a>
        <a href="#" onclick="applyFlowPreset('energy');return false;"><i class="ph ph-lightning"></i> Energy drink</a>
        <a href="#" onclick="applyFlowPreset('sneakers');return false;"><i class="ph ph-sneaker-move"></i> Running shoes</a>
        <a href="#" onclick="applyFlowPreset('saas');return false;"><i class="ph ph-desktop"></i> Software app</a>
        <a href="#" onclick="applyFlowPreset('coffee');return false;"><i class="ph ph-coffee"></i> Cold brew</a>
      </div>
    </nav>

    <!-- ── Middle: the controls ───────────────────────────── -->
    <section class="fl-panel">

      <?php if ($success): ?>
        <div class="fl-alert ok"><i class="ph ph-check-circle" style="font-size:18px"></i><span><?= e($success) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="fl-alert bad"><i class="ph ph-warning-circle" style="font-size:18px"></i>
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if ($activeTab === 'generate_image'): ?>
        <form method="POST" id="ugcGenForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="generate_ugc_image">

          <div class="fl-lbl"><span class="step">1</span> What are you selling?</div>
          <input type="text" id="ugcProductName" name="product_name" class="fl-in" required
                 placeholder="e.g. Luxe Radiance Facial Serum" value="<?= e($curProduct) ?>">
          <p class="fl-help">The product, brand or service name. Example: “AquaBottle Pro”.</p>

          <div class="fl-lbl"><span class="step">2</span> What shape do you need?</div>
          <div class="fl-chips">
            <?php foreach ($aspectOptions as $val => [$label, $use]): ?>
              <label class="fl-chip <?= $curAspect === $val ? 'on' : '' ?>">
                <input type="radio" name="aspect_ratio" value="<?= e($val) ?>" class="d-none" <?= $curAspect === $val ? 'checked' : '' ?>>
                <?= e($val) ?> · <?= e($label) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="fl-help">Square for feed posts, vertical for Reels and TikTok, wide for YouTube.</p>

          <div class="fl-lbl"><span class="step">3</span> Where is the photo taken?</div>
          <?php foreach ($styleOptions as $val => [$label, $desc]): ?>
            <label class="fl-opt <?= $curStyle === $val ? 'on' : '' ?>">
              <input type="radio" name="ugc_style" value="<?= e($val) ?>" class="d-none" <?= $curStyle === $val ? 'checked' : '' ?>>
              <b><?= e($label) ?></b><span><?= e($desc) ?></span>
            </label>
          <?php endforeach; ?>

          <div class="fl-lbl"><span class="step">4</span> Who is in the photo?</div>
          <div class="fl-chips">
            <?php foreach ($personaOptions as $val => [$label, $desc]): ?>
              <label class="fl-chip <?= $curPersona === $val ? 'on' : '' ?>">
                <input type="radio" name="creator_persona" value="<?= e($val) ?>" class="d-none" <?= $curPersona === $val ? 'checked' : '' ?>>
                <?= e($label) ?> · <?= e($desc) ?>
              </label>
            <?php endforeach; ?>
          </div>

          <details class="fl-disc" <?= ($curAudience !== '' || $curHeadline !== '') ? 'open' : '' ?>>
            <summary><i class="ph ph-plus-circle"></i> Optional extras <i class="ph ph-caret-down caret"></i></summary>
            <div class="body">
              <label class="fl-label" for="ugcAudience">Who is it for?</label>
              <input type="text" id="ugcAudience" name="target_audience" class="fl-in" style="margin-bottom:10px"
                     placeholder="e.g. busy students who need focus" value="<?= e($curAudience) ?>">
              <label class="fl-label" for="ugcHeadline">Text overlay on the image</label>
              <input type="text" id="ugcHeadline" name="headline" class="fl-in"
                     placeholder="e.g. My skin in 14 days" value="<?= e($curHeadline) ?>">
              <p class="fl-help">Leave these blank if you are not sure — we will work without them.</p>

              <label class="fl-label" for="ugcBadgeSelect">Trust badge</label>
              <select id="ugcBadgeSelect" name="badge_text" class="fl-in">
                <?php foreach ($badgeOptions as $b): ?>
                  <option value="<?= e($b) ?>" <?= $curBadge === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </details>

          <button type="submit" class="fl-go" id="stGenerateBtn">
            <i class="ph-bold ph-sparkle"></i> <span>Generate image</span>
          </button>
          <p class="fl-help" style="text-align:center;margin-top:10px">Takes about 10 seconds. Uses 5 credits.</p>
        </form>

      <?php else: ?>
        <div class="fl-lbl">Your library</div>
        <p class="fl-help" style="margin:0 0 14px">
          Everything you have created is saved here automatically. Pick one to edit, post, or turn into an ad.
        </p>
        <a href="?tab=generate_image" class="fl-act pri" style="width:100%;justify-content:center">
          <i class="ph ph-plus"></i> Create a new image
        </a>
      <?php endif; ?>
    </section>

    <!-- ── Right: the stage ───────────────────────────────── -->
    <section class="fl-stage">
      <?php if ($activeTab === 'generate_image'): ?>
        <div class="fl-stagehead">
          <span class="t"><i class="ph ph-eye"></i> Preview</span>
          <?php if ($generatedUgc): ?>
            <span class="m">
              <?= e($generatedUgc['aspect_ratio']) ?> · <?= (int)$generatedUgc['width'] ?>×<?= (int)$generatedUgc['height'] ?> · seed <?= e((string)$generatedUgc['seed']) ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="fl-canvaswrap" id="stStage">
          <?php if ($generatedUgc): ?>
            <div style="position:relative;display:inline-block;max-width:100%">
              <?php if (!empty($generatedUgc['badge_text'])): ?>
                <span style="position:absolute;top:14px;left:14px;background:var(--accent);color:#0b111c;font-size:12px;font-weight:800;padding:5px 12px;border-radius:999px;z-index:2"><?= e($generatedUgc['badge_text']) ?></span>
              <?php endif; ?>
              <img id="activeUgcImage" class="fl-img" src="<?= e($generatedUgc['image_url']) ?>" alt="Generated ad image">
              <div style="position:absolute;left:0;right:0;bottom:0;padding:40px 16px 14px;background:linear-gradient(transparent,rgba(0,0,0,.9));border-radius:0 0 12px 12px;z-index:2">
                <b style="display:block;color:#fff;font-size:15px;text-shadow:0 2px 6px rgba(0,0,0,.8)"><?= e($generatedUgc['hook_headline']) ?></b>
                <span style="display:block;color:#cbd5e1;font-size:12.5px;margin-top:3px;text-shadow:0 1px 4px rgba(0,0,0,.8)"><?= e($generatedUgc['ad_copy']) ?></span>
              </div>
            </div>
          <?php else: ?>
            <div class="fl-empty">
              <i class="ph ph-image"></i>
              <h4>Your image appears here</h4>
              <p>Type your product name on the left, or tap one of the examples in the side menu to fill everything in for you.</p>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($generatedUgc): ?>
          <div class="pill-row" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="regenerate_variation">
              <input type="hidden" name="product_name" value="<?= e($generatedUgc['product_name']) ?>">
              <input type="hidden" name="ugc_style" value="<?= e($generatedUgc['ugc_style']) ?>">
              <input type="hidden" name="creator_persona" value="<?= e($generatedUgc['creator_persona']) ?>">
              <input type="hidden" name="aspect_ratio" value="<?= e($generatedUgc['aspect_ratio']) ?>">
              <input type="hidden" name="target_audience" value="<?= e($curAudience) ?>">
              <input type="hidden" name="headline" value="<?= e($generatedUgc['hook_headline']) ?>">
              <input type="hidden" name="badge_text" value="<?= e($generatedUgc['badge_text']) ?>">
              <button type="submit" class="fl-act"><i class="ph ph-arrows-clockwise"></i> Try another version</button>
            </form>
            <a href="?tab=image_editor&img=<?= urlencode($generatedUgc['image_url']) ?>&headline=<?= urlencode($generatedUgc['hook_headline']) ?>&badge=<?= urlencode($generatedUgc['badge_text'] ?? '') ?>" class="fl-act"><i class="ph ph-paint-brush-broad"></i> Edit</a>
            <a href="<?= e($generatedUgc['image_url']) ?>" target="_blank" rel="noopener" download="adsbooster-ugc-<?= time() ?>.png" class="fl-act"><i class="ph ph-download-simple"></i> Download</a>
            <a href="<?= url('post-creator') ?>?media_url=<?= urlencode($generatedUgc['image_url']) ?>&caption=<?= urlencode($generatedUgc['hook_headline'] . "\n\n" . $generatedUgc['ad_copy']) ?>" class="fl-act"><i class="ph ph-paper-plane-tilt"></i> Post to social</a>
            <a href="<?= url('create-ad') ?>?image_url=<?= urlencode($generatedUgc['image_url']) ?>&headline=<?= urlencode($generatedUgc['hook_headline']) ?>&primary_text=<?= urlencode($generatedUgc['ad_copy']) ?>" class="fl-act pri"><i class="ph ph-rocket-launch"></i> Run as an ad</a>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="fl-stagehead">
          <span class="t"><i class="ph ph-images"></i> My images</span>
          <span class="m"><?= count($recentCreatives) ?> saved</span>
        </div>
        <?php if (!empty($recentCreatives)): ?>
          <div class="fl-lib">
            <?php foreach ($recentCreatives as $c):
              $data     = json_decode($c['result_output'] ?? '{}', true) ?: [];
              $img      = (string)($data['image_url'] ?? '');
              $title    = (string)($data['product_name'] ?? 'Image creative');
              $headline = (string)($data['hook_headline'] ?? ($c['prompt'] ?? ''));
              $badgeTxt = (string)($data['badge_text'] ?? '');
            ?>
              <div class="fl-card">
                <?php if ($img): ?>
                  <img src="<?= e($img) ?>" alt="<?= e($title) ?>" loading="lazy">
                <?php else: ?>
                  <div style="height:168px;display:flex;align-items:center;justify-content:center;color:var(--fg3);font-size:30px"><i class="ph ph-image"></i></div>
                <?php endif; ?>
                <div class="bd">
                  <h5><?= e($title) ?></h5>
                  <p><?= e($headline) ?></p>
                  <span style="font-size:11px;color:var(--fg3)"><?= date('M d, Y', strtotime($c['created_at'])) ?> · <?= (int)$c['credits_used'] ?> credits</span>
                </div>
                <?php if ($img): ?>
                  <div class="ft">
                    <a class="pri" href="?tab=image_editor&img=<?= urlencode($img) ?>&headline=<?= urlencode($headline) ?>&badge=<?= urlencode($badgeTxt) ?>"><i class="ph ph-paint-brush-broad"></i> Edit</a>
                    <a href="<?= url('post-creator') ?>?media_url=<?= urlencode($img) ?>&caption=<?= urlencode($headline) ?>">Post</a>
                    <a href="<?= url('create-ad') ?>?image_url=<?= urlencode($img) ?>&headline=<?= urlencode($headline) ?>&primary_text=<?= urlencode($data['ad_copy'] ?? '') ?>">Run ad</a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="fl-canvaswrap">
            <div class="fl-empty">
              <i class="ph ph-images"></i>
              <h4>Nothing saved yet</h4>
              <p>Images you create are kept here automatically so you can reuse them any time. Start with an example from the left menu.</p>
              <a href="?tab=generate_image" class="fl-act pri" style="margin-top:16px"><i class="ph ph-sparkle"></i> Create your first image</a>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
/* ── Chip / option selection (delegated, works for any group) ── */
document.addEventListener('click', function (e) {
  const chip = e.target.closest('.fl-chip, .fl-opt');
  if (!chip || !chip.closest('form')) return;
  const input = chip.querySelector('input[type=radio]');
  if (!input) return;
  const name = input.name;
  chip.closest('form').querySelectorAll('input[name="' + name + '"]').forEach(r => {
    const lab = r.closest('.fl-chip, .fl-opt');
    if (lab) lab.classList.remove('on');
  });
  chip.classList.add('on');
});

/* ── Loading state on generate ── */
const genForm = document.getElementById('ugcGenForm');
if (genForm) {
  genForm.addEventListener('submit', function () {
    const btn = document.getElementById('stGenerateBtn');
    if (btn) { btn.disabled = true; btn.querySelector('span').textContent = 'Generating…'; }
  });
}

/* ── One-click example presets ── */
function applyFlowPreset(type) {
  const presets = {
    serum:   { name:"Luxe Botanical Glow Serum (50ml)", style:"selfie_hold", persona:"luxury_model", aspect:"1:1", audience:"Women 22-45 looking for simple daily hydration", headline:"Glass skin without ten complicated steps", badge:"⭐⭐⭐⭐⭐ 4.9/5 Rating (1,240+ Reviews)" },
    earbuds: { name:"AuraWave ANC Pro Wireless Earbuds", style:"influencer_desk", persona:"tech_reviewer", aspect:"9:16", audience:"Remote workers who need quiet focus", headline:"I replaced my $300 headphones with these", badge:"🏆 #1 Bestseller in 2026" },
    energy:  { name:"VoltClean Organic Energy Drink", style:"selfie_hold", persona:"fitness_athlete", aspect:"9:16", audience:"Students and athletes who need steady focus", headline:"Zero sugar, zero crash", badge:"🔥 50% OFF TODAY ONLY" },
    sneakers:{ name:"StrideZero Cloud-Knit Running Shoes", style:"unboxing", persona:"gen_z", aspect:"1:1", audience:"Runners and city commuters", headline:"20,000 steps and no sore feet", badge:"✅ 100% Verified Customer" },
    saas:    { name:"AdFlow AI Ad Scaling Suite", style:"influencer_desk", persona:"saas_founder", aspect:"16:9", audience:"Online store owners and media buyers", headline:"Scaling ads without wasting budget", badge:"🏆 #1 Bestseller in 2026" },
    coffee:  { name:"VelvetRoast Micro-Batch Cold Brew", style:"lifestyle_kitchen", persona:"homemaker", aspect:"1:1", audience:"Coffee drinkers who want a smoother cup", headline:"The smoothest morning brew you will try", badge:"🚚 Free Express Shipping Worldwide" }
  };
  const p = presets[type];
  const form = document.getElementById('ugcGenForm');
  if (!p || !form) return;

  const setRadio = (name, val) => {
    const r = form.querySelector('input[name="' + name + '"][value="' + val + '"]');
    if (!r) return;
    r.checked = true;
    form.querySelectorAll('input[name="' + name + '"]').forEach(x => {
      const lab = x.closest('.fl-chip, .fl-opt');
      if (lab) lab.classList.toggle('on', x.checked);
    });
  };

  const n = document.getElementById('ugcProductName'); if (n) n.value = p.name;
  const a = document.getElementById('ugcAudience');   if (a) a.value = p.audience;
  const h = document.getElementById('ugcHeadline');   if (h) h.value = p.headline;
  const b = document.getElementById('ugcBadgeSelect');if (b) b.value = p.badge;
  setRadio('ugc_style', p.style);
  setRadio('creator_persona', p.persona);
  setRadio('aspect_ratio', p.aspect);

  if (n) { n.focus(); n.scrollIntoView({behavior:'smooth', block:'center'}); }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
