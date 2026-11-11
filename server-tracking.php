<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/server_tracking.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access Server-Side Tracking.');
    redirect(url('login'));
}

$pageTitle = 'Server-Side Conversion Tracking (Meta CAPI & TikTok via Zernio) — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

// Handle Pixel Creation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('server-tracking'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_pixel') {
        ServerTrackingEngine::createPixel($userId, $_POST);
        set_flash('success', 'Server-Side Pixel generated successfully!');
        redirect(url('server-tracking'));
    }

    if ($action === 'test_event') {
        $token = $_POST['pixel_token'] ?? '';
        $evt   = $_POST['event_name'] ?? 'Purchase';
        $res   = ServerTrackingEngine::trackEvent($token, $evt, [
            'email' => $user['email'] ?? '',
            'phone' => $user['phone'] ?? '',
        ], [
            'value'    => 1.00,
            'currency' => 'USD',
            'order_id' => 'TEST-' . bin2hex(random_bytes(4)),
        ]);

        if (!empty($res['error'])) {
            set_flash('error', "Test event '{$evt}' failed: " . $res['error']);
            redirect(url('server-tracking'));
        }

        // Report the honest queue state — dispatch happens out-of-band and
        // per-destination outcomes appear in the ingestion feed automatically.
        $parts = ['Meta: ' . ($res['meta_status'] ?? 'queued'), 'TikTok: ' . ($res['tiktok_status'] ?? 'queued')];
        $allOk = !empty($res['success']);
        set_flash(
            $allOk ? 'success' : 'error',
            "Test event '{$evt}' ({$res['event_id']}) queued for Zernio delivery (job #" . ($res['job_id'] ?? 0) . ") — " . implode(' · ', $parts) . ". Outcomes appear in the ingestion feed once the worker confirms them."
        );
        redirect(url('server-tracking'));
    }
}

// Fetch user pixels and events
$pixels = db()->fetchAll("SELECT * FROM server_tracking_pixels WHERE user_id = ? ORDER BY id DESC", [$userId]);
$activePixel = !empty($pixels) ? $pixels[0] : null;
$events = db()->fetchAll("SELECT * FROM server_tracking_events WHERE user_id = ? ORDER BY id DESC LIMIT 15", [$userId]);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-shield-check me-1"></i> iOS 14.5+ Bypass
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-broadcast me-1"></i> 100% Attribution Accuracy
          </span>
        </div>
        <h2 class="h3 fw-bold mb-1">Server-Side Tracking Bridge (Meta CAPI & TikTok)</h2>
        <p class="text-secondary mb-0">Bypass browser ad-blockers and iOS privacy restrictions. Conversions route server-to-server through the <span class="text-info fw-bold">Zernio Ads Engine</span> (which holds all platform credentials) with SHA-256 hashed matching — queued, retried and honestly logged.</p>
      </div>
      <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modal-new-pixel">
        <i class="ph ph-plus-circle me-1"></i> Create Tracking Pixel
      </button>
    </div>

    <!-- Active Pixel Info & Script Snippet -->
    <?php if ($activePixel): ?>
    <div class="row g-4 mb-4">
      <div class="col-lg-6">
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm h-100">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-white"><?= e($activePixel['name']) ?></h5>
            <span class="badge bg-success rounded-pill">Active & Receiving Events</span>
          </div>
          
          <div class="mb-3">
            <label class="form-label small text-secondary">Your Unique Pixel Token</label>
            <div class="input-group">
              <input type="text" class="form-control font-monospace text-primary bg-dark border-secondary border-opacity-25" value="<?= e($activePixel['pixel_token']) ?>" readonly id="inp-token">
              <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= e($activePixel['pixel_token']) ?>'); alert('Token copied!');">
                <i class="ph ph-copy"></i>
              </button>
            </div>
          </div>

          <div class="d-flex gap-2">
            <form method="POST" class="flex-grow-1">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="test_event">
              <input type="hidden" name="pixel_token" value="<?= e($activePixel['pixel_token']) ?>">
              <input type="hidden" name="event_name" value="PageView">
              <button type="submit" class="btn btn-outline-primary w-100 btn-sm">
                <i class="ph ph-paper-plane-tilt me-1"></i> Test PageView Event
              </button>
            </form>
            <form method="POST" class="flex-grow-1">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="test_event">
              <input type="hidden" name="pixel_token" value="<?= e($activePixel['pixel_token']) ?>">
              <input type="hidden" name="event_name" value="Purchase">
              <button type="submit" class="btn btn-primary w-100 btn-sm fw-bold">
                <i class="ph ph-shopping-bag me-1"></i> Test $99 Purchase CAPI
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Website Tag Snippet -->
      <div class="col-lg-6">
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm h-100">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0 text-white">Embed Script (Add to &lt;head&gt; of your site)</h6>
            <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('code-snippet').innerText); alert('Code snippet copied!');">
              <i class="ph ph-copy me-1"></i> Copy
            </button>
          </div>
          <pre class="bg-dark p-3 rounded-3 text-info small mb-0 border border-secondary border-opacity-25" id="code-snippet" style="max-height: 180px; overflow-y: auto;"><?= htmlspecialchars(ServerTrackingEngine::getTrackingSnippet($activePixel['pixel_token'])) ?></pre>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Real-Time Server-Side Event Log -->
    <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
          <i class="ph ph-activity text-primary"></i> Live Server-Side Ingestion Feed
        </h5>
        <span class="badge bg-dark border border-secondary border-opacity-25 text-secondary">Last 15 Events</span>
      </div>

      <?php if (empty($events)): ?>
      <div class="text-center py-4 text-secondary small">
        <i class="ph ph-broadcast fs-2 text-secondary mb-2 d-block"></i>
        No conversion events tracked yet. Trigger a test event or install the snippet.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle small mb-0">
          <thead>
            <tr class="text-secondary">
              <th>Event ID</th>
              <th>Event Name</th>
              <th>Source URL</th>
              <th>Meta CAPI</th>
              <th>TikTok Events</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Honest, gateway-derived status badges — every state the async
            // delivery flow can record gets an explicit reading. No fake
            // "200 Sent" for unconfirmed rows.
            $statusBadge = static function (?string $s): string {
                $s = trim((string)$s) ?: 'unknown';
                if (str_starts_with($s, 'gateway_dispatched')) {
                    return '<span class="badge bg-success-subtle text-success">✓ Gateway Sent</span>';
                }
                if ($s === 'simulated') {
                    return '<span class="badge bg-warning-subtle text-warning">◇ Simulated Hold</span>';
                }
                if (str_starts_with($s, 'error')) {
                    return '<span class="badge bg-danger-subtle text-danger" title="' . e($s) . '">✗ Error</span>';
                }
                if ($s === 'queued' || $s === 'pending') {
                    return '<span class="badge bg-info-subtle text-info">⏳ Queued</span>';
                }
                if (str_starts_with($s, 'skipped')) {
                    return '<span class="badge bg-dark border border-secondary text-secondary" title="' . e($s) . '">– Skipped</span>';
                }
                return '<span class="badge bg-secondary text-light" title="' . e($s) . '">' . e(mb_substr($s, 0, 22)) . '</span>';
            };
            foreach ($events as $ev): ?>
            <tr>
              <td class="font-monospace text-primary"><?= e($ev['event_id']) ?></td>
              <td><span class="badge bg-primary-subtle text-primary fw-bold"><?= e($ev['event_name']) ?></span></td>
              <td class="text-truncate text-secondary" style="max-width: 250px;"><?= e($ev['event_source_url']) ?></td>
              <td><?= $statusBadge($ev['meta_capi_status'] ?? null) ?></td>
              <td><?= $statusBadge($ev['tiktok_events_status'] ?? null) ?></td>
              <td class="text-secondary"><?= dt($ev['created_at'], 'M d, H:i:s') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Modal: New Pixel -->
<div class="modal fade" id="modal-new-pixel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-surface-card text-white border border-secondary border-opacity-25 rounded-4">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_pixel">
        <div class="modal-header border-secondary border-opacity-25">
          <h5 class="modal-title fw-bold">Create CAPI Tracking Pixel</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small text-secondary">Pixel Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. My E-Commerce Store Pixel" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Meta Pixel ID (Optional — Zernio routing label)</label>
            <input type="text" name="meta_pixel_id" class="form-control" placeholder="e.g. 182938472910">
            <div class="form-text text-secondary">Router label only: the Zernio Ads Engine holds your Meta CAPI credential and delivers events to this pixel. No platform token is stored here.</div>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">TikTok Pixel ID (Optional — Zernio routing label)</label>
            <input type="text" name="tiktok_pixel_id" class="form-control" placeholder="e.g. C123456789">
            <div class="form-text text-secondary">Router label only: the Zernio Ads Engine holds your TikTok Events credential and delivers events to this pixel. No platform token is stored here.</div>
          </div>
        </div>
        <div class="modal-footer border-secondary border-opacity-25">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Generate Pixel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
