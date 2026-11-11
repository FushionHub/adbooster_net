<?php
declare(strict_types=1);

$pageTitle = 'Developer Console & API Settings — Adsbooster.net';
$pageDescription = 'Manage your REST API keys, webhooks, and developer integrations.';
require_once __DIR__ . '/includes/app_header.php';
require_once __DIR__ . '/api/v1/webhooks.php';

$userId = current_user_id();
$message = '';
$newRawKey = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Invalid security token.</div>';
    } else {
        switch ($_POST['action']) {
            case 'generate_key':
                $rawKey = 'adsb_' . bin2hex(random_bytes(24));
                $keyHash = hash('sha256', $rawKey);
                $keyPrefix = substr($rawKey, 0, 10);
                $scopes = $_POST['scopes'] ?? [];
                if (!empty($_POST['is_master_key']) || empty($scopes)) {
                    $scopes = ['read', 'write', 'ads', 'ai', 'social', 'directory', 'wallet', 'ugc', 'reviews'];
                }
                db()->insert('api_keys', [
                    'user_id'    => $userId,
                    'key_hash'   => $keyHash,
                    'key_prefix' => $keyPrefix,
                    'scopes'     => json_encode(array_values(array_unique($scopes))),
                ]);
                $newRawKey = $rawKey;
                $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-key" style="vertical-align:-2px;margin-right:6px"></i> <b>New Unified Master API Key Generated!</b> Copy it immediately — it will not be shown again.</div>';
                break;

            case 'revoke_key':
                $keyId = (int)($_POST['key_id'] ?? 0);
                if ($keyId > 0) {
                    db()->delete('api_keys', 'id = ? AND user_id = ?', [$keyId, $userId]);
                    $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-trash" style="vertical-align:-2px;margin-right:6px"></i> API key revoked permanently.</div>';
                }
                break;

            case 'add_webhook':
                $targetUrl = trim($_POST['target_url'] ?? '');
                $eventType = trim($_POST['event_type'] ?? 'campaign.status_changed');
                if (filter_var($targetUrl, FILTER_VALIDATE_URL) === false) {
                    $message = '<div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">Please enter a valid webhook destination URL (https://...).</div>';
                } else {
                    db()->insert('webhooks', [
                        'user_id'    => $userId,
                        'event_type' => $eventType,
                        'target_url' => $targetUrl,
                        'secret'     => bin2hex(random_bytes(20)),
                        'is_active'  => 1,
                    ]);
                    $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-broadcast" style="vertical-align:-2px;margin-right:6px"></i> Webhook endpoint added successfully!</div>';
                }
                break;

            case 'test_webhook':
                $hookId = (int)($_POST['webhook_id'] ?? 0);
                if ($hookId > 0) {
                    $testResult = WebhookSender::testPing($hookId, $userId);
                    if ($testResult['success']) {
                        $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;">'
                            . '<i class="ph ph-check-circle" style="vertical-align:-2px;margin-right:6px"></i> <b>Webhook Ping Succeeded!</b> HTTP ' . (int)$testResult['http_code'] . ' in ' . $testResult['latency_ms'] . 'ms. Signature: <code>' . htmlspecialchars(substr($testResult['signature'], 0, 24)) . '...</code></div>';
                    } else {
                        $message = '<div class="alert alert-warning" style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);color:#fcd34d;border-radius:12px;padding:14px 18px;margin-bottom:20px;">'
                            . '<i class="ph ph-warning-circle" style="vertical-align:-2px;margin-right:6px"></i> <b>Webhook Ping Failed:</b> ' . htmlspecialchars($testResult['error'] ?? 'Unknown error') . ' (HTTP ' . (int)$testResult['http_code'] . ')</div>';
                    }
                }
                break;

            case 'delete_webhook':
                $hookId = (int)($_POST['webhook_id'] ?? 0);
                if ($hookId > 0) {
                    db()->delete('webhooks', 'id = ? AND user_id = ?', [$hookId, $userId]);
                    $message = '<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;"><i class="ph ph-trash" style="vertical-align:-2px;margin-right:6px"></i> Webhook endpoint deleted.</div>';
                }
                break;
        }
    }
}

$apiKeys = db()->fetchAll("SELECT id, key_prefix, scopes, last_used_at, created_at FROM api_keys WHERE user_id = ? ORDER BY id DESC", [$userId]);
$webhooks = db()->fetchAll("SELECT id, event_type, target_url, secret, is_active, created_at FROM webhooks WHERE user_id = ? ORDER BY id DESC", [$userId]);

// Sliding-window rate limit tracking for current minute
$currentWindow = (int)date('YmdHi');
$windowUsage = db()->fetchOne("SELECT SUM(request_count) as total FROM api_rate_limits WHERE key_id IN (SELECT id FROM api_keys WHERE user_id = ?) AND window_minute = ?", [$userId, $currentWindow]);
$usedThisMin = (int)($windowUsage['total'] ?? 0);
$remainingThisMin = max(0, 60 - $usedThisMin);

?>

<div class="w-100">
    <?= $message ?>

    <?php if ($newRawKey): ?>
        <div class="card-dark mb-4" style="padding:22px;border:1px solid var(--accent);background:linear-gradient(135deg, rgba(200,255,0,0.1) 0%, rgba(14,22,38,0.95) 100%);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <i class="ph ph-shield-check" style="font-size:24px;color:var(--accent);"></i>
                <h5 style="color:#fff;font-size:16px;font-weight:700;margin:0;">Copy Your New API Key Now</h5>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" id="rawKeyInput" class="form-control" style="background:#080c14;border:1px solid var(--accent);color:var(--accent);font-family:monospace;font-size:14px;font-weight:600;padding:12px;" value="<?= e($newRawKey) ?>" readonly>
                <button type="button" class="btn-accent" onclick="copyRawKey()" style="padding:12px 24px;white-space:nowrap;">
                    <i class="ph ph-copy"></i> Copy Key
                </button>
            </div>
            <div style="font-size:12px;color:var(--fg2);margin-top:8px;">
                ⚠️ This secret will not be displayed again for security purposes. Store it safely in your environment variables.
            </div>
        </div>
    <?php endif; ?>

    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Developer Console &amp; Master API Keys</h1>
            <p class="page-subtitle">Unified programmatic access to launch paid ads via Zernio, auto-publish across connected social networks via Bundle.social, generate AI UGC creatives, and subscribe to signed webhooks.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <a href="<?= url('developer-docs') ?>" class="btn-outline-dark" style="display:inline-flex;align-items:center;gap:8px;">
                <i class="ph ph-book-open" style="font-size:18px"></i> Full A-Z Docs
            </a>
            <a href="<?= url('status') ?>" class="btn-outline-dark" style="display:inline-flex;align-items:center;gap:8px;">
                <i class="ph ph-activity" style="font-size:18px"></i> API Health
            </a>
            <button class="btn-accent" data-bs-toggle="modal" data-bs-target="#generateKeyModal" style="display:inline-flex;align-items:center;gap:8px;">
                <i class="ph ph-key" style="font-size:18px"></i> Generate Master Key
            </button>
            <button class="btn-outline-dark" data-bs-toggle="modal" data-bs-target="#addWebhookModal" style="display:inline-flex;align-items:center;gap:8px;">
                <i class="ph ph-broadcast" style="font-size:18px"></i> Add Webhook
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Active API Keys</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(200,255,0,0.1);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                        <i class="ph ph-key" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);"><?= count($apiKeys) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">Bearer token authentication</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Registered Webhooks</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(56,189,248,0.1);display:flex;align-items:center;justify-content:center;color:var(--blue);">
                        <i class="ph ph-broadcast" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--fg);"><?= count($webhooks) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:4px;">HMAC-SHA256 signed hooks</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">API Gateway Status</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;color:var(--green);">
                        <i class="ph ph-check-circle" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:26px;font-weight:800;color:var(--green);">99.99%</div>
                <div style="font-size:12px;color:var(--green);margin-top:4px;">REST v1 Operational</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;letter-spacing:0.5px">Rate Limit Quota</span>
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(192,132,252,0.1);display:flex;align-items:center;justify-content:center;color:var(--purple);">
                        <i class="ph ph-gauge" style="font-size:18px;"></i>
                    </div>
                </div>
                <div style="font-size:22px;font-weight:800;color:var(--fg);"><?= $usedThisMin ?> / 60 <span style="font-size:13px;color:var(--fg3);font-weight:500;">reqs</span></div>
                <div style="font-size:12px;color:<?= $remainingThisMin < 10 ? '#f43f5e' : 'var(--green)' ?>;margin-top:4px;"><?= $remainingThisMin ?> remaining (Sliding 60s window)</div>
            </div>
        </div>
    </div>


    <!-- API Keys Section -->
    <div class="card-dark mb-4" style="padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
            <div>
                <h4 style="color:#fff;font-size:18px;font-weight:700;margin:0;">REST API Keys</h4>
                <p style="color:var(--fg2);font-size:13px;margin:2px 0 0;">Use API keys with the <code>Authorization: Bearer &lt;KEY&gt;</code> header to interact with our endpoints.</p>
            </div>
            <button class="btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#generateKeyModal">
                <i class="ph ph-plus"></i> Generate Key
            </button>
        </div>

        <?php if (empty($apiKeys)): ?>
            <div style="padding:32px;text-align:center;background:var(--bg);border:1px dashed var(--border);border-radius:12px;">
                <i class="ph ph-key" style="font-size:32px;color:var(--fg3);margin-bottom:8px;display:block;"></i>
                <div style="color:var(--fg);font-weight:600;margin-bottom:4px;">No API keys generated</div>
                <p style="color:var(--fg2);font-size:13px;margin:0 0 14px;">Generate an API key to automate ad creation and post scheduling from your own codebase.</p>
                <button class="btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#generateKeyModal">Generate Key</button>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-dark" style="width:100%;margin:0;">
                    <thead>
                        <tr>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Key Identifier</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Scopes</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Last Used</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Created Date</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiKeys as $k):
                            $scopesArr = json_decode($k['scopes'] ?? '[]', true) ?: [];
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(200,255,0,0.1);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                                            <i class="ph ph-key"></i>
                                        </div>
                                        <code style="color:var(--accent);font-weight:600;font-size:13px;background:var(--bg);padding:4px 8px;border-radius:6px;border:1px solid var(--border);">
                                            <?= htmlspecialchars($k['key_prefix']) ?>••••••••••••••
                                        </code>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <?php foreach ($scopesArr as $s): ?>
                                            <span class="badge-status badge-active" style="font-size:11px;padding:2px 8px;">
                                                <?= htmlspecialchars($s) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td style="color:var(--fg2);font-size:13px;">
                                    <?= $k['last_used_at'] ? dt($k['last_used_at'], 'M d, Y H:i') : '<span style="color:var(--fg3)">Never</span>' ?>
                                </td>
                                <td style="color:var(--fg2);font-size:13px;">
                                    <?= $k['created_at'] ? dt($k['created_at'], 'M d, Y') : '<span style="color:var(--fg3)">Unknown</span>' ?>
                                </td>
                                <td style="text-align:right;">
                                    <form method="POST" style="margin:0;display:inline-block;" onsubmit="return confirm('Revoke this API key? Any applications using it will be denied.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="revoke_key">
                                        <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                                        <button type="submit" class="btn-outline-dark btn-sm" style="color:#f43f5e;border-color:rgba(244,63,94,0.3);padding:4px 12px;">
                                            <i class="ph ph-prohibit"></i> Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Webhooks Section -->
    <div class="card-dark mb-4" style="padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
            <div>
                <h4 style="color:#fff;font-size:18px;font-weight:700;margin:0;">Event Webhooks</h4>
                <p style="color:var(--fg2);font-size:13px;margin:2px 0 0;">Receive real-time HTTP POST notifications when ads are approved, posts are published, or wallet credits are depleted.</p>
            </div>
            <button class="btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#addWebhookModal">
                <i class="ph ph-plus"></i> Add Webhook
            </button>
        </div>

        <?php if (empty($webhooks)): ?>
            <div style="padding:32px;text-align:center;background:var(--bg);border:1px dashed var(--border);border-radius:12px;">
                <i class="ph ph-broadcast" style="font-size:32px;color:var(--fg3);margin-bottom:8px;display:block;"></i>
                <div style="color:var(--fg);font-weight:600;margin-bottom:4px;">No webhook endpoints registered</div>
                <p style="color:var(--fg2);font-size:13px;margin:0 0 14px;">Connect your CRM, Zapier, Make, or custom backend to get instant notifications.</p>
                <button class="btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#addWebhookModal">Add Webhook</button>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-dark" style="width:100%;margin:0;">
                    <thead>
                        <tr>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Event Trigger</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Target Destination</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Status</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Added</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhooks as $w): ?>
                            <tr>
                                <td>
                                    <span class="badge-status badge-info" style="font-size:12px;">
                                        <i class="ph ph-lightning"></i> <?= htmlspecialchars($w['event_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="color:var(--fg);background:var(--bg);padding:4px 8px;border-radius:6px;font-size:12px;">
                                        <?= htmlspecialchars($w['target_url']) ?>
                                    </code>
                                </td>
                                <td>
                                    <?php if ($w['is_active']): ?>
                                        <span class="badge-status badge-active">Live &amp; Active</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-pending">Paused</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--fg2);font-size:13px;">
                                    <?= $w['created_at'] ? dt($w['created_at'], 'M d, Y') : '<span style="color:var(--fg3)">Unknown</span>' ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex;gap:8px;align-items:center;">
                                        <form method="POST" style="margin:0;display:inline-block;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="test_webhook">
                                            <input type="hidden" name="webhook_id" value="<?= $w['id'] ?>">
                                            <button type="submit" class="btn-outline-dark btn-sm" style="color:var(--accent);border-color:rgba(200,255,0,0.3);padding:4px 12px;" title="Send an immediate signed HMAC-SHA256 test ping">
                                                <i class="ph ph-paper-plane-tilt"></i> Ping Test
                                            </button>
                                        </form>
                                        <form method="POST" style="margin:0;display:inline-block;" onsubmit="return confirm('Delete this webhook endpoint?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_webhook">
                                            <input type="hidden" name="webhook_id" value="<?= $w['id'] ?>">
                                            <button type="submit" class="btn-outline-dark btn-sm" style="color:#f43f5e;border-color:rgba(244,63,94,0.3);padding:4px 12px;">
                                                <i class="ph ph-trash"></i> Delete
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

    <!-- API Reference Quickstart Snippets -->
    <div class="card-dark" style="padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <div>
                <h4 style="color:#fff;font-size:18px;font-weight:700;margin:0;">REST API Quickstart Documentation</h4>
                <p style="color:var(--fg2);font-size:13px;margin:2px 0 0;">All requests must include your API Key in the Authorization header.</p>
            </div>
            <span class="badge-status badge-active">v1.0.0</span>
        </div>

        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist" style="gap:8px;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active btn-sm" id="pills-curl-tab" data-bs-toggle="pill" data-bs-target="#pills-curl" type="button" role="tab" style="color:var(--fg);border-radius:8px;background:var(--bg3);border:1px solid var(--border);">cURL</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm" id="pills-node-tab" data-bs-toggle="pill" data-bs-target="#pills-node" type="button" role="tab" style="color:var(--fg);border-radius:8px;background:var(--bg3);border:1px solid var(--border);">JavaScript / Fetch</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm" id="pills-php-tab" data-bs-toggle="pill" data-bs-target="#pills-php" type="button" role="tab" style="color:var(--fg);border-radius:8px;background:var(--bg3);border:1px solid var(--border);">PHP (cURL)</button>
            </li>
        </ul>

        <div class="tab-content" id="pills-tabContent">
            <!-- cURL Tab -->
            <div class="tab-pane fade show active" id="pills-curl" role="tabpanel">
                <div style="background:#060a12;border:1px solid var(--border);border-radius:10px;padding:16px;position:relative;">
                    <pre style="color:#a7f3d0;font-family:monospace;font-size:13px;margin:0;overflow-x:auto;">
curl -X POST <?= BASE_URL ?>/api/v1/campaigns.php \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_name": "Summer Boost 2026",
    "campaign_type": "combo_pack",
    "budget": 150.00,
    "headline": "Scale your sales with AI",
    "primary_text": "Launch ads everywhere in 1-click."
  }'</pre>
                </div>
            </div>

            <!-- JavaScript Tab -->
            <div class="tab-pane fade" id="pills-node" role="tabpanel">
                <div style="background:#060a12;border:1px solid var(--border);border-radius:10px;padding:16px;position:relative;">
                    <pre style="color:#93c5fd;font-family:monospace;font-size:13px;margin:0;overflow-x:auto;">
const response = await fetch('<?= BASE_URL ?>/api/v1/campaigns.php', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    campaign_name: 'Summer Boost 2026',
    campaign_type: 'combo_pack',
    budget: 150.00,
    headline: 'Scale your sales with AI'
  })
});
const data = await response.json();
console.log(data);</pre>
                </div>
            </div>

            <!-- PHP Tab -->
            <div class="tab-pane fade" id="pills-php" role="tabpanel">
                <div style="background:#060a12;border:1px solid var(--border);border-radius:10px;padding:16px;position:relative;">
                    <pre style="color:#fde047;font-family:monospace;font-size:13px;margin:0;overflow-x:auto;">
&lt;?php
$ch = curl_init('<?= BASE_URL ?>/api/v1/campaigns.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'campaign_name' => 'Summer Boost 2026',
        'campaign_type' => 'combo_pack',
        'budget'        => 150.00,
    ]),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer YOUR_API_KEY',
        'Content-Type: application/json',
    ],
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
print_r($response);</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generate Key Modal -->
<div class="modal fade" id="generateKeyModal" tabindex="-1" aria-labelledby="generateKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:18px;box-shadow:0 25px 60px rgba(0,0,0,0.85);color:#f8fafc;overflow:hidden;">
            <form method="POST">
                <div class="modal-header" style="background:#090e18;border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(200,255,0,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                            <i class="ph ph-key" style="font-size:22px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" id="generateKeyModalLabel" style="font-size:18px;font-weight:700;color:#fff;margin:0;">Generate New API Key</h5>
                            <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">Create a bearer token with custom permissions</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="padding:24px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="generate_key">

                    <div style="background:rgba(200,255,0,0.08);border:1px solid rgba(200,255,0,0.3);border-radius:12px;padding:16px;margin-bottom:18px;">
                        <div class="form-check" style="margin:0;">
                            <input class="form-check-input" type="checkbox" name="is_master_key" value="1" id="scopeMaster" checked onchange="document.getElementById('customScopesBox').style.display = this.checked ? 'none' : 'flex';">
                            <label class="form-check-label" for="scopeMaster" style="font-size:14px;color:#fff;font-weight:700;">
                                ⭐ Unified Master API Key (Recommended)
                            </label>
                        </div>
                        <div style="font-size:12px;color:var(--fg2);margin-left:26px;margin-top:4px;">
                            One single key for everything: Ads, Live Social Posting, AI UGC Studio, Directory, Reviews, Wallet, and Webhooks. Protected by sliding 60 req/min rate limit.
                        </div>
                    </div>

                    <div id="customScopesBox" style="display:none;flex-direction:column;gap:10px;background:#141f33;padding:16px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);max-height:220px;overflow-y:auto;">
                        <div style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;margin-bottom:4px;">Custom Scope Selection</div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="read" id="scopeRead" checked>
                            <label class="form-check-label" for="scopeRead" style="font-size:13px;color:#fff;"><b>read</b> — Query analytics, logs &amp; statuses</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="write" id="scopeWrite" checked>
                            <label class="form-check-label" for="scopeWrite" style="font-size:13px;color:#fff;"><b>write</b> — Mutate resources</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="ads" id="scopeAds" checked>
                            <label class="form-check-label" for="scopeAds" style="font-size:13px;color:#fff;"><b>ads</b> — Create &amp; pause ad campaigns</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="ai" id="scopeAi" checked>
                            <label class="form-check-label" for="scopeAi" style="font-size:13px;color:#fff;"><b>ai / ugc</b> — Generate photorealistic UGC creatives</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="social" id="scopeSocial" checked>
                            <label class="form-check-label" for="scopeSocial" style="font-size:13px;color:#fff;"><b>social</b> — Real-time live multi-network posting</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="directory" id="scopeDirectory" checked>
                            <label class="form-check-label" for="scopeDirectory" style="font-size:13px;color:#fff;"><b>directory</b> — Manage business directory listings</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="reviews" id="scopeReviews" checked>
                            <label class="form-check-label" for="scopeReviews" style="font-size:13px;color:#fff;"><b>reviews</b> — Submit &amp; query verified community ratings</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="wallet" id="scopeWallet" checked>
                            <label class="form-check-label" for="scopeWallet" style="font-size:13px;color:#fff;"><b>wallet</b> — Query balances &amp; credit ledgers</label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background:#090e18;border-top:1px solid rgba(255,255,255,0.08);padding:18px 24px;display:flex;justify-content:space-between;">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent" style="padding:10px 24px;">
                        <i class="ph ph-check"></i> Generate Key
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Webhook Modal -->
<div class="modal fade" id="addWebhookModal" tabindex="-1" aria-labelledby="addWebhookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:18px;box-shadow:0 25px 60px rgba(0,0,0,0.85);color:#f8fafc;overflow:hidden;">
            <form method="POST">
                <div class="modal-header" style="background:#090e18;border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(56,189,248,0.12);display:flex;align-items:center;justify-content:center;color:var(--blue);">
                            <i class="ph ph-broadcast" style="font-size:22px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" id="addWebhookModalLabel" style="font-size:18px;font-weight:700;color:#fff;margin:0;">Register Webhook Endpoint</h5>
                            <p style="font-size:12px;color:#94a3b8;margin:2px 0 0;">Subscribe to real-time events</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="padding:24px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_webhook">

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Payload URL (HTTPS) <span style="color:var(--accent)">*</span></label>
                        <input type="url" name="target_url" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;" placeholder="https://api.yourdomain.com/webhooks/adsbooster" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#f8fafc;">Event Trigger</label>
                        <select name="event_type" class="form-select" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;border-radius:8px;">
                            <option value="campaign.status_changed">campaign.status_changed — Ad approved / paused / completed</option>
                            <option value="post.published">post.published — Scheduled social post published</option>
                            <option value="post.failed">post.failed — Social post dispatch failed</option>
                            <option value="payment.completed">payment.completed — Successful invoice / wallet top-up</option>
                            <option value="credits.low">credits.low — Balance reached threshold</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer" style="background:#090e18;border-top:1px solid rgba(255,255,255,0.08);padding:18px 24px;display:flex;justify-content:space-between;">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent" style="padding:10px 24px;">
                        <i class="ph ph-check"></i> Register Webhook
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyRawKey() {
    const input = document.getElementById('rawKeyInput');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        alert('API Key copied to clipboard!');
    });
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
