<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$errors  = [];
$old     = ['email' => ''];
$success = '';

if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if (!empty($_SESSION['admin_id'])) {
    redirect(url('admin/index'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } elseif (!check_rate_limit('admin_login', 5, 300)) {
        $errors[] = 'Too many login attempts. Please wait a few minutes and try again.';
    } else {
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        $old['email'] = $email;

        if ($email === '' || $password === '') {
            $errors[] = 'Please enter both email and password.';
        } else {
            $admin = db()->fetch(
                "SELECT * FROM users WHERE email = ? AND role = 'admin'",
                [$email]
            );

            if (!$admin || !password_verify($password, $admin['password'] ?? '')) {
                $errors[] = 'Invalid admin credentials.';
            } elseif ($admin['status'] !== 'active') {
                $errors[] = 'This admin account has been suspended.';
            } else {
                if (!empty($admin['totp_enabled']) || !empty($admin['two_factor_enabled'])) {
                    $_SESSION['admin_pending_totp'] = (int)$admin['id'];
                    redirect(url('admin/totp-verify'));
                }

                auth_admin_login($admin);
                redirect(url('admin/index'));
            }
        }
    }
}

if (!empty($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$pageTitle = 'Admin Login â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height:80vh;display:flex;align-items:center;">
    <div class="container" style="max-width:440px;">
        <div class="text-center tw-mb-10">
            <div class="tw-mb-4">
                <div class="tw-w-16 tw-h-16 tw-rounded-lg bg-main-600 d-inline-flex align-items-center justify-content-center">
                    <i class="ph-bold ph-shield-check text-white tw-text-3xl"></i>
                </div>
            </div>
            <h1 class="h2 text-white fw-bold">Admin Portal</h1>
            <p class="text-white-50 tw-mt-2">Authorized personnel only</p>
        </div>

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

        <form method="POST" action="<?= url('admin-login') ?>" class="auth-form">
            <?= csrf_field() ?>

            <div class="tw-mb-5">
                <label for="email" class="form-label text-white fw-medium">Admin Email</label>
                <input type="email" id="email" name="email"
                    class="form-control tw-p-3 tw-rounded-lg bg-dark-900 text-white border-neutral-700"
                    value="<?= e($old['email']) ?>" required autofocus>
            </div>

            <div class="tw-mb-6">
                <label for="password" class="form-label text-white fw-medium">Password</label>
                <input type="password" id="password" name="password"
                    class="form-control tw-p-3 tw-rounded-lg bg-dark-900 text-white border-neutral-700"
                    required>
            </div>

            <button type="submit"
                class="btn btn-main hover-style-one button--stroke active-scale-094 tw-duration-100 w-100 d-inline-flex align-items-center justify-content-center tw-gap-205 active--translate-y-2 rounded-pill tw-py-3 fw-semibold">
                <span class="button__flair"></span>
                <span class="button__label">Access Admin Panel</span>
                <span class="btn-icon tw-w-10 tw-h-10 bg-white text-main-600 tw-text-base rounded-circle d-flex justify-content-center align-items-center position-relative tw-duration-500">
                    <i class="ph-bold ph-arrow-right tw-rotate--45-deg"></i>
                </span>
            </button>
        </form>

        <p class="text-center text-white-50 tw-mt-6 tw-text-sm">
            <a href="<?= url('login') ?>" class="text-white-50 hover-text-main-600">
                <i class="ph ph-arrow-left tw-me-1"></i> Back to user login
            </a>
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
