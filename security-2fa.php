<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/two_factor.php';
require_once __DIR__ . '/modules/email_service.php';

$userId   = current_user_id();
$userData = current_user();

$pageTitle = 'Two-Factor Authentication (2FA) Security — ' . get_setting('site_name', 'Adsbooster.net');

$errors  = [];
$success = '';

$is2faEnabled = (bool)($userData['two_factor_enabled'] ?? false);
$currentType  = $userData['two_factor_type'] ?? 'both';

// Generate or retrieve current setup secret
$setupSecret = $_SESSION['setup_totp_secret'] ?? '';
if (empty($setupSecret)) {
    $setupSecret = !empty($userData['two_factor_secret']) ? $userData['two_factor_secret'] : TwoFactorAuth::generateSecret();
    $_SESSION['setup_totp_secret'] = $setupSecret;
}
$qrCodeUrl  = TwoFactorAuth::getQRCodeUrl($setupSecret, $userData['email']);
$otpauthUri = TwoFactorAuth::getOtpAuthUri($setupSecret, $userData['email']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    // Action: Enable 2FA
    if ($action === 'enable_2fa') {
        $type = $_POST['two_factor_type'] ?? 'both';
        $code = trim($_POST['verification_code'] ?? '');

        if ($type === 'totp' || $type === 'both') {
            if (empty($code) || !TwoFactorAuth::verifyCode($setupSecret, $code)) {
                $errors[] = 'Invalid 6-digit Google Authenticator code. Check your authenticator app time and try again.';
            }
        } elseif ($type === 'email') {
            $expected = $_SESSION['temp_email_2fa_code'] ?? '';
            if (empty($code) || $code !== $expected) {
                $errors[] = 'Invalid email verification code. Please check your inbox or request a new code.';
            }
        }

        if (empty($errors)) {
            $backupCodes = TwoFactorAuth::generateBackupCodes();

            // Show the plaintext codes to the user exactly once, but persist
            // only bcrypt hashes so a DB leak cannot be replayed.
            $_SESSION['new_backup_codes'] = $backupCodes;
            $hashedForStorage = [];
            foreach ($backupCodes as $bc) {
                $hashedForStorage[] = [
                    'hash' => password_hash((string)$bc['code'], PASSWORD_DEFAULT),
                    'used' => false,
                ];
            }

            db()->update('users', [
                'two_factor_enabled'  => 1,
                'two_factor_secret'   => $setupSecret,
                'two_factor_type'     => $type,
                'two_factor_backup'   => json_encode($hashedForStorage),
                'two_factor_setup_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$userId]);

            unset($_SESSION['setup_totp_secret'], $_SESSION['temp_email_2fa_code']);

            $success = 'Two-Factor Authentication is now ENABLED on your account! Save your emergency backup codes below.';
            $is2faEnabled = true;
            $userData = current_user();
        }
    }

    // Action: Send Email Test Code
    elseif ($action === 'send_test_email_code') {
        $tempCode = (string)random_int(100000, 999999);
        $_SESSION['temp_email_2fa_code'] = $tempCode;

        EmailService::sendTransactional($userData['email'], 'two_factor_code', [
            'name' => $userData['full_name'],
            'code' => $tempCode,
        ]);

        $success = "A 6-digit confirmation code has been emailed to {$userData['email']}. Check your inbox and enter it below.";
    }

    // Action: Disable 2FA
    elseif ($action === 'disable_2fa') {
        $password = $_POST['password'] ?? '';
        if (!password_verify($password, $userData['password'] ?? '')) {
            $errors[] = 'Incorrect account password. 2FA cannot be disabled without valid credentials.';
        } else {
            db()->update('users', [
                'two_factor_enabled' => 0,
                'two_factor_secret'  => null,
                'email_2fa_code'     => null,
            ], 'id = ?', [$userId]);

            $success = 'Two-Factor Authentication has been disabled.';
            $is2faEnabled = false;
            $userData = current_user();
        }
    }
}

$newBackupCodes = $_SESSION['new_backup_codes'] ?? null;
unset($_SESSION['new_backup_codes']);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Two-Factor Authentication (2FA)</h1>
            <p class="page-subtitle">Protect your account and ad campaign budgets with multi-factor authentication security.</p>
        </div>
        <div>
            <a href="<?= url('profile') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-user"></i> Profile Settings
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

    <!-- Status Overview Banner -->
    <div style="background:<?= $is2faEnabled ? 'rgba(16,185,129,0.08)' : 'rgba(251,146,60,0.08)' ?>;border:1px solid <?= $is2faEnabled ? 'rgba(16,185,129,0.3)' : 'rgba(251,146,60,0.3)' ?>;border-radius:14px;padding:20px 24px;margin-bottom:32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;border-radius:12px;background:<?= $is2faEnabled ? 'rgba(16,185,129,0.2)' : 'rgba(251,146,60,0.2)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $is2faEnabled ? 'var(--green)' : 'var(--orange)' ?>;">
                <i class="ph ph-shield-check" style="font-size:26px;"></i>
            </div>
            <div>
                <h4 style="color:#fff;font-size:16px;font-weight:700;margin:0 0 4px;">
                    2FA Security Status: <?= $is2faEnabled ? '<span class="text-success">Active &amp; Protected</span>' : '<span class="text-warning">Disabled (Action Recommended)</span>' ?>
                </h4>
                <p style="color:var(--fg2);font-size:13px;margin:0;">
                    <?= $is2faEnabled ? 'Logins require password verification plus an authenticator code or email OTP.' : 'Add an extra layer of defense against unauthorized logins and account takeovers.' ?>
                </p>
            </div>
        </div>
        <span class="badge-status <?= $is2faEnabled ? 'badge-active' : 'badge-inactive' ?>" style="font-size:12px;padding:6px 14px;">
            <?= $is2faEnabled ? 'Protected' : 'Unprotected' ?>
        </span>
    </div>

    <!-- Backup Codes Modal/Banner if just enabled -->
    <?php if ($newBackupCodes): ?>
        <div class="card-dark" style="padding:28px;margin-bottom:32px;border:1px solid var(--accent);background:linear-gradient(135deg, rgba(200,255,0,0.05), #0d1524);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                <i class="ph ph-key" style="font-size:24px;color:var(--accent);"></i>
                <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">Save Your Emergency Backup Codes</h4>
            </div>
            <p style="color:var(--fg2);font-size:13px;margin-bottom:18px;">
                Store these recovery codes in a secure password manager. If you lose access to your phone or authenticator app, each code can be used once to recover access to your account:
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px;">
                <?php foreach ($newBackupCodes as $c): ?>
                    <div style="background:#080c14;border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:8px 12px;text-align:center;font-family:monospace;font-weight:700;color:var(--accent);font-size:13px;">
                        <?= e($c['code']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$is2faEnabled): ?>
        <!-- Setup 2FA Form -->
        <div class="card-dark" style="padding:32px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                <i class="ph ph-lock-key" style="font-size:24px;color:var(--accent);"></i>
                <div>
                    <h3 style="color:#fff;font-size:18px;font-weight:700;margin:0 0 2px;">Enable Two-Factor Authentication</h3>
                    <p style="color:var(--fg2);font-size:13px;margin:0;">Select your preferred verification method and confirm setup.</p>
                </div>
            </div>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="enable_2fa">

                <!-- 2FA Type Selection -->
                <div style="margin-bottom:28px;">
                    <label class="form-label" style="font-weight:700;color:#fff;font-size:13px;margin-bottom:12px;">1. Choose Verification Method</label>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="card-dark" style="padding:18px;border:1px solid rgba(255,255,255,0.1);border-radius:12px;cursor:pointer;display:block;height:100%;" onclick="select2faType('totp')">
                                <input type="radio" name="two_factor_type" value="totp" checked style="margin-bottom:10px;">
                                <div style="font-weight:700;color:#fff;font-size:14px;margin-bottom:4px;">Google Authenticator</div>
                                <span style="font-size:12px;color:var(--fg2);">Use Google Authenticator, Authy, or 1Password app codes (Recommended).</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="card-dark" style="padding:18px;border:1px solid rgba(255,255,255,0.1);border-radius:12px;cursor:pointer;display:block;height:100%;" onclick="select2faType('email')">
                                <input type="radio" name="two_factor_type" value="email" style="margin-bottom:10px;">
                                <div style="font-weight:700;color:#fff;font-size:14px;margin-bottom:4px;">Email OTP Code</div>
                                <span style="font-size:12px;color:var(--fg2);">Receive a 6-digit one-time code to <?= e($userData['email']) ?> on login.</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="card-dark" style="padding:18px;border:1px solid rgba(255,255,255,0.1);border-radius:12px;cursor:pointer;display:block;height:100%;" onclick="select2faType('both')">
                                <input type="radio" name="two_factor_type" value="both" style="margin-bottom:10px;">
                                <div style="font-weight:700;color:#fff;font-size:14px;margin-bottom:4px;">Flexible (Both)</div>
                                <span style="font-size:12px;color:var(--fg2);">Authenticate using Authenticator App with email code backup.</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Google Authenticator QR Section -->
                <div id="totpSection" style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:24px;margin-bottom:28px;">
                    <div class="row align-items-center g-4">
                        <div class="col-md-4 text-center">
                            <div style="background:#fff;padding:12px;border-radius:14px;display:inline-block;box-shadow:0 8px 30px rgba(0,0,0,0.5);">
                                <img id="qrCodeImage" src="<?= e($qrCodeUrl) ?>" alt="Google Authenticator QR Code" style="width:160px;height:160px;display:block;" onerror="renderJsQrFallback()">
                                <div id="qrCodeCanvas" style="display:none;width:160px;height:160px;"></div>
                            </div>
                            <div style="font-size:12px;color:var(--fg2);margin-top:10px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:6px;">
                                <i class="ph ph-qr-code text-accent"></i> Real-Time TOTP QR
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge-status badge-active" style="font-size:11px;padding:3px 10px;">
                                    <i class="ph ph-broadcast"></i> Live TOTP (RFC 6238)
                                </span>
                                <span id="totpClockSync" style="font-size:12px;color:var(--fg3);">
                                    Cycle: <span id="totpCycleCountdown" style="color:var(--accent);font-weight:700;">30</span>s
                                </span>
                            </div>
                            <h5 style="color:#fff;font-weight:700;font-size:16px;margin-bottom:8px;">Scan QR Code or Enter Manual Secret Key</h5>
                            <p style="color:var(--fg2);font-size:13px;line-height:1.6;margin-bottom:14px;">
                                1. Open <b>Google Authenticator</b>, <b>Authy</b>, <b>1Password</b>, or <b>Microsoft Authenticator</b> on your device.<br>
                                2. Tap <b>Add Account (+)</b> &rarr; Scan the QR code or manually enter the Base32 security key:
                            </p>
                            <div style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:10px;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;max-width:420px;">
                                <span style="font-family:'JetBrains Mono', monospace;font-weight:700;color:var(--accent);font-size:15px;letter-spacing:1px;"><?= e($setupSecret) ?></span>
                                <button type="button" class="btn-outline-dark btn-sm" id="copySecretBtn" onclick="copySecretKey('<?= e($setupSecret) ?>')">
                                    <i class="ph ph-copy me-1"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email OTP Test Section (hidden by default unless email selected) -->
                <div id="emailOtpSection" style="background:#080c14;border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:24px;margin-bottom:28px;display:none;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                        <div>
                            <h5 style="color:#fff;font-weight:700;font-size:15px;margin-bottom:4px;">Email Code Verification</h5>
                            <p style="color:var(--fg2);font-size:13px;margin:0;">Click below to dispatch a test 6-digit confirmation code to <?= e($userData['email']) ?>.</p>
                        </div>
                        <button type="submit" form="sendEmailForm" class="btn-outline-dark btn-sm">
                            <i class="ph ph-paper-plane-tilt me-1"></i> Send Test Code to Email
                        </button>
                    </div>
                </div>

                <!-- Step 2: Verification Code Confirmation -->
                <div style="margin-bottom:28px;max-width:400px;">
                    <label for="verification_code" class="form-label" style="font-weight:700;color:#fff;font-size:13px;">2. Enter 6-Digit Authenticator Code to Confirm</label>
                    <input type="text" id="verification_code" name="verification_code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" class="form-control" required placeholder="000000" style="background:#080c14;border:1px solid var(--accent);color:#fff;font-size:24px;font-family:'JetBrains Mono', monospace;letter-spacing:8px;text-align:center;padding:12px;">
                    <span style="font-size:11px;color:var(--fg3);margin-top:6px;display:block;">Codes refresh automatically every 30 seconds in your authenticator app.</span>
                </div>

                <button type="submit" class="btn-accent" style="padding:14px 36px;font-size:15px;font-weight:700;border-radius:10px;">
                    <i class="ph ph-shield-check me-2"></i> Activate Real-Time Two-Factor Authentication
                </button>
            </form>

            <form method="POST" id="sendEmailForm" style="display:none;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_test_email_code">
            </form>
        </div>
    <?php else: ?>
        <!-- Manage / Disable 2FA Form -->
        <div class="card-dark" style="padding:32px;border:1px solid rgba(244,63,94,0.3);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="width:42px;height:42px;border-radius:10px;background:rgba(244,63,94,0.15);display:flex;align-items:center;justify-content:center;color:#f43f5e;">
                    <i class="ph ph-shield-warning" style="font-size:24px;"></i>
                </div>
                <div>
                    <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">Disable Two-Factor Authentication</h4>
                    <p style="color:var(--fg2);font-size:13px;margin:2px 0 0;">Enter your account password to confirm removal of 2FA protection.</p>
                </div>
            </div>

            <form method="POST" style="max-width:440px;" onsubmit="return confirm('Are you sure you want to disable 2FA? Your account will be less secure.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disable_2fa">

                <div class="mb-4">
                    <label for="password" class="form-label" style="font-size:13px;color:var(--fg);">Current Account Password</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Enter password to confirm" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px;">
                </div>

                <button type="submit" class="btn-outline-dark" style="color:#f43f5e;border-color:rgba(244,63,94,0.4);padding:10px 24px;font-weight:600;">
                    <i class="ph ph-lock-key-open me-1"></i> Disable 2FA Protection
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Client-side QRCode JS fallback -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function select2faType(type) {
    const totpSec = document.getElementById('totpSection');
    const emailSec = document.getElementById('emailOtpSection');
    if (type === 'email') {
        if (totpSec) totpSec.style.display = 'none';
        if (emailSec) emailSec.style.display = 'block';
    } else if (type === 'totp') {
        if (totpSec) totpSec.style.display = 'block';
        if (emailSec) emailSec.style.display = 'none';
    } else {
        if (totpSec) totpSec.style.display = 'block';
        if (emailSec) emailSec.style.display = 'block';
    }
}

function renderJsQrFallback() {
    const img = document.getElementById('qrCodeImage');
    const canvasBox = document.getElementById('qrCodeCanvas');
    if (img && canvasBox) {
        img.style.display = 'none';
        canvasBox.style.display = 'block';
        canvasBox.innerHTML = '';
        try {
            new QRCode(canvasBox, {
                text: <?= json_encode($otpauthUri) ?>,
                width: 160,
                height: 160,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } catch (e) {
            console.error("QR Code JS fallback error", e);
        }
    }
}

function copySecretKey(secret) {
    navigator.clipboard.writeText(secret).then(() => {
        const btn = document.getElementById('copySecretBtn');
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="ph ph-check text-accent me-1"></i> Copied!';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
        }
    });
}

// Live 30-second TOTP cycle countdown
setInterval(() => {
    const now = Math.floor(Date.now() / 1000);
    const rem = 30 - (now % 30);
    const el = document.getElementById('totpCycleCountdown');
    if (el) el.textContent = rem;
}, 1000);
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
