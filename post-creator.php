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

    // AJAX mode: reply with JSON so the composer never reloads and the
    // generated copy is kept in the client (no browser redirect can lose it).
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'success'          => $aiResult !== null,
            'result'           => $aiResult,
            'credits'          => (int)$aiCredits,
            'message'          => $success !== '' ? $success : implode(' ', $errors),
            'credits_label'    => number_format((int)$aiCredits),
        ]);
        exit;
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

    // AJAX mode: JSON reply, no redirect.
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($errors) && $success !== '',
            'message' => $success,
            'errors'  => $errors,
            'queue'   => $scheduledPosts,
        ]);
        exit;
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

    // AJAX mode: JSON reply, no redirect.
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($errors) && $success !== '',
            'message' => $success,
            'errors'  => $errors,
            'queue'   => $scheduledPosts,
        ]);
        exit;
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

    // AJAX mode: JSON reply, no redirect.
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($errors) && $success !== '',
            'message' => $success,
            'errors'  => $errors,
            'queue'   => $scheduledPosts,
        ]);
        exit;
    }
}

require_once __DIR__ . '/includes/app_header.php';

// JS-ready queue payload for the client-side pipeline renderer.
$pcQueueJs = array_map(static function (array $p): array {
    return [
        'id'             => (int)$p['id'],
        'platform'       => (string)$p['platform'],
        'caption'        => mb_substr(strip_tags((string)$p['caption']), 0, 160),
        'media_type'     => (string)($p['media_type'] ?? 'text'),
        'media_url'      => (string)($p['media_url'] ?? ''),
        'scheduled_time' => (string)$p['scheduled_time'],
        'status'         => (string)$p['status'],
        'bundle_post_id' => (string)($p['bundle_post_id'] ?? ''),
    ];
}, $scheduledPosts);
?>

<div class="w-100 pc-wrap">
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">
                <i class="ph-bold ph-paper-plane-tilt text-accent me-2"></i> Post Creator Studio
            </h1>
            <p class="page-subtitle mb-0">Compose once, preview it live, and publish or queue it to every connected channel — no page reloads, ever.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="pc-creditpill" title="AI credits available">
                <i class="ph ph-sparkle"></i> <b id="pcCredits"><?= number_format($aiCredits) ?></b> credits
            </span>
            <a href="<?= url('calendar') ?>" class="pc-pillbtn">
                <i class="ph ph-calendar me-1"></i> Content Calendar
            </a>
        </div>
    </div>

    <!-- Toasts -->
    <div id="pcToasts"></div>

    <!-- JS pane tabs (no ?tab= reloads) -->
    <div class="pc-tabs" role="tablist">
        <button type="button" class="pc-tab on" data-pane="composer" role="tab" aria-selected="true">
            <i class="ph ph-note-pencil me-1"></i> Composer
        </button>
        <button type="button" class="pc-tab" data-pane="ai" role="tab" aria-selected="false">
            <i class="ph ph-sparkle me-1"></i> AI Copywriter
        </button>
    </div>

    <div id="pcAlert"></div>

    <!-- ═══════════ Pane: Composer ═══════════ -->
    <section id="pc-pane-composer" class="pc-pane">
        <div class="row g-4">
            <!-- Left: composer form -->
            <div class="col-xl-7 col-lg-7">
                <div class="pc-card">
                    <form id="pcForm" method="POST" action="<?= url('post-creator') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="existing_media_url" id="inp_existing_media_url" value="<?= e($prefilledMedia) ?>">
                        <input type="hidden" name="media_type" id="inp_media_type" value="<?= e($prefilledMediaType) ?>">

                        <!-- Channels -->
                        <div class="mb-4">
                            <label class="pc-lbl"><i class="ph-bold ph-broadcast text-accent me-1"></i> Target channels <span class="pc-soft">(multi-select)</span></label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                $channels = [
                                    'meta'     => ['name' => 'Meta / Instagram', 'icon' => 'ph-facebook-logo', 'color' => '#7fa7f5'],
                                    'tiktok'   => ['name' => 'TikTok',           'icon' => 'ph-tiktok-logo',   'color' => '#7ee3f0'],
                                    'twitter'  => ['name' => 'Twitter / X',      'icon' => 'ph-twitter-logo',  'color' => '#a5c8ef'],
                                    'linkedin' => ['name' => 'LinkedIn',         'icon' => 'ph-linkedin-logo', 'color' => '#9db8e8'],
                                    // NOTE: paid channels (Snapchat, Google Ads) are owned by the
                                    // Zernio ad gateway and must never appear in the organic
                                    // publishing selector — manage them from create-ad.php.
                                ];
                                foreach ($channels as $key => $info):
                                    $isConnected = in_array($key, $connectedPlatforms, true);
                                ?>
                                    <label class="pc-chip <?= $isConnected ? 'on' : '' ?>">
                                        <input type="checkbox" name="platforms[]" value="<?= $key ?>" <?= $isConnected ? 'checked' : '' ?>
                                               onchange="this.closest('.pc-chip').classList.toggle('on', this.checked)">
                                        <i class="ph <?= $info['icon'] ?>" style="color:<?= $info['color'] ?>;"></i>
                                        <span><?= $info['name'] ?></span>
                                        <?php if ($isConnected): ?>
                                            <span class="pc-livebadge">Live</span>
                                        <?php else: ?>
                                            <a href="<?= url('social-connect') ?>" class="pc-connectlink" title="Connect this account">Connect</a>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Caption -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="caption" class="pc-lbl mb-0">Caption</label>
                                <span class="pc-soft">hashtags &amp; emoji welcome</span>
                            </div>
                            <textarea id="caption" name="caption" rows="5" class="pc-in" required
                                      placeholder="Write your post caption, hashtags, and call to action..."
                                      oninput="updatePostPreview()"><?= e($_POST['caption'] ?? $prefilledCaption) ?></textarea>
                        </div>

                        <!-- Attached media card -->
                        <div id="attachedMediaCard" class="pc-attached mb-4" <?= empty($prefilledMedia) ? 'style="display:none;"' : '' ?>>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="pc-lbl mb-0"><i class="ph-bold ph-image text-accent me-1"></i> Attached creative</span>
                                <span class="pc-livebadge" id="attachedMediaTypeBadge"><?= ucfirst($prefilledMediaType) ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <img id="attachedThumbImg" src="<?= BASE_URL ?>/<?= e($prefilledThumb ?: 'assets/images/thumbs/about-five-img1.png') ?>" alt="Media" style="width:58px;height:58px;object-fit:cover;border-radius:12px;border:1px solid rgba(255,255,255,.1);">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="pc-clip" id="attachedMediaName"><?= e(basename($prefilledMedia)) ?></div>
                                    <div class="pc-soft">Ready for live dispatch</div>
                                </div>
                                <button type="button" class="pc-xbtn" onclick="clearPostMedia()" title="Remove"><i class="ph ph-x"></i></button>
                            </div>
                        </div>

                        <!-- Upload + library -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="pc-lbl mb-0">Attach media <span class="pc-soft">(optional)</span></label>
                                <button type="button" class="pc-pillbtn" data-bs-toggle="modal" data-bs-target="#postAssetLibraryModal">
                                    <i class="ph-bold ph-squares-four me-1"></i> AI Library
                                </button>
                            </div>
                            <div class="pc-dropzone" onclick="document.getElementById('media_upload').click()">
                                <i class="ph ph-upload-simple"></i>
                                <div>Click to choose a file — or drag it here</div>
                                <span class="pc-soft">JPG, PNG, WebP, MP4, MOV · up to 50 MB</span>
                                <input type="file" id="media_upload" name="media_upload" accept="image/*,video/*" style="display:none;" onchange="handlePostMedia(this)">
                            </div>
                        </div>

                        <!-- AI illustration -->
                        <div class="mb-4">
                            <label class="pc-lbl d-block">
                                <i class="ph-bold ph-sparkle text-accent me-1"></i> Generate an AI post image
                                <span class="pc-soft">(Nano Banana 2 · 5 credits)</span>
                            </label>
                            <div class="d-flex gap-2">
                                <input type="text" id="ai_illustration_prompt" class="pc-in" placeholder="e.g. Flat-lay of our skincare set on marble with soft morning light">
                                <button type="button" id="btn-ai-illustration" class="pc-pillbtn text-nowrap" onclick="generatePostIllustration()">
                                    <i class="ph-bold ph-magic-wand me-1"></i> Generate
                                </button>
                            </div>
                            <span class="pc-soft d-block mt-2">The image attaches automatically — publish it live or queue it right after.</span>
                        </div>

                        <!-- Schedule -->
                        <div class="mb-4">
                            <label for="scheduled_at" class="pc-lbl">Publish schedule <span class="pc-soft">(for delayed queue)</span></label>
                            <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="pc-in" value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>">
                        </div>

                        <!-- Actions -->
                        <div class="d-flex align-items-center gap-3 pt-1 flex-wrap">
                            <button type="submit" name="action" value="publish_now" class="pc-btn pri flex-fill">
                                <i class="ph-bold ph-paper-plane-tilt"></i> Publish Live Now
                            </button>
                            <button type="submit" name="action" value="schedule" class="pc-btn ghost">
                                <i class="ph ph-clock me-1"></i> Queue Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: live phone preview -->
            <div class="col-xl-5 col-lg-5">
                <div style="position:sticky;top:90px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="pc-lbl mb-0"><i class="ph ph-device-mobile me-2 text-warning"></i> Live feed preview</span>
                        <span class="pc-livebadge" id="liveChannelPill">Social Feed</span>
                    </div>

                    <div class="pc-phone">
                        <div class="pc-notch"></div>
                        <div class="pc-screen">
                            <div class="pc-screenhead">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="pc-avatar"><?= strtoupper(substr($userData['full_name'] ?? 'B', 0, 1)) ?></div>
                                    <div>
                                        <div class="pc-profilename"><?= htmlspecialchars($userData['full_name'] ?? 'My Social Page') ?></div>
                                        <div class="pc-soft" style="font-size:10px;">Just now • <i class="ph ph-globe" style="font-size:10px;"></i></div>
                                    </div>
                                </div>
                                <i class="ph ph-dots-three text-muted"></i>
                            </div>

                            <div id="mockPostMediaBox" class="pc-mediabox">
                                <img id="mockPostImg" src="<?= e($prefilledMedia) ?>" alt="Post Creative" style="width:100%;height:100%;object-fit:cover;<?= ($prefilledMedia && $prefilledMediaType === 'image') ? 'display:block;' : 'display:none;' ?>">
                                <video id="mockPostVideo" src="<?= e($prefilledMedia) ?>" poster="<?= BASE_URL ?>/<?= e($prefilledThumb) ?>" controls loop muted style="width:100%;height:100%;object-fit:cover;<?= ($prefilledMedia && $prefilledMediaType === 'video') ? 'display:block;' : 'display:none;' ?>"></video>
                                <div id="mockPostPlaceholder" class="pc-mockplaceholder" <?= !empty($prefilledMedia) ? 'style="display:none;"' : '' ?>>
                                    <i class="ph ph-image"></i>
                                    <span>Attached media will display here</span>
                                </div>
                            </div>

                            <div class="pc-engage">
                                <div class="d-flex gap-3">
                                    <i class="ph ph-heart text-danger"></i>
                                    <i class="ph ph-chat-circle"></i>
                                    <i class="ph ph-paper-plane-tilt"></i>
                                </div>
                                <i class="ph ph-bookmark-simple"></i>
                            </div>

                            <div class="pc-mockcaption" id="mockCaptionText"><?= e($prefilledCaption ?: 'Write your caption to preview how it looks to your followers...') ?></div>
                        </div>
                        <div class="pc-notch" style="margin:14px auto 4px;width:110px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════ Pane: AI Copywriter ═══════════ -->
    <section id="pc-pane-ai" class="pc-pane" style="display:none">
        <div class="pc-card" style="max-width:860px;">
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="text-white fw-bold mb-1" style="font-size:17px;">AI Content Copywriter</h4>
                    <p class="pc-soft mb-0">Describe your product or campaign goal — get engagement-ready copy with hashtags. Nothing is lost: the result stays on screen until you use it.</p>
                </div>
                <span class="pc-creditpill"><i class="ph ph-sparkle"></i> <b><?= number_format($aiCredits) ?></b> credits</span>
            </div>

            <form id="pcAiForm" method="POST" action="<?= url('post-creator') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="generate_ai">

                <div class="mb-3">
                    <label for="ai_prompt" class="pc-lbl">Prompt / topic description</label>
                    <textarea id="ai_prompt" name="ai_prompt" rows="4" class="pc-in" required placeholder="e.g. Write a viral TikTok caption announcing our 50% flash sale with strong urgency..."><?= e($_POST['ai_prompt'] ?? '') ?></textarea>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <label for="ai_platform" class="pc-lbl">Platform style</label>
                        <select id="ai_platform" name="ai_platform" class="pc-in" required>
                            <option value="meta">Meta / Facebook</option>
                            <option value="instagram" selected>Instagram Reel &amp; Feed</option>
                            <option value="tiktok">TikTok Video Script &amp; Caption</option>
                            <option value="twitter">Twitter / X Thread</option>
                            <option value="linkedin">LinkedIn Professional</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex align-items-end">
                        <button type="submit" class="pc-btn pri w-100" id="pcAiGo">
                            <i class="ph ph-sparkle me-1"></i> Generate Copy (1 Credit)
                        </button>
                    </div>
                </div>
            </form>

            <!-- Inline result (filled by AJAX, never discarded) -->
            <div id="pcAiResult" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="text-white fw-bold mb-0"><i class="ph ph-magic-wand text-warning me-2"></i>Generated copy</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="pc-pillbtn" onclick="pcMakeIllustration()">
                            <i class="ph ph-image me-1"></i> Make an illustration
                        </button>
                        <button type="button" class="pc-btn pri sm" onclick="pcUseInComposer()">
                            Use in Composer <i class="ph ph-arrow-right"></i>
                        </button>
                    </div>
                </div>
                <pre id="pcAiResultText" class="pc-airesult"></pre>
                <div class="pc-soft mt-2">Your copy stays here — switch tabs or publish without losing a word.</div>
            </div>

            <?php if ($aiResult): ?>
                <!-- Server-rendered result (e.g. arrived with a prefilled state) -->
                <script>document.addEventListener('DOMContentLoaded', () => pcShowAiResult(<?= json_encode($aiResult) ?>));</script>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═══════════ Scheduled pipeline (client-rendered) ═══════════ -->
    <div class="pc-card mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="ph ph-calendar-check text-accent" style="font-size:20px;"></i>
                <h4 class="text-white fw-bold mb-0" style="font-size:16px;">Scheduled content pipeline</h4>
            </div>
            <span class="pc-livebadge" id="pcQueueCount">0 queued</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 text-white pc-table">
                <thead>
                    <tr>
                        <th>Channel</th>
                        <th>Content preview</th>
                        <th>Media</th>
                        <th>Scheduled for</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="pcQueueBody"></tbody>
            </table>
        </div>
        <div id="pcQueueEmpty" class="text-center py-4 pc-soft">
            <i class="ph ph-calendar-blank d-block mb-2" style="font-size:30px;"></i>
            Nothing queued yet — compose your first post above.
        </div>
    </div>
</div>

<!-- Queue seed data for the client renderer -->
<script type="application/json" id="pcQueueData"><?= json_encode($pcQueueJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<!-- Modal: Asset Picker from AI Library for Post Creator -->
<div class="modal fade" id="postAssetLibraryModal" tabindex="-1" aria-labelledby="postAssetLibraryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content pc-modal">
            <div class="modal-header pc-modalhead">
                <h5 class="modal-title text-white fw-bold d-flex align-items-center gap-2" id="postAssetLibraryModalLabel">
                    <i class="ph-bold ph-squares-four text-accent"></i> Choose from your AI media library
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
                            <div class="text-center py-5 pc-soft">
                                <i class="ph ph-video-camera fs-1 mb-2 d-block text-secondary"></i>
                                <p>No AI video creatives generated yet.</p>
                                <a href="<?= url('ugc-video-studio') ?>" class="pc-pillbtn">Create AI UGC Video</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($userVideos as $vid): ?>
                                <div class="col-md-6">
                                    <div class="pc-libcard d-flex flex-column justify-content-between h-100">
                                        <div class="d-flex gap-3 mb-3">
                                            <div class="ratio ratio-1x1 rounded-2 overflow-hidden bg-black flex-shrink-0" style="width:72px;">
                                                <img src="<?= e(media_url($vid['thumbnail_url'] ?? 'assets/images/thumbs/about-five-img1.png')) ?>" class="w-100 h-100 object-fit-cover">
                                            </div>
                                            <div class="min-w-0">
                                                <span class="pc-livebadge mb-1"><?= (int)$vid['duration_seconds'] ?>s <?= e(ucfirst($vid['avatar_id'])) ?></span>
                                                <h6 class="text-white fw-bold font-size-13 mb-1 text-truncate"><?= e($vid['hook_text']) ?></h6>
                                                <p class="pc-soft font-size-11 mb-0 text-truncate-2"><?= e($vid['script_body']) ?></p>
                                            </div>
                                        </div>
                                        <button type="button" class="pc-btn pri sm w-100" onclick="selectPostLibraryMedia('video', <?= htmlspecialchars(json_encode(media_url($vid['video_url']), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode(media_url($vid['thumbnail_url'] ?? 'assets/images/thumbs/about-five-img1.png'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode(($vid['hook_text'] ?? '') . "\n\n" . ($vid['script_body'] ?? '') . "\n\n" . ($vid['cta_text'] ?? ''), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)">
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
                            <div class="text-center py-5 pc-soft">
                                <i class="ph ph-image fs-1 mb-2 d-block text-secondary"></i>
                                <p>No AI images generated yet.</p>
                                <a href="<?= url('ugc-generator') ?>" class="pc-pillbtn">Generate UGC Images</a>
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
                                    <div class="pc-libcard d-flex flex-column justify-content-between h-100">
                                        <div class="d-flex gap-3 mb-3">
                                            <div class="ratio ratio-1x1 rounded-2 overflow-hidden bg-black flex-shrink-0" style="width:72px;">
                                                <img src="<?= e($imgSrc) ?>" class="w-100 h-100 object-fit-cover">
                                            </div>
                                            <div class="min-w-0">
                                                <span class="pc-livebadge mb-1"><?= e($imgData['aspect_ratio'] ?? '1:1') ?></span>
                                                <h6 class="text-white fw-bold font-size-13 mb-1 text-truncate"><?= e($imgTitle) ?></h6>
                                                <p class="pc-soft font-size-11 mb-0 text-truncate-2"><?= e($imgCopy ?: $img['prompt']) ?></p>
                                            </div>
                                        </div>
                                        <button type="button" class="pc-btn pri sm w-100" onclick="selectPostLibraryMedia('image', '<?= e($imgSrc) ?>', '<?= e($imgSrc) ?>', <?= htmlspecialchars(json_encode($imgTitle . "\n\n" . $imgCopy), ENT_QUOTES) ?>)">
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

<style>
/* ── Post Creator Studio — soft, easy-going theme ── */
.pc-wrap{max-width:1320px}
.pc-card{background:#101a2b;border:1px solid rgba(255,255,255,.07);border-radius:20px;padding:24px}
.pc-tabs{display:inline-flex;gap:6px;background:#0c1424;border:1px solid rgba(255,255,255,.07);border-radius:999px;padding:5px;margin-bottom:22px;flex-wrap:wrap}
.pc-tab{border:0;background:transparent;color:#8ea0bd;border-radius:999px;padding:9px 22px;font-size:.84rem;font-weight:700;cursor:pointer;transition:.15s}
.pc-tab:hover{color:#cdd9ee}
.pc-tab.on{background:rgba(200,255,0,.13);color:#d7f36a}
.pc-lbl{display:block;font-weight:700;color:#f1f5f9;font-size:13px;margin-bottom:8px}
.pc-soft{font-size:11.5px;color:#8ea0bd}
.pc-in{width:100%;background:#0c1424;border:1px solid rgba(255,255,255,.1);color:#f1f5f9;border-radius:12px;padding:12px 14px;font-size:.88rem;outline:none;transition:border-color .15s, box-shadow .15s}
.pc-in:focus{border-color:rgba(200,255,0,.45);box-shadow:0 0 0 3px rgba(200,255,0,.08)}
textarea.pc-in{resize:vertical;min-height:110px;line-height:1.55}
.pc-chip{display:inline-flex;align-items:center;gap:7px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.03);color:#dbe4f3;border-radius:999px;padding:8px 14px;font-size:12.5px;cursor:pointer;transition:.15s;user-select:none}
.pc-chip input{accent-color:#c8ff00;margin:0}
.pc-chip.on{border-color:rgba(200,255,0,.45);background:rgba(200,255,0,.07)}
.pc-livebadge{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;background:rgba(74,222,128,.14);color:#4ade80;border-radius:999px;padding:2px 8px;display:inline-block}
.pc-connectlink{font-size:10px;color:#8ea0bd;text-decoration:none;border-bottom:1px dotted #8ea0bd}
.pc-connectlink:hover{color:#c8ff00}
.pc-attached{background:#0c1424;border:1px solid rgba(200,255,0,.25);border-radius:14px;padding:14px 16px}
.pc-clip{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#fff;font-size:12px}
.pc-xbtn{width:28px;height:28px;border-radius:50%;border:1px solid rgba(244,63,94,.3);background:transparent;color:#fda4af;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;flex:none}
.pc-xbtn:hover{background:rgba(244,63,94,.1)}
.pc-dropzone{border:2px dashed rgba(255,255,255,.13);border-radius:14px;padding:22px;text-align:center;background:#0c1424;cursor:pointer;transition:border-color .15s}
.pc-dropzone:hover{border-color:rgba(200,255,0,.4)}
.pc-dropzone i{font-size:26px;color:#c8ff00;display:block;margin-bottom:6px}
.pc-dropzone div{font-weight:600;color:#fff;font-size:13px;margin-bottom:3px}
.pc-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:999px;padding:13px 26px;font-size:.9rem;font-weight:800;cursor:pointer;transition:.15s;text-decoration:none}
.pc-btn.pri{background:linear-gradient(135deg,#d3fa50,#9fe60b);color:#0a1220}
.pc-btn.pri:hover{filter:brightness(1.05)}
.pc-btn.ghost{background:transparent;border:1px solid rgba(255,255,255,.16);color:#cdd9ee}
.pc-btn.ghost:hover{border-color:rgba(200,255,0,.4);color:#fff}
.pc-btn.sm{padding:9px 18px;font-size:.8rem}
.pc-btn:disabled{opacity:.55;cursor:not-allowed}
.pc-pillbtn{display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);color:#dbe4f3;border-radius:999px;padding:8px 16px;font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;transition:.15s}
.pc-pillbtn:hover{border-color:rgba(200,255,0,.4);color:#fff}
.pc-creditpill{display:inline-flex;align-items:center;gap:6px;background:#0c1424;border:1px solid rgba(200,255,0,.25);border-radius:999px;padding:8px 16px;font-size:12.5px;color:#dbe4f3}
.pc-creditpill b{color:#c8ff00}
.pc-creditpill i{color:#c8ff00}
/* Phone preview */
.pc-phone{background:#020617;border:3px solid #1e293b;border-radius:36px;box-shadow:0 20px 40px rgba(0,0,0,.5);padding:14px;max-width:380px;margin:0 auto}
.pc-notch{width:100px;height:4px;background:#334155;border-radius:10px;margin:4px auto 14px}
.pc-screen{background:#0f172a;border-radius:24px;overflow:hidden;border:1px solid rgba(255,255,255,.06)}
.pc-screenhead{padding:12px 14px;display:flex;align-items:center;justify-content:space-between}
.pc-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#d3fa50,#34d399);display:flex;align-items:center;justify-content:center;color:#0a1220;font-weight:700;font-size:13px;flex:none}
.pc-profilename{font-weight:700;color:#fff;font-size:13px}
.pc-mediabox{width:100%;height:230px;background:#1e293b;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.pc-mockplaceholder{text-align:center;padding:20px;color:#8ea0bd}
.pc-mockplaceholder i{font-size:36px;margin-bottom:6px;display:block}
.pc-mockplaceholder span{font-size:12px}
.pc-engage{padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.04);color:#cbd5e1;font-size:18px}
.pc-mockcaption{padding:12px 14px;font-size:12.5px;color:#f1f5f9;line-height:1.5;min-height:44px}
/* Pipeline table */
.pc-table th{border-bottom:1px solid rgba(255,255,255,.08);font-size:11px;color:#8ea0bd;text-transform:uppercase;letter-spacing:.5px;padding:10px 12px;font-weight:700}
.pc-table td{border-bottom:1px solid rgba(255,255,255,.04);padding:12px;font-size:13px}
.pc-ch{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;border-radius:999px;padding:3px 10px;text-transform:uppercase}
.pc-qcap{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px;color:#fff;max-width:320px}
.pc-status{font-size:10px;font-weight:800;border-radius:999px;padding:3px 10px;text-transform:uppercase}
.pc-status.scheduled{background:rgba(56,189,248,.14);color:#7dd3fc}
.pc-status.published{background:rgba(74,222,128,.14);color:#4ade80}
.pc-status.failed{background:rgba(248,113,113,.14);color:#fca5a5}
/* AI result */
.pc-airesult{background:#0c1424;border:1px solid rgba(200,255,0,.35);border-radius:14px;padding:20px;color:#f1f5f9;white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.6;margin:0;max-height:420px;overflow:auto}
.pc-libcard{background:#0c1424;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:14px}
.pc-modal{background:#101a2b;border:1px solid rgba(255,255,255,.1);border-radius:20px;overflow:hidden}
.pc-modalhead{border-bottom:1px solid rgba(255,255,255,.08);padding:18px 22px}
/* Toasts */
#pcToasts{position:fixed;right:18px;bottom:18px;z-index:99999;display:flex;flex-direction:column;gap:9px;max-width:340px}
.pc-toast{border-radius:12px;padding:12px 14px;font-size:.8rem;font-weight:600;color:#fff;background:#101a2b;border:1px solid rgba(200,255,0,.4);box-shadow:0 14px 40px rgba(0,0,0,.5);animation:pcIn .25s ease}
.pc-toast.err{border-color:rgba(244,63,94,.45)}
@keyframes pcIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@media (max-width:576px){
  .pc-card{padding:18px 16px}
  .pc-tab{padding:8px 16px;font-size:.78rem}
  .pc-btn{padding:12px 18px;font-size:.84rem}
}
</style>

<script>
/* ── Post Creator Studio — fully AJAX, nothing ever reloads ── */
const PC = {
  csrf: document.querySelector('input[name="csrf_token"]')?.value || '',
  queue: [],
};

try { PC.queue = JSON.parse(document.getElementById('pcQueueData').textContent || '[]'); } catch (e) { PC.queue = []; }

const PC_PLATFORM_COLORS = { meta:'#7fa7f5', facebook:'#7fa7f5', instagram:'#e8a1b8', tiktok:'#7ee3f0', twitter:'#a5c8ef', linkedin:'#9db8e8' };

function pcToast(msg, isErr) {
  const wrap = document.createElement('div');
  wrap.className = 'pc-toast' + (isErr ? ' err' : '');
  wrap.textContent = msg;
  document.getElementById('pcToasts').appendChild(wrap);
  setTimeout(() => { wrap.style.opacity = '0'; wrap.style.transition = 'opacity .3s'; setTimeout(() => wrap.remove(), 320); }, 4200);
}

function pcAlert(errs) {
  const box = document.getElementById('pcAlert');
  if (!errs || !errs.length) { box.innerHTML = ''; return; }
  box.innerHTML = '<div class="pc-card" style="border-color:rgba(244,63,94,.35);padding:14px 18px;margin-bottom:18px;">' +
    errs.map(e2 => '<div style="color:#fca5a5;font-size:13px;"><i class="ph ph-warning me-1"></i>' + escapePc(e2) + '</div>').join('') + '</div>';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function escapePc(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }

function fmtPcDT(s) {
  const d = new Date(String(s).replace(' ', 'T'));
  if (isNaN(d)) return String(s ?? '');
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' +
         d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

/* ── Tabs (JS panes, no navigation) ── */
function pcSwitchTab(name) {
  document.querySelectorAll('.pc-tab').forEach(t => {
    const on = t.dataset.pane === name;
    t.classList.toggle('on', on);
    t.setAttribute('aria-selected', on ? 'true' : 'false');
  });
  document.getElementById('pc-pane-composer').style.display = name === 'composer' ? 'block' : 'none';
  document.getElementById('pc-pane-ai').style.display = name === 'ai' ? 'block' : 'none';
}
document.querySelectorAll('.pc-tab').forEach(t => t.addEventListener('click', () => pcSwitchTab(t.dataset.pane)));

/* ── Scheduled pipeline (client-rendered) ── */
function pcRenderQueue() {
  const body = document.getElementById('pcQueueBody');
  const rows = PC.queue || [];
  document.getElementById('pcQueueCount').textContent = rows.length + ' queued';
  document.getElementById('pcQueueEmpty').style.display = rows.length ? 'none' : 'block';
  body.innerHTML = rows.map(p => {
    const color = PC_PLATFORM_COLORS[(p.platform || '').toLowerCase()] || '#8ea0bd';
    const st = (p.status || 'scheduled').toLowerCase();
    return '<tr>' +
      '<td><span class="pc-ch" style="background:' + color + '22;color:' + color + '">' + escapePc(p.platform) + '</span></td>' +
      '<td><div class="pc-qcap" title="' + escapePc(p.caption) + '">' + escapePc(p.caption) + '</div></td>' +
      '<td><span class="pc-soft" style="text-transform:capitalize">' + escapePc(p.media_type || 'text') + '</span></td>' +
      '<td class="pc-soft">' + escapePc(fmtPcDT(p.scheduled_time)) + '</td>' +
      '<td><span class="pc-status ' + st + '">' + escapePc(st) + '</span>' +
        (p.bundle_post_id ? '<div class="pc-soft mt-1" style="font-size:10px;"><i class="ph-bold ph-link-simple text-accent"></i> ' + escapePc(p.bundle_post_id) + '</div>' : '') +
      '</td>' +
      '<td class="text-end">' + (st === 'scheduled'
        ? '<button type="button" class="pc-pillbtn" style="border-color:rgba(244,63,94,.3);color:#fda4af;" onclick="pcCancel(' + (p.id) + ')"><i class="ph ph-trash"></i> Cancel</button>'
        : '<span class="pc-soft">—</span>') + '</td>' +
    '</tr>';
  }).join('');
}

/* ── Publish / Schedule (AJAX — no redirect, AI text never discarded) ── */
document.getElementById('pcForm').addEventListener('submit', async e => {
  e.preventDefault();
  const action = e.submitter ? e.submitter.value : 'publish_now';
  const btn = e.submitter || null;
  const orig = btn ? btn.innerHTML : '';
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ph-bold ph-circle-notch" style="animation:spin 1s linear infinite"></i> Working…'; }

  try {
    const fd = new FormData(document.getElementById('pcForm'));
    fd.set('action', action);
    fd.set('ajax', '1');
    fd.set('csrf_token', PC.csrf);
    const res = await fetch('<?= url('post-creator') ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || (data.errors && data.errors.join(' ')) || 'Something went wrong.');
    if (Array.isArray(data.queue)) { PC.queue = data.queue; pcRenderQueue(); }
    pcToast(data.message || 'Done!');
  } catch (err) {
    pcToast(err.message || 'Request failed — please try again.', true);
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = orig; }
  }
});

/* ── Cancel from the pipeline (AJAX) ── */
async function pcCancel(postId) {
  if (!confirm('Cancel this scheduled post?')) return;
  try {
    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('post_id', postId);
    fd.append('ajax', '1');
    fd.append('csrf_token', PC.csrf);
    const res = await fetch('<?= url('post-creator') ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Could not cancel the post.');
    if (Array.isArray(data.queue)) { PC.queue = data.queue; pcRenderQueue(); }
    pcToast(data.message || 'Post cancelled.');
  } catch (err) { pcToast(err.message || 'Request failed.', true); }
}

/* ── AI Copywriter (AJAX) ── */
document.getElementById('pcAiForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('pcAiGo');
  btn.disabled = true;
  btn.innerHTML = '<i class="ph-bold ph-circle-notch" style="animation:spin 1s linear infinite"></i> Writing…';
  try {
    const fd = new FormData(document.getElementById('pcAiForm'));
    fd.set('ajax', '1');
    const res = await fetch('<?= url('post-creator') ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Generation failed — please try again.');
    pcShowAiResult(data.result || '');
    const credits = document.getElementById('pcCredits');
    if (credits && typeof data.credits === 'number') credits.textContent = data.credits.toLocaleString();
    pcToast(data.message || 'Copy generated.');
  } catch (err) { pcToast(err.message || 'Generation failed.', true); }
  finally { btn.disabled = false; btn.innerHTML = '<i class="ph ph-sparkle me-1"></i> Generate Copy (1 Credit)'; }
});

let pcLastResult = '';
function pcShowAiResult(text) {
  pcLastResult = String(text ?? '');
  if (!pcLastResult) return;
  document.getElementById('pcAiResultText').textContent = pcLastResult;
  document.getElementById('pcAiResult').style.display = 'block';
}

/* Keep the generated copy: fill the composer caption client-side. */
function pcUseInComposer() {
  if (!pcLastResult) return;
  document.getElementById('caption').value = pcLastResult;
  updatePostPreview();
  pcSwitchTab('composer');
  pcToast('Copy moved into the composer — it is saved in the caption box.');
}

/* Bridge: use the generated copy as the illustration prompt. */
function pcMakeIllustration() {
  if (!pcLastResult) return;
  const short = pcLastResult.replace(/\s+/g, ' ').trim().slice(0, 220);
  document.getElementById('ai_illustration_prompt').value = short;
  pcSwitchTab('composer');
  document.getElementById('ai_illustration_prompt').focus();
}

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
  if (!prompt) { pcToast('Describe the illustration you want first.', true); if (promptEl) promptEl.focus(); return; }

  const btn = document.getElementById('btn-ai-illustration');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="ph-bold ph-circle-notch" style="animation:spin 1s linear infinite"></i> Creating…';

  try {
    const fd = new FormData();
    fd.append('action', 'generate_illustration');
    fd.append('csrf_token', PC.csrf);
    fd.append('illustration_prompt', prompt);
    const res = await fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Image generation failed. Please try again.');

    selectPostLibraryMedia('image', data.url, data.url, '');
    pcToast('Illustration ready — attached to your post.');
  } catch (e) {
    pcToast(e.message, true);
  } finally {
    btn.disabled = false;
    btn.innerHTML = orig;
  }
}

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

document.addEventListener('DOMContentLoaded', pcRenderQueue);
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
