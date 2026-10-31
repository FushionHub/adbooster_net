<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Portfolios & Digital vCards';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $portId = (int)($_POST['portfolio_id'] ?? 0);
        $vcardId = (int)($_POST['vcard_id'] ?? 0);

        if ($act === 'toggle_portfolio' && $portId > 0) {
            $p = db()->fetchOne("SELECT is_public FROM user_portfolios WHERE id=?", [$portId]);
            if ($p) {
                $newVal = $p['is_public'] ? 0 : 1;
                db()->update('user_portfolios', ['is_public' => $newVal], 'id=?', [$portId]);
                set_flash('success', "Portfolio #{$portId} visibility toggled to " . ($newVal ? 'Public' : 'Private') . ".");
            }
        } elseif ($act === 'delete_portfolio' && $portId > 0) {
            db()->delete('user_portfolios', 'id=?', [$portId]);
            set_flash('success', "Portfolio #{$portId} deleted permanently.");
        } elseif ($act === 'toggle_vcard' && $vcardId > 0) {
            $v = db()->fetchOne("SELECT is_public FROM user_vcards WHERE id=?", [$vcardId]);
            if ($v) {
                $newVal = $v['is_public'] ? 0 : 1;
                db()->update('user_vcards', ['is_public' => $newVal], 'id=?', [$vcardId]);
                set_flash('success', "vCard #{$vcardId} visibility toggled to " . ($newVal ? 'Public' : 'Private') . ".");
            }
        } elseif ($act === 'delete_vcard' && $vcardId > 0) {
            db()->delete('user_vcards', 'id=?', [$vcardId]);
            set_flash('success', "vCard #{$vcardId} deleted.");
        }
    }
    redirect(url('admin/portfolios-vcards'));
}

// KPI Metrics
$totalPortfolios = db()->count('user_portfolios');
$totalVcards     = db()->count('user_vcards');
$portViews       = (int)(db()->fetchOne("SELECT COALESCE(SUM(views_count), 0) as total FROM user_portfolios")['total'] ?? 0);
$vcardViews      = (int)(db()->fetchOne("SELECT COALESCE(SUM(views_count), 0) as total FROM user_vcards")['total'] ?? 0);
$totalViews      = $portViews + $vcardViews;
$publicCount     = db()->count('user_portfolios', 'is_public=1') + db()->count('user_vcards', 'is_public=1');

$tab = $_GET['tab'] ?? 'portfolios';

// Portfolios query
$portfolios = db()->fetchAll(
    "SELECT p.*, u.full_name, u.email 
     FROM user_portfolios p
     LEFT JOIN users u ON u.id = p.user_id
     ORDER BY p.views_count DESC, p.created_at DESC LIMIT 50"
);

// vCards query
$vcards = db()->fetchAll(
    "SELECT v.*, u.full_name, u.email 
     FROM user_vcards v
     LEFT JOIN users u ON u.id = v.user_id
     ORDER BY v.views_count DESC, v.created_at DESC LIMIT 50"
);

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-identification-card"></i></div>
            <div>
                <div class="card-title">User Portfolios</div>
                <div class="card-value"><?= number_format($totalPortfolios) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon green"><i class="ph-bold ph-address-book"></i></div>
            <div>
                <div class="card-title">Digital vCards</div>
                <div class="card-value"><?= number_format($totalVcards) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-eye"></i></div>
            <div>
                <div class="card-title">Total Showcase Views</div>
                <div class="card-value"><?= number_format($totalViews) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon purple"><i class="ph-bold ph-globe"></i></div>
            <div>
                <div class="card-title">Public Live Profiles</div>
                <div class="card-value"><?= number_format($publicCount) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex gap-2">
        <a href="?tab=portfolios" class="btn-admin <?= $tab === 'portfolios' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-user-circle"></i> Portfolios (<?= $totalPortfolios ?>)
        </a>
        <a href="?tab=vcards" class="btn-admin <?= $tab === 'vcards' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-address-book"></i> Digital vCards (<?= $totalVcards ?>)
        </a>
    </div>
</div>

<?php if ($tab === 'portfolios'): ?>
    <div class="admin-card">
        <h6 class="text-white fw-bold mb-3">User Portfolio Showcase Directory</h6>
        <?php if (empty($portfolios)): ?>
            <p class="text-muted py-4 text-center">No user portfolios created yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Display Name & Slug</th>
                            <th>User Owner</th>
                            <th>Headline & Title</th>
                            <th>Template</th>
                            <th>Views</th>
                            <th>Visibility</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($portfolios as $p): ?>
                            <tr>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($p['display_name']) ?></div>
                                    <div class="text-muted small font-monospace">@<?= htmlspecialchars($p['username']) ?></div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($p['full_name'] ?? 'User') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($p['email'] ?? '') ?></div>
                                </td>
                                <td style="max-width: 250px;">
                                    <div class="text-white small text-truncate" title="<?= htmlspecialchars($p['headline'] ?? '') ?>">
                                        <?= htmlspecialchars($p['headline'] ?? 'Creative Portfolio') ?>
                                    </div>
                                    <div class="text-muted small text-truncate">
                                        <?= htmlspecialchars($p['company'] ?? '') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-info small">
                                        <?= ucwords(str_replace('_', ' ', $p['template_style'] ?? 'dark_luxury')) ?>
                                    </span>
                                </td>
                                <td class="text-white fw-bold small">
                                    <i class="ph-bold ph-eye text-muted"></i> <?= number_format((int)$p['views_count']) ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $p['is_public'] ? 'active' : 'inactive' ?>">
                                        <?= $p['is_public'] ? 'Public' : 'Private' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="<?= url('portfolio', ['u' => $p['username']]) ?>" target="_blank" class="btn-admin btn-admin-blue" title="View Portfolio Live">
                                            <i class="ph-bold ph-arrow-square-out"></i>
                                        </a>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="toggle_portfolio">
                                            <input type="hidden" name="portfolio_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn-admin <?= $p['is_public'] ? 'btn-admin-yellow' : 'btn-admin-green' ?>" title="Toggle Public/Private">
                                                <i class="ph-bold <?= $p['is_public'] ? 'ph-eye-slash' : 'ph-eye' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this portfolio permanently?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_portfolio">
                                            <input type="hidden" name="portfolio_id" value="<?= $p['id'] ?>">
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

<?php else: ?>
    <div class="admin-card">
        <h6 class="text-white fw-bold mb-3">User Digital vCards</h6>
        <?php if (empty($vcards)): ?>
            <p class="text-muted py-4 text-center">No digital vCards created yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Card Owner / Handle</th>
                            <th>Company & Title</th>
                            <th>Contact Phone</th>
                            <th>Template</th>
                            <th>Views</th>
                            <th>Visibility</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vcards as $v): ?>
                            <tr>
                                <td>
                                    <div class="text-white fw-semibold">@<?= htmlspecialchars($v['username']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($v['full_name'] ?? 'User') ?></div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($v['company'] ?? 'N/A') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($v['job_title'] ?? '') ?></div>
                                </td>
                                <td class="text-muted small font-monospace">
                                    <?= htmlspecialchars($v['phone'] ?: $v['whatsapp'] ?: 'N/A') ?>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-light small">
                                        <?= htmlspecialchars($v['template'] ?? 'modern') ?>
                                    </span>
                                </td>
                                <td class="text-white fw-bold small">
                                    <i class="ph-bold ph-eye text-muted"></i> <?= number_format((int)$v['views_count']) ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $v['is_public'] ? 'active' : 'inactive' ?>">
                                        <?= $v['is_public'] ? 'Public' : 'Private' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="<?= url('vcard', ['u' => $v['username']]) ?>" target="_blank" class="btn-admin btn-admin-blue" title="View Digital vCard">
                                            <i class="ph-bold ph-arrow-square-out"></i>
                                        </a>
                                        <a href="<?= url('vcard-download', ['u' => $v['username']]) ?>" class="btn-admin btn-admin-outline" title="Download .vcf File">
                                            <i class="ph-bold ph-download-simple"></i>
                                        </a>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="toggle_vcard">
                                            <input type="hidden" name="vcard_id" value="<?= $v['id'] ?>">
                                            <button type="submit" class="btn-admin <?= $v['is_public'] ? 'btn-admin-yellow' : 'btn-admin-green' ?>" title="Toggle Public/Private">
                                                <i class="ph-bold <?= $v['is_public'] ? 'ph-eye-slash' : 'ph-eye' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this digital vCard?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_vcard">
                                            <input type="hidden" name="vcard_id" value="<?= $v['id'] ?>">
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
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
