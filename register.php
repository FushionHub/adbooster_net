<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$errors = [];
$old = ['full_name' => '', 'email' => ''];

$inviteToken = $_GET['invite'] ?? '';
$inviteData  = null;
if ($inviteToken !== '') {
    $inviteData = db()->fetch(
        "SELECT * FROM team_invitations WHERE invite_token = ? AND status = 'pending' AND (expires_at IS NULL OR expires_at > NOW())",
        [$inviteToken]
    );
}

// Referral attribution tracking
$refCode = trim($_GET['ref'] ?? ($_SESSION['ref_code'] ?? ($_COOKIE['ref_code'] ?? '')));
if (!empty($_GET['ref'])) {
    $_SESSION['ref_code'] = $refCode;
    setcookie('ref_code', $refCode, time() + (86400 * 30), '/');
}
$referrerUser = null;
if ($refCode !== '') {
    $referrerUser = db()->fetch("SELECT id, full_name FROM users WHERE referral_code = ?", [$refCode]);
}

// Admins can close registration from Settings → Site Access. Team-invite
// signups are always allowed so invited members can still join.
$registrationClosed = (get_setting('registration_open', '1') === '0') && !$inviteData;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($registrationClosed) {
        $errors[] = 'Registration is currently closed. Please check back later or contact support.';
    } elseif (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $fullName    = trim($_POST['full_name'] ?? '');
        $email       = trim(strtolower($_POST['email'] ?? ''));
        $password    = $_POST['password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        $acceptTerms = !empty($_POST['accept_terms']);

        $old['full_name'] = $fullName;
        $old['email']     = $email;

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $exists = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if ($exists) {
                $errors[] = 'An account with that email already exists.';
            }
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirmPass) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$acceptTerms) {
            $errors[] = 'You must accept the Terms of Service and Privacy Policy.';
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $referralCode   = bin2hex(random_bytes(8));

            $userId = db()->insert('users', [
                'full_name'          => $fullName,
                'email'              => $email,
                'password'           => $hashedPassword,
                'role'               => 'user',
                'account_type'       => 'individual',
                'referral_code'      => $referralCode,
                'onboarding_completed' => 0,
                'status'             => 'active',
                'ai_credits'         => max(0, (int)get_setting('signup_ai_credits', '100')),
            ]);

            // Link referral if registered via referral code
            if ($referrerUser) {
                try {
                    db()->update('users', ['referred_by' => (int)$referrerUser['id']], 'id = ?', [$userId]);
                    db()->insert('referrals', [
                        'referrer_id'    => (int)$referrerUser['id'],
                        'referred_id'    => $userId,
                        'status'         => 'pending',
                        'reward_credits' => 10.00,
                    ]);
                } catch (\Throwable $refInsertErr) {
                    error_log("Referral link error: " . $refInsertErr->getMessage());
                }
            }

            if ($inviteData) {
                db()->update('users', [
                    'parent_user_id' => (int)$inviteData['owner_id'],
                    'account_type'   => 'team_member',
                ], 'id = ?', [$userId]);

                db()->update('team_invitations', [
                    'status' => 'accepted',
                ], 'id = ?', [$inviteData['id']]);
            }

            $siteName = get_setting('site_name', 'Adsbooster.net');
            $subject  = "Welcome to {$siteName}!";
            $body     = "Hi {$fullName},\n\nYour account has been created. Start exploring your dashboard:\n\n" . BASE_URL . "/dashboard.php\n\nâ€“ The {$siteName} Team";
            mail($email, $subject, $body);

            auth_login(db()->fetch("SELECT * FROM users WHERE id = ?", [$userId]));

            redirect(url('onboarding'));
        }
    }
}

$pageTitle = 'Register â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height:80vh;display:flex;align-items:center;">
    <div class="container" style="max-width:480px;">
        <div class="text-center tw-mb-10">
            <h1 class="h2 text-white fw-bold">Create Your Account</h1>
            <?php if ($inviteData): ?>
                <p class="text-white-50 tw-mt-2">You've been invited to join a team on <?= e(get_setting('site_name', 'Adsbooster.net')) ?>.</p>
            <?php elseif ($referrerUser): ?>
                <div class="alert" style="background:rgba(200,255,0,0.1);border:1px solid rgba(200,255,0,0.3);color:#c8ff00;border-radius:10px;padding:12px;margin-top:14px;font-size:13px;">
                    <i class="ph ph-gift" style="font-size:16px;vertical-align:-2px;margin-right:4px"></i>
                    Referred by <b><?= htmlspecialchars($referrerUser['full_name']) ?></b>! Bonus ad credits unlocked on your first top-up.
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success bg-success-100 text-success-700 border-0 tw-p-4 tw-rounded-lg tw-mb-6" role="alert">
                <?= e($_SESSION['flash_success']) ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
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

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger bg-danger-100 text-danger-700 border-0 tw-p-4 tw-rounded-lg tw-mb-6" role="alert">
                <?= e($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if ($registrationClosed): ?>
            <div class="alert" style="background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.4);color:#fbbf24;border-radius:14px;padding:26px 22px;text-align:center;" role="alert">
                <i class="ph-bold ph-lock" style="font-size:30px;display:block;margin-bottom:10px;"></i>
                <h5 class="fw-bold mb-2">Registration is currently closed</h5>
                <p class="mb-3" style="font-size:13.5px;opacity:.9;">New accounts are temporarily disabled. Please check back later or <a href="<?= url('contact') ?>" style="color:#fff;text-decoration:underline;">contact support</a> for early access.</p>
                <a href="<?= url('login') ?>" class="btn btn-main rounded-pill px-4 py-2 fw-semibold">Log in instead</a>
            </div>
        <?php else: ?>
        <form method="POST" action="<?= url('register') . ($inviteToken ? '?invite=' . e($inviteToken) : '') ?>" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="ref_code" value="<?= htmlspecialchars($refCode) ?>">

            <div class="tw-mb-5">
                <label for="full_name" class="form-label text-white fw-medium">Full Name</label>
                <input type="text" id="full_name" name="full_name"
                    class="form-control tw-p-3 tw-rounded-lg bg-dark-900 text-white border-neutral-700"
                    value="<?= e($old['full_name']) ?>" required autofocus>
            </div>

            <div class="tw-mb-5">
                <label for="email" class="form-label text-white fw-medium">Email Address</label>
                <input type="email" id="email" name="email"
                    class="form-control tw-p-3 tw-rounded-lg bg-dark-900 text-white border-neutral-700"
                    value="<?= e($old['email']) ?>" required>
            </div>

            <div class="tw-mb-5">
                <label for="password" class="form-label text-white fw-medium">Password</label>
                <input type="password" id="password" name="password"
                    class="form-control tw-p-3 tw-rounded-lg bg-dark-900 text-white border-neutral-700"
                    minlength="8" required>
                <small class="text-white-50">At least 8 characters</small>
            </div>

            <div class="tw-mb-5">
                <label for="confirm_password" class="form-label text-white fw-medium">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                    class="form-control tw-p-3 tw-rounded-lg bg-dark-900 text-white border-neutral-700"
                    minlength="8" required>
            </div>

            <div class="tw-mb-6">
                <div class="form-check">
                    <input type="checkbox" id="accept_terms" name="accept_terms" value="1"
                        class="form-check-input tw-border-neutral-700" required>
                    <label for="accept_terms" class="form-check-label text-white-50 tw-text-sm">
                        I agree to the <a href="<?= url('terms') ?>" class="text-main-600 text-decoration-underline" target="_blank">Terms of Service</a>
                        and <a href="<?= url('privacy') ?>" class="text-main-600 text-decoration-underline" target="_blank">Privacy Policy</a>
                    </label>
                </div>
            </div>

            <button type="submit"
                class="btn btn-main hover-style-one button--stroke active-scale-094 tw-duration-100 w-100 d-inline-flex align-items-center justify-content-center tw-gap-205 active--translate-y-2 rounded-pill tw-py-3 fw-semibold">
                <span class="button__flair"></span>
                <span class="button__label">Create Account</span>
                <!-- <span class="btn-icon tw-w-10 tw-h-10 bg-white text-main-600 tw-text-base rounded-circle d-flex justify-content-center align-items-center position-relative tw-duration-500">
                    <i class="ph-bold ph-arrow-right tw-rotate--45-deg"></i>
                </span> -->
            </button>
        </form>

        <div class="tw-mt-6 text-center">
            <div class="d-flex align-items-center tw-gap-4 tw-mb-4">
                <hr class="border-neutral-700 flex-grow-1">
                <span class="text-white-50 tw-text-sm">or</span>
                <hr class="border-neutral-700 flex-grow-1">
            </div>

            <a href="<?= BASE_URL ?>/modules/google_auth.php?action=register"
                class="btn bg-white text-dark tw-py-3 w-100 rounded-pill fw-semibold tw-mb-4 d-inline-flex align-items-center justify-content-center tw-gap-2">
                <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                Sign up with Google
            </a>

            <p class="text-white-50 tw-mt-6">
                Already have an account?
                <a href="<?= url('login') ?>" class="text-main-600 fw-semibold text-decoration-underline">Log in</a>
            </p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
