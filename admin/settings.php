<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/payment_gateways.php';

$gatewayConfigs    = PaymentGateways::all();
$activeGatewayKeys = PaymentGateways::enabled();

$pageTitle = 'Platform Settings & Integrations';

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF security token.');
    } else {
        $settings = $_POST['settings'] ?? [];
        if (is_array($settings)) {
            foreach ($settings as $key => $val) {
                $val = trim((string)$val);
                // Check if key exists
                $exists = db()->fetchOne("SELECT setting_key FROM site_settings WHERE setting_key=?", [$key]);
                if ($exists) {
                    db()->update('site_settings', ['setting_value' => $val], 'setting_key=?', [$key]);
                } else {
                    // Determine group
                    $group = 'general';
                    if (str_contains($key, 'paystack') || str_contains($key, 'flutterwave')) $group = 'payment';
                    elseif (str_contains($key, 'bundle_social') || str_contains($key, 'zernio')) $group = 'gateways';
                    elseif (str_contains($key, 'groq') || str_contains($key, 'gemini') || str_contains($key, 'nvidia') || str_contains($key, 'firecrawl')) $group = 'ai';
                    elseif (str_contains($key, 'google')) $group = 'integrations';
                    elseif (str_contains($key, 'social_')) $group = 'social';
                    elseif (str_contains($key, 'referral') || str_contains($key, 'reward')) $group = 'referrals';
                    elseif (str_contains($key, 'policy') || str_contains($key, 'consent')) $group = 'policies';

                    db()->insert('site_settings', [
                        'setting_key' => $key,
                        'setting_value' => $val,
                        'setting_group' => $group
                    ]);
                }
            }

            // Synchronize third_party_gateways table
            try {
                if (isset($settings['bundle_social_api_key']) || isset($settings['bundle_social_base_url'])) {
                    $bKey = trim((string)($settings['bundle_social_api_key'] ?? ''));
                    $bUrl = trim((string)($settings['bundle_social_base_url'] ?? 'https://api.bundle.social/v1'));
                    db()->query(
                        "INSERT INTO third_party_gateways (provider_key, api_key, base_url, is_active, updated_at) 
                         VALUES ('bundle_social', ?, ?, 1, NOW()) 
                         ON DUPLICATE KEY UPDATE api_key = VALUES(api_key), base_url = VALUES(base_url), updated_at = NOW()",
                        [$bKey, $bUrl]
                    );
                }
                if (isset($settings['zernio_api_key']) || isset($settings['zernio_base_url'])) {
                    $zKey = trim((string)($settings['zernio_api_key'] ?? ''));
                    $zUrl = trim((string)($settings['zernio_base_url'] ?? 'https://api.zernio.com/v1'));
                    db()->query(
                        "INSERT INTO third_party_gateways (provider_key, api_key, base_url, is_active, updated_at) 
                         VALUES ('zernio', ?, ?, 1, NOW()) 
                         ON DUPLICATE KEY UPDATE api_key = VALUES(api_key), base_url = VALUES(base_url), updated_at = NOW()",
                        [$zKey, $zUrl]
                    );
                }
            } catch (\Throwable $e) {
                error_log("Failed to sync third_party_gateways table: " . $e->getMessage());
            }

            set_flash('success', 'Platform settings and integration keys saved successfully.');
        }
    }
    redirect(url('admin/settings'));
}

// Load current settings as associative map
$rawSettings = db()->fetchAll("SELECT * FROM site_settings");
$currentSettings = [];
foreach ($rawSettings as $r) {
    $currentSettings[$r['setting_key']] = $r['setting_value'];
}

function sval(array $arr, string $k, string $def = ''): string {
    return htmlspecialchars($arr[$k] ?? $def);
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="admin-card mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="card-icon green"><i class="ph-bold ph-gear"></i></div>
        <div>
            <h5 class="text-white fw-bold mb-1">Global Platform Settings</h5>
            <p class="text-muted small mb-0">Configure site identity, AI engine API credentials, payment gateways, OAuth clients, and compliance parameters.</p>
        </div>
    </div>
</div>

<form method="POST">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Site Identity -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <h6 class="text-white fw-bold mb-3"><i class="ph-bold ph-globe text-primary"></i> Site Identity & Contact</h6>
                
                <div class="mb-3">
                    <label class="form-label-admin">Platform Brand Name</label>
                    <input type="text" name="settings[site_name]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'site_name', 'Adsbooster.net') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Platform Slogan</label>
                    <input type="text" name="settings[site_slogan]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'site_slogan', 'Multi-Network AI Advertising Platform') ?>">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-admin">Support Email</label>
                        <input type="email" name="settings[site_email]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'site_email', 'support@adsbooster.net') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Currency Symbol</label>
                        <input type="text" name="settings[currency_symbol]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'currency_symbol', '$') ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Contact Phone</label>
                    <input type="text" name="settings[site_phone]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'site_phone', '+1 (555) 123-4567') ?>">
                </div>
                <div>
                    <label class="form-label-admin">Business Physical Address</label>
                    <input type="text" name="settings[site_address]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'site_address', 'New York, NY 10002, United States') ?>">
                </div>
            </div>
        </div>

        <!-- AI Engine & Model Credentials -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <h6 class="text-white fw-bold mb-3"><i class="ph-bold ph-cpu text-info"></i> AI & LLM Engine API Keys</h6>
                
                <div class="mb-3">
                    <label class="form-label-admin">Google Gemini API Key</label>
                    <input type="password" name="settings[gemini_api_key]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'gemini_api_key') ?>" placeholder="AIzaSy...">
                    <small class="text-muted" style="font-size: 11px;">Powers Nano Banana 2 image generation (UGC Studio), Veo video rendering, and scene stills. Get a key at aistudio.google.com/apikey.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Default Video Provider</label>
                    <select name="settings[ai_video_provider]" class="form-select-admin w-100">
                        <option value="" <?= sval($currentSettings, 'ai_video_provider') === '' ? 'selected' : '' ?>>Auto (Veo when keyed, else the free Pollinations/Storyboard route)</option>
                        <option value="veo" <?= sval($currentSettings, 'ai_video_provider') === 'veo' ? 'selected' : '' ?>>Google Veo (requires Gemini key)</option>
                        <option value="pollinations" <?= sval($currentSettings, 'ai_video_provider') === 'pollinations' ? 'selected' : '' ?>>Pollinations (FREE, keyless — Flux stills + browser compositor)</option>
                        <option value="storyboard" <?= sval($currentSettings, 'ai_video_provider') === 'storyboard' ? 'selected' : '' ?>>Storyboard Studio only (free, browser-rendered)</option>
                    </select>
                    <small class="text-muted" style="font-size: 11px;">Cloud renders automatically fall back to the free Pollinations/Storyboard route if the provider rejects a request.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Groq Fast LLM API Key</label>
                    <input type="password" name="settings[groq_api_key]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'groq_api_key') ?>" placeholder="gsk_...">
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">NVIDIA NIM API Key (extended LLM fallback)</label>
                    <input type="password" name="settings[nvidia_api_key]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'nvidia_api_key') ?>" placeholder="nvapi-...">
                </div>
                <div>
                    <label class="form-label-admin">Firecrawl Scraping API Key</label>
                    <input type="password" name="settings[firecrawl_api_key]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'firecrawl_api_key') ?>" placeholder="fc-...">
                </div>
            </div>
        </div>

        <!-- Payment Gateways -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="text-white fw-bold mb-0"><i class="ph-bold ph-credit-card text-warning"></i> Payment Gateways</h6>
                    <span class="badge-status <?= empty($activeGatewayKeys) ? 'failed' : 'active' ?>">
                        <?= empty($activeGatewayKeys) ? 'All offline' : count($activeGatewayKeys) . ' active' ?>
                    </span>
                </div>

                <div class="text-muted mb-3" style="font-size:.76rem;line-height:1.6;">
                    A gateway is shown to customers only when its <strong>public key</strong> and
                    <strong>secret key</strong> are both present. Save the keys below and the
                    gateway activates immediately \u2014 no code change needed.
                </div>

                <?php foreach ($gatewayConfigs as $gwKey => $gw): ?>
                    <?php $gwOn = !empty($gw['configured']); ?>
                    <div style="border:1px solid var(--admin-border);border-radius:10px;padding:14px;margin-bottom:12px;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <strong style="color:#fff;font-size:.88rem;">
                                <i class="ph <?= htmlspecialchars($gw['icon']) ?>"></i>
                                <?= htmlspecialchars($gw['label']) ?>
                            </strong>
                            <span class="badge-status <?= $gwOn ? 'active' : 'draft' ?>">
                                <?= $gwOn ? 'Active' : 'Disabled' ?>
                            </span>
                        </div>

                        <?php if (!$gwOn): ?>
                            <div class="text-muted mb-2" style="font-size:.74rem;">
                                <?= htmlspecialchars(PaymentGateways::unavailableReason($gwKey)) ?>
                                Hidden from customers until configured.
                            </div>
                        <?php endif; ?>

                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label-admin">Public key</label>
                                <input type="text" name="settings[<?= htmlspecialchars($gwKey) ?>_public_key]"
                                       class="form-control-admin w-100" value="<?= htmlspecialchars(sval($currentSettings, $gwKey . '_public_key')) ?>"
                                       placeholder="<?= htmlspecialchars($gwKey === 'flutterwave' ? 'FLWPUBK-...' : 'pk_live_...') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label-admin">Secret key</label>
                                <input type="password" name="settings[<?= htmlspecialchars($gwKey) ?>_secret_key]"
                                       class="form-control-admin w-100" value="<?= htmlspecialchars(sval($currentSettings, $gwKey . '_secret_key')) ?>"
                                       placeholder="<?= htmlspecialchars($gwKey === 'flutterwave' ? 'FLWSECK-...' : 'sk_live_...') ?>">
                            </div>
                            <?php if ($gwKey === 'flutterwave'): ?>
                            <div class="col-12">
                                <label class="form-label-admin">Encryption key <span class="text-muted" style="font-weight:400;">(optional, 32 chars)</span></label>
                                <input type="password" name="settings[flutterwave_encryption_key]"
                                       class="form-control-admin w-100" value="<?= htmlspecialchars(sval($currentSettings, 'flutterwave_encryption_key')) ?>"
                                       placeholder="32-character encryption key">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- OAuth, Social & Policy Thresholds -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <h6 class="text-white fw-bold mb-3"><i class="ph-bold ph-shield-check text-success"></i> OAuth & Compliance Policies</h6>
                
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-admin">Google OAuth Client ID</label>
                        <input type="text" name="settings[google_client_id]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'google_client_id') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Google Client Secret</label>
                        <input type="password" name="settings[google_client_secret]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'google_client_secret') ?>">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-admin">Policy Compliance Min %</label>
                        <input type="number" name="settings[policy_compliance_threshold]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'policy_compliance_threshold', '70') ?>" min="1" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Cookie Consent Default</label>
                        <select name="settings[consent_mode_default]" class="form-select-admin w-100">
                            <option value="denied" <?= ($currentSettings['consent_mode_default'] ?? '') === 'denied' ? 'selected' : '' ?>>Denied (GDPR Strict)</option>
                            <option value="granted" <?= ($currentSettings['consent_mode_default'] ?? '') === 'granted' ? 'selected' : '' ?>>Granted</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label-admin">Referral Reward ($)</label>
                        <input type="number" step="0.01" name="settings[referral_reward_amount]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'referral_reward_amount', '50') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Refill Reward Credits</label>
                        <input type="number" name="settings[auto_refill_reward_credits]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'auto_refill_reward_credits', '10') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Third-Party Gateways (Bundle.social & Zernio) -->
        <div class="col-12">
            <div class="admin-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="text-white fw-bold mb-0">
                        <i class="ph-bold ph-plugs-connected text-accent me-1"></i> Third-Party Unified API Gateways (Architecture Mandate)
                    </h6>
                    <span class="badge bg-success bg-opacity-25 text-success font-size-11 px-2 py-1">Dual Gateway Architecture Active</span>
                </div>
                <p class="text-muted small mb-4">
                    100% of external platform traffic is routed exclusively through these two unified gateways. Direct social network API credentials (Meta Graph, TikTok API, Google Ads, Snapchat) have been eliminated.
                </p>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="ph-bold ph-share-network text-info fs-5"></i>
                                <span class="text-white fw-bold">Bundle.social API (Organic Social Hub)</span>
                            </div>
                            <p class="text-muted font-size-12 mb-3">Handles all multi-account organic publishing, scheduled post queue, UGC video publishing, comment moderation, and Google Calendar sync.</p>
                            
                            <div class="mb-3">
                                <label class="form-label-admin">Bundle.social API Key</label>
                                <input type="password" name="settings[bundle_social_api_key]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'bundle_social_api_key', defined('BUNDLE_SOCIAL_API_KEY') ? BUNDLE_SOCIAL_API_KEY : '') ?>" placeholder="bndl_live_...">
                            </div>
                            <div>
                                <label class="form-label-admin">Bundle.social Base API URL</label>
                                <input type="text" name="settings[bundle_social_base_url]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'bundle_social_base_url', defined('BUNDLE_SOCIAL_BASE_URL') ? BUNDLE_SOCIAL_BASE_URL : 'https://api.bundle.social/v1') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="ph-bold ph-megaphone text-warning fs-5"></i>
                                <span class="text-white fw-bold">Zernio API (Paid Advertising Engine)</span>
                            </div>
                            <p class="text-muted font-size-12 mb-3">Handles multi-network paid ad launches, live ROAS/metric ingestion, 1-click campaign pause/resume, and auto-buyer anti-burn synchronization across Meta, TikTok, Google Ads, Snapchat, and Combo Pack.</p>
                            
                            <div class="mb-3">
                                <label class="form-label-admin">Zernio API Key</label>
                                <input type="password" name="settings[zernio_api_key]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'zernio_api_key', defined('ZERNIO_API_KEY') ? ZERNIO_API_KEY : '') ?>" placeholder="zrn_live_...">
                            </div>
                            <div>
                                <label class="form-label-admin">Zernio Base API URL</label>
                                <input type="text" name="settings[zernio_base_url]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'zernio_base_url', defined('ZERNIO_BASE_URL') ? ZERNIO_BASE_URL : 'https://api.zernio.com/v1') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Site Access & Maintenance -->
        <div class="col-12">
            <div class="admin-card">
                <h6 class="text-white fw-bold mb-3"><i class="ph-bold ph-power text-danger"></i> Site Access & Maintenance</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-admin">Maintenance Mode</label>
                        <select name="settings[maintenance_mode]" class="form-select-admin w-100">
                            <option value="0" <?= sval($currentSettings, 'maintenance_mode', '0') === '0' ? 'selected' : '' ?>>Off — site fully public</option>
                            <option value="1" <?= sval($currentSettings, 'maintenance_mode') === '1' ? 'selected' : '' ?>>On — public sees a maintenance page (admins still have access)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Open Registration</label>
                        <select name="settings[registration_open]" class="form-select-admin w-100">
                            <option value="1" <?= sval($currentSettings, 'registration_open', '1') === '1' ? 'selected' : '' ?>>Open — anyone can create an account</option>
                            <option value="0" <?= sval($currentSettings, 'registration_open') === '0' ? 'selected' : '' ?>>Closed — registration page shows a waiting notice</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">New User AI Credit Grant</label>
                        <input type="number" name="settings[signup_ai_credits]" class="form-control-admin w-100" value="<?= sval($currentSettings, 'signup_ai_credits', '100') ?>" min="0">
                        <small class="text-muted" style="font-size: 11px;">AI credits granted automatically when a new user registers.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 text-end">
        <button type="submit" class="btn-admin btn-admin-green px-4 py-2" style="font-size: 0.95rem;">
            <i class="ph-bold ph-floppy-disk"></i> Save All Platform Settings
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
