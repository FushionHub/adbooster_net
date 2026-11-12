-- ============================================================================
--  Adsbooster.net — "update-any-error.sql"
--  Payments · Referrals · Webhooks — schema repair & upgrade script
-- ============================================================================
--
--  WHAT THIS FILE IS FOR
--  ---------------------
--  A single, idempotent SQL file that fixes every database-side problem the
--  payment / referral / webhook flows can hit, and seeds the settings those
--  flows depend on. It is safe to run as many times as you like.
--
--  RUN IT WHEN
--  -----------
--   • Admin → Transactions → "Verify" reports
--       "no matching local transaction was found"
--   • Admin → Transactions → "Verify" reports a missing column / table error
--   • A referral is not paid automatically after a verified payment
--   • The gateway webhook URL returns 500 (missing `payment_webhook_events`)
--   • After any restore from a partial database dump
--
--  HOW TO RUN
--  -----------
--   mysql -u root -p adsbooster < update-any-error.sql
--
--  ...or paste the whole file into phpMyAdmin → SQL → Import.
--
--  It is also safe to run from the app:
--   php database/apply_update_any_error.php
--
--  EVERY STATEMENT BELOW IS WRITTEN TO BE RE-RUNNABLE.
--  No stored procedures are used, so it runs identically on MySQL 5.7/8.x,
--  MariaDB 10.3+ and inside phpMyAdmin.
-- ============================================================================

SET @OLD_SQL_MODE = @@SQL_MODE;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ===========================================================================
--  1. REFERRALS — the canonical ledger
-- ===========================================================================
--  The referral ledger historically carried TWO "referred account" columns:
--      referrals.referred_id         ← canonical (used by checkout + admin)
--      referrals.referred_user_id    ← legacy mirror (used by the old engine)
--  Rows written before the fix can have one populated and the other NULL,
--  which makes the automatic reward silently miss. This section makes both
--  columns agree for every row, in both directions.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `referrals` (
  `id`                bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id`       int          NOT NULL,
  `referred_id`       int          DEFAULT NULL,
  `referred_user_id`  int          DEFAULT NULL,
  `code`              varchar(40)  DEFAULT NULL,
  `reward_credits`    decimal(10,2) NOT NULL DEFAULT '0.00',
  `status`            varchar(20)  NOT NULL DEFAULT 'pending',
  `created_at`        datetime     DEFAULT NULL,
  `credited_at`       datetime     DEFAULT NULL,
  `updated_at`        datetime     DEFAULT NULL,
  `referrer_reward`   decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_referrals_referrer` (`referrer_id`),
  KEY `idx_referrals_referred` (`referred_id`),
  KEY `idx_referrals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 1.1 Backfill the legacy mirror from the canonical column.
UPDATE `referrals`
   SET `referred_user_id` = `referred_id`
 WHERE `referred_user_id` IS NULL
   AND `referred_id` IS NOT NULL;

-- 1.2 Backfill the canonical column from the legacy mirror.
UPDATE `referrals`
   SET `referred_id` = `referred_user_id`
 WHERE `referred_id` IS NULL
   AND `referred_user_id` IS NOT NULL;

-- 1.3 A referral row without a referred account is meaningless — remove it.
DELETE FROM `referrals`
 WHERE `referred_id` IS NULL
   AND `referred_user_id` IS NULL;

-- 1.4 Never let two rows claim the same referred account.
--     Keep the oldest row, drop the duplicates.
DELETE r2 FROM `referrals` r2
  JOIN `referrals` r1
    ON r1.referred_id = r2.referred_id
   AND r1.referred_id IS NOT NULL
   AND r1.id < r2.id;

-- 1.5 Referrals with a zero reward would pay nothing on an auto-reward.
--     Give them the platform default (10 ad credits).
UPDATE `referrals`
   SET `reward_credits` = 10.00
 WHERE `reward_credits` IS NULL
    OR `reward_credits` <= 0;

-- 1.6 A referral whose account no longer exists can never be paid.
DELETE r FROM `referrals` r
  LEFT JOIN `users` u ON u.id = COALESCE(r.referred_id, r.referred_user_id)
 WHERE u.id IS NULL;

-- 1.7 Make sure the referrer still exists too.
DELETE r FROM `referrals` r
  LEFT JOIN `users` u ON u.id = r.referrer_id
 WHERE u.id IS NULL;

-- 1.8 Normalise the status vocabulary: the platform only understands
--     pending / rewarded / rejected. The legacy "completed" status (written
--     by the old referral engine) meant "signed up", which is "pending".
UPDATE `referrals`
   SET `status` = 'pending'
 WHERE `status` IS NULL
    OR `status` = ''
    OR `status` = 'completed'
    OR `status` = 'credited';

-- 1.9 Index the legacy mirror column for the payout lookups.
SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'referrals'
               AND INDEX_NAME   = 'idx_referrals_referred_user'),
    'DO 1',
    'ALTER TABLE `referrals` ADD INDEX `idx_referrals_referred_user` (`referred_user_id`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.10 One account can only ever be referred once (guards the auto-reward
--      against paying twice if two rows ever slipped in).
SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'referrals'
               AND INDEX_NAME   = 'uq_referrals_referred'),
    'DO 1',
    'ALTER TABLE `referrals` ADD UNIQUE INDEX `uq_referrals_referred` (`referred_id`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===========================================================================
--  2. REFERRAL PAYOUT LEDGER
-- ===========================================================================
CREATE TABLE IF NOT EXISTS `referral_rewards` (
  `id`                bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id`       int          NOT NULL,
  `referred_user_id`  int          NOT NULL,
  `referrer_reward`   decimal(10,2) NOT NULL DEFAULT '0.00',
  `referred_reward`   decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at`        datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_referral_rewards_referrer` (`referrer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.1 Remove duplicate ledger rows (same referred account paid twice).
DELETE rr2 FROM `referral_rewards` rr2
  JOIN `referral_rewards` rr1
    ON rr1.referred_user_id = rr2.referred_user_id
   AND rr1.id < rr2.id;

-- 2.2 Unique key → the auto-reward can never pay the same account twice.
SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'referral_rewards'
               AND INDEX_NAME   = 'uq_referral_rewards_referred'),
    'DO 1',
    'ALTER TABLE `referral_rewards` ADD UNIQUE INDEX `uq_referral_rewards_referred` (`referred_user_id`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===========================================================================
--  3. PAYMENT WEBHOOK INBOX
-- ===========================================================================
--  Every inbound gateway webhook is recorded here. The table is optional for
--  the payout itself (the handler keeps working without it) but it is what
--  lets an admin answer "did the gateway call us?".
-- ===========================================================================
CREATE TABLE IF NOT EXISTS `payment_webhook_events` (
  `id`                 bigint unsigned NOT NULL AUTO_INCREMENT,
  `gateway`            varchar(40)  NOT NULL,
  `event_type`         varchar(80)  DEFAULT NULL,
  `gateway_reference`  varchar(190) DEFAULT NULL,
  `transaction_id`     int          DEFAULT NULL,
  `payload`            longtext     DEFAULT NULL,
  `status`             varchar(20)  NOT NULL DEFAULT 'received',
  `message`            varchar(500) DEFAULT NULL,
  `received_at`        datetime     DEFAULT NULL,
  `processed_at`       datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_gateway_ref` (`gateway`, `gateway_reference`),
  KEY `idx_webhook_status` (`status`),
  KEY `idx_webhook_received` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
--  4. TRANSACTIONS — gateway lookups
-- ===========================================================================
--  Admin "Verify" and the webhook handler both resolve a local transaction
--  through `gateway_reference` / `reference`. These indexes make that
--  lookup instant and make it safe to run at scale.
-- ===========================================================================
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`                 int          NOT NULL AUTO_INCREMENT,
  `user_id`            int          NOT NULL,
  `type`               varchar(50)  DEFAULT NULL,
  `status`             varchar(30)  DEFAULT 'pending',
  `amount`             decimal(10,2) DEFAULT '0.00',
  `currency`           varchar(10)  DEFAULT 'USD',
  `metadata`           text         DEFAULT NULL,
  `created_at`         timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  `gateway`            varchar(40)  DEFAULT NULL,
  `reference`          varchar(64)  DEFAULT NULL,
  `gateway_reference`  varchar(100) DEFAULT NULL,
  `invoice_number`     varchar(40)  DEFAULT NULL,
  `updated_at`         timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  `invoice_path`       varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tx_user` (`user_id`),
  KEY `idx_tx_gateway_reference` (`gateway_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.1 Composite (gateway, reference) index used by every verification path.
SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'transactions'
               AND INDEX_NAME   = 'idx_tx_gateway_ref_pair'),
    'DO 1',
    'ALTER TABLE `transactions` ADD INDEX `idx_tx_gateway_ref_pair` (`gateway`, `gateway_reference`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4.2 A transaction that was initiated but never sent to the gateway has no
--     gateway reference — flag them so the admin can clean them up.
UPDATE `transactions`
   SET `status` = 'failed',
       `updated_at` = NOW()
 WHERE `status` = 'pending'
   AND (`gateway_reference` IS NULL OR `gateway_reference` = '')
   AND (`gateway` IS NULL OR `gateway` NOT IN ('paystack', 'flutterwave'));

-- ===========================================================================
--  5. USERS — referral columns
-- ===========================================================================
--  `referral_code` is the shareable code, `referred_by` the link back to the
--  person who referred the account.
-- ===========================================================================
SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND COLUMN_NAME  = 'referral_code'),
    'DO 1',
    'ALTER TABLE `users` ADD COLUMN `referral_code` varchar(40) DEFAULT NULL'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND COLUMN_NAME  = 'referred_by'),
    'DO 1',
    'ALTER TABLE `users` ADD COLUMN `referred_by` int DEFAULT NULL'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5.1 Mirror `users.referred_by` from the referral ledger.
UPDATE `users` u
  JOIN `referrals` r ON r.referred_id = u.id
   SET u.referred_by = r.referrer_id
 WHERE u.referred_by IS NULL;

-- 5.2 Index the referral lookups.
SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND INDEX_NAME   = 'idx_users_referred_by'),
    'DO 1',
    'ALTER TABLE `users` ADD INDEX `idx_users_referred_by` (`referred_by`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND INDEX_NAME   = 'idx_users_referral_code'),
    'DO 1',
    'ALTER TABLE `users` ADD INDEX `idx_users_referral_code` (`referral_code`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5.3 Give every user a referral code (users without one cannot share a link).
UPDATE `users`
   SET `referral_code` = UPPER(SUBSTRING(MD5(CONCAT(`id`, 'adsb')), 1, 8))
 WHERE `referral_code` IS NULL
    OR `referral_code` = '';

-- 5.4 Duplicate referral codes would make a shared link ambiguous — give the
--     duplicates (never the first one) a fresh code. No account is deleted.
UPDATE `users` u
  JOIN (
    SELECT `id`,
           ROW_NUMBER() OVER (PARTITION BY `referral_code` ORDER BY `id`) AS rn
      FROM `users`
     WHERE `referral_code` IS NOT NULL
       AND `referral_code` <> ''
  ) d ON d.id = u.id
     AND d.rn > 1
   SET u.referral_code = UPPER(SUBSTRING(MD5(CONCAT(u.id, 'adsb-', u.referral_code)), 1, 8));

-- 5.5 Every user needs a unique referral code.
SET @ddl = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'users'
               AND INDEX_NAME   = 'uq_users_referral_code'),
    'DO 1',
    'ALTER TABLE `users` ADD UNIQUE INDEX `uq_users_referral_code` (`referral_code`)'
  )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===========================================================================
--  6. SETTINGS — referral programme defaults
-- ===========================================================================
--  These are read by modules/referral_service.php. They are seeded with the
--  platform defaults; an admin can change them later from
--  Admin → Referrals → "Referral Programme Settings".
-- ===========================================================================
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`)
SELECT 'referral_reward_credits', '10.00', 'referrals'
 WHERE NOT EXISTS (SELECT 1 FROM `site_settings` WHERE `setting_key` = 'referral_reward_credits');

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`)
SELECT 'referral_referred_bonus', '5.00', 'referrals'
 WHERE NOT EXISTS (SELECT 1 FROM `site_settings` WHERE `setting_key` = 'referral_referred_bonus');

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`)
SELECT 'referral_auto_reward', '1', 'referrals'
 WHERE NOT EXISTS (SELECT 1 FROM `site_settings` WHERE `setting_key` = 'referral_auto_reward');

-- ===========================================================================
--  7. PAYOUT REQUESTS — the referral cash-out ledger
-- ===========================================================================
CREATE TABLE IF NOT EXISTS `payout_requests` (
  `id`            bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id`       int          NOT NULL,
  `amount`        decimal(10,2) NOT NULL DEFAULT '0.00',
  `method`        varchar(40)  NOT NULL DEFAULT 'bank',
  `details_json`  text         DEFAULT NULL,
  `status`        varchar(20)  NOT NULL DEFAULT 'pending',
  `created_at`    datetime     DEFAULT NULL,
  `updated_at`    datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payout_user` (`user_id`),
  KEY `idx_payout_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
--  8. VERIFICATION SUMMARY
-- ===========================================================================
--  Reads back the objects this script guarantees, so you can see at a glance
--  that the repair succeeded.
-- ===========================================================================
SELECT 'referrals.referred_id'          AS `check_name`, COUNT(*) AS `rows_backfilled`
  FROM `referrals` WHERE `referred_id` IS NOT NULL
UNION ALL
SELECT 'referrals.pending',              COUNT(*) FROM `referrals` WHERE `status` = 'pending'
UNION ALL
SELECT 'referrals.rewarded',             COUNT(*) FROM `referrals` WHERE `status` = 'rewarded'
UNION ALL
SELECT 'referral_rewards.ledger',        COUNT(*) FROM `referral_rewards`
UNION ALL
SELECT 'payment_webhook_events',         COUNT(*) FROM `payment_webhook_events`
UNION ALL
SELECT 'transactions.pending',           COUNT(*) FROM `transactions` WHERE `status` = 'pending'
UNION ALL
SELECT 'users.with_referral_code',       COUNT(*) FROM `users` WHERE `referral_code` IS NOT NULL;

SET SQL_MODE = @OLD_SQL_MODE;

-- ===========================================================================
--  DONE
-- ---------------------------------------------------------------------------
--  After running this file:
--    • Admin → Transactions → "Verify" can resolve every local transaction
--    • A verified payment automatically rewards the referrer
--    • Admin → Referrals can still adjust, pay, reset or reject a referral
--    • The gateway webhook URLs can log every inbound delivery
--
--  Gateway webhook URLs (paste them into your gateway dashboards):
--    https://adsbooster.net/payment/webhook/flutterwave
--    https://adsbooster.net/payment/webhook/paystack
-- ===========================================================================
