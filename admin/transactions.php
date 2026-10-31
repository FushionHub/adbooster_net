<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Transaction Log';

$filterStatus = $_GET['status'] ?? '';
$filterGateway = $_GET['gateway'] ?? '';
$filterType = $_GET['type'] ?? '';

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
                <tr><th>ID</th><th>User</th><th>Gateway</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th><th>Date</th></tr>
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
                    <td class="text-white fw-bold">$<?= number_format((float)$t['amount'], 2) ?></td>
                    <td><span class="badge-status <?= $t['status'] ?>"><?= $t['status'] ?></span></td>
                    <td class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($t['gateway_reference'] ?? 'N/A') ?></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No transactions found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
