<?php declare(strict_types=1);

/**
 * Charset Migration — normalise every table to utf8mb4.
 *
 * Why: the platform stores user-authored text (names, captions, bounty
 * titles, inbox messages, gateway credentials) that legitimately contains
 * emoji and non-Latin scripts. latin1 cannot represent those, so MySQL
 * truncates them on write ("Data truncated for column ..."). A mixed
 * schema (some tables utf8mb4, some latin1) also makes the PDO connection
 * charset ambiguous.
 *
 * This script is idempotent: tables already on utf8mb4 are skipped.
 *
 * Usage: php database/migrate_charset_utf8mb4.php
 */

require_once __DIR__ . '/../config/db.php';

$targetCharset = 'utf8mb4';
// utf8mb4_general_ci is the one utf8mb4 collation that exists on every
// host the project is deployed to:
//   * MySQL 5.5.3+  (utf8mb4_0900_ai_ci does NOT exist here)
//   * MySQL 8.x
//   * MariaDB 10.x  (utf8mb4_0900_ai_ci does NOT exist here)
// Using utf8mb4_unicode_ci / utf8mb4_0900_ai_ci in a dump breaks imports
// on shared cPanel hosting (phpMyAdmin reports "Unknown collation").
$targetCollate = 'utf8mb4_general_ci';

$pdo = db()->getConnection();

$tables = $pdo->query(
    "SELECT table_name, table_collation FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
     ORDER BY table_name"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Charset migration -> {$targetCharset}/{$targetCollate}\n";
echo str_repeat('-', 60) . "\n";

$converted = 0;
$skipped  = 0;
$failed   = [];

foreach ($tables as $t) {
    $name    = (string)($t['TABLE_NAME'] ?? $t['table_name'] ?? '');
    $collate = (string)($t['TABLE_COLLATION'] ?? $t['table_collation'] ?? '');

    if ($name === '') {
        continue;
    }
    if (str_starts_with($collate, $targetCharset . '_') || $collate === $targetCharset) {
        // The charset is already utf8mb4 — only skip when the collation is
        // the target one too. MySQL 8 creates tables with
        // utf8mb4_0900_ai_ci, which does not exist on MariaDB / MySQL 5.x.
        if ($collate === $targetCollate) {
            $skipped++;
            continue;
        }
    }

    $sql = "ALTER TABLE `{$name}` CONVERT TO CHARACTER SET {$targetCharset} COLLATE {$targetCollate}";
    try {
        $pdo->exec($sql);
        echo "  [converted] {$name} ({$collate})\n";
        $converted++;
    } catch (\Throwable $e) {
        echo "  [FAILED]    {$name}: " . $e->getMessage() . "\n";
        $failed[] = $name;
    }
}

// The connection charset must match the schema so no implicit conversion
// happens on the way in or out.
try {
    $pdo->exec("SET NAMES {$targetCharset} COLLATE {$targetCollate}");
    echo "\nConnection charset set to {$targetCharset}.\n";
} catch (\Throwable $e) {
    echo "\nCould not set connection charset: " . $e->getMessage() . "\n";
}

echo "\nConverted: {$converted} | Already correct: {$skipped} | Failed: " . count($failed) . "\n";
if ($failed) {
    echo "Failed tables: " . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
