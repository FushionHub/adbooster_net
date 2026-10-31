<?php

declare(strict_types=1);

$base = 'c:/laragon/www/seopp';
require_once $base . '/config/db.php';
$pdo = Database::getInstance()->getConnection();

// 1. Get all base tables (exclude VIEW 'tickets')
$stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$rawTables = $stmt->fetchAll(PDO::FETCH_NUM);
$tables = [];
foreach ($rawTables as $rt) {
    $tables[] = $rt[0];
}
sort($tables);

echo "Found " . count($tables) . " base tables.\n";

$header = <<<SQL
-- =====================================================================
-- ADSBOOSTER.NET — MASTER DATABASE SCHEMA & COMPLETE SEED DATA
-- Version: 3.1 (cPanel Shared Hosting & Production Compatible)
-- Engine: InnoDB | Character Set: latin1 | Collation: latin1_swedish_ci
-- Generated Date: 2026-10-20
-- Compatible with: MySQL 5.5+, MySQL 5.7+, MySQL 8.0+, MariaDB 10.x+
--
-- Safe for fresh install or updates:
-- Uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE INTO`.
-- Zero undefined collations: No latin1_0900_ai_ci errors on cPanel!
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES latin1;
SET FOREIGN_KEY_CHECKS = 0;


SQL;

$tablesSql = "";

foreach ($tables as $t) {
    $row = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
    $create = $row['Create Table'] ?? '';

    // Convert CREATE TABLE to CREATE TABLE IF NOT EXISTS
    $create = preg_replace('/^CREATE TABLE/i', 'CREATE TABLE IF NOT EXISTS', $create);

    // Remove AUTO_INCREMENT=\d+ from table options so clean starts start at 1 or use insert values
    $create = preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $create);

    // Replace all latin1 / latin1_0900_ai_ci / latin1_swedish_ci column collations with latin1_swedish_ci
    $create = preg_replace('/COLLATE\s*=?\s*latin1_[a-zA-Z0-9_]+/i', 'COLLATE latin1_swedish_ci', $create);
    $create = preg_replace('/COLLATE\s*=?\s*utf8_[a-zA-Z0-9_]+/i', 'COLLATE latin1_swedish_ci', $create);

    // Replace table charset and collation
    $create = preg_replace('/DEFAULT CHARSET=\w+/i', 'DEFAULT CHARSET=latin1', $create);
    $create = preg_replace('/COLLATE=\w+/i', 'COLLATE=latin1_swedish_ci', $create);

    // If COLLATE is not specified on the table options, add it
    if (!str_contains($create, 'COLLATE=')) {
        $create = preg_replace('/ENGINE=InnoDB/i', 'ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci', $create);
    }

    $tablesSql .= "-- -----------------------------------------------------\n";
    $tablesSql .= "-- Table structure for `$t`\n";
    $tablesSql .= "-- -----------------------------------------------------\n";
    $tablesSql .= $create . ";\n\n";
}

// Add the view 'tickets' (without DEFINER)
$viewSql = <<<SQL
-- -----------------------------------------------------
-- View structure for `tickets`
-- -----------------------------------------------------
DROP VIEW IF EXISTS `tickets`;
CREATE OR REPLACE VIEW `tickets` AS select `support_tickets`.`id` AS `id`,`support_tickets`.`user_id` AS `user_id`,lpad(`support_tickets`.`id`,6,'0') AS `ticket_number`,`support_tickets`.`ticket_type` AS `type`,`support_tickets`.`priority` AS `priority`,`support_tickets`.`subject` AS `subject`,`support_tickets`.`status` AS `status`,`support_tickets`.`created_at` AS `created_at`,`support_tickets`.`updated_at` AS `updated_at` from `support_tickets`;


SQL;

// 2. Extract ALL INSERT statements from adsbooster (2).sql
$dumpFile = $base . '/adsbooster (2).sql';
$lines = file($dumpFile);

$insertsSql = "-- =====================================================================\n";
$insertsSql .= "-- COMPLETE DATA DUMP (PRESERVED FROM ADSBOOSTER (2).SQL)\n";
$insertsSql .= "-- =====================================================================\n\n";

$inInsert = false;
$currentInsert = "";
$insertCount = 0;

foreach ($lines as $line) {
    if (!$inInsert) {
        if (preg_match('/^INSERT INTO `([^`]+)`/i', $line)) {
            $inInsert = true;
            $currentInsert = $line;
            if (str_ends_with(rtrim($line), ';')) {
                // Single line insert
                $currentInsert = preg_replace('/^INSERT INTO /i', 'INSERT IGNORE INTO ', $currentInsert);
                $insertsSql .= $currentInsert . "\n";
                $insertCount++;
                $inInsert = false;
                $currentInsert = "";
            }
        }
    } else {
        $currentInsert .= $line;
        if (str_ends_with(rtrim($line), ';')) {
            $currentInsert = preg_replace('/^INSERT INTO /i', 'INSERT IGNORE INTO ', $currentInsert);
            $insertsSql .= $currentInsert . "\n";
            $insertCount++;
            $inInsert = false;
            $currentInsert = "";
        }
    }
}

echo "Extracted and converted {$insertCount} INSERT statements.\n";

$footer = <<<SQL
-- =====================================================================
-- RE-ENABLE FOREIGN KEY CHECKS & COMMIT
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

SQL;

$finalSql = $header . $tablesSql . $viewSql . $insertsSql . $footer;

// Save to both adsbooster_cpanel_full.sql and database/adsbooster_complete.sql
file_put_contents($base . '/adsbooster_cpanel_full.sql', $finalSql);
file_put_contents($base . '/database/adsbooster_complete.sql', $finalSql);

echo "Successfully wrote adsbooster_cpanel_full.sql (" . strlen($finalSql) . " bytes)\n";
echo "Successfully updated database/adsbooster_complete.sql\n";
