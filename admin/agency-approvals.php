<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Agency Portals & Client Approvals';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $approvalId = (int)($_POST['approval_id'] ?? 0);
        $portalId   = (int)($_POST['portal_id'] ?? 0);

        if ($act === 'update_approval' && $approvalId > 0) {
            $status = $_POST['status'] ?? 'approved';
            if (in_array($status, ['pending', 'approved', 'rejected_revision'])) {
                db()->update('agency_client_approvals', [
                    'approval_status' => $status,
                    'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
                ], 'id=?', [$approvalId]);
                set_flash('success', "Approval item #{$approvalId} marked as {$status}.");
            }
        } elseif ($act === 'delete_approval' && $approvalId > 0) {
            db()->delete('agency_client_approvals', 'id=?', [$approvalId]);
            set_flash('success', "Approval item #{$approvalId} deleted.");
        } elseif ($act === 'toggle_whitelabel' && $portalId > 0) {
            $portal = db()->fetchOne("SELECT is_whitelabel_active FROM agency_portals WHERE id=?", [$portalId]);
            if ($portal) {
                $newVal = $portal['is_whitelabel_active'] ? 0 : 1;
                db()->update('agency_portals', ['is_whitelabel_active' => $newVal], 'id=?', [$portalId]);
                set_flash('success', "Whitelabel status for portal #{$portalId} updated.");
            }
        }
    }
    redirect(url('admin/agency-approvals'));
}

// KPI Metrics
$totalPortals   = db()->count('agency_portals');
$totalClients   = db()->count('agency_client_accounts');
$pendingApprovals = db()->count('agency_client_approvals', "approval_status='pending'");
$totalRetainers = (float)(db()->fetchOne("SELECT COALESCE(SUM(monthly_retainer), 0) as total FROM agency_client_accounts WHERE status='active'")['total'] ?? 0);

$tab = $_GET['tab'] ?? 'approvals';
$filterStatus = $_GET['status'] ?? '';

// Approvals query
$appWhere = '1=1';
$appParams = [];
if ($filterStatus && in_array($filterStatus, ['pending', 'approved', 'rejected_revision'])) {
    $appWhere .= " AND a.approval_status = ?";
    $appParams[] = $filterStatus;
}

$approvals = db()->fetchAll(
    "SELECT a.*, u.full_name as agency_name, c.client_name, c.client_company 
     FROM agency_client_approvals a
     LEFT JOIN users u ON u.id = a.agency_user_id
     LEFT JOIN agency_client_accounts c ON c.id = a.client_account_id
     WHERE {$appWhere}
     ORDER BY a.created_at DESC LIMIT 50",
    $appParams
);

// Portals query
$portals = db()->fetchAll(
    "SELECT p.*, u.full_name, u.email 
     FROM agency_portals p
     LEFT JOIN users u ON u.id = p.agency_user_id
     ORDER BY p.created_at DESC LIMIT 50"
);

// Clients query
$clients = db()->fetchAll(
    "SELECT c.*, u.full_name as agency_name 
     FROM agency_client_accounts c
     LEFT JOIN users u ON u.id = c.agency_user_id
     ORDER BY c.created_at DESC LIMIT 50"
);

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon purple"><i class="ph-bold ph-buildings"></i></div>
            <div>
                <div class="card-title">Agency Portals</div>
                <div class="card-value"><?= number_format($totalPortals) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-briefcase"></i></div>
            <div>
                <div class="card-title">Client Accounts</div>
                <div class="card-value"><?= number_format($totalClients) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-hourglass-high"></i></div>
            <div>
                <div class="card-title">Pending Approvals</div>
                <div class="card-value"><?= number_format($pendingApprovals) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon green"><i class="ph-bold ph-currency-dollar"></i></div>
            <div>
                <div class="card-title">Monthly Retainers</div>
                <div class="card-value">$<?= number_format($totalRetainers, 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex gap-2">
        <a href="?tab=approvals" class="btn-admin <?= $tab === 'approvals' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-check-square-offset"></i> Client Approvals (<?= $pendingApprovals ?>)
        </a>
        <a href="?tab=portals" class="btn-admin <?= $tab === 'portals' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-browser"></i> Agency Whitelabel Portals (<?= $totalPortals ?>)
        </a>
        <a href="?tab=clients" class="btn-admin <?= $tab === 'clients' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-users-four"></i> Retainer Clients (<?= $totalClients ?>)
        </a>
    </div>
</div>

<?php if ($tab === 'approvals'): ?>
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h6 class="text-white fw-bold mb-0">Client Approval Review Queue</h6>
            <div class="d-flex gap-2">
                <a href="?tab=approvals" class="btn-admin btn-admin-outline <?= !$filterStatus ? 'active' : '' ?>">All</a>
                <a href="?tab=approvals&status=pending" class="btn-admin btn-admin-outline <?= $filterStatus === 'pending' ? 'active' : '' ?>">Pending</a>
                <a href="?tab=approvals&status=approved" class="btn-admin btn-admin-outline <?= $filterStatus === 'approved' ? 'active' : '' ?>">Approved</a>
                <a href="?tab=approvals&status=rejected_revision" class="btn-admin btn-admin-outline <?= $filterStatus === 'rejected_revision' ? 'active' : '' ?>">Revision Needed</a>
            </div>
        </div>

        <?php if (empty($approvals)): ?>
            <p class="text-muted py-4 text-center">No approval requests currently queued.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Item Title & Type</th>
                            <th>Agency & Client</th>
                            <th>Status</th>
                            <th>Client Feedback</th>
                            <th>Submitted</th>
                            <th>Admin Override</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvals as $a): ?>
                            <tr>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($a['title']) ?></div>
                                    <span class="badge bg-dark border border-secondary text-info small">
                                        <?= strtoupper(str_replace('_', ' ', $a['item_type'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-white small fw-semibold"><?= htmlspecialchars($a['client_name'] ?? 'Client') ?> (<?= htmlspecialchars($a['client_company'] ?? '') ?>)</div>
                                    <div class="text-muted small">Agency: <?= htmlspecialchars($a['agency_name'] ?? 'Agency') ?></div>
                                </td>
                                <td>
                                    <?php
                                    $stClass = [
                                        'approved' => 'active',
                                        'pending' => 'pending',
                                        'rejected_revision' => 'rejected'
                                    ];
                                    $cls = $stClass[$a['approval_status']] ?? 'pending';
                                    ?>
                                    <span class="badge-status <?= $cls ?>"><?= ucwords(str_replace('_', ' ', $a['approval_status'])) ?></span>
                                </td>
                                <td style="max-width: 250px;">
                                    <div class="small text-muted text-truncate" title="<?= htmlspecialchars($a['client_feedback'] ?? '') ?>">
                                        <?= htmlspecialchars($a['client_feedback'] ?: 'No comments provided') ?>
                                    </div>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M j, Y', strtotime($a['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php if ($a['approval_status'] !== 'approved'): ?>
                                            <form method="POST" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="update_approval">
                                                <input type="hidden" name="approval_id" value="<?= $a['id'] ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn-admin btn-admin-green" title="Force Approve">
                                                    <i class="ph-bold ph-check"></i> Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this approval item?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_approval">
                                            <input type="hidden" name="approval_id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn-admin btn-admin-red" title="Delete">
                                                <i class="ph-bold ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'portals'): ?>
    <div class="admin-card">
        <h6 class="text-white fw-bold mb-3">Agency Whitelabel Portals</h6>
        <?php if (empty($portals)): ?>
            <p class="text-muted py-4 text-center">No agency whitelabel portals registered.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Portal Name</th>
                            <th>Agency Owner</th>
                            <th>Subdomain / Domain</th>
                            <th>Colors</th>
                            <th>Whitelabel</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($portals as $p): ?>
                            <tr>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($p['portal_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($p['welcome_headline'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($p['full_name'] ?? 'User') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($p['email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="font-monospace text-info small"><?= htmlspecialchars($p['custom_domain'] ?: ($p['custom_subdomain'] . '.adsbooster.net')) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 align-items-center">
                                        <div style="width: 14px; height: 14px; border-radius: 3px; background: <?= htmlspecialchars($p['brand_primary_color'] ?: '#22c55e') ?>;" title="Primary"></div>
                                        <div style="width: 14px; height: 14px; border-radius: 3px; background: <?= htmlspecialchars($p['brand_secondary_color'] ?: '#3b82f6') ?>;" title="Secondary"></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status <?= $p['is_whitelabel_active'] ? 'active' : 'inactive' ?>">
                                        <?= $p['is_whitelabel_active'] ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M j, Y', strtotime($p['created_at'])) ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="toggle_whitelabel">
                                        <input type="hidden" name="portal_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-admin <?= $p['is_whitelabel_active'] ? 'btn-admin-yellow' : 'btn-admin-green' ?>">
                                            <i class="ph-bold <?= $p['is_whitelabel_active'] ? 'ph-pause' : 'ph-play' ?>"></i> Toggle
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="admin-card">
        <h6 class="text-white fw-bold mb-3">Agency Retainer Clients</h6>
        <?php if (empty($clients)): ?>
            <p class="text-muted py-4 text-center">No agency clients found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Client & Company</th>
                            <th>Managing Agency</th>
                            <th>Email</th>
                            <th>Monthly Retainer</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($c['client_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($c['client_company'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($c['agency_name'] ?? 'Agency') ?></div>
                                </td>
                                <td>
                                    <div class="text-muted small"><?= htmlspecialchars($c['client_email']) ?></div>
                                </td>
                                <td>
                                    <div class="text-success fw-bold"><?= htmlspecialchars($c['currency'] ?? '$') ?><?= number_format((float)$c['monthly_retainer'], 2) ?>/mo</div>
                                </td>
                                <td>
                                    <span class="badge-status <?= $c['status'] === 'active' ? 'active' : 'pending' ?>">
                                        <?= ucfirst($c['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M j, Y', strtotime($c['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
