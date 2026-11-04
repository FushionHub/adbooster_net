<?php
declare(strict_types=1);

/**
 * Idempotent Migration: Asynchronous Dual-Gateway Queue (v5.0)
 * Adsbooster.net
 *
 * Creates/repairs the async_job_queue table and guarantees the gateway
 * linkage columns exist. Safe to run repeatedly (information_schema–
 * guarded ALTERs, so it works on partially migrated databases too):
 *
 *     php database/migrate_async.php
 *
 * The bootstrap auto-runs the queue-table creation; this script additionally
 * repairs any missing columns on custom/partial schemas. Full canonical SQL
 * artifact: database/async_gateway_migration.sql
 *
 * NOTE: the legacy OAuth purge itself was executed by migrate_gateways.php
 * (v4.0), which restructured connected_accounts into the gateway shape.
 * users.two_factor_secret is app-local 2FA (not OAuth) and is preserved.
 */

if (PHP_SAPI !== 'cli' && !defined('APP_INSTALL_ALLOW')) {
    if (!headers_sent()) {
        http_response_code(403);
    }
    exit('Forbidden: migrate_async is CLI-only.');
}

require_once __DIR__ . '/../config/site_setting_and_config.php';

try {
    $pdo = Database::getInstance()->getConnection();
    echo "Running Async Gateway Migration (v5.0)...\n";

    // 1. Async job queue (with superset columns: subject_key, cancelled, heartbeat)
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
    ");
    echo " [x] `async_job_queue` ready.\n";

    // 2. Column guards for partial schemas (MySQL 8.0 lacks ADD COLUMN IF NOT EXISTS)
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    };

    $alterTodos = [
        ['ad_campaigns',   'zernio_campaign_id',   "ALTER TABLE `ad_campaigns` ADD COLUMN `zernio_campaign_id` VARCHAR(191) NULL AFTER `status`"],
        ['ad_campaigns',   'zernio_ad_account_id', "ALTER TABLE `ad_campaigns` ADD COLUMN `zernio_ad_account_id` VARCHAR(191) NULL AFTER `zernio_campaign_id`"],
        ['scheduled_posts','bundle_post_id',       "ALTER TABLE `scheduled_posts` ADD COLUMN `bundle_post_id` VARCHAR(191) NULL AFTER `status`"],
        ['scheduled_posts','publish_error',        "ALTER TABLE `scheduled_posts` ADD COLUMN `publish_error` TEXT NULL"],
        ['async_job_queue','subject_key',          "ALTER TABLE `async_job_queue` ADD COLUMN `subject_key` VARCHAR(64) NULL AFTER `job_type`"],
        ['async_job_queue','last_attempt_at',      "ALTER TABLE `async_job_queue` ADD COLUMN `last_attempt_at` DATETIME NULL"],
    ];
    foreach ($alterTodos as [$table, $column, $sql]) {
        if (!$hasColumn($table, $column)) {
            $pdo->exec($sql);
            echo " [x] Added `{$table}`.`{$column}`.\n";
        }
    }

    // 3. Subject dedupe index (only if the column was just added or index missing)
    try {
        $idx = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'async_job_queue' AND INDEX_NAME = 'idx_queue_subject'"
        )->fetchColumn();
        if ((int)$idx === 0 && $hasColumn('async_job_queue', 'subject_key')) {
            $pdo->exec("ALTER TABLE `async_job_queue` ADD INDEX `idx_queue_subject` (`subject_key`, `status`)");
            echo " [x] Added `async_job_queue`.`idx_queue_subject`.\n";
        }
    } catch (\Throwable $e) {
        echo " [!] Index check skipped: " . $e->getMessage() . "\n";
    }

    // 4. Direct-API purge (dual gateway v5.1): drop retired per-platform
    //    token columns. The Zernio Ads Engine holds every platform
    //    credential; pixel IDs stay as honest routing labels. Grandfathered
    //    token DATA is removed with it — no platform secret is kept.
    foreach (
        ['meta_capi_access_token', 'tiktok_events_access_token'] as $legacyTokenColumn
    ) {
        if ($hasColumn('server_tracking_pixels', $legacyTokenColumn)) {
            $pdo->exec("ALTER TABLE `server_tracking_pixels` DROP COLUMN `{$legacyTokenColumn}`");
            echo " [x] Purged legacy platform token column `server_tracking_pixels`.`{$legacyTokenColumn}` (credentials live inside the Zernio engine).\n";
        }
    }

    // 5. Gateway credentials re-seed (idempotent)
    $pdo->exec("
        INSERT INTO `third_party_gateways` (`provider_key`, `api_key`, `base_url`, `is_active`) VALUES
        ('bundle_social', 'YOUR_BUNDLE_SOCIAL_API_KEY', 'https://api.bundle.social/v1', 1),
        ('zernio', 'YOUR_ZERNIO_API_KEY', 'https://api.zernio.com/v1', 1)
        ON DUPLICATE KEY UPDATE `updated_at` = NOW();
    ");
    echo " [x] `third_party_gateways` seeded (placeholder keys until you set live ones).\n";

    echo "Async Gateway Migration completed successfully!\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
