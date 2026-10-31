<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'SEO Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act = $_POST['act'] ?? '';

        if ($act === 'save') {
            $pageSlug  = trim($_POST['page_slug'] ?? '');
            $metaTitle = trim($_POST['meta_title'] ?? '');
            $metaDesc  = trim($_POST['meta_description'] ?? '');
            $ogImage   = trim($_POST['og_image'] ?? '');

            if ($pageSlug !== '') {
                $entries = [
                    ['item_key' => 'meta_title', 'value' => $metaTitle],
                    ['item_key' => 'meta_description', 'value' => $metaDesc],
                    ['item_key' => 'og_image', 'value' => $ogImage],
                ];
                foreach ($entries as $entry) {
                    $existing = db()->fetchOne(
                        "SELECT id FROM dynamic_content WHERE page_slug=? AND section_key='seo' AND item_key=?",
                        [$pageSlug, $entry['item_key']]
                    );
                    if ($existing) {
                        db()->update('dynamic_content', ['content_value' => $entry['value']], 'id=?', [$existing['id']]);
                    } else {
                        db()->insert('dynamic_content', [
                            'page_slug' => $pageSlug,
                            'section_key' => 'seo',
                            'item_key' => $entry['item_key'],
                            'content_value' => $entry['value'],
                        ]);
                    }
                }
                set_flash('success', "SEO data saved for page: {$pageSlug}");
            }
        } elseif ($act === 'delete') {
            $deleteId = (int)($_POST['delete_id'] ?? 0);
            if ($deleteId > 0) {
                db()->delete('dynamic_content', 'id=?', [$deleteId]);
                set_flash('success', 'SEO entry deleted.');
            }
        }
    }
    redirect(url('admin/seo'));
}

$seoEntries = db()->fetchAll(
    "SELECT * FROM dynamic_content WHERE section_key='seo' ORDER BY page_slug, item_key"
);

$pages = [];
foreach ($seoEntries as $se) {
    $pages[$se['page_slug']][$se['item_key']] = $se;
}

$editPage = null;
if (!empty($_GET['page'])) {
    $editPage = $_GET['page'];
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3"><?= $editPage ? "Edit SEO: {$editPage}" : 'Add SEO Override' ?></h6>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="save">
                <div class="mb-3">
                    <label class="form-label-admin">Page Slug</label>
                    <input type="text" name="page_slug" class="form-control-admin" required
                           placeholder="e.g. home, about, services"
                           value="<?= htmlspecialchars($editPage ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Meta Title</label>
                    <input type="text" name="meta_title" class="form-control-admin"
                           value="<?= htmlspecialchars($pages[$editPage]['meta_title']['content_value'] ?? '') ?>"
                           placeholder="Page title for search engines">
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">Meta Description</label>
                    <textarea name="meta_description" class="form-control-admin" rows="3"
                              placeholder="Brief description for search results"><?= htmlspecialchars($pages[$editPage]['meta_description']['content_value'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label-admin">OG Image URL</label>
                    <input type="url" name="og_image" class="form-control-admin"
                           value="<?= htmlspecialchars($pages[$editPage]['og_image']['content_value'] ?? '') ?>"
                           placeholder="https://...image.jpg">
                </div>
                <button type="submit" class="btn-admin btn-admin-green">
                    <i class="ph-bold ph-floppy-disk"></i> Save SEO
                </button>
                <?php if ($editPage): ?>
                    <a href="?" class="btn-admin btn-admin-outline ms-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">SEO Entries (<?= count($pages) ?> pages)</h6>
            <?php if (empty($pages)): ?>
                <p class="text-muted">No SEO entries yet.</p>
            <?php else: ?>
                <?php foreach ($pages as $pageSlug => $items): ?>
                    <div class="d-flex align-items-center justify-content-between p-2 rounded mb-2" style="background:var(--admin-hover);">
                        <div>
                            <div class="text-white fw-medium" style="font-size:0.9rem;"><?= htmlspecialchars($pageSlug) ?></div>
                            <div class="text-muted" style="font-size:0.72rem;">
                                Title: <?= htmlspecialchars($items['meta_title']['content_value'] ?? '(none)') ?>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="?page=<?= urlencode($pageSlug) ?>" class="btn-admin btn-admin-blue">
                                <i class="ph-bold ph-pencil-simple"></i>
                            </a>
                            <?php foreach ($items as $item): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="delete">
                                    <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                    <button class="btn-admin btn-admin-red"><i class="ph-bold ph-trash"></i></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
