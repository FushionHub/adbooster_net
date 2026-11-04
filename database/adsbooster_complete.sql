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

-- -----------------------------------------------------
-- Table structure for `account_deletions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_deletions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `reason` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_deletions_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `achievements`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `achievements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE latin1_swedish_ci NOT NULL,
  `slug` varchar(120) COLLATE latin1_swedish_ci NOT NULL,
  `description` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `icon` varchar(64) COLLATE latin1_swedish_ci DEFAULT NULL,
  `points` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_achievements_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `ad_campaign_variants`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ad_campaign_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int NOT NULL,
  `variant_name` varchar(120) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'Variant 1',
  `description` text COLLATE latin1_swedish_ci,
  `media_url` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `budget_share` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT NULL,
  `variant_label` varchar(120) COLLATE latin1_swedish_ci DEFAULT NULL,
  `headline` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `traffic_split_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `is_winner` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_ad_campaign_variants_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `ad_campaigns`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ad_campaigns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `campaign_name` varchar(190) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `campaign_type` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `objective` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `budget_type` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'daily',
  `budget_amount` decimal(12,2) DEFAULT '0.00',
  `targeting_spec` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  `headline` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `primary_text` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  `destination_url` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `creative_media_url` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'pending',
  `zernio_campaign_id` varchar(191) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `zernio_ad_account_id` varchar(191) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `autopilot_enabled` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `winning_variant_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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

-- Seed data for `agency_portals` (INSERT IGNORE — existing rows win)
INSERT IGNORE INTO `agency_portals` (`id`, `agency_user_id`, `custom_subdomain`, `custom_domain`, `portal_name`, `portal_logo_url`, `brand_primary_color`, `brand_secondary_color`, `welcome_headline`, `is_whitelabel_active`, `created_at`) VALUES
(1, 4, 'agency-4', NULL, 'E2E Check Marketing Agency', 'assets/images/logo/logo.png', '#c8ff00', '#080c14', 'Welcome to Your Client Growth & ROI Dashboard', 1, '2026-11-02 11:29:29');

-- -----------------------------------------------------
-- Table structure for `ai_creatives`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_creatives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(30) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'image',
  `prompt` text COLLATE latin1_swedish_ci,
  `result_output` mediumtext COLLATE latin1_swedish_ci,
  `credits_used` int NOT NULL DEFAULT '0',
  `metadata` text COLLATE latin1_swedish_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_creatives_user_type` (`user_id`,`type`),
  KEY `idx_ai_creatives_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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

-- Seed data for `ai_media_buyer_rules` (INSERT IGNORE — existing rows win)
INSERT IGNORE INTO `ai_media_buyer_rules` (`id`, `user_id`, `campaign_id`, `rule_name`, `condition_metric`, `condition_operator`, `condition_value`, `action_type`, `action_value`, `is_active`, `last_triggered_at`, `created_at`) VALUES
(1, 4, NULL, '🚨 Anti-Burn: Pause if CPA > $30 after spend', 'cpa', '>', '30.00', 'pause_campaign', '0.00', 1, NULL, '2026-11-02 19:40:59'),
(2, 4, NULL, '⚡ Auto-Scale: Boost Winner Budget +20% if ROAS > 3.0', 'roas', '>=', '3.00', 'scale_budget_pct', '20.00', 1, NULL, '2026-11-02 19:40:59'),
(3, 4, NULL, '📉 Stop Fatigue: Reduce Budget -25% if CTR < 0.8%', 'ctr', '<', '0.80', 'reduce_budget_pct', '25.00', 1, NULL, '2026-11-02 19:40:59'),
(4, 4, NULL, '🔄 Creative Refresh: Trigger AI Variant if Spend > $50 with 0 Conv', 'spend_without_conv', '>=', '50.00', 'trigger_creative_refresh', '0.00', 1, NULL, '2026-11-02 19:40:59');

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
  `status` enum('queued','generating','rendering_client','completed','failed') NOT NULL DEFAULT 'queued',
  `credits_used` int DEFAULT '3',
  `error_message` text,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `render_mode` varchar(40) DEFAULT NULL,
  `provider` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_video_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------
-- Table structure for `api_keys`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `key_hash` char(64) COLLATE latin1_swedish_ci NOT NULL,
  `key_prefix` varchar(20) COLLATE latin1_swedish_ci DEFAULT NULL,
  `scopes` text COLLATE latin1_swedish_ci,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'active',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_api_keys_hash` (`key_hash`),
  KEY `idx_api_keys_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `api_rate_limits`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key_id` int unsigned NOT NULL DEFAULT '0',
  `ip_address` varchar(45) COLLATE latin1_swedish_ci NOT NULL DEFAULT '',
  `window_minute` int unsigned NOT NULL DEFAULT '0',
  `request_count` int unsigned NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_window` (`key_id`,`ip_address`,`window_minute`),
  KEY `idx_window` (`window_minute`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `audience_segments`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `audience_segments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(191) COLLATE latin1_swedish_ci NOT NULL,
  `criteria` text COLLATE latin1_swedish_ci,
  `criteria_json` text COLLATE latin1_swedish_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audience_segments_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `campaign_recommendations`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int NOT NULL,
  `recommendation_type` varchar(60) COLLATE latin1_swedish_ci NOT NULL DEFAULT '',
  `message` text COLLATE latin1_swedish_ci,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_recommendations_campaign` (`campaign_id`),
  KEY `idx_campaign_recommendations_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `campaign_run_logs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_run_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int DEFAULT NULL,
  `action` varchar(80) COLLATE latin1_swedish_ci NOT NULL DEFAULT '',
  `details` text COLLATE latin1_swedish_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_run_logs_campaign` (`campaign_id`),
  KEY `idx_campaign_run_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `combo_purchases`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `combo_purchases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_combo_purchases_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Table structure for `cookie_consents`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cookie_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consent_uuid` char(32) COLLATE latin1_swedish_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `necessary` tinyint(1) NOT NULL DEFAULT '1',
  `preferences` tinyint(1) NOT NULL DEFAULT '0',
  `analytics` tinyint(1) NOT NULL DEFAULT '0',
  `marketing` tinyint(1) NOT NULL DEFAULT '0',
  `ip_address` varchar(45) COLLATE latin1_swedish_ci DEFAULT NULL,
  `current_page` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cookie_consents_uuid` (`consent_uuid`),
  KEY `idx_cookie_consents_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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

-- Seed data for `creator_bounties` (INSERT IGNORE — existing rows win)
INSERT IGNORE INTO `creator_bounties` (`id`, `brand_user_id`, `title`, `description`, `platform`, `content_type`, `deliverables_req`, `bounty_amount`, `platform_fee_pct`, `total_escrow_held`, `escrow_status`, `claimed_creator_id`, `status`, `submission_deadline`, `created_at`) VALUES
(1, 3, '15s UGC Talking Head Review for Hydrating Skincare Brand', 'Looking for a female creator (20-35) to test our glow serum and record an authentic selfie-style talking head review.', 'tiktok', 'ugc_talking_head', '1x 9:16 vertical video, 1080p, clear audio, with hook & CTA.', '120.00', '10.00', '120.00', 'funded', NULL, 'open', '2026-11-08 18:45:52', '2026-11-01 19:45:52'),
(2, 3, 'SaaS Productivity App Unboxing & Workflow Demo', 'Tech creator needed to record a screen-share and face-cam walkthrough of our AI automation tool.', 'instagram_reels', 'tutorial', '1x Reel (30s) highlighting 3 core features.', '200.00', '10.00', '200.00', 'funded', NULL, 'open', '2026-11-08 18:45:52', '2026-11-01 19:45:52'),
(3, 3, 'Athletic Compression Gym Wear Try-On Haul', 'Fitness influencer needed to showcase workout durability and squat-proof fit.', 'youtube_shorts', 'lifestyle_testimonial', '1x Short with trending audio beat.', '150.00', '10.00', '150.00', 'funded', NULL, 'open', '2026-11-08 18:45:52', '2026-11-01 19:45:52');

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
  `job_name` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `status` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `notes` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `dating_blocks`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dating_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blocker_id` int NOT NULL,
  `blocked_id` int NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dating_blocks_pair` (`blocker_id`,`blocked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `dating_pairs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dating_pairs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `icebreaker_message` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'pending',
  `matched_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dating_pairs_sender` (`sender_id`),
  KEY `idx_dating_pairs_receiver` (`receiver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `dating_profiles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dating_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `age` int DEFAULT NULL,
  `gender` varchar(30) COLLATE latin1_swedish_ci DEFAULT NULL,
  `looking_for` varchar(30) COLLATE latin1_swedish_ci DEFAULT NULL,
  `city` varchar(120) COLLATE latin1_swedish_ci DEFAULT NULL,
  `occupation` varchar(120) COLLATE latin1_swedish_ci DEFAULT NULL,
  `interests` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `bio` text COLLATE latin1_swedish_ci,
  `relationship_goals` varchar(120) COLLATE latin1_swedish_ci DEFAULT NULL,
  `photo` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `photo_url` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dating_profiles_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `dating_reports`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dating_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reporter_id` int NOT NULL,
  `reported_id` int NOT NULL,
  `reason` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dating_reports_reported` (`reported_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `dynamic_content`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dynamic_content` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page_slug` varchar(120) COLLATE latin1_swedish_ci NOT NULL,
  `section_key` varchar(120) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'general',
  `item_key` varchar(120) COLLATE latin1_swedish_ci NOT NULL,
  `content_value` mediumtext COLLATE latin1_swedish_ci,
  `media_url` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dynamic_content_page` (`page_slug`,`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Table structure for `email_logs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` int DEFAULT NULL,
  `recipient` varchar(191) COLLATE latin1_swedish_ci NOT NULL,
  `subject` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `body` mediumtext COLLATE latin1_swedish_ci,
  `type` varchar(60) COLLATE latin1_swedish_ci DEFAULT NULL,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'sent',
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_recipient` (`recipient`),
  KEY `idx_email_logs_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `fraud_flags`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `fraud_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `flag_type` varchar(60) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'generic',
  `description` text COLLATE latin1_swedish_ci,
  `severity` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'low',
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'flagged',
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fraud_flags_user` (`user_id`),
  KEY `idx_fraud_flags_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `invoices`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `invoice_number` varchar(40) COLLATE latin1_swedish_ci NOT NULL,
  `file_path` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoices_transaction` (`transaction_id`),
  KEY `idx_invoices_number` (`invoice_number`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_0900_ai_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `marketplace_listings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `marketplace_listings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(191) COLLATE latin1_swedish_ci NOT NULL,
  `slug` varchar(220) COLLATE latin1_swedish_ci NOT NULL,
  `category` varchar(80) COLLATE latin1_swedish_ci DEFAULT NULL,
  `address` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE latin1_swedish_ci DEFAULT NULL,
  `whatsapp` varchar(40) COLLATE latin1_swedish_ci DEFAULT NULL,
  `description` text COLLATE latin1_swedish_ci,
  `website_url` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'active',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `rating` decimal(3,1) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketplace_listings_user` (`user_id`),
  KEY `idx_marketplace_listings_slug` (`slug`(191)),
  KEY `idx_marketplace_listings_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `notifications`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'system',
  `title` varchar(190) COLLATE latin1_swedish_ci NOT NULL,
  `body` text COLLATE latin1_swedish_ci,
  `link` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`,`is_read`),
  KEY `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `p2p_conversations`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `p2p_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_a` int NOT NULL,
  `user_b` int NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_conversations_pair` (`user_a`,`user_b`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `p2p_deals`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `p2p_deals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `proposer_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `title` varchar(191) COLLATE latin1_swedish_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(10) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'USD',
  `terms` text COLLATE latin1_swedish_ci,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'proposed',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_deals_conversation` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `p2p_messages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `p2p_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `message` mediumtext COLLATE latin1_swedish_ci,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_messages_conversation` (`conversation_id`),
  KEY `idx_p2p_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `payout_requests`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `payout_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `method` varchar(40) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'paypal',
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'pending',
  `details_json` text COLLATE latin1_swedish_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payout_requests_user` (`user_id`),
  KEY `idx_payout_requests_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `policy_rules`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `policy_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `platform` varchar(40) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'all',
  `category` varchar(60) COLLATE latin1_swedish_ci DEFAULT NULL,
  `name` varchar(120) COLLATE latin1_swedish_ci DEFAULT NULL,
  `rule_type` varchar(30) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'text',
  `pattern` text COLLATE latin1_swedish_ci,
  `description` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `severity` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'low',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_policy_rules_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `policy_scan_logs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `policy_scan_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `campaign_id` int DEFAULT NULL,
  `post_id` int DEFAULT NULL,
  `score` int NOT NULL DEFAULT '0',
  `issues` mediumtext COLLATE latin1_swedish_ci,
  `scanned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_policy_scan_logs_user` (`user_id`),
  KEY `idx_policy_scan_logs_scanned` (`scanned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `referral_rewards`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `referral_rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id` int NOT NULL,
  `referred_user_id` int NOT NULL,
  `referrer_reward` decimal(10,2) NOT NULL DEFAULT '0.00',
  `referred_reward` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_referral_rewards_referrer` (`referrer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `referrals`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id` int NOT NULL,
  `referred_id` int DEFAULT NULL,
  `referred_user_id` int DEFAULT NULL,
  `code` varchar(40) COLLATE latin1_swedish_ci DEFAULT NULL,
  `reward_credits` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  `credited_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_referrals_referrer` (`referrer_id`),
  KEY `idx_referrals_referred` (`referred_id`),
  KEY `idx_referrals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `scheduled_posts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `scheduled_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `platform` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `post_type` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `caption` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  `media_url` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `media_type` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'text',
  `scheduled_time` datetime DEFAULT NULL,
  `status` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'scheduled',
  `bundle_post_id` varchar(191) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `publish_error` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `seller_verifications`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `seller_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_seller_verifications_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Table structure for `service_check_log`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_check_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `check_key` varchar(80) COLLATE latin1_swedish_ci NOT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT '0',
  `latency_ms` int NOT NULL DEFAULT '0',
  `detail` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service_check_log_key` (`check_key`),
  KEY `idx_service_check_log_checked` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `service_incidents`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_incidents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `check_key` varchar(64) COLLATE latin1_swedish_ci NOT NULL,
  `check_label` varchar(120) COLLATE latin1_swedish_ci DEFAULT NULL,
  `severity` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'major',
  `title` varchar(190) COLLATE latin1_swedish_ci NOT NULL,
  `cause` text COLLATE latin1_swedish_ci,
  `detail` text COLLATE latin1_swedish_ci,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'open',
  `started_at` datetime NOT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_incident_check` (`check_key`),
  KEY `idx_incident_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `site_settings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(190) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `setting_value` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Table structure for `support_tickets`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `ticket_number` varchar(24) COLLATE latin1_swedish_ci DEFAULT NULL,
  `ticket_type` varchar(40) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'general',
  `priority` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'medium',
  `subject` varchar(191) COLLATE latin1_swedish_ci NOT NULL,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'open',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_support_tickets_user` (`user_id`),
  KEY `idx_support_tickets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `team_invitations`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `team_invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `email` varchar(191) COLLATE latin1_swedish_ci DEFAULT NULL,
  `invite_token` varchar(64) COLLATE latin1_swedish_ci NOT NULL,
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'pending',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_team_invitations_token` (`invite_token`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `team_members`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `team_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_user_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `email` varchar(191) COLLATE latin1_swedish_ci NOT NULL,
  `role` varchar(40) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'member',
  `status` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'pending',
  `invite_token` varchar(64) COLLATE latin1_swedish_ci DEFAULT NULL,
  `invited_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_team_members_parent` (`parent_user_id`),
  KEY `idx_team_members_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
-- Table structure for `ticket_messages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int unsigned NOT NULL,
  `sender_id` int NOT NULL,
  `sender_role` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'customer',
  `message` text COLLATE latin1_swedish_ci,
  `attachment` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_messages_ticket` (`ticket_id`),
  KEY `idx_ticket_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `tickets`
-- -----------------------------------------------------
;

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
  `gateway` varchar(40) DEFAULT NULL,
  `reference` varchar(64) DEFAULT NULL,
  `gateway_reference` varchar(100) DEFAULT NULL,
  `invoice_number` varchar(40) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tx_user` (`user_id`),
  KEY `idx_tx_gateway_reference` (`gateway_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `translations`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `locale` varchar(10) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'en',
  `translation_key` varchar(190) COLLATE latin1_swedish_ci NOT NULL,
  `value` text COLLATE latin1_swedish_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_translation_locale_key` (`locale`,`translation_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `user_achievements`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_achievements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `achievement_id` int unsigned NOT NULL,
  `earned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_achievements` (`user_id`,`achievement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `user_portfolios`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_portfolios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `username` varchar(100) COLLATE latin1_swedish_ci DEFAULT NULL,
  `display_name` varchar(191) COLLATE latin1_swedish_ci DEFAULT NULL,
  `job_title` varchar(120) COLLATE latin1_swedish_ci DEFAULT NULL,
  `headline` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `bio` text COLLATE latin1_swedish_ci,
  `template_style` varchar(40) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'dark_luxury',
  `phone` varchar(40) COLLATE latin1_swedish_ci DEFAULT NULL,
  `email` varchar(191) COLLATE latin1_swedish_ci DEFAULT NULL,
  `company` varchar(191) COLLATE latin1_swedish_ci DEFAULT NULL,
  `location` varchar(191) COLLATE latin1_swedish_ci DEFAULT NULL,
  `website` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `skills_json` mediumtext COLLATE latin1_swedish_ci,
  `services_json` mediumtext COLLATE latin1_swedish_ci,
  `portfolio_items_json` mediumtext COLLATE latin1_swedish_ci,
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `views_count` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_user_portfolios_user` (`user_id`),
  KEY `idx_user_portfolios_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_0900_ai_ci;

-- -----------------------------------------------------
-- Table structure for `user_vcards`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_vcards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `username` varchar(100) COLLATE latin1_swedish_ci DEFAULT NULL,
  `headline` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `bio` text COLLATE latin1_swedish_ci,
  `template` varchar(40) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'dark_luxury',
  `phone` varchar(40) COLLATE latin1_swedish_ci DEFAULT NULL,
  `whatsapp` varchar(40) COLLATE latin1_swedish_ci DEFAULT NULL,
  `company` varchar(191) COLLATE latin1_swedish_ci DEFAULT NULL,
  `job_title` varchar(120) COLLATE latin1_swedish_ci DEFAULT NULL,
  `location` varchar(191) COLLATE latin1_swedish_ci DEFAULT NULL,
  `website` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `skills_json` mediumtext COLLATE latin1_swedish_ci,
  `projects_json` mediumtext COLLATE latin1_swedish_ci,
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `views_count` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_user_vcards_user` (`user_id`),
  KEY `idx_user_vcards_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `email` varchar(190) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `ad_credits` decimal(12,2) DEFAULT '0.00',
  `ai_credits` int DEFAULT '0',
  `ai_preferred_provider` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `avatar` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `company` varchar(150) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `user_role` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'user',
  `plan_id` int DEFAULT NULL,
  `status` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'active',
  `is_admin` tinyint(1) DEFAULT '0',
  `password` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `role` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'user',
  `phone` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `facebook` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `twitter` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `linkedin` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `account_type` varchar(30) COLLATE latin1_swedish_ci DEFAULT 'individual',
  `google_id` varchar(64) COLLATE latin1_swedish_ci DEFAULT NULL,
  `referral_code` varchar(32) COLLATE latin1_swedish_ci DEFAULT NULL,
  `referred_by` int DEFAULT NULL,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_secret` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `totp_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `achievement_points` int NOT NULL DEFAULT '0',
  `billing_cycle` varchar(20) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'monthly',
  `plan_expiry` datetime DEFAULT NULL,
  `auto_refill_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `auto_refill_threshold` decimal(10,2) NOT NULL DEFAULT '10.00',
  `auto_refill_amount` decimal(12,2) NOT NULL DEFAULT '50.00',
  `auto_refill_gateway` varchar(40) COLLATE latin1_swedish_ci DEFAULT NULL,
  `parent_user_id` int DEFAULT NULL,
  `username` varchar(100) COLLATE latin1_swedish_ci DEFAULT NULL,
  `headline` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `bio` text COLLATE latin1_swedish_ci,
  `two_factor_backup` text COLLATE latin1_swedish_ci,
  `fa_attempts` int NOT NULL DEFAULT '0',
  `is_verified_seller` tinyint(1) NOT NULL DEFAULT '0',
  `totp_secret` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
  `two_factor_type` varchar(20) COLLATE latin1_swedish_ci DEFAULT NULL,
  `two_factor_setup_at` datetime DEFAULT NULL,
  `remember_token` varchar(64) COLLATE latin1_swedish_ci DEFAULT NULL,
  `remember_token_expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Seed data for `users` (INSERT IGNORE — existing rows win)
INSERT IGNORE INTO `users` (`id`, `full_name`, `email`, `ad_credits`, `ai_credits`, `ai_preferred_provider`, `avatar`, `company`, `user_role`, `plan_id`, `status`, `is_admin`, `password`, `role`, `phone`, `facebook`, `twitter`, `linkedin`, `account_type`, `google_id`, `referral_code`, `referred_by`, `onboarding_completed`, `two_factor_secret`, `two_factor_enabled`, `totp_enabled`, `created_at`, `achievement_points`, `billing_cycle`, `plan_expiry`, `auto_refill_enabled`, `auto_refill_threshold`, `auto_refill_amount`, `auto_refill_gateway`, `parent_user_id`, `username`, `headline`, `bio`, `two_factor_backup`, `fa_attempts`, `is_verified_seller`, `totp_secret`, `two_factor_type`, `two_factor_setup_at`, `remember_token`, `remember_token_expires_at`) VALUES
(1, 'Smoke Tester', 'smoke@adsbooster.net', '500.00', 500, 'nvidia', NULL, NULL, 'user', NULL, 'active', 0, '$2y$10$S4.kmWp6AnyMgi6d.unA4ewBqlNvVXAKPvM01Jx7/H92YYc1XWSke', 'user', NULL, NULL, NULL, NULL, 'individual', NULL, NULL, NULL, 0, NULL, 0, 0, '2020-06-23 00:00:00', 0, 'monthly', NULL, 0, '10.00', '50.00', NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(2, 'Adsbooster Administrator', 'admin@adsbooster.net', '101000000.00', 10000100, NULL, NULL, NULL, 'admin', NULL, 'active', 1, '$2y$10$/q92Hapy9UrSnuq1qkCj2eFA7XAM3M91OEng4t/XdhCvSoJP4c3E.', 'admin', NULL, NULL, NULL, NULL, 'individual', NULL, NULL, NULL, 0, NULL, 0, 0, '2019-11-29 00:00:00', 0, 'monthly', NULL, 0, '10.00', '50.00', NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, NULL, NULL, NULL, NULL, NULL),
(3, 'Jamachi Maurice Mauricennadi', 'jotahecomng@gmail.com', '109999999.00', 1111911206, 'nvidia', NULL, 'MOREX WEB ENT.', 'user', NULL, 'active', 0, '$2y$10$1OGZYLBBprChu8fF2CONle1ZMgi77x5c6FLC1DfumCdE7eA7pQBNG', 'user', '', NULL, NULL, NULL, 'individual', NULL, '488651a1e3b454ea', NULL, 1, NULL, 0, 0, '2019-02-10 00:00:00', 0, 'monthly', NULL, 0, '10.00', '50.00', NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, NULL, NULL, NULL, NULL, NULL),
(4, 'E2E Check', 'e2e-check@adsbooster.net', '100.00', 100, NULL, NULL, NULL, 'user', NULL, 'active', 0, '$2y$10$L88/48fz57ARb.cgoMilz..ppvrPk9c8mZrMzof1WaOQ.S9G52SPK', 'user', NULL, NULL, NULL, NULL, 'individual', NULL, 'B8B3A514', NULL, 0, NULL, 0, 0, '2023-09-02 00:00:00', 0, 'monthly', NULL, 0, '10.00', '50.00', NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(99999, 'Adsbooster AI Consultant', 'ai@adsbooster.net', '0.00', 0, NULL, NULL, NULL, 'user', NULL, 'active', 0, 'ai_bot_protected', 'admin', NULL, NULL, NULL, NULL, 'individual', NULL, NULL, NULL, 0, NULL, 0, 0, '2026-11-01 21:47:10', 0, 'monthly', NULL, 0, '10.00', '50.00', NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL);

-- -----------------------------------------------------
-- Table structure for `visitor_logs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `visitor_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `ip` varchar(45) COLLATE latin1_swedish_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `browser` varchar(60) COLLATE latin1_swedish_ci DEFAULT NULL,
  `os` varchar(60) COLLATE latin1_swedish_ci DEFAULT NULL,
  `device_type` varchar(20) COLLATE latin1_swedish_ci DEFAULT NULL,
  `request_uri` varchar(500) COLLATE latin1_swedish_ci DEFAULT NULL,
  `country` varchar(60) COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visitor_logs_uri` (`request_uri`(191)),
  KEY `idx_visitor_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- -----------------------------------------------------
-- Table structure for `webhooks`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `target_url` varchar(500) COLLATE latin1_swedish_ci NOT NULL,
  `event_type` varchar(80) COLLATE latin1_swedish_ci NOT NULL DEFAULT 'campaign.status_changed',
  `secret` varchar(64) COLLATE latin1_swedish_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhooks_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


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
