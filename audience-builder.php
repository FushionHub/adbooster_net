<?php
declare(strict_types=1);

$pageTitle = 'Audience Builder & Lookalikes — Adsbooster.net';
$pageDescription = 'Build and manage custom audience segments, demographic cohorts, and AI lookalike audiences.';
require_once __DIR__ . '/includes/app_header.php';
require_once __DIR__ . '/modules/audience_builder.php';

$userId = current_user_id();
$message = '';
$suggestionData = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Invalid security token.</div>';
    } else {
        switch ($_POST['action']) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                if ($name === '') {
                    $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Segment name is required.</div>';
                } else {
                    $criteria = [
                        'age_min'   => max(13, (int)($_POST['age_min'] ?? 18)),
                        'age_max'   => min(100, (int)($_POST['age_max'] ?? 65)),
                        'geo'       => trim($_POST['geo'] ?? 'Global'),
                        'interests' => trim($_POST['interests'] ?? 'General Audience'),
                        'device'    => trim($_POST['device'] ?? 'all'),
                        'gender'    => trim($_POST['gender'] ?? 'all'),
                    ];
                    AudienceBuilder::createSegment($userId, $name, $criteria);
                    $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-check-circle" style="vertical-align:-2px;margin-right:6px"></i> Audience segment "<b>' . htmlspecialchars($name) . '</b>" created successfully!</div>';
                }
                break;

            case 'delete':
                $segmentId = (int)($_POST['segment_id'] ?? 0);
                if ($segmentId > 0) {
                    AudienceBuilder::deleteSegment($segmentId);
                    $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-trash" style="vertical-align:-2px;margin-right:6px"></i> Segment deleted.</div>';
                }
                break;

            case 'suggest':
                $campId = (int)($_POST['campaign_id'] ?? 0);
                if ($campId > 0) {
                    $suggestionData = AudienceBuilder::suggestLookalike($userId, $campId);
                    $message = '<div class="alert alert-info" style="background:rgba(56,189,248,0.15);border:1px solid rgba(56,189,248,0.3);color:#7dd3fc;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-magic-wand" style="vertical-align:-2px;margin-right:6px"></i> <b>AI Lookalike Insight Generated:</b> Recommendation expanded age bracket to ' .
                        $suggestionData['age_min'] . '–' . $suggestionData['age_max'] . ' and broadened keywords to: <i>' . htmlspecialchars($suggestionData['interests']) . '</i></div>';
                }
                break;
        }
    }
}

// Fetch segments
$segments = db()->fetchAll(
    "SELECT * FROM audience_segments WHERE user_id = ? ORDER BY created_at DESC",
    [$userId]
);

// Fetch user's campaigns for lookalike generator
$campaigns = db()->fetchAll(
    "SELECT id, campaign_name, campaign_type, budget_amount FROM ad_campaigns WHERE user_id = ? ORDER BY id DESC",
    [$userId]
);
?>

<div class="w-100">
    <?= $message ?>

    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Smart Audience Builder</h1>
            <p class="page-subtitle">Build targeted buyer cohorts, custom demographics, and AI lookalikes to supercharge your multi-network ads.</p>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#createSegmentModal" style="display:inline-flex;align-items:center;gap:8px;">
                <i class="ph ph-plus-circle" style="font-size:18px"></i> Create Audience Segment
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Saved Cohorts</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(200,255,0,0.1);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                        <i class="ph ph-users" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);"><?= count($segments) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Custom targeting presets</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Est. Combined Reach</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;color:var(--green);">
                        <i class="ph ph-globe" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);"><?= count($segments) > 0 ? (number_format(count($segments) * 420)) . 'K+' : '0' ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Across Meta, TikTok, Google & Snap</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Lookalike Engine</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(56,189,248,0.1);display:flex;align-items:center;justify-content:center;color:var(--blue);">
                        <i class="ph ph-sparkle" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);">Ready</div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Heuristic similarity algorithm</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Active Campaigns</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(192,132,252,0.1);display:flex;align-items:center;justify-content:center;color:var(--purple);">
                        <i class="ph ph-broadcast" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);"><?= count($campaigns) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Eligible for lookalike expansion</div>
            </div>
        </div>
    </div>

    <!-- Smart Lookalike Suggestion Box -->
    <div class="card-dark mb-4" style="padding:24px;border:1px solid rgba(200,255,0,0.2);background:linear-gradient(135deg, rgba(14,22,38,0.95) 0%, rgba(20,33,56,0.85) 100%);">
        <div class="row align-items-center g-3">
            <div class="col-lg-6">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                    <div style="width:38px;height:38px;border-radius:10px;background:rgba(200,255,0,0.15);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                        <i class="ph ph-magic-wand" style="font-size:22px;"></i>
                    </div>
                    <div>
                        <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">AI Lookalike & Expansion Analyzer</h4>
                        <p style="color:var(--fg2);font-size:13px;margin:2px 0 0;">Select an existing campaign to extract winning demographics and generate high-yield lookalikes.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:lg-end;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="suggest">
                    <select name="campaign_id" class="form-select" style="max-width:320px;background:#141f33;border:1px solid rgba(255,255,255,0.15);color:#fff;border-radius:10px;padding:10px 14px;" required>
                        <option value="">Choose a campaign to analyze...</option>
                        <?php foreach ($campaigns as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['campaign_name']) ?> (<?= ucfirst($c['campaign_type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-accent" style="padding:10px 20px;display:inline-flex;align-items:center;gap:8px;">
                        <i class="ph ph-sparkle"></i> Suggest Lookalike
                    </button>
                </form>
            </div>
        </div>

        <?php if ($suggestionData): ?>
            <div style="margin-top:20px;padding-top:18px;border-top:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
                <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                    <div style="font-size:12px;color:var(--fg3);">Recommended Profile:</div>
                    <span class="badge-status badge-active">Age: <?= $suggestionData['age_min'] ?>–<?= $suggestionData['age_max'] ?></span>
                    <span class="badge-status badge-info">Interests: <?= htmlspecialchars($suggestionData['interests']) ?></span>
                    <span class="badge-status badge-pending">Geo: <?= htmlspecialchars($suggestionData['geo'] ?? 'Global') ?></span>
                </div>
                <div>
                    <button type="button" class="btn-outline-dark btn-sm" onclick="populateLookalike(<?= htmlspecialchars(json_encode($suggestionData)) ?>)" data-bs-toggle="modal" data-bs-target="#createSegmentModal">
                        <i class="ph ph-floppy-disk"></i> Save as New Segment
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Segments Grid -->
    <?php if (empty($segments)): ?>
        <div class="card-dark" style="padding:50px 30px;text-align:center;border:1px dashed var(--border2);">
            <div style="width:68px;height:68px;border-radius:50%;background:rgba(200,255,0,0.1);display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="ph ph-users-three" style="font-size:32px;color:var(--accent);"></i>
            </div>
            <h3 style="color:var(--fg);font-size:20px;font-weight:700;margin-bottom:8px;">No Audience Segments Created</h3>
            <p style="color:var(--fg2);max-width:500px;margin:0 auto 24px;font-size:14px;line-height:1.6;">
                Save targeted cohorts by age, location, interests, and device types to instantly pre-fill targeting across Meta, Google, TikTok, and Snapchat ad campaigns.
            </p>
            <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#createSegmentModal">
                <i class="ph ph-plus-circle"></i> Create Your First Audience Segment
            </button>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($segments as $seg):
                $criteria = json_decode($seg['criteria_json'] ?? '{}', true) ?: [];
            ?>
                <div class="col-xl-4 col-md-6">
                    <div class="card-dark" style="padding:22px;height:100%;display:flex;flex-direction:column;justify-content:space-between;position:relative;">
                        <div>
                            <!-- Segment Header -->
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
                                <div>
                                    <h4 style="color:var(--fg);font-size:17px;font-weight:700;margin:0;"><?= htmlspecialchars($seg['name']) ?></h4>
                                    <span style="font-size:11px;color:var(--fg3);">Created <?= date('M d, Y', strtotime($seg['created_at'])) ?></span>
                                </div>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this audience segment?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="segment_id" value="<?= $seg['id'] ?>">
                                    <button type="submit" class="btn-outline-dark btn-sm" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;color:#f43f5e;border-color:rgba(244,63,94,0.3);">
                                        <i class="ph ph-trash" style="font-size:14px;"></i>
                                    </button>
                                </form>
                            </div>

                            <!-- Criteria Badges -->
                            <div style="display:flex;flex-direction:column;gap:8px;margin-top:14px;">
                                <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
                                    <span style="width:80px;color:var(--fg3);font-size:12px;">Age Group:</span>
                                    <span class="badge-status" style="background:rgba(255,255,255,0.06);color:var(--fg);font-size:12px;">
                                        <i class="ph ph-user"></i> <?= $criteria['age_min'] ?? 18 ?> – <?= $criteria['age_max'] ?? 65 ?> yrs
                                    </span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
                                    <span style="width:80px;color:var(--fg3);font-size:12px;">Geography:</span>
                                    <span class="badge-status" style="background:rgba(56,189,248,0.1);color:var(--blue);font-size:12px;">
                                        <i class="ph ph-map-pin"></i> <?= htmlspecialchars($criteria['geo'] ?? 'Global') ?>
                                    </span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
                                    <span style="width:80px;color:var(--fg3);font-size:12px;">Devices:</span>
                                    <span class="badge-status" style="background:rgba(192,132,252,0.1);color:var(--purple);font-size:12px;">
                                        <i class="ph ph-device-mobile"></i> <?= ucfirst($criteria['device'] ?? 'All') ?>
                                    </span>
                                </div>
                                <div style="margin-top:6px;">
                                    <span style="color:var(--fg3);font-size:12px;display:block;margin-bottom:4px;">Interests & Keywords:</span>
                                    <div style="font-size:12px;color:var(--fg);background:var(--bg);padding:8px 12px;border-radius:8px;border:1px solid var(--border);line-height:1.4;">
                                        <?= htmlspecialchars($criteria['interests'] ?? 'General') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CTA -->
                        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                            <a href="<?= url('create-ad') ?>?segment_id=<?= $seg['id'] ?>" class="btn-accent" style="width:100%;text-align:center;display:block;padding:9px 16px;font-size:13px;">
                                <i class="ph ph-rocket-launch"></i> Launch Ad With This Audience
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Segment Modal (Dark Glassmorphic) -->
<div class="modal fade" id="createSegmentModal" tabindex="-1" aria-labelledby="createSegmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:18px;box-shadow:0 25px 60px rgba(0,0,0,0.85);color:#f8fafc;overflow:hidden;">
            <form method="POST">
                <div class="modal-header" style="background:#090e18;border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(200,255,0,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                            <i class="ph ph-users-three" style="font-size:22px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" id="createSegmentModalLabel" style="font-size:18px;font-weight:700;color:#fff;margin:0;">Create Audience Segment</h5>
                            <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">Define reusable demographic cohorts for ads</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="padding:24px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Segment Name <span style="color:var(--accent)">*</span></label>
                        <input type="text" id="segName" name="name" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;" placeholder="e.g., Tech Professionals US 25-45" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Min Age</label>
                            <input type="number" id="segAgeMin" name="age_min" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;" value="18" min="13" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Max Age</label>
                            <input type="number" id="segAgeMax" name="age_max" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;" value="65" min="13" max="100">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Target Locations / Geography</label>
                        <input type="text" id="segGeo" name="geo" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;" placeholder="e.g., United States, United Kingdom, Canada">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Interests & Keywords</label>
                        <input type="text" id="segInterests" name="interests" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;" placeholder="e.g., e-commerce, saas, digital marketing, startups">
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Device Targeting</label>
                            <select id="segDevice" name="device" class="form-select" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;">
                                <option value="all">All Devices</option>
                                <option value="mobile">Mobile Only</option>
                                <option value="desktop">Desktop Only</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Gender</label>
                            <select id="segGender" name="gender" class="form-select" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;">
                                <option value="all">All Genders</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background:#090e18;border-top:1px solid rgba(255,255,255,0.08);padding:18px 24px;display:flex;justify-content:space-between;">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent" style="padding:10px 24px;">
                        <i class="ph ph-check"></i> Save Segment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function populateLookalike(data) {
    if (!data) return;
    document.getElementById('segName').value = 'AI Lookalike - ' + (data.interests || 'Broadened');
    document.getElementById('segAgeMin').value = data.age_min || 18;
    document.getElementById('segAgeMax').value = data.age_max || 65;
    document.getElementById('segInterests').value = data.interests || '';
    if (data.geo) document.getElementById('segGeo').value = data.geo;
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
