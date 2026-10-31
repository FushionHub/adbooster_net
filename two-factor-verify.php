<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/two_factor.php';
require_once __DIR__ . '/modules/email_service.php';

$pendingUserId = (int)($_SESSION['2fa_pending_user_id'] ?? 0);
if ($pendingUserId <= 0) {
    redirect(url('login'));
}

$user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$pendingUserId]);
if (!$user || (int)($user['two_factor_enabled'] ?? 0) !== 1) {
    unset($_SESSION['2fa_pending_user_id']);
    redirect(url('login'));
}

$errors  = [];
$success = '';

// â”€â”€ Abuse control â”€â”€
// The TOTP space is only 10^6 and a code stays valid for 30s, so an
// attacker who can spam guesses could walk it. Cap attempts and OTP emails.
$verifyAllowed = check_rate_limit('2fa_verify_' . $pendingUserId, 10, 900);
$otpAllowed    = check_rate_limit('2fa_otp_' . $pendingUserId, 3, 900);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $action = (string)($_POST['action'] ?? 'verify_code');

    // 1. Send OTP to Email
    if ($action === 'send_email_otp') {
        if (!$otpAllowed) {
            $errors[] = 'Too many security codes requested. Please wait 15 minutes before requesting another.';
        } else {
            $otp = (string)random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

            // Hash at rest: the plaintext code never leaves this scope, so a
            // database leak cannot be replayed against a live login.
            db()->update('users', [
                'email_2fa_code'       => password_hash($otp, PASSWORD_DEFAULT),
                'email_2fa_expires_at' => $expires,
            ], 'id = ?', [$pendingUserId]);

            EmailService::sendTransactional($user['email'], 'two_factor_code', [
                'name' => $user['full_name'],
                'code' => $otp,
            ]);

            $success = "A 6-digit security code has been sent to your registered email ({$user['email']}). It expires in 10 minutes.";
        }
    }

    // 2. Verify 6-digit code or backup code
    elseif ($action === 'verify_code') {
        if (!$verifyAllowed) {
            $errors[] = 'Too many incorrect attempts. Please wait 15 minutes before trying again.';
        } else {
        $code = trim((string)($_POST['code'] ?? ''));
        $code = str_replace([' ', '-'], '', $code);
        $verified = false;

        // Check Google Authenticator TOTP
        if (!empty($user['two_factor_secret']) && preg_match('/^\d{6}$/', $code)) {
            if (TwoFactorAuth::verifyCode($user['two_factor_secret'], $code)) {
                $verified = true;
            }
        }

        // Check Email OTP (stored hashed, single use)
        if (!$verified && !empty($user['email_2fa_code']) && !empty($user['email_2fa_expires_at'])) {
            if (strtotime((string)$user['email_2fa_expires_at']) >= time()
                && password_verify($code, (string)$user['email_2fa_code'])
            ) {
                $verified = true;
                db()->update('users', ['email_2fa_code' => null], 'id = ?', [$pendingUserId]);
            }
        }

        // Check Emergency Backup Codes (hashed going forward, plaintext legacy accepted once)
        if (!$verified && !empty($user['two_factor_backup'])) {
            $backupCodes = json_decode((string)$user['two_factor_backup'], true) ?: [];
            if (is_array($backupCodes)) {
                foreach ($backupCodes as &$b) {
                    // Skip malformed / already-used records. `$b` may be a bare
                    // string in the oldest format, so never index into it blindly.
                    if (!is_array($b) || !empty($b['used'])) {
                        continue;
                    }
                    $stored = (string)($b['hash'] ?? ($b['code'] ?? ''));
                    if ($stored === '') {
                        continue;
                    }
                    $cleanB = str_replace('-', '', $stored);
                    $match  = password_verify($code, $stored)
                        || ($cleanB !== '' && hash_equals($cleanB, $code));
                    if ($match) {
                        $b['used'] = true;
                        $b['hash'] = password_hash($code, PASSWORD_DEFAULT);  // upgrade in place
                        unset($b['code']);
                        $verified = true;
                        db()->update('users', ['two_factor_backup' => json_encode($backupCodes)], 'id = ?', [$pendingUserId]);
                        break;
                    }
                }
                unset($b);
            }
        }

        if ($verified) {
            unset($_SESSION['2fa_pending_user_id']);
            auth_login($user);

            // Reset any lingering lockout state.
            try {
                db()->update('users', ['email_2fa_code' => null, '2fa_attempts' => 0], 'id = ?', [$pendingUserId]);
            } catch (\Throwable $e) {
                // column optional
            }

            if (!empty($_SESSION['2fa_remember'])) {
                issue_remember_token((int)$user['id']);
                unset($_SESSION['2fa_remember']);
            }

            // Log security notice email
            EmailService::sendTransactional($user['email'], 'login_alert', [
                'name'     => $user['full_name'],
                'ip'       => client_ip(),
                'time'     => date('M d, Y H:i:s T'),
                'location' => 'Verified Device (2FA Approved)',
            ]);

            set_flash('success', 'Two-Factor Authentication successful. Welcome back!');
            redirect(url('dashboard'));
        } else {
            $errors[] = 'Invalid or expired authentication code. Please try again.';
        }
        }
    }
}

$pageTitle = 'Verify Identity (2FA) â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height:85vh;display:flex;align-items:center;">
    <div class="container" style="max-width:460px;">
        <div class="text-center tw-mb-8">
            <div style="width:64px;height:64px;border-radius:18px;background:rgba(200,255,0,0.12);border:1px solid rgba(200,255,0,0.3);display:flex;align-items:center;justify-content:center;color:var(--accent);margin:0 auto 16px;">
                <i class="ph ph-shield-check" style="font-size:32px;"></i>
            </div>
            <h1 class="h3 text-white fw-bold">Two-Factor Authentication</h1>
            <p class="text-white-50 tw-mt-1 small">Enter the 6-digit code from Google Authenticator or your email.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="padding:14px 18px;border-radius:12px;margin-bottom:20px;">
                <?= e($success) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="padding:14px 18px;border-radius:12px;margin-bottom:20px;">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card-dark" style="background:#0d1524;padding:32px;border:1px solid rgba(255,255,255,0.1);border-radius:18px;">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify_code">

                <div class="mb-4">
                    <label for="code" class="form-label" style="font-size:13px;color:#e2e8f0;font-weight:600;">Security Code / Backup Code</label>
                    <input type="text" id="code" name="code" class="form-control text-center" autocomplete="one-time-code" autofocus required placeholder="000000" style="background:#080c14;border:1px solid #c4f000;color:#fff;font-size:24px;font-family:monospace;letter-spacing:6px;padding:12px;border-radius:10px;">
                </div>

                <button type="submit" class="btn btn-main-600 text-dark fw-bold rounded-pill w-100 py-3 font-size-15 mb-3 d-flex align-items-center justify-content-center gap-2">
                    <span>Verify &amp; Continue</span>
                    <i class="ph-bold ph-arrow-right"></i>
                </button>
            </form>

            <!-- Send Email Code Option -->
            <form method="POST" style="margin-bottom:12px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_email_otp">
                <button type="submit" class="btn btn-outline-light rounded-pill w-100 py-2 font-size-13">
                    <i class="ph ph-envelope-simple me-1"></i> Send code to my email (<?= e($user['email']) ?>)
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= url('login') ?>" class="text-white-50 font-size-13 text-decoration-none hover-text-main-600">
                    &larr; Back to login
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
