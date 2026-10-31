<?php
declare(strict_types=1);

$pageTitle = 'A/B Split Testing — Adsbooster.net';
$pageDescription = 'Experiment with multiple headlines, ad creatives, and audiences to maximize ROI.';
require_once __DIR__ . '/includes/app_header.php';
require_once __DIR__ . '/modules/ab_test.php';

$userId = current_user_id();
$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Invalid security token.</div>';
    } else {
        switch ($_POST['action']) {
            case 'create_test':
                $campaignId = (int)($_POST['campaign_id'] ?? 0);
                $variants = [];
                for ($i = 1; $i <= 4; $i++) {
                    $h = trim($_POST["variant_headline_{$i}"] ?? '');
                    if ($h !== '') {
                        $variants[] = [
                            'label'        => 'Variant ' . chr(64 + $i),
                            'headline'     => $h,
                            'primary_text' => trim($_POST["variant_text_{$i}"] ?? ''),
                            'split_pct'    => max(5, min(100, (int)($_POST["variant_split_{$i}"] ?? 25))),
                        ];
                    }
                }
                if ($campaignId <= 0) {
                    $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Please select a campaign.</div>';
                } elseif (count($variants) < 2) {
                    $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">At least 2 creative variants are required for a split test.</div>';
                } else {
                    ABTest::createTest($campaignId, $variants);
                    $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-check-circle" style="vertical-align:-2px;margin-right:6px"></i> A/B Split Test initialized successfully with ' . count($variants) . ' variants!</div>';
                }
                break;

            case 'declare_winner':
                $campaignId = (int)($_POST['campaign_id'] ?? 0);
                if ($campaignId > 0) {
                    ABTest::declareWinner($campaignId);
                    $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-trophy" style="vertical-align:-2px;margin-right:6px"></i> Winning variant declared! 100% of remaining budget reallocated automatically.</div>';
                }
                break;
        }
    }
}

// Fetch user's eligible campaigns
$campaigns = db()->fetchAll(
    "SELECT id, campaign_name, campaign_type, status, budget_amount FROM ad_campaigns WHERE user_id = ? AND status IN ('active','paused') ORDER BY id DESC",
    [$userId]
);

// Fetch existing tests
$tests = db()->fetchAll(
    "SELECT ac.id, ac.campaign_name, ac.campaign_type, ac.status as campaign_status, ac.budget_amount,
            COUNT(acv.id) as variant_count,
            SUM(acv.is_winner) as has_winner
     FROM ad_campaigns ac
     LEFT JOIN ad_campaign_variants acv ON acv.campaign_id = ac.id
     WHERE ac.user_id = ?
     GROUP BY ac.id
     HAVING variant_count > 0
     ORDER BY ac.id DESC",
    [$userId]
);

$platformBadges = [
    'meta'       => ['icon' => 'facebook-logo', 'color' => '#1877F2', 'name' => 'Meta / FB'],
    'instagram'  => ['icon' => 'instagram-logo', 'color' => '#E4405F', 'name' => 'Instagram'],
    'tiktok'     => ['icon' => 'tiktok-logo', 'color' => '#00f2fe', 'name' => 'TikTok'],
    'google_ads' => ['icon' => 'google-logo', 'color' => '#4285F4', 'name' => 'Google Ads'],
    'snapchat'   => ['icon' => 'ghost', 'color' => '#FFFC00', 'name' => 'Snapchat'],
    'combo_pack' => ['icon' => 'sparkle', 'color' => 'var(--accent)', 'name' => 'All-in-One Combo'],
];
?>

<div class="w-100">
    <?= $message ?>

    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">A/B Creative Split Testing</h1>
            <p class="page-subtitle">Pit copy variations and creative assets against each other. Automatically reallocate budget to the highest converting variant.</p>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#createTestModal" style="display:inline-flex;align-items:center;gap:8px;">
                <i class="ph ph-plus-circle" style="font-size:18px"></i> Create Split Test
            </button>
        </div>
    </div>

    <!-- Overview Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Active Split Tests</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(200,255,0,0.1);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                        <i class="ph ph-split-horizontal" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);"><?= count(array_filter($tests, fn($t) => empty($t['has_winner']))) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Multi-variant experiments running</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Completed Tests</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;color:var(--green);">
                        <i class="ph ph-trophy" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);"><?= count(array_filter($tests, fn($t) => !empty($t['has_winner']))) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Winners declared & budget scaled</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Avg. Conversion Lift</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(56,189,248,0.1);display:flex;align-items:center;justify-content:center;color:var(--blue);">
                        <i class="ph ph-trend-up" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);">+38.4%</div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Estimated CTR enhancement</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Budget Auto-Pilot</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(192,132,252,0.1);display:flex;align-items:center;justify-content:center;color:var(--purple);">
                        <i class="ph ph-lightning" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);">Active</div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Automated statistical winner detection</div>
            </div>
        </div>
    </div>

    <?php if (empty($tests)): ?>
        <div class="card-dark" style="padding:50px 30px;text-align:center;border:1px dashed var(--border2);margin-top:10px;">
            <div style="width:68px;height:68px;border-radius:50%;background:rgba(200,255,0,0.1);display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="ph ph-split-horizontal" style="font-size:32px;color:var(--accent);"></i>
            </div>
            <h3 style="color:var(--fg);font-size:20px;font-weight:700;margin-bottom:8px;">No A/B Split Tests Yet</h3>
            <p style="color:var(--fg2);max-width:480px;margin:0 auto 24px;font-size:14px;line-height:1.6;">
                Split testing allows you to launch up to 4 variations of headlines, copy, and creative formats simultaneously to discover which converts best.
            </p>
            <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#createTestModal">
                <i class="ph ph-plus-circle"></i> Create Your First Test
            </button>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($tests as $test):
                $pInfo = $platformBadges[$test['campaign_type']] ?? ['icon' => 'broadcast', 'color' => '#888', 'name' => ucfirst($test['campaign_type'])];
                $variants = db()->fetchAll(
                    "SELECT * FROM ad_campaign_variants WHERE campaign_id = ? ORDER BY variant_label ASC",
                    [$test['id']]
                );
            ?>
                <div class="col-xl-6 col-lg-12">
                    <div class="card-dark" style="padding:24px;height:100%;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;">
                        <?php if ($test['has_winner']): ?>
                            <div style="position:absolute;top:0;right:0;background:linear-gradient(135deg, transparent 50%, var(--green) 50%);width:45px;height:45px;"></div>
                        <?php endif; ?>

                        <div>
                            <!-- Header -->
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;">
                                <div>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                        <span style="font-size:11px;font-weight:700;color:<?= $pInfo['color'] ?>;background:rgba(255,255,255,0.06);padding:3px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
                                            <i class="ph ph-<?= $pInfo['icon'] ?>"></i> <?= e($pInfo['name']) ?>
                                        </span>
                                        <span style="font-size:12px;color:var(--fg3);">Campaign #<?= $test['id'] ?></span>
                                    </div>
                                    <h4 style="color:var(--fg);font-size:18px;font-weight:700;margin:0;"><?= e($test['campaign_name']) ?></h4>
                                </div>
                                <div>
                                    <?php if ($test['has_winner']): ?>
                                        <span class="badge-status badge-active" style="display:inline-flex;align-items:center;gap:5px;">
                                            <i class="ph ph-trophy"></i> Winner Decided
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status" style="background:rgba(200,255,0,0.15);color:var(--accent);display:inline-flex;align-items:center;gap:5px;">
                                            <span style="width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block;animation:pulse 1.5s infinite;"></span>
                                            Running Split
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Variants List -->
                            <div style="margin-top:18px;display:flex;flex-direction:column;gap:10px;">
                                <?php foreach ($variants as $v):
                                    $isWin = (bool)$v['is_winner'];
                                    $split = (int)($v['traffic_split_pct'] ?? 25);
                                ?>
                                    <div style="background:<?= $isWin ? 'rgba(16,185,129,0.08)' : 'var(--bg)' ?>;border:1px solid <?= $isWin ? 'rgba(16,185,129,0.4)' : 'var(--border)' ?>;border-radius:10px;padding:14px 16px;">
                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;background:<?= $isWin ? 'var(--green)' : 'var(--bg3)' ?>;color:<?= $isWin ? '#000' : 'var(--fg)' ?>;">
                                                    <?= e($v['variant_label']) ?>
                                                </span>
                                                <span style="font-weight:600;font-size:14px;color:var(--fg);">
                                                    <?= e($v['headline']) ?>
                                                </span>
                                            </div>
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <span style="font-size:12px;font-weight:600;color:var(--fg2);"><?= $split ?>% Traffic</span>
                                                <?php if ($isWin): ?>
                                                    <span style="font-size:11px;font-weight:800;color:#000;background:var(--accent);padding:2px 7px;border-radius:4px;display:inline-flex;align-items:center;gap:4px;">
                                                        <i class="ph ph-crown-simple"></i> WINNER
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($v['primary_text'])): ?>
                                            <div style="font-size:12px;color:var(--fg2);margin-bottom:8px;line-height:1.4;">
                                                <?= e($v['primary_text']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Progress Bar -->
                                        <div style="width:100%;height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden;">
                                            <div style="width:<?= $split ?>%;height:100%;background:<?= $isWin ? 'var(--green)' : 'var(--accent)' ?>;border-radius:3px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;">
                            <div style="font-size:12px;color:var(--fg3);">
                                <i class="ph ph-stack"></i> <?= $test['variant_count'] ?> Total Variants
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a href="<?= url('create-ad') ?>?campaign_id=<?= $test['id'] ?>" class="btn-outline-dark btn-sm">
                                    <i class="ph ph-gear"></i> Campaign
                                </a>
                                <?php if (!$test['has_winner']): ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="declare_winner">
                                        <input type="hidden" name="campaign_id" value="<?= $test['id'] ?>">
                                        <button type="submit" class="btn-accent btn-sm" onclick="return confirm('Declare current top performer as winner and route 100% budget to it?')">
                                            <i class="ph ph-trophy"></i> Declare Winner
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Test Modal (Fully Styled Dark Glassmorphism) -->
<div class="modal fade" id="createTestModal" tabindex="-1" aria-labelledby="createTestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:18px;box-shadow:0 25px 60px rgba(0,0,0,0.85);color:#f8fafc;overflow:hidden;">
            <form method="POST">
                <div class="modal-header" style="background:#090e18;border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(200,255,0,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                            <i class="ph ph-split-horizontal" style="font-size:22px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" id="createTestModalLabel" style="font-size:18px;font-weight:700;color:#fff;margin:0;">Create A/B Creative Split Test</h5>
                            <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">Compare up to 4 copy and creative combinations</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="padding:24px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_test">

                    <!-- Select Campaign -->
                    <div class="mb-4">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;margin-bottom:8px;">Target Ad Campaign <span style="color:var(--accent)">*</span></label>
                        <select name="campaign_id" class="form-select" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:10px;padding:12px 14px;" required>
                            <option value="">Select a live or paused campaign...</option>
                            <?php foreach ($campaigns as $c): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['campaign_name']) ?> — <?= ucfirst($c['campaign_type']) ?> ($<?= number_format((float)$c['budget_amount'], 2) ?> Budget)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size:12px;color:#64748b;margin-top:6px;">Select which campaign budget will be distributed between variants.</div>
                    </div>

                    <!-- Variants Section -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <label style="font-size:14px;font-weight:700;color:#fff;margin:0;">
                            Creative Variations <span style="font-size:12px;font-weight:400;color:#94a3b8;">(Min 2, Max 4)</span>
                        </label>
                        <span style="font-size:12px;color:var(--accent);font-weight:600;">
                            <i class="ph ph-lightning"></i> Equal 25% Auto-Allocation
                        </span>
                    </div>

                    <?php for ($i = 1; $i <= 4; $i++): 
                        $letter = chr(64 + $i);
                        $isReq = $i <= 2;
                    ?>
                        <div style="background:#111a2c;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:16px;margin-bottom:14px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span style="width:24px;height:24px;border-radius:6px;background:var(--accent);color:#000;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;">
                                        <?= $letter ?>
                                    </span>
                                    <span style="font-size:13px;font-weight:700;color:#fff;">Variant <?= $letter ?> <?= $isReq ? '<span style="color:#f43f5e">*</span>' : '<span style="font-size:11px;font-weight:400;color:#64748b">(Optional)</span>' ?></span>
                                </div>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span style="font-size:12px;color:#94a3b8;">Split:</span>
                                    <input type="number" name="variant_split_<?= $i ?>" class="form-control form-control-sm" style="width:70px;background:#1a253a;border:1px solid rgba(255,255,255,0.15);color:#c8ff00;font-weight:700;text-align:center;border-radius:6px;" value="25" min="5" max="100">
                                    <span style="font-size:12px;color:#c8ff00;font-weight:700;">%</span>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <input type="text" name="variant_headline_<?= $i ?>" class="form-control" style="background:#182338;border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;font-size:13px;" placeholder="Catchy Headline <?= $letter ?><?= $isReq ? ' (Required)' : '' ?>" <?= $isReq ? 'required' : '' ?>>
                                </div>
                                <div class="col-md-7">
                                    <input type="text" name="variant_text_<?= $i ?>" class="form-control" style="background:#182338;border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;font-size:13px;" placeholder="Body Copy / Value Proposition for Variant <?= $letter ?>">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="modal-footer" style="background:#090e18;border-top:1px solid rgba(255,255,255,0.08);padding:18px 24px;display:flex;justify-content:space-between;">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent" style="padding:10px 24px;">
                        <i class="ph ph-rocket-launch"></i> Launch Split Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
