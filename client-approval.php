<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/agency_engine.php';

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    die("Access token missing or invalid.");
}

$client = db()->fetch("SELECT * FROM agency_client_accounts WHERE access_token = ?", [$token]);
if (!$client) {
    die("Invalid or expired client portal access token.");
}

$agencyId = (int)$client['agency_user_id'];
$portal = AgencyEngine::getAgencyPortal($agencyId);
$approvals = db()->fetchAll("SELECT * FROM agency_client_approvals WHERE client_account_id = ? ORDER BY created_at DESC", [(int)$client['id']]);

// Handle Client Action (Approve / Request Revision)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approval_id']) && csrf_verify()) {
    $approvalId = (int)$_POST['approval_id'];
    $clientAction = $_POST['client_action'] ?? 'approved';
    $feedback = trim($_POST['client_feedback'] ?? '');

    db()->update('agency_client_approvals', [
        'approval_status' => $clientAction,
        'client_feedback' => $feedback,
        'approved_at' => $clientAction === 'approved' ? date('Y-m-d H:i:s') : null
    ], 'id = ? AND client_account_id = ?', [$approvalId, (int)$client['id']]);

    set_flash('success', $clientAction === 'approved' ? 'Campaign draft approved successfully! Agency notified.' : 'Revision feedback submitted to agency.');
    redirect(url('client-approval', ['token' => $token]));
}

$primaryColor = $portal['brand_primary_color'] ?? '#c8ff00';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($portal['portal_name']) ?> — Client Review Portal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <style>
    body { background: #080c14; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .bg-surface-card { background: #0d1524; border: 1px solid rgba(255,255,255,0.08); }
    .btn-brand { background: <?= e($primaryColor) ?>; color: #000; font-weight: 700; }
    .btn-brand:hover { background: <?= e($primaryColor) ?>; opacity: 0.9; }
  </style>
</head>
<body>

<div class="container py-5" style="max-width: 900px;">
  <!-- Agency Whitelabel Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-25 gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="p-2 rounded-3 bg-dark border border-secondary border-opacity-25">
        <i class="ph ph-buildings fs-3" style="color: <?= e($primaryColor) ?>;"></i>
      </div>
      <div>
        <h4 class="fw-bold mb-0 text-white"><?= e($portal['portal_name']) ?></h4>
        <small class="text-secondary">Client Portal for <strong class="text-white"><?= e($client['client_name']) ?> (<?= e($client['client_company']) ?>)</strong></small>
      </div>
    </div>
    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold">
      <i class="ph ph-lock-key-open me-1"></i> Secure Session Active
    </span>
  </div>

  <!-- Flash Notification -->
  <?php if ($msg = get_flash('success')): ?>
  <div class="alert alert-success bg-success bg-opacity-20 border-success text-white rounded-3 mb-4">
    <i class="ph ph-check-circle me-1"></i> <?= e($msg) ?>
  </div>
  <?php endif; ?>

  <!-- Welcome Banner -->
  <div class="card bg-surface-card rounded-4 p-4 mb-4">
    <h5 class="fw-bold mb-1" style="color: <?= e($primaryColor) ?>;"><?= e($portal['welcome_headline']) ?></h5>
    <p class="text-secondary small mb-0">Please review the creative drafts and campaign schedules prepared by your marketing team below. You can approve in 1 click or request specific revisions.</p>
  </div>

  <!-- Approvals List -->
  <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
    <i class="ph ph-files" style="color: <?= e($primaryColor) ?>;"></i> Pending Campaign & Creative Deliverables
  </h5>

  <?php if (empty($approvals)): ?>
  <!-- Sample Demo Approval for Fresh Client Link -->
  <div class="card bg-surface-card rounded-4 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <span class="badge bg-primary-subtle text-primary mb-2">Omnichannel Ad Campaign & UGC Package</span>
        <h5 class="fw-bold text-white mb-1">Q4 High-Growth Scaling Campaign (Meta + TikTok + Google)</h5>
        <small class="text-secondary">Budget: $2,500.00 / month &bull; Target ROAS: 3.5x</small>
      </div>
      <span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill fw-bold">Awaiting Your Review</span>
    </div>

    <div class="p-3 rounded-3 bg-dark border border-secondary border-opacity-25 mb-4">
      <h6 class="fw-bold small text-white mb-2">Campaign Strategy & Assets:</h6>
      <ul class="text-secondary small mb-0 ps-3">
        <li>3x Photorealistic AI UGC Video Creatives (9:16 Vertical format)</li>
        <li>14x Scheduled Multi-Network Organic Posts</li>
        <li>Autonomous 24/7 Anti-Burn CPA Protection armed at $25.00</li>
      </ul>
    </div>

    <!-- Client Approval Buttons -->
    <div class="d-flex flex-wrap gap-2">
      <button class="btn btn-brand px-4 py-2" onclick="alert('Thank you! Deliverable marked as 100% Approved.');">
        <i class="ph ph-check-circle me-1"></i> 1-Click Approve & Launch
      </button>
      <button class="btn btn-outline-secondary px-4 py-2" onclick="prompt('Please provide your revision notes for the agency:'); alert('Revision feedback dispatched to agency team.');">
        <i class="ph ph-pencil-simple me-1"></i> Request Revision
      </button>
    </div>
  </div>
  <?php else: ?>
    <?php foreach ($approvals as $ap): ?>
    <div class="card bg-surface-card rounded-4 p-4 mb-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <span class="badge bg-info-subtle text-info mb-1"><?= ucwords(str_replace('_', ' ', e($ap['item_type']))) ?></span>
          <h5 class="fw-bold text-white mb-0"><?= e($ap['title']) ?></h5>
        </div>
        <span class="badge <?= $ap['approval_status'] === 'approved' ? 'bg-success' : 'bg-warning text-dark' ?>">
          <?= strtoupper(e($ap['approval_status'])) ?>
        </span>
      </div>
      <?php if ($ap['approval_status'] === 'pending'): ?>
      <form method="POST" class="mt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="approval_id" value="<?= $ap['id'] ?>">
        <div class="mb-3">
          <textarea name="client_feedback" rows="2" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="Optional notes or feedback for your agency..."></textarea>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" name="client_action" value="approved" class="btn btn-brand">
            <i class="ph ph-check-circle me-1"></i> Approve Deliverable
          </button>
          <button type="submit" name="client_action" value="rejected_revision" class="btn btn-outline-secondary">
            Request Changes
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <footer class="mt-5 text-center text-secondary small pt-3 border-top border-secondary border-opacity-25">
    Powered by <?= e($portal['portal_name']) ?> Whitelabel Infrastructure.
  </footer>
</div>

</body>
</html>
