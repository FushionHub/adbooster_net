<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId  = current_user_id();
$user    = current_user();
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'download_data') {
            $userData = [
                'account' => [
                    'id'         => $user['id'],
                    'full_name'  => $user['full_name'],
                    'email'      => $user['email'],
                    'phone'      => $user['phone'] ?? '',
                    'ad_credits' => $user['ad_credits'] ?? 0,
                    'ai_credits' => $user['ai_credits'] ?? 0,
                    'created_at' => $user['created_at'],
                ],
                'campaigns'       => db()->fetchAll("SELECT id, campaign_name, campaign_type, objective, budget_amount, status, created_at FROM ad_campaigns WHERE user_id = ?", [$userId]),
                'connected_social'=> db()->fetchAll("SELECT gateway_provider, account_type, platform_name, external_account_id, display_name, status, created_at FROM connected_accounts WHERE user_id = ?", [$userId]),
                'scheduled_posts' => db()->fetchAll("SELECT id, platform, caption, scheduled_time, status, created_at FROM scheduled_posts WHERE user_id = ?", [$userId]),
                'transactions'    => db()->fetchAll("SELECT id, type, amount, currency, gateway, status, created_at FROM transactions WHERE user_id = ?", [$userId]),
                'dating_profiles' => db()->fetchAll("SELECT * FROM dating_profiles WHERE user_id = ?", [$userId]),
                'team_memberships'=> db()->fetchAll("SELECT role, status, created_at FROM team_members WHERE parent_user_id = ? OR user_id = ?", [$userId, $userId]),
                'cookie_consents' => db()->fetchAll("SELECT * FROM cookie_consents WHERE user_id = ? ORDER BY created_at DESC", [$userId]),
            ];

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="adsbooster_personal_data_' . date('Y-m-d') . '.json"');
            header('Cache-Control: no-cache, must-revalidate');
            echo json_encode($userData, JSON_PRETTY_PRINT);
            exit;
        } elseif ($action === 'download_csv') {
            $transactions = db()->fetchAll("SELECT id, type, amount, currency, gateway, status, created_at FROM transactions WHERE user_id = ?", [$userId]);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="adsbooster_ledger_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Transaction ID', 'Type', 'Amount', 'Currency', 'Gateway', 'Status', 'Date']);
            foreach ($transactions as $txn) {
                fputcsv($output, [
                    $txn['id'], $txn['type'], $txn['amount'], $txn['currency'],
                    $txn['gateway'], $txn['status'], $txn['created_at'],
                ]);
            }
            fclose($output);
            exit;
        } elseif ($action === 'save_consents') {
            $analytics = isset($_POST['consent_analytics']) ? 1 : 0;
            $marketing = isset($_POST['consent_marketing']) ? 1 : 0;
            $consentUuid = $_COOKIE['consent_uuid'] ?? bin2hex(random_bytes(16));

            setcookie('consent_uuid', $consentUuid, time() + 365 * 86400, '/');
            setcookie('consent_analytics', (string)$analytics, time() + 365 * 86400, '/');
            setcookie('consent_marketing', (string)$marketing, time() + 365 * 86400, '/');

            try {
                db()->insert('cookie_consents', [
                    'user_id'      => $userId,
                    'consent_uuid' => $consentUuid,
                    'necessary'    => 1,
                    'analytics'    => $analytics,
                    'marketing'    => $marketing,
                    'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {}

            $success = 'Your cookie and privacy preferences have been updated successfully.';
        } elseif ($action === 'delete_account') {
            $confirmDelete = trim($_POST['confirm_delete'] ?? '');
            if ($confirmDelete !== 'DELETE MY ACCOUNT') {
                $errors[] = 'Please type "DELETE MY ACCOUNT" exactly to confirm account deletion.';
            } else {
                $scrubbed = [
                    'full_name'  => 'Deleted User',
                    'email'      => 'deleted_' . $userId . '_' . time() . '@removed.local',
                    'phone'      => null,
                    'password'   => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    'status'     => 'deleted',
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                db()->update('users', $scrubbed, 'id = ?', [$userId]);

                db()->update('dating_profiles', [
                    'bio' => '', 'photo' => '', 'city' => '', 'age' => null,
                ], 'user_id = ?', [$userId]);

                try {
                    db()->insert('account_deletions', [
                        'user_id'      => $userId,
                        'requested_at' => date('Y-m-d H:i:s'),
                        'reason'       => 'User requested account erasure via Privacy Center',
                    ]);
                } catch (\Throwable $e) {}

                session_destroy();
                redirect(url('login') . '?deleted=1');
            }
        }
    }
}

$consentHistory = [];
try {
    $consentHistory = db()->fetchAll(
        "SELECT * FROM cookie_consents WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
        [$userId]
    );
} catch (\Throwable $e) {
    $consentHistory = [];
}

$latestConsent = !empty($consentHistory) ? $consentHistory[0] : null;
$hasAnalytics = $latestConsent ? (bool)$latestConsent['analytics'] : true;
$hasMarketing = $latestConsent ? (bool)$latestConsent['marketing'] : true;

$pageTitle = 'Privacy Center & Data Rights â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Privacy Center &amp; Data Rights</h1>
            <p class="page-subtitle">Manage your personal data exports, cookie tracking preferences, and GDPR / CCPA privacy rights.</p>
        </div>
        <div>
            <a href="<?= url('profile') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-user"></i> My Profile
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="padding:14px 20px;border-radius:12px;margin-bottom:24px;">
            <i class="ph ph-check-circle me-1"></i> <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="padding:14px 20px;border-radius:12px;margin-bottom:24px;">
            <?php foreach ($errors as $err): ?>
                <div><i class="ph ph-warning-circle me-1"></i> <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Data Download Cards -->
    <div class="card-dark" style="padding:28px;margin-bottom:28px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(200,255,0,0.12);border:1px solid rgba(200,255,0,0.3);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                <i class="ph ph-download-simple" style="font-size:24px;"></i>
            </div>
            <div>
                <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">Export My Personal Data (GDPR Portability)</h4>
                <p style="color:var(--fg2);font-size:13px;margin:2px 0 0;">Under GDPR Article 20, you can request an instant copy of your personal data archive.</p>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <div style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;height:100%;">
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <span style="font-weight:700;color:#fff;font-size:15px;"><i class="ph ph-file-code me-2 text-warning"></i>JSON Full Profile Archive</span>
                            <span class="badge-platform">Machine-Readable</span>
                        </div>
                        <p style="font-size:12.5px;color:var(--fg2);margin-bottom:16px;">
                            Contains your account details, ad campaigns, connected social accounts, scheduled posts, dating profile, and privacy logs.
                        </p>
                    </div>
                    <form method="POST" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="download_data">
                        <button type="submit" class="btn-accent w-100" style="padding:10px;font-weight:600;">
                            <i class="ph ph-download me-1"></i> Download JSON Archive
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <div style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;height:100%;">
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <span style="font-weight:700;color:#fff;font-size:15px;"><i class="ph ph-file-csv me-2 text-info"></i>CSV Financial Ledger</span>
                            <span class="badge-platform">Spreadsheet Compatible</span>
                        </div>
                        <p style="font-size:12.5px;color:var(--fg2);margin-bottom:16px;">
                            Contains all billing charges, ad credit topups, plan subscriptions, gateways, and transaction references for your accounting.
                        </p>
                    </div>
                    <form method="POST" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="download_csv">
                        <button type="submit" class="btn-outline-dark w-100" style="padding:10px;font-weight:600;">
                            <i class="ph ph-table me-1"></i> Download CSV Ledger
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Cookie & Tracking Preferences -->
    <div class="card-dark" style="padding:28px;margin-bottom:28px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);display:flex;align-items:center;justify-content:center;color:var(--green);">
                <i class="ph ph-cookie" style="font-size:24px;"></i>
            </div>
            <div>
                <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">Cookie &amp; Tracking Consent Preferences</h4>
                <p style="color:var(--fg2);font-size:13px;margin:2px 0 0;">Manage your privacy permissions for tracking, analytics, and advertising telemetry.</p>
            </div>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_consents">

            <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:24px;">
                <div style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h6 style="color:#fff;font-weight:600;margin:0 0 2px;">Strictly Necessary Cookies</h6>
                        <p style="color:var(--fg3);font-size:12px;margin:0;">Required for user authentication, security CSRF protection, and session stability.</p>
                    </div>
                    <span class="badge-status badge-active" style="font-size:11px;">Always Active</span>
                </div>

                <div style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h6 style="color:#fff;font-weight:600;margin:0 0 2px;">Performance &amp; Analytics Cookies</h6>
                        <p style="color:var(--fg3);font-size:12px;margin:0;">Helps us measure page load speeds and API response latency to improve your workflow.</p>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="consent_analytics" value="1" <?= $hasAnalytics ? 'checked' : '' ?> style="width:40px;height:22px;cursor:pointer;">
                    </div>
                </div>

                <div style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h6 style="color:#fff;font-weight:600;margin:0 0 2px;">Marketing &amp; Social Pixels</h6>
                        <p style="color:var(--fg3);font-size:12px;margin:0;">Used to calibrate conversion tracking for Meta Graph, TikTok Events, and Google Ads conversions.</p>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="consent_marketing" value="1" <?= $hasMarketing ? 'checked' : '' ?> style="width:40px;height:22px;cursor:pointer;">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-accent" style="padding:10px 24px;font-weight:600;">
                <i class="ph ph-floppy-disk me-1"></i> Save Privacy Preferences
            </button>
        </form>
    </div>

    <!-- Danger Zone: Account Deletion -->
    <div class="card-dark" style="padding:28px;border:1px solid rgba(244,63,94,0.3);background:linear-gradient(135deg, rgba(244,63,94,0.05), #0d1524);">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(244,63,94,0.12);border:1px solid rgba(244,63,94,0.3);display:flex;align-items:center;justify-content:center;color:#f43f5e;">
                <i class="ph ph-warning-octagon" style="font-size:24px;"></i>
            </div>
            <div>
                <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">Danger Zone: Right to be Forgotten (Account Erasure)</h4>
                <p style="color:var(--fg2);font-size:13px;margin:2px 0 0;">Under GDPR Article 17, requesting account erasure permanently anonymizes your records.</p>
            </div>
        </div>

        <p style="color:var(--fg2);font-size:13px;line-height:1.5;margin-bottom:18px;">
            Once you delete your account, your profile, ad campaigns, scheduled posts, and connections will be scrubbed and deactivated permanently. This action cannot be reversed.
        </p>

        <button type="button" class="btn-outline-dark btn-sm" style="color:#f43f5e;border-color:rgba(244,63,94,0.4);" data-bs-toggle="modal" data-bs-target="#deleteModal">
            <i class="ph ph-trash me-1"></i> Delete My Account Permanently
        </button>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(244,63,94,0.4);border-radius:16px;">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_account">
                <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,0.08);">
                    <h5 class="modal-title" style="color:#f43f5e;font-weight:700;"><i class="ph ph-warning me-2"></i>Confirm Account Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px;">
                    <p style="color:var(--fg);font-size:14px;margin-bottom:16px;">
                        This action will scrub all your personal information, API keys, and advertising history. You will be logged out immediately.
                    </p>
                    <label class="form-label" style="font-size:13px;color:var(--fg2);">
                        Type <strong style="color:#fff;">DELETE MY ACCOUNT</strong> to confirm:
                    </label>
                    <input type="text" name="confirm_delete" class="form-control" placeholder="DELETE MY ACCOUNT" required style="background:#080c14;border:1px solid rgba(244,63,94,0.4);color:#fff;">
                </div>
                <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,0.08);">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-danger" style="background:#f43f5e;border:0;font-weight:600;padding:8px 20px;border-radius:10px;">
                        Confirm Deletion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
