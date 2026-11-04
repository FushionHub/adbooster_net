<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../includes/seo_meta.php';

$pageTitle = 'SEO Management';

/**
 * Settings handled by this screen (site-wide defaults).
 */
const SEO_SITE_SETTINGS = [
    'site_url'              => 'Canonical site URL (used for sitemap, canonical links, llms files)',
    'seo_default_title'     => 'Default meta title for pages without an override',
    'seo_default_description' => 'Default meta description for pages without an override',
    'seo_keywords'          => 'Default meta keywords',
    'seo_og_image'          => 'Default social share image (absolute URL or path)',
    'seo_twitter_site'      => 'Twitter/X handle, e.g. @adsbooster',
    'seo_default_robots'    => 'Default robots directive',
];

/**
 * Pages that can carry an override, with a friendly label.
 */
const SEO_PAGES = [
    'index'          => 'Home',
    'about'          => 'About',
    'pricing-plan'   => 'Pricing',
    'faq'            => 'FAQ',
    'contact'        => 'Contact',
    'blog'           => 'Blog',
    'marketplace'    => 'Marketplace',
    'jobs'           => 'Jobs',
    'bounties'       => 'Creator Bounties',
    'developer-docs' => 'Developer Docs',
    'status'         => 'System Status',
    'search'         => 'Search',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act = (string)($_POST['act'] ?? '');

        if ($act === 'save_site') {
            $values = $_POST['settings'] ?? [];
            if (is_array($values)) {
                $saved = 0;
                foreach (SEO_SITE_SETTINGS as $key => $label) {
                    if (!array_key_exists($key, $values)) {
                        continue;
                    }
                    $val = trim((string)$values[$key]);

                    // The OG image may be stored as a path or an absolute URL.
                    if ($key === 'seo_og_image' && $val !== '' && !preg_match('#^https?://#i', $val)) {
                        $val = '/' . ltrim($val, '/');
                    }
                    if ($key === 'site_url' && $val !== '') {
                        $val = rtrim($val, '/');
                        if (!preg_match('#^https?://#i', $val)) {
                            $val = 'https://' . $val;
                        }
                    }

                    $exists = db()->fetchOne("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
                    if ($exists) {
                        db()->update('site_settings', ['setting_value' => $val], 'setting_key = ?', [$key]);
                    } else {
                        db()->insert('site_settings', [
                            'setting_key'   => $key,
                            'setting_value' => $val,
                            'setting_group' => 'seo',
                        ]);
                    }
                    $saved++;
                }
                set_flash('success', "Saved {$saved} site-wide SEO setting(s).");
            }
        } elseif ($act === 'save_page') {
            $pageSlug = trim((string)($_POST['page_slug'] ?? ''));
            if ($pageSlug !== '' && preg_match('/^[a-z0-9_\-]{1,60}$/i', $pageSlug)) {
                $entries = [
                    'meta_title'       => trim((string)($_POST['meta_title'] ?? '')),
                    'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
                    'meta_keywords'    => trim((string)($_POST['meta_keywords'] ?? '')),
                    'og_image'         => trim((string)($_POST['og_image'] ?? '')),
                    'robots'           => trim((string)($_POST['robots'] ?? '')),
                ];

                foreach ($entries as $itemKey => $value) {
                    $existing = db()->fetchOne(
                        "SELECT id FROM dynamic_content
                         WHERE page_slug = ? AND section_key = 'seo' AND item_key = ?",
                        [$pageSlug, $itemKey]
                    );
                    if ($existing) {
                        if ($value === '') {
                            db()->delete('dynamic_content', 'id = ?', [(int)$existing['id']]);
                        } else {
                            db()->update('dynamic_content', ['content_value' => $value], 'id = ?', [(int)$existing['id']]);
                        }
                    } elseif ($value !== '') {
                        db()->insert('dynamic_content', [
                            'page_slug'     => $pageSlug,
                            'section_key'   => 'seo',
                            'item_key'      => $itemKey,
                            'content_value' => $value,
                        ]);
                    }
                }
                set_flash('success', "SEO overrides saved for \"{$pageSlug}\". They now apply to that page.");
            } else {
                set_flash('error', 'Invalid page slug. Use letters, numbers, dashes and underscores only.');
            }
        } elseif ($act === 'delete_page') {
            $slug = trim((string)($_POST['page_slug'] ?? ''));
            if ($slug !== '') {
                db()->query(
                    "DELETE FROM dynamic_content WHERE page_slug = ? AND section_key = 'seo'",
                    [$slug]
                );
                set_flash('success', "Cleared every SEO override for \"{$slug}\".");
            }
        }
    }
    redirect(url('admin/seo'));
}

// ── load state ───────────────────────────────────────────────────────────
$seoEntries = [];
try {
    $seoEntries = db()->fetchAll(
        "SELECT * FROM dynamic_content WHERE section_key = 'seo' ORDER BY page_slug, item_key"
    );
} catch (\Throwable $e) {
    $seoEntries = [];
}

$pages = [];
foreach ($seoEntries as $entry) {
    $pages[$entry['page_slug']][$entry['item_key']] = (string)($entry['content_value'] ?? '');
}

$editPage = isset($_GET['page']) ? (string)$_GET['page'] : '';
$editValues = $editPage !== '' ? ($pages[$editPage] ?? []) : [];

$siteValues = [];
foreach (array_keys(SEO_SITE_SETTINGS) as $key) {
    $siteValues[$key] = (string)get_setting($key, '');
}

// Preview resolution for the page currently being edited.
$preview = SeoMeta::resolve($editPage !== '' ? $editPage : 'index');

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3">

    <!-- ── Site-wide defaults ─────────────────────────────────────────── -->
    <div class="col-12">
        <div class="admin-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="text-white fw-bold mb-1"><i class="ph-bold ph-globe-hemisphere-west me-1"></i> Site-wide SEO defaults</h6>
                    <div class="text-muted" style="font-size:0.78rem;">
                        These apply to every page that has no page-specific override.
                    </div>
                </div>
                <a class="btn-admin btn-admin-outline" href="<?= htmlspecialchars(url('robots.txt')) ?>" target="_blank" rel="noopener">
                    <i class="ph-bold ph-file-code"></i> robots.txt
                </a>
                <a class="btn-admin btn-admin-outline ms-1" href="<?= htmlspecialchars(url('sitemap.xml')) ?>" target="_blank" rel="noopener">
                    <i class="ph-bold ph-sitemap"></i> sitemap.xml
                </a>
                <a class="btn-admin btn-admin-outline ms-1" href="<?= htmlspecialchars(url('llms.txt')) ?>" target="_blank" rel="noopener">
                    <i class="ph-bold ph-robot"></i> llms.txt
                </a>
            </div>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="save_site">

                <div class="row g-3">
                    <?php foreach (SEO_SITE_SETTINGS as $key => $label): ?>
                        <div class="col-12 col-lg-6">
                            <label class="form-label-admin" for="seo_<?= htmlspecialchars($key) ?>">
                                <?= htmlspecialchars($label) ?>
                                <span class="text-muted" style="font-weight:400;">(<?= htmlspecialchars($key) ?>)</span>
                            </label>
                            <?php if ($key === 'seo_default_description'): ?>
                                <textarea id="seo_<?= htmlspecialchars($key) ?>" name="settings[<?= htmlspecialchars($key) ?>]"
                                          class="form-control-admin" rows="2"
                                          placeholder="Shown when a page has no description of its own."><?= htmlspecialchars($siteValues[$key] ?? '') ?></textarea>
                            <?php else: ?>
                                <input type="text" id="seo_<?= htmlspecialchars($key) ?>" name="settings[<?= htmlspecialchars($key) ?>]"
                                       class="form-control-admin"
                                       value="<?= htmlspecialchars($siteValues[$key] ?? '') ?>"
                                       placeholder="<?= htmlspecialchars($key === 'seo_og_image' ? SeoMeta::DEFAULT_OG_IMAGE : '') ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-admin btn-admin-green mt-3">
                    <i class="ph-bold ph-floppy-disk"></i> Save site defaults
                </button>
            </form>
        </div>
    </div>

    <!-- ── Live preview ───────────────────────────────────────────────── -->
    <div class="col-12 col-lg-5">
        <div class="admin-card h-100">
            <h6 class="text-white fw-bold mb-3"><i class="ph-bold ph-eye me-1"></i> Live preview</h6>

            <div style="background:#fff;border-radius:12px;padding:14px;margin-bottom:14px;">
                <div style="color:#202124;font-size:0.78rem;margin-bottom:3px;">
                    <?= htmlspecialchars($preview['canonical']) ?>
                </div>
                <div style="color:#1a0dab;font-size:1.18rem;line-height:1.3;margin-bottom:3px;">
                    <?= htmlspecialchars($preview['title']) ?>
                </div>
                <div style="color:#4d5156;font-size:0.82rem;line-height:1.45;">
                    <?= htmlspecialchars($preview['description']) ?>
                </div>
            </div>

            <div style="background:#111827;border:1px solid var(--admin-border);border-radius:12px;overflow:hidden;">
                <img src="<?= htmlspecialchars($preview['og_image']) ?>" alt=""
                     style="width:100%;display:block;aspect-ratio:1200/630;object-fit:cover;"
                     onerror="this.style.display='none';">
                <div style="padding:12px 14px;">
                    <div style="color:#94a3b8;font-size:0.72rem;text-transform:uppercase;letter-spacing:.06em;">
                        <?= htmlspecialchars((string)get_setting('site_name', 'Adsbooster.net')) ?>
                    </div>
                    <div style="color:#e2e8f0;font-size:0.95rem;font-weight:600;margin:3px 0;">
                        <?= htmlspecialchars($preview['title']) ?>
                    </div>
                    <div style="color:#94a3b8;font-size:0.8rem;">
                        <?= htmlspecialchars($preview['description']) ?>
                    </div>
                </div>
            </div>

            <div class="text-muted mt-3" style="font-size:0.75rem;line-height:1.7;">
                <div><strong>Title:</strong> <?= strlen($preview['title']) ?> chars (aim 50-60)</div>
                <div><strong>Description:</strong> <?= strlen($preview['description']) ?> chars (aim 140-160)</div>
                <div><strong>Image:</strong> 1200 x 630 required for a full-width card</div>
                <div><strong>Robots:</strong> <?= htmlspecialchars($preview['robots']) ?></div>
            </div>
        </div>
    </div>

    <!-- ── Page overrides ─────────────────────────────────────────────── -->
    <div class="col-12 col-lg-7">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">
                <i class="ph-bold ph-file-text me-1"></i>
                <?= $editPage !== '' ? 'Edit SEO override: ' . htmlspecialchars($editPage) : 'Add SEO override' ?>
            </h6>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="save_page">

                <div class="mb-3">
                    <label class="form-label-admin" for="page_slug">Page</label>
                    <select id="page_slug" name="page_slug" class="form-control-admin" onchange="if(this.value)window.location='?page='+encodeURIComponent(this.value)">
                        <option value="">— choose a page —</option>
                        <?php foreach (SEO_PAGES as $slug => $label): ?>
                            <option value="<?= htmlspecialchars($slug) ?>" <?= $editPage === $slug ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?> (<?= htmlspecialchars($slug) ?>)<?= isset($pages[$slug]) ? ' — overridden' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-muted mt-1" style="font-size:0.72rem;">
                        The slug is the page file name without <code>.php</code> — e.g. <code>about</code> for <code>/about.php</code>.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label-admin" for="meta_title">Meta title</label>
                    <input type="text" id="meta_title" name="meta_title" class="form-control-admin"
                           value="<?= htmlspecialchars($editValues['meta_title'] ?? '') ?>"
                           placeholder="Leave empty to use the page's own title">
                </div>

                <div class="mb-3">
                    <label class="form-label-admin" for="meta_description">Meta description</label>
                    <textarea id="meta_description" name="meta_description" class="form-control-admin" rows="3"
                              placeholder="Leave empty to use the page's own description"><?= htmlspecialchars($editValues['meta_description'] ?? '') ?></textarea>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label-admin" for="meta_keywords">Meta keywords</label>
                        <input type="text" id="meta_keywords" name="meta_keywords" class="form-control-admin"
                               value="<?= htmlspecialchars($editValues['meta_keywords'] ?? '') ?>"
                               placeholder="comma, separated, terms">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label-admin" for="og_image">Social share image</label>
                        <input type="text" id="og_image" name="og_image" class="form-control-admin"
                               value="<?= htmlspecialchars($editValues['og_image'] ?? '') ?>"
                               placeholder="<?= htmlspecialchars(SeoMeta::DEFAULT_OG_IMAGE) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label-admin" for="robots">Robots directive</label>
                        <input type="text" id="robots" name="robots" class="form-control-admin"
                               value="<?= htmlspecialchars($editValues['robots'] ?? '') ?>"
                               placeholder="index, follow, max-snippet:-1, max-image-preview:large">
                    </div>
                </div>

                <button type="submit" class="btn-admin btn-admin-green mt-3">
                    <i class="ph-bold ph-floppy-disk"></i> Save page override
                </button>
                <?php if ($editPage !== ''): ?>
                    <a href="<?= htmlspecialchars(url('admin/seo')) ?>" class="btn-admin btn-admin-outline ms-2">Clear form</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ── Saved overrides ────────────────────────────────────────────── -->
    <div class="col-12">
        <div class="admin-card">
            <h6 class="text-white fw-bold mb-3">
                <i class="ph-bold ph-list-checks me-1"></i> Saved page overrides (<?= count($pages) ?>)
            </h6>

            <?php if (empty($pages)): ?>
                <div class="text-muted" style="font-size:0.86rem;">
                    No page overrides yet. The site-wide defaults above are in use.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark" style="font-size:0.84rem;">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Image</th>
                                <th style="width:150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pages as $slug => $items): ?>
                            <tr>
                                <td>
                                    <div class="text-white fw-medium"><?= htmlspecialchars($slug) ?></div>
                                    <div class="text-muted" style="font-size:0.72rem;">
                                        <?= htmlspecialchars(SEO_PAGES[$slug] ?? 'custom page') ?>
                                    </div>
                                </td>
                                <td style="max-width:220px;"><?= htmlspecialchars($items['meta_title'] ?? '—') ?></td>
                                <td style="max-width:280px;"><?= htmlspecialchars(mb_substr($items['meta_description'] ?? '—', 0, 90)) ?></td>
                                <td><?= !empty($items['og_image']) ? htmlspecialchars(mb_substr($items['og_image'], 0, 40)) : '—' ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn-admin btn-admin-blue" href="?page=<?= urlencode($slug) ?>">
                                            <i class="ph-bold ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Clear all overrides for <?= htmlspecialchars(addslashes($slug)) ?>?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_page">
                                            <input type="hidden" name="page_slug" value="<?= htmlspecialchars($slug) ?>">
                                            <button class="btn-admin btn-admin-red"><i class="ph-bold ph-trash"></i></button>
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
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
