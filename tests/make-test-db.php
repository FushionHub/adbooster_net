<?php
/**
 * One-time setup: create an isolated test database by cloning the schema
 * of the live local development database (read-only on the source).
 *
 * Usage: php -d extension=php_pdo_mysql.dll tests/make-test-db.php
 */
declare(strict_types=1);

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_TIMEOUT => 5]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE DATABASE IF NOT EXISTS adsbooster_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE adsbooster");

$tables = ['users', 'creator_bounties', 'creator_bounty_submissions', 'transactions', 'site_settings', 'invoices', 'notifications', 'referrals', 'pricing_plans'];

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

foreach ($tables as $table) {
    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
    if (!$exists) {
        echo "SKIP (missing in source): {$table}\n";
        continue;
    }
    $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
    $pdo->exec("USE adsbooster_test");
    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    $pdo->exec($create);
    $pdo->exec("USE adsbooster");
    echo "cloned: {$table}\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "Done. Test database ready.\n";
