<?php declare(strict_types=1);

/**
 * Logout
 * Adsbooster.net
 *
 * Destroys session, clears remember me tokens, and redirects to login.
 */

require_once __DIR__ . '/config/site_setting_and_config.php';

$userId = current_user_id();
clear_remember_token($userId);

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

redirect(url('login'));
