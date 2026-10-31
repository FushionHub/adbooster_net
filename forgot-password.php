<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/email_service.php';

if (is_logged_in()) {
    redirect(url('dashboard'));
}

$errors = [];
$success = '';
$step = isset($_SESSION['reset_step']) ? (int)$_SESSION['reset_step'] : 1;
$resetEmail = $_SESSION['reset_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } elseif (!check_rate_limit('password_reset', 8, 300)) {
        $errors[] = 'Too many attempts. Please wait 5 minutes and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // Step 1: Request reset code
        if ($action === 'request_code') {
            $email = trim(strtolower($_POST['email'] ?? ''));
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } else {
                $user = db()->fetchOne("SELECT id, full_name, email FROM users WHERE email = ? AND status = 'active'", [$email]);
                if ($user) {
                    $code = (string)random_int(100000, 999999);
                    $expires = date('Y-m-d H:i:s', time() + 900); // 15 mins

                    // Save code in user table if column exists, or store in session
                    try {
                        db()->update('users', [
                            'email_2fa_code'       => $code,
                            'email_2fa_expires_at' => $expires,
                        ], 'id = ?', [(int)$user['id']]);
                    } catch (\Throwable $e) {}

                    $_SESSION['reset_user_id'] = (int)$user['id'];
                    $_SESSION['reset_code'] = $code;
                    $_SESSION['reset_expires'] = time() + 900;
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_step'] = 2;
                    $step = 2;
                    $resetEmail = $email;

                    EmailService::sendTransactional($email, 'password_reset', [
                        'name' => $user['full_name'] ?: 'Adsbooster User',
                        'code' => $code,
                    ]);

                    $success = "A 6-digit reset code has been sent to {$email}. Please check your inbox.";
                } else {
                    // Ambiguous message for security
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_step'] = 2;
                    $step = 2;
                    $resetEmail = $email;
                    $success = "If an account exists with {$email}, a 6-digit reset code has been sent.";
                }
            }
        }

        // Step 2: Verify code & reset password
        elseif ($action === 'reset_password') {
            $code = trim($_POST['code'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $userId = (int)($_SESSION['reset_user_id'] ?? 0);

            if (empty($code) || strlen($code) < 6) {
                $errors[] = 'Please enter the 6-digit verification code.';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            } else {
                $isValid = false;
                $user = null;

                if ($userId > 0) {
                    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
                    if ($user && !empty($user['email_2fa_code']) && hash_equals((string)$user['email_2fa_code'], $code)) {
                        if (strtotime($user['email_2fa_expires_at'] ?? 'now') >= time()) {
                            $isValid = true;
                        } else {
                            $errors[] = 'The verification code has expired. Please request a new one.';
                        }
                    }
                }

                // Fallback session check
                if (!$isValid && !empty($_SESSION['reset_code']) && hash_equals((string)$_SESSION['reset_code'], $code)) {
                    if (($_SESSION['reset_expires'] ?? 0) >= time()) {
                        $isValid = true;
                    } else {
                        $errors[] = 'The verification code has expired. Please request a new one.';
                    }
                }

                if ($isValid && $user) {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    db()->update('users', [
                        'password'             => $hash,
                        'email_2fa_code'       => null,
                        'email_2fa_expires_at' => null,
                        'remember_token'       => null,
                    ], 'id = ?', [(int)$user['id']]);

                    unset($_SESSION['reset_step'], $_SESSION['reset_user_id'], $_SESSION['reset_code'], $_SESSION['reset_expires'], $_SESSION['reset_email']);

                    set_flash('success', 'Your password has been successfully reset! You can now log in.');
                    redirect(url('login'));
                } elseif (empty($errors)) {
                    $errors[] = 'Invalid verification code. Please check and try again.';
                }
            }
        }
    }
}

$pageTitle = 'Reset Password â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height:80vh;display:flex;align-items:center;">
    <div class="container" style="max-width:480px;">
        <div class="text-center tw-mb-8">
            <div class="tw-mb-4">
                <div class="tw-w-16 tw-h-16 tw-rounded-lg bg-main-600 d-inline-flex align-items-center justify-content-center">
                    <i class="ph-bold ph-lock-key text-dark tw-text-3xl"></i>
                </div>
            </div>
            <h1 class="h2 text-white fw-bold">Reset Password</h1>
            <p class="text-white-50 tw-mt-2">Recover access to your <?= e(get_setting('site_name', 'Adsbooster.net')) ?> account</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 text-success tw-p-4 tw-rounded-lg tw-mb-6" role="alert">
                <div class="d-flex align-items-center tw-gap-2">
                    <i class="ph-bold ph-check-circle tw-text-lg"></i>
                    <span><?= e($success) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger tw-p-4 tw-rounded-lg tw-mb-6" role="alert">
                <ul class="tw-m-0 tw-ps-4">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card bg-white-06 border border-neutral-800 tw-rounded-xl tw-p-6">
            <?php if ($step === 1): ?>
                <form method="POST" action="<?= url('forgot-password') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="request_code">

                    <p class="text-white-50 tw-text-sm tw-mb-4">
                        Enter your registered account email address. We'll send you a single-use 6-digit verification code to reset your password.
                    </p>

                    <div class="tw-mb-5">
                        <label for="email" class="form-label text-white fw-medium">Email Address</label>
                        <input type="email" id="email" name="email"
                            class="form-control tw-p-3 tw-rounded-lg bg-dark text-white border-neutral-700"
                            placeholder="name@example.com" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-main w-100 rounded-pill tw-py-3 fw-semibold text-dark">
                        Send Verification Code &rarr;
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="<?= url('forgot-password') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">

                    <div class="d-flex justify-content-between align-items-center tw-mb-4 pb-2 border-bottom border-neutral-800">
                        <span class="text-white-50 tw-text-xs">Resetting for: <strong class="text-white"><?= e($resetEmail) ?></strong></span>
                        <a href="<?= url('forgot-password') ?>?action=restart" onclick="<?php unset($_SESSION['reset_step']); ?>" class="text-main-600 tw-text-xs text-decoration-underline">Change Email</a>
                    </div>

                    <div class="tw-mb-4">
                        <label for="code" class="form-label text-white fw-medium">6-Digit Code</label>
                        <input type="text" id="code" name="code" maxlength="6" pattern="[0-9]{6}"
                            class="form-control tw-p-3 tw-rounded-lg bg-dark text-white border-neutral-700 text-center fw-bold font-monospace"
                            placeholder="123456" style="letter-spacing: 6px; font-size: 20px;" required autofocus>
                    </div>

                    <div class="tw-mb-4">
                        <label for="new_password" class="form-label text-white fw-medium">New Password</label>
                        <input type="password" id="new_password" name="new_password" minlength="8"
                            class="form-control tw-p-3 tw-rounded-lg bg-dark text-white border-neutral-700"
                            placeholder="At least 8 characters" required>
                    </div>

                    <div class="tw-mb-5">
                        <label for="confirm_password" class="form-label text-white fw-medium">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8"
                            class="form-control tw-p-3 tw-rounded-lg bg-dark text-white border-neutral-700"
                            placeholder="Repeat new password" required>
                    </div>

                    <button type="submit" class="btn btn-main w-100 rounded-pill tw-py-3 fw-semibold text-dark">
                        Save New Password &amp; Log In
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="text-center tw-mt-6">
            <a href="<?= url('login') ?>" class="text-white-50 hover-text-main-600 tw-text-sm d-inline-flex align-items-center tw-gap-1">
                <i class="ph ph-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
