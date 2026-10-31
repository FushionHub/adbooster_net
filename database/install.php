<?php

declare(strict_types=1);

/**
 * One-shot schema installer for Adsbooster.net
 *
 * Executes database/adsbooster_complete.sql (schema + seed data) against the
 * database already configured in config/db.php. Triggered automatically from
 * config/site_setting_and_config.php on a fresh deployment where the DB exists
 * but has no tables, and runnable manually from CLI:
 *
 *     php database/install.php
 *
 * Direct web access is blocked unless APP_INSTALL_ALLOW is defined by the
 * application bootstrap.
 */

if (php_sapi_name() !== 'cli' && !defined('APP_INSTALL_ALLOW')) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/db.php';

$dumpFile = __DIR__ . '/adsbooster_complete.sql';
if (!is_readable($dumpFile)) {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Missing {$dumpFile}\n");
    }
    return false;
}

$startedAt = microtime(true);

/**
 * Returns names of tables the dump creates.
 */
$dumpTables = static function (string $sql): array {
    preg_match_all('/CREATE TABLE [`"]?([a-zA-Z0-9_]+)[`"]?/i', $sql, $m);
    return array_values(array_unique($m[1]));
};

$expectedTables = [];

// Already installed? Fast path — every table the dump creates must exist
// (a partial earlier attempt leaves gaps and must be completed).
try {
    $probe = db()->fetchOne("SHOW TABLES LIKE 'site_settings'");
    if ($probe !== null) {
        $sqlHead = (string)file_get_contents($dumpFile);
        $expectedTables = $dumpTables($sqlHead);
        $missing = [];
        foreach ($expectedTables as $t) {
            if (db()->fetchOne("SHOW TABLES LIKE '" . $t . "'") === null) {
                $missing[] = $t;
            }
        }
        if ($missing === []) {
            if (php_sapi_name() === 'cli') {
                echo 'Schema already present (' . count($expectedTables) . " tables) — nothing to do.\n";
            }
            return true;
        }
        error_log('schema install: completing partial install, missing tables: ' . implode(', ', array_slice($missing, 0, 10)));
    }
} catch (\Throwable $e) {
    // Connection itself failed — nothing we can do here.
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, 'schema check failed: ' . $e->getMessage() . "\n");
    }
    return false;
}

$sql = file_get_contents($dumpFile);
if ($sql === false || trim($sql) === '') {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Could not read dump file.\n");
    }
    return false;
}

// Strip statements that would fight the existing connection/database context,
// and stray empty statements (a lone ";" aborts a multi-statement exec).
$sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;.*$/mi', '', $sql);
$sql = preg_replace('/(?m)^\s*;\s*$/', '', $sql);

if ($expectedTables === []) {
    $expectedTables = $dumpTables($sql);
}

$cred = db()->credentials();
$dsn = 'mysql:host=' . $cred['host']
    . ';port='   . $cred['port']
    . ';dbname=' . $cred['dbname']
    . ';charset=latin1';

$ok = false;
try {
    $pdo = new PDO($dsn, $cred['username'], $cred['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,   // the dump is many statements
    ]);
    $pdo->exec($sql);
    $ok = true;
} catch (\Throwable $e) {
    error_log('schema install exec failed: ' . $e->getMessage());
    // A race with a concurrent first request can make the second run fail on
    // "table already exists" — treat a complete schema as success below.
}

// Final verification: every table the dump declares must now exist.
try {
    $still = [];
    foreach ($expectedTables as $t) {
        if (db()->fetchOne("SHOW TABLES LIKE '" . $t . "'") === null) {
            $still[] = $t;
        }
    }
    $ok = ($still === []);
    if (!$ok) {
        error_log('schema install incomplete, missing: ' . implode(', ', array_slice($still, 0, 10)));
    }
} catch (\Throwable $e) {
    $ok = false;
}

if ($ok) {
    $ms = (int)round((microtime(true) - $startedAt) * 1000);
    error_log("schema installed successfully ({$ms} ms)");
    if (php_sapi_name() === 'cli') {
        echo "Schema installed in {$ms} ms.\n";
    }
} elseif (php_sapi_name() === 'cli') {
    fwrite(STDERR, "Schema install FAILED.\n");
}

return $ok;
