<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/site_setting_and_config.php';

$sqls = [
  // 1. AI Video Creatives
  "CREATE TABLE IF NOT EXISTS `ai_video_creatives` (
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
      PRIMARY KEY (`id`),
      KEY `idx_ai_video_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  // 2. AI Media Buyer Rules & Logs
  "CREATE TABLE IF NOT EXISTS `ai_media_buyer_rules` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  "CREATE TABLE IF NOT EXISTS `ai_media_buyer_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  // 3. Competitor Ad Analyses
  "CREATE TABLE IF NOT EXISTS `competitor_ad_analyses` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  // 4. E-Commerce Stores, Products & Campaign Packs
  "CREATE TABLE IF NOT EXISTS `ecommerce_stores` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  "CREATE TABLE IF NOT EXISTS `ecommerce_products` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  "CREATE TABLE IF NOT EXISTS `ecommerce_campaign_packs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  // 5. Server Tracking Pixels & Events
  // NOTE: per-platform access tokens (meta_capi_access_token /
  // tiktok_events_access_token) were PURGED in the dual-gateway
  // architecture — the Zernio Ads Engine holds all platform credentials.
  // Pixel IDs remain as Zernio routing labels only.
  "CREATE TABLE IF NOT EXISTS `server_tracking_pixels` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  "CREATE TABLE IF NOT EXISTS `server_tracking_events` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  // 6. Social Inbox Messages & Rules
  "CREATE TABLE IF NOT EXISTS `social_inbox_messages` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  "CREATE TABLE IF NOT EXISTS `social_inbox_rules` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  // 7. Agency Portals, Clients & Approvals
  "CREATE TABLE IF NOT EXISTS `agency_portals` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  "CREATE TABLE IF NOT EXISTS `agency_client_accounts` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  "CREATE TABLE IF NOT EXISTS `agency_client_approvals` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  // 8. Creator Bounties & Submissions (Escrow)
  "CREATE TABLE IF NOT EXISTS `creator_bounties` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  "CREATE TABLE IF NOT EXISTS `creator_bounty_submissions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

  // Content Calendar: free-form sticky notes pinned to a day. Kept separate
  // from scheduled_posts so a note never depends on a post existing.
  "CREATE TABLE IF NOT EXISTS `calendar_notes` (
      `id` int NOT NULL AUTO_INCREMENT,
      `user_id` int NOT NULL,
      `note_date` date NOT NULL,
      `note` text,
      `color` varchar(20) DEFAULT 'lime',
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_calendar_notes_user` (`user_id`, `note_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
];

foreach ($sqls as $sql) {
  try {
    db()->query($sql);
  } catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
  }
}

// 9. AI provider preference column (guarded — MySQL 8 lacks ADD COLUMN IF NOT EXISTS)
// AIEngine::getUserProvider()/setUserProvider() persist the user's chosen AI
// engine (groq | gemini | nvidia — NVIDIA NIM replaced OpenRouter) here.
$featureHasCol = static function (string $table, string $column): bool {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
};
foreach (['users' => "ALTER TABLE `users` ADD COLUMN `ai_preferred_provider` VARCHAR(20) NULL DEFAULT NULL"] as $table => $sql) {
    if (!$featureHasCol($table, 'ai_preferred_provider')) {
        try {
            db()->query($sql);
            echo " [x] Added `{$table}`.`ai_preferred_provider`.\n";
        } catch (PDOException $e) {
            echo "Error adding `{$table}`.`ai_preferred_provider`: " . $e->getMessage() . PHP_EOL;
        }
    }
}

echo "Migration completed successfully!\n";
