<?php
declare(strict_types=1);

/**
 * Admin Authentication Guard
 * Adsbooster.net
 *
 * Protects everything under /admin/. Requires an authenticated session with
 * role = 'admin' and a completed 2FA challenge.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

// â”€â”€ 1. Must be an authenticated admin â”€â”€
if (!is_logged_in() || current_user_role() !== 'admin') {
    set_flash('error', 'Unauthorized access. Admin login required.');
    redirect(url('admin-login'));
}

// â”€â”€ 2. Account must still be active â”€â”€
$adminRow = db()->fetchOne(
    "SELECT id, status, two_factor_enabled, totp_enabled FROM users WHERE id = ? AND role = 'admin'",
    [current_user_id()]
);

if (!$adminRow || $adminRow['status'] !== 'active') {
    $_SESSION = [];
    session_destroy();
    set_flash('error', 'Admin account has been suspended.');
    redirect(url('admin-login'));
}

// â”€â”€ 3. Enforce two-factor for every admin â”€â”€
// Columns differ across releases (`two_factor_enabled` is the canonical one,
// `totp_enabled` predates it) so both are accepted.
$adminHas2FA = !empty($adminRow['two_factor_enabled']) || !empty($adminRow['totp_enabled']);

if ($adminHas2FA && empty($_SESSION['admin_2fa_verified'])) {
    // Remember where we were headed so 2FA can return the user there.
    if (empty($_SESSION['admin_post_2fa_url'])) {
        $_SESSION['admin_post_2fa_url'] = $_SERVER['REQUEST_URI'] ?? '/admin/index.php';
    }
    redirect(url('admin/totp-verify'));
}

// Admins without 2FA are forced to enrol rather than run unprotected.
if (!$adminHas2FA && empty($_SESSION['admin_2fa_enrol_shown'])) {
    $_SESSION['admin_2fa_enrol_shown'] = 1;
    set_flash('warning', 'Two-factor authentication is strongly recommended for admin accounts. Set it up in Security Settings.');
}
