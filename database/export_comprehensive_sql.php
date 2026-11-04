<?php

declare(strict_types=1);

/**
 * Adsbooster.net — Comprehensive SQL exporter.
 *
 * Generates `database/adsbooster_complete.sql`:
 *   1. Every table as `CREATE TABLE IF NOT EXISTS` (existing tables untouched)
 *   2. Seed data via `INSERT IGNORE` (existing rows never modified)
 *   3. A SCHEMA HARMONIZATION section: guarded, idempotent ADD COLUMN fixes
 *      (via INFORMATION_SCHEMA + PREPARED statements) for columns the
 *      application expects — safe to run against an upgraded or partial DB.
 *
 * CLI:  php database/export_comprehensive_sql.php
 */

$base = 'c:/laragon/www/seopp';
require_once $base . '/config/db.php';

$pdo = Database::getInstance()->getConnection();

$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

// Tables whose seed data (INSERT IGNORE) is included in the dump.
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
    'ai_media_buyer_rules',
];

$sqlHeader = <<<SQL
-- =====================================================================
-- ADSBOOSTER.NET — COMPREHENSIVE MASTER DATABASE SCHEMA & INITIAL DATA
-- Version: 6.0 (Asynchronous Dual Third-Party Gateway Edition)
--          Bundle.social (organic engine) + Zernio (ads engine)
--          + the high-throughput `async_job_queue` processed by
--          `cron/queue_worker.php`. 100% of external platform traffic
--          routes through the two gateways; no direct platform tokens.
-- Engine: InnoDB | Charset: as emitted from the live schema (latin1/latin1)
-- Compatible with: MySQL 8.0+, MySQL 5.7+, MariaDB 10.3+
--
-- IDEMPOTENT / UPGRADE-SAFE:
--   * `CREATE TABLE IF NOT EXISTS`  → existing tables are NEVER touched
--   * `INSERT IGNORE` seed rows     → existing data is NEVER modified
--   * «SCHEMA HARMONIZATION» ALTERs → each guarded by INFORMATION_SCHEMA,
--     applied ONLY when the column does not exist yet
-- Safe for: fresh installation AND upgrading an existing installation.
-- Re-run as often as needed.
-- =====================================================================

SET NAMES latin1;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


SQL;

$sqlTables = "";

foreach ($tables as $table) {
    // Get CREATE TABLE
    $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $createSql = $row['Create Table'] ?? '';

    // Convert to CREATE TABLE IF NOT EXISTS
    $createSql = preg_replace('/^CREATE TABLE/i', 'CREATE TABLE IF NOT EXISTS', $createSql);

    // Remove AUTO_INCREMENT=\d+ from table options so clean imports start at 1 or follow data
    $createSql = preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $createSql);

    // Keep the live schema's own charset/collation — do NOT rewrite it.

    $sqlTables .= "-- -----------------------------------------------------\n";
    $sqlTables .= "-- Table structure for `$table`\n";
    $sqlTables .= "-- -----------------------------------------------------\n";
    $sqlTables .= $createSql . ";\n\n";

    // Dump seed data if in seedTables
    if (in_array($table, $seedTables, true)) {
        $dataRows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dataRows)) {
            $sqlTables .= "-- Seed data for `$table` (INSERT IGNORE — existing rows win)\n";
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

// -------------------------------------------------------------------------
// SCHEMA HARMONIZATION — guarded, idempotent ADD COLUMN fixes.
// Application code queries these columns; partial/older databases are
// upgraded in place without touching anything that already exists.
// -------------------------------------------------------------------------

$harmonize = [
    'users' => [
        // [column, full ADD COLUMN definition]
        ['account_type',          "`account_type` VARCHAR(30) NULL DEFAULT 'individual'"],
        ['role',                  "`role` VARCHAR(30) NOT NULL DEFAULT 'user'"],
        ['user_role',             "`user_role` VARCHAR(30) NULL"],
        ['is_admin',              "`is_admin` TINYINT(1) NOT NULL DEFAULT 0"],
        ['status',                "`status` VARCHAR(30) NOT NULL DEFAULT 'active'"],
        ['password',              "`password` VARCHAR(255) NULL"],
        ['google_id',             "`google_id` VARCHAR(64) NULL"],
        ['avatar',                "`avatar` VARCHAR(255) NULL"],
        ['company',               "`company` VARCHAR(150) NULL"],
        ['phone',                 "`phone` VARCHAR(40) NULL"],
        ['facebook',              "`facebook` VARCHAR(255) NULL"],
        ['twitter',               "`twitter` VARCHAR(255) NULL"],
        ['linkedin',              "`linkedin` VARCHAR(255) NULL"],
        ['plan_id',               "`plan_id` INT NULL"],
        ['ai_preferred_provider', "`ai_preferred_provider` VARCHAR(20) NULL DEFAULT NULL"],
        ['referral_code',         "`referral_code` VARCHAR(32) NULL"],
        ['referred_by',           "`referred_by` INT NULL"],
        ['onboarding_completed',  "`onboarding_completed` TINYINT(1) NOT NULL DEFAULT 0"],
        ['two_factor_secret',     "`two_factor_secret` VARCHAR(255) NULL"],
        ['two_factor_enabled',    "`two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0"],
        ['totp_enabled',          "`totp_enabled` TINYINT(1) NOT NULL DEFAULT 0"],
    ],
    'ad_campaigns' => [
        ['zernio_campaign_id',   "`zernio_campaign_id` VARCHAR(191) NULL"],
        ['zernio_ad_account_id', "`zernio_ad_account_id` VARCHAR(191) NULL"],
        ['creative_media_url',   "`creative_media_url` VARCHAR(255) NULL"],
        ['headline',             "`headline` VARCHAR(190) NULL"],
        ['primary_text',         "`primary_text` TEXT NULL"],
        ['destination_url',      "`destination_url` VARCHAR(255) NULL"],
    ],
    'scheduled_posts' => [
        ['bundle_post_id', "`bundle_post_id` VARCHAR(191) NULL"],
        ['publish_error',  "`publish_error` TEXT NULL"],
        ['media_url',      "`media_url` VARCHAR(255) NULL"],
    ],
    'async_job_queue' => [
        ['subject_key',     "`subject_key` VARCHAR(64) NULL"],
        ['last_attempt_at', "`last_attempt_at` DATETIME NULL"],
        ['max_attempts',    "`max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5"],
    ],
    'marketplace_items' => [
        ['views_count', "`views_count` INT UNSIGNED NOT NULL DEFAULT 0"],
        ['rating',      "`rating` DECIMAL(3,2) NULL DEFAULT NULL"],
    ],
    'ai_video_creatives' => [
        ['render_mode', "`render_mode` VARCHAR(40) NULL"],
        ['provider',    "`provider` VARCHAR(40) NULL"],
    ],
];

$sqlHarmonize = <<<'SQL'

-- =====================================================================
-- SCHEMA HARMONIZATION (guarded & idempotent)
-- Each ALTER below runs ONLY if the column is missing on this database.
-- MySQL has no `ADD COLUMN IF NOT EXISTS`; the INFORMATION_SCHEMA +
-- PREPARE pattern makes every statement re-runnable safely.
-- =====================================================================
SQL;

foreach ($harmonize as $table => $cols) {
    $hasTable = in_array($table, $tables, true);
    $sqlHarmonize .= "\n-- `$table`\n";
    if (!$hasTable) {
        // Table missing entirely → it was created above; nothing to alter.
        continue;
    }
    foreach ($cols as [$col, $definition]) {
        // The definition is embedded in a single-quoted SQL string literal:
        // double any single quotes so `DEFAULT 'individual'` survives intact.
        $definition = str_replace("'", "''", $definition);
        $sqlHarmonize .= "SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS\n"
            . "    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$col}');\n"
            . "SET @db_fix_stmt := IF(@db_has_col = 0,\n"
            . "    'ALTER TABLE `{$table}` ADD COLUMN {$definition}',\n"
            . "    'SELECT 1');\n"
            . "PREPARE db_fix FROM @db_fix_stmt;\n"
            . "EXECUTE db_fix;\n"
            . "DEALLOCATE PREPARE db_fix;\n";
    }
}

$sqlHarmonize .= <<<'SQL'

-- ---------------------------------------------------------------------
-- Gateway credential seed (idempotent — placeholder keys keep the
-- gateways in documented simulation mode until real keys are set)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `third_party_gateways` (`provider_key`, `api_key`, `base_url`, `is_active`) VALUES
('bundle_social', 'YOUR_BUNDLE_SOCIAL_API_KEY', 'https://api.bundle.social',  1),
('zernio',        'YOUR_ZERNIO_API_KEY',        'https://zernio.com/api/v1', 1);

-- =====================================================================
-- RE-ENABLE FOREIGN KEY CHECKS & COMMIT
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

SQL;

$fullSql = $sqlHeader . $sqlTables . $sqlHarmonize;

file_put_contents($base . '/database/adsbooster_complete.sql', $fullSql);
file_put_contents($base . '/adsbooster_cpanel_full.sql', $fullSql);

echo "Successfully generated:\n";
echo "1. database/adsbooster_complete.sql (" . strlen($fullSql) . " bytes)\n";
echo "2. adsbooster_cpanel_full.sql (" . strlen($fullSql) . " bytes)\n";
echo "Total tables processed: " . count($tables) . "\n";
echo "Harmonized column guards: " . array_sum(array_map('count', $harmonize)) . "\n";
