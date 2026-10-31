<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/ThirdPartyGateway.php';

$pageTitle = 'Edit Campaign â€” ' . get_setting('site_name', 'Adsbooster.net');

$userId = current_user_id();
$campaignId = (int)($_GET['id'] ?? 0);
if ($campaignId <= 0) {
    set_flash('error', 'Campaign not found.');
    redirect(url('dashboard'));
}

$campaign = db()->fetch("SELECT * FROM ad_campaigns WHERE id = ? AND user_id = ?", [$campaignId, $userId]);
if (!$campaign) {
    set_flash('error', 'Campaign not found.');
    redirect(url('dashboard'));
}

$spec = [];
if (!empty($campaign['targeting_spec'])) {
    $decoded = is_string($campaign['targeting_spec']) ? json_decode($campaign['targeting_spec'], true) : $campaign['targeting_spec'];
    if (is_array($decoded)) {
        $spec = $decoded;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $campaignName = trim($_POST['campaign_name'] ?? '');
        $objective = trim($_POST['objective'] ?? '');
        $budgetType = trim($_POST['budget_type'] ?? '');
        $budgetAmount = trim($_POST['budget_amount'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $primaryText = trim($_POST['primary_text'] ?? '');
        $destinationUrl = trim($_POST['destination_url'] ?? '');
        $ageMin = trim($_POST['age_min'] ?? '');
        $ageMax = trim($_POST['age_max'] ?? '');
        $geo = trim($_POST['geo'] ?? '');
        $interests = trim($_POST['interests'] ?? '');

        $validObjectives = ['brand_awareness', 'reach', 'traffic', 'engagement', 'leads', 'conversions', 'sales', 'app_installs'];

        if ($campaignName === '') {
            $errors[] = 'Campaign name is required.';
        }
        if (!in_array($objective, $validObjectives, true)) {
            $errors[] = 'Please select a valid objective.';
        }
        if (!in_array($budgetType, ['daily', 'lifetime'], true)) {
            $errors[] = 'Please select a valid budget type.';
        }
        if (!is_numeric($budgetAmount) || (float)$budgetAmount <= 0) {
            $errors[] = 'Please enter a valid budget amount.';
        }
        if (!in_array($status, ['active', 'paused'], true)) {
            $errors[] = 'Please select a valid status.';
        }
        if ($destinationUrl !== '' && !filter_var($destinationUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Please enter a valid destination URL.';
        }
        if ($ageMin !== '' && (!is_numeric($ageMin) || (int)$ageMin < 13 || (int)$ageMin > 100)) {
            $errors[] = 'Min age must be between 13 and 100.';
        }
        if ($ageMax !== '' && (!is_numeric($ageMax) || (int)$ageMax < 13 || (int)$ageMax > 100)) {
            $errors[] = 'Max age must be between 13 and 100.';
        }
        if ($ageMin !== '' && $ageMax !== '' && (int)$ageMin > (int)$ageMax) {
            $errors[] = 'Min age cannot be greater than max age.';
        }

        if (empty($errors)) {
            try {
                $persistStatus = $status;
                // Asynchronize status Synchronization With The Zernio Ad Engine.
                // When mapped and the requested status differs from the confirmed
                // one, we enqueue the toggle; the local status stays at the last
                // gateway-confirmed value until cron/queue_worker.php reports
                // Zernio's confirmation — the table never diverges from Zernio.
                if (!empty($campaign['zernio_campaign_id']) && $status !== ($campaign['status'] ?? '')) {
                    if (!class_exists('AsyncQueue')) {
                        require_once __DIR__ . '/modules/AsyncQueue.php';
                    }
                    $jobId = AsyncQueue::push((int)$userId, 'zernio_toggle_status', [
                        'internal_campaign_id' => (int)$campaignId,
                        'zernio_campaign_id'   => (string)$campaign['zernio_campaign_id'],
                        'target_status'        => $status,
                    ], null, 'campaign-toggle:' . (int)$campaignId);

                    if ($jobId > 0) {
                        $persistStatus = (string)($campaign['status'] ?? $status);
                        set_flash('info', 'Status change queued for Zernio (job #' . $jobId . ') — the confirmed status below is kept until the gateway confirms the change. All other fields were saved.');
                    } else {
                        $persistStatus = (string)($campaign['status'] ?? $status);
                        $errors[] = 'Background queue temporarily unavailable — the previously confirmed status was kept. All other fields were saved.';
                    }
                }

                db()->update('ad_campaigns', [
                    'campaign_name' => $campaignName,
                    'objective' => $objective,
                    'budget_type' => $budgetType,
                    'budget_amount' => (float)$budgetAmount,
                    'status' => $persistStatus,
                    'headline' => $headline !== '' ? $headline : null,
                    'primary_text' => $primaryText !== '' ? $primaryText : null,
                    'destination_url' => $destinationUrl !== '' ? $destinationUrl : null,
                    'targeting_spec' => json_encode([
                        'age_min' => $ageMin !== '' ? (int)$ageMin : null,
                        'age_max' => $ageMax !== '' ? (int)$ageMax : null,
                        'geo' => $geo,
                        'interests' => $interests,
                    ]),
                ], 'id = ? AND user_id = ?', [$campaignId, $userId]);

                try {
                    require_once __DIR__ . '/modules/notification_service.php';
                    NotificationService::create((int)$userId, 'campaign', 'Campaign Updated', 'Campaign "' . $campaignName . '" was updated.');
                } catch (Throwable $e) {
                    error_log('Campaign notification failed: ' . $e->getMessage());
                }

                set_flash('success', 'Campaign updated successfully.');
                redirect(url('dashboard'));
            } catch (Throwable $e) {
                error_log('Edit campaign failed: ' . $e->getMessage());
                $errors[] = 'Failed to update campaign. Please try again.';
            }
        }

        // Re-render with submitted values on error
        $campaign['campaign_name'] = $campaignName;
        $campaign['objective'] = $objective;
        $campaign['budget_type'] = $budgetType;
        $campaign['budget_amount'] = $budgetAmount;
        $campaign['status'] = $status;
        $campaign['headline'] = $headline;
        $campaign['primary_text'] = $primaryText;
        $campaign['destination_url'] = $destinationUrl;
        $spec = ['age_min' => $ageMin, 'age_max' => $ageMax, 'geo' => $geo, 'interests' => $interests];
    }
}

require_once __DIR__ . '/includes/app_header.php';

$creativeUrl = trim((string)($campaign['creative_media_url'] ?? ''));
$creativeExt = strtolower(pathinfo($creativeUrl, PATHINFO_EXTENSION));
$isVideo = in_array($creativeExt, ['mp4', 'mov', 'webm'], true);
$objectiveLabels = [
    'brand_awareness' => 'Brand Awareness', 'reach' => 'Reach', 'traffic' => 'Traffic',
    'engagement' => 'Engagement', 'leads' => 'Lead Generation', 'conversions' => 'Conversions',
    'sales' => 'Sales', 'app_installs' => 'App Installs',
];
$statusBadge = ($campaign['status'] ?? '') === 'active' ? 'badge-active' : 'badge-inactive';
?>

<h1 class="page-title">Edit Campaign</h1>
<p class="page-subtitle">Update <strong><?= e($campaign['campaign_name'] ?? 'campaign') ?></strong> <span class="badge-status <?= $statusBadge ?>" style="margin-left:8px"><?= e(ucfirst($campaign['status'] ?? '')) ?></span></p>

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
        <h5 style="margin-bottom:20px"><i class="ph ph-megaphone" style="margin-right:8px"></i>Campaign Details</h5>
        <form method="POST" action="<?= url('edit-campaign') . '?id=' . (int)$campaignId ?>">
            <?= csrf_field() ?>
            <div style="margin-bottom:16px">
                <label for="campaign_name" class="form-label">Campaign Name</label>
                <input type="text" id="campaign_name" name="campaign_name" class="form-control" value="<?= e((string)($campaign['campaign_name'] ?? '')) ?>" required placeholder="e.g. Summer Sale 2026">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label for="objective" class="form-label">Objective</label>
                    <select id="objective" name="objective" class="form-select" required>
                        <option value="">Choose objective...</option>
                        <?php foreach ($objectiveLabels as $val => $label): ?>
                        <option value="<?= e($val) ?>" <?= ($campaign['objective'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="active" <?= ($campaign['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="paused" <?= ($campaign['status'] ?? '') === 'paused' ? 'selected' : '' ?>>Paused</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label for="budget_type" class="form-label">Budget Type</label>
                    <select id="budget_type" name="budget_type" class="form-select" required>
                        <option value="daily" <?= ($campaign['budget_type'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="lifetime" <?= ($campaign['budget_type'] ?? '') === 'lifetime' ? 'selected' : '' ?>>Lifetime</option>
                    </select>
                </div>
                <div>
                    <label for="budget_amount" class="form-label">Budget Amount ($)</label>
                    <input type="number" id="budget_amount" name="budget_amount" class="form-control" step="0.01" min="1" value="<?= e((string)($campaign['budget_amount'] ?? '')) ?>" required placeholder="0.00">
                </div>
            </div>
            <div style="margin-bottom:16px">
                <label for="headline" class="form-label">Headline</label>
                <input type="text" id="headline" name="headline" class="form-control" value="<?= e((string)($campaign['headline'] ?? '')) ?>" maxlength="150" placeholder="Compelling headline...">
            </div>
            <div style="margin-bottom:16px">
                <label for="primary_text" class="form-label">Primary Text</label>
                <textarea id="primary_text" name="primary_text" rows="4" class="form-control" placeholder="Write your ad copy here..."><?= e((string)($campaign['primary_text'] ?? '')) ?></textarea>
            </div>
            <div style="margin-bottom:16px">
                <label for="destination_url" class="form-label">Destination URL</label>
                <input type="url" id="destination_url" name="destination_url" class="form-control" value="<?= e((string)($campaign['destination_url'] ?? '')) ?>" placeholder="https://example.com">
            </div>
            <hr class="divider">
            <h6 style="margin-bottom:16px">Targeting</h6>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label for="age_min" class="form-label">Min Age</label>
                    <input type="number" id="age_min" name="age_min" class="form-control" min="13" max="100" value="<?= e((string)($spec['age_min'] ?? '')) ?>" placeholder="18">
                </div>
                <div>
                    <label for="age_max" class="form-label">Max Age</label>
                    <input type="number" id="age_max" name="age_max" class="form-control" min="13" max="100" value="<?= e((string)($spec['age_max'] ?? '')) ?>" placeholder="65">
                </div>
            </div>
            <div style="margin-bottom:16px">
                <label for="geo" class="form-label">Geographic Targeting</label>
                <input type="text" id="geo" name="geo" class="form-control" value="<?= e((string)($spec['geo'] ?? '')) ?>" placeholder="e.g. United States, Nigeria">
            </div>
            <div style="margin-bottom:24px">
                <label for="interests" class="form-label">Interests</label>
                <input type="text" id="interests" name="interests" class="form-control" value="<?= e((string)($spec['interests'] ?? '')) ?>" placeholder="e.g. Fitness, Technology, Fashion">
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" class="btn-accent"><i class="ph ph-check"></i>Save Changes</button>
                <a href="<?= url('dashboard') ?>" class="btn-outline-dark">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card-dark">
        <h6 style="margin-bottom:16px"><i class="ph ph-eye" style="margin-right:6px"></i>Creative Preview</h6>
        <?php if ($creativeUrl !== ''): ?>
            <?php if ($isVideo): ?>
            <video src="<?= BASE_URL ?>/<?= e(ltrim($creativeUrl, '/')) ?>" controls style="width:100%;border-radius:var(--radius-sm);border:1px solid var(--border)"></video>
            <?php else: ?>
            <img src="<?= BASE_URL ?>/<?= e(ltrim($creativeUrl, '/')) ?>" alt="Creative preview" style="width:100%;border-radius:var(--radius-sm);border:1px solid var(--border);object-fit:cover">
            <?php endif; ?>
            <p style="font-size:12px;color:var(--fg3);margin:12px 0 0;word-break:break-all"><?= e($creativeUrl) ?></p>
        <?php else: ?>
            <div class="empty-state" style="padding:24px">
                <i class="ph ph-image"></i>
                <p>No creative attached to this campaign.</p>
            </div>
        <?php endif; ?>
        <hr class="divider">
        <div style="font-size:13px;color:var(--fg2);display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between"><span>Platform</span><span class="badge-status badge-info"><?= e(ucfirst((string)($campaign['campaign_type'] ?? ''))) ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>Created</span><span style="color:var(--fg)"><?= e(date('M d, Y', strtotime((string)($campaign['created_at'] ?? 'now')))) ?></span></div>
        </div>
    </div>
</div>

<style>
@media(max-width:767.98px){div[style*="grid-template-columns:2fr 1fr"]{grid-template-columns:1fr !important}div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important}}
</style>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
