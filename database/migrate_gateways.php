<?php
declare(strict_types=1);

/**
 * Migration Script: Bundle.social & Zernio Third-Party Gateway Integration
 * Adsbooster.net
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

try {
    $pdo = Database::getInstance()->getConnection();

    echo "Running Gateway Migration...\n";

    // 1. Third-Party API Gateway Configuration Store
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `third_party_gateways` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `provider_key` ENUM('bundle_social', 'zernio') UNIQUE NOT NULL,
            `api_key` VARCHAR(255) NOT NULL,
            `base_url` VARCHAR(255) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
    ");

    // Seed default endpoints
    $pdo->exec("
        INSERT INTO `third_party_gateways` (`provider_key`, `api_key`, `base_url`, `is_active`) VALUES
        ('bundle_social', 'YOUR_BUNDLE_SOCIAL_API_KEY', 'https://api.bundle.social/v1', 1),
        ('zernio', 'YOUR_ZERNIO_API_KEY', 'https://api.zernio.com/v1', 1)
        ON DUPLICATE KEY UPDATE `updated_at` = NOW();
    ");
    echo " [x] `third_party_gateways` created and seeded.\n";

    // 2. Restructure Connected Accounts Table
    $pdo->exec("DROP TABLE IF EXISTS `connected_accounts`;");
    $pdo->exec("
        CREATE TABLE `connected_accounts` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `gateway_provider` ENUM('bundle_social', 'zernio') NOT NULL,
            `account_type` ENUM('organic_profile', 'ad_account') NOT NULL,
            `platform_name` VARCHAR(50) NOT NULL,
            `external_account_id` VARCHAR(191) NOT NULL,
            `display_name` VARCHAR(150) NULL,
            `avatar_url` VARCHAR(255) NULL,
            `status` ENUM('connected', 'expired', 'error') DEFAULT 'connected',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `idx_connected_accounts_user` (`user_id`),
            UNIQUE KEY `user_gateway_account` (`user_id`, `gateway_provider`, `external_account_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
    ");
    echo " [x] `connected_accounts` restructured.\n";

    // 3. Extend `ad_campaigns` Table
    $colsCampaigns = $pdo->query("SHOW COLUMNS FROM `ad_campaigns`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('zernio_campaign_id', $colsCampaigns, true)) {
        $pdo->exec("ALTER TABLE `ad_campaigns` ADD COLUMN `zernio_campaign_id` VARCHAR(191) NULL AFTER `status`;");
        echo " [x] Added `zernio_campaign_id` to `ad_campaigns`.\n";
    }
    if (!in_array('zernio_ad_account_id', $colsCampaigns, true)) {
        $pdo->exec("ALTER TABLE `ad_campaigns` ADD COLUMN `zernio_ad_account_id` VARCHAR(191) NULL AFTER `zernio_campaign_id`;");
        echo " [x] Added `zernio_ad_account_id` to `ad_campaigns`.\n";
    }

    // 4. Extend `scheduled_posts` Table
    $colsPosts = $pdo->query("SHOW COLUMNS FROM `scheduled_posts`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('bundle_post_id', $colsPosts, true)) {
        $pdo->exec("ALTER TABLE `scheduled_posts` ADD COLUMN `bundle_post_id` VARCHAR(191) NULL AFTER `status`;");
        echo " [x] Added `bundle_post_id` to `scheduled_posts`.\n";
    }

    // 5. Seed site_settings for third-party gateways if missing
    $settingKeys = [
        'bundle_social_api_key'  => 'YOUR_BUNDLE_SOCIAL_API_KEY',
        'bundle_social_base_url' => 'https://api.bundle.social/v1',
        'zernio_api_key'         => 'YOUR_ZERNIO_API_KEY',
        'zernio_base_url'        => 'https://api.zernio.com/v1',
    ];
    foreach ($settingKeys as $k => $v) {
        $exists = $pdo->query("SELECT 1 FROM `site_settings` WHERE `setting_key` = " . $pdo->quote($k))->fetch();
        if (!$exists) {
            $pdo->exec("INSERT INTO `site_settings` (`setting_key`, `setting_value`, `created_at`) VALUES (" . $pdo->quote($k) . ", " . $pdo->quote($v) . ", NOW())");
        }
    }
    echo " [x] Gateway settings seeded in `site_settings`.\n";

    echo "Migration completed successfully!\n";
} catch (\Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}

