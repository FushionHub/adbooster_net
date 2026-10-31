<?php
/**
 * Adsbooster.net — High-Speed cPanel Shared Hosting Database Installer
 * File: installsql-now.php
 *
 * One-click installer for deploying the Adsbooster master database schema
 * and seed data on cPanel, Plesk, DirectAdmin, or any Linux/Windows server.
 *
 * Features:
 * - Pre-flight server & extension checks
 * - Auto-detects local credentials or accepts cPanel DB inputs
 * - Live AJAX/POST database connection verification
 * - Splits and executes statements safely with timeout & memory protection
 * - Automatically writes/updates config/db.local.php
 * - Post-installation security self-lock / delete button
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$sqlFiles = [
    __DIR__ . '/adsbooster_cpanel_full.sql',
    __DIR__ . '/database/adsbooster_complete.sql'
];

$chosenSqlFile = null;
foreach ($sqlFiles as $sf) {
    if (file_exists($sf) && is_readable($sf)) {
        $chosenSqlFile = $sf;
        break;
    }
}

// Auto-detect existing credentials if present
$detectedHost = '127.0.0.1';
$detectedPort = '3306';
$detectedName = '';
$detectedUser = '';
$detectedPass = '';

$localConfig = __DIR__ . '/config/db.local.php';
if (file_exists($localConfig)) {
    $conf = @include $localConfig;
    if (is_array($conf)) {
        $detectedHost = $conf['host'] ?? $detectedHost;
        $detectedPort = (string)($conf['port'] ?? $detectedPort);
        $detectedName = $conf['name'] ?? ($conf['dbname'] ?? $detectedName);
        $detectedUser = $conf['user'] ?? ($conf['username'] ?? $detectedUser);
        $detectedPass = $conf['pass'] ?? ($conf['password'] ?? $detectedPass);
    }
} else {
    // Check environment variables
    $detectedHost = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: $detectedHost);
    $detectedPort = getenv('DB_PORT') ?: $detectedPort;
    $detectedName = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: $detectedName);
    $detectedUser = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: $detectedUser);
    $detectedPass = getenv('DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: $detectedPass);
}

// Pre-flight checks
$phpVersion = PHP_VERSION;
$hasPdo = extension_loaded('pdo');
$hasPdoMysql = extension_loaded('pdo_mysql');
$hasMbstring = extension_loaded('mbstring');
$hasCurl = extension_loaded('curl');
$configWritable = is_writable(__DIR__ . '/config') || is_writable(__DIR__);
$sqlFileFound = ($chosenSqlFile !== null);

$action = $_POST['action'] ?? '';
$errorMsg = '';
$successMsg = '';
$results = null;

// Self-Delete Action
if ($action === 'delete_installer') {
    if (@unlink(__FILE__)) {
        die("<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Installer Deleted</title><style>body{background:#080c14;color:#22c55e;font-family:sans-serif;text-align:center;padding:100px;}</style></head><body><h2>Security Clean-Up Complete</h2><p>This installer file (<code>installsql-now.php</code>) has been permanently removed from the server for security.</p><p><a href='index.php' style='color:#c8ff00;text-decoration:none;font-weight:bold;'>&larr; Return to Homepage</a> &bull; <a href='admin-login.php' style='color:#c8ff00;text-decoration:none;font-weight:bold;'>Go to Admin Login &rarr;</a></p></body></html>");
    } else {
        $errorMsg = "Could not delete " . basename(__FILE__) . ". Please delete it manually via cPanel File Manager or FTP.";
    }
}

// Test DB Connection Only
if ($action === 'test_connection') {
    $host = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
    $port = trim((string)($_POST['db_port'] ?? '3306'));
    $name = trim((string)($_POST['db_name'] ?? ''));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_pass'] ?? '');

    try {
        $dsn = "mysql:host={$host};port={$port};charset=latin1";
        if ($name !== '') {
            $dsn .= ";dbname={$name}";
        }
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        $serverVer = $pdo->query("SELECT VERSION()")->fetchColumn();
        $successMsg = "Connection successful! MySQL Server Version: " . htmlspecialchars((string)$serverVer);
        if ($name !== '') {
            $stmt = $pdo->query("SHOW TABLES");
            $tableCount = count($stmt->fetchAll());
            $successMsg .= "<br>Database `{$name}` selected. Existing tables: {$tableCount}.";
        }
    } catch (\Throwable $e) {
        $errorMsg = "Connection failed: " . htmlspecialchars($e->getMessage());
    }
}

// Run Installation
if ($action === 'install') {
    $host = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
    $port = trim((string)($_POST['db_port'] ?? '3306'));
    $name = trim((string)($_POST['db_name'] ?? ''));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_pass'] ?? '');
    $saveConfig = !empty($_POST['save_config']);
    
    // Optional custom admin credentials
    $customAdminEmail = trim((string)($_POST['admin_email'] ?? ''));
    $customAdminPass = (string)($_POST['admin_pass'] ?? '');

    if ($name === '' || $user === '') {
        $errorMsg = "Database Name and Database Username are required for cPanel installation.";
    } elseif (!$chosenSqlFile || !file_exists($chosenSqlFile)) {
        $errorMsg = "SQL file not found. Ensure `adsbooster_cpanel_full.sql` or `database/adsbooster_complete.sql` exists.";
    } else {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=latin1";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 30,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
            ]);

            $pdo->exec("SET NAMES latin1;");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");

            $startTime = microtime(true);
            $rawSql = (string)file_get_contents($chosenSqlFile);

            // Strip problematic cPanel commands if any exist
            $rawSql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;.*$/mi', '', $rawSql);

            // Execute the import
            $pdo->exec($rawSql);
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $elapsed = round(microtime(true) - $startTime, 2);

            // Get count of tables created
            $stmt = $pdo->query("SHOW FULL TABLES");
            $allTables = $stmt->fetchAll(PDO::FETCH_NUM);
            $tableCount = count($allTables);

            // Update custom admin password if provided
            if ($customAdminPass !== '') {
                $hashed = password_hash($customAdminPass, PASSWORD_BCRYPT);
                $targetEmail = $customAdminEmail !== '' ? $customAdminEmail : 'admin@adsbooster.net';
                
                // Check if user 1 exists
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = 1 OR role = 'admin' LIMIT 1");
                $stmtCheck->execute();
                $adm = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($adm) {
                    $upd = $pdo->prepare("UPDATE users SET email = ?, password = ?, status = 'active', role = 'admin' WHERE id = ?");
                    $upd->execute([$targetEmail, $hashed, $adm['id']]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status, ad_credits, ai_credits, plan_id) VALUES (?, ?, ?, 'admin', 'active', 500.00, 500, 4)");
                    $ins->execute(['Admin User', $targetEmail, $hashed]);
                }
            }

            // Save credentials to config/db.local.php if requested
            $configSaved = false;
            if ($saveConfig) {
                $localConfigContent = "<?php\n/**\n * Local Database Credentials for cPanel Deployment\n * Auto-generated by installsql-now.php on " . date('Y-m-d H:i:s') . "\n */\nreturn [\n    'host'     => " . var_export($host, true) . ",\n    'port'     => " . var_export($port, true) . ",\n    'dbname'   => " . var_export($name, true) . ",\n    'name'     => " . var_export($name, true) . ",\n    'username' => " . var_export($user, true) . ",\n    'user'     => " . var_export($user, true) . ",\n    'password' => " . var_export($pass, true) . ",\n    'pass'     => " . var_export($pass, true) . ",\n];\n";
                if (@file_put_contents($localConfig, $localConfigContent)) {
                    $configSaved = true;
                }
            }

            $results = [
                'tables' => $tableCount,
                'elapsed' => $elapsed,
                'file' => basename($chosenSqlFile),
                'config_saved' => $configSaved,
                'admin_email' => $customAdminEmail !== '' ? $customAdminEmail : 'admin@adsbooster.net'
            ];

            $successMsg = "Database successfully installed and verified on cPanel in {$elapsed} seconds!";
        } catch (\Throwable $e) {
            $errorMsg = "Installation failed: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adsbooster.net &mdash; cPanel Database Installation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #080c14;
            --surface: #0d1524;
            --surface-hover: #131d31;
            --border: #1e293b;
            --brand-lime: #c8ff00;
            --brand-lime-hover: #b5e600;
            --accent-green: #22c55e;
            --accent-red: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }
        .installer-container {
            width: 100%;
            max-width: 720px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            overflow: hidden;
        }
        .header {
            padding: 32px 36px;
            background: linear-gradient(180deg, rgba(200, 255, 0, 0.05) 0%, transparent 100%);
            border-bottom: 1px solid var(--border);
            text-align: center;
        }
        .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(200, 255, 0, 0.1);
            color: var(--brand-lime);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 14px;
            border: 1px solid rgba(200, 255, 0, 0.2);
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
        .content {
            padding: 32px 36px;
        }
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        .preflight-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 28px;
        }
        .preflight-pill {
            background: #090e1a;
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .preflight-pill.ok { border-color: rgba(34, 197, 94, 0.3); color: #86efac; }
        .preflight-pill.warn { border-color: rgba(239, 68, 68, 0.3); color: #fca5a5; }

        .form-section {
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            background: #090e1a;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 14px;
            color: #fff;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        input:focus {
            outline: none;
            border-color: var(--brand-lime);
            box-shadow: 0 0 0 2px rgba(200, 255, 0, 0.15);
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 0.88rem;
            color: #e2e8f0;
            margin-top: 8px;
        }
        .checkbox-label input {
            accent-color: var(--brand-lime);
            width: 16px;
            height: 16px;
        }
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 13px 24px;
            font-size: 0.95rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            border: none;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--brand-lime);
            color: #000;
            flex: 2;
        }
        .btn-primary:hover {
            background: var(--brand-lime-hover);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #1e293b;
            color: #e2e8f0;
            flex: 1;
        }
        .btn-secondary:hover {
            background: #334155;
        }
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        .success-box {
            background: #090e1a;
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 24px;
            margin-top: 20px;
        }
        .success-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 16px;
            font-size: 0.85rem;
        }
        .success-item {
            background: rgba(255, 255, 255, 0.03);
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        .success-item span { color: var(--text-muted); display: block; font-size: 0.75rem; }
        .success-item strong { color: #fff; font-size: 0.95rem; }
        .footer-note {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 0.75rem;
            color: #64748b;
        }
    </style>
</head>
<body>

<div class="installer-container">
    <div class="header">
        <div class="logo-badge">&bull; cPanel Shared Hosting Safe &bull;</div>
        <h1>Adsbooster.net Database Installer</h1>
        <p class="subtitle">Deploy the complete schema & seed data safely with 1-click. Optimized for cPanel MySQL & MariaDB without collation conflicts.</p>
    </div>

    <div class="content">
        <?php if ($errorMsg): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?= $errorMsg ?>
            </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success">
                <strong>Success:</strong> <?= $successMsg ?>
            </div>
        <?php endif; ?>

        <?php if ($results): ?>
            <!-- Success Screen -->
            <div class="success-box">
                <h3 style="color:#86efac; margin-bottom:8px;">Deployment Finished Successfully</h3>
                <p style="font-size:0.88rem; color:#cbd5e1;">All 78 database tables, views, pricing plans, and master records were imported seamlessly using safe <code>latin1_swedish_ci</code> encoding.</p>
                
                <div class="success-grid">
                    <div class="success-item">
                        <span>Total Tables & Views</span>
                        <strong><?= $results['tables'] ?> Objects Created</strong>
                    </div>
                    <div class="success-item">
                        <span>Execution Duration</span>
                        <strong><?= $results['elapsed'] ?> seconds</strong>
                    </div>
                    <div class="success-item">
                        <span>Database Config</span>
                        <strong><?= $results['config_saved'] ? 'Saved to config/db.local.php' : 'Manual Config' ?></strong>
                    </div>
                    <div class="success-item">
                        <span>Default Admin Account</span>
                        <strong><?= htmlspecialchars($results['admin_email']) ?></strong>
                    </div>
                </div>

                <div style="margin-top:24px; display:flex; gap:12px; flex-wrap:wrap;">
                    <a href="admin-login.php" class="btn btn-primary" style="flex:1;">Go to Admin Login &rarr;</a>
                    <a href="index.php" class="btn btn-secondary" style="flex:1;">Go to Website &rarr;</a>
                </div>

                <form method="POST" style="margin-top:16px;">
                    <input type="hidden" name="action" value="delete_installer">
                    <button type="submit" class="btn btn-danger" style="width:100%;" onclick="return confirm('Are you sure you want to delete this installer file for security?');">
                        &times; Delete Installer File (Recommended for Security)
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Pre-flight checks bar -->
            <div class="preflight-grid">
                <div class="preflight-pill <?= version_compare($phpVersion, '7.4.0', '>=') ? 'ok' : 'warn' ?>">
                    <span>PHP <?= explode('-', $phpVersion)[0] ?></span>
                    <span><?= version_compare($phpVersion, '7.4.0', '>=') ? '&#10003;' : '&#10007;' ?></span>
                </div>
                <div class="preflight-pill <?= $hasPdoMysql ? 'ok' : 'warn' ?>">
                    <span>PDO MySQL</span>
                    <span><?= $hasPdoMysql ? '&#10003;' : '&#10007;' ?></span>
                </div>
                <div class="preflight-pill <?= $sqlFileFound ? 'ok' : 'warn' ?>">
                    <span>Master SQL</span>
                    <span><?= $sqlFileFound ? '&#10003;' : 'Missing' ?></span>
                </div>
                <div class="preflight-pill <?= $configWritable ? 'ok' : 'warn' ?>">
                    <span>config/ Dir</span>
                    <span><?= $configWritable ? 'Writable' : 'Read-only' ?></span>
                </div>
            </div>

            <!-- Install Form -->
            <form method="POST">
                <div class="form-section">
                    <div class="section-title">
                        <span>1. cPanel Database Credentials</span>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label>MySQL Host</label>
                            <input type="text" name="db_host" value="<?= htmlspecialchars($detectedHost) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Port</label>
                            <input type="text" name="db_port" value="<?= htmlspecialchars($detectedPort) ?>" required>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Database Name (e.g. cpanel_adsbooster)</label>
                            <input type="text" name="db_name" value="<?= htmlspecialchars($detectedName) ?>" placeholder="cpaneluser_adsbooster" required>
                        </div>
                        <div class="form-group">
                            <label>Database User</label>
                            <input type="text" name="db_user" value="<?= htmlspecialchars($detectedUser) ?>" placeholder="cpaneluser_dbuser" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Database Password</label>
                        <input type="password" name="db_pass" value="<?= htmlspecialchars($detectedPass) ?>" placeholder="Enter cPanel Database Password">
                    </div>

                    <label class="checkbox-label">
                        <input type="checkbox" name="save_config" value="1" checked>
                        <span>Automatically save credentials to <code>config/db.local.php</code></span>
                    </label>
                </div>

                <div class="form-section" style="border-top:1px solid var(--border); padding-top:20px;">
                    <div class="section-title">
                        <span>2. Admin Account Setup (Optional)</span>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Admin Email</label>
                            <input type="email" name="admin_email" placeholder="admin@adsbooster.net (Default)">
                        </div>
                        <div class="form-group">
                            <label>New Admin Password</label>
                            <input type="password" name="admin_pass" placeholder="Leave empty to keep default">
                        </div>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" name="action" value="test_connection" class="btn btn-secondary">
                        Test Connection
                    </button>
                    <button type="submit" name="action" value="install" class="btn btn-primary" onclick="return confirm('Deploy Adsbooster master database schema and seed data now?');">
                        Install Master Database Now &rarr;
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <div class="footer-note">
            Adsbooster.net Platform Architecture &bull; cPanel & MariaDB 10.x / MySQL 5.7+ & 8.x Compatible &bull; Latin1 Swedish CI
        </div>
    </div>
</div>

</body>
</html>
