<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Translation Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act = $_POST['act'] ?? '';

        if ($act === 'save') {
            $id       = (int)($_POST['id'] ?? 0);
            $locale   = trim($_POST['locale'] ?? '');
            $key      = trim($_POST['translation_key'] ?? '');
            $value    = $_POST['value'] ?? '';

            if ($locale === '' || $key === '') {
                set_flash('error', 'Locale and key are required.');
            } else {
                $existing = db()->fetchOne(
                    "SELECT id FROM translations WHERE locale=? AND translation_key=? AND id!=?",
                    [$locale, $key, $id]
                );
                if ($existing) {
                    set_flash('error', 'A translation with this locale and key already exists.');
                } else {
                    $data = ['locale' => $locale, 'translation_key' => $key, 'value' => $value];
                    if ($id > 0) {
                        db()->update('translations', $data, 'id=?', [$id]);
                        set_flash('success', 'Translation updated.');
                    } else {
                        db()->insert('translations', $data);
                        set_flash('success', 'Translation created.');
                    }
                }
            }
        } elseif ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db()->delete('translations', 'id=?', [$id]);
                set_flash('success', 'Translation deleted.');
            }
        }
    }
    redirect(url('admin/translations'));
}

$filterLocale = $_GET['locale'] ?? '';
$where = '1=1';
$params = [];
if ($filterLocale !== '') {
    $where .= " AND locale=?";
    $params[] = $filterLocale;
}

$translations = db()->fetchAll(
    "SELECT * FROM translations WHERE {$where} ORDER BY locale, translation_key LIMIT 200",
    $params
);

$locales = db()->fetchAll("SELECT DISTINCT locale FROM translations ORDER BY locale");

$editEntry = null;
if (!empty($_GET['edit'])) {
    $editEntry = db()->fetchOne("SELECT * FROM translations WHERE id=?", [(int)$_GET['edit']]);
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3"><?= $editEntry ? 'Edit Translation' : 'Add Translation' ?></h6>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="save">
                <input type="hidden" name="id" value="<?= $editEntry['id'] ?? '' ?>">
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label-admin">Locale</label>
                        <input type="text" name="locale" class="form-control-admin" required
                               placeholder="e.g. en, fr, es"
                               value="<?= htmlspecialchars($editEntry['locale'] ?? 'en') ?>">
                    </div>
                    <div class="col">
                        <label class="form-label-admin">Key</label>
                        <input type="text" name="translation_key" class="form-control-admin" required
                               placeholder="e.g. nav.home"
                               value="<?= htmlspecialchars($editEntry['translation_key'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Value</label>
                    <textarea name="value" class="form-control-admin" rows="4"
                              placeholder="Translated text"><?= htmlspecialchars($editEntry['value'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-admin btn-admin-green">
                    <i class="ph-bold ph-floppy-disk"></i> <?= $editEntry ? 'Update' : 'Create' ?>
                </button>
                <?php if ($editEntry): ?>
                    <a href="?" class="btn-admin btn-admin-outline ms-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="?" class="btn-admin <?= !$filterLocale ? 'btn-admin-green' : 'btn-admin-outline' ?>">All</a>
                <?php foreach ($locales as $loc): ?>
                    <a href="?locale=<?= urlencode($loc['locale']) ?>" class="btn-admin <?= $filterLocale === $loc['locale'] ? 'btn-admin-green' : 'btn-admin-outline' ?>"><?= htmlspecialchars($loc['locale']) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead><tr><th>Locale</th><th>Key</th><th>Value</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($translations as $t): ?>
                        <tr>
                            <td><span class="badge-status"><?= htmlspecialchars($t['locale']) ?></span></td>
                            <td class="text-white fw-medium" style="font-size:0.85rem;"><?= htmlspecialchars($t['translation_key']) ?></td>
                            <td class="text-muted" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.85rem;"><?= htmlspecialchars($t['value']) ?></td>
                            <td>
                                <a href="?edit=<?= $t['id'] ?>" class="btn-admin btn-admin-blue"><i class="ph-bold ph-pencil-simple"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button class="btn-admin btn-admin-red"><i class="ph-bold ph-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($translations)): ?>
                        <tr><td colspan="4" class="text-muted text-center py-4">No translations found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
