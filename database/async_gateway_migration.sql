-- =====================================================================
-- ADSBOOSTER.NET — ASYNCHRONOUS DUAL THIRD-PARTY GATEWAY MIGRATION
-- BUNDLE.SOCIAL (ORGANIC ENGINE) + ZERNIO (ADS ENGINE) + ASYNC QUEUE
-- Version: 5.0
-- Compatible with: MySQL 8.0+, MariaDB 10.3+
--
-- PURPOSE
--   Adds the persistent asynchronous job queue so that NO user-facing
--   request performs synchronous external gateway calls. HTTP requests
--   enqueue instantly and `cron/queue_worker.php` performs every
--   Bundle.social / Zernio interaction out-of-band with exponential
--   backoff retries.
--
--   NOTE ON THE LEGACY OAUTH PURGE: the removal of direct OAuth token
--   columns/tables was completed by database/migrate_gateways.php (v4.0),
--   which restructured `connected_accounts` into the gateway-shaped table
--   below. That step is intentionally NOT repeated as a DROP here —
--   `users.two_factor_secret` is app-local 2FA, not an OAuth artifact,
--   and is deliberately preserved. Never re-run destructive drops on a
--   database that already carried the v4.0 restructure.
--
--   SUPERSET COLUMNS (documented add-ons beyond the original spec):
--     - `subject_key`     : dedupe key (e.g. 'post:12', 'campaign:9')
--                           so enqueueing the same subject twice is a no-op.
--     - `last_attempt_at` : worker heartbeat used to reclaim jobs stuck
--                           in 'processing' after a crashed worker.
--     - status 'cancelled': lets local cancels withdraw queued jobs.
--
--   For databases missing individual columns, run the guarded idempotent
--   migration instead:  php database/migrate_async.php
-- =====================================================================

-- 1. Third-Party Gateway Credentials Store (idempotent re-seed)
CREATE TABLE IF NOT EXISTS `third_party_gateways` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider_key` ENUM('bundle_social', 'zernio') UNIQUE NOT NULL,
    `api_key` VARCHAR(255) NOT NULL,
    `base_url` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `third_party_gateways` (`provider_key`, `api_key`, `base_url`, `is_active`) VALUES
('bundle_social', 'YOUR_BUNDLE_SOCIAL_API_KEY', 'https://api.bundle.social/v1', 1),
('zernio', 'YOUR_ZERNIO_API_KEY', 'https://api.zernio.com/v1', 1)
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 2. Unified Connected Accounts (refactored via the v4.0 gateway handshake;
--    recreated here only when a legacy database still carries OAuth columns)
CREATE TABLE IF NOT EXISTS `connected_accounts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `gateway_provider` ENUM('bundle_social', 'zernio') NOT NULL,
    `account_type` ENUM('organic_profile', 'ad_account') NOT NULL,
    `platform_name` VARCHAR(50) NOT NULL, -- 'meta','tiktok','google_ads','snapchat','linkedin','x','twitter','pinterest','youtube'...
    `external_account_id` VARCHAR(191) NOT NULL,
    `display_name` VARCHAR(150) NULL,
    `avatar_url` VARCHAR(255) NULL,
    `status` ENUM('connected', 'expired', 'error') NOT NULL DEFAULT 'connected',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `user_gateway_ext_acc` (`user_id`, `gateway_provider`, `external_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- 3. Asynchronous High-Throughput Job Queue Engine
CREATE TABLE IF NOT EXISTS `async_job_queue` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `job_type` VARCHAR(100) NOT NULL, -- 'bundle_publish_post','bundle_cancel_post','zernio_launch_ad','zernio_toggle_status'
    `subject_key` VARCHAR(64) NULL,   -- dedupe key, e.g. 'post:12' / 'campaign:9'
    `payload` LONGTEXT NOT NULL,      -- JSON Payload
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

-- 4. Link Ad Campaigns to the Zernio gateway (idempotent: pre-existing
--    columns from migrate_gateways.php v4.0 are left untouched)
-- ALTER TABLE `ad_campaigns`
--     ADD COLUMN `zernio_campaign_id` VARCHAR(191) NULL AFTER `status`,
--     ADD COLUMN `zernio_ad_account_id` VARCHAR(191) NULL AFTER `zernio_campaign_id`;
--    (The statements above are commented out because v4.0 already applied
--     them; re-running ALTERs that already exist fails on MySQL 8.0. Use
--     php database/migrate_async.php for guarded, idempotent applies.)

-- 5. Link Scheduled Posts to the Bundle.social gateway (same caveat)
-- ALTER TABLE `scheduled_posts`
--     ADD COLUMN `bundle_post_id` VARCHAR(191) NULL AFTER `status`;
