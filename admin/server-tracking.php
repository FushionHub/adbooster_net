<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/server_tracking.php';

$pageTitle = 'Server-Side Tracking & CAPI Hub';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $pixelId = (int)($_POST['pixel_id'] ?? 0);

        if ($act === 'toggle_pixel' && $pixelId > 0) {
            $px = db()->fetchOne("SELECT is_active FROM server_tracking_pixels WHERE id=?", [$pixelId]);
            if ($px) {
                $newStatus = $px['is_active'] ? 0 : 1;
                db()->update('server_tracking_pixels', ['is_active' => $newStatus], 'id=?', [$pixelId]);
                set_flash('success', "Pixel #{$pixelId} " . ($newStatus ? 'enabled' : 'disabled') . ".");
            }
        } elseif ($act === 'delete_pixel' && $pixelId > 0) {
            db()->delete('server_tracking_pixels', 'id=?', [$pixelId]);
            set_flash('success', "Pixel #{$pixelId} deleted permanently.");
        } elseif ($act === 'simulate_test_event') {
            $testPixel = db()->fetchOne("SELECT id, user_id FROM server_tracking_pixels WHERE is_active=1 LIMIT 1");
            if ($testPixel) {
                db()->insert('server_tracking_events', [
                    'pixel_id' => $testPixel['id'],
                    'user_id' => $testPixel['user_id'],
                    'event_name' => 'Purchase',
                    'event_id' => 'sim_' . bin2hex(random_bytes(8)),
                    'event_source_url' => BASE_URL . '/checkout/success',
                    'user_data_hashed' => json_encode(['em' => [hash('sha256', 'admin-test@adsbooster.net')]]),
                    'custom_data' => json_encode(['value' => 99.00, 'currency' => 'USD']),
                    'meta_capi_status' => 'delivered',
                    'tiktok_events_status' => 'delivered',
                ]);
                set_flash('success', 'Test simulated Purchase event dispatched to server tracking stream.');
            } else {
                set_flash('error', 'No active tracking pixel found to simulate event against.');
            }
        }
    }
    redirect(url('admin/server-tracking'));
}

// KPI Metrics
$totalPixels  = db()->count('server_tracking_pixels');
$activePixels = db()->count('server_tracking_pixels', 'is_active=1');
$totalEvents  = db()->count('server_tracking_events');
$eventsToday  = db()->count('server_tracking_events', 'DATE(created_at) = CURDATE()');

$tab = $_GET['tab'] ?? 'events';
$filterEvent = $_GET['event'] ?? '';

// Pixels list
$pixels = db()->fetchAll(
    "SELECT p.*, u.full_name, u.email 
     FROM server_tracking_pixels p
     LEFT JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC LIMIT 50"
);

// Events stream
$eventWhere = '1=1';
$eventParams = [];
if ($filterEvent) {
    $eventWhere .= ' AND e.event_name = ?';
    $eventParams[] = $filterEvent;
}

$events = db()->fetchAll(
    "SELECT e.*, p.name as pixel_name, u.full_name 
     FROM server_tracking_events e
     LEFT JOIN server_tracking_pixels p ON p.id = e.pixel_id
     LEFT JOIN users u ON u.id = e.user_id
     WHERE {$eventWhere}
     ORDER BY e.created_at DESC LIMIT 60",
    $eventParams
);

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-crosshair"></i></div>
            <div>
                <div class="card-title">Tracking Pixels</div>
                <div class="card-value"><?= number_format($totalPixels) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon green"><i class="ph-bold ph-shield-check"></i></div>
            <div>
                <div class="card-title">Active Pixels</div>
                <div class="card-value"><?= number_format($activePixels) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon purple"><i class="ph-bold ph-broadcast"></i></div>
            <div>
                <div class="card-title">All-Time CAPI Events</div>
                <div class="card-value"><?= number_format($totalEvents) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-activity"></i></div>
            <div>
                <div class="card-title">Events Today</div>
                <div class="card-value"><?= number_format($eventsToday) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex gap-2">
        <a href="?tab=events" class="btn-admin <?= $tab === 'events' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-pulse"></i> Realtime Event Stream (<?= $totalEvents ?>)
        </a>
        <a href="?tab=pixels" class="btn-admin <?= $tab === 'pixels' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-sliders-horizontal"></i> Registered Pixels (<?= $totalPixels ?>)
        </a>
    </div>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="simulate_test_event">
        <button type="submit" class="btn-admin btn-admin-blue">
            <i class="ph-bold ph-paper-plane-tilt"></i> Fire Test Server Event
        </button>
    </form>
</div>

<?php if ($tab === 'events'): ?>
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h6 class="text-white fw-bold mb-0">Live Server-Side Event Feed</h6>
            <div class="d-flex gap-2">
                <a href="?tab=events" class="btn-admin btn-admin-outline <?= !$filterEvent ? 'active' : '' ?>">All</a>
                <a href="?tab=events&event=Purchase" class="btn-admin btn-admin-outline <?= $filterEvent === 'Purchase' ? 'active' : '' ?>">Purchase</a>
                <a href="?tab=events&event=Lead" class="btn-admin btn-admin-outline <?= $filterEvent === 'Lead' ? 'active' : '' ?>">Lead</a>
                <a href="?tab=events&event=AddToCart" class="btn-admin btn-admin-outline <?= $filterEvent === 'AddToCart' ? 'active' : '' ?>">AddToCart</a>
                <a href="?tab=events&event=PageView" class="btn-admin btn-admin-outline <?= $filterEvent === 'PageView' ? 'active' : '' ?>">PageView</a>
            </div>
        </div>

        <?php if (empty($events)): ?>
            <p class="text-muted py-4 text-center">No server tracking events recorded yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Pixel / User</th>
                            <th>Event ID</th>
                            <th>Meta CAPI</th>
                            <th>TikTok Events</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark border border-success text-success fw-bold">
                                        <?= htmlspecialchars($e['event_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-white small fw-semibold"><?= htmlspecialchars($e['pixel_name'] ?? 'Default Pixel') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($e['full_name'] ?? 'Advertiser') ?></div>
                                </td>
                                <td>
                                    <code class="small text-muted"><?= htmlspecialchars(substr($e['event_id'] ?? '', 0, 16)) ?>...</code>
                                </td>
                                <td>
                                    <?php $metaOk = ($e['meta_capi_status'] ?? '') === 'delivered'; ?>
                                    <span class="badge-status <?= $metaOk ? 'active' : 'pending' ?>">
                                        <i class="ph-bold <?= $metaOk ? 'ph-check' : 'ph-clock' ?>"></i>
                                        <?= htmlspecialchars($e['meta_capi_status'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $ttOk = ($e['tiktok_events_status'] ?? '') === 'delivered'; ?>
                                    <span class="badge-status <?= $ttOk ? 'active' : 'pending' ?>">
                                        <i class="ph-bold <?= $ttOk ? 'ph-check' : 'ph-clock' ?>"></i>
                                        <?= htmlspecialchars($e['tiktok_events_status'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M j, H:i:s', strtotime($e['created_at'])) ?>
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
        <h6 class="text-white fw-bold mb-3">Registered Tracking Pixels</h6>
        <?php if (empty($pixels)): ?>
            <p class="text-muted py-4 text-center">No server tracking pixels configured.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Pixel Name</th>
                            <th>Advertiser</th>
                            <th>Meta Pixel ID</th>
                            <th>TikTok Pixel ID</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pixels as $px): ?>
                            <tr>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($px['name'] ?? 'Pixel') ?></div>
                                    <div class="text-muted small font-monospace"><?= htmlspecialchars($px['pixel_token'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($px['full_name'] ?? 'User') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($px['email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="text-info font-monospace small"><?= htmlspecialchars($px['meta_pixel_id'] ?: 'Not linked') ?></span>
                                </td>
                                <td>
                                    <span class="text-warning font-monospace small"><?= htmlspecialchars($px['tiktok_pixel_id'] ?: 'Not linked') ?></span>
                                </td>
                                <td class="text-muted small">
                                    <?= htmlspecialchars($px['domain_verified'] ?: 'Universal') ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $px['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $px['is_active'] ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="toggle_pixel">
                                            <input type="hidden" name="pixel_id" value="<?= $px['id'] ?>">
                                            <button type="submit" class="btn-admin <?= $px['is_active'] ? 'btn-admin-yellow' : 'btn-admin-green' ?>" title="Toggle status">
                                                <i class="ph-bold <?= $px['is_active'] ? 'ph-pause' : 'ph-play' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete tracking pixel permanently?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_pixel">
                                            <input type="hidden" name="pixel_id" value="<?= $px['id'] ?>">
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
