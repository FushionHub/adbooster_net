<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ThirdPartyGateway.php';
require_once __DIR__ . '/modules/notification_service.php';

$userId = current_user_id();
$tab = $_GET['tab'] ?? 'all'; // 'all', 'organic', 'ads'

// Start Bundle.social connect ON CLICK (server dispatch → OAuth redirect).
// Connect requests are dispatched here so clicking a platform card always
// starts the real gateway flow — no live gateway calls at render time.
if (isset($_GET['action']) && $_GET['action'] === 'connect_bundle') {
    $platform = strtolower(trim((string)($_GET['platform'] ?? '')));
    $authUrl  = ThirdPartyGateway::getBundleConnectUrl($userId, $platform, 'social-connect.php');
    if ($authUrl === '') {
        set_flash('error', 'Bundle.social could not start the connection (gateway key or connectivity issue). Check the gateway settings and try again.');
        redirect(url('social-connect'));
    }
    header('Location: ' . $authUrl);
    exit;
}

// Start Zernio ad-account connect ON CLICK (server dispatch → OAuth redirect).
if (isset($_GET['action']) && $_GET['action'] === 'connect_zernio') {
    $network = strtolower(trim((string)($_GET['platform'] ?? '')));
    $authUrl = ThirdPartyGateway::getZernioAdConnectUrl($userId, $network, 'social-connect.php');
    if ($authUrl === '') {
        set_flash('error', 'Zernio could not start the ads connection (gateway key or connectivity issue). Check the gateway settings and try again.');
        redirect(url('social-connect'));
    }
    header('Location: ' . $authUrl);
    exit;
}

// Handle Bundle.social OAuth callback (organic journey — signed, single-use state)
if (isset($_GET['action']) && $_GET['action'] === 'bundle_connected') {
    $validatedPlatform = ThirdPartyGateway::validateGatewayState($_GET['state'] ?? null, 'bundle_social', $userId);
    if ($validatedPlatform === null) {
        set_flash('error', 'Bundle.social connection could not be verified (invalid, expired or already-used auth state). Please start the connect flow again.');
        redirect(url('social-connect'));
    }
    $platform = $validatedPlatform;
    $extId = trim((string)($_GET['account_id'] ?? ($_GET['external_id'] ?? ('bndl_' . $platform . '_' . bin2hex(random_bytes(4))))));
    $name = trim((string)($_GET['display_name'] ?? ($_GET['name'] ?? ucfirst($platform) . ' Profile')));
    $avatar = trim((string)($_GET['avatar_url'] ?? ''));

    try {
        db()->query(
            "INSERT INTO connected_accounts (user_id, gateway_provider, account_type, platform_name, external_account_id, display_name, avatar_url, status)
             VALUES (?, 'bundle_social', 'organic_profile', ?, ?, ?, ?, 'connected')
             ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), avatar_url = VALUES(avatar_url), status = 'connected'",
            [$userId, $platform, $extId, $name, $avatar]
        );
        try {
            NotificationService::create(
                $userId,
                'social_connected',
                'Organic Profile Connected',
                "{$name} was successfully linked via Bundle.social.",
                '/social-connect.php'
            );
        } catch (\Throwable $e) {}
        try {
            require_once __DIR__ . '/modules/notifier.php';
            Notifier::user($userId, 'account_connected', [
                'platform'     => ucfirst((string)$platform),
                'account_name' => $name,
                'account_type' => 'Organic profile (Bundle.social)',
                'connected_at' => date('c'),
                'link'         => BASE_URL . '/post-creator',
            ]);
        } catch (\Throwable $e) {
            error_log('Account-connected email failed: ' . $e->getMessage());
        }
        set_flash('success', "Organic social profile ({$name}) connected successfully via Bundle.social!");
    } catch (\Throwable $e) {
        set_flash('error', "Could not link account: " . $e->getMessage());
    }
    redirect(url('social-connect'));
}

// Handle Zernio Ad Account callback (paid-ads journey — signed, single-use state)
if (isset($_GET['action']) && $_GET['action'] === 'zernio_connected') {
    $validatedNetwork = ThirdPartyGateway::validateGatewayState($_GET['state'] ?? null, 'zernio', $userId);
    if ($validatedNetwork === null) {
        set_flash('error', 'Zernio connection could not be verified (invalid, expired or already-used auth state). Please start the ad-account connect flow again.');
        redirect(url('social-connect'));
    }
    $network = $validatedNetwork;
    $extId = trim((string)($_GET['ad_account_id'] ?? ($_GET['account_id'] ?? ('act_' . $network . '_' . bin2hex(random_bytes(4))))));
    $name = trim((string)($_GET['name'] ?? ($_GET['display_name'] ?? ucfirst($network) . ' Ad Account')));

    try {
        db()->query(
            "INSERT INTO connected_accounts (user_id, gateway_provider, account_type, platform_name, external_account_id, display_name, status)
             VALUES (?, 'zernio', 'ad_account', ?, ?, ?, 'connected')
             ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), status = 'connected'",
            [$userId, $network, $extId, $name]
        );
        try {
            NotificationService::create(
                $userId,
                'social_connected',
                'Ad Manager Connected',
                "{$name} was successfully linked via Zernio Ad Gateway.",
                '/social-connect.php'
            );
        } catch (\Throwable $e) {}
        try {
            require_once __DIR__ . '/modules/notifier.php';
            Notifier::user($userId, 'account_connected', [
                'platform'     => ucfirst((string)$network) . ' Ads',
                'account_name' => $name,
                'account_type' => 'Ad account (Zernio)',
                'connected_at' => date('c'),
                'link'         => BASE_URL . '/create-ad',
            ]);
        } catch (\Throwable $e) {
            error_log('Ad-account-connected email failed: ' . $e->getMessage());
        }
        set_flash('success', "Ad Manager account ({$name}) connected successfully via Zernio!");
    } catch (\Throwable $e) {
        set_flash('error', "Could not link ad account: " . $e->getMessage());
    }
    redirect(url('social-connect'));
}

// Handle Disconnect & Manual Linking POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
    } else {
        $action = $_POST['action'];

        if ($action === 'disconnect') {
            $accId = (int)($_POST['account_id'] ?? 0);
            if ($accId > 0) {
                db()->delete('connected_accounts', 'user_id = ? AND id = ?', [$userId, $accId]);
                set_flash('success', 'Account unlinked successfully.');
            }
        } elseif ($action === 'manual_connect_organic') {
            $platform    = trim((string)($_POST['platform'] ?? 'facebook'));
            $accountName = trim((string)($_POST['account_name'] ?? ''));
            $accountId   = trim((string)($_POST['account_id'] ?? ''));

            if ($accountName === '') $accountName = ucfirst($platform) . ' Organic Profile';
            if ($accountId === '') $accountId = 'bndl_' . $platform . '_' . bin2hex(random_bytes(4));

            db()->query(
                "INSERT INTO connected_accounts (user_id, gateway_provider, account_type, platform_name, external_account_id, display_name, status)
                 VALUES (?, 'bundle_social', 'organic_profile', ?, ?, ?, 'connected')
                 ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), status = 'connected'",
                [$userId, $platform, $accountId, $accountName]
            );
            set_flash('success', "{$accountName} linked to Bundle.social successfully!");
        } elseif ($action === 'manual_connect_ad') {
            $network     = trim((string)($_POST['network'] ?? 'meta'));
            $accountName = trim((string)($_POST['account_name'] ?? ''));
            $adAccountId = trim((string)($_POST['ad_account_id'] ?? ''));

            if ($accountName === '') $accountName = ucfirst($network) . ' Ad Account';
            if ($adAccountId === '') $adAccountId = 'act_' . $network . '_' . bin2hex(random_bytes(4));

            db()->query(
                "INSERT INTO connected_accounts (user_id, gateway_provider, account_type, platform_name, external_account_id, display_name, status)
                 VALUES (?, 'zernio', 'ad_account', ?, ?, ?, 'connected')
                 ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), status = 'connected'",
                [$userId, $network, $adAccountId, $accountName]
            );
            set_flash('success', "{$accountName} linked to Zernio Ad Engine successfully!");
        }
    }
    redirect(url('social-connect'));
}

// Fetch user's connected accounts
$allConnected = db()->fetchAll("SELECT * FROM connected_accounts WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
$organicAccounts = array_filter($allConnected, fn($a) => ($a['gateway_provider'] ?? '') === 'bundle_social');
$adAccounts      = array_filter($allConnected, fn($a) => ($a['gateway_provider'] ?? '') === 'zernio');

$connectedOrganicPlatforms = array_column($organicAccounts, 'platform_name');
$connectedAdPlatforms      = array_column($adAccounts, 'platform_name');

$organicList = [
    'facebook'  => ['label' => 'Facebook',       'icon' => 'ph-facebook-logo', 'color' => '#1877F2', 'desc' => 'Pages, groups, and auto-feed scheduling'],
    'instagram' => ['label' => 'Instagram',      'icon' => 'ph-instagram-logo','color' => '#E4405F', 'desc' => 'Feed posts, carousels, and 9:16 reels'],
    'tiktok'    => ['label' => 'TikTok',         'icon' => 'ph-tiktok-logo',   'color' => '#00f2fe', 'desc' => 'Short-form UGC video clips & sounds'],
    'twitter'   => ['label' => 'Twitter / X',    'icon' => 'ph-x-logo',        'color' => '#f8fafc', 'desc' => 'Instant updates, threads, and viral hooks'],
    'linkedin'  => ['label' => 'LinkedIn',       'icon' => 'ph-linkedin-logo', 'color' => '#0A66C2', 'desc' => 'Professional thought leadership & articles'],
    'pinterest' => ['label' => 'Pinterest',      'icon' => 'ph-pinterest-logo','color' => '#E60023', 'desc' => 'Product pins, lifestyle boards & traffic'],
    'youtube'   => ['label' => 'YouTube Shorts', 'icon' => 'ph-youtube-logo',  'color' => '#FF0000', 'desc' => 'Vertical Shorts automated publishing'],
];

$adList = [
    'meta'      => ['label' => 'Meta Ads (FB & IG)', 'icon' => 'ph-facebook-logo',  'color' => '#1877F2', 'desc' => 'Advantage+ shopping, dynamic creative feeds, and retargeting'],
    'tiktok'    => ['label' => 'TikTok Ads Manager', 'icon' => 'ph-video-camera',   'color' => '#00f2fe', 'desc' => 'Spark ads, in-feed video ads, and high-converting UGC'],
    'google'    => ['label' => 'Google Ads & PMax',  'icon' => 'ph-google-logo',    'color' => '#4285F4', 'desc' => 'High-intent search, Performance Max, and YouTube video ads'],
    'linkedin'  => ['label' => 'LinkedIn Ads',       'icon' => 'ph-linkedin-logo',  'color' => '#0A66C2', 'desc' => 'B2B lead-gen campaigns, sponsored content, and job ads'],
    'pinterest' => ['label' => 'Pinterest Ads',      'icon' => 'ph-pinterest-logo', 'color' => '#E60023', 'desc' => 'Product pins, shopping spotlights, and affinity targeting'],
];

$pageTitle = 'Connected Accounts & Third-Party Gateways — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <!-- Header -->
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Channel Connections &amp; Gateways</h1>
            <p class="page-subtitle">Route 100% of social media and advertising activity through unified high-speed third-party gateways.</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span class="badge-status badge-active" style="font-size:12px;padding:6px 14px">
                <i class="ph ph-share-network"></i> <?= count($organicAccounts) ?> Organic (Bundle.social)
            </span>
            <span class="badge-status badge-info" style="font-size:12px;padding:6px 14px">
                <i class="ph ph-megaphone"></i> <?= count($adAccounts) ?> Ad Accounts (Zernio)
            </span>
        </div>
    </div>

    <!-- Architecture Callout Banner -->
    <div style="background:linear-gradient(135deg, rgba(200,255,0,0.06), rgba(56,189,248,0.06));border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:20px 24px;margin-bottom:28px">
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(200,255,0,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:22px;flex-shrink:0">
                <i class="ph ph-plugs-connected"></i>
            </div>
            <div style="flex:1;min-width:260px">
                <h5 style="color:#fff;font-size:15px;font-weight:700;margin:0 0 4px">Dual Third-Party Integration Architecture</h5>
                <p style="color:var(--fg2);font-size:13px;line-height:1.5;margin:0">
                    Legacy direct social API tokens have been replaced with two dedicated gateways. 
                    <strong style="color:var(--accent)">Bundle.social API</strong> handles organic post scheduling, video publishing, and calendar sync. 
                    <strong style="color:var(--blue)">Zernio API</strong> manages multi-network ad launches, budget scaling, and real-time pause/resume.
                </p>
            </div>
        </div>
    </div>

    <!-- Gateway 1: Bundle.social Organic Hub -->
    <div class="card-dark" style="margin-bottom:32px;padding:24px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(200,255,0,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:20px">
                    <i class="ph ph-share-network"></i>
                </div>
                <div>
                    <h4 style="margin:0;font-size:16px;font-weight:700;color:#fff">Bundle.social — Organic Media Gateway</h4>
                    <p style="margin:2px 0 0;font-size:12.5px;color:var(--fg3)">Multi-account organic post scheduling, UGC video distribution, and automation.</p>
                </div>
            </div>
            <a href="<?= url('post-creator') ?>" class="btn-accent btn-sm">
                <i class="ph ph-paper-plane-tilt"></i> Open Post Studio
            </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
            <?php foreach ($organicList as $opKey => $op):
                $isLinked = in_array($opKey, $connectedOrganicPlatforms, true);
                $linkedAccount = null;
                foreach ($organicAccounts as $oa) {
                    if (($oa['platform_name'] ?? '') === $opKey) {
                        $linkedAccount = $oa;
                        break;
                    }
                }
                // Connect happens ON CLICK via the server-dispatch route — the
                // gateway OAuth URL is only resolved when the user initiates it.
                $connectUrl = url('social-connect') . '?action=connect_bundle&platform=' . rawurlencode($opKey);
            ?>
                <div style="background:var(--bg);border:1px solid <?= $isLinked ? 'rgba(200,255,0,0.3)' : 'var(--border)' ?>;border-radius:14px;padding:16px;display:flex;flex-direction:column;justify-content:space-between">
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;color:<?= $op['color'] ?>;font-size:18px">
                                    <i class="ph <?= $op['icon'] ?>"></i>
                                </div>
                                <strong style="color:#fff;font-size:14px"><?= $op['label'] ?></strong>
                            </div>
                            <?php if ($isLinked): ?>
                                <span class="badge-status badge-active" style="font-size:10.5px">Connected</span>
                            <?php else: ?>
                                <span class="badge-status badge-inactive" style="font-size:10.5px">Unlinked</span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size:12px;color:var(--fg3);margin:0 0 14px;line-height:1.4"><?= $op['desc'] ?></p>
                        <?php if ($linkedAccount): ?>
                            <div style="background:rgba(255,255,255,0.03);padding:8px 10px;border-radius:8px;margin-bottom:12px;font-size:11.5px">
                                <div style="color:var(--fg);font-weight:600"><?= e($linkedAccount['display_name']) ?></div>
                                <div style="color:var(--fg3);font-family:'JetBrains Mono',monospace;font-size:10px"><?= e($linkedAccount['external_account_id']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($isLinked && $linkedAccount): ?>
                            <form method="POST" onsubmit="return confirm('Disconnect this account from Bundle.social?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="disconnect">
                                <input type="hidden" name="account_id" value="<?= (int)$linkedAccount['id'] ?>">
                                <button type="submit" class="btn-outline-dark btn-sm w-100" style="color:#f43f5e;border-color:rgba(244,63,94,0.3)">
                                    <i class="ph ph-link-break"></i> Disconnect
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= e($connectUrl) ?>" class="btn-accent btn-sm w-100" style="justify-content:center">
                                <i class="ph ph-plug"></i> Connect via Bundle.social
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Gateway 2: Zernio Paid Advertising Manager -->
    <div class="card-dark" style="margin-bottom:32px;padding:24px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(56,189,248,0.12);display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:20px">
                    <i class="ph ph-megaphone"></i>
                </div>
                <div>
                    <h4 style="margin:0;font-size:16px;font-weight:700;color:#fff">Zernio — Paid Advertising Gateway</h4>
                    <p style="margin:2px 0 0;font-size:12.5px;color:var(--fg3)">Multi-network campaign launcher, automated budget scaling, and ROAS telemetry.</p>
                </div>
            </div>
            <a href="<?= url('create-ad') ?>" class="btn-outline-dark btn-sm" style="color:var(--blue);border-color:rgba(56,189,248,0.3)">
                <i class="ph ph-rocket-launch"></i> Launch Campaign
            </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
            <?php foreach ($adList as $apKey => $ap):
                $isLinked = in_array($apKey, $connectedAdPlatforms, true);
                $linkedAccount = null;
                foreach ($adAccounts as $aa) {
                    if (($aa['platform_name'] ?? '') === $apKey) {
                        $linkedAccount = $aa;
                        break;
                    }
                }
                // Connect happens ON CLICK via the server-dispatch route — the
                // gateway OAuth URL is only resolved when the user initiates it.
                $connectUrl = url('social-connect') . '?action=connect_zernio&platform=' . rawurlencode($apKey);
            ?>
                <div style="background:var(--bg);border:1px solid <?= $isLinked ? 'rgba(56,189,248,0.35)' : 'var(--border)' ?>;border-radius:14px;padding:16px;display:flex;flex-direction:column;justify-content:space-between">
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;color:<?= $ap['color'] ?>;font-size:18px">
                                    <i class="ph <?= $ap['icon'] ?>"></i>
                                </div>
                                <strong style="color:#fff;font-size:14px"><?= $ap['label'] ?></strong>
                            </div>
                            <?php if ($isLinked): ?>
                                <span class="badge-status badge-info" style="font-size:10.5px">Active</span>
                            <?php else: ?>
                                <span class="badge-status badge-inactive" style="font-size:10.5px">Unlinked</span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size:12px;color:var(--fg3);margin:0 0 14px;line-height:1.4"><?= $ap['desc'] ?></p>
                        <?php if ($linkedAccount): ?>
                            <div style="background:rgba(255,255,255,0.03);padding:8px 10px;border-radius:8px;margin-bottom:12px;font-size:11.5px">
                                <div style="color:var(--fg);font-weight:600"><?= e($linkedAccount['display_name']) ?></div>
                                <div style="color:var(--blue);font-family:'JetBrains Mono',monospace;font-size:10px"><?= e($linkedAccount['external_account_id']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($isLinked && $linkedAccount): ?>
                            <form method="POST" onsubmit="return confirm('Disconnect this ad account from Zernio?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="disconnect">
                                <input type="hidden" name="account_id" value="<?= (int)$linkedAccount['id'] ?>">
                                <button type="submit" class="btn-outline-dark btn-sm w-100" style="color:#f43f5e;border-color:rgba(244,63,94,0.3)">
                                    <i class="ph ph-link-break"></i> Disconnect
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= e($connectUrl) ?>" class="btn-outline-dark btn-sm w-100" style="justify-content:center;color:var(--blue);border-color:rgba(56,189,248,0.3)">
                                <i class="ph ph-plug"></i> Connect Ad Account via Zernio
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
