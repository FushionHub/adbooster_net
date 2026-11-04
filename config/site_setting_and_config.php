<?php
declare(strict_types=1);

/**
 * Site Configuration & Settings Loader
 * Adsbooster.net
 * 
 * Derives BASE_URL from the incoming request.
 * Provides get_setting() and get_content() helpers with in-request static cache.
 */

// Prevent double inclusion
if (defined('BASE_URL')) {
    return;
}

// ============================================================
// Dynamic BASE_URL derivation
// ============================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
// Behind reverse proxies / Cloudflare / cPanel AutoSSL proxies
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $protocol = 'https';
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
    $protocol = 'https';
} elseif (($_SERVER['SERVER_PORT'] ?? '') === '443') {
    $protocol = 'https';
}
$rawRequestIsHttps = ($protocol === 'https');
// Production domain is always HTTPS
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isProductionHost = (bool)preg_match('/^adsbooster\.net(:\d+)?$/i', $host);
if ($isProductionHost) {
    $protocol = 'https';
}

$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
$appDir = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));

$subDir = '';
if (!empty($docRoot) && !empty($appDir) && str_starts_with(strtolower($appDir), strtolower($docRoot))) {
    $subDir = substr($appDir, strlen($docRoot));
    $subDir = '/' . trim(str_replace('\\', '/', $subDir), '/');
    if ($subDir === '/') {
        $subDir = '';
    }
} else {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (preg_match('/^[a-zA-Z]:/i', $scriptName)) {
        $scriptName = '';
    }
    $scriptDir = rtrim(dirname($scriptName), '/\\');
    if ($scriptDir === '.' || $scriptDir === '/' || $scriptDir === '\\') {
        $scriptDir = '';
    }
    $subDir = $scriptDir;
}

$baseUrl = $protocol . '://' . $host . $subDir;

define('BASE_URL', $baseUrl);
define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_URL', BASE_URL . '/assets');

// PHP-level HTTPS redirect — belt & suspenders for hosts where mod_rewrite
// is disabled and the vhost itself does not force TLS. Only fires for genuine
// plain-HTTP hits on the production host, so there is no redirect loop.
if ($isProductionHost && !$rawRequestIsHttps
    && php_sapi_name() !== 'cli' && php_sapi_name() !== 'phpdbg'
    && !headers_sent()
    && !defined('SKIP_HTTPS_REDIRECT')) {
    $target = 'https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $target, true, 301);
    exit;
}

/**
 * Generate clean URL (without .php extension)
 */
function url(string $path = '', array $params = []): string
{
    $cleanPath = preg_replace('/\.php$/i', '', ltrim($path, '/'));
    if ($cleanPath === 'index') {
        $cleanPath = '';
    }
    
    // Clean parameterized routes
    if ($cleanPath === 'bounty-details' && isset($params['id'])) {
        $cleanPath .= '/' . urlencode((string)$params['id']);
        unset($params['id']);
    } elseif ($cleanPath === 'portfolio' && isset($params['u'])) {
        $cleanPath .= '/' . urlencode((string)$params['u']);
        unset($params['u']);
    } elseif ($cleanPath === 'client-approval' && isset($params['token'])) {
        $cleanPath .= '/' . urlencode((string)$params['token']);
        unset($params['token']);
    } elseif ($cleanPath === 'blog-details' && isset($params['slug'])) {
        $cleanPath .= '/' . urlencode((string)$params['slug']);
        unset($params['slug']);
    } elseif (in_array($cleanPath, ['listing-details', 'job-details'], true) && isset($params['id'])) {
        $cleanPath .= '/' . (int)$params['id'];
        unset($params['id']);
    }

    $url = empty($cleanPath) ? (BASE_URL ?: '/') : BASE_URL . '/' . $cleanPath;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

// ============================================================
// Harden output before anything else can leak a stack trace
// ============================================================
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
@ini_set('html_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// ============================================================
// Never serve a blank 500: log the real error and render a
// friendly page with a correlation id ops can grep in the log.
// ============================================================
set_exception_handler(static function (\Throwable $e): void {
    $ref = 'E-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    error_log("[{$ref}] uncaught " . get_class($e) . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $hint = '';
    if ($e instanceof \PDOException && preg_match('/doesn\'t exist|not found|Unknown database|access denied|could not find driver/i', $e->getMessage())) {
        $hint = '<p style="margin-top:14px;font-size:15px;color:#f59e0b;">Database setup is incomplete on this server.<br>'
              . 'Import <code>database/adsbooster_complete.sql</code> via phpMyAdmin '
              . '(or verify <code>config/db.php</code> credentials), then reload this page.</p>';
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Temporary error &mdash; Adsbooster.net</title><style>'
       . 'body{margin:0;background:#070b12;color:#e5e9f0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center}'
       . '.c{max-width:560px;padding:36px;background:#0d1320;border:1px solid rgba(255,255,255,.08);border-radius:16px;text-align:center}'
       . 'h1{font-size:22px;margin:0 0 10px}p{color:#9aa4b8;line-height:1.6;font-size:14px}'
       . 'code{background:#141b2b;padding:2px 7px;border-radius:6px;color:#c8ff00;font-size:13px}'
       . '.r{margin-top:16px;font-size:12px;color:#5b647a}</style></head><body><div class="c">'
       . '<h1>We hit a temporary glitch</h1>'
       . '<p>The team has been notified automatically. Please try again in a moment.</p>'
       . $hint
       . '<div class="r">Reference: <code>' . $ref . '</code></div>'
       . '</div></body></html>';
    exit;
});

// ============================================================
// Start session (hardened cookie parameters)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
        || preg_match('/^adsbooster\.net(:\d+)?$/i', (string)($_SERVER['HTTP_HOST'] ?? ''));

    // Harden the session id before it is ever generated.
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.sid_length', '48');
    @ini_set('session.sid_bits_per_character', '6');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // true in production behind TLS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Rotate the id periodically to blunt session fixation / hijacking.
    if (empty($_SESSION['__created'])) {
        $_SESSION['__created'] = time();
    } elseif ((time() - (int)$_SESSION['__created']) > 1800 && !headers_sent()) {
        session_regenerate_id(true);
        $_SESSION['__created'] = time();
    }
}
if (ob_get_level() === 0) {
    ob_start();
}

// ============================================================
// Autoload vendor dependencies (PHPMailer, etc.)
// ============================================================
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// ============================================================
// Database connection
// ============================================================
require_once ROOT_PATH . '/config/db.php';

// ============================================================
// Central API keys store (constants + api_key() helper)
// ============================================================
require_once ROOT_PATH . '/config/apikeys.php';

// ============================================================
// Central security helpers (escaping, sanitizer, uploads, SSRF)
// ============================================================
require_once ROOT_PATH . '/includes/security.php';

// Auto-check and migrate third-party gateway tables (Bundle.social & Zernio)
function maybe_migrate_gateways(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $hasTable = db()->fetchOne("SHOW TABLES LIKE 'third_party_gateways'");
        if (!$hasTable) {
            $migrator = ROOT_PATH . '/database/migrate_gateways.php';
            if (is_readable($migrator)) {
                require_once $migrator;
            }
        }
    } catch (\Throwable $e) {}
}
maybe_migrate_gateways();

// Auto-create the asynchronous job queue table (Dual Gateway v5.0).
// Direct OAuth/token paths were already removed by maybe_migrate_gateways()
// (which restructures `connected_accounts`); app-local 2FA on `users` is
// deliberately preserved — it is not an OAuth artifact.
function maybe_migrate_async_queue(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->fetchOne("SELECT id FROM async_job_queue LIMIT 1");
    } catch (\Throwable $e) {
        try {
            // Table missing (or DB momentarily unavailable) — create it.
            db()->query("
                CREATE TABLE IF NOT EXISTS `async_job_queue` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `job_type` VARCHAR(100) NOT NULL,
                    `subject_key` VARCHAR(64) NULL,
                    `payload` LONGTEXT NOT NULL,
                    `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
                    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
                    `error_log` TEXT NULL,
                    `scheduled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `processed_at` DATETIME NULL,
                    `last_attempt_at` DATETIME NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                    INDEX `idx_queue_status_schedule` (`status`, `scheduled_at`),
                    INDEX `idx_user_jobs` (`user_id`, `status`),
                    INDEX `idx_queue_subject` (`subject_key`, `status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            error_log('maybe_migrate_async_queue: async_job_queue table auto-created.');
        } catch (\Throwable $e2) {
            error_log('maybe_migrate_async_queue: auto-create failed: ' . $e2->getMessage());
        }
    }
}
maybe_migrate_async_queue();

// ============================================================
// Schema guard — add any column the code needs but the database
// is missing. Keeps a deployment one migration behind from
// dying with "Unknown column 'x' in 'field list'".
// ============================================================
require_once __DIR__ . '/schema_guard.php';
SchemaGuard::ensure();

// ============================================================
// Settings cache (single query per page load)
// ============================================================
static $settingsCache = null;
static $contentCache = null;

/**
 * One-shot auto-install: when the database exists but the schema has not
 * been imported yet, run database/adsbooster_complete.sql so a fresh cPanel
 * deployment lights up on the first page view instead of throwing a 500.
 */
function maybe_install_schema(): bool
{
    static $tried = false;
    if ($tried) {
        return false;
    }
    $tried = true;

    $installer = __DIR__ . '/../database/install.php';
    if (!is_readable($installer)) {
        return false;
    }

    define('APP_INSTALL_ALLOW', 1);
    try {
        require $installer;
    } catch (\Throwable $e) {
        error_log('auto schema install failed: ' . $e->getMessage());
    }

    // Did it work?
    try {
        $ok = db()->fetchOne("SELECT 1 AS ok FROM site_settings LIMIT 1");
        return $ok !== null;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Fetch a settings cache: DB -> (auto-install on missing schema) -> DB -> defaults.
 */
function _load_settings_cache(): array
{
    global $settingsCache;
    if ($settingsCache !== null) {
        return $settingsCache;
    }

    $rows = null;
    try {
        $rows = db()->fetchAll("SELECT setting_key, setting_value FROM site_settings");
    } catch (\Throwable $e) {
        error_log('get_setting query failed: ' . $e->getMessage());
        if (maybe_install_schema()) {
            try {
                $rows = db()->fetchAll("SELECT setting_key, setting_value FROM site_settings");
            } catch (\Throwable $e2) {
                error_log('get_setting retry failed: ' . $e2->getMessage());
            }
        }
    }

    $settingsCache = [];
    foreach ($rows ?? [] as $row) {
        $settingsCache[$row['setting_key']] = $row['setting_value'];
    }
    return $settingsCache;
}

function _load_content_cache(): array
{
    global $contentCache;
    if ($contentCache !== null) {
        return $contentCache;
    }

    $rows = null;
    try {
        $rows = db()->fetchAll(
            "SELECT page_slug, section_key, item_key, content_value, media_url FROM dynamic_content"
        );
    } catch (\Throwable $e) {
        error_log('get_content query failed: ' . $e->getMessage());
        if (maybe_install_schema()) {
            try {
                $rows = db()->fetchAll(
                    "SELECT page_slug, section_key, item_key, content_value, media_url FROM dynamic_content"
                );
            } catch (\Throwable $e2) {
                error_log('get_content retry failed: ' . $e2->getMessage());
            }
        }
    }

    $contentCache = [];
    foreach ($rows ?? [] as $row) {
        $key = $row['page_slug'] . '|' . $row['section_key'] . '|' . $row['item_key'];
        $contentCache[$key] = $row;
    }
    return $contentCache;
}

/**
 * Get a site setting by key
 */
function get_setting(string $key, string $default = ''): string
{
    $cache = _load_settings_cache();
    return $cache[$key] ?? $default;
}

/**
 * Get dynamic content by page/section/item
 */
function get_content(string $page, string $section, string $item, string $fallback = ''): string
{
    $cache = _load_content_cache();
    $key = $page . '|' . $section . '|' . $item;
    if (isset($cache[$key])) {
        return $cache[$key]['content_value'] ?? $fallback;
    }

    return $fallback;
}

/**
 * Get dynamic content media URL
 */
function get_content_media(string $page, string $section, string $item, string $fallback = ''): string
{
    $cache = _load_content_cache();
    $key = $page . '|' . $section . '|' . $item;
    if (isset($cache[$key]) && !empty($cache[$key]['media_url'])) {
        return $cache[$key]['media_url'];
    }

    return $fallback;
}

/**
 * Generate CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Output a hidden CSRF input field
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Check if user is authenticated
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function current_user_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Check if current user is admin
 */
function is_admin(): bool
{
    return current_user_role() === 'admin';
}

/**
 * Redirect helper
 */
function redirect(string $url): void
{
    if (!headers_sent()) {
        header("Location: {$url}");
        exit;
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

/**
 * Sanitize output
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message helper
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// ============================================================
// CSRF alias (used by auth files)
// ============================================================
function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return verify_csrf($token);
}

// ============================================================
// Current user helper
// ============================================================
function current_user(): ?array
{
    $userId = current_user_id();
    if (!$userId) {
        return null;
    }
    return db()->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
}

// ============================================================
// User login (sets session data)
// SECURITY: central account-status gate. Every login path
// (password, Google OAuth, remember-me) funnels through here, so a
// suspended or banned account can never mint a session anywhere.
// ============================================================
function auth_login(array $user): void
{
    if (isset($user['status']) && $user['status'] !== 'active') {
        error_log('auth_login refused: user id=' . (int)($user['id'] ?? 0) . ' status=' . (string)($user['status'] ?? '?'));
        throw new RuntimeException('This account is not active. Please contact support if you believe this is an error.');
    }

    session_regenerate_id(true);
    $_SESSION['user_id']         = (int)$user['id'];
    $_SESSION['user_role']       = $user['role'];
    $_SESSION['user_name']       = $user['full_name'];
    $_SESSION['user_email']      = $user['email'];
    $_SESSION['account_type']    = $user['account_type'] ?? 'individual';
    $_SESSION['logged_in']       = true;
}

// ============================================================
// Admin login (sets admin session data)
// ============================================================
function auth_admin_login(array $admin): void
{
    if (isset($admin['status']) && $admin['status'] !== 'active') {
        error_log('auth_admin_login refused: admin id=' . (int)($admin['id'] ?? 0) . ' status=' . (string)($admin['status'] ?? '?'));
        throw new RuntimeException('This account is not active. Please contact support.');
    }
    if (($admin['role'] ?? '') !== 'admin') {
        error_log('auth_admin_login refused non-admin role for id=' . (int)($admin['id'] ?? 0));
        throw new RuntimeException('Unauthorized: administrator account required.');
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']    = (int)$admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name']  = $admin['full_name'];
    $_SESSION['admin_role']  = $admin['role'];
    $_SESSION['admin_logged_in'] = true;
    // includes/admin_guard.php authenticates through the normal user session
    // keys (is_logged_in / current_user_role). Mirror them here — without
    // this, a pure /admin-login session can never pass the guard and bounces
    // in a redirect loop between /admin-login and /admin/index.
    $_SESSION['user_id']       = (int)$admin['id'];
    $_SESSION['user_role']     = $admin['role'];
    $_SESSION['user_name']     = $admin['full_name'];
    $_SESSION['user_email']    = $admin['email'];
    $_SESSION['account_type']  = $admin['account_type'] ?? 'individual';
    $_SESSION['logged_in']     = true;
}

// ============================================================
// Simple rate limiter (file-based)
// ============================================================
function check_rate_limit(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $cacheDir = ROOT_PATH . '/cache/rate_limit';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $filePath = $cacheDir . '/' . md5($key . $ip) . '.json';

    $data = ['attempts' => 0, 'first_at' => time()];
    if (file_exists($filePath)) {
        $raw = file_get_contents($filePath);
        $data = json_decode($raw, true) ?: $data;
    }

    if ((time() - ($data['first_at'] ?? 0)) > $windowSeconds) {
        $data = ['attempts' => 0, 'first_at' => time()];
    }

    if ($data['attempts'] >= $maxAttempts) {
        return false;
    }

    $data['attempts']++;
    file_put_contents($filePath, json_encode($data));
    return true;
}

// ============================================================
// Remember Me Authentication System
// ============================================================

/**
 * Issue a secure remember me token, store hash in DB, and set browser cookies.
 */
function issue_remember_token(int $userId): void
{
    try {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

        db()->update('users', [
            'remember_token'            => $hashedToken,
            'remember_token_expires_at' => $expiresAt,
        ], 'id = ?', [$userId]);

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $cookieParams = [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        setcookie('remember_token', $token, $cookieParams);
        setcookie('remember_user', (string)$userId, $cookieParams);
        $_COOKIE['remember_token'] = $token;
        $_COOKIE['remember_user'] = (string)$userId;
    } catch (\Throwable $e) {
        error_log('issue_remember_token error: ' . $e->getMessage());
    }
}

/**
 * Clear remember me token in DB and invalidate cookies.
 */
function clear_remember_token(?int $userId = null): void
{
    try {
        if ($userId !== null && $userId > 0) {
            db()->update('users', [
                'remember_token'            => null,
                'remember_token_expires_at' => null,
            ], 'id = ?', [$userId]);
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $cookieParams = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        setcookie('remember_token', '', $cookieParams);
        setcookie('remember_user', '', $cookieParams);
        unset($_COOKIE['remember_token'], $_COOKIE['remember_user']);
    } catch (\Throwable $e) {
        error_log('clear_remember_token error: ' . $e->getMessage());
    }
}

/**
 * Middleware: Verify remember me cookie and automatically authenticate session.
 */
function check_remember_login(): void
{
    if (is_logged_in()) {
        return;
    }

    $rawUserId = $_COOKIE['remember_user'] ?? '';
    $rawToken  = $_COOKIE['remember_token'] ?? '';

    if (empty($rawUserId) || empty($rawToken) || !is_numeric($rawUserId)) {
        return;
    }

    $userId = (int)$rawUserId;
    if ($userId <= 0) {
        return;
    }

    try {
        $user = db()->fetch("SELECT * FROM users WHERE id = ? AND status = 'active'", [$userId]);
        if (!$user || empty($user['remember_token'])) {
            clear_remember_token($userId);
            return;
        }

        // Check if token has expired
        if (!empty($user['remember_token_expires_at']) && strtotime($user['remember_token_expires_at']) < time()) {
            clear_remember_token($userId);
            return;
        }

        // Compare SHA-256 hash using timing-safe comparison
        $inputHash = hash('sha256', $rawToken);
        if (!hash_equals($user['remember_token'], $inputHash)) {
            clear_remember_token($userId);
            return;
        }

        // Login user
        auth_login($user);

        // Rotate remember token to prevent replay attacks
        issue_remember_token($userId);
    } catch (\Throwable $e) {
        error_log('check_remember_login error: ' . $e->getMessage());
    }
}

// Automatically check and restore user session from Remember Me cookie
check_remember_login();

// ============================================================
// Currency engine — USD / NGN dual pricing with a session switcher
// ============================================================

/** All user-selectable display currencies. */
function currency_supported(): array
{
    return ['USD' => '$', 'NGN' => '₦'];
}

/** Phosphor icon for a display currency (pickers/buttons). */
function currency_icon(string $code): string
{
    return match (strtoupper($code)) {
        'USD'   => 'ph-currency-dollar',
        'NGN'   => 'ph-currency-ngn',
        default => 'ph-coins',
    };
}

/** Human-readable currency name for pickers/dropdowns. */
function currency_name(string $code): string
{
    return match (strtoupper($code)) {
        'USD'   => 'US Dollar',
        'NGN'   => 'Nigerian Naira',
        default => strtoupper($code),
    };
}

/** The active display currency (session → cookie → USD). */
function currency_current(): string
{
    $code = strtoupper((string)($_SESSION['currency'] ?? ($_COOKIE['ab_currency'] ?? '')));
    return isset(currency_supported()[$code]) ? $code : 'USD';
}

/** Switch the active currency for this session (and remember it for 90 days). */
function currency_set(string $code): void
{
    $code = strtoupper(trim($code));
    if (!isset(currency_supported()[$code])) {
        return;
    }
    $_SESSION['currency'] = $code;
    setcookie('ab_currency', $code, [
        'expires'  => time() + 86400 * 90,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
}

/** NGN units per 1 USD (admin-tunable via Settings → ngn_usd_rate). */
function exchange_rate_ngn_per_usd(): float
{
    $rate = (float)get_setting('ngn_usd_rate', '1550');
    return $rate > 0 ? $rate : 1550.0;
}

/**
 * Convert a canonical USD price into the active display currency.
 * NGN values are rounded to the nearest ₦25 so prices stay clean.
 */
function price_convert(float $usd, ?string $to = null): float
{
    $to = $to ?? currency_current();
    if ($to === 'NGN') {
        return round($usd * exchange_rate_ngn_per_usd() / 25) * 25;
    }
    return round($usd, 2);
}

/** Format a canonical USD price in the active display currency. */
function price_fmt(float $usd, ?string $cur = null): string
{
    $cur = $cur ?? currency_current();
    $symbols = currency_supported();
    return $symbols[$cur] . number_format(price_convert($usd, $cur), $cur === 'NGN' ? 0 : 2);
}

/** Both currencies side by side, e.g. "$18.99 / ₦29,400". */
function price_dual(float $usd): string
{
    return '$' . number_format($usd, 2) . ' / ' . '₦' . number_format(price_convert($usd, 'NGN'), 0);
}

/** URL for switching currency on the current page, preserving other params. */
function currency_toggle_url(string $code): string
{
    $qs = $_GET;
    $qs['cur'] = $code;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/';
    return e($path . '?' . http_build_query($qs));
}

/** Handle ?cur= switches on any page (GET, one line in the switcher link). */
if (isset($_GET['cur']) && PHP_SAPI !== 'cli') {
    currency_set((string)$_GET['cur']);
    // Strip the cur param so refreshes don't re-trigger, keeping other params.
    $qs = $_GET;
    unset($qs['cur']);
    $target = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/';
    if ($qs !== []) {
        $target .= '?' . http_build_query($qs);
    }
    header('Location: ' . $target);
    exit;
}

// ============================================================
// Maintenance mode (Admin → Settings → Site Access & Maintenance)
// ============================================================
if (PHP_SAPI !== 'cli') {
    try {
        $_mmFlag = get_setting('maintenance_mode', '0');
    } catch (\Throwable $e) {
        $_mmFlag = '0';
    }
    if ($_mmFlag === '1') {
        $_mmScript  = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $_mmIsAdmin = str_contains($_mmScript, '/admin/');
        $_mmIsLogin = str_contains($_mmScript, 'admin-login')
                   || str_contains($_mmScript, '/login')
                   || str_contains($_mmScript, 'exit-impersonate');
        $_mmAdminOk = is_logged_in() && current_user_role() === 'admin';
        if (!$_mmIsAdmin && !$_mmIsLogin && !$_mmAdminOk) {
            http_response_code(503);
            header('Retry-After: 300');
            header('Content-Type: text/html; charset=utf-8');
            $_mmSite = htmlspecialchars(get_setting('site_name', 'Adsbooster.net'), ENT_QUOTES);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
               . '<meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<title>Down for maintenance — ' . $_mmSite . '</title>'
               . '<style>body{margin:0;background:#080c14;color:#e2e8f0;font-family:Inter,system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center}'
               . '.box{max-width:420px;padding:40px;text-align:center}h1{font-size:22px;margin:16px 0 8px}p{color:#94a3b8;font-size:14px;line-height:1.6}'
               . '.spin{width:38px;height:38px;border:3px solid #1e293b;border-top-color:#22c55e;border-radius:50%;margin:0 auto;animation:r 1s linear infinite}'
               . '@keyframes r{to{transform:rotate(360deg)}}</style></head><body><div class="box">'
               . '<div class="spin"></div><h1>We\'ll be back soon</h1>'
               . '<p>' . $_mmSite . ' is undergoing scheduled maintenance. Please check again in a few minutes.</p>'
               . '</div></body></html>';
            exit;
        }
    }
    unset($_mmFlag, $_mmScript, $_mmIsAdmin, $_mmIsLogin, $_mmAdminOk, $_mmSite);
}

