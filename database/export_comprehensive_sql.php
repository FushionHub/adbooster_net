<?php

declare(strict_types=1);

$base = 'c:/laragon/www/seopp';
require_once $base . '/config/db.php';

$pdo = Database::getInstance()->getConnection();

// Tables in correct foreign key creation order or with FOREIGN_KEY_CHECKS = 0
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

$sqlHeader = <<<SQL
-- =====================================================================
-- ADSBOOSTER.NET — COMPREHENSIVE MASTER DATABASE SCHEMA & INITIAL DATA
-- Version: 3.0 (cPanel Shared Hosting & Production Optimized)
-- Engine: InnoDB | Character Set: latin1 | Collation: latin1_swedish_ci
-- Generated Date: 2026-10-18
-- Compatible with: MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+
--
-- Safe for fresh installation or updating an existing database.
-- Uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE INTO`.
-- =====================================================================

SET NAMES latin1;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


SQL;

$sqlTables = "";

// Tables whose seed data should be included
$seedTables = [
    'achievements',
    'pricing_plans',
    'pricing_plan_currency_overrides',
    'site_settings',
    'policy_rules',
    'blog_posts',
    'translations',
    'users',
    'agency_portals',
    'marketplace_items',
    'jobs',
    'creator_bounties',
    'user_portfolios',
    'user_vcards',
    'dating_profiles',
    'ai_media_buyer_rules'
];

foreach ($tables as $table) {
    // Get CREATE TABLE
    $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $createSql = $row['Create Table'] ?? '';

    // Convert to CREATE TABLE IF NOT EXISTS
    $createSql = preg_replace('/^CREATE TABLE/i', 'CREATE TABLE IF NOT EXISTS', $createSql);

    // Remove AUTO_INCREMENT=\d+ from table options so clean imports start at 1 or follow data
    $createSql = preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $createSql);

    // Ensure InnoDB and latin1_swedish_ci
    if (!str_contains($createSql, 'COLLATE=')) {
        $createSql = preg_replace('/DEFAULT CHARSET=\w+/i', 'DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci', $createSql);
    }

    $sqlTables .= "-- -----------------------------------------------------\n";
    $sqlTables .= "-- Table structure for `$table`\n";
    $sqlTables .= "-- -----------------------------------------------------\n";
    $sqlTables .= $createSql . ";\n\n";

    // Dump seed data if in seedTables
    if (in_array($table, $seedTables, true)) {
        $dataRows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dataRows)) {
            $sqlTables .= "-- Dumping seed data for table `$table`\n";
            $cols = array_keys($dataRows[0]);
            $colList = '`' . implode('`, `', $cols) . '`';

            $valChunks = [];
            foreach ($dataRows as $r) {
                $vals = [];
                foreach ($cols as $c) {
                    $val = $r[$c];
                    if ($val === null) {
                        $vals[] = 'NULL';
                    } elseif (is_int($val) || is_float($val)) {
                        $vals[] = $val;
                    } else {
                        $vals[] = $pdo->quote((string)$val);
                    }
                }
                $valChunks[] = "(" . implode(', ', $vals) . ")";
            }

            // Write in batches of 25 for safe cPanel execution
            $batches = array_chunk($valChunks, 25);
            foreach ($batches as $batch) {
                $sqlTables .= "INSERT IGNORE INTO `$table` ($colList) VALUES\n" . implode(",\n", $batch) . ";\n";
            }
            $sqlTables .= "\n";
        }
    }
}

$sqlFooter = <<<SQL
-- =====================================================================
-- RE-ENABLE FOREIGN KEY CHECKS & COMMIT
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

SQL;

$fullSql = $sqlHeader . $sqlTables . $sqlFooter;

file_put_contents($base . '/database/adsbooster_complete.sql', $fullSql);
file_put_contents($base . '/adsbooster_cpanel_full.sql', $fullSql);

echo "Successfully generated:\n";
echo "1. database/adsbooster_complete.sql (" . strlen($fullSql) . " bytes)\n";
echo "2. adsbooster_cpanel_full.sql (" . strlen($fullSql) . " bytes)\n";
echo "Total tables processed: " . count($tables) . "\n";
