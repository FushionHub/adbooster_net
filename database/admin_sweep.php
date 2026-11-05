<?php declare(strict_types=1);
/**
 * Admin sweep — logs in as the admin over HTTP and requests every admin
 * screen, reporting status codes and any PHP diagnostics in the output.
 *
 * Usage: php database/admin_sweep.php [base-url]
 */

$base = $argv[1] ?? 'http://seopp.test';

require_once __DIR__ . '/../config/site_setting_and_config.php';

$admin = db()->fetchOne("SELECT id, email, password, two_factor_enabled, totp_enabled FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
if (!$admin) {
    fwrite(STDERR, "No admin user found.\n");
    exit(1);
}

$adminId  = (int)$admin['id'];
$email    = (string)$admin['email'];
$origHash = (string)$admin['password'];

$tempPassword = 'Adm!nSweep2026';
$tempHash     = password_hash($tempPassword, PASSWORD_BCRYPT);

// ── temporarily swap the password + disable 2FA ─────────────────────────
db()->query(
    "UPDATE users SET password = ?, two_factor_enabled = 0, totp_enabled = 0, status = 'active' WHERE id = ?",
    [$tempHash, $adminId]
);

// Clear the login rate-limit buckets so repeated sweeps are not throttled.
foreach (glob(ROOT_PATH . '/cache/rate_limit/*.json') ?: [] as $f) {
    @unlink($f);
}

$cookieJar = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'adsb_admin_sweep_' . getmypid() . '.txt';
@unlink($cookieJar);

/**
 * HTTP request with a shared cookie jar.
 */
function req(string $url, ?array $post = null): array
{
    global $cookieJar;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 6,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'AdsboosterAdminSweep/1.0',
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);
    curl_close($ch);

    return ['code' => $code, 'body' => $body, 'url' => $final, 'error' => $err];
}

/** Pull the CSRF token out of an HTML page. */
function csrfFrom(string $html): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value="([^"]+)"\s+name="csrf_token"/i', $html, $m)) {
        return $m[1];
    }
    return '';
}

// ── log in ───────────────────────────────────────────────────────────────
$login = req($base . '/admin-login');
$token = csrfFrom($login['body']);
if ($token === '') {
    fwrite(STDERR, "Could not read CSRF token from the login page.\n");
    echo substr($login['body'], 0, 800) . "\n";
    // restore
    db()->query("UPDATE users SET password = ? WHERE id = ?", [$origHash, $adminId]);
    exit(1);
}

$auth = req($base . '/admin-login', [
    'csrf_token' => $token,
    'email'      => $email,
    'password'   => $tempPassword,
]);

if (strpos($auth['url'], '/admin-login') !== false && strpos($auth['url'], '/admin/totp-verify') === false) {
    fwrite(STDERR, "Login failed (landed on {$auth['url']}).\n");
    if (preg_match('/alert-danger.*?<ul>(.*?)<\/ul>/s', $auth['body'], $m)) {
        fwrite(STDERR, strip_tags($m[1]) . "\n");
    }
    db()->query("UPDATE users SET password = ? WHERE id = ?", [$origHash, $adminId]);
    exit(1);
}

echo "Logged in as {$email}\n";
echo str_repeat('-', 78) . "\n";

// ── sweep every admin screen ─────────────────────────────────────────────
$files = [];
foreach (glob(__DIR__ . '/../admin/*.php') ?: [] as $f) {
    $files[] = basename($f);
}
sort($files);

$problems = 0;
foreach ($files as $file) {
    if (in_array($file, ['login.php', 'footer.php', 'sidebar.php'], true)) {
        continue; // shell files / redirects
    }
    if ($file === 'exit-impersonate.php') {
        continue; // deliberately ends the session — keep it out of the sweep
    }

    $url = $base . '/admin/' . $file;
    $r   = req($url);
    $body = $r['body'];

    $issues = [];
    if ($r['code'] !== 200) {
        $issues[] = 'HTTP ' . $r['code'];
    }
    if ($r['error'] !== '') {
        $issues[] = 'curl: ' . $r['error'];
    }
    foreach ([
        'Fatal error'      => 'FATAL',
        'Parse error'      => 'PARSE',
        'Uncaught'         => 'UNCAUGHT',
        'Warning:'         => 'WARN',
        'Deprecated:'      => 'DEPRECATED',
        'Notice:'          => 'NOTICE',
        'Undefined (array|variable|index|property)' => 'UNDEFINED',
    ] as $needle => $tag) {
        if (stripos($body, $needle) !== false) {
            $issues[] = $tag;
        }
    }
    // Unauthenticated bounce
    if (strpos($r['url'], '/admin-login') !== false && $file !== 'totp-verify.php') {
        $issues[] = 'REDIRECTED-TO-LOGIN';
    }
    // Empty output (a blank page is a glitch too)
    if ($r['code'] === 200 && strlen(trim(strip_tags($body))) < 220) {
        $issues[] = 'NEARLY-EMPTY';
    }

    if ($issues) {
        $problems++;
        printf("  [%-22s] %-28s %s\n", implode(',', $issues), $file, $r['url']);
        // show the diagnostic line
        if (preg_match('/(Fatal error|Parse error|Uncaught[^<\n]*|Warning:[^<\n]*|Deprecated:[^<\n]*|Notice:[^<\n]*|Undefined [^<\n]*)/i', $body, $m)) {
            $snippet = preg_replace('/\s+/', ' ', strip_tags($m[1]));
            echo "        > " . mb_substr($snippet, 0, 220) . "\n";
        }
    } else {
        printf("  [ok] %-32s %s bytes\n", $file, strlen($body));
    }
}

echo str_repeat('-', 78) . "\n";
echo $problems === 0 ? "All admin pages clean.\n" : "{$problems} admin page(s) need attention.\n";

// ── restore ──────────────────────────────────────────────────────────────
db()->query(
    "UPDATE users SET password = ?, two_factor_enabled = ?, totp_enabled = ? WHERE id = ?",
    [$origHash, $admin['two_factor_enabled'], $admin['totp_enabled'], $adminId]
);
@unlink($cookieJar);

echo "Admin password + 2FA state restored.\n";
exit($problems === 0 ? 0 : 1);
