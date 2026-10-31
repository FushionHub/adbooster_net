<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

if (is_logged_in()) {
    redirect(url('dashboard'));
}

$errors  = [];
$old     = ['email' => '', 'remember' => 0];
$success = '';

$inviteToken = $_GET['invite'] ?? '';
$inviteData  = null;
if ($inviteToken !== '') {
    $inviteData = db()->fetch(
        "SELECT * FROM team_invitations WHERE invite_token = ? AND status = 'pending' AND (expires_at IS NULL OR expires_at > NOW())",
        [$inviteToken]
    );
}

if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } elseif (!check_rate_limit('login', 5, 300)) {
        $errors[] = 'Too many login attempts. Please wait a few minutes and try again.';
    } else {
        $email      = trim(strtolower($_POST['email'] ?? ''));
        $password   = $_POST['password'] ?? '';
        $remember   = !empty($_POST['remember']);

        $old['email']    = $email;
        $old['remember'] = $remember ? 1 : 0;

        if ($email === '' || $password === '') {
            $errors[] = 'Please enter both email and password.';
        } else {
            $user = db()->fetch("SELECT * FROM users WHERE email = ?", [$email]);

            if (!$user || !password_verify($password, $user['password'] ?? '')) {
                $errors[] = 'Invalid email or password.';
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'Your account has been suspended. Please contact support.';
            } else {
                // If 2FA is enabled, route to two-factor-verify
                if (!empty($user['two_factor_enabled'])) {
                    $_SESSION['2fa_pending_user_id'] = (int)$user['id'];
                    $_SESSION['2fa_remember'] = $remember ? 1 : 0;
                    if (!empty($inviteToken)) {
                        $_SESSION['2fa_invite_token'] = $inviteToken;
                    }
                    redirect(url('two-factor-verify'));
                }

                auth_login($user);

                if ($remember) {
                    issue_remember_token((int)$user['id']);
                }

                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                require_once __DIR__ . '/modules/email_service.php';
                EmailService::sendTransactional($user['email'], 'login_alert', [
                    'name'     => $user['full_name'] ?: 'User',
                    'ip'       => $ip,
                    'time'     => date('Y-m-d H:i:s T'),
                    'location' => 'Web App Session',
                ]);

                redirect(url('dashboard'));
            }
        }
    }
}

if (!empty($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$pageTitle = 'Login — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height:80vh;display:flex;align-items:center;">
    <div class="container" style="max-width:480px;">
        <div class="text-center tw-mb-10">
            <h1 class="h2 text-white fw-bold">Welcome Back</h1>
            <p class="text-white-50 tw-mt-2">Log in to your <?= e(get_setting('site_name', 'Adsbooster.net')) ?> account</p>
        </div>

        <?php if ($inviteData): ?>
            <div class="alert bg-main-600 bg-opacity-10 border border-main-600 border-opacity-25 text-white tw-p-4 tw-rounded-lg tw-mb-6" role="alert">
                <div class="d-flex align-items-center tw-gap-3">
                    <i class="ph-bold ph-users tw-text-xl text-main-600"></i>
                    <div>
                        <strong>You've been invited to join a team!</strong>
                        <p class="tw-mb-0 tw-mt-1 tw-text-sm text-white-50">Log in or register to accept the invitation.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success bg-success-100 text-success-700 border-0 tw-p-4 tw-rounded-lg tw-mb-6" role="alert">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger bg-danger-100 text-danger-700 border-0 tw-p-4 tw-rounded-lg tw-mb-6" role="alert">
                <ul class="tw-m-0 tw-ps-4">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('login') ?><?= $inviteToken ? '?invite=' . e($inviteToken) : '' ?>" class="auth-form">
            <?= csrf_field() ?>
            <?php if ($inviteToken): ?>
                <input type="hidden" name="invite_token" value="<?= e($inviteToken) ?>">
            <?php endif; ?>

            <div class="tw-mb-5">
                <label for="email" class="form-label text-white fw-medium">Email Address</label>
                <input type="email" id="email" name="email"
                    class="form-control tw-p-3 tw-rounded-lg bg-dark-900 text-white border-neutral-700"
                    value="<?= e($old['email']) ?>" required autofocus>
            </div>

            <div class="tw-mb-5">
                <label for="password" class="form-label text-white fw-medium">Password</label>
                <input type="password" id="password" name="password"
                    class="form-control tw-p-3 tw-rounded-lg bg-dark-900 text-white border-neutral-700"
                    required>
            </div>

            <div class="d-flex align-items-center justify-content-between tw-mb-6">
                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember" value="1"
                        class="form-check-input tw-border-neutral-700"
                        <?= $old['remember'] ? 'checked' : '' ?>>
                    <label for="remember" class="form-check-label text-white-50 tw-text-sm">Remember me</label>
                </div>
                <a href="<?= url('forgot-password') ?>" class="text-main-600 tw-text-sm fw-medium text-decoration-underline">
                    Forgot password?
                </a>
            </div>

            <button type="submit"
                class="btn btn-main hover-style-one button--stroke active-scale-094 tw-duration-100 w-100 d-inline-flex align-items-center justify-content-center tw-gap-205 active--translate-y-2 rounded-pill tw-py-3 fw-semibold">
                <span class="button__flair"></span>
                <span class="button__label">Log In</span>
            </button>
        </form>

        <div class="tw-mt-6 text-center">
            <div class="d-flex align-items-center tw-gap-4 tw-mb-4">
                <hr class="border-neutral-700 flex-grow-1">
                <span class="text-white-50 tw-text-sm">or</span>
                <hr class="border-neutral-700 flex-grow-1">
            </div>

            <a href="<?= BASE_URL ?>/modules/google_auth.php?action=login"
                class="btn bg-white text-dark tw-py-3 w-100 rounded-pill fw-semibold tw-mb-4 d-inline-flex align-items-center justify-content-center tw-gap-2">
                <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                Sign in with Google
            </a>

            <p class="text-white-50 tw-mt-6">
                Don't have an account?
                <a href="<?= url('register') ?>" class="text-main-600 fw-semibold text-decoration-underline">Register</a>
            </p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
