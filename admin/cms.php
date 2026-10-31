<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Content Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $action = $_POST['cms_action'] ?? '';
        if ($action === 'save') {
            $pageSlug  = trim($_POST['page_slug'] ?? '');
            $sectionKey = trim($_POST['section_key'] ?? '');
            $itemKey   = trim($_POST['item_key'] ?? '');
            $contentValue = $_POST['content_value'] ?? '';
            $mediaUrl  = trim($_POST['media_url'] ?? '');

            if ($pageSlug && $sectionKey && $itemKey) {
                $existing = db()->fetchOne(
                    "SELECT id FROM dynamic_content WHERE page_slug=? AND section_key=? AND item_key=?",
                    [$pageSlug, $sectionKey, $itemKey]
                );
                if ($existing) {
                    db()->update('dynamic_content',
                        ['content_value' => $contentValue, 'media_url' => $mediaUrl],
                        'id=?', [$existing['id']]
                    );
                } else {
                    db()->insert('dynamic_content', [
                        'page_slug' => $pageSlug,
                        'section_key' => $sectionKey,
                        'item_key' => $itemKey,
                        'content_value' => $contentValue,
                        'media_url' => $mediaUrl,
                    ]);
                }
                set_flash('success', 'Content saved successfully.');
            } else {
                set_flash('error', 'Page, section, and item keys are required.');
            }
        } elseif ($action === 'delete') {
            $deleteId = (int)($_POST['delete_id'] ?? 0);
            if ($deleteId > 0) {
                db()->delete('dynamic_content', 'id=?', [$deleteId]);
                set_flash('success', 'Content entry deleted.');
            }
        }
    }
    redirect(url('admin/cms'));
}

$entries = db()->fetchAll("SELECT * FROM dynamic_content ORDER BY page_slug, section_key, item_key");
$editEntry = null;
if (!empty($_GET['edit'])) {
    $editEntry = db()->fetchOne("SELECT * FROM dynamic_content WHERE id=?", [(int)$_GET['edit']]);
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3"><?= $editEntry ? 'Edit Content' : 'Add Content' ?></h6>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="cms_action" value="save">
                <div class="mb-3">
                    <label class="form-label-admin">Page Slug</label>
                    <input type="text" name="page_slug" class="form-control-admin" required
                           placeholder="e.g. home, about, meta"
                           value="<?= htmlspecialchars($editEntry['page_slug'] ?? $_GET['page'] ?? '') ?>">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label-admin">Section Key</label>
                        <input type="text" name="section_key" class="form-control-admin" required
                               placeholder="e.g. hero, seo"
                               value="<?= htmlspecialchars($editEntry['section_key'] ?? $_GET['section'] ?? '') ?>">
                    </div>
                    <div class="col">
                        <label class="form-label-admin">Item Key</label>
                        <input type="text" name="item_key" class="form-control-admin" required
                               placeholder="e.g. title, description"
                               value="<?= htmlspecialchars($editEntry['item_key'] ?? $_GET['item'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Content Value</label>
                    <textarea name="content_value" class="form-control-admin" rows="6"><?= htmlspecialchars($editEntry['content_value'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Media URL</label>
                    <input type="url" name="media_url" class="form-control-admin"
                           value="<?= htmlspecialchars($editEntry['media_url'] ?? '') ?>"
                           placeholder="https://...">
                </div>
                <button type="submit" class="btn-admin btn-admin-green">
                    <i class="ph-bold ph-floppy-disk"></i> Save Content
                </button>
                <?php if ($editEntry): ?>
                    <a href="<?= url('admin/cms') ?>" class="btn-admin btn-admin-outline ms-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">Content Entries (<?= count($entries) ?>)</h6>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr><th>Page</th><th>Section</th><th>Item</th><th>Value</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td class="text-white fw-medium"><?= htmlspecialchars($e['page_slug']) ?></td>
                            <td><?= htmlspecialchars($e['section_key']) ?></td>
                            <td><?= htmlspecialchars($e['item_key']) ?></td>
                            <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(substr($e['content_value'], 0, 60)) ?></td>
                            <td>
                                <a href="?edit=<?= $e['id'] ?>" class="btn-admin btn-admin-blue" title="Edit">
                                    <i class="ph-bold ph-pencil-simple"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this entry?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="cms_action" value="delete">
                                    <input type="hidden" name="delete_id" value="<?= $e['id'] ?>">
                                    <button type="submit" class="btn-admin btn-admin-red" title="Delete">
                                        <i class="ph-bold ph-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="5" class="text-muted text-center">No content entries yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
