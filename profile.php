<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Edit Profile â€” ' . get_setting('site_name', 'Adsbooster.net');

// If viewing another user's public profile or vcard link, delegate to public portfolio
if (!empty($_GET['u']) || !empty($_GET['id'])) {
    require_once __DIR__ . '/portfolio.php';
    exit;
}

$userId = current_user_id();
$user = current_user();
if (!$user) {
    set_flash('error', 'Please log in to access this page.');
    redirect(url('login'));
}

// Detect optional profile columns (migration-safe: users table may not have phone/business_name/country yet)
$existingCols = [];
try {
    $colRows = db()->fetchAll("SHOW COLUMNS FROM users");
    $existingCols = array_column($colRows, 'Field');
} catch (Throwable $e) {
    $existingCols = ['full_name', 'email'];
}
$hasCol = static function (string $col) use ($existingCols): bool {
    return in_array($col, $existingCols, true);
};

$errors = [];

// ---------- Handle profile update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $businessName = trim($_POST['business_name'] ?? '');
        $country = trim($_POST['country'] ?? '');

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }

        if (empty($errors)) {
            $data = ['full_name' => $fullName];
            if ($hasCol('phone')) {
                $data['phone'] = $phone !== '' ? $phone : null;
            }
            if ($hasCol('business_name')) {
                $data['business_name'] = $businessName !== '' ? $businessName : null;
            }
            if ($hasCol('country')) {
                $data['country'] = $country !== '' ? $country : null;
            }
            try {
                db()->update('users', $data, 'id = ?', [$userId]);
                $_SESSION['user_name'] = $fullName;
                try {
                    require_once __DIR__ . '/modules/notification_service.php';
                    NotificationService::create((int)$userId, 'profile', 'Profile Updated', 'Your profile was updated.');
                } catch (Throwable $e) {
                    error_log('Profile notification failed: ' . $e->getMessage());
                }
                set_flash('success', 'Profile updated successfully.');
                redirect(url('profile'));
            } catch (Throwable $e) {
                error_log('Profile update failed: ' . $e->getMessage());
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
        // Refresh user for re-render on error
        $user = current_user() ?: $user;
    }
}

// ---------- Handle password change ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $errors[] = 'All password fields are required.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } elseif (!password_verify($current, (string)($user['password'] ?? ''))) {
            $errors[] = 'Current password is incorrect.';
        } else {
            try {
                db()->update('users', ['password' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$userId]);
                try {
                    require_once __DIR__ . '/modules/notification_service.php';
                    NotificationService::create((int)$userId, 'profile', 'Profile Updated', 'Your profile was updated.');
                } catch (Throwable $e) {
                    error_log('Profile notification failed: ' . $e->getMessage());
                }
                set_flash('success', 'Password changed successfully.');
                redirect(url('profile'));
            } catch (Throwable $e) {
                error_log('Password change failed: ' . $e->getMessage());
                $errors[] = 'Failed to change password. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/includes/app_header.php';
?>

<h1 class="page-title">Edit Profile</h1>
<p class="page-subtitle">Update your personal details and security settings.</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <ul style="margin:0;padding-left:18px">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(200,255,0,.1);color:var(--accent)"><i class="ph ph-user"></i></div>
        <div class="stat-value" style="font-size:18px"><?= e($user['role'] ?? 'user') ?></div>
        <div class="stat-label">Role</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--blue)"><i class="ph ph-sparkle"></i></div>
        <div class="stat-value"><?= number_format((int)($user['ai_credits'] ?? 0)) ?></div>
        <div class="stat-label">AI Credits</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(34,197,94,.1);color:var(--green)"><i class="ph ph-wallet"></i></div>
        <div class="stat-value">$<?= number_format((float)($user['ad_credits'] ?? 0), 2) ?></div>
        <div class="stat-label">Ad Credits</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(168,85,247,.15);color:var(--purple,#a855f7)"><i class="ph ph-share-network"></i></div>
        <div class="stat-value" style="font-size:18px;font-family:monospace"><?= e($user['referral_code'] ?? 'â€”') ?></div>
        <div class="stat-label">Referral Code</div>
    </div>
</div>

<div class="grid-2">
    <div class="card-dark">
        <h5 style="margin-bottom:4px"><i class="ph ph-note-pencil" style="margin-right:8px"></i>Profile Details</h5>
        <p style="font-size:13px;color:var(--fg2);margin-bottom:20px">Keep your contact information up to date.</p>
        <form method="POST" action="<?= url('profile') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <div style="margin-bottom:16px">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= e($user['full_name'] ?? '') ?>" required placeholder="Your full name">
            </div>
            <div style="margin-bottom:16px">
                <label for="email" class="form-label">Email <span class="badge-status badge-inactive" style="margin-left:6px">Locked</span></label>
                <input type="email" id="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" readonly disabled>
                <small class="form-text">Email changes are disabled for security. Contact support to change it.</small>
            </div>
            <div style="margin-bottom:16px">
                <label for="phone" class="form-label">Phone <span style="color:var(--fg3);font-weight:400">(optional)</span></label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= e((string)($user['phone'] ?? '')) ?>" placeholder="e.g. +1 555 000 1234">
            </div>
            <div style="margin-bottom:16px">
                <label for="business_name" class="form-label">Business Name <span style="color:var(--fg3);font-weight:400">(optional)</span></label>
                <input type="text" id="business_name" name="business_name" class="form-control" value="<?= e((string)($user['business_name'] ?? '')) ?>" placeholder="e.g. Acme Inc.">
            </div>
            <div style="margin-bottom:24px">
                <label for="country" class="form-label">Country <span style="color:var(--fg3);font-weight:400">(optional)</span></label>
                <input type="text" id="country" name="country" class="form-control" value="<?= e((string)($user['country'] ?? '')) ?>" placeholder="e.g. United States">
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" class="btn-accent"><i class="ph ph-check"></i>Save Changes</button>
                <a href="<?= url('dashboard') ?>" class="btn-outline-dark">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card-dark">
        <h5 style="margin-bottom:4px"><i class="ph ph-lock" style="margin-right:8px"></i>Change Password</h5>
        <p style="font-size:13px;color:var(--fg2);margin-bottom:20px">Use a strong password with at least 8 characters.</p>
        <form method="POST" action="<?= url('profile') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div style="margin-bottom:16px">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
            </div>
            <div style="margin-bottom:16px">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password" placeholder="Min. 8 characters">
            </div>
            <div style="margin-bottom:24px">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password" placeholder="Repeat new password">
            </div>
            <button type="submit" class="btn-outline-dark"><i class="ph ph-key"></i>Update Password</button>
        </form>
    </div>
</div>

<style>
@media(max-width:767.98px){div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important}}
</style>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
