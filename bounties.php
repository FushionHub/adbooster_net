<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/escrow_bounty_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to view Creator Bounties.');
    redirect(url('login'));
}

$pageTitle = 'Creator Escrow Bounties & UGC Marketplace — Adsbooster.net';
$user = current_user();
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

$bounties = db()->fetchAll("SELECT b.*, u.full_name as brand_name FROM creator_bounties b JOIN users u ON b.brand_user_id = u.id ORDER BY b.id DESC");

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-shield-check me-1"></i> 100% Escrow Protected
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-sparkle me-1"></i> Automated AI Video Quality Check
          </span>
        </div>
        <h2 class="h3 fw-bold mb-1">Creator Escrow Bounties & UGC Hub</h2>
        <p class="text-secondary mb-0">Brands deposit funds safely into escrow. Creators submit video drafts with instant AI compliance scans. Fast payouts upon approval.</p>
      </div>
      <a href="<?= url('create-bounty') ?>" class="btn btn-primary fw-bold">
        <i class="ph ph-plus-circle me-1"></i> Post Brand Bounty (Lock Escrow)
      </a>
    </div>

    <!-- Bounties Feed -->
    <div class="row g-4">
      <?php foreach ($bounties as $b): ?>
      <div class="col-lg-4 col-md-6">
        <div class="card border-0 bg-surface-card rounded-4 p-4 h-100 shadow-sm d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-primary-subtle text-primary text-uppercase small"><?= e(str_replace('_', ' ', $b['platform'])) ?></span>
              <span class="badge bg-success text-black fw-bold fs-6">
                $<?= number_format((float)$b['bounty_amount'], 2) ?> USD
              </span>
            </div>
            <h5 class="fw-bold text-white mb-2"><?= e($b['title']) ?></h5>
            <p class="text-secondary small mb-3"><?= e($b['description']) ?></p>

            <div class="p-2 rounded-2 bg-dark border border-secondary border-opacity-25 mb-3 small">
              <div class="d-flex justify-content-between text-secondary mb-1">
                <span>Format:</span>
                <strong class="text-white"><?= ucwords(str_replace('_', ' ', e($b['content_type']))) ?></strong>
              </div>
              <div class="d-flex justify-content-between text-secondary">
                <span>Escrow Status:</span>
                <strong class="text-success"><i class="ph ph-shield-check"></i> <?= strtoupper(e($b['escrow_status'])) ?></strong>
              </div>
            </div>
          </div>

          <div>
            <a href="<?= url('bounty-details', ['id' => $b['id']]) ?>" class="btn btn-outline-primary w-100 fw-bold">
              <i class="ph ph-eye me-1"></i> View Deliverables & Claim
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
