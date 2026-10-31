<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Edit Post â€” ' . get_setting('site_name', 'Adsbooster.net');

$userId = current_user_id();
$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    set_flash('error', 'Post not found.');
    redirect(url('post-creator'));
}

$post = db()->fetch("SELECT * FROM scheduled_posts WHERE id = ? AND user_id = ?", [$postId, $userId]);
if (!$post) {
    set_flash('error', 'Post not found.');
    redirect(url('post-creator'));
}

// Map DB media_type enum -> spec values used in the UI
$dbToSpec = static function (?string $db): string {
    return match ($db) {
        'image' => 'image',
        'ugc_video', 'reel' => 'video',
        'text_only' => 'text',
        'document', 'pdf' => 'document',
        default => 'text',
    };
};
// Carousel has no DB enum equivalent; stored as reel.
$specToDb = static function (string $spec): string {
    return match ($spec) {
        'image' => 'image',
        'video' => 'ugc_video',
        'carousel' => 'reel',
        'document' => 'document',
        default => 'text_only',
    };
};

// Organic platform options (Bundle.social)
$platformOptions = ['meta', 'facebook', 'instagram', 'tiktok', 'twitter', 'linkedin'];
try {
    $connected = db()->fetchAll(
        "SELECT DISTINCT platform_name FROM connected_accounts 
         WHERE user_id = ? AND gateway_provider = 'bundle_social'", 
        [$userId]
    );
    foreach ($connected as $c) {
        $pName = $c['platform_name'] ?? '';
        if (!empty($pName) && !in_array($pName, $platformOptions, true)) {
            $platformOptions[] = $pName;
        }
    }
} catch (Throwable $e) {
    // keep defaults
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $caption = trim($_POST['caption'] ?? '');
        $platform = trim($_POST['platform'] ?? '');
        $mediaType = trim($_POST['media_type'] ?? '');
        $scheduledTime = trim($_POST['scheduled_time'] ?? '');
        $status = trim($_POST['status'] ?? '');

        $validMedia = ['text', 'image', 'video', 'carousel', 'document'];

        if ($caption === '') {
            $errors[] = 'Caption is required.';
        }
        if ($platform === '') {
            $errors[] = 'Please select a platform.';
        }
        if (!in_array($mediaType, $validMedia, true)) {
            $errors[] = 'Please select a valid media type.';
        }
        if (!in_array($status, ['scheduled', 'cancelled'], true)) {
            $errors[] = 'Please select a valid status.';
        }
        if ($scheduledTime === '') {
            $errors[] = 'Please choose a scheduled time.';
        } else {
            $ts = strtotime($scheduledTime);
            if ($ts === false) {
                $errors[] = 'Invalid scheduled time.';
            } elseif ($ts <= time()) {
                $errors[] = 'Scheduled time must be in the future.';
            }
        }

        if (empty($errors)) {
            $dbTime = date('Y-m-d H:i:s', strtotime($scheduledTime));
            try {
                db()->update('scheduled_posts', [
                    'caption' => $caption,
                    'platform' => $platform,
                    'media_type' => $specToDb($mediaType),
                    'scheduled_time' => $dbTime,
                    'status' => $status,
                ], 'id = ? AND user_id = ?', [$postId, $userId]);

                try {
                    require_once __DIR__ . '/modules/notification_service.php';
                    NotificationService::create((int)$userId, 'post', 'Post Updated', 'Your scheduled post for ' . $platform . ' was updated.');
                } catch (Throwable $e) {
                    error_log('Post notification failed: ' . $e->getMessage());
                }

                set_flash('success', 'Post updated successfully.');
                redirect(url('post-creator'));
            } catch (Throwable $e) {
                error_log('Edit post failed: ' . $e->getMessage());
                $errors[] = 'Failed to update post. Please try again.';
            }
        }

        // Re-render with submitted values on error
        $post['caption'] = $caption;
        $post['platform'] = $platform;
        $post['media_type'] = $mediaType;
        $post['scheduled_time'] = $scheduledTime;
        $post['status'] = $status;
    }
}

require_once __DIR__ . '/includes/app_header.php';

$currentMediaSpec = in_array(($post['media_type'] ?? ''), ['text', 'image', 'video', 'carousel', 'document'], true)
    ? $post['media_type']
    : $dbToSpec($post['media_type'] ?? null);

// datetime-local value
$schedValue = '';
try {
    $schedValue = $post['scheduled_time'] ? date('Y-m-d\TH:i', strtotime((string)$post['scheduled_time'])) : '';
} catch (Throwable $e) {
    $schedValue = '';
}

$mediaUrl = trim((string)($post['media_url'] ?? ''));
$mediaExt = strtolower(pathinfo($mediaUrl, PATHINFO_EXTENSION));
$isImage = in_array($mediaExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
$isVid = in_array($mediaExt, ['mp4', 'mov', 'webm'], true);
$isPdf = $mediaExt === 'pdf';
$statusBadge = ($post['status'] ?? '') === 'scheduled' ? 'badge-active' : 'badge-danger';
?>

<h1 class="page-title">Edit Post</h1>
<p class="page-subtitle">Update your scheduled post <span class="badge-status <?= $statusBadge ?>" style="margin-left:8px"><?= e(ucfirst($post['status'] ?? '')) ?></span></p>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <ul style="margin:0;padding-left:18px">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">
    <div class="card-dark">
        <h5 style="margin-bottom:20px"><i class="ph ph-note-pencil" style="margin-right:8px"></i>Post Details</h5>
        <form method="POST" action="<?= url('edit-post') . '?id=' . (int)$postId ?>">
            <?= csrf_field() ?>
            <div style="margin-bottom:16px">
                <label for="caption" class="form-label">Caption</label>
                <textarea id="caption" name="caption" rows="5" class="form-control" required placeholder="Write your caption here..."><?= e((string)($post['caption'] ?? '')) ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label for="platform" class="form-label">Platform</label>
                    <select id="platform" name="platform" class="form-select" required>
                        <option value="">Choose platform...</option>
                        <?php foreach ($platformOptions as $p): ?>
                        <option value="<?= e($p) ?>" <?= ($post['platform'] ?? '') === $p ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $p))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="media_type" class="form-label">Media Type</label>
                    <select id="media_type" name="media_type" class="form-select" required>
                        <option value="text" <?= $currentMediaSpec === 'text' ? 'selected' : '' ?>>Text</option>
                        <option value="image" <?= $currentMediaSpec === 'image' ? 'selected' : '' ?>>Image</option>
                        <option value="video" <?= $currentMediaSpec === 'video' ? 'selected' : '' ?>>Video</option>
                        <option value="carousel" <?= $currentMediaSpec === 'carousel' ? 'selected' : '' ?>>Carousel</option>
                        <option value="document" <?= $currentMediaSpec === 'document' ? 'selected' : '' ?>>Document</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
                <div>
                    <label for="scheduled_time" class="form-label">Scheduled Time</label>
                    <input type="datetime-local" id="scheduled_time" name="scheduled_time" class="form-control" value="<?= e($schedValue) ?>" required>
                    <small class="form-text">Must be a future date and time.</small>
                </div>
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="scheduled" <?= ($post['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="cancelled" <?= ($post['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" class="btn-accent"><i class="ph ph-check"></i>Save Changes</button>
                <a href="<?= url('post-creator') ?>" class="btn-outline-dark">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card-dark">
        <h6 style="margin-bottom:16px"><i class="ph ph-eye" style="margin-right:6px"></i>Media Preview</h6>
        <?php if ($mediaUrl !== ''): ?>
            <?php if ($isImage): ?>
            <img src="<?= BASE_URL ?>/<?= e(ltrim($mediaUrl, '/')) ?>" alt="Media preview" style="width:100%;border-radius:var(--radius-sm);border:1px solid var(--border);object-fit:cover">
            <?php elseif ($isVid): ?>
            <video src="<?= BASE_URL ?>/<?= e(ltrim($mediaUrl, '/')) ?>" controls style="width:100%;border-radius:var(--radius-sm);border:1px solid var(--border)"></video>
            <?php elseif ($isPdf): ?>
            <a href="<?= BASE_URL ?>/<?= e(ltrim($mediaUrl, '/')) ?>" target="_blank" class="btn-outline-dark btn-sm" style="width:100%;justify-content:center"><i class="ph ph-file-pdf"></i>View PDF attachment</a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>/<?= e(ltrim($mediaUrl, '/')) ?>" target="_blank" class="btn-outline-dark btn-sm" style="width:100%;justify-content:center"><i class="ph ph-link"></i>Open attachment</a>
            <?php endif; ?>
            <p style="font-size:12px;color:var(--fg3);margin:12px 0 0;word-break:break-all"><?= e($mediaUrl) ?></p>
        <?php else: ?>
            <div class="empty-state" style="padding:24px">
                <i class="ph ph-image"></i>
                <p>No media attached to this post.</p>
            </div>
        <?php endif; ?>
        <hr class="divider">
        <div style="font-size:13px;color:var(--fg2);display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between"><span>Platform</span><span class="badge-status badge-info"><?= e($post['platform'] ?? 'â€”') ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>Media type</span><span class="badge-status badge-inactive"><?= e($currentMediaSpec) ?></span></div>
        </div>
    </div>
</div>

<style>
@media(max-width:767.98px){div[style*="grid-template-columns:2fr 1fr"]{grid-template-columns:1fr !important}div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important}}
</style>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
