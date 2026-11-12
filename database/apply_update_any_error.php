<?php declare(strict_types=1);

/**
 * apply_update_any_error.php — CLI runner for `update-any-error.sql`
 *
 * Applies the payment / referral / webhook schema repair file from the
 * command line. Idempotent: run it as often as you like.
 *
 * Usage:
 *   php database/apply_update_any_error.php
 *   php database/apply_update_any_error.php --dry-run
 *
 * Requires the file `update-any-error.sql` in the project root.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script is CLI-only.\n";
    exit(1);
}

$root = dirname(__DIR__);
$sqlFile = $root . '/update-any-error.sql';

if (!is_readable($sqlFile)) {
    fwrite(STDERR, "ERROR: update-any-error.sql not found at {$sqlFile}\n");
    exit(1);
}

// Load the connection helper without bootstrapping the web stack.
require_once $root . '/config/db.php';

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);
$sql    = (string)file_get_contents($sqlFile);

echo "Adsbooster — update-any-error.sql\n";
echo str_repeat('-', 60) . "\n";

try {
    $credentials = db()->credentials();
    printf(
        "Database : %s@%s:%s/%s\n",
        $credentials['username'],
        $credentials['host'],
        $credentials['port'],
        $credentials['dbname']
    );
} catch (\Throwable $e) {
    fwrite(STDERR, "ERROR: cannot open database connection: " . $e->getMessage() . "\n");
    exit(1);
}

if ($dryRun) {
    printf("Mode     : dry-run (%d bytes of SQL parsed, nothing executed)\n", strlen($sql));
    echo str_repeat('-', 60) . "\n";
    echo "Statements that would run:\n";
    $withoutComments = (string)preg_replace('/^--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $withoutComments) as $i => $statement) {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            continue;
        }
        printf("  %d. %s\n", $i + 1, strtok($trimmed, "\r\n"));
    }
    exit(0);
}

// A dedicated connection is used so the whole file can be handed to one
// multi-statement execution: the file relies on PREPARE / EXECUTE, which a
// normal PDO prepared statement cannot run.
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $credentials['host'],
    $credentials['port'],
    $credentials['dbname'],
    $credentials['charset']
);

$started = microtime(true);

try {
    $pdo = new PDO($dsn, $credentials['username'], $credentials['password'], [
        PDO::ATTR_ERRMODE                 => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES        => false,
        PDO::ATTR_TIMEOUT                 => 30,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS  => true,
    ]);
    $pdo->exec('SET NAMES ' . $credentials['charset']);
    $pdo->exec($sql);
} catch (\PDOException $e) {
    fwrite(STDERR, "\nupdate-any-error.sql FAILED\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    fwrite(STDERR, "\nFix the reported statement and re-run — the script is idempotent,\n");
    fwrite(STDERR, "so a partially applied run is safe to repeat.\n");
    exit(1);
}

$elapsed = round((microtime(true) - $started) * 1000);

// Read back the objects the script guarantees.
$summary = [
    'referrals (total)'        => 'SELECT COUNT(*) FROM `referrals`',
    'referrals pending'        => "SELECT COUNT(*) FROM `referrals` WHERE `status` = 'pending'",
    'referrals rewarded'       => "SELECT COUNT(*) FROM `referrals` WHERE `status` = 'rewarded'",
    'referral reward ledger'   => 'SELECT COUNT(*) FROM `referral_rewards`',
    'webhook deliveries'       => 'SELECT COUNT(*) FROM `payment_webhook_events`',
    'transactions pending'     => "SELECT COUNT(*) FROM `transactions` WHERE `status` = 'pending'",
    'users with referral code' => 'SELECT COUNT(*) FROM `users` WHERE `referral_code` IS NOT NULL',
];

echo str_repeat('-', 60) . "\n";
foreach ($summary as $label => $query) {
    try {
        printf("  %-26s %d\n", $label . ':', (int)$pdo->query($query)->fetchColumn());
    } catch (\Throwable $e) {
        printf("  %-26s unavailable (%s)\n", $label . ':', $e->getMessage());
    }
}

echo str_repeat('-', 60) . "\n";
printf("Applied in %d ms.\n", $elapsed);
echo "update-any-error.sql completed successfully.\n";
exit(0);
