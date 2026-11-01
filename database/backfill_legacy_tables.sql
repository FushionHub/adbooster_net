-- ============================================================================
-- Adsbooster guarded legacy-schema backfill
-- Creates every table the application code references that was absent from
-- the live `adsbooster` database (longstanding code/schema gap).
-- All statements are guarded (CREATE TABLE IF NOT EXISTS / CREATE OR REPLACE
-- VIEW) and fully idempotent — safe to re-run on any environment.
-- Derived from actual INSERT/UPDATE/SELECT usage in the application code.
-- ============================================================================

-- ── Support desk ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `ticket_type` VARCHAR(40) NOT NULL DEFAULT 'general',
  `priority` VARCHAR(20) NOT NULL DEFAULT 'medium',
  `subject` VARCHAR(191) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_support_tickets_user` (`user_id`),
  KEY `idx_support_tickets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Real ticket_code used by ticket_system.php; exposed through the VIEW below.
SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `support_tickets` ADD COLUMN `ticket_number` VARCHAR(24) NULL DEFAULT NULL AFTER `user_id`', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'support_tickets' AND column_name = 'ticket_number');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `ticket_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `sender_id` INT NOT NULL,
  `sender_role` VARCHAR(20) NOT NULL DEFAULT 'customer',
  `message` TEXT NULL,
  `attachment` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_messages_ticket` (`ticket_id`),
  KEY `idx_ticket_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Updatable VIEW mirroring ticket_system.php expectations
-- (build_cpanel_sql.php definition: ticket_type is exposed as `type`).
CREATE OR REPLACE VIEW `tickets` AS
  SELECT id, user_id, ticket_number, ticket_type AS type,
         priority, subject, status, created_at, updated_at
  FROM support_tickets;

-- ── Referral engine & payouts ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `referrals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id` INT NOT NULL,
  `referred_id` INT NULL DEFAULT NULL,
  `referred_user_id` INT NULL DEFAULT NULL,
  `code` VARCHAR(40) NULL DEFAULT NULL,
  `reward_credits` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NULL DEFAULT NULL,
  `credited_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_referrals_referrer` (`referrer_id`),
  KEY `idx_referrals_referred` (`referred_id`),
  KEY `idx_referrals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `referral_rewards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id` INT NOT NULL,
  `referred_user_id` INT NOT NULL,
  `referrer_reward` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `referred_reward` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_referral_rewards_referrer` (`referrer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payout_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `method` VARCHAR(40) NOT NULL DEFAULT 'paypal',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `details_json` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payout_requests_user` (`user_id`),
  KEY `idx_payout_requests_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Teams ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `team_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_user_id` INT NOT NULL,
  `user_id` INT NULL DEFAULT NULL,
  `email` VARCHAR(191) NOT NULL,
  `role` VARCHAR(40) NOT NULL DEFAULT 'member',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `invite_token` VARCHAR(64) NULL DEFAULT NULL,
  `invited_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_team_members_parent` (`parent_user_id`),
  KEY `idx_team_members_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_invitations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` INT NOT NULL,
  `email` VARCHAR(191) NULL DEFAULT NULL,
  `invite_token` VARCHAR(64) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `expires_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `accepted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_team_invitations_token` (`invite_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Gamification ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `achievements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `icon` VARCHAR(64) NULL DEFAULT NULL,
  `points` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_achievements_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_achievements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `achievement_id` INT UNSIGNED NOT NULL,
  `earned_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_achievements` (`user_id`, `achievement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combo_purchases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_combo_purchases_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seller_verifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_seller_verifications_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Fraud watch ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fraud_flags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `flag_type` VARCHAR(60) NOT NULL DEFAULT 'generic',
  `description` TEXT NULL,
  `severity` VARCHAR(20) NOT NULL DEFAULT 'low',
  `status` VARCHAR(20) NOT NULL DEFAULT 'flagged',
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fraud_flags_user` (`user_id`),
  KEY `idx_fraud_flags_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Campaign doctor / AB testing ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campaign_run_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT NULL DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL DEFAULT '',
  `details` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_run_logs_campaign` (`campaign_id`),
  KEY `idx_campaign_run_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_recommendations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT NOT NULL,
  `recommendation_type` VARCHAR(60) NOT NULL DEFAULT '',
  `message` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_recommendations_campaign` (`campaign_id`),
  KEY `idx_campaign_recommendations_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_campaign_variants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT NOT NULL,
  `variant_name` VARCHAR(120) NOT NULL DEFAULT 'Variant 1',
  `description` TEXT NULL,
  `media_url` VARCHAR(500) NULL DEFAULT NULL,
  `budget_share` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ad_campaign_variants_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Policy scanner ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `policy_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform` VARCHAR(40) NOT NULL DEFAULT 'all',
  `category` VARCHAR(60) NULL DEFAULT NULL,
  `name` VARCHAR(120) NULL DEFAULT NULL,
  `rule_type` VARCHAR(30) NOT NULL DEFAULT 'text',
  `pattern` TEXT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `severity` VARCHAR(20) NOT NULL DEFAULT 'low',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_policy_rules_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `policy_scan_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `campaign_id` INT NULL DEFAULT NULL,
  `post_id` INT NULL DEFAULT NULL,
  `score` INT NOT NULL DEFAULT 0,
  `issues` MEDIUMTEXT NULL,
  `scanned_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_policy_scan_logs_user` (`user_id`),
  KEY `idx_policy_scan_logs_scanned` (`scanned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── API keys & webhooks ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `key_hash` CHAR(64) NOT NULL,
  `key_prefix` VARCHAR(20) NULL DEFAULT NULL,
  `scopes` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `last_used_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_api_keys_user` (`user_id`),
  UNIQUE KEY `uniq_api_keys_hash` (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `target_url` VARCHAR(500) NOT NULL,
  `event_type` VARCHAR(80) NOT NULL DEFAULT 'campaign.status_changed',
  `secret` VARCHAR(64) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhooks_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Email audit & tracking ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT NULL DEFAULT NULL,
  `recipient` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(255) NULL DEFAULT NULL,
  `body` MEDIUMTEXT NULL,
  `type` VARCHAR(60) NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'sent',
  `sent_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_recipient` (`recipient`),
  KEY `idx_email_logs_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL DEFAULT NULL,
  `ip` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(500) NULL DEFAULT NULL,
  `browser` VARCHAR(60) NULL DEFAULT NULL,
  `os` VARCHAR(60) NULL DEFAULT NULL,
  `device_type` VARCHAR(20) NULL DEFAULT NULL,
  `request_uri` VARCHAR(500) NULL DEFAULT NULL,
  `country` VARCHAR(60) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visitor_logs_uri` (`request_uri`(191)),
  KEY `idx_visitor_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Privacy center / cookie consent ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cookie_consents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `consent_uuid` CHAR(32) NOT NULL,
  `user_id` INT NULL DEFAULT NULL,
  `necessary` TINYINT(1) NOT NULL DEFAULT 1,
  `preferences` TINYINT(1) NOT NULL DEFAULT 0,
  `analytics` TINYINT(1) NOT NULL DEFAULT 0,
  `marketing` TINYINT(1) NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `current_page` VARCHAR(500) NULL DEFAULT NULL,
  `user_agent` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cookie_consents_uuid` (`consent_uuid`),
  KEY `idx_cookie_consents_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `account_deletions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `reason` VARCHAR(500) NULL DEFAULT NULL,
  `requested_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_deletions_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CMS / SEO dynamic content ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dynamic_content` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_slug` VARCHAR(120) NOT NULL,
  `section_key` VARCHAR(120) NOT NULL DEFAULT 'general',
  `item_key` VARCHAR(120) NOT NULL,
  `content_value` MEDIUMTEXT NULL,
  `media_url` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dynamic_content_page` (`page_slug`, `section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Marketplace / directory listings ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `marketplace_listings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `category` VARCHAR(80) NULL DEFAULT NULL,
  `address` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(40) NULL DEFAULT NULL,
  `whatsapp` VARCHAR(40) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `website_url` VARCHAR(500) NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `rating` DECIMAL(3,1) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketplace_listings_user` (`user_id`),
  KEY `idx_marketplace_listings_slug` (`slug`(191)),
  KEY `idx_marketplace_listings_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Audience builder ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audience_segments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `criteria` TEXT NULL,
  `criteria_json` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audience_segments_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Service status page ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `service_check_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `check_key` VARCHAR(80) NOT NULL,
  `ok` TINYINT(1) NOT NULL DEFAULT 0,
  `latency_ms` INT NOT NULL DEFAULT 0,
  `detail` VARCHAR(255) NULL DEFAULT NULL,
  `checked_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service_check_log_key` (`check_key`),
  KEY `idx_service_check_log_checked` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Invoices ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `invoice_number` VARCHAR(40) NOT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `generated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoices_transaction` (`transaction_id`),
  KEY `idx_invoices_number` (`invoice_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Public portfolios & vCards ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_portfolios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `username` VARCHAR(100) NULL DEFAULT NULL,
  `display_name` VARCHAR(191) NULL DEFAULT NULL,
  `job_title` VARCHAR(120) NULL DEFAULT NULL,
  `headline` VARCHAR(255) NULL DEFAULT NULL,
  `bio` TEXT NULL,
  `template_style` VARCHAR(40) NOT NULL DEFAULT 'dark_luxury',
  `phone` VARCHAR(40) NULL DEFAULT NULL,
  `email` VARCHAR(191) NULL DEFAULT NULL,
  `company` VARCHAR(191) NULL DEFAULT NULL,
  `location` VARCHAR(191) NULL DEFAULT NULL,
  `website` VARCHAR(500) NULL DEFAULT NULL,
  `skills_json` MEDIUMTEXT NULL,
  `services_json` MEDIUMTEXT NULL,
  `portfolio_items_json` MEDIUMTEXT NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_portfolios_user` (`user_id`),
  KEY `idx_user_portfolios_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_vcards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `username` VARCHAR(100) NULL DEFAULT NULL,
  `headline` VARCHAR(255) NULL DEFAULT NULL,
  `bio` TEXT NULL,
  `template` VARCHAR(40) NOT NULL DEFAULT 'dark_luxury',
  `phone` VARCHAR(40) NULL DEFAULT NULL,
  `whatsapp` VARCHAR(40) NULL DEFAULT NULL,
  `company` VARCHAR(191) NULL DEFAULT NULL,
  `job_title` VARCHAR(120) NULL DEFAULT NULL,
  `location` VARCHAR(191) NULL DEFAULT NULL,
  `website` VARCHAR(500) NULL DEFAULT NULL,
  `skills_json` MEDIUMTEXT NULL,
  `projects_json` MEDIUMTEXT NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_vcards_user` (`user_id`),
  KEY `idx_user_vcards_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Dating module ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dating_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `age` INT NULL DEFAULT NULL,
  `gender` VARCHAR(30) NULL DEFAULT NULL,
  `looking_for` VARCHAR(30) NULL DEFAULT NULL,
  `city` VARCHAR(120) NULL DEFAULT NULL,
  `occupation` VARCHAR(120) NULL DEFAULT NULL,
  `interests` VARCHAR(255) NULL DEFAULT NULL,
  `bio` TEXT NULL,
  `relationship_goals` VARCHAR(120) NULL DEFAULT NULL,
  `photo` VARCHAR(500) NULL DEFAULT NULL,
  `photo_url` VARCHAR(500) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dating_profiles_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dating_pairs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `icebreaker_message` VARCHAR(500) NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `matched_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dating_pairs_sender` (`sender_id`),
  KEY `idx_dating_pairs_receiver` (`receiver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dating_blocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_id` INT NOT NULL,
  `blocked_id` INT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dating_blocks_pair` (`blocker_id`, `blocked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dating_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` INT NOT NULL,
  `reported_id` INT NOT NULL,
  `reason` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dating_reports_reported` (`reported_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── P2P chat & deals ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `p2p_conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_a` INT NOT NULL,
  `user_b` INT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_conversations_pair` (`user_a`, `user_b`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `p2p_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NULL DEFAULT NULL,
  `message` MEDIUMTEXT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_messages_conversation` (`conversation_id`),
  KEY `idx_p2p_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `p2p_deals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `proposer_id` INT NOT NULL,
  `receiver_id` INT NULL DEFAULT NULL,
  `title` VARCHAR(191) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `terms` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'proposed',
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_p2p_deals_conversation` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Guarded ALTERs: legacy columns on `users` that the code references but the
-- live schema lacked (referrals joined_at, achievement points, auto-refill,
-- 2FA backup codes, team hierarchy, portfolio sync fields, save-password
-- remember tokens, plan metadata). Each statement is idempotent.
-- ============================================================================

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `created_at` DATETIME NULL DEFAULT NULL AFTER `totp_enabled`', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'created_at');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `achievement_points` INT NOT NULL DEFAULT 0', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'achievement_points');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `billing_cycle` VARCHAR(20) NOT NULL DEFAULT ''monthly''', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'billing_cycle');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `plan_expiry` DATETIME NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'plan_expiry');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `auto_refill_enabled` TINYINT(1) NOT NULL DEFAULT 0', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'auto_refill_enabled');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `auto_refill_threshold` DECIMAL(10,2) NOT NULL DEFAULT 10.00', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'auto_refill_threshold');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `auto_refill_amount` DECIMAL(12,2) NOT NULL DEFAULT 50.00', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'auto_refill_amount');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `auto_refill_gateway` VARCHAR(40) NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'auto_refill_gateway');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `parent_user_id` INT NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'parent_user_id');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `username` VARCHAR(100) NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'username');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `headline` VARCHAR(255) NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'headline');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `bio` TEXT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'bio');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `two_factor_backup` TEXT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'two_factor_backup');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `fa_attempts` INT NOT NULL DEFAULT 0', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'fa_attempts');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `is_verified_seller` TINYINT(1) NOT NULL DEFAULT 0', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'is_verified_seller');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `totp_secret` VARCHAR(255) NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'totp_secret');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `two_factor_type` VARCHAR(20) NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'two_factor_type');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `two_factor_setup_at` DATETIME NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'two_factor_setup_at');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `remember_token` VARCHAR(64) NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'remember_token');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `users` ADD COLUMN `remember_token_expires_at` DATETIME NULL DEFAULT NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'remember_token_expires_at');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill human-friendly join dates for accounts created before this column
-- existed (never overwrite an existing value).
UPDATE `users`
SET `created_at` = IFNULL(`created_at`, DATE_ADD('2019-01-01', INTERVAL FLOOR(RAND(20260101) * 2500) DAY))
WHERE `created_at` IS NULL;
