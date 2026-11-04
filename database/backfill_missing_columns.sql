-- =====================================================================
-- ADSBOOSTER.NET — MISSING COLUMN BACKFILL
--
-- Adds the columns the application reads/writes but that an older
-- deployment does not have yet. Without these, the affected pages die
-- with "Unknown column 'x' in 'field list'".
--
-- IDEMPOTENT: safe to run repeatedly. The helper procedure only issues
-- the ALTER when the column is genuinely absent.
--
-- Run with:
--   mysql -u root adsbooster -e "source C:/laragon/www/seopp/database/backfill_missing_columns.sql"
-- =====================================================================

DROP PROCEDURE IF EXISTS `adsb_add_column_if_missing`;

DELIMITER $$
CREATE PROCEDURE `adsb_add_column_if_missing`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition VARCHAR(255)
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ── users: email-based 2FA / password reset codes ───────────────────
CALL `adsb_add_column_if_missing`('users', 'email_2fa_code',       'VARCHAR(255) NULL');
CALL `adsb_add_column_if_missing`('users', 'email_2fa_expires_at', 'DATETIME NULL');
CALL `adsb_add_column_if_missing`('users', '2fa_attempts',         'TINYINT UNSIGNED NOT NULL DEFAULT 0');

-- ── campaign_recommendations: full recommendation payload ───────────
CALL `adsb_add_column_if_missing`('campaign_recommendations', 'user_id',          'INT NULL');
CALL `adsb_add_column_if_missing`('campaign_recommendations', 'type',             'VARCHAR(50) NULL');
CALL `adsb_add_column_if_missing`('campaign_recommendations', 'severity',         'VARCHAR(20) NULL');
CALL `adsb_add_column_if_missing`('campaign_recommendations', 'title',            'VARCHAR(190) NULL');
CALL `adsb_add_column_if_missing`('campaign_recommendations', 'description',      'TEXT NULL');
CALL `adsb_add_column_if_missing`('campaign_recommendations', 'suggested_action', 'TEXT NULL');
CALL `adsb_add_column_if_missing`('campaign_recommendations', 'applied_at',       'DATETIME NULL');

-- ── ad_campaigns: bid strategy ──────────────────────────────────────
CALL `adsb_add_column_if_missing`('ad_campaigns', 'bid_amount',  'DECIMAL(10,2) NULL');
CALL `adsb_add_column_if_missing`('ad_campaigns', 'ad_rotation', 'VARCHAR(50) NULL');

-- ── notifications: when the bell item was read ──────────────────────
CALL `adsb_add_column_if_missing`('notifications', 'read_at', 'DATETIME NULL');

-- ── referrals: reward credited to the referrer ──────────────────────
CALL `adsb_add_column_if_missing`('referrals', 'referrer_reward', 'DECIMAL(10,2) NULL');

DROP PROCEDURE IF EXISTS `adsb_add_column_if_missing`;

-- ── Data backfill: mirror the pre-existing reward_credits value ─────
UPDATE `referrals`
   SET `referrer_reward` = `reward_credits`
 WHERE `referrer_reward` IS NULL
   AND `reward_credits` IS NOT NULL;

-- ── Legacy recommendation rows: derive the new columns from the old
--    recommendation_type / message pair so existing rows stay readable.
UPDATE `campaign_recommendations`
   SET `type`       = COALESCE(`type`, `recommendation_type`),
       `title`      = COALESCE(`title`, `recommendation_type`),
       `description`= COALESCE(`description`, `message`)
 WHERE `type` IS NULL OR `title` IS NULL OR `description` IS NULL;
