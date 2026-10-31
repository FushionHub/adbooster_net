<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/escrow_bounty_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to view bounty details.');
    redirect(url('login'));
}

$bountyId = (int)($_GET['id'] ?? 0);
$bounty = db()->fetch("SELECT b.*, u.full_name as brand_name FROM creator_bounties b JOIN users u ON b.brand_user_id = u.id WHERE b.id = ?", [$bountyId]);

if (!$bounty) {
    set_flash('error', 'Bounty not found.');
    redirect(url('bounties'));
}

$pageTitle = e($bounty['title']) . ' — Creator Bounty Workspace';
$user = current_user();
$userId = current_user_id();
$isBrandOwner = ((int)$bounty['brand_user_id'] === $userId);

// Handle Actions (Claim, Submit Draft, Approve & Release Escrow)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('bounty-details', ['id' => $bountyId]));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'claim_bounty') {
        EscrowBountyEngine::claimBounty($userId, $bountyId);
        set_flash('success', 'You have claimed this bounty! Please submit your video draft before the deadline.');
        redirect(url('bounty-details', ['id' => $bountyId]));
    }

    if ($action === 'submit_draft') {
        $videoUrl = trim($_POST['video_url'] ?? '');
        $notes = trim($_POST['submission_notes'] ?? '');
        $subRes = EscrowBountyEngine::submitDraft($userId, $bountyId, $videoUrl, $notes);
        set_flash('success', "Draft submitted! AI Quality & Compliance Score: {$subRes['score']}% (Passed).");
        redirect(url('bounty-details', ['id' => $bountyId]));
    }

    if ($action === 'approve_release' && $isBrandOwner) {
        $subId = (int)($_POST['submission_id'] ?? 0);
        try {
            $rel = EscrowBountyEngine::approveAndReleaseEscrow($userId, $bountyId, $subId);
            set_flash('success', "Escrow funds of \${$rel['bounty_amount']} released! Platform fee deducted: \${$rel['platform_fee']}. Creator received \${$rel['payout_to_creator']}.");
        } catch (Exception $e) {
            set_flash('error', $e->getMessage());
        }
        redirect(url('bounty-details', ['id' => $bountyId]));
    }
}

// Fetch submissions
$submissions = db()->fetchAll("SELECT s.*, u.full_name as creator_name FROM creator_bounty_submissions s JOIN users u ON s.creator_user_id = u.id WHERE s.bounty_id = ? ORDER BY s.id DESC", [$bountyId]);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <a href="<?= url('bounties') ?>" class="text-secondary small text-decoration-none">
            <i class="ph ph-arrow-left me-1"></i> Back to Bounties
          </a>
          <span class="badge bg-success-subtle text-success small">Escrow: <?= strtoupper(e($bounty['escrow_status'])) ?></span>
        </div>
        <h2 class="h3 fw-bold mb-1"><?= e($bounty['title']) ?></h2>
        <p class="text-secondary mb-0">Posted by <strong class="text-white"><?= e($bounty['brand_name']) ?></strong> &bull; <?= ucwords(str_replace('_', ' ', e($bounty['platform']))) ?></p>
      </div>
      <div class="text-end">
        <span class="text-secondary small d-block">Bounty Reward:</span>
        <h3 class="fw-bold text-success mb-0">$<?= number_format((float)$bounty['bounty_amount'], 2) ?> USD</h3>
      </div>
    </div>

    <div class="row g-4">
      <!-- Left Column: Bounty Brief & Submissions -->
      <div class="col-lg-8">
        <!-- Brief Card -->
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm mb-4">
          <h5 class="fw-bold text-white mb-3">Project Brief & Deliverable Requirements</h5>
          <p class="text-secondary mb-3"><?= nl2br(e($bounty['description'])) ?></p>

          <div class="p-3 rounded-3 bg-dark border border-secondary border-opacity-25">
            <h6 class="fw-bold text-primary small mb-2"><i class="ph ph-list-checks me-1"></i> Mandatory Requirements:</h6>
            <p class="text-white small mb-0"><?= nl2br(e($bounty['deliverables_req'])) ?></p>
          </div>
        </div>

        <!-- Submissions Feed -->
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm">
          <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
            <i class="ph ph-video-camera text-primary"></i> Submitted Creator Video Drafts
          </h5>

          <?php if (empty($submissions)): ?>
          <div class="text-center py-4 text-secondary small">
            <i class="ph ph-cloud-arrow-up fs-2 text-secondary mb-2 d-block"></i>
            No draft submissions uploaded yet.
          </div>
          <?php else: ?>
            <?php foreach ($submissions as $sub): 
              $report = json_decode($sub['ai_compliance_report'] ?? '{}', true);
            ?>
            <div class="p-3 rounded-3 bg-dark border border-secondary border-opacity-25 mb-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <h6 class="fw-bold text-white mb-0"><?= e($sub['creator_name']) ?></h6>
                  <small class="text-secondary"><?= date('M d, Y H:i', strtotime($sub['submitted_at'])) ?></small>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-success-subtle text-success fw-bold">
                    <i class="ph ph-sparkle me-1"></i> AI Compliance: <?= (int)$sub['ai_compliance_score'] ?>%
                  </span>
                  <span class="badge <?= $sub['review_status'] === 'approved' ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= strtoupper(e($sub['review_status'])) ?>
                  </span>
                </div>
              </div>

              <!-- AI Compliance Audit Details -->
              <div class="row g-2 mb-3 small">
                <?php foreach ($report as $rKey => $rVal): ?>
                <div class="col-sm-6">
                  <span class="text-secondary"><?= ucwords(str_replace('_', ' ', $rKey)) ?>:</span>
                  <strong class="text-info"><?= e($rVal) ?></strong>
                </div>
                <?php endforeach; ?>
              </div>

              <!-- Notes -->
              <?php if (!empty($sub['submission_notes'])): ?>
              <p class="text-secondary small mb-3"><strong>Notes:</strong> <?= e($sub['submission_notes']) ?></p>
              <?php endif; ?>

              <!-- Brand Approval Button & Cross-Feature Actions -->
              <?php if ($isBrandOwner): ?>
                <?php if ($sub['review_status'] !== 'approved'): ?>
                <form method="POST" class="d-flex gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve_release">
                  <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                    <i class="ph ph-check-circle me-1"></i> Approve Draft & Release $<?= number_format((float)$bounty['bounty_amount'], 2) ?> Escrow
                  </button>
                </form>
                <?php else: ?>
                <div class="d-flex gap-2 pt-2 border-top border-secondary border-opacity-25">
                  <a href="<?= url('create-ad') ?>" class="btn btn-sm btn-primary fw-bold">
                    <i class="ph ph-rocket-launch me-1"></i> Launch Approved Video as Live Ad
                  </a>
                  <a href="<?= url('post-creator') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="ph ph-paper-plane-tilt me-1"></i> Publish to Socials
                  </a>
                </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right Column: Claim or Submit Workspace -->
      <div class="col-lg-4">
        <!-- Action Card for Creator -->
        <?php if (!$isBrandOwner): ?>
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm mb-4">
          <h5 class="fw-bold text-white mb-3">Creator Workspace</h5>
          
          <?php if ($bounty['status'] === 'open'): ?>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="claim_bounty">
            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold">
              <i class="ph ph-hand-pointing me-1"></i> Claim This Bounty ($<?= number_format((float)$bounty['bounty_amount'], 2) ?>)
            </button>
          </form>
          <?php else: ?>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_draft">
            <div class="mb-3">
              <label class="form-label small text-secondary">Video URL (or Cloud Link)</label>
              <input type="url" name="video_url" class="form-control" placeholder="https://..." value="https://adsbooster.net/uploads/draft_demo.mp4" required>
            </div>
            <div class="mb-3">
              <label class="form-label small text-secondary">Submission Notes</label>
              <textarea name="submission_notes" rows="2" class="form-control" placeholder="Explain your hook and delivery..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
              <i class="ph ph-paper-plane-tilt me-1"></i> Run AI Compliance Scan & Submit
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Escrow Protection Badge Card -->
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="ph ph-shield-check text-success fs-3"></i>
            <h6 class="fw-bold text-white mb-0">Platform Escrow Guarantee</h6>
          </div>
          <p class="text-secondary small mb-0">
            The full bounty amount is locked in Adsbooster escrow. Creators are guaranteed payment upon delivering compliant work, and brands only release funds when 100% satisfied.
          </p>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
