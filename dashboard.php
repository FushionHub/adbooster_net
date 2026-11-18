<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/ThirdPartyGateway.php';

$userId   = current_user_id();
$userData = current_user();

// Start Bundle.social connect (server dispatch → OAuth URL redirect). The
// gateway call is done HERE, not at render time, so the dashboard never
// makes live upstream calls while painting the page.
if (isset($_GET['action']) && $_GET['action'] === 'connect_bundle') {
    $platform = strtolower(trim((string)($_GET['platform'] ?? '')));
    $authUrl  = ThirdPartyGateway::getBundleConnectUrl($userId, $platform);
    if ($authUrl === '') {
        set_flash('error', 'Bundle.social could not start the connection (gateway key or connectivity issue). Check the gateway settings and try again.');
        redirect(url('dashboard'));
    }
    header('Location: ' . $authUrl);
    exit;
}

// Start Zernio ad-account connect (server dispatch → OAuth URL redirect).
if (isset($_GET['action']) && $_GET['action'] === 'connect_zernio') {
    $network = strtolower(trim((string)($_GET['platform'] ?? '')));
    $authUrl = ThirdPartyGateway::getZernioAdConnectUrl($userId, $network);
    if ($authUrl === '') {
        set_flash('error', 'Zernio could not start the ads connection (gateway key or connectivity issue). Check the gateway settings and try again.');
        redirect(url('dashboard'));
    }
    header('Location: ' . $authUrl);
    exit;
}

// Gateway OAuth callbacks land here (the connect links default to this page).
// They are FORWARDED to the Channel Connections hub, which owns the verified
// persistence flow: the connection is re-checked server-side with the gateway
// (Bundle social-account/by-type / Zernio accounts list) and the REAL id is
// stored. This page previously invented ids like 'bndl_facebook_…' and
// 'act_google_…' from callback query params, marking accounts connected that
// did not exist — every later publish then failed with "No social accounts
// selected".
if (isset($_GET['action']) && in_array($_GET['action'], ['bundle_connected', 'zernio_connected'], true)) {
    $cbKind  = $_GET['action'] === 'zernio_connected' ? 'ad-account' : 'social';
    $cbError = trim((string)($_GET['error'] ?? ''));
    if ($cbError !== '') {
        // The provider landed back with ?error= appended (cancelled or
        // refused authorization) — there is no valid state to process.
        set_flash('error', 'The ' . $cbKind . ' connection was not completed: ' . $cbError . '. Please start the connect flow again.');
        redirect(url('social-connect'));
    }
    $cbQuery = ['action' => (string)$_GET['action']];
    if (isset($_GET['state'])) {
        $cbQuery['state'] = (string)$_GET['state'];
    }
    redirect(url('social-connect') . '?' . http_build_query($cbQuery));
}

// Handle Campaign Pause/Resume Toggle — ASYNCHRONOUS via the Zernio queue.
// The request only enqueues the job and answers instantly; the local status
// stays untouched until cron/queue_worker.php reports Zernio's confirmation
// (the gateway remains the single source of truth).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'toggle_campaign') {
    if (csrf_verify()) {
        $cId = (int)($_POST['campaign_id'] ?? 0);
        $campaign = db()->fetchOne("SELECT * FROM ad_campaigns WHERE id = ? AND user_id = ?", [$cId, $userId]);
        if ($campaign) {
            $newStatus = ($campaign['status'] === 'active') ? 'paused' : 'active';

            if (!class_exists('AsyncQueue')) {
                require_once __DIR__ . '/modules/AsyncQueue.php';
            }
            $jobId = AsyncQueue::push($userId, 'zernio_toggle_status', [
                'internal_campaign_id' => $cId,
                'zernio_campaign_id'   => (string)($campaign['zernio_campaign_id'] ?? ''),
                'target_status'        => $newStatus,
            ], null, 'campaign-toggle:' . $cId);

            if ($jobId > 0) {
                set_flash('success', " '{$campaign['campaign_name']}' {$newStatus} request queued for Zernio (job #{$jobId}). Background workers are applying it now — status updates automatically on gateway confirmation.");
            } else {
                set_flash('error', "Background queue temporarily unavailable — '{$campaign['campaign_name']}' status left unchanged.");
            }
        }
    }
    redirect(url('dashboard'));
}

$pageTitle = 'Dashboard — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';

$adCredits    = (float)($userData['ad_credits'] ?? 0);
$aiCredits    = (int)($userData['ai_credits'] ?? 0);
$referralCode = $userData['referral_code'] ?? '';
$referralCount = 0;
try { $referralCount = db()->count('users', 'referred_by = ?', [$referralCode]); } catch (Throwable $e) {}

$planRow = null;
try {
    $planRow = db()->fetchOne(
        "SELECT p.name as plan_name, t.created_at as expires_at FROM transactions t JOIN pricing_plans p ON p.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(t.metadata, '$.plan_id')) AS UNSIGNED) WHERE t.user_id = ? AND t.type = 'plan_subscription' AND t.status = 'successful' ORDER BY t.created_at DESC LIMIT 1",
        [$userId]
    );
} catch (Throwable $e) {}
$planName = $planRow['plan_name'] ?? 'Free Tier';

// Gateway-specific account lookups
$organicAccounts = db()->fetchAll(
    "SELECT * FROM connected_accounts WHERE user_id = ? AND gateway_provider = 'bundle_social' AND status = 'connected'",
    [$userId]
);
$adAccounts = db()->fetchAll(
    "SELECT * FROM connected_accounts WHERE user_id = ? AND gateway_provider = 'zernio' AND status = 'connected'",
    [$userId]
);
$connectedOrganicPlatforms = array_column($organicAccounts, 'platform_name');
$connectedAdPlatforms = array_column($adAccounts, 'platform_name');

// Backward compatibility array
$connectedPlatforms = array_merge($connectedOrganicPlatforms, $connectedAdPlatforms);

$recentCampaigns = db()->fetchAll("SELECT id, campaign_name, campaign_type, status, budget_amount, zernio_campaign_id, created_at FROM ad_campaigns WHERE user_id = ? ORDER BY created_at DESC LIMIT 6", [$userId]);
$recentTransactions = db()->fetchAll("SELECT id, type, amount, currency, gateway, gateway_reference, status, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 6", [$userId]);

$hour = (int)date('H');
$timeGreeting = match (true) {
    $hour < 12 => 'Good morning',
    $hour < 17 => 'Good afternoon',
    default    => 'Good evening',
};
$referralUrl = BASE_URL . '/register.php?ref=' . urlencode($referralCode);
?>

<!-- Top Welcome & Quick Action Bar -->
<div class="page-header" style="margin-bottom:28px">
    <div>
        <h1 class="page-title" style="margin:0"><?= $timeGreeting ?>, <?= $_appName ?></h1>
        <p class="page-subtitle" style="margin-top:4px">
            <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase"><?= e($planName) ?></span>
        </p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <a href="<?= url('create-ad') ?>" class="btn-accent">
            <i class="ph ph-plus-circle" style="font-size:18px"></i> New Campaign
        </a>
        <a href="<?= url('post-creator') ?>" class="btn-outline-dark">
            <i class="ph ph-note-pencil" style="font-size:18px"></i> Create Post
        </a>
        <a href="<?= url('billing') ?>" class="btn-outline-dark">
            <i class="ph ph-wallet" style="font-size:18px"></i> Add Funds
        </a>
    </div>
</div>

<?php
// Review prompt eligibility: Pro users anytime; Free users after 5 days of usage
$showReviewPrompt = false;
$promptDismissed = (int)($_SESSION['review_prompt_dismissed'] ?? $_COOKIE['review_prompt_dismissed'] ?? 0);
if (time() > $promptDismissed) {
    try {
        $hasReviewed = (bool)db()->fetchOne("SELECT id FROM site_reviews WHERE user_id = ?", [$userId]);
        if (!$hasReviewed) {
            $createdAt = strtotime($userData['created_at'] ?? 'now');
            $daysSinceSignup = (int)floor((time() - $createdAt) / 86400);
            $isProUser = in_array($userData['role'] ?? '', ['admin', 'pro', 'agency'], true) || !empty($planRow);
            if ($isProUser || $daysSinceSignup >= 5) {
                $showReviewPrompt = true;
            }
        }
    } catch (\Throwable $e) {}
}
?>
<?php if ($showReviewPrompt): ?>
    <div class="card-dark" style="padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
        <div style="display:flex;align-items:center;gap:14px;min-width:0">
            <i class="ph-fill ph-star" style="font-size:20px;color:var(--accent);flex-shrink:0"></i>
            <div style="min-width:0">
                <div style="font-weight:600;font-size:14px;color:var(--fg)">How is scaling with Adsbooster.net going?</div>
                <div style="font-size:12.5px;color:var(--fg3);margin-top:2px">Rate your experience — good reviews are featured on the community homepage.</div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <a href="<?= url('reviews') ?>" class="btn-accent btn-sm">
                <i class="ph-bold ph-pencil-simple"></i> Leave Review
            </a>
            <a href="<?= url('reviews', ['skip' => 1]) ?>" class="btn-outline-dark btn-sm">Skip</a>
        </div>
    </div>
<?php endif; ?>

<!-- Stats Row: KPI ticker band (label / value / link, ledger style) -->
<div class="kpi-band">
    <a href="<?= url('billing') ?>" class="kpi-cell">
        <div class="kpi-label">Ad Wallet Credits</div>
        <div class="kpi-value money"><?= price_fmt($adCredits) ?></div>
        <div class="kpi-delta"><span class="kpi-link">Top up →</span></div>
    </a>
    <a href="<?= url('post-creator') ?>" class="kpi-cell">
        <div class="kpi-label">AI Credits</div>
        <div class="kpi-value"><?= number_format($aiCredits) ?></div>
        <div class="kpi-delta"><span class="kpi-link">Generate →</span></div>
    </a>
    <a href="<?= url('analytics') ?>" class="kpi-cell">
        <div class="kpi-label">Campaigns Launched</div>
        <div class="kpi-value"><?= count($recentCampaigns) ?></div>
        <div class="kpi-delta"><span class="kpi-link">Analytics →</span></div>
    </a>
    <a href="<?= url('social-connect') ?>" class="kpi-cell">
        <div class="kpi-label">Connected Accounts</div>
        <div class="kpi-value"><?= count($organicAccounts) + count($adAccounts) ?></div>
        <div class="kpi-delta"><span class="kpi-link"><?= count($organicAccounts) ?> organic · <?= count($adAccounts) ?> ads →</span></div>
    </a>
</div>

<!-- Tool directory: two columns of hairline rows, mono index, no icon tiles -->
<div class="card-dark" style="margin-bottom:24px;padding:20px 22px">
    <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
        <h6 style="margin:0;font-size:13px;font-weight:600;font-family:'IBM Plex Mono',monospace;letter-spacing:.09em;text-transform:uppercase;color:var(--fg3)">Tool Directory</h6>
        <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--fg3)">15 tools</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(300px,100%),1fr));gap:0 32px">
        <?php
        $dashTools = [
            ['create-ad',         'ph-megaphone',      'Create Ad',          'Launch a paid campaign via Zernio'],
            ['post-creator',      'ph-note-pencil',    'Post Creator',       'AI organic posts, schedule or publish'],
            ['analytics',         'ph-chart-line-up',  'Analytics',          'Spend, reach and performance by network'],
            ['ab-test',           'ph-git-fork',       'A/B Testing',        'Split variants, auto-pick the winner'],
            ['ai-media-buyer',    'ph-shield-warning', 'AI Media Buyer',     'Autonomous budget rules and kill-switch'],
            ['ad-spy',            'ph-detective',      'Ad Spy',             'Competitive briefs from proven templates'],
            ['audience-builder',  'ph-users-three',    'Audience Builder',   'Segments and lookalike audiences'],
            ['ugc-generator',     'ph-image',          'AI Image Studio',    'Nano Banana image generation'],
            ['ugc-video-studio',  'ph-video-camera',   'AI Video Studio',    'Veo / Kling rendered ad videos'],
            ['marketplace',       'ph-storefront',     'Marketplace',        'Listings and business directory'],
            ['ecommerce-sync',    'ph-shopping-bag',   'E-Com Sync',         'Shopify and WooCommerce sync'],
            ['bounties',          'ph-hand-coins',     'Creator Bounties',   'Escrow-backed creative jobs'],
            ['agency-portal',     'ph-buildings',      'Agency Portal',      'Whitelabel client portal builder'],
            ['server-tracking',   'ph-broadcast',      'Server Tracking',    'Meta CAPI / TikTok conversion events'],
            ['calendar',          'ph-calendar-blank', 'Content Calendar',   'Scheduled posts and campaign dates'],
        ];
        $half = (int)ceil(count($dashTools) / 2);
        ?>
        <?php foreach ($dashTools as $i => $tool): ?>
            <?php if ($i === $half): ?>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(300px,100%),1fr));gap:0 32px;margin-top:2px">
            <?php endif; ?>
            <a href="<?= url($tool[0]) ?>" style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border-soft);text-decoration:none;color:var(--fg)">
                <span style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:var(--fg3);width:20px;flex-shrink:0"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <i class="ph <?= $tool[1] ?>" style="font-size:16px;color:var(--fg3);flex-shrink:0"></i>
                <span style="font-size:13.5px;font-weight:600;white-space:nowrap"><?= e($tool[2]) ?></span>
                <span style="font-size:12px;color:var(--fg3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-left:auto"><?= e($tool[3]) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Third-Party Unified Gateways Hub: Bundle.social (Organic) & Zernio (Paid Ads) -->
<div class="grid-2" style="margin-bottom:28px">
    <!-- 1. Organic Profiles Gateway (Bundle.social) -->
    <div class="card-dark" style="display:flex;flex-direction:column;justify-content:space-between;padding:20px 22px">
        <div>
            <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:8px">
                <h6 style="margin:0;font-size:13px;font-weight:600;font-family:'IBM Plex Mono',monospace;letter-spacing:.09em;text-transform:uppercase;color:var(--fg3)">Organic Social Hub</h6>
                <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:<?= count($organicAccounts) > 0 ? 'var(--green)' : 'var(--fg3)' ?>;"><?= count($organicAccounts) ?> connected</span>
            </div>
            <p style="margin:0 0 14px;font-size:12.5px;color:var(--fg3);line-height:1.5">
                Schedule posts and publish UGC across 15+ networks from one queue.
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
                <?php
                $organicPlatformList = [
                    'facebook'  => ['label' => 'Facebook', 'icon' => 'ph-facebook-logo'],
                    'instagram' => ['label' => 'Instagram', 'icon' => 'ph-instagram-logo'],
                    'tiktok'    => ['label' => 'TikTok', 'icon' => 'ph-tiktok-logo'],
                    'twitter'   => ['label' => 'X / Twitter', 'icon' => 'ph-x-logo'],
                    'linkedin'  => ['label' => 'LinkedIn', 'icon' => 'ph-linkedin-logo'],
                    'pinterest' => ['label' => 'Pinterest', 'icon' => 'ph-pinterest-logo'],
                ];
                foreach ($organicPlatformList as $opKey => $op):
                    $isConnected = in_array($opKey, $connectedOrganicPlatforms, true);
                ?>
                    <a href="<?= e(url('dashboard')) ?>?action=connect_bundle&platform=<?= e($opKey) ?>" class="platform-card <?= $isConnected ? 'selected' : '' ?>" style="flex:0 0 auto;padding:6px 12px;gap:8px;border-radius:99px;font-size:12px" title="<?= $isConnected ? 'Connected' : 'Click to connect via Bundle.social' ?>">
                        <i class="ph <?= $op['icon'] ?>" style="font-size:15px;color:<?= $isConnected ? 'var(--accent)' : 'var(--fg3)' ?>"></i>
                        <span><?= $op['label'] ?></span>
                        <span style="width:7px;height:7px;border-radius:50%;background:<?= $isConnected ? 'var(--green)' : 'var(--fg3)' ?>;flex-shrink:0"></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= url('post-creator') ?>" class="btn-accent btn-sm" style="flex:1;justify-content:center">
                <i class="ph ph-paper-plane-tilt"></i> Create Organic Post
            </a>
            <a href="<?= url('social-connect') ?>?tab=organic" class="btn-outline-dark btn-sm">
                <i class="ph ph-gear"></i> Manage
            </a>
        </div>
    </div>

    <!-- 2. Paid Ads Gateway (Zernio) -->
    <div class="card-dark" style="display:flex;flex-direction:column;justify-content:space-between;padding:20px 22px">
        <div>
            <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:8px">
                <h6 style="margin:0;font-size:13px;font-weight:600;font-family:'IBM Plex Mono',monospace;letter-spacing:.09em;text-transform:uppercase;color:var(--fg3)">Paid Ad Manager</h6>
                <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:<?= count($adAccounts) > 0 ? 'var(--blue)' : 'var(--fg3)' ?>;"><?= count($adAccounts) ?> ad accounts</span>
            </div>
            <p style="margin:0 0 14px;font-size:12.5px;color:var(--fg3);line-height:1.5">
                Launch, monitor, and auto-optimize campaigns across Meta, TikTok, Google Ads, and Snapchat.
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
                <?php
                $adPlatformList = [
                    'meta'     => ['label' => 'Meta Ads', 'icon' => 'ph-facebook-logo'],
                    'tiktok'   => ['label' => 'TikTok Ads', 'icon' => 'ph-video-camera'],
                    'google'   => ['label' => 'Google Ads', 'icon' => 'ph-google-logo'],
                    'linkedin' => ['label' => 'LinkedIn Ads', 'icon' => 'ph-linkedin-logo'],
                    'pinterest'=> ['label' => 'Pinterest Ads', 'icon' => 'ph-pinterest-logo'],
                ];
                foreach ($adPlatformList as $apKey => $ap):
                    $isConnected = in_array($apKey, $connectedAdPlatforms, true);
                ?>
                    <a href="<?= e(url('dashboard')) ?>?action=connect_zernio&platform=<?= e($apKey) ?>" class="platform-card <?= $isConnected ? 'selected' : '' ?>" style="flex:0 0 auto;padding:6px 12px;gap:8px;border-radius:99px;font-size:12px" title="<?= $isConnected ? 'Connected' : 'Click to link Ad Account via Zernio' ?>">
                        <i class="ph <?= $ap['icon'] ?>" style="font-size:15px;color:<?= $isConnected ? 'var(--blue)' : 'var(--fg3)' ?>"></i>
                        <span><?= $ap['label'] ?></span>
                        <span style="width:7px;height:7px;border-radius:50%;background:<?= $isConnected ? 'var(--green)' : 'var(--fg3)' ?>;flex-shrink:0"></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= url('create-ad') ?>" class="btn-outline-dark btn-sm" style="flex:1;justify-content:center">
                <i class="ph ph-rocket-launch"></i> Launch Paid Ad
            </a>
            <a href="<?= url('social-connect') ?>?tab=ads" class="btn-outline-dark btn-sm">
                <i class="ph ph-gear"></i> Manage
            </a>
        </div>
    </div>
</div>

<!-- Main Data Split: Campaigns & Transactions -->
<div class="grid-2" style="margin-bottom:28px">
    <!-- Recent Campaigns -->
    <div class="card-dark" style="padding:0;overflow:hidden;display:flex;flex-direction:column">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h6 style="margin:0;font-size:12px;font-weight:600;font-family:'IBM Plex Mono',monospace;letter-spacing:.09em;text-transform:uppercase;color:var(--fg3)">Campaigns · Zernio</h6>
            <a href="<?= url('create-ad') ?>" class="btn-accent btn-sm">
                <i class="ph ph-plus"></i> New
            </a>
        </div>
        <div style="flex:1">
            <?php if (empty($recentCampaigns)): ?>
                <div class="empty-state" style="padding:40px 20px">
                    <i class="ph ph-megaphone"></i>
                    <p style="font-weight:600;color:var(--fg);margin-bottom:4px">No campaigns launched yet</p>
                    <p style="font-size:13px;color:var(--fg3);margin-bottom:16px">Create your first ad to begin generating impressions and clicks.</p>
                    <a href="<?= url('create-ad') ?>" class="btn-accent btn-sm">Create First Ad</a>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border:none;border-radius:0">
                    <table class="table-dark">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Platform</th>
                                <th>Status</th>
                                <th>Budget</th>
                                <th style="text-align:right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCampaigns as $c):
                                $sc = match($c['status'] ?? '') { 'active' => 'active', 'paused' => 'inactive', 'completed' => 'accent', default => 'pending' };
                            ?>
                                <tr>
                                    <td style="font-weight:600">
                                        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px"><?= e($c['campaign_name']) ?></div>
                                        <?php if (!empty($c['zernio_campaign_id'])): ?>
                                            <span style="font-size:10px;color:var(--fg3);font-family:'IBM Plex Mono',monospace"><?= e(substr((string)$c['zernio_campaign_id'], 0, 16)) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge-platform"><?= e(ucfirst($c['campaign_type'])) ?></span></td>
                                    <td><span class="badge-status badge-<?= $sc ?>"><?= e(ucfirst($c['status'] ?? 'draft')) ?></span></td>
                                    <td style="font-weight:700;font-family:'IBM Plex Mono',monospace"><?= price_fmt((float)$c['budget_amount']) ?></td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <!-- Instant Pause / Resume Toggle -->
                                        <form method="POST" style="display:inline-block;margin-right:4px;">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_campaign">
                                            <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn-outline-dark btn-sm" title="<?= ($c['status'] ?? '') === 'active' ? 'Pause Campaign' : 'Resume Campaign' ?>" style="padding:4px 8px;font-size:11.5px">
                                                <i class="ph <?= ($c['status'] ?? '') === 'active' ? 'ph-pause' : 'ph-play' ?>"></i>
                                                <?= ($c['status'] ?? '') === 'active' ? 'Pause' : 'Resume' ?>
                                            </button>
                                        </form>
                                        <a href="<?= url('edit-campaign', ['id' => (int)$c['id']]) ?>" class="btn-outline-dark btn-sm" style="padding:4px 8px;font-size:11.5px">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card-dark" style="padding:0;overflow:hidden;display:flex;flex-direction:column">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <h6 style="margin:0;font-size:12px;font-weight:600;font-family:'IBM Plex Mono',monospace;letter-spacing:.09em;text-transform:uppercase;color:var(--fg3)">Wallet Ledger</h6>
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm">View All</a>
        </div>
        <div style="flex:1">
            <?php if (empty($recentTransactions)): ?>
                <div class="empty-state" style="padding:40px 20px">
                    <i class="ph ph-credit-card"></i>
                    <p style="font-weight:600;color:var(--fg);margin-bottom:4px">No transactions recorded</p>
                    <p style="font-size:13px;color:var(--fg3);margin-bottom:16px">Top up your wallet to activate paid promotions and upgrades.</p>
                    <a href="<?= url('billing') ?>" class="btn-accent btn-sm">Fund Wallet</a>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border:none;border-radius:0">
                    <table class="table-dark">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th style="text-align:right">Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $t):
                                $tsc = match($t['status'] ?? '') { 'completed','successful' => 'active', 'pending' => 'pending', 'failed' => 'danger', default => 'pending' };
                            ?>
                                <tr>
                                    <td style="text-transform:capitalize;font-weight:500">
                                        <?= e(str_replace('_', ' ', $t['type'])) ?>
                                    </td>
                                    <td style="font-weight:700;font-family:'IBM Plex Mono',monospace"><?= txn_money((float)$t['amount'], $t['currency'] ?? null) ?></td>
                                    <td><span class="badge-status badge-<?= $tsc ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                                    <td style="text-align:right;color:var(--fg3);font-size:12.5px;white-space:nowrap"><?= dt($t['created_at'] ?? null) ?></td>
                                    <td style="text-align:right">
                                        <?php if (($t['status'] ?? '') === 'pending' && in_array($t['gateway'] ?? '', ['flutterwave', 'paystack'], true)):
                                            $verifyUrl = $t['gateway'] === 'paystack'
                                                ? url('payment-verify') . '?gateway=paystack&reference=' . urlencode((string)($t['gateway_reference'] ?? ''))
                                                : url('payment-verify') . '?gateway=flutterwave&tx_ref=' . urlencode((string)($t['gateway_reference'] ?? $t['id']));
                                        ?>
                                            <a href="<?= $verifyUrl ?>" class="btn-outline-dark btn-sm" style="white-space:nowrap" title="Re-check this payment with the gateway and credit your wallet if it succeeded">
                                                <i class="ph ph-arrows-clockwise"></i> Verify
                                            </a>
                                        <?php elseif (in_array((string)($t['status'] ?? ''), ['completed', 'successful', 'success'], true)): ?>
                                            <a href="<?= e(url('invoice', ['id' => (int)$t['id']])) ?>" target="_blank" rel="noopener" class="btn-outline-dark btn-sm" style="white-space:nowrap" title="Open the invoice for this transaction">
                                                <i class="ph ph-file-text"></i> Invoice
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Referral Partner Full-Width Banner -->
<div class="card-dark" style="padding:20px 24px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px">
        <div style="display:flex;align-items:center;gap:36px;flex-wrap:wrap">
            <div>
                <h6 style="margin:0;font-size:13px;font-weight:600;font-family:'IBM Plex Mono',monospace;letter-spacing:.09em;text-transform:uppercase;color:var(--fg3)">Referral Partner Program</h6>
                <p style="margin:4px 0 0;font-size:13px;color:var(--fg2)">Earn 10 ad credits for every client who signs up with your link.</p>
            </div>
            <div style="display:flex;align-items:center;gap:24px">
                <div>
                    <div style="font-size:22px;font-weight:700;color:var(--fg);line-height:1;font-family:'IBM Plex Mono',monospace"><?= number_format($referralCount) ?></div>
                    <div style="font-size:11px;color:var(--fg3);margin-top:4px;text-transform:uppercase;letter-spacing:.08em">Invited</div>
                </div>
                <div style="width:1px;height:32px;background:var(--border)"></div>
                <div>
                    <div style="font-size:22px;font-weight:700;color:var(--accent);line-height:1;font-family:'IBM Plex Mono',monospace"><?= number_format($referralCount * 10) ?></div>
                    <div style="font-size:11px;color:var(--fg3);margin-top:4px;text-transform:uppercase;letter-spacing:.08em">Ad Credits Earned</div>
                </div>
            </div>
        </div>

        <!-- 1-Click Referral Link -->
        <div style="flex:1;min-width:300px;max-width:540px">
            <div style="display:flex;gap:8px">
                <input type="text" readonly value="<?= e($referralUrl) ?>" class="form-control" style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;color:var(--fg);background:var(--bg)!important" id="refLinkInput">
                <button type="button" class="btn-accent btn-sm" style="white-space:nowrap" onclick="copyRefLink(this)">
                    <i class="ph ph-copy"></i> <span id="copyRefBtnText">Copy</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function copyRefLink(btn) {
    var input = document.getElementById('refLinkInput');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function() {
        var span = document.getElementById('copyRefBtnText');
        if (span) span.textContent = 'Copied!';
        btn.classList.add('btn-outline-dark');
        btn.classList.remove('btn-accent');
        setTimeout(function() {
            if (span) span.textContent = 'Copy';
            btn.classList.add('btn-accent');
            btn.classList.remove('btn-outline-dark');
        }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
