<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/escrow_bounty_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to view Creator Bounties.');
    redirect(url('login'));
}

$pageTitle = 'Creator Escrow Bounties & UGC Marketplace — Adsbooster.net';
$user   = current_user();
$userId = current_user_id();

// Seed initial bounties if empty
$bountiesCount = db()->count('creator_bounties');
if ($bountiesCount === 0) {
    $initialBounties = [
        [
            'title' => '15s UGC Talking Head Review for Hydrating Skincare Brand',
            'desc' => 'Looking for a female creator (20-35) to test our glow serum and record an authentic selfie-style talking head review.',
            'platform' => 'tiktok',
            'content_type' => 'ugc_talking_head',
            'deliverables' => '1x 9:16 vertical video, 1080p, clear audio, with hook & CTA.',
            'amount' => 120.00
        ],
        [
            'title' => 'SaaS Productivity App Unboxing & Workflow Demo',
            'desc' => 'Tech creator needed to record a screen-share and face-cam walkthrough of our AI automation tool.',
            'platform' => 'instagram_reels',
            'content_type' => 'tutorial',
            'deliverables' => '1x Reel (30s) highlighting 3 core features.',
            'amount' => 200.00
        ],
        [
            'title' => 'Athletic Compression Gym Wear Try-On Haul',
            'desc' => 'Fitness influencer needed to showcase workout durability and squat-proof fit.',
            'platform' => 'youtube_shorts',
            'content_type' => 'lifestyle_testimonial',
            'deliverables' => '1x Short with trending audio beat.',
            'amount' => 150.00
        ]
    ];

    foreach ($initialBounties as $ib) {
        EscrowBountyEngine::createBounty($userId, [
            'title' => $ib['title'],
            'description' => $ib['desc'],
            'platform' => $ib['platform'],
            'content_type' => $ib['content_type'],
            'deliverables_req' => $deliverables = $ib['deliverables'],
            'bounty_amount' => $ib['amount']
        ]);
    }
}

$bounties = db()->fetchAll(
    "SELECT b.*, u.full_name AS brand_name, u.avatar AS brand_avatar,
            (SELECT COUNT(*) FROM creator_bounty_submissions s WHERE s.bounty_id = b.id) AS submission_count
     FROM creator_bounties b
     JOIN users u ON b.brand_user_id = u.id
     ORDER BY b.id DESC"
);

// ── Filters ──────────────────────────────────────────────────────────────
$filterPlatform = trim((string)($_GET['platform'] ?? ''));
$filterStatus   = trim((string)($_GET['status'] ?? ''));
$filterSearch   = trim((string)($_GET['q'] ?? ''));

$filtered = $bounties;
if ($filterPlatform !== '' && $filterPlatform !== 'all') {
    $filtered = array_values(array_filter($filtered, static fn($b) => (string)$b['platform'] === $filterPlatform));
}
if ($filterStatus !== '' && $filterStatus !== 'all') {
    $filtered = array_values(array_filter($filtered, static fn($b) => (string)$b['status'] === $filterStatus));
}
if ($filterSearch !== '') {
    $needle = mb_strtolower($filterSearch);
    $filtered = array_values(array_filter($filtered, static function ($b) use ($needle) {
        return str_contains(mb_strtolower((string)$b['title']), $needle)
            || str_contains(mb_strtolower((string)$b['description']), $needle);
    }));
}

// ── Stats ────────────────────────────────────────────────────────────────
$openBounties   = count(array_filter($bounties, static fn($b) => (string)$b['status'] === 'open'));
$escrowHeld     = array_sum(array_map(
    static fn($b) => (float)($b['status'] === 'released_to_creator' ? 0 : $b['total_escrow_held']),
    $bounties
));
$totalPayout    = array_sum(array_map(
    static fn($b) => (float)($b['status'] === 'released_to_creator' ? $b['total_escrow_held'] : 0),
    $bounties
));
$totalSubmissions = array_sum(array_map(static fn($b) => (int)$b['submission_count'], $bounties));

/**
 * Platform metadata for the badge/icon.
 */
function bounty_platform_meta(string $platform): array
{
    return match ($platform) {
        'tiktok'          => ['label' => 'TikTok',            'icon' => 'ph-tiktok-logo',      'colour' => '#25f4ee'],
        'instagram_reels' => ['label' => 'Instagram Reels',  'icon' => 'ph-instagram-logo',   'colour' => '#e1306c'],
        'youtube_shorts'  => ['label' => 'YouTube Shorts',   'icon' => 'ph-youtube-logo',     'colour' => '#ff0000'],
        default           => ['label' => 'All networks',     'icon' => 'ph-globe-hemisphere-west', 'colour' => '#c8ff00'],
    };
}

/**
 * Escrow status badge styling.
 */
function bounty_escrow_badge(string $status): array
{
    return match ($status) {
        'funded'              => ['cls' => 'active',   'icon' => 'ph-shield-check',      'label' => 'Escrow funded'],
        'released_to_creator' => ['cls' => 'resolved', 'icon' => 'ph-check-circle',      'label' => 'Paid out'],
        'refunded_to_brand'   => ['cls' => 'failed',   'icon' => 'ph-arrow-u-up-left',   'label' => 'Refunded'],
        default               => ['cls' => 'pending',  'icon' => 'ph-hourglass',         'label' => ucfirst($status)],
    };
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">

    <!-- ── Header ────────────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
            <i class="ph ph-shield-check me-1"></i> 100% Escrow Protected
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill">
            <i class="ph ph-sparkle me-1"></i> AI Video Quality Check
          </span>
        </div>
        <h2 class="h3 fw-bold mb-1 text-white">Creator Escrow Bounties & UGC Hub</h2>
        <p class="text-secondary mb-0" style="max-width:640px;">
          Brands deposit funds safely into escrow. Creators submit video drafts with instant AI compliance scans.
          Fast payouts on approval.
        </p>
      </div>
      <a href="<?= url('create-bounty') ?>" class="btn btn-main fw-bold rounded-pill px-4">
        <i class="ph ph-plus-circle me-1"></i> Post Brand Bounty
      </a>
    </div>

    <!-- ── Stats ─────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <?php
      $statCards = [
          ['label' => 'Open Bounties',   'value' => number_format($openBounties),   'icon' => 'ph-megaphone',        'tone' => 'var(--accent)'],
          ['label' => 'Held In Escrow',  'value' => price_fmt($escrowHeld), 'icon' => 'ph-shield-check', 'tone' => 'var(--green)'],
          ['label' => 'Paid To Creators','value' => price_fmt($totalPayout), 'icon' => 'ph-money',         'tone' => 'var(--blue)'],
          ['label' => 'Submissions',     'value' => number_format($totalSubmissions), 'icon' => 'ph-paper-plane-tilt','tone' => 'var(--orange)'],
      ];
      foreach ($statCards as $sc): ?>
        <div class="col-6 col-lg-3">
          <div class="card-dark h-100" style="padding:18px;">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon" style="background:rgba(200,255,0,.1);">
                <i class="ph <?= $sc['icon'] ?>" style="font-size:20px;color:<?= $sc['tone'] ?>;"></i>
              </div>
              <div style="min-width:0;">
                <p class="stat-label mb-1"><?= e($sc['label']) ?></p>
                <h3 class="stat-value mb-0" style="font-size:1.5rem;"><?= e($sc['value']) ?></h3>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Filters ───────────────────────────────────────────────────── -->
    <div class="card-dark mb-4" style="padding:16px 18px;">
      <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
        <div class="position-relative flex-grow-1" style="min-width:220px;">
          <i class="ph ph-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--fg3);"></i>
          <input type="search" name="q" value="<?= e($filterSearch) ?>" class="form-control ps-5"
                 placeholder="Search bounties by title or brief…" style="padding-left:38px;">
        </div>

        <select name="platform" class="form-select" style="max-width:190px;" onchange="this.form.submit()">
          <?php
          $platforms = ['all' => 'All platforms', 'tiktok' => 'TikTok', 'instagram_reels' => 'Instagram Reels', 'youtube_shorts' => 'YouTube Shorts'];
          foreach ($platforms as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $filterPlatform === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="status" class="form-select" style="max-width:170px;" onchange="this.form.submit()">
          <?php
          $statuses = ['all' => 'Any status', 'open' => 'Open', 'in_progress' => 'In progress', 'draft_submitted' => 'Draft submitted', 'completed' => 'Completed'];
          foreach ($statuses as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-main btn-sm"><i class="ph ph-funnel me-1"></i> Filter</button>
        <?php if ($filterSearch !== '' || $filterPlatform !== 'all' || $filterStatus !== 'all'): ?>
          <a href="<?= url('bounties') ?>" class="btn btn-outline-dark btn-sm">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- ── Feed ──────────────────────────────────────────────────────── -->
    <?php if (empty($filtered)): ?>
      <div class="card-dark text-center" style="padding:56px 24px;">
        <div class="stat-icon mx-auto mb-3" style="background:rgba(200,255,0,.08);width:56px;height:56px;">
          <i class="ph ph-magnifying-glass" style="font-size:26px;color:var(--accent);"></i>
        </div>
        <h3 class="text-white fw-bold mb-2" style="font-size:1.15rem;">No bounties match your filters</h3>
        <p class="text-secondary mb-4" style="font-size:13px;">
          Try a different platform, clear the search, or post the first bounty for this brief.
        </p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
          <a href="<?= url('bounties') ?>" class="btn btn-outline-dark btn-sm">Clear filters</a>
          <a href="<?= url('create-bounty') ?>" class="btn btn-main btn-sm"><i class="ph ph-plus-circle me-1"></i> Post a bounty</a>
        </div>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($filtered as $b):
            $pm = bounty_platform_meta((string)$b['platform']);
            $eb = bounty_escrow_badge((string)$b['escrow_status']);
            $deadline = $b['submission_deadline'] ? strtotime((string)$b['submission_deadline']) : null;
            $daysLeft = $deadline ? (int)ceil(($deadline - time()) / 86400) : null;
        ?>
          <div class="col-lg-4 col-md-6">
            <div class="card-dark h-100 d-flex flex-column" style="padding:22px;position:relative;overflow:hidden;">
              <div style="position:absolute;inset:0 auto 0 0;width:3px;background:<?= $pm['colour'] ?>;"></div>

              <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                <span class="badge bg-dark border border-secondary border-opacity-25 px-3 py-1 rounded-pill d-inline-flex align-items-center gap-1"
                      style="font-size:11px;font-weight:700;color:<?= $pm['colour'] ?>;">
                  <i class="ph <?= $pm['icon'] ?>"></i> <?= e($pm['label']) ?>
                </span>
                <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-30 px-3 py-1 rounded-pill fw-bold"
                      style="font-size:13px;">
                  <?= price_fmt((float)$b['bounty_amount']) ?>
                </span>
              </div>

              <h5 class="fw-bold text-white mb-2" style="font-size:16px;line-height:1.4;"><?= e($b['title']) ?></h5>
              <p class="text-secondary small mb-3" style="line-height:1.6;"><?= e(mb_strimwidth((string)$b['description'], 0, 180, '…')) ?></p>

              <div class="p-3 rounded-3 bg-dark border border-secondary border-opacity-25 mb-3 small">
                <div class="d-flex justify-content-between text-secondary mb-2">
                  <span>Format:</span>
                  <strong class="text-white"><?= e(ucwords(str_replace('_', ' ', (string)$b['content_type']))) ?></strong>
                </div>
                <div class="d-flex justify-content-between text-secondary mb-2">
                  <span>Brand:</span>
                  <strong class="text-white"><?= e((string)$b['brand_name']) ?></strong>
                </div>
                <div class="d-flex justify-content-between text-secondary mb-2">
                  <span>Escrow:</span>
                  <strong class="text-<?= $eb['cls'] === 'active' ? 'success' : ($eb['cls'] === 'failed' ? 'danger' : 'muted') ?>">
                    <i class="ph <?= $eb['icon'] ?>"></i> <?= e($eb['label']) ?>
                  </strong>
                </div>
                <?php if ($daysLeft !== null): ?>
                  <div class="d-flex justify-content-between text-secondary">
                    <span>Deadline:</span>
                    <strong class="<?= $daysLeft <= 3 ? 'text-danger' : 'text-white' ?>">
                      <?= e(date('M d, Y', $deadline)) ?><?= $daysLeft >= 0 ? ' (' . $daysLeft . 'd left)' : ' (closed)' ?>
                    </strong>
                  </div>
                <?php endif; ?>
              </div>

              <div class="d-flex gap-2 mb-3 flex-wrap">
                <span class="badge bg-dark border border-secondary border-opacity-25 px-2 py-1 rounded-pill" style="font-size:10.5px;">
                  <?= e(ucwords(str_replace('_', ' ', (string)$b['status']))) ?>
                </span>
                <span class="badge bg-dark border border-secondary border-opacity-25 px-2 py-1 rounded-pill" style="font-size:10.5px;">
                  <i class="ph ph-paper-plane-tilt me-1"></i><?= (int)$b['submission_count'] ?> submissions
                </span>
                <span class="badge bg-dark border border-secondary border-opacity-25 px-2 py-1 rounded-pill" style="font-size:10.5px;">
                  <i class="ph ph-percent me-1"></i><?= number_format((float)$b['platform_fee_pct'], 0) ?>% fee
                </span>
              </div>

              <a href="<?= url('bounty-details', ['id' => $b['id']]) ?>"
                 class="btn btn-outline-primary w-100 fw-bold mt-auto">
                <i class="ph ph-eye me-1"></i> View Deliverables & Claim
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
