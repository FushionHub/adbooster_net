<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Policy Rules Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act = $_POST['act'] ?? '';

        if ($act === 'save') {
            $id          = (int)($_POST['id'] ?? 0);
            $platform    = trim($_POST['platform'] ?? '');
            $ruleType    = $_POST['rule_type'] ?? 'text';
            $pattern     = trim($_POST['pattern'] ?? '');
            $severity    = $_POST['severity'] ?? 'low';
            $description = trim($_POST['description'] ?? '');
            $isActive    = isset($_POST['is_active']) ? 1 : 0;

            if ($platform === '' || $pattern === '') {
                set_flash('error', 'Platform and pattern are required.');
            } else {
                $data = [
                    'platform' => $platform,
                    'rule_type' => $ruleType,
                    'pattern' => $pattern,
                    'severity' => $severity,
                    'description' => $description,
                    'is_active' => $isActive,
                ];
                if ($id > 0) {
                    db()->update('policy_rules', $data, 'id=?', [$id]);
                    set_flash('success', 'Rule updated.');
                } else {
                    db()->insert('policy_rules', $data);
                    set_flash('success', 'Rule created.');
                }
            }
        } elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db()->delete('policy_rules', 'id=?', [$id]);
                set_flash('success', 'Rule deleted.');
            }
        }
    }
    redirect(url('admin/policy-rules'));
}

$rules = db()->fetchAll("SELECT * FROM policy_rules ORDER BY platform, severity DESC");
$editRule = null;
if (!empty($_GET['edit'])) {
    $editRule = db()->fetchOne("SELECT * FROM policy_rules WHERE id=?", [(int)$_GET['edit']]);
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3"><?= $editRule ? 'Edit Rule' : 'Add Rule' ?></h6>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="save">
                <input type="hidden" name="id" value="<?= $editRule['id'] ?? '' ?>">
                <div class="mb-3">
                    <label class="form-label-admin">Platform</label>
                    <input type="text" name="platform" class="form-control-admin" required
                           placeholder="e.g. meta, tiktok, all"
                           value="<?= htmlspecialchars($editRule['platform'] ?? '') ?>">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label-admin">Rule Type</label>
                        <select name="rule_type" class="form-select-admin">
                            <?php foreach (['text','image','url'] as $rt): ?>
                                <option value="<?= $rt ?>" <?= ($editRule['rule_type'] ?? '') === $rt ? 'selected' : '' ?>><?= ucfirst($rt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label-admin">Severity</label>
                        <select name="severity" class="form-select-admin">
                            <?php foreach (['low','medium','high','critical'] as $sev): ?>
                                <option value="<?= $sev ?>" <?= ($editRule['severity'] ?? '') === $sev ? 'selected' : '' ?>><?= ucfirst($sev) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Pattern</label>
                    <input type="text" name="pattern" class="form-control-admin" required
                           placeholder="Regex or keyword pattern"
                           value="<?= htmlspecialchars($editRule['pattern'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Description</label>
                    <textarea name="description" class="form-control-admin" rows="2"><?= htmlspecialchars($editRule['description'] ?? '') ?></textarea>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" name="is_active" class="form-check-input" id="ruleActive"
                        <?= !empty($editRule['is_active']) || !$editRule ? 'checked' : '' ?>>
                    <label class="form-check-label text-muted" for="ruleActive" style="font-size:0.8rem;">Active</label>
                </div>
                <button type="submit" class="btn-admin btn-admin-green">
                    <i class="ph-bold ph-floppy-disk"></i> <?= $editRule ? 'Update' : 'Create' ?> Rule
                </button>
                <?php if ($editRule): ?>
                    <a href="?" class="btn-admin btn-admin-outline ms-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">Rules (<?= count($rules) ?>)</h6>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead><tr><th>Platform</th><th>Type</th><th>Pattern</th><th>Severity</th><th>Active</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rules as $r): ?>
                        <tr>
                            <td class="text-white fw-medium"><?= htmlspecialchars($r['platform']) ?></td>
                            <td class="text-capitalize"><?= $r['rule_type'] ?></td>
                            <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.8rem;"><?= htmlspecialchars($r['pattern']) ?></td>
                            <td><span class="badge-status <?= $r['severity'] === 'critical' ? 'rejected' : ($r['severity'] === 'high' ? 'flagged' : ($r['severity'] === 'medium' ? 'pending' : '')) ?>"><?= $r['severity'] ?></span></td>
                            <td><?= $r['is_active'] ? '<span class="badge-status active">Yes</span>' : '<span class="badge-status inactive">No</span>' ?></td>
                            <td>
                                <a href="?edit=<?= $r['id'] ?>" class="btn-admin btn-admin-blue"><i class="ph-bold ph-pencil-simple"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn-admin btn-admin-red"><i class="ph-bold ph-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rules)): ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No rules yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
