<?php
declare(strict_types=1);

/**
 * Authentication Guard
 * Adsbooster.net
 * 
 * Protects pages that require user authentication.
 * Redirects to login.php if not logged in.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access this page.');
    redirect(BASE_URL . '/login');
}

// Check if user account is active
$user = db()->fetchOne(
    "SELECT id, status FROM users WHERE id = ?",
    [current_user_id()]
);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    set_flash('error', 'Your account has been suspended. Please contact support.');
    redirect(BASE_URL . '/login');
}
