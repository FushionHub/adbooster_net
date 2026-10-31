<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
$pageTitle = 'AI Settings — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ai_engine.php';

$userId = current_user_id();
$message = '';
$providerStatus = AIEngine::getProviderStatus();
$currentProvider = AIEngine::getUserProvider($userId, 'text');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $message = '<div class="alert alert-danger">Invalid security token.</div>';
    } else {
        $action = $_POST['action'];

        if ($action === 'set_provider') {
            $provider = trim($_POST['provider'] ?? '');
            if (isset(AIEngine::getProviderStatus()[$provider])) {
                AIEngine::setUserProvider($userId, $provider);
                $currentProvider = $provider;
                $message = '<div class="alert alert-success">Default AI provider updated to ' . ucfirst(e($provider)) . '.</div>';
            }
        } elseif ($action === 'save_keys' && is_admin()) {
            $keys = ['groq_api_key', 'gemini_api_key', 'nvidia_api_key'];
            foreach ($keys as $key) {
                $val = trim($_POST[$key] ?? '');
                if ($val !== '') {
                    db()->query("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?", [$val, $key]);
                }
            }
            $message = '<div class="alert alert-success">API keys saved.</div>';
            $providerStatus = AIEngine::getProviderStatus();
        }
    }
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">

        <?= $message ?>

        <div class="page-header" style="margin-bottom:28px">
            <div>
                <h1 class="page-title">AI Provider Settings</h1>
                <p class="page-subtitle">Choose which AI powers your content generation. Configure API keys and set your default provider.</p>
            </div>
            <div>
                <a href="<?= url('post-creator') ?>" class="btn-outline-dark btn-sm">
                    <i class="ph ph-note-pencil"></i> Post Creator
                </a>
            </div>
        </div>

        <!-- Provider Status Cards -->
        <div class="row g-4" style="margin-bottom:32px">
            <?php foreach ($providerStatus as $provider => $info): ?>
                <div class="col-md-4">
                    <div class="card-dark" style="height:100%;padding:20px;<?= $currentProvider === $provider ? 'border-color:var(--accent);border-width:2px' : '' ?>">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                            <h5 style="font-weight:700;color:var(--fg);margin:0;text-transform:capitalize"><?= e($provider) ?></h5>
                            <?php if ($info['configured']): ?>
                                <span class="badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge-inactive">No Key</span>
                            <?php endif; ?>
                        </div>
                        <div style="margin-bottom:12px">
                            <?php if ($info['text_models']): ?>
                                <small style="font-size:12px;color:var(--fg3);display:block">Text: <?= e($info['text_models']) ?></small>
                            <?php endif; ?>
                            <?php if ($info['image_models']): ?>
                                <small style="font-size:12px;color:var(--fg3);display:block">Image: <?= e($info['image_models']) ?></small>
                            <?php endif; ?>
                            <?php if ($info['video_models']): ?>
                                <small style="font-size:12px;color:var(--fg3);display:block">Video: <?= e($info['video_models']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if ($info['configured']): ?>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_provider">
                                <input type="hidden" name="provider" value="<?= e($provider) ?>">
                                <button type="submit" class="<?= $currentProvider === $provider ? 'btn-accent' : 'btn-outline-dark' ?> btn-sm" style="width:100%">
                                    <?= $currentProvider === $provider ? 'Selected' : 'Set as Default' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <p style="font-size:11px;color:var(--fg3);margin:0">Add your API key below to enable.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- API Key Configuration (admin only) -->
        <?php if (is_admin()): ?>
        <div class="card-dark" style="padding:24px">
            <h5 style="font-weight:700;margin-bottom:16px;color:var(--fg)">API Keys (Admin)</h5>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_keys">
                <div class="row g-4">
                    <?php foreach (['groq_api_key' => 'Groq API Key', 'gemini_api_key' => 'Gemini API Key', 'nvidia_api_key' => 'NVIDIA NIM API Key'] as $key => $label): ?>
                        <div class="col-md-4">
                            <label class="form-label"><?= $label ?></label>
                            <input type="password" name="<?= $key ?>" class="form-control" placeholder="<?= $key === 'nvidia_api_key' ? 'nvapi-... or leave blank' : 'sk-... or leave blank' ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn-accent btn-sm" style="margin-top:16px">
                    <i class="ph ph-floppy-disk" style="margin-right:4px"></i>Save Keys
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
