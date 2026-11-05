<?php declare(strict_types=1);
/**
 * Temporary admin credential helper.
 *
 *   php database/admin_temp_login.php set    <password>   -> set a temp password
 *   php database/admin_temp_login.php restore            -> restore the original hash
 *
 * The original bcrypt hash and 2FA state are kept in cache/ so the admin
 * account is left exactly as it was found.
 */
require_once __DIR__ . '/../config/site_setting_and_config.php';

$cmd  = $argv[1] ?? 'restore';
$file = ROOT_PATH . '/cache/admin_temp_login_state.json';

$admin = db()->fetchOne(
    "SELECT id, email, password, two_factor_enabled, totp_enabled FROM users WHERE role='admin' ORDER BY id LIMIT 1"
);
if (!$admin) {
    fwrite(STDERR, "No admin user found.\n");
    exit(1);
}
$adminId = (int)$admin['id'];

if ($cmd === 'set') {
    $password = $argv[2] ?? 'Adm!nSweep2026';
    $state = [
        'password'           => (string)$admin['password'],
        'two_factor_enabled' => $admin['two_factor_enabled'],
        'totp_enabled'       => $admin['totp_enabled'],
    ];
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    file_put_contents($file, json_encode($state));

    db()->query(
        "UPDATE users SET password = ?, two_factor_enabled = 0, totp_enabled = 0 WHERE id = ?",
        [password_hash($password, PASSWORD_BCRYPT), $adminId]
    );
    foreach (glob(ROOT_PATH . '/cache/rate_limit/*.json') ?: [] as $f) {
        @unlink($f);
    }
    echo "Temp password set for {$admin['email']}\n";
    exit(0);
}

if ($cmd === 'restore') {
    if (!is_file($file)) {
        echo "No saved state — nothing to restore.\n";
        exit(0);
    }
    $state = json_decode((string)file_get_contents($file), true) ?: [];
    db()->query(
        "UPDATE users SET password = ?, two_factor_enabled = ?, totp_enabled = ? WHERE id = ?",
        [
            (string)($state['password'] ?? $admin['password']),
            $state['two_factor_enabled'] ?? $admin['two_factor_enabled'],
            $state['totp_enabled'] ?? $admin['totp_enabled'],
            $adminId,
        ]
    );
    @unlink($file);
    echo "Admin credentials restored.\n";
    exit(0);
}

fwrite(STDERR, "Unknown command.\n");
exit(1);
