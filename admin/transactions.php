<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/payments.php';
require_once __DIR__ . '/../modules/payment_gateways.php';

$pageTitle = 'Transaction Log';

$filterStatus = $_GET['status'] ?? '';
$filterGateway = $_GET['gateway'] ?? '';
$filterType = $_GET['type'] ?? '';

// ============================================================
//  Admin actions — verify / retry a transaction
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
        redirect(url('admin/transactions'));
    }

    $act = $_POST['act'] ?? '';

    if ($act === 'verify') {
        $txnId = (int)($_POST['transaction_id'] ?? 0);
        $row   = PaymentProcessor::findTransactionById($txnId);

        if ($row === null) {
            set_flash('error', 'Transaction #' . $txnId . ' was not found in the database.');
            set_flash('warning', 'A missing transaction usually means the schema is behind — apply update-any-error.sql, then verify again.');
            redirect(url('checkout', ['txn' => $txnId]));
        }

        $gateway = strtolower((string)($row['gateway'] ?? ''));
        $reference = (string)($row['gateway_reference'] ?? $row['reference'] ?? '');

        if ($gateway === '' || $reference === '') {
            set_flash('error', 'Transaction #' . $txnId . ' has no gateway reference to verify.');
            redirect(PaymentProcessor::checkoutUrlForTransaction($row));
        }

        $result = PaymentProcessor::verifyGateway($gateway, $reference);

        if (!empty($result['success'])) {
            set_flash('success', 'Payment #' . $txnId . ' verified — ' . $result['message']);
            redirect(url('admin/transactions'));
        }

        // Verification failed → the Checkout page shows the error next to
        // the order so the admin can see exactly what needs fixing.
        set_flash('error', $result['message'] ?: 'Payment verification failed.');
        set_flash(
            'warning',
            'Verification failed for ' . strtoupper($gateway) . ' reference ' . $reference . '. '
            . 'The checkout page below shows the order context. Database fixes for this class '
            . 'of error are listed in update-any-error.sql.'
        );
        redirect(PaymentProcessor::checkoutUrlForTransaction($row, $reference));
    }

    if ($act === 'mark_failed') {
        $txnId = (int)($_POST['transaction_id'] ?? 0);
        if ($txnId > 0) {
            db()->update('transactions', [
                'status'     => 'failed',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ? AND status <> ?', [$txnId, 'successful']);
            set_flash('success', 'Transaction #' . $txnId . ' marked as failed.');
        }
        redirect(url('admin/transactions'));
    }

    redirect(url('admin/transactions'));
}

$where = '1=1';
$params = [];
if ($filterStatus && in_array($filterStatus, ['pending','successful','failed','refunded'])) {
    $where .= " AND t.status=?";
    $params[] = $filterStatus;
}
if ($filterGateway && in_array($filterGateway, ['paystack','flutterwave'])) {
    $where .= " AND t.gateway=?";
    $params[] = $filterGateway;
}
if ($filterType && in_array($filterType, ['plan_subscription','ad_credit_topup','ai_credit_topup'])) {
    $where .= " AND t.type=?";
    $params[] = $filterType;
}

$transactions = db()->fetchAll(
    "SELECT t.*, u.full_name, u.email 
     FROM transactions t LEFT JOIN users u ON u.id = t.user_id 
     WHERE {$where} ORDER BY t.created_at DESC LIMIT 200",
    $params
);

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID','User','Email','Gateway','Type','Amount','Currency','Status','Date']);
    foreach ($transactions as $row) {
        fputcsv($output, [
            $row['id'], $row['full_name'] ?? 'N/A', $row['email'] ?? '',
            $row['gateway'], $row['type'], $row['amount'], $row['currency'],
            $row['status'], $row['created_at']
        ]);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="admin-card mb-4">
    <div class="card-title mb-2">
        <i class="ph-bold ph-webhooks-logo me-1"></i> Gateway Webhook URLs
    </div>
    <p style="font-size:0.78rem;color:#8b93a7;margin-bottom:14px;">
        Paste these URLs into each gateway dashboard so a settled payment is verified
        automatically — even when the payer never returns to the browser.
    </p>
    <?php foreach (PaymentGateways::webhookUrls() as $gwKey => $hook): ?>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">
            <span class="badge-status <?= PaymentGateways::isEnabled($gwKey) ? 'successful' : 'pending' ?>" style="min-width:96px;">
                <?= e($hook['label']) ?>
            </span>
            <code id="hook-<?= e($gwKey) ?>" style="flex:1;min-width:260px;background:#0b1220;border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:8px 12px;font-size:0.75rem;color:#c8ff00;word-break:break-all;">
                <?= e($hook['url']) ?>
            </code>
            <button type="button" class="btn-admin btn-admin-blue" onclick="copyHook('hook-<?= e($gwKey) ?>')" title="Copy webhook URL">
                <i class="ph-bold ph-copy"></i> Copy
            </button>
        </div>
        <div style="font-size:0.72rem;color:#6f7891;margin:-6px 0 14px 106px;">
            <?= e($hook['note']) ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" style="width:100%;">
        <select name="status" class="form-select-admin" onchange="this.form.submit()">
            <option value="">All Status</option>
            <?php foreach (['pending','successful','failed','refunded'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="gateway" class="form-select-admin" onchange="this.form.submit()">
            <option value="">All Gateways</option>
            <?php foreach (['paystack','flutterwave'] as $g): ?>
                <option value="<?= $g ?>" <?= $filterGateway === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" class="form-select-admin" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach (['plan_subscription','ad_credit_topup','ai_credit_topup'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= str_replace('_',' ',ucfirst($t)) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="?" class="btn-admin btn-admin-outline">Reset</a>
        <div class="ms-auto d-flex gap-2">
            <a href="?export_csv&<?= http_build_query($_GET) ?>" class="btn-admin btn-admin-green">
                <i class="ph-bold ph-file-csv"></i> CSV
            </a>
            <button onclick="window.print()" class="btn-admin btn-admin-blue">
                <i class="ph-bold ph-printer"></i> Print
            </button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>User</th><th>Gateway</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th><th>Date</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td class="text-white fw-bold">#<?= $t['id'] ?></td>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($t['full_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($t['email'] ?? '') ?></div>
                    </td>
                    <td class="text-capitalize"><?= $t['gateway'] ?></td>
                    <td class="text-capitalize" style="font-size:0.8rem;"><?= str_replace('_',' ',$t['type']) ?></td>
                    <td class="text-white fw-bold"><?= txn_money((float)$t['amount'], $t['currency'] ?? null) ?></td>
                    <td><span class="badge-status <?= $t['status'] ?>"><?= $t['status'] ?></span></td>
                    <td class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($t['gateway_reference'] ?? 'N/A') ?></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= dt($t['created_at'], 'M d, Y H:i') ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (($t['status'] ?? '') === 'pending' && in_array($t['gateway'] ?? '', ['flutterwave', 'paystack'], true)): ?>
                                <form method="POST" onsubmit="return confirm('Verify this payment with <?= ucfirst($t['gateway']) ?> and credit the user?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="verify">
                                    <input type="hidden" name="transaction_id" value="<?= (int)$t['id'] ?>">
                                    <button class="btn-admin btn-admin-blue" title="Re-check with the gateway and credit the user if it succeeded">
                                        <i class="ph-bold ph-arrows-clockwise"></i> Verify
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Mark this transaction as failed?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="mark_failed">
                                    <input type="hidden" name="transaction_id" value="<?= (int)$t['id'] ?>">
                                    <button class="btn-admin btn-admin-outline" title="Mark as failed">
                                        <i class="ph-bold ph-x"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= e(PaymentProcessor::checkoutUrlForTransaction($t)) ?>" class="btn-admin btn-admin-outline" title="Open this order on the checkout page">
                                <i class="ph-bold ph-shopping-cart"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="9" class="text-muted text-center py-4">No transactions found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

<script>
function copyHook(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const text = el.textContent.trim();
    navigator.clipboard.writeText(text).then(function () {
        const btn = el.parentElement.querySelector('button');
        if (btn) {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="ph-bold ph-check"></i> Copied';
            setTimeout(function () { btn.innerHTML = original; }, 1600);
        }
    });
}
</script>
