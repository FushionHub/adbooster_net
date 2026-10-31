<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/agency_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access the Agency Whitelabel Portal.');
    redirect(url('login'));
}

$pageTitle = 'Agency Whitelabel & Client Portals — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

$portal = AgencyEngine::getAgencyPortal($userId);

// Handle Adding New Client Account & Updating Whitelabel Settings
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('agency-portal'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_client') {
        $newClient = AgencyEngine::addClientAccount($userId, $_POST);
        set_flash('success', "Client added! Magic Approval Link: {$newClient['magic_link']}");
        redirect(url('agency-portal'));
    }

    if ($action === 'update_branding') {
        $portalName = trim($_POST['portal_name'] ?? 'Agency');
        $primaryColor = trim($_POST['brand_primary_color'] ?? '#c8ff00');
        $welcome = trim($_POST['welcome_headline'] ?? 'Welcome');
        $subdomain = trim($_POST['custom_subdomain'] ?? 'agency');

        db()->update('agency_portals', [
            'portal_name' => $portalName,
            'brand_primary_color' => $primaryColor,
            'welcome_headline' => $welcome,
            'custom_subdomain' => $subdomain
        ], 'agency_user_id = ?', [$userId]);

        set_flash('success', 'Agency whitelabel branding updated successfully!');
        redirect(url('agency-portal'));
    }
}

// Fetch all agency clients
$clients = db()->fetchAll("SELECT * FROM agency_client_accounts WHERE agency_user_id = ? ORDER BY id DESC", [$userId]);
$totalRetainer = array_sum(array_map(fn($c) => (float)$c['monthly_retainer'], $clients));

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-purple-subtle text-purple border border-purple-subtle px-2 py-1 rounded-pill" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
            <i class="ph ph-buildings me-1"></i> B2B Whitelabel Suite V2.0
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-magic-wand me-1"></i> Magic Link Approvals
          </span>
        </div>
        <h2 class="h3 fw-bold mb-1">Full Agency Whitelabel & Client Portals</h2>
        <p class="text-secondary mb-0">Transform Adsbooster into your proprietary client growth portal. Manage client retainers, deliver branded executive reports, and streamline campaign approvals.</p>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-branding">
          <i class="ph ph-paint-brush me-1"></i> Whitelabel Branding
        </button>
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modal-add-client">
          <i class="ph ph-user-plus me-1"></i> Add Client Account
        </button>
      </div>
    </div>

    <!-- Agency KPIs -->
    <div class="row g-3 mb-4">
      <div class="col-md-4 col-sm-6">
        <div class="card border-0 bg-surface-card rounded-4 p-3 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small fw-semibold">Active Client Accounts</span>
            <i class="ph ph-users-three text-primary fs-5"></i>
          </div>
          <h3 class="fw-bold text-white mb-0"><?= count($clients) ?></h3>
          <small class="text-success"><i class="ph ph-check me-1"></i> Full Access Enabled</small>
        </div>
      </div>
      <div class="col-md-4 col-sm-6">
        <div class="card border-0 bg-surface-card rounded-4 p-3 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small fw-semibold">Monthly Managed Retainers</span>
            <i class="ph ph-currency-dollar text-warning fs-5"></i>
          </div>
          <h3 class="fw-bold text-white mb-0">$<?= number_format($totalRetainer, 2) ?></h3>
          <small class="text-secondary">Recurring monthly MRR</small>
        </div>
      </div>
      <div class="col-md-4 col-sm-6">
        <div class="card border-0 bg-surface-card rounded-4 p-3 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small fw-semibold">Agency Subdomain</span>
            <i class="ph ph-globe text-info fs-5"></i>
          </div>
          <h5 class="fw-bold text-info mb-0 text-truncate"><?= e($portal['custom_subdomain']) ?>.adsbooster.net</h5>
          <small class="text-secondary">Custom CNAME Ready</small>
        </div>
      </div>
    </div>

    <!-- Client Accounts Management Table -->
    <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-white">Managed Client Accounts</h5>
      </div>

      <?php if (empty($clients)): ?>
      <div class="text-center py-5 border border-dashed rounded-4 border-secondary border-opacity-25">
        <i class="ph ph-users-four fs-1 text-secondary mb-2 d-block"></i>
        <h6 class="fw-semibold text-white">No client accounts added yet</h6>
        <p class="text-secondary small mb-3">Add your first agency client to generate their private approval magic link.</p>
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modal-add-client">
          <i class="ph ph-plus me-1"></i> Add First Client
        </button>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr class="text-secondary small">
              <th>Client / Company</th>
              <th>Email</th>
              <th>Monthly Retainer</th>
              <th>Status</th>
              <th>Magic Approval Portal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($clients as $cl): ?>
            <tr>
              <td>
                <span class="fw-semibold text-white d-block"><?= e($cl['client_name']) ?></span>
                <small class="text-secondary"><?= e($cl['client_company'] ?: 'Individual') ?></small>
              </td>
              <td class="text-secondary small"><?= e($cl['client_email']) ?></td>
              <td class="fw-bold text-white">$<?= number_format((float)$cl['monthly_retainer'], 2) ?></td>
              <td><span class="badge bg-success-subtle text-success">Active</span></td>
              <td>
                <div class="input-group input-group-sm" style="max-width: 320px;">
                  <input type="text" class="form-control bg-dark text-info font-monospace border-secondary border-opacity-25" value="<?= url('client-approval', ['token' => $cl['access_token']]) ?>" readonly>
                  <a href="<?= url('client-approval', ['token' => $cl['access_token']]) ?>" target="_blank" class="btn btn-primary">
                    <i class="ph ph-arrow-square-out"></i>
                  </a>
                </div>
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

<!-- Modal: Add Client -->
<div class="modal fade" id="modal-add-client" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-surface-card text-white border border-secondary border-opacity-25 rounded-4">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_client">
        <div class="modal-header border-secondary border-opacity-25">
          <h5 class="modal-title fw-bold">Add New Agency Client</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small text-secondary">Client Full Name</label>
            <input type="text" name="client_name" class="form-control" placeholder="e.g. Robert Sterling" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Client Email</label>
            <input type="email" name="client_email" class="form-control" placeholder="robert@sterlingagency.com" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Company Name</label>
            <input type="text" name="client_company" class="form-control" placeholder="Sterling Growth Capital">
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Monthly Retainer ($ USD)</label>
            <input type="number" step="0.01" name="monthly_retainer" class="form-control" placeholder="2500.00" value="2000.00">
          </div>
        </div>
        <div class="modal-footer border-secondary border-opacity-25">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Create Client & Generate Magic Link</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Whitelabel Branding -->
<div class="modal fade" id="modal-branding" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-surface-card text-white border border-secondary border-opacity-25 rounded-4">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_branding">
        <div class="modal-header border-secondary border-opacity-25">
          <h5 class="modal-title fw-bold">Custom Whitelabel Branding</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small text-secondary">Agency Portal Name</label>
            <input type="text" name="portal_name" class="form-control" value="<?= e($portal['portal_name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Subdomain (.adsbooster.net)</label>
            <input type="text" name="custom_subdomain" class="form-control" value="<?= e($portal['custom_subdomain']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Brand Accent Color</label>
            <input type="color" name="brand_primary_color" class="form-control form-control-color w-100" value="<?= e($portal['brand_primary_color']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Welcome Headline</label>
            <input type="text" name="welcome_headline" class="form-control" value="<?= e($portal['welcome_headline']) ?>">
          </div>
        </div>
        <div class="modal-footer border-secondary border-opacity-25">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Save Branding Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
