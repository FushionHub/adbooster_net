<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/escrow_bounty_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to post a Creator Bounty.');
    redirect(url('login'));
}

$pageTitle = 'Post Creator Escrow Bounty — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

// Handle Bounty Creation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('create-bounty'));
    }

    $bountyId = EscrowBountyEngine::createBounty($userId, $_POST);
    set_flash('success', "Bounty created and escrow deposit locked! Creators can now submit drafts.");
    redirect(url('bounty-details', ['id' => $bountyId]));
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4" style="max-width: 800px;">
    <!-- Header -->
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
        <i class="ph ph-shield-check me-1"></i> 100% Escrow Protection
      </span>
    </div>
    <h2 class="h3 fw-bold mb-1">Post a Creator Video Bounty</h2>
    <p class="text-secondary mb-4">Set your requirements, deposit the bounty into platform escrow, and receive AI-scanned UGC video drafts from verified creators.</p>

    <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label small text-secondary fw-semibold">Bounty Title</label>
          <input type="text" name="title" class="form-control" placeholder="e.g. 15s UGC Talking Head Review for Organic Skincare Brand" required>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small text-secondary fw-semibold">Target Platform</label>
            <select name="platform" class="form-select">
              <option value="tiktok">TikTok Video</option>
              <option value="instagram_reels">Instagram Reels</option>
              <option value="youtube_shorts">YouTube Shorts</option>
              <option value="all">Omnichannel (All Networks)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-secondary fw-semibold">Content Format</label>
            <select name="content_type" class="form-select">
              <option value="ugc_talking_head">Selfie Talking Head & Review</option>
              <option value="unboxing">Product Unboxing & First Impressions</option>
              <option value="tutorial">Software / Physical Tutorial</option>
              <option value="lifestyle_testimonial">Lifestyle Gym / Kitchen Demo</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label small text-secondary fw-semibold">Project Overview & Background</label>
          <textarea name="description" rows="3" class="form-control" placeholder="Describe your product, brand vibe, and what creator persona you are looking for..." required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label small text-secondary fw-semibold">Specific Deliverable Requirements</label>
          <textarea name="deliverables_req" rows="2" class="form-control" placeholder="e.g. 1080x1920 9:16 vertical video, 15-30 seconds, must include hook in first 3 seconds, natural lighting." required></textarea>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label small text-secondary fw-semibold">Bounty Amount ($ USD)</label>
            <div class="input-group">
              <span class="input-group-text bg-dark border-secondary border-opacity-25 text-success fw-bold">$</span>
              <input type="number" step="1.00" name="bounty_amount" class="form-control" placeholder="150.00" value="100.00" required>
            </div>
            <small class="text-secondary">Funds locked securely in escrow until you approve the draft.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-secondary fw-semibold">Platform Protection Fee</label>
            <input type="text" class="form-control bg-dark border-secondary border-opacity-25 text-secondary" value="10% (Creator payout after approval)" readonly>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary border-opacity-25">
          <a href="<?= url('bounties') ?>" class="btn btn-outline-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
            <i class="ph ph-lock-key me-1"></i> Lock Escrow & Post Bounty
          </button>
        </div>
      </form>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
