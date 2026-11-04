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

<?php require_once __DIR__ . '/includes/app_footer.php'; exit; ?>
<?php endif; ?>

<style>
/* The image editor tab reuses assets/css/studio-ui.css; the generate and
   gallery surfaces use the dedicated cinematic flow theme below. */
.page { padding-bottom: 0; }
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/image-studio-flow.css">

<div class="gf-page">
  <div class="gf-topnav" style="position:relative">
    <a href="?tab=generate_image" class="<?= $activeTab === 'generate_image' ? 'on' : '' ?>">
      <i class="ph ph-aperture"></i> Image Studio
    </a>
    <a href="?tab=creative_gallery" class="<?= $activeTab === 'creative_gallery' ? 'on' : '' ?>">
      <i class="ph ph-squares-four"></i> My images
    </a>
    <a href="<?= url('ugc-video-studio') ?>" class="nx">
      <i class="ph ph-film"></i> Video Studio
    </a>
  </div>

  <div class="gf-shell">

    <!-- ── Left: prompt composer ───────────────────────────── -->
    <section class="gf-composer">

      <?php if ($success): ?>
        <div class="gf-alert ok"><i class="ph ph-check-circle" style="font-size:18px"></i><span><?= e($success) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="gf-alert bad"><i class="ph ph-warning-circle" style="font-size:18px"></i>
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if ($activeTab === 'generate_image'): ?>
        <form method="POST" id="ugcGenForm" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="generate_ugc_image">

          <div class="gf-sec">
            <div class="gf-lbl"><span class="gf-secnum">1</span> What are you selling?</div>
            <textarea id="ugcProductName" name="product_name" class="gf-in" rows="2" required
                      placeholder="Describe the product — e.g. Luxe Radiance Facial Serum&#10;(brand, look, what the ad is for)"><?= e($curProduct) ?></textarea>
            <p class="gf-hint">The studio turns your product into a ready-to-run ad creative.</p>
          </div>

          <div class="gf-sec">
            <div class="gf-lbl"><span class="gf-secnum">2</span> Aspect ratio</div>
            <div class="gf-chips gf-aspectgr" style="margin-top:8px">
              <?php foreach ($aspectOptions as $val => [$label, $use]): ?>
                <label class="gf-chip <?= $curAspect === $val ? 'on' : '' ?>">
                  <input type="radio" name="aspect_ratio" value="<?= e($val) ?>" class="d-none" <?= $curAspect === $val ? 'checked' : '' ?>>
                  <i class="ph <?= $val === '9:16' ? 'ph-device-mobile' : ($val === '16:9' ? 'ph-monitor-play' : 'ph-squares-four') ?>"></i>
                  <span><?= e($val) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <p class="gf-hint">Portrait for Reels &amp; TikTok, square for feed posts, wide for YouTube.</p>
          </div>

          <div class="gf-sec">
            <div class="gf-lbl"><span class="gf-secnum">3</span> Scene</div>
            <div class="gf-styles" style="margin-top:8px">
              <?php
              $styleIcons = ['selfie_hold' => 'ph-handbag-simple', 'unboxing' => 'ph-package', 'lifestyle_kitchen' => 'ph-house-line', 'influencer_desk' => 'ph-webcam', 'before_after' => 'ph-swap', 'flatlay' => 'ph-grid-four'];
              foreach ($styleOptions as $val => [$label, $desc]): ?>
                <label class="gf-tile <?= $curStyle === $val ? 'on' : '' ?>">
                  <input type="radio" name="ugc_style" value="<?= e($val) ?>" class="d-none" <?= $curStyle === $val ? 'checked' : '' ?>>
                  <b><i class="ph <?= $styleIcons[$val] ?? 'ph-image' ?>"></i> <?= e($label) ?></b>
                  <span><?= e($desc) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="gf-sec">
            <div class="gf-lbl"><span class="gf-secnum">4</span> Creator persona</div>
            <div class="gf-chips" style="margin-top:8px">
              <?php foreach ($personaOptions as $val => [$label, $desc]): ?>
                <label class="gf-chip <?= $curPersona === $val ? 'on' : '' ?>" title="<?= e($desc) ?>">
                  <input type="radio" name="creator_persona" value="<?= e($val) ?>" class="d-none" <?= $curPersona === $val ? 'checked' : '' ?>>
                  <span><?= e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="gf-sec">
            <details class="gf-disc" <?= ($curAudience !== '' || $curHeadline !== '' || ($generatedUgc || $curBadge !== $badgeOptions[0])) ? 'open' : '' ?>>
              <summary><i class="ph ph-plus-circle"></i> Overlay &amp; audience <i class="ph ph-caret-down gf-caret"></i></summary>
              <div class="gf-discbody">
                <div class="gf-dl">
                  <div>
                    <label for="ugcAudience">Who is it for?</label>
                    <input type="text" id="ugcAudience" name="target_audience" class="gf-in"
                           placeholder="e.g. busy students who need focus" value="<?= e($curAudience) ?>">
                  </div>
                  <div>
                    <label for="ugcHeadline">Text overlay</label>
                    <input type="text" id="ugcHeadline" name="headline" class="gf-in"
                           placeholder="e.g. My skin in 14 days" value="<?= e($curHeadline) ?>">
                  </div>
                </div>
                <div style="margin-top:12px">
                  <label for="ugcBadgeSelect" style="font-size:11px;font-weight:700;color:var(--gf-fg2);display:block;margin-bottom:5px;letter-spacing:.02em">Trust badge</label>
                  <select id="ugcBadgeSelect" name="badge_text" class="gf-in">
                    <?php foreach ($badgeOptions as $b): ?>
                      <option value="<?= e($b) ?>" <?= $curBadge === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <p class="gf-hint">Leave blank if unsure — the studio fills reasonable defaults.</p>
              </div>
            </details>
          </div>

          <button type="submit" class="gf-go" id="stGenerateBtn">
            <i class="ph-bold ph-sparkle"></i> <span>Generate</span>
          </button>
          <p class="gf-foot">~10 seconds · 5 AI credits</p>

          <div class="gf-insp">
            <a href="#" onclick="applyFlowPreset('serum');return false;"><i class="ph ph-drop"></i>Skincare serum</a>
            <a href="#" onclick="applyFlowPreset('earbuds');return false;"><i class="ph ph-headphones"></i>Earbuds</a>
            <a href="#" onclick="applyFlowPreset('energy');return false;"><i class="ph ph-lightning"></i>Energy drink</a>
            <a href="#" onclick="applyFlowPreset('sneakers');return false;"><i class="ph ph-sneaker-move"></i>Running shoes</a>
            <a href="#" onclick="applyFlowPreset('saas');return false;"><i class="ph ph-desktop"></i>Software app</a>
            <a href="#" onclick="applyFlowPreset('coffee');return false;"><i class="ph ph-coffee"></i>Cold brew</a>
          </div>
        </form>
      <?php else: ?>
        <div class="gf-sec" style="text-align:center">
          <i class="ph ph-plus" style="font-size:34px;color:var(--gf-fg3);display:inline-block;border:1px solid var(--gf-border);border-radius:18px;padding:18px 22px;margin-bottom:16px"></i>
          <div class="gf-lbl" style="justify-content:center">Everything you generate is saved here automatically.</div>
          <p class="gf-hint" style="margin-bottom:16px">Pick any image to edit, post, or turn into a campaign.</p>
          <a href="?tab=generate_image" class="gf-act pri" style="width:100%;justify-content:center">
            <i class="ph ph-sparkle"></i> Create a new image
          </a>
        </div>
      <?php endif; ?>
    </section>

    <!-- ── Right: the stage ───────────────────────────────── -->
    <section class="gf-stage">
      <div class="gf-stagehead">
        <span class="t"><i class="ph <?= $activeTab === 'generate_image' ? 'ph-image' : 'ph-squares-four' ?>"></i> <?= $activeTab === 'generate_image' ? 'Preview' : 'My images' ?></span>
        <?php if ($activeTab === 'generate_image' && $generatedUgc): ?>
          <span class="m">UGGC-1 · <?= e($generatedUgc['aspect_ratio']) ?> · <?= (int)$generatedUgc['width'] ?>×<?= (int)$generatedUgc['height'] ?> · seed <?= e((string)$generatedUgc['seed']) ?></span>
        <?php elseif ($activeTab === 'generate_image'): ?>
          <span class="m">waiting for your prompt</span>
        <?php else: ?>
          <span class="m"><?= count($recentCreatives) ?> saved</span>
        <?php endif; ?>
      </div>

      <?php if ($activeTab === 'generate_image'): ?>
        <div class="gf-canvas" id="stStage">
          <?php if ($generatedUgc): ?>
            <div class="gf-hero">
              <?php if (!empty($generatedUgc['badge_text'])): ?>
                <span class="gf-badge"><?= e($generatedUgc['badge_text']) ?></span>
              <?php endif; ?>
              <img id="activeUgcImage" src="<?= e($generatedUgc['image_url']) ?>" alt="Generated ad image">
              <div class="gf-caption">
                <b><?= e($generatedUgc['hook_headline']) ?></b>
                <span><?= e($generatedUgc['ad_copy']) ?></span>
              </div>
            </div>
          <?php else: ?>
            <div class="gf-empty">
              <i class="ph ph-aperture"></i>
              <h4>Your image appears here</h4>
              <p>Describe your product on the left and hit Generate — the studio renders a full ad creative in about ten seconds.</p>
              <div class="pf-keys">
                <span><i class="ph ph-handbag-simple" style="vertical-align:-2px"></i> Selfie hold</span>
                <span><i class="ph ph-package" style="vertical-align:-2px"></i> Unboxing</span>
                <span><i class="ph ph-grid-four" style="vertical-align:-2px"></i> Flat lay</span>
                <span><i class="ph ph-swap" style="vertical-align:-2px"></i> Before / after</span>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($generatedUgc): ?>
          <div class="gf-actions">
            <form method="POST" style="display:contents">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="regenerate_variation">
              <input type="hidden" name="product_name" value="<?= e($generatedUgc['product_name']) ?>">
              <input type="hidden" name="ugc_style" value="<?= e($generatedUgc['ugc_style']) ?>">
              <input type="hidden" name="creator_persona" value="<?= e($generatedUgc['creator_persona']) ?>">
              <input type="hidden" name="aspect_ratio" value="<?= e($generatedUgc['aspect_ratio']) ?>">
              <input type="hidden" name="target_audience" value="<?= e($curAudience) ?>">
              <input type="hidden" name="headline" value="<?= e($generatedUgc['hook_headline']) ?>">
              <input type="hidden" name="badge_text" value="<?= e($generatedUgc['badge_text']) ?>">
              <button type="submit" class="gf-act"><i class="ph ph-arrows-clockwise"></i> New variation</button>
            </form>
            <a href="?tab=image_editor&img=<?= urlencode($generatedUgc['image_url']) ?>&headline=<?= urlencode($generatedUgc['hook_headline']) ?>&badge=<?= urlencode($generatedUgc['badge_text'] ?? '') ?>" class="gf-act"><i class="ph ph-paint-brush-broad"></i> Edit</a>
            <a href="<?= e($generatedUgc['image_url']) ?>" target="_blank" rel="noopener" download="adsbooster-ugc-<?= time() ?>.png" class="gf-act"><i class="ph ph-download-simple"></i> Download</a>
            <a href="<?= url('post-creator') ?>?media_url=<?= urlencode($generatedUgc['image_url']) ?>&caption=<?= urlencode($generatedUgc['hook_headline'] . "\n\n" . $generatedUgc['ad_copy']) ?>" class="gf-act"><i class="ph ph-paper-plane-tilt"></i> Post</a>
            <a href="<?= url('create-ad') ?>?image_url=<?= urlencode($generatedUgc['image_url']) ?>&headline=<?= urlencode($generatedUgc['hook_headline']) ?>&primary_text=<?= urlencode($generatedUgc['ad_copy']) ?>" class="gf-act pri"><i class="ph ph-rocket-launch"></i> Run as ad</a>
          </div>
        <?php endif; ?>

        <!-- Generation filmstrip — your last creations -->
        <?php if (!empty($recentCreatives)): ?>
          <div class="gf-filmwrap">
            <div class="gf-filmhead">
              <span><i class="ph ph-clock-counter-clockwise" style="vertical-align:-1px"></i> Recent generations</span>
              <a href="?tab=creative_gallery">View all <i class="ph ph-arrow-right" style="vertical-align:-2px"></i></a>
            </div>
            <div class="gf-film">
              <?php foreach ($recentCreatives as $c):
                $data = json_decode($c['result_output'] ?? '{}', true) ?: [];
                $img  = (string)($data['image_url'] ?? '');
                if (!$img) continue;
                $title = (string)($data['product_name'] ?? 'Image creative');
                $isCur = $generatedUgc && ($generatedUgc['image_url'] ?? '') === $img;
              ?>
                <div class="gf-thumb <?= $isCur ? 'cur' : '' ?>">
                  <img src="<?= e($img) ?>" alt="<?= e($title) ?>" loading="lazy">
                  <div class="gf-ov">
                    <a href="?tab=image_editor&img=<?= urlencode($img) ?>" title="Edit"><i class="ph ph-paint-brush-broad"></i></a>
                    <a href="<?= url('post-creator') ?>?media_url=<?= urlencode($img) ?>" title="Post"><i class="ph ph-paper-plane-tilt"></i></a>
                    <a href="<?= url('create-ad') ?>?image_url=<?= urlencode($img) ?>" title="Run as ad"><i class="ph ph-rocket-launch"></i></a>
                  </div>
                  <div class="tt"><?= e($title) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="gf-canvas" style="min-height:auto;padding:26px;align-items:stretch;display:block">
          <?php if (!empty($recentCreatives)): ?>
            <div class="gf-grid">
              <?php foreach ($recentCreatives as $c):
                $data     = json_decode($c['result_output'] ?? '{}', true) ?: [];
                $img      = (string)($data['image_url'] ?? '');
                $title    = (string)($data['product_name'] ?? 'Image creative');
                $headline = (string)($data['hook_headline'] ?? ($c['prompt'] ?? ''));
                $badgeTxt = (string)($data['badge_text'] ?? '');
              ?>
                <div class="gf-gcard">
                  <?php if ($img): ?>
                    <img src="<?= e($img) ?>" alt="<?= e($title) ?>" loading="lazy">
                  <?php else: ?>
                    <div style="height:165px;display:flex;align-items:center;justify-content:center;color:var(--gf-fg3);font-size:32px"><i class="ph ph-image"></i></div>
                  <?php endif; ?>
                  <div class="bd">
                    <h5><?= e($title) ?></h5>
                    <p><?= e($headline) ?></p>
                  </div>
                  <?php if ($img): ?>
                    <div class="ft">
                      <a class="pri" href="?tab=image_editor&img=<?= urlencode($img) ?>&headline=<?= urlencode($headline) ?>&badge=<?= urlencode($badgeTxt) ?>"><i class="ph ph-paint-brush-broad"></i> Edit</a>
                      <a href="<?= url('post-creator') ?>?media_url=<?= urlencode($img) ?>&caption=<?= urlencode($headline) ?>"><i class="ph ph-paper-plane-tilt"></i> Post</a>
                      <a href="<?= url('create-ad') ?>?image_url=<?= urlencode($img) ?>&headline=<?= urlencode($headline) ?>&primary_text=<?= urlencode($data['ad_copy'] ?? '') ?>"><i class="ph ph-rocket-launch"></i> Ad</a>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="gf-empty" style="padding:30px 10px">
              <i class="ph ph-squares-four"></i>
              <h4>Nothing saved yet</h4>
              <p>Every creative you generate lands here. Start with an example preset from the composer.</p>
              <a href="?tab=generate_image" class="gf-act pri" style="margin-top:4px"><i class="ph ph-sparkle"></i> Create your first image</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
/* ── Chip / tile selection (delegated, covers persona chips too) ── */
document.addEventListener('click', function (e) {
  const pick = e.target.closest('.gf-chip, .gf-tile');
  if (!pick) return;
  const input = pick.querySelector('input[type=radio]');
  if (!input || !input.name) return;
  document.querySelectorAll('input[name="' + input.name + '"]').forEach(r => {
    const lab = r.closest('.gf-chip, .gf-tile');
    if (lab) lab.classList.toggle('on', r.checked);
  });
  pick.classList.add('on');
});

/* Persona chips have no visual "on" until one is clicked; keep the styled
   default (Lexi) in sync with the checked radio on load. */
document.querySelectorAll('input[name="creator_persona"]:checked').forEach(r => {
  const lab = r.closest('.gf-chip');
  if (lab) lab.classList.add('on');
});
document.querySelectorAll('input[name="aspect_ratio"]:checked, input[name="ugc_style"]:checked').forEach(r => {
  const lab = r.closest('.gf-chip, .gf-tile');
  if (lab && !lab.classList.contains('on')) lab.classList.add('on');
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
    serum:   { name:"Luxe Botanical Glow Serum (50ml)\nA glass skincare bottle with botanical styling, photographed for a paid social ad.", style:"selfie_hold", persona:"luxury_model", aspect:"1:1", audience:"Women 22-45 looking for simple daily hydration", headline:"Glass skin without ten complicated steps", badge:"⭐⭐⭐⭐⭐ 4.9/5 Rating (1,240+ Reviews)" },
    earbuds: { name:"AuraWave ANC Pro Wireless Earbuds\nMatte black charging case, studio desk setup.", style:"influencer_desk", persona:"tech_reviewer", aspect:"9:16", audience:"Remote workers who need quiet focus", headline:"I replaced my $300 headphones with these", badge:"🏆 #1 Bestseller in 2026" },
    energy:  { name:"VoltClean Organic Energy Drink\nSlim aluminum can, condensation, gym lighting.", style:"selfie_hold", persona:"fitness_athlete", aspect:"9:16", audience:"Students and athletes who need steady focus", headline:"Zero sugar, zero crash", badge:"🔥 50% OFF TODAY ONLY" },
    sneakers:{ name:"StrideZero Cloud-Knit Running Shoes\nKnit upper, street colors, city backdrop.", style:"unboxing", persona:"gen_z", aspect:"1:1", audience:"Runners and city commuters", headline:"20,000 steps and no sore feet", badge:"✅ 100% Verified Customer" },
    saas:    { name:"AdFlow AI Ad Scaling Suite\nDark dashboard on a laptop for a B2B ad.", style:"influencer_desk", persona:"saas_founder", aspect:"16:9", audience:"Online store owners and media buyers", headline:"Scaling ads without wasting budget", badge:"🏆 #1 Bestseller in 2026" },
    coffee:  { name:"VelvetRoast Micro-Batch Cold Brew\nFrosted glass bottle, morning kitchen light.", style:"lifestyle_kitchen", persona:"homemaker", aspect:"1:1", audience:"Coffee drinkers who want a smoother cup", headline:"The smoothest morning brew you will try", badge:"🚚 Free Express Shipping Worldwide" }
  };
  const p = presets[type];
  const form = document.getElementById('ugcGenForm');
  if (!p || !form) return;

  const setRadio = (name, val) => {
    const r = form.querySelector('input[name="' + name + '"][value="' + val + '"]');
    if (!r) return;
    r.checked = true;
    form.querySelectorAll('input[name="' + name + '"]').forEach(x => {
      const lab = x.closest('.gf-chip, .gf-tile');
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

  if (n) { n.focus(); n.scrollIntoView({behavior:'smooth', block:'start'}); }
  const details = form.querySelector('details.gf-disc'); if (details) details.open = true;
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
