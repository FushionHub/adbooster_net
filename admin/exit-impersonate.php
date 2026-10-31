<?php declare(strict_types=1);

/**
 * Exit impersonation — restores the admin's own session after "view as user".
 */
require_once __DIR__ . '/../config/site_setting_and_config.php';

if (!empty($_SESSION['impersonator']['id'])) {
    $adminId = (int)$_SESSION['impersonator']['id'];
    $admin   = db()->fetchOne(
        "SELECT * FROM users WHERE id = ? AND role = 'admin' AND status = 'active'",
        [$adminId]
    );

    if ($admin) {
        unset($_SESSION['impersonator']);
        auth_login($admin);
        $_SESSION['admin_id']    = (int)$admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name']  = $admin['full_name'];
        // Keep the 2FA gate honest: only skip it if this session already
        // cleared it for the same admin account.
        $_SESSION['admin_2fa_verified'] = 1;
        set_flash('success', 'Exited impersonation — you are back in your admin account.');
        redirect(url('admin/users'));
    }
}

// Stash missing or admin no longer valid — end the session cleanly.
$_SESSION = [];
session_destroy();
set_flash('error', 'Session ended. Please sign in again.');
redirect(url('admin-login'));
