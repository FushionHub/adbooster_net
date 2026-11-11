<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Post Creator Studio — ' . get_setting('site_name', 'Adsbooster.net');

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ai_engine.php';
require_once __DIR__ . '/modules/social_post_engine.php';

$userId   = current_user_id();
$userData = current_user();
$aiCredits = (int)($userData['ai_credits'] ?? 0);

// ── AJAX: AI post illustration (Google Gemini — Nano Banana 2 first) ──
// Returns JSON; the composer fills the attached-media card so the image can
// be published live or scheduled to any connected social account.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_illustration') {
    header('Content-Type: application/json');
    if (!csrf_verify()) {
        echo json_encode(['success' => false, 'error' => 'Your session expired. Refresh the page and try again.']);
        exit;
    }
    $prompt = trim((string)($_POST['illustration_prompt'] ?? ''));
    if ($prompt === '') {
        echo json_encode(['success' => false, 'error' => 'Describe the illustration you want first.']);
        exit;
    }

    $illCost = 5; // matches AIEngine::IMAGE_CREDIT_COST
    if ($aiCredits < $illCost) {
        echo json_encode(['success' => false, 'error' => "Not enough AI credits — this image costs {$illCost}. Top up in AI Settings."]);
        exit;
    }

    require_once __DIR__ . '/modules/gemini_engine.php';
    $styled = $prompt . '. Scroll-stopping social media post illustration, bold vibrant colors, high contrast, professional marketing visual, clean composition, ultra detailed, no watermark, no text overlays.';
    $img = GeminiEngine::generateImage($styled, 1024, 1024, 'post_' . $userId . '_' . time());

    if ($img === null) {
        echo json_encode(['success' => false, 'error' => 'The image engine is unavailable right now. Attach a file or pick from the AI Library instead.']);
        exit;
    }

    db()->query("UPDATE users SET ai_credits = GREATEST(0, ai_credits - ?) WHERE id = ?", [$illCost, $userId]);
    echo json_encode([
        'success' => true,
        'url'     => $img['url'],
        'model'   => $img['model'],
        'credits' => max(0, $aiCredits - $illCost),
    ]);
    exit;
}

// Gateway-shaped connection detection (Bundle.social organic profiles only —
// the legacy `platform`/`access_token` columns no longer exist).
$connections = db()->fetchAll(
    "SELECT platform_name, gateway_provider, account_type
     FROM connected_accounts
     WHERE user_id = ? AND status = 'connected'",
    [$userId]
);
$connectedPlatforms = array_values(array_filter(array_column(array_filter(
    $connections,
    static fn(array $c): bool => ($c['gateway_provider'] ?? '') === 'bundle_social'
        && ($c['account_type'] ?? '') === 'organic_profile'
), 'platform_name')));

// A stored 'meta' row covers both Facebook and Instagram channels.
if (in_array('meta', $connectedPlatforms, true)) {
    $connectedPlatforms = array_values(array_unique(array_merge($connectedPlatforms, ['facebook', 'instagram'])));
}

$scheduledPosts = db()->fetchAll(
    "SELECT id, platform, caption, media_type, media_url, scheduled_time, status, bundle_post_id FROM scheduled_posts WHERE user_id = ? AND status IN ('scheduled','published') ORDER BY scheduled_time ASC LIMIT 20",
    [$userId]
);

$userVideos = db()->fetchAll("SELECT * FROM ai_video_creatives WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$userId]);
$userImages = db()->fetchAll("SELECT * FROM ai_creatives WHERE user_id = ? AND type = 'image' ORDER BY created_at DESC LIMIT 20", [$userId]);

$errors = [];
$success = '';
$activeTab = $_GET['tab'] ?? 'manual';
$aiResult = null;

$prefilledMedia = trim($_GET['media_url'] ?? ($_GET['image_url'] ?? ''));
$prefilledCaption = trim($_GET['caption'] ?? '');
$prefilledMediaType = 'image';
$prefilledThumb = $prefilledMedia;
$targetPlatform = trim($_GET['platform'] ?? '');

if (!empty($_GET['creative_id'])) {
    $imgCreative = db()->fetch("SELECT * FROM ai_creatives WHERE id = ? AND user_id = ?", [(int)$_GET['creative_id'], $userId]);
    if ($imgCreative) {
        $data = json_decode($imgCreative['result_output'] ?? '', true) ?: [];
        $imgUrl = $data['image_url'] ?? $imgCreative['result_output'];
        $prefilledMedia = $imgUrl;
        $prefilledMediaType = 'image';
        $prefilledThumb = $imgUrl;
        if (!empty($data['hook_headline']) || !empty($data['ad_copy'])) {
            $prefilledCaption = trim(($data['hook_headline'] ?? '') . "\n\n" . ($data['ad_copy'] ?? ''));
        }
    }
}

if (!empty($_GET['video_id'])) {
    $videoCreative = db()->fetch("SELECT * FROM ai_video_creatives WHERE id = ? AND user_id = ?", [(int)$_GET['video_id'], $userId]);
    if ($videoCreative) {
        $prefilledMedia = $videoCreative['video_url'];
        $prefilledMediaType = 'video';
        $prefilledThumb = $videoCreative['thumbnail_url'] ?? 'assets/images/thumbs/about-five-img1.png';
        $prefilledCaption = $videoCreative['hook_text'] . "\n\n" . $videoCreative['script_body'] . "\n\n" . ($videoCreative['cta_text'] ?? '');
    }
}

// Handle AI Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_ai') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $prompt   = trim($_POST['ai_prompt'] ?? '');
        $platform = trim($_POST['ai_platform'] ?? '');

        if ($prompt === '') {
            $errors[] = 'Please enter a prompt.';
        } elseif ($platform === '') {
            $errors[] = 'Please select a platform.';
        } else {
            $result = AIEngine::generateText($prompt, $userId);
            if ($result['success']) {
                $aiResult = $result['result'];
                $aiCredits = $result['credits_remaining'];
                $success = $result['message'];
            } else {
                $errors[] = $result['message'];
                $aiCredits = $result['credits_remaining'];
            }
        }
    }
}

// Handle Real-Time Live Publish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_now') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $caption   = trim($_POST['caption'] ?? '');
        $platforms = (array)($_POST['platforms'] ?? []);
        if (empty($platforms) && !empty($_POST['platform'])) {
            $platforms = [trim($_POST['platform'])];
        }
        $mediaType = trim($_POST['media_type'] ?? 'image');
        $mediaUrl  = trim($_POST['existing_media_url'] ?? '');

        if (!empty($_FILES['media_upload']['tmp_name']) && $_FILES['media_upload']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['media_upload']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','mp4','mov','pdf'];
            if (in_array($ext, $allowed)) {
                $uploadDir = ROOT_PATH . '/uploads/posts/' . date('Y/m');
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = bin2hex(random_bytes(12)) . '.' . $ext;
                move_uploaded_file($_FILES['media_upload']['tmp_name'], $uploadDir . '/' . $filename);
                $mediaUrl = 'uploads/posts/' . date('Y/m') . '/' . $filename;
                $mediaType = in_array($ext, ['mp4','mov']) ? 'video' : 'image';
            }
        }

        if ($caption === '') {
            $errors[] = 'Please enter a caption or copy for your post.';
        } elseif (empty($platforms)) {
            $errors[] = 'Please select at least one social media channel to publish to.';
        } else {
            $multiRes = SocialPostEngine::publishMultiLive($userId, $platforms, $caption, $mediaUrl, $mediaType);
            $successChans = [];
            $failedChans = [];

            foreach ($multiRes['results'] as $plat => $r) {
                if (!empty($r['success'])) {
                    $successChans[] = ucfirst($plat) . " (post #{$r['post_id']} queued)";
                } else {
                    $failedChans[] = ucfirst($plat) . ": " . ($r['error'] ?? 'Could not queue');
                }
            }

            if (!empty($successChans)) {
                $success = "🚀 Publish queued! Background workers are dispatching to Bundle.social now — Channels: " . implode(', ', $successChans);
            }
            if (!empty($failedChans)) {
                $errors[] = "Some channels could not be queued: " . implode(' | ', $failedChans);
            }

            $scheduledPosts = db()->fetchAll(
                "SELECT id, platform, caption, media_type, media_url, scheduled_time, status, bundle_post_id FROM scheduled_posts WHERE user_id = ? AND status IN ('scheduled','published') ORDER BY created_at DESC LIMIT 20",
                [$userId]
            );
        }
    }
}

// Handle Schedule Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $caption      = trim($_POST['caption'] ?? '');
        $platform     = trim($_POST['platform'] ?? '');
        $mediaType    = trim($_POST['media_type'] ?? 'image');
        $scheduledAt  = trim($_POST['scheduled_at'] ?? '');
        $mediaUrl     = trim($_POST['existing_media_url'] ?? '');

        if ($caption === '') $errors[] = 'Caption is required.';
        if ($platform === '') $errors[] = 'Please select a platform.';
        if ($scheduledAt === '') $errors[] = 'Please choose a scheduled time.';

        if (!empty($_FILES['media_upload']['tmp_name']) && $_FILES['media_upload']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['media_upload']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','mp4','mov','pdf'];
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Invalid file type.';
            } else {
                $uploadDir = ROOT_PATH . '/uploads/posts/' . date('Y/m');
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = bin2hex(random_bytes(12)) . '.' . $ext;
                move_uploaded_file($_FILES['media_upload']['tmp_name'], $uploadDir . '/' . $filename);
                $mediaUrl = 'uploads/posts/' . date('Y/m') . '/' . $filename;
                $mediaType = in_array($ext, ['mp4','mov']) ? 'video' : 'image';
            }
        }

        if (empty($errors)) {
            try {
                SocialPostEngine::createPost(
                    $userId,
                    $platform,
                    $caption,
                    $mediaUrl ?: null,
                    $mediaType,
                    $scheduledAt
                );
                $success = 'Post queued into scheduled pipeline successfully!';
                $scheduledPosts = db()->fetchAll(
                    "SELECT id, platform, caption, media_type, media_url, scheduled_time, status, bundle_post_id FROM scheduled_posts WHERE user_id = ? AND status IN ('scheduled','published') ORDER BY scheduled_time ASC LIMIT 20",
                    [$userId]
                );
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Handle Cancel Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $postId = (int)($_POST['post_id'] ?? 0);
        if ($postId > 0) {
            SocialPostEngine::cancelPost($postId);
            $success = 'Post cancelled.';
            $scheduledPosts = db()->fetchAll(
                "SELECT id, platform, caption, media_type, media_url, scheduled_time, status, bundle_post_id FROM scheduled_posts WHERE user_id = ? AND status IN ('scheduled','published') ORDER BY scheduled_time ASC LIMIT 20",
                [$userId]
            );
        }
    }
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">
                <i class="ph-bold ph-paper-plane-tilt text-accent me-2"></i> Post Creator Studio
            </h1>
            <p class="page-subtitle mb-0">Compose, pick generated UGC assets, preview on live smartphone mockups, and publish across all connected channels.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-light rounded-pill px-3 font-size-13" data-bs-toggle="modal" data-bs-target="#postAssetLibraryModal">
                <i class="ph-bold ph-squares-four text-accent me-1"></i> Pick from AI Library
            </button>
            <a href="<?= url('calendar') ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                <i class="ph ph-calendar me-1"></i> Content Calendar
            </a>
        </div>
    </div>

    <?php if ($flashSuccess = get_flash('success')): ?>
        <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-4 p-3 mb-4">
            <?= e($flashSuccess) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-4 p-3 mb-4">
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger rounded-4 p-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Tabs Row -->
    <div class="d-flex gap-2 mb-4 border-bottom border-secondary border-opacity-10 pb-3">
        <a class="btn rounded-pill px-4 py-2 font-size-13 fw-bold <?= $activeTab === 'manual' ? 'btn-accent text-dark' : 'btn-outline-dark text-white' ?>" href="?tab=manual">
            <i class="ph ph-note-pencil me-1"></i> Post Composer &amp; Live Simulation
        </a>
        <a class="btn rounded-pill px-4 py-2 font-size-13 fw-bold <?= $activeTab === 'ai' ? 'btn-accent text-dark' : 'btn-outline-dark text-white' ?>" href="?tab=ai">
            <i class="ph ph-sparkle me-1"></i> AI Copywriter Engine
        </a>
    </div>

    <?php if ($activeTab === 'ai'): ?>
        <!-- AI Generation Tab -->
        <div class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                <div>
                    <h4 class="text-white fw-bold mb-1 font-size-18">AI Content Copywriter</h4>
                    <p class="text-white-50 font-size-13 mb-0">Describe your product or campaign goal to generate high-engagement social media copy with hashtags.</p>
                </div>
                <div style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:8px 16px;text-align:right;">
                    <span style="font-size:11px;color:var(--fg3);text-transform:uppercase;">AI Credits:</span>
                    <span style="color:var(--accent);font-weight:700;font-size:16px;margin-left:6px;"><?= number_format($aiCredits) ?></span>
                </div>
            </div>

            <form method="POST" action="<?= url('post-creator') ?>?tab=ai">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="generate_ai">

                <div class="mb-4">
                    <label for="ai_prompt" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Prompt / Topic Description</label>
                    <textarea id="ai_prompt" name="ai_prompt" rows="4" class="form-control" required placeholder="e.g. Write a viral TikTok caption announcing our 50% flash sale with strong urgency..." style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:14px;"><?= e($_POST['ai_prompt'] ?? '') ?></textarea>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="ai_platform" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Target Platform Style</label>
                        <select id="ai_platform" name="ai_platform" class="form-select" required style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
                            <option value="meta">Meta / Facebook</option>
                            <option value="instagram" selected>Instagram Reel &amp; Feed</option>
                            <option value="tiktok">TikTok Video Script &amp; Caption</option>
                            <option value="twitter">Twitter / X Thread</option>
                            <option value="linkedin">LinkedIn Professional</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn-accent w-100 py-3 font-size-14 font-weight-600 rounded-pill">
                            <i class="ph ph-sparkle me-1"></i> Generate Post Content (1 Credit)
                        </button>
                    </div>
                </div>
            </form>

            <?php if ($aiResult): ?>
                <div style="margin-top:28px;background:#080c14;border:1px solid var(--accent);border-radius:12px;padding:24px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
                        <h6 style="color:#fff;font-weight:700;margin:0;"><i class="ph ph-magic-wand text-warning me-2"></i>Generated Content Output</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="seedIllustrationFromText()">
                                <i class="ph ph-image me-1"></i> Make an illustration
                            </button>
                            <a href="<?= url('post-creator') ?>?tab=manual" class="btn-accent btn-sm rounded-pill px-3" onclick="sessionStorage.setItem('cached_caption', <?= json_encode($aiResult) ?>)">
                                Use in Composer <i class="ph ph-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <pre style="color:#fff;white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.6;margin:0;"><?= e($aiResult) ?></pre>
                </div>
                <script>
                function seedIllustrationFromText(){
                    const text = <?= json_encode($aiResult) ?>;
                    const short = String(text).replace(/\s+/g,' ').trim().slice(0, 220);
                    sessionStorage.setItem('ai_illustration_prompt', short);
                    sessionStorage.setItem('cached_caption', text);
                    window.location.href = <?= json_encode(url('post-creator') . '?tab=manual') ?>;
                }
                </script>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Manual Composer with Live Preview (2-Column Layout) -->
        <div class="row g-4 mb-5">
            <!-- Left Form Column -->
            <div class="col-xl-7 col-lg-7">
                <div class="card-dark p-4 rounded-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                    <form method="POST" action="<?= url('post-creator') ?>?tab=manual" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="existing_media_url" id="inp_existing_media_url" value="<?= e($prefilledMedia) ?>">
                        <input type="hidden" name="media_type" id="inp_media_type" value="<?= e($prefilledMediaType) ?>">

                        <!-- Target Platforms Selector -->
                        <div class="mb-4">
                            <label class="form-label d-block text-white fw-bold font-size-13 mb-2">
                                <i class="ph-bold ph-broadcast text-accent me-1"></i> Target Publishing Channels (Multi-Select)
                            </label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                $channels = [
                                    'meta'     => ['name' => 'Meta / Instagram', 'icon' => 'ph-facebook-logo', 'color' => '#1877F2'],
                                    'tiktok'   => ['name' => 'TikTok',           'icon' => 'ph-tiktok-logo',   'color' => '#00f2fe'],
                                    'twitter'  => ['name' => 'Twitter / X',      'icon' => 'ph-twitter-logo',  'color' => '#1da1f2'],
                                    'linkedin' => ['name' => 'LinkedIn',         'icon' => 'ph-linkedin-logo', 'color' => '#0a66c2'],
                                    // NOTE: paid channels (Snapchat, Google Ads) are owned by the
                                    // Zernio ad gateway and must never appear in the organic
                                    // publishing selector — manage them from create-ad.php.
                                ];
                                foreach ($channels as $key => $info): 
                                    $isConnected = in_array($key, $connectedPlatforms, true);
                                ?>
                                    <label class="btn btn-outline-dark rounded-pill py-2 px-3 text-white font-size-13 d-flex align-items-center gap-2 <?= $isConnected ? 'border-accent' : 'opacity-75' ?>">
                                        <input type="checkbox" name="platforms[]" value="<?= $key ?>" <?= $isConnected ? 'checked' : '' ?> onchange="this.parentElement.classList.toggle('active', this.checked)">
                                        <i class="ph <?= $info['icon'] ?>" style="color:<?= $info['color'] ?>;"></i>
                                        <span><?= $info['name'] ?></span>
                                        <?php if ($isConnected): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success font-size-10 px-1">Live</span>
                                        <?php else: ?>
                                            <a href="<?= url('social-connect') ?>" class="text-white-50 font-size-10 text-decoration-none" title="Connect this account">(Connect)</a>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Caption -->
                        <div class="mb-4">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                <label for="caption" class="form-label" style="font-weight:700;color:#fff;font-size:13px;margin:0;">Post Caption / Message</label>
                                <span style="font-size:11px;color:var(--fg3);">Rich formatting &amp; hashtags</span>
                            </div>
                            <textarea id="caption" name="caption" rows="5" class="form-control" required placeholder="Write your post caption, hashtags, and call to action..." oninput="updatePostPreview()" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:14px;"><?= e($_POST['caption'] ?? $prefilledCaption) ?></textarea>
                        </div>

                        <!-- Attached Media Preview -->
                        <div id="attachedMediaCard" class="mb-4 p-3 rounded-3" style="background:#080c14;border:1px solid rgba(196,240,0,0.3);<?= empty($prefilledMedia) ? 'display:none;' : '' ?>">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-white font-size-13 fw-bold" id="attachedMediaTitle">
                                    <i class="ph-bold ph-image text-accent me-1"></i> Attached Creative Asset
                                </span>
                                <span class="badge bg-success text-dark font-size-11 fw-bold" id="attachedMediaTypeBadge"><?= ucfirst($prefilledMediaType) ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <img id="attachedThumbImg" src="<?= BASE_URL ?>/<?= e($prefilledThumb ?: 'assets/images/thumbs/about-five-img1.png') ?>" alt="Media" style="width:64px;height:64px;object-fit:cover;border-radius:10px;border:1px solid rgba(255,255,255,0.1);">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="text-white font-size-12 text-truncate" id="attachedMediaName"><?= e(basename($prefilledMedia)) ?></div>
                                    <div class="text-white-50 font-size-11">Ready for real-time live dispatch</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1" onclick="clearPostMedia()" title="Remove">
                                    <i class="ph ph-x"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Media File Upload and AI Picker -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label mb-0" style="font-weight:600;color:var(--fg);font-size:13px;">Attach Media File (Optional)</label>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#postAssetLibraryModal">
                                    <i class="ph-bold ph-squares-four me-1"></i> Pick from AI Library
                                </button>
                            </div>
                            <div style="border:2px dashed rgba(255,255,255,0.15);border-radius:12px;padding:20px;text-align:center;background:#080c14;cursor:pointer;" onclick="document.getElementById('media_upload').click()">
                                <i class="ph ph-upload-simple" style="font-size:28px;color:var(--accent);margin-bottom:6px;"></i>
                                <div style="font-weight:600;color:#fff;font-size:13.5px;">Click to select file from device or drag here</div>
                                <span style="font-size:11px;color:var(--fg3);">JPG, PNG, WebP, MP4, MOV (Max 50MB)</span>
                                <input type="file" id="media_upload" name="media_upload" accept="image/*,video/*" style="display:none;" onchange="handlePostMedia(this)">
                            </div>
                        </div>

                        <!-- AI Post Image Generator (Google Gemini — Nano Banana 2) -->
                        <div class="mb-4">
                            <label class="form-label mb-2" style="font-weight:600;color:var(--fg);font-size:13px;display:block;">
                                <i class="ph-bold ph-sparkle text-accent me-1"></i> Generate AI Post Image
                                <span style="font-weight:500;color:var(--fg3);font-size:11px;">(Nano Banana 2 · 5 credits)</span>
                            </label>
                            <div class="d-flex gap-2">
                                <input type="text" id="ai_illustration_prompt" class="form-control" placeholder="e.g. Flat-lay of our skincare set on marble with soft morning light" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
                                <button type="button" id="btn-ai-illustration" class="btn btn-outline-primary rounded-pill px-3 text-nowrap" onclick="generatePostIllustration()">
                                    <i class="ph-bold ph-magic-wand me-1"></i> Generate
                                </button>
                            </div>
                            <span style="font-size:11px;color:var(--fg3);display:block;margin-top:6px;">The image attaches automatically — publish it live or queue it to any connected account right after.</span>
                        </div>

                        <!-- Schedule Time -->
                        <div class="mb-4">
                            <label for="scheduled_at" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Publish Schedule (For Delayed Queue)</label>
                            <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-3 pt-2">
                            <button type="submit" name="action" value="publish_now" class="btn btn-main-600 text-dark fw-bold rounded-pill py-3 px-4 flex-fill font-size-15 d-flex align-items-center justify-content-center gap-2">
                                <i class="ph-bold ph-paper-plane-tilt"></i>
                                <span>🚀 Publish Live Now (Real-Time)</span>
                            </button>
                            <button type="submit" name="action" value="schedule" class="btn btn-outline-light rounded-pill py-3 px-4 font-size-14 text-white-50">
                                <i class="ph ph-clock me-1"></i> Queue Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Live Preview Column -->
            <div class="col-xl-5 col-lg-5">
                <div style="position:sticky;top:90px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <span style="font-weight:700;color:#fff;font-size:14px;"><i class="ph ph-device-mobile me-2 text-warning"></i>Live Feed Simulation</span>
                        <span class="badge-status badge-active" id="liveChannelPill">Social Feed</span>
                    </div>

                    <!-- Smartphone Device Mockup -->
                    <div style="background:#020617;border:3px solid #1e293b;border-radius:36px;box-shadow:0 20px 40px rgba(0,0,0,0.6);padding:14px;max-width:380px;margin:0 auto;">
                        <div style="width:100px;height:4px;background:#334155;border-radius:10px;margin:4px auto 14px;"></div>

                        <div style="background:#0f172a;border-radius:24px;overflow:hidden;border:1px solid rgba(255,255,255,0.06);">
                            <!-- Top Profile Bar -->
                            <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg, var(--accent), #10b981);display:flex;align-items:center;justify-content:center;color:#080c14;font-weight:700;font-size:13px;">
                                        <?= strtoupper(substr($userData['full_name'] ?? 'B', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;color:#fff;font-size:13px;"><?= htmlspecialchars($userData['full_name'] ?? 'My Social Page') ?></div>
                                        <div style="font-size:10px;color:var(--fg3);">Just now • <i class="ph ph-globe" style="font-size:10px;"></i></div>
                                    </div>
                                </div>
                                <i class="ph ph-dots-three text-muted"></i>
                            </div>

                            <!-- Media Container -->
                            <div id="mockPostMediaBox" style="width:100%;height:230px;background:#1e293b;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;">
                                <img id="mockPostImg" src="<?= e($prefilledMedia) ?>" alt="Post Creative" style="width:100%;height:100%;object-fit:cover;<?= ($prefilledMedia && $prefilledMediaType === 'image') ? 'display:block;' : 'display:none;' ?>">
                                
                                <video id="mockPostVideo" src="<?= e($prefilledMedia) ?>" poster="<?= BASE_URL ?>/<?= e($prefilledThumb) ?>" controls loop muted style="width:100%;height:100%;object-fit:cover;<?= ($prefilledMedia && $prefilledMediaType === 'video') ? 'display:block;' : 'display:none;' ?>"></video>

                                <div id="mockPostPlaceholder" style="text-align:center;padding:20px;color:var(--fg3);<?= !empty($prefilledMedia) ? 'display:none;' : '' ?>">
                                    <i class="ph ph-image" style="font-size:36px;margin-bottom:6px;display:block;"></i>
                                    <span style="font-size:12px;">Attached media will display here</span>
                                </div>
                            </div>

                            <!-- Engagement Bar -->
                            <div style="padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.04);color:var(--fg2);font-size:18px;">
                                <div style="display:flex;gap:14px;">
                                    <i class="ph ph-heart text-danger"></i>
                                    <i class="ph ph-chat-circle"></i>
                                    <i class="ph ph-paper-plane-tilt"></i>
                                </div>
                                <i class="ph ph-bookmark-simple"></i>
                            </div>

                            <!-- Post Caption -->
                            <div style="padding:12px 14px;font-size:12.5px;color:#f1f5f9;line-height:1.5;" id="mockCaptionText">
                                <?= e($prefilledCaption ?: 'Write your caption to preview how it looks to your followers...') ?>
                            </div>
                        </div>

                        <div style="width:120px;height:4px;background:#334155;border-radius:10px;margin:14px auto 4px;"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Scheduled Posts Queue Table -->
    <div class="card-dark p-4 rounded-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="ph ph-calendar-check text-accent fs-4"></i>
                <h4 class="text-white fw-bold mb-0 font-size-17">Scheduled Content Pipeline</h4>
            </div>
            <span class="badge bg-primary text-dark fw-bold px-3 py-1 rounded-pill"><?= count($scheduledPosts) ?> Queued</span>
        </div>

        <?php if (empty($scheduledPosts)): ?>
            <div class="text-center py-5 text-white-50">
                <i class="ph ph-calendar-blank fs-1 mb-2 d-block text-secondary"></i>
                <p class="mb-0">No scheduled posts in the pipeline. Compose your first post above!</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 text-white">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.08);font-size:12px;color:var(--fg3);text-transform:uppercase;">
                            <th>Channel</th>
                            <th>Content Preview</th>
                            <th>Media</th>
                            <th>Scheduled For</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduledPosts as $p): ?>
                            <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                                <td>
                                    <span class="badge bg-secondary text-white text-uppercase" style="font-size:11px;">
                                        <?= e($p['platform']) ?>
                                    </span>
                                </td>
                                <td style="max-width:320px;">
                                    <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px;color:#fff;">
                                        <?= strip_tags($p['caption']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary border-opacity-25" style="text-transform:capitalize;">
                                        <?= e($p['media_type']) ?>
                                    </span>
                                </td>
                                <td style="font-size:13px;color:var(--fg2);">
                                    <?= dt($p['scheduled_time'], 'M d, Y \a\t g:i A') ?>
                                </td>
                                <td>
                                    <span class="badge bg-success bg-opacity-25 text-success" style="text-transform:capitalize;">
                                        <?= e($p['status']) ?>
                                    </span>
                                    <?php if (!empty($p['bundle_post_id'])): ?>
                                        <div class="font-size-10 text-white-50 mt-1" title="Bundle.social Post Reference">
                                            <i class="ph-bold ph-link-simple text-accent"></i> <?= e($p['bundle_post_id']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="<?= url('post-creator') ?>?tab=<?= $activeTab ?>" style="display:inline;" onsubmit="return confirm('Cancel this scheduled post?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                            <i class="ph ph-trash"></i> Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Asset Picker from AI Library for Post Creator -->
<div class="modal fade" id="postAssetLibraryModal" tabindex="-1" aria-labelledby="postAssetLibraryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4" style="background:#0d1524;border:1px solid rgba(255,255,255,0.1) !important;">
            <div class="modal-header border-secondary border-opacity-10 pb-3">
                <h5 class="modal-title text-white fw-bold d-flex align-items-center gap-2" id="postAssetLibraryModalLabel">
                    <i class="ph-bold ph-squares-four text-accent"></i> Choose from Generated AI Media Library
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <ul class="nav nav-pills gap-2 mb-4" id="postLibraryTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-4 py-2 font-size-13 fw-bold" id="post-lib-videos-tab" data-bs-toggle="pill" data-bs-target="#post-tab-lib-videos" type="button" role="tab">
                            <i class="ph ph-video-camera me-1"></i> AI Videos (<?= count($userVideos) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-4 py-2 font-size-13 fw-bold" id="post-lib-images-tab" data-bs-toggle="pill" data-bs-target="#post-tab-lib-images" type="button" role="tab">
                            <i class="ph ph-image me-1"></i> AI UGC Images (<?= count($userImages) ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="postLibraryTabsContent">
                    <!-- Tab: AI Videos -->
                    <div class="tab-pane fade show active" id="post-tab-lib-videos" role="tabpanel">
                        <?php if (empty($userVideos)): ?>
                            <div class="text-center py-5 text-white-50">
                                <i class="ph ph-video-camera fs-1 mb-2 d-block text-secondary"></i>
                                <p>No AI video creatives generated yet.</p>
                                <a href="<?= url('ugc-video-studio') ?>" class="btn btn-sm btn-primary rounded-pill">Create AI UGC Video</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($userVideos as $vid): ?>
                                <div class="col-md-6">
                                    <div class="card border-0 bg-dark p-3 rounded-3 h-100 d-flex flex-column justify-content-between">
                                        <div class="d-flex gap-3 mb-3">
                                            <div class="ratio ratio-1x1 rounded-2 overflow-hidden bg-black flex-shrink-0" style="width:72px;">
                                                <img src="<?= BASE_URL ?>/<?= e($vid['thumbnail_url'] ?? 'assets/images/thumbs/about-five-img1.png') ?>" class="w-100 h-100 object-fit-cover">
                                            </div>
                                            <div class="min-w-0">
                                                <span class="badge bg-primary text-black fw-bold font-size-10 mb-1"><?= (int)$vid['duration_seconds'] ?>s <?= e(ucfirst($vid['avatar_id'])) ?></span>
                                                <h6 class="text-white fw-bold font-size-13 mb-1 text-truncate"><?= e($vid['hook_text']) ?></h6>
                                                <p class="text-white-50 font-size-11 mb-0 text-truncate-2"><?= e($vid['script_body']) ?></p>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-accent w-100 rounded-pill fw-bold" onclick="selectPostLibraryMedia('video', '<?= e($vid['video_url']) ?>', '<?= BASE_URL ?>/<?= e($vid['thumbnail_url'] ?? 'assets/images/thumbs/about-five-img1.png') ?>', <?= htmlspecialchars(json_encode($vid['hook_text'] . "\n\n" . $vid['script_body'] . "\n\n" . ($vid['cta_text'] ?? '')), ENT_QUOTES) ?>)">
                                            <i class="ph ph-check-circle me-1"></i> Use This Video
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: AI Images -->
                    <div class="tab-pane fade" id="post-tab-lib-images" role="tabpanel">
                        <?php if (empty($userImages)): ?>
                            <div class="text-center py-5 text-white-50">
                                <i class="ph ph-image fs-1 mb-2 d-block text-secondary"></i>
                                <p>No AI images generated yet.</p>
                                <a href="<?= url('ugc-generator') ?>" class="btn btn-sm btn-primary rounded-pill">Generate UGC Images</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($userImages as $img): 
                                    $imgData = json_decode($img['result_output'] ?? '', true) ?: [];
                                    $imgSrc = $imgData['image_url'] ?? $img['result_output'];
                                    $imgTitle = $imgData['hook_headline'] ?? ($imgData['product_name'] ?? 'UGC Image');
                                    $imgCopy = $imgData['ad_copy'] ?? '';
                                ?>
                                <div class="col-md-6">
                                    <div class="card border-0 bg-dark p-3 rounded-3 h-100 d-flex flex-column justify-content-between">
                                        <div class="d-flex gap-3 mb-3">
                                            <div class="ratio ratio-1x1 rounded-2 overflow-hidden bg-black flex-shrink-0" style="width:72px;">
                                                <img src="<?= e($imgSrc) ?>" class="w-100 h-100 object-fit-cover">
                                            </div>
                                            <div class="min-w-0">
                                                <span class="badge bg-secondary text-white font-size-10 mb-1"><?= e($imgData['aspect_ratio'] ?? '1:1') ?></span>
                                                <h6 class="text-white fw-bold font-size-13 mb-1 text-truncate"><?= e($imgTitle) ?></h6>
                                                <p class="text-white-50 font-size-11 mb-0 text-truncate-2"><?= e($imgCopy ?: $img['prompt']) ?></p>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-accent w-100 rounded-pill fw-bold" onclick="selectPostLibraryMedia('image', '<?= e($imgSrc) ?>', '<?= e($imgSrc) ?>', <?= htmlspecialchars(json_encode($imgTitle . "\n\n" . $imgCopy), ENT_QUOTES) ?>)">
                                            <i class="ph ph-check-circle me-1"></i> Use This Image
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cached = sessionStorage.getItem('cached_caption');
    const captionEl = document.querySelector('#caption');
    if (cached && captionEl) {
        captionEl.value = cached;
        sessionStorage.removeItem('cached_caption');
        updatePostPreview();
    }
});

function updatePostPreview() {
    const caption = document.getElementById('caption').value;
    const mock = document.getElementById('mockCaptionText');
    if (mock) {
        mock.textContent = caption || 'Write your caption to preview how it looks...';
    }
}

/* ── AI Post Image (Gemini Nano Banana 2) ── */
async function generatePostIllustration() {
    const promptEl = document.getElementById('ai_illustration_prompt');
    const prompt = promptEl ? promptEl.value.trim() : '';
    if (!prompt) { alert('Describe the illustration you want first.'); if (promptEl) promptEl.focus(); return; }

    const btn = document.getElementById('btn-ai-illustration');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ph-bold ph-circle-notch" style="animation:spin 1s linear infinite"></i> Creating…';

    try {
        const fd = new FormData();
        fd.append('action', 'generate_illustration');
        fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        fd.append('illustration_prompt', prompt);
        const res = await fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Image generation failed. Please try again.');

        selectPostLibraryMedia('image', data.url, data.url, '');
        toastMsg('Illustration ready — attached to your post.', 'Created with ' + data.model + '.');
    } catch (e) {
        alert(e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

function toastMsg(msg, sub) {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:#0d1524;border:1px solid rgba(196,240,0,.45);border-radius:12px;padding:14px 18px;color:#fff;font-size:13.5px;box-shadow:0 10px 40px rgba(0,0,0,.5);max-width:340px;';
    wrap.innerHTML = '<b style="color:#c8ff00;display:block;margin-bottom:2px;">' + msg + '</b>' + (sub ? '<span style="color:#94a3b8;font-size:12px;">' + sub + '</span>' : '');
    document.body.appendChild(wrap);
    setTimeout(() => { wrap.style.opacity = '0'; wrap.style.transition = 'opacity .3s'; setTimeout(() => wrap.remove(), 320); }, 4200);
}

/* Bridge from the AI text tab: prefill the illustration prompt. */
(function () {
    try {
        const seeded = sessionStorage.getItem('ai_illustration_prompt');
        if (seeded) {
            const el = document.getElementById('ai_illustration_prompt');
            if (el) { el.value = seeded; el.focus(); }
            sessionStorage.removeItem('ai_illustration_prompt');
        }
    } catch (e) {}
})();

function selectPostLibraryMedia(type, mediaUrl, thumbUrl, caption) {
    document.getElementById('inp_existing_media_url').value = mediaUrl;
    document.getElementById('inp_media_type').value = type;

    if (caption) {
        document.getElementById('caption').value = caption;
        updatePostPreview();
    }

    const card = document.getElementById('attachedMediaCard');
    const thumb = document.getElementById('attachedThumbImg');
    const name = document.getElementById('attachedMediaName');
    const badge = document.getElementById('attachedMediaTypeBadge');

    if (card) card.style.display = 'block';
    if (thumb) thumb.src = thumbUrl || mediaUrl;
    if (name) name.textContent = mediaUrl;
    if (badge) badge.textContent = type.toUpperCase();

    const prevImg = document.getElementById('mockPostImg');
    const prevVideo = document.getElementById('mockPostVideo');
    const prevPlaceholder = document.getElementById('mockPostPlaceholder');

    if (type === 'video') {
        if (prevImg) prevImg.style.display = 'none';
        if (prevVideo) {
            prevVideo.src = mediaUrl;
            prevVideo.poster = thumbUrl;
            prevVideo.style.display = 'block';
        }
    } else {
        if (prevVideo) prevVideo.style.display = 'none';
        if (prevImg) {
            prevImg.src = mediaUrl;
            prevImg.style.display = 'block';
        }
    }
    if (prevPlaceholder) prevPlaceholder.style.display = 'none';

    // Close modal
    const modalEl = document.getElementById('postAssetLibraryModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

function clearPostMedia() {
    document.getElementById('inp_existing_media_url').value = '';
    document.getElementById('inp_media_type').value = 'image';

    const card = document.getElementById('attachedMediaCard');
    if (card) card.style.display = 'none';

    const prevImg = document.getElementById('mockPostImg');
    const prevVideo = document.getElementById('mockPostVideo');
    const prevPlaceholder = document.getElementById('mockPostPlaceholder');
    if (prevImg) prevImg.style.display = 'none';
    if (prevVideo) prevVideo.style.display = 'none';
    if (prevPlaceholder) prevPlaceholder.style.display = 'block';
}

function handlePostMedia(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const isVideo = file.type.startsWith('video/');
        const reader = new FileReader();

        reader.onload = function(e) {
            const prevImg = document.getElementById('mockPostImg');
            const prevVideo = document.getElementById('mockPostVideo');
            const prevPlaceholder = document.getElementById('mockPostPlaceholder');

            if (isVideo) {
                if (prevImg) prevImg.style.display = 'none';
                if (prevVideo) {
                    prevVideo.src = e.target.result;
                    prevVideo.style.display = 'block';
                }
            } else {
                if (prevVideo) prevVideo.style.display = 'none';
                if (prevImg) {
                    prevImg.src = e.target.result;
                    prevImg.style.display = 'block';
                }
            }
            if (prevPlaceholder) prevPlaceholder.style.display = 'none';

            const card = document.getElementById('attachedMediaCard');
            if (card) {
                card.style.display = 'block';
                document.getElementById('attachedMediaName').textContent = file.name;
                document.getElementById('attachedMediaTypeBadge').textContent = isVideo ? 'VIDEO' : 'IMAGE';
            }
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
