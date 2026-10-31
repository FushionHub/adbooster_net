<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Pricing Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act = $_POST['act'] ?? '';

        if ($act === 'save') {
            $id          = (int)($_POST['id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = $_POST['description'] ?? '';
            $price       = (float)($_POST['price'] ?? 0);
            $cycle       = $_POST['billing_cycle'] ?? 'monthly';
            $adCredits   = (float)($_POST['ad_credits_included'] ?? 0);
            $aiCredits   = (int)($_POST['ai_credits_included'] ?? 0);
            $features    = trim($_POST['features'] ?? '');
            $featured    = isset($_POST['is_featured']) ? 1 : 0;
            $status      = $_POST['status'] ?? 'active';

            if ($name === '') {
                set_flash('error', 'Plan name is required.');
            } else {
                $featuresJson = json_encode(array_filter(array_map('trim', explode("\n", $features))));
                $data = [
                    'name'                => $name,
                    'description'         => $description,
                    'price'               => $price,
                    'billing_cycle'       => $cycle,
                    'ad_credits_included' => $adCredits,
                    'ai_credits_included' => $aiCredits,
                    'features_json'       => $featuresJson,
                    'is_featured'         => $featured,
                    'status'              => $status,
                ];
                if ($id > 0) {
                    db()->update('pricing_plans', $data, 'id=?', [$id]);
                    set_flash('success', 'Plan updated.');
                } else {
                    db()->insert('pricing_plans', $data);
                    set_flash('success', 'Plan created.');
                }
            }
        } elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db()->delete('pricing_plans', 'id=?', [$id]);
                set_flash('success', 'Plan deleted.');
            }
        }
    }
    redirect(url('admin/pricing'));
}

$plans = db()->fetchAll("SELECT * FROM pricing_plans ORDER BY price ASC");
$editPlan = null;
if (!empty($_GET['edit'])) {
    $editPlan = db()->fetchOne("SELECT * FROM pricing_plans WHERE id=?", [(int)$_GET['edit']]);
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3"><?= $editPlan ? 'Edit Plan' : 'Add Plan' ?></h6>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="save">
                <input type="hidden" name="id" value="<?= $editPlan['id'] ?? '' ?>">
                <div class="mb-3">
                    <label class="form-label-admin">Name</label>
                    <input type="text" name="name" class="form-control-admin" required
                           value="<?= htmlspecialchars($editPlan['name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Description</label>
                    <textarea name="description" class="form-control-admin" rows="3"><?= htmlspecialchars($editPlan['description'] ?? '') ?></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label-admin">Price ($)</label>
                        <input type="number" name="price" class="form-control-admin" step="0.01" min="0"
                               value="<?= $editPlan['price'] ?? '0.00' ?>">
                    </div>
                    <div class="col">
                        <label class="form-label-admin">Cycle</label>
                        <select name="billing_cycle" class="form-select-admin">
                            <?php foreach (['monthly','yearly','pay_as_you_go'] as $c): ?>
                                <option value="<?= $c ?>" <?= ($editPlan['billing_cycle'] ?? '') === $c ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label-admin">Ad Credits ($)</label>
                        <input type="number" step="0.01" name="ad_credits_included" class="form-control-admin" placeholder="0.00"
                               value="<?= htmlspecialchars((string)($editPlan['ad_credits_included'] ?? '0.00')) ?>">
                    </div>
                    <div class="col">
                        <label class="form-label-admin">AI Credits</label>
                        <input type="number" name="ai_credits_included" class="form-control-admin" placeholder="100"
                               value="<?= htmlspecialchars((string)($editPlan['ai_credits_included'] ?? '100')) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Features (one per line)</label>
                    <textarea name="features" class="form-control-admin" rows="4" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"><?= htmlspecialchars(implode("\n", json_decode($editPlan['features_json'] ?? '[]', true) ?? [])) ?></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label-admin">Status</label>
                        <select name="status" class="form-select-admin">
                            <option value="active" <?= ($editPlan['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($editPlan['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_featured" class="form-check-input" id="isFeatured"
                                <?= !empty($editPlan['is_featured']) ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted" for="isFeatured" style="font-size:0.8rem;">Featured</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-admin btn-admin-green">
                    <i class="ph-bold ph-floppy-disk"></i> <?= $editPlan ? 'Update' : 'Create' ?> Plan
                </button>
                <?php if ($editPlan): ?>
                    <a href="? " class="btn-admin btn-admin-outline ms-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">Plans (<?= count($plans) ?>)</h6>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead><tr><th>Name</th><th>Price</th><th>Cycle</th><th>Featured</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($plans as $p): ?>
                        <tr>
                            <td class="text-white fw-medium"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="text-white">$<?= number_format((float)$p['price'], 2) ?></td>
                            <td class="text-capitalize"><?= str_replace('_',' ',$p['billing_cycle']) ?></td>
                            <td><?= $p['is_featured'] ? '<span class="badge-status active">Yes</span>' : '<span class="badge-status inactive">No</span>' ?></td>
                            <td><span class="badge-status <?= $p['status'] ?>"><?= $p['status'] ?></span></td>
                            <td>
                                <a href="?edit=<?= $p['id'] ?>" class="btn-admin btn-admin-blue"><i class="ph-bold ph-pencil-simple"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this plan?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn-admin btn-admin-red"><i class="ph-bold ph-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($plans)): ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No plans yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
