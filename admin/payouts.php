<?php declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/email_service.php';

$pageTitle = 'Commission & Payout Moderation';

// Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $payoutId = (int)($_POST['payout_id'] ?? 0);

        if ($payoutId > 0) {
            $payout = db()->fetchOne("SELECT p.*, u.email, u.full_name FROM payout_requests p JOIN users u ON p.user_id = u.id WHERE p.id = ?", [$payoutId]);
            if (!$payout) {
                set_flash('error', 'Payout request not found.');
            } else {
                $status = '';
                if ($act === 'approve') $status = 'approved';
                elseif ($act === 'pay') $status = 'paid';
                elseif ($act === 'reject') $status = 'rejected';

                if ($status !== '') {
                    db()->update('payout_requests', ['status' => $status], 'id = ?', [$payoutId]);

                    // Send email notification to user
                    EmailService::sendTransactional($payout['email'], 'payout_status', [
                        'name'   => $payout['full_name'],
                        'amount' => number_format((float)$payout['amount'], 2),
                        'method' => strtoupper($payout['method']),
                        'status' => ucfirst($status),
                        'notes'  => $status === 'paid' ? 'Funds have been disbursed to your receiving account.' : 'Your request status has been updated by administration.',
                    ]);

                    set_flash('success', "Payout #{$payoutId} marked as {$status} and notification email sent.");
                }
            }
        }
    }
    redirect(url('admin/payouts'));
}

$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT p.*, u.full_name as user_name, u.email as user_email 
        FROM payout_requests p 
        JOIN users u ON p.user_id = u.id";
$params = [];

if (in_array($statusFilter, ['pending', 'approved', 'paid', 'rejected'], true)) {
    $sql .= " WHERE p.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY p.created_at DESC";

$requests = db()->fetchAll($sql, $params);

// Stats
$totalPaid = (float)(db()->fetchOne("SELECT SUM(amount) as s FROM payout_requests WHERE status = 'paid'")['s'] ?? 0);
$totalPending = (float)(db()->fetchOne("SELECT SUM(amount) as s FROM payout_requests WHERE status = 'pending'")['s'] ?? 0);
$countPending = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM payout_requests WHERE status = 'pending'")['cnt'] ?? 0);

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="text-white fw-bold mb-1">
            <i class="ph-bold ph-hand-coins text-admin-green me-2"></i> Affiliate &amp; Referral Payout Requests
        </h5>
        <p class="text-muted mb-0 font-size-13">Process and disburse referral commission withdrawals.</p>
    </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Pending Payout Amount</div>
            <div class="h3 text-warning fw-bold mb-0">$<?= number_format($totalPending, 2) ?></div>
            <small class="text-muted"><?= $countPending ?> requests awaiting approval</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="admin-card p-3">
            <div class="text-muted font-size-12 mb-1">Total Disbursed</div>
            <div class="h3 text-success fw-bold mb-0">$<?= number_format($totalPaid, 2) ?></div>
            <small class="text-muted">Completed commission payouts</small>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="d-flex gap-2 mb-3">
        <a href="?status=all" class="btn-admin <?= $statusFilter === 'all' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">All</a>
        <a href="?status=pending" class="btn-admin <?= $statusFilter === 'pending' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">Pending (<?= $countPending ?>)</a>
        <a href="?status=paid" class="btn-admin <?= $statusFilter === 'paid' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">Paid</a>
        <a href="?status=rejected" class="btn-admin <?= $statusFilter === 'rejected' ? 'btn-admin-green' : 'btn-admin-outline' ?> py-1 px-3" style="font-size:0.8rem;">Rejected</a>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Details</th>
                    <th>Status</th>
                    <th>Requested At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No payout requests found.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td class="text-muted">#<?= $r['id'] ?></td>
                            <td>
                                <div class="text-white fw-medium font-size-13"><?= htmlspecialchars($r['user_name']) ?></div>
                                <div class="text-muted font-size-11"><?= htmlspecialchars($r['user_email']) ?></div>
                            </td>
                            <td class="text-white fw-bold">$<?= number_format((float)$r['amount'], 2) ?></td>
                            <td>
                                <span class="badge bg-secondary font-size-11 text-uppercase"><?= htmlspecialchars($r['method']) ?></span>
                            </td>
                            <td class="font-size-12 text-muted" style="max-width:220px; word-break:break-all;">
                                <?= htmlspecialchars($r['details_json']) ?>
                            </td>
                            <td>
                                <span class="badge-status <?= $r['status'] === 'paid' ? 'active' : ($r['status'] === 'pending' ? 'pending' : 'inactive') ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </td>
                            <td class="text-muted font-size-12">
                                <?= date('M d, Y H:i', strtotime($r['created_at'])) ?>
                            </td>
                            <td class="text-end">
                                <?php if ($r['status'] === 'pending' || $r['status'] === 'approved'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payout_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="act" value="pay">
                                        <button type="submit" class="btn-admin btn-admin-green" title="Mark as Paid" onclick="return confirm('Confirm payment disbursement?');">
                                            <i class="ph-bold ph-check-circle"></i> Pay
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payout_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="act" value="reject">
                                        <button type="submit" class="btn-admin btn-admin-red" title="Reject Request" onclick="return confirm('Reject this payout request?');">
                                            <i class="ph-bold ph-x-circle"></i> Reject
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted font-size-11">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
