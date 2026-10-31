<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/two_factor.php';
require_once __DIR__ . '/../modules/email_service.php';

$pendingAdminId = (int)($_SESSION['admin_pending_totp'] ?? 0);
if ($pendingAdminId <= 0) {
    redirect(url('admin-login'));
}

$admin = db()->fetchOne("SELECT * FROM users WHERE id = ? AND role = 'admin'", [$pendingAdminId]);
if (!$admin) {
    unset($_SESSION['admin_pending_totp']);
    redirect(url('admin-login'));
}

$errors = [];
$success = '';

// â”€â”€ Rate limit: a 6-digit code is only 10^6 possibilities and lives for
//    10 minutes. Lock out after 5 failed attempts per admin per 15 minutes. â”€â”€
$rlKey = 'admin_2fa_' . $pendingAdminId;
$rlOk  = check_rate_limit($rlKey, 5, 900);

/**
 * Best-effort persistent attempt counter. Never fatal if the column is absent.
 */
if (!function_exists('admin_2fa_bump')) {
    function admin_2fa_bump(int $adminId): void
    {
        try {
            db()->query("UPDATE users SET `2fa_attempts` = `2fa_attempts` + 1 WHERE id = ?", [$adminId]);
        } catch (\Throwable $e) {
            error_log('admin_2fa_bump: ' . $e->getMessage());
        }
    }
}

if (!function_exists('admin_2fa_reset')) {
    function admin_2fa_reset(int $adminId): void
    {
        try {
            db()->update('users', ['2fa_attempts' => 0], 'id = ?', [$adminId]);
        } catch (\Throwable $e) {
            error_log('admin_2fa_reset: ' . $e->getMessage());
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    if (!$rlOk) {
        $errors[] = 'Too many incorrect attempts. Please wait 15 minutes before trying again.';
    } else {
    $action = $_POST['action'] ?? 'verify_code';

    // 1. Send OTP to Email
    if ($action === 'send_email_otp') {
        // Hash at rest + single use. The plaintext value never leaves this scope.
        $otp = (string)random_int(100000, 999999);
        $expires = date('Y-m-d H:i:s', time() + 600);

        db()->update('users', [
            'email_2fa_code'       => password_hash($otp, PASSWORD_DEFAULT),
            'email_2fa_expires_at' => $expires,
            '2fa_attempts'         => 0,
        ], 'id = ?', [$pendingAdminId]);

        EmailService::sendTransactional($admin['email'], 'two_factor_code', [
            'name' => $admin['full_name'],
            'code' => $otp,
        ]);

        $success = "A 6-digit administrative security code has been sent to {$admin['email']}. It is valid for 10 minutes.";
    }

    // 2. Verify 6-digit code or backup code
    elseif ($action === 'verify_code') {
        $code = trim((string)($_POST['code'] ?? ''));
        $code = str_replace([' ', '-'], '', $code);
        $verified = false;

        admin_2fa_bump($pendingAdminId);

        $totpSecret = $admin['two_factor_secret'] ?: ($admin['totp_secret'] ?? '');
        if (!empty($totpSecret) && preg_match('/^\d{6}$/', $code)) {
            if (TwoFactorAuth::verifyCode($totpSecret, $code)) {
                $verified = true;
            }
        }

        // Email OTP check (stored hashed, constant-time compare, single use)
        if (!$verified && !empty($admin['email_2fa_code']) && !empty($admin['email_2fa_expires_at'])) {
            if (strtotime($admin['email_2fa_expires_at']) >= time()
                && password_verify($code, (string)$admin['email_2fa_code'])
            ) {
                $verified = true;
            }
            if ($verified) {
                db()->update('users', ['email_2fa_code' => null], 'id = ?', [$pendingAdminId]);
            }
        }

        // Emergency Backup check
        if (!$verified && !empty($admin['two_factor_backup'])) {
            $backupCodes = json_decode((string)$admin['two_factor_backup'], true) ?: [];
            if (is_array($backupCodes)) {
                foreach ($backupCodes as &$b) {
                    // `$b` may be a bare string in the oldest format â€” never
                    // index into it without checking, and skip used records.
                    if (!is_array($b) || !empty($b['used'])) {
                        continue;
                    }
                    $stored = (string)($b['hash'] ?? ($b['code'] ?? ''));
                    if ($stored === '') {
                        continue;
                    }
                    // `$code` is already dash-stripped above.
                    $plain = str_replace('-', '', $stored);
                    $match = password_verify($code, $stored)
                        || ($plain !== '' && hash_equals($plain, $code));
                    if ($match) {
                        $b['used'] = true;
                        $b['hash'] = password_hash($code, PASSWORD_DEFAULT);  // upgrade in place
                        unset($b['code']);
                        $verified = true;
                        db()->update('users', ['two_factor_backup' => json_encode($backupCodes)], 'id = ?', [$pendingAdminId]);
                        break;
                    }
                }
                unset($b);
            }
        }

        if ($verified) {
            unset($_SESSION['admin_pending_totp']);
            db()->update('users', ['email_2fa_code' => null, '2fa_attempts' => 0], 'id = ?', [$pendingAdminId]);
            auth_admin_login($admin);
            // Flag consumed by includes/admin_guard.php
            $_SESSION['admin_2fa_verified'] = time();
            set_flash('success', 'Admin two-factor authentication successful. Welcome to the command center.');
            $dest = (string)($_SESSION['admin_post_2fa_url'] ?? '/admin/index.php');
            unset($_SESSION['admin_post_2fa_url'], $_SESSION['admin_2fa_enrol_shown']);
            // Only ever redirect to a same-site relative path.
            $dest = (str_starts_with($dest, '/') && !str_starts_with($dest, '//')) ? $dest : '/admin/index.php';
            redirect(BASE_URL . $dest);
        } else {
            $errors[] = 'Invalid or expired administrative 2FA security code.';
        }
    }
    }
}

$pageTitle = 'Admin 2FA Security Challenge â€” ' . get_setting('site_name', 'Adsbooster.net');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #080c14;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-auth-card {
            background: #0d1524;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 36px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .code-input {
            background: #060910 !important;
            border: 1px solid #c4f000 !important;
            color: #fff !important;
            font-family: monospace;
            font-size: 26px;
            letter-spacing: 8px;
            text-align: center;
            border-radius: 12px;
            padding: 14px;
        }
        .btn-verify {
            background: #c4f000;
            color: #080c14;
            font-weight: 700;
            border-radius: 50px;
            padding: 14px;
            border: none;
            width: 100%;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        .btn-verify:hover {
            background: #d4ff1a;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="admin-auth-card">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:64px;height:64px;background:rgba(196,240,0,0.12);color:#c4f000;font-size:30px;border:1px solid rgba(196,240,0,0.25);">
                <i class="ph ph-shield-check"></i>
            </div>
            <h2 class="h4 fw-bold text-white mb-1">Admin Security Verification</h2>
            <p class="text-secondary small mb-0">Enter the 6-digit TOTP code from your Authenticator app.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success py-2 px-3 small rounded-3 mb-3"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_code">
            <div class="mb-4">
                <label class="form-label small text-secondary fw-semibold">Authenticator Code / Backup Key</label>
                <input type="text" name="code" class="form-control code-input" placeholder="000000" autofocus required autocomplete="one-time-code">
            </div>
            <button type="submit" class="btn-verify mb-3">
                Confirm &amp; Enter Dashboard &rarr;
            </button>
        </form>

        <form method="POST" class="mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_email_otp">
            <button type="submit" class="btn btn-outline-secondary w-100 rounded-pill py-2 small text-light border-secondary">
                <i class="ph ph-envelope me-1"></i> Send backup code to <?= e($admin['email']) ?>
            </button>
        </form>

        <div class="text-center">
            <a href="<?= url('admin-login') ?>" class="text-secondary small text-decoration-none hover-text-light">
                &larr; Back to Admin Login
            </a>
        </div>
    </div>
</body>
</html>
