-- =====================================================================
-- ADSBOOSTER.NET — COMPREHENSIVE MASTER DATABASE SCHEMA & INITIAL DATA
-- Version: 6.0 (Asynchronous Dual Third-Party Gateway Edition)
--          Bundle.social (organic engine) + Zernio (ads engine)
--          + the high-throughput `async_job_queue` processed by
--          `cron/queue_worker.php`. 100% of external platform traffic
--          routes through the two gateways; no direct platform tokens.
-- Engine: InnoDB | Charset: as emitted from the live schema (utf8mb4/latin1)
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

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- -----------------------------------------------------
-- Table structure for `ad_campaigns`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ad_campaigns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `campaign_name` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `campaign_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objective` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `budget_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'daily',
  `budget_amount` decimal(12,2) DEFAULT '0.00',
  `targeting_spec` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `headline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `destination_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creative_media_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `zernio_campaign_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zernio_ad_account_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autopilot_enabled` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for `agency_client_accounts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agency_client_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agency_user_id` int NOT NULL,
  `client_user_id` int DEFAULT NULL,
  `client_name` varchar(255) NOT NULL,
  `client_email` varchar(255) NOT NULL,
  `client_company` varchar(255) DEFAULT NULL,
  `monthly_retainer` decimal(10,2) DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'USD',
  `access_token` varchar(100) NOT NULL,
  `status` enum('active','paused','pending') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_access_token` (`access_token`),
  KEY `idx_agency_clients_user` (`agency_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `agency_client_approvals`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agency_client_approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agency_user_id` int NOT NULL,
  `client_account_id` int NOT NULL,
  `item_type` enum('ad_campaign','social_post','ugc_video','report') NOT NULL,
  `item_id` int DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `preview_data` json DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected_revision') DEFAULT 'pending',
  `client_feedback` text,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_approvals_agency` (`agency_user_id`),
  KEY `idx_approvals_client` (`client_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `agency_portals`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `agency_portals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agency_user_id` int NOT NULL,
  `custom_subdomain` varchar(100) NOT NULL,
  `custom_domain` varchar(255) DEFAULT NULL,
  `portal_name` varchar(255) NOT NULL,
  `portal_logo_url` varchar(500) DEFAULT NULL,
  `brand_primary_color` varchar(20) DEFAULT '#c8ff00',
  `brand_secondary_color` varchar(20) DEFAULT '#080c14',
  `welcome_headline` varchar(255) DEFAULT 'Welcome to Your Client Growth Portal',
  `is_whitelabel_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_agency_subdomain` (`custom_subdomain`),
  KEY `idx_agency_portal_user` (`agency_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `ai_media_buyer_logs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_media_buyer_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `campaign_id` int DEFAULT NULL,
  `rule_id` int DEFAULT NULL,
  `action_taken` varchar(255) NOT NULL,
  `details` text,
  `metrics_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_buyer_logs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `ai_media_buyer_rules`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_media_buyer_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `campaign_id` int DEFAULT NULL,
  `rule_name` varchar(255) NOT NULL,
  `condition_metric` enum('cpa','ctr','roas','spend_without_conv','frequency') NOT NULL,
  `condition_operator` enum('>','<','>=','<=') NOT NULL,
  `condition_value` decimal(10,2) NOT NULL,
  `action_type` enum('pause_campaign','scale_budget_pct','reduce_budget_pct','trigger_creative_refresh','notify_only') NOT NULL,
  `action_value` decimal(10,2) DEFAULT '0.00',
  `is_active` tinyint(1) DEFAULT '1',
  `last_triggered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_buyer_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `ai_video_creatives`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_video_creatives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `avatar_id` varchar(100) NOT NULL,
  `voice_id` varchar(100) DEFAULT 'en_female_energetic',
  `hook_text` varchar(500) NOT NULL,
  `script_body` text NOT NULL,
  `cta_text` varchar(500) DEFAULT NULL,
  `caption_style` varchar(100) DEFAULT 'tiktok_glow',
  `broll_keyword` varchar(255) DEFAULT 'lifestyle unboxing',
  `audio_track` varchar(255) DEFAULT 'viral_lofi_beat',
  `video_url` varchar(500) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `aspect_ratio` varchar(20) DEFAULT '9:16',
  `duration_seconds` int DEFAULT '20',
  `status` enum('generating','completed','failed') DEFAULT 'completed',
  `credits_used` int DEFAULT '3',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `render_mode` varchar(40) DEFAULT NULL,
  `provider` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_video_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `api_rate_limits`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key_id` int unsigned NOT NULL DEFAULT '0',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `window_minute` int unsigned NOT NULL DEFAULT '0',
  `request_count` int unsigned NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_window` (`key_id`,`ip_address`,`window_minute`),
  KEY `idx_window` (`window_minute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for `async_job_queue`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `async_job_queue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `job_type` varchar(100) NOT NULL,
  `subject_key` varchar(64) DEFAULT NULL,
  `payload` longtext NOT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `max_attempts` tinyint unsigned NOT NULL DEFAULT '5',
  `error_log` text,
  `scheduled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `last_attempt_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue_status_schedule` (`status`,`scheduled_at`),
  KEY `idx_user_jobs` (`user_id`,`status`),
  KEY `idx_queue_subject` (`subject_key`,`status`),
  CONSTRAINT `async_job_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `blog_posts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `author_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `tags` varchar(255) DEFAULT NULL,
  `excerpt` text,
  `body` longtext,
  `featured_image` varchar(500) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `is_featured` tinyint(1) DEFAULT '0',
  `views_count` int DEFAULT '0',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_slug` (`slug`),
  KEY `idx_blog_status_pub` (`status`,`published_at`),
  KEY `idx_blog_author` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `campaign_metrics_daily`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_metrics_daily` (
  `id` int NOT NULL AUTO_INCREMENT,
  `campaign_id` int DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `impressions` int DEFAULT '0',
  `clicks` int DEFAULT '0',
  `spend` decimal(12,2) DEFAULT '0.00',
  `conversions` int DEFAULT '0',
  `ctr` decimal(8,2) DEFAULT '0.00',
  `cpc` decimal(12,2) DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for `competitor_ad_analyses`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `competitor_ad_analyses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `competitor_name` varchar(255) NOT NULL,
  `competitor_url` varchar(500) NOT NULL,
  `platform` varchar(50) DEFAULT 'meta',
  `scraped_ads_count` int DEFAULT '0',
  `extracted_hooks` json DEFAULT NULL,
  `extracted_angles` json DEFAULT NULL,
  `target_audience_insights` json DEFAULT NULL,
  `counter_creatives` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ad_analyses_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `connected_accounts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `connected_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `gateway_provider` enum('bundle_social','zernio') NOT NULL,
  `account_type` enum('organic_profile','ad_account') NOT NULL,
  `platform_name` varchar(50) NOT NULL,
  `external_account_id` varchar(191) NOT NULL,
  `display_name` varchar(150) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `status` enum('connected','expired','error') DEFAULT 'connected',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_gateway_account` (`user_id`,`gateway_provider`,`external_account_id`),
  KEY `idx_connected_accounts_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `creator_bounties`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `creator_bounties` (
  `id` int NOT NULL AUTO_INCREMENT,
  `brand_user_id` int NOT NULL,
  `title` varchar(500) NOT NULL,
  `description` text NOT NULL,
  `platform` enum('tiktok','instagram_reels','youtube_shorts','all') DEFAULT 'tiktok',
  `content_type` enum('ugc_talking_head','unboxing','tutorial','lifestyle_testimonial') DEFAULT 'ugc_talking_head',
  `deliverables_req` text NOT NULL,
  `bounty_amount` decimal(10,2) NOT NULL,
  `platform_fee_pct` decimal(5,2) DEFAULT '10.00',
  `total_escrow_held` decimal(10,2) NOT NULL,
  `escrow_status` enum('funded','released_to_creator','refunded_to_brand') DEFAULT 'funded',
  `claimed_creator_id` int DEFAULT NULL,
  `status` enum('open','in_progress','draft_submitted','completed','disputed','cancelled') DEFAULT 'open',
  `submission_deadline` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bounties_brand` (`brand_user_id`),
  KEY `idx_bounties_status` (`status`),
  KEY `idx_bounties_creator` (`claimed_creator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `creator_bounty_submissions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `creator_bounty_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bounty_id` int NOT NULL,
  `creator_user_id` int NOT NULL,
  `video_url` varchar(500) NOT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `submission_notes` text,
  `ai_compliance_score` int DEFAULT '100',
  `ai_compliance_report` json DEFAULT NULL,
  `review_status` enum('pending_review','approved','revision_requested') DEFAULT 'pending_review',
  `revision_notes` text,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bounty_subs_bounty` (`bounty_id`),
  KEY `idx_bounty_subs_creator` (`creator_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `cron_run_logs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cron_run_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for `ecommerce_campaign_packs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ecommerce_campaign_packs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `user_id` int NOT NULL,
  `ugc_images` json DEFAULT NULL,
  `copy_variations` json DEFAULT NULL,
  `posting_calendar_14d` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ecom_packs_product` (`product_id`),
  KEY `idx_ecom_packs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `ecommerce_products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ecommerce_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `store_id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_title` varchar(500) NOT NULL,
  `product_handle` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'USD',
  `image_url` varchar(500) DEFAULT NULL,
  `description` text,
  `rating` decimal(3,2) DEFAULT '5.00',
  `reviews_count` int DEFAULT '0',
  `auto_pack_generated` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ecom_prods_store` (`store_id`),
  KEY `idx_ecom_prods_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `ecommerce_stores`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ecommerce_stores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `store_type` enum('shopify','woocommerce','custom') NOT NULL DEFAULT 'shopify',
  `store_name` varchar(255) NOT NULL,
  `store_url` varchar(500) NOT NULL,
  `api_key` varchar(500) DEFAULT NULL,
  `is_connected` tinyint(1) DEFAULT '1',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ecom_stores_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `jobs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `job_type` varchar(50) DEFAULT 'full_time',
  `location_type` varchar(50) DEFAULT 'remote',
  `location` varchar(255) DEFAULT NULL,
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `salary_currency` varchar(10) DEFAULT 'USD',
  `salary_period` varchar(20) DEFAULT 'month',
  `description` longtext,
  `requirements` text,
  `skills_required` varchar(500) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'open',
  `featured` tinyint(1) DEFAULT '0',
  `views_count` int DEFAULT '0',
  `applications_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jobs_slug` (`slug`),
  KEY `idx_jobs_status` (`status`,`created_at`),
  KEY `idx_jobs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `marketplace_items`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `marketplace_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `item_type` varchar(50) DEFAULT 'business',
  `business_type` varchar(50) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `price` decimal(10,2) DEFAULT '0.00',
  `description` longtext,
  `address` varchar(500) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `whatsapp` varchar(50) DEFAULT NULL,
  `contact_email` varchar(190) DEFAULT NULL,
  `website_url` varchar(500) DEFAULT NULL,
  `google_maps_url` varchar(500) DEFAULT NULL,
  `google_maps_embed` text,
  `image_url` varchar(500) DEFAULT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `listing_fee` decimal(10,2) DEFAULT '0.00',
  `status` varchar(20) DEFAULT 'active',
  `seller_verified` tinyint(1) DEFAULT '0',
  `is_boosted` tinyint(1) DEFAULT '0',
  `featured` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `views_count` int DEFAULT '0',
  `rating` decimal(3,1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_slug` (`slug`),
  KEY `idx_mp_status` (`status`,`created_at`),
  KEY `idx_mp_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `pricing_plans`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pricing_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text,
  `price` decimal(10,2) DEFAULT '0.00',
  `billing_cycle` varchar(30) DEFAULT 'monthly',
  `ad_credits_included` int DEFAULT '0',
  `ai_credits_included` int DEFAULT '0',
  `features_json` json DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `scheduled_posts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `scheduled_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `platform` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `media_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `scheduled_time` datetime DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'scheduled',
  `bundle_post_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `publish_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for `server_tracking_events`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `server_tracking_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pixel_id` int NOT NULL,
  `user_id` int NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `event_id` varchar(255) DEFAULT NULL,
  `event_source_url` varchar(500) DEFAULT NULL,
  `user_data_hashed` json DEFAULT NULL,
  `custom_data` json DEFAULT NULL,
  `meta_capi_status` varchar(50) DEFAULT 'sent',
  `tiktok_events_status` varchar(50) DEFAULT 'sent',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracking_events_pixel` (`pixel_id`),
  KEY `idx_tracking_events_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `server_tracking_pixels`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `server_tracking_pixels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `pixel_token` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `meta_pixel_id` varchar(100) DEFAULT NULL,
  `meta_test_event_code` varchar(100) DEFAULT NULL,
  `tiktok_pixel_id` varchar(100) DEFAULT NULL,
  `tiktok_test_event_code` varchar(100) DEFAULT NULL,
  `domain_verified` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pixel_token` (`pixel_token`),
  KEY `idx_tracking_pixels_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `site_reviews`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `rating` tinyint NOT NULL DEFAULT '5',
  `review_title` varchar(255) DEFAULT NULL,
  `review_text` text,
  `plan_type` enum('free','pro') DEFAULT 'free',
  `user_role` varchar(120) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_featured` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_status` (`status`),
  KEY `idx_reviews_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `site_settings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data for `site_settings` (INSERT IGNORE — existing rows win)
INSERT IGNORE INTO `site_settings` (`id`, `setting_key`, `setting_value`, `created_at`) VALUES
(1, 'bundle_social_api_key', 'YOUR_BUNDLE_SOCIAL_API_KEY', '2026-10-31 07:36:48'),
(2, 'bundle_social_base_url', 'https://api.bundle.social/v1', '2026-10-31 07:36:48'),
(3, 'zernio_api_key', 'YOUR_ZERNIO_API_KEY', '2026-10-31 07:36:48'),
(4, 'zernio_base_url', 'https://api.zernio.com/v1', '2026-10-31 07:36:48');

-- -----------------------------------------------------
-- Table structure for `social_inbox_messages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `social_inbox_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `platform` enum('instagram','facebook','tiktok','whatsapp','p2p') NOT NULL,
  `sender_id` varchar(255) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `sender_avatar` varchar(500) DEFAULT NULL,
  `message_type` enum('comment','dm','whatsapp_chat') NOT NULL,
  `post_context_id` varchar(255) DEFAULT NULL,
  `message_text` text NOT NULL,
  `ai_auto_replied` tinyint(1) DEFAULT '0',
  `auto_reply_text` text,
  `status` enum('unread','read','replied','closed') DEFAULT 'unread',
  `sentiment` enum('positive','neutral','negative','high_intent_buy') DEFAULT 'neutral',
  `received_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `replied_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inbox_user` (`user_id`),
  KEY `idx_inbox_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `social_inbox_rules`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `social_inbox_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `trigger_keywords` varchar(500) DEFAULT 'price, how to buy, link, cost, where to get, info',
  `platforms` varchar(255) DEFAULT 'instagram,facebook,tiktok,whatsapp',
  `auto_dm_enabled` tinyint(1) DEFAULT '1',
  `promo_discount_code` varchar(100) DEFAULT 'FLASH15',
  `product_link` varchar(500) DEFAULT NULL,
  `reply_template` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inbox_rules_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `third_party_gateways`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `third_party_gateways` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `provider_key` enum('bundle_social','zernio') NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `base_url` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_key` (`provider_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `transactions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `amount` decimal(10,2) DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'USD',
  `metadata` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `user_subscriptions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usubs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ad_credits` decimal(12,2) DEFAULT '0.00',
  `ai_credits` int DEFAULT '0',
  `ai_preferred_provider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `plan_id` int DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `is_admin` tinyint(1) DEFAULT '0',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `twitter` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkedin` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'individual',
  `google_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referral_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referred_by` int DEFAULT NULL,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `totp_enabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data for `users` (INSERT IGNORE — existing rows win)
INSERT IGNORE INTO `users` (`id`, `full_name`, `email`, `ad_credits`, `ai_credits`, `ai_preferred_provider`, `avatar`, `company`, `user_role`, `plan_id`, `status`, `is_admin`, `password`, `role`, `phone`, `facebook`, `twitter`, `linkedin`, `account_type`, `google_id`, `referral_code`, `referred_by`, `onboarding_completed`, `two_factor_secret`, `two_factor_enabled`, `totp_enabled`) VALUES
(1, 'Smoke Tester', 'smoke@adsbooster.net', '500.00', 500, 'nvidia', NULL, NULL, 'user', NULL, 'active', 0, '$2y$10$S4.kmWp6AnyMgi6d.unA4ewBqlNvVXAKPvM01Jx7/H92YYc1XWSke', 'user', NULL, NULL, NULL, NULL, 'individual', NULL, NULL, NULL, 0, NULL, 0, 0),
(2, 'Adsbooster Administrator', 'admin@adsbooster.net', '0.00', 100, NULL, NULL, NULL, 'admin', NULL, 'active', 1, '$2y$10$/q92Hapy9UrSnuq1qkCj2eFA7XAM3M91OEng4t/XdhCvSoJP4c3E.', 'admin', NULL, NULL, NULL, NULL, 'individual', NULL, NULL, NULL, 0, NULL, 0, 0);


-- =====================================================================
-- SCHEMA HARMONIZATION (guarded & idempotent)
-- Each ALTER below runs ONLY if the column is missing on this database.
-- MySQL has no `ADD COLUMN IF NOT EXISTS`; the INFORMATION_SCHEMA +
-- PREPARE pattern makes every statement re-runnable safely.
-- =====================================================================
-- `users`
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_type');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `account_type` VARCHAR(30) NULL DEFAULT ''individual''',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `role` VARCHAR(30) NOT NULL DEFAULT ''user''',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'user_role');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `user_role` VARCHAR(30) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_admin');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''active''',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `password` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_id');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(64) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'company');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `company` VARCHAR(150) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(40) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'facebook');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `facebook` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'twitter');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `twitter` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'linkedin');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `linkedin` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'plan_id');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `plan_id` INT NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'ai_preferred_provider');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `ai_preferred_provider` VARCHAR(20) NULL DEFAULT NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'referral_code');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `referral_code` VARCHAR(32) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'referred_by');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `referred_by` INT NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'onboarding_completed');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `onboarding_completed` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'two_factor_secret');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `two_factor_secret` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'two_factor_enabled');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'totp_enabled');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `users` ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;

-- `ad_campaigns`
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_campaigns' AND COLUMN_NAME = 'zernio_campaign_id');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `ad_campaigns` ADD COLUMN `zernio_campaign_id` VARCHAR(191) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_campaigns' AND COLUMN_NAME = 'zernio_ad_account_id');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `ad_campaigns` ADD COLUMN `zernio_ad_account_id` VARCHAR(191) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_campaigns' AND COLUMN_NAME = 'creative_media_url');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `ad_campaigns` ADD COLUMN `creative_media_url` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_campaigns' AND COLUMN_NAME = 'headline');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `ad_campaigns` ADD COLUMN `headline` VARCHAR(190) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_campaigns' AND COLUMN_NAME = 'primary_text');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `ad_campaigns` ADD COLUMN `primary_text` TEXT NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_campaigns' AND COLUMN_NAME = 'destination_url');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `ad_campaigns` ADD COLUMN `destination_url` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;

-- `scheduled_posts`
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheduled_posts' AND COLUMN_NAME = 'bundle_post_id');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `scheduled_posts` ADD COLUMN `bundle_post_id` VARCHAR(191) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheduled_posts' AND COLUMN_NAME = 'publish_error');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `scheduled_posts` ADD COLUMN `publish_error` TEXT NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheduled_posts' AND COLUMN_NAME = 'media_url');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `scheduled_posts` ADD COLUMN `media_url` VARCHAR(255) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;

-- `async_job_queue`
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'async_job_queue' AND COLUMN_NAME = 'subject_key');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `async_job_queue` ADD COLUMN `subject_key` VARCHAR(64) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'async_job_queue' AND COLUMN_NAME = 'last_attempt_at');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `async_job_queue` ADD COLUMN `last_attempt_at` DATETIME NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'async_job_queue' AND COLUMN_NAME = 'max_attempts');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `async_job_queue` ADD COLUMN `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;

-- `marketplace_items`
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketplace_items' AND COLUMN_NAME = 'views_count');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `marketplace_items` ADD COLUMN `views_count` INT UNSIGNED NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketplace_items' AND COLUMN_NAME = 'rating');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `marketplace_items` ADD COLUMN `rating` DECIMAL(3,2) NULL DEFAULT NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;

-- `ai_video_creatives`
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_video_creatives' AND COLUMN_NAME = 'render_mode');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `ai_video_creatives` ADD COLUMN `render_mode` VARCHAR(40) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;
SET @db_has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_video_creatives' AND COLUMN_NAME = 'provider');
SET @db_fix_stmt := IF(@db_has_col = 0,
    'ALTER TABLE `ai_video_creatives` ADD COLUMN `provider` VARCHAR(40) NULL',
    'SELECT 1');
PREPARE db_fix FROM @db_fix_stmt;
EXECUTE db_fix;
DEALLOCATE PREPARE db_fix;

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
