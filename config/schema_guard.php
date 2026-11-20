<?php declare(strict_types=1);

/**
 * Schema Guard — keeps the live schema in step with the code.
 *
 * Every column the application reads or writes is declared here. On boot the
 * guard checks the live schema once and adds anything that is missing, so a
 * deployment that is one migration behind still works instead of dying with
 * "Unknown column 'x' in 'field list'".
 *
 * The same list is the source of truth for database/backfill_missing_columns.sql.
 */

require_once __DIR__ . '/db.php';

class SchemaGuard
{
    /**
     * table => [column => definition]
     *
     * Definitions must be valid MySQL column definitions (without the
     * ADD COLUMN keywords).
     */
    private const COLUMNS = [
        // Email-based 2FA / password reset codes.
        'users' => [
            'email_2fa_code'       => 'VARCHAR(255) NULL',
            'email_2fa_expires_at' => 'DATETIME NULL',
            '2fa_attempts'         => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
            // "Don't show again" for the onboarding popup (permanent opt-out;
            // the session-scoped "Skip for later" lives in $_SESSION).
            'onboarding_popup_dismissed' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        ],
        // Campaign Doctor recommendations (the code stores richer fields than
        // the original legacy recommendation_type/message pair).
        'campaign_recommendations' => [
            'user_id'          => 'INT NULL',
            'type'             => 'VARCHAR(50) NULL',
            'severity'         => 'VARCHAR(20) NULL',
            'title'            => 'VARCHAR(190) NULL',
            'description'      => 'TEXT NULL',
            'suggested_action' => 'TEXT NULL',
            'applied_at'       => 'DATETIME NULL',
        ],
        // Bid strategy fields used by the campaign optimiser.
        'ad_campaigns' => [
            'bid_amount'  => 'DECIMAL(10,2) NULL',
            'ad_rotation' => 'VARCHAR(50) NULL',
        ],
        // When a bell notification was read.
        'notifications' => [
            'read_at' => 'DATETIME NULL',
        ],
        // Reward credited to the referrer (mirrors reward_credits).
        'referrals' => [
            'referrer_reward' => 'DECIMAL(10,2) NULL',
        ],
        // Boosted listings expire after a window.
        'marketplace_items' => [
            'boost_expires_at' => 'DATETIME NULL',
        ],
        // Settings are grouped in the admin UI.
        'site_settings' => [
            'setting_group' => "VARCHAR(30) NULL DEFAULT 'general'",
        ],
        // Link to the generated PDF invoice.
        'transactions' => [
            'invoice_path' => 'VARCHAR(255) NULL',
        ],
        // Ticket reply attachments.
        'ticket_messages' => [
            'attachment_path' => 'VARCHAR(255) NULL',
        ],
        // Per-post reminder plan for the content calendar: how many email
        // reminders the user wants before publish time, and how many the
        // cron has already sent (duplicate-prevention counter).
        'scheduled_posts' => [
            'reminder_count'  => 'INT NULL DEFAULT 3',
            'reminders_sent'  => 'INT NULL DEFAULT 0',
        ],
        // NGN-native pricing for products that must never drift with the
        // admin-tunable USD exchange rate (e.g. the '1,000 Leads — ₦3,500'
        // SKU). NULL = legacy USD-canonical pricing.
        'pricing_plans' => [
            'price_ngn' => 'DECIMAL(12,2) NULL',
            // 'plan' (subscription) | 'lead_pack' (the 1,000 Leads product).
            'product_type' => "VARCHAR(40) NOT NULL DEFAULT 'plan'",
        ],
        // Real conversion value + reach pulled from the ad gateway.
        // NULL = the gateway did not report the metric (unavailable), which
        // is NOT the same as zero — the auto-buyer must never read a
        // missing column as "no revenue".
        'campaign_metrics_daily' => [
            'revenue' => 'DECIMAL(12,2) NULL',
            'reach'   => 'INT NULL',
        ],
    ];

    /**
     * Tables the application creates on boot when missing (idempotent).
     * table => CREATE TABLE statement.
     */
    private const TABLES = [
        // Directory/lead-list fulfillment for the '1,000 Leads' product:
        // a purchase creates a pending order; an admin attaches the
        // delivered lead list and flips it to 'delivered'.
        'lead_orders' => "CREATE TABLE IF NOT EXISTS `lead_orders` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `transaction_id` INT NULL,
            `leads_count` INT NOT NULL DEFAULT 1000,
            `price_ngn` DECIMAL(12,2) NOT NULL DEFAULT 3500.00,
            `status` ENUM('pending','processing','delivered','refunded') NOT NULL DEFAULT 'pending',
            `deliverable_path` VARCHAR(255) NULL,
            `notes` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `delivered_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_lead_orders_user` (`user_id`),
            KEY `idx_lead_orders_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];

    /**
     * Data migrations that backfill a newly added column from an existing one.
     * [table => [new column => SQL expression]]
     */
    private const BACKFILL = [
        'referrals' => [
            'referrer_reward' => 'reward_credits',
        ],
    ];

    /**
     * Bumped whenever a hand-written data migration below changes, so the
     * on-disk probe cache is invalidated and the migration runs again.
     */
    private const MIGRATIONS_VERSION = 2;

    private static bool $done = false;

    /**
     * Persistent cache: probing information_schema on EVERY request is
     * brutally slow on shared hosting (each information_schema scan can take
     * 100ms–seconds on a contended server, and ensure() issues ~2×declared
     * tables/columns of them per boot). The probe result is therefore cached
     * on disk, keyed by a fingerprint of the COLUMNS/BACKFILL maps — a new
     * deployment (any edit to those maps) naturally invalidates the cache
     * and re-probes exactly once.
     */
    private static function cachePath(): string
    {
        $dir = __DIR__ . '/../cache';
        if (!is_dir($dir) || !is_writable($dir)) {
            $dir = sys_get_temp_dir() . '/adsbooster-cache';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        return $dir . '/schema_guard.json';
    }

    private static function fingerprint(): string
    {
        return sha1(serialize([self::COLUMNS, self::BACKFILL, self::MIGRATIONS_VERSION]));
    }

    /**
     * Ensure every declared column exists. Runs the live probe a single time
     * per deployment; subsequent requests short-circuit to the cached answer.
     *
     * @return array{added:string[], skipped:bool, error:?string}
     */
    public static function ensure(): array
    {
        if (self::$done) {
            return ['added' => [], 'skipped' => true, 'error' => null];
        }
        self::$done = true;

        // Fast path: a cached verdict for the current fingerprint skips all
        // information_schema work.
        $fp = self::fingerprint();
        $cacheFile = self::cachePath();
        if (is_readable($cacheFile)) {
            $cache = @json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cache) && ($cache['fp'] ?? '') === $fp) {
                return [
                    'added'   => (array)($cache['added'] ?? []),
                    'skipped' => true,
                    'error'   => null,
                ];
            }
        }

        $added = [];
        try {
            $pdo = db()->getConnection();

            foreach (self::COLUMNS as $table => $columns) {
                if (!self::tableExists($pdo, $table)) {
                    continue; // the table itself is created by the SQL backfill
                }
                foreach ($columns as $column => $definition) {
                    if (self::columnExists($pdo, $table, $column)) {
                        continue;
                    }
                    $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
                    $pdo->exec($sql);
                    $added[] = "{$table}.{$column}";
                    error_log("SchemaGuard: added column {$table}.{$column}");
                }
            }

            // Tables (CREATE TABLE IF NOT EXISTS is idempotent by design).
            foreach (self::TABLES as $table => $createSql) {
                if (self::tableExists($pdo, $table)) {
                    continue;
                }
                $pdo->exec($createSql);
                $added[] = $table;
                error_log("SchemaGuard: created table {$table}");
            }

            // Backfills (only after the columns exist).
            foreach (self::BACKFILL as $table => $columns) {
                foreach ($columns as $column => $expression) {
                    if (!self::columnExists($pdo, $table, $column)) {
                        continue;
                    }
                    $pdo->exec(
                        "UPDATE `{$table}` SET `{$column}` = `{$expression}`
                         WHERE `{$column}` IS NULL AND `{$expression}` IS NOT NULL"
                    );
                }
            }

            // Hand-written data migrations (NGN pricing, product catalogue).
            self::migratePricingPlans($pdo);
        } catch (\Throwable $e) {
            error_log('SchemaGuard: ' . $e->getMessage());
            return ['added' => $added, 'skipped' => false, 'error' => $e->getMessage()];
        }

        // Persist the successful verdict (once per deployment).
        if ($added === []) {
            @file_put_contents(
                self::cachePath(),
                json_encode(['fp' => $fp, 'added' => [], 'ts' => time()]),
                LOCK_EX
            );
        }

        return ['added' => $added, 'skipped' => false, 'error' => null];
    }

    private static function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Run the NGN pricing migration on demand (ignores the probe cache).
     *
     * The guard calls this automatically on boot; this entry point exists for
     * operators (and tests) that need to force the migration, e.g. right
     * after importing a legacy database dump.
     */
    public static function migratePricingPlansNow(): void
    {
        try {
            self::migratePricingPlans(db()->getConnection());
        } catch (\Throwable $e) {
            error_log('SchemaGuard: pricing migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Translate the legacy USD-canonical price sheet into NGN and guarantee
     * the published '1,000 Leads' product exists.
     *
     * WHY THIS MATTERS
     * -----------------
     * The platform currency is NGN, and every price is rendered with
     * price_ngn_fmt(). Before this migration, pricing_plans.price held a
     * USD-canonical number (9 / 24 / 59 / 149) and price_ngn was NULL, so
     * the pricing page would have offered "Starter — ₦9.00" and the
     * checkout would have charged ₦9 instead of ₦13,950 — a 1,550×
     * undercharge on every subscription.
     *
     * The legacy signal is price_ngn IS NULL: a row that already carries a
     * naira price is a migrated row and is left alone. The translation uses
     * the admin-tunable naira rate (1550 by default), which reproduces the
     * published price sheet exactly (9 → ₦13,950, 24 → ₦37,200,
     * 59 → ₦91,450, 149 → ₦230,950, 29 → ₦44,950).
     *
     * Idempotent: once price_ngn is populated the rows no longer match, so
     * re-running the guard changes nothing.
     */
    private static function migratePricingPlans(\PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'pricing_plans')
            || !self::columnExists($pdo, 'pricing_plans', 'price_ngn')) {
            return;
        }

        // ── 1. Legacy ad-credit bundles were USD-canonical too ────────────
        // ad_credits_included is INT; ₦186,000 still fits comfortably.
        $rate = self::ngnPerUsdRate();

        try {
            $pdo->exec(
                'UPDATE `pricing_plans`
                    SET `ad_credits_included` = ROUND(`ad_credits_included` * ' . $rate . ')
                  WHERE `price_ngn` IS NULL
                    AND `ad_credits_included` IS NOT NULL
                    AND `ad_credits_included` > 0'
            );
        } catch (\Throwable $e) {
            error_log('SchemaGuard: ad-credit currency migration failed: ' . $e->getMessage());
        }

        // ── 2. Legacy plan price → naira ──────────────────────────────────
        try {
            $pdo->exec(
                'UPDATE `pricing_plans`
                    SET `price_ngn` = `price` * ' . $rate . '
                  WHERE `price_ngn` IS NULL
                    AND `price` IS NOT NULL
                    AND `price` > 0'
            );
            // `price` now holds the naira price, so every consumer agrees on
            // which column to read.
            $pdo->exec(
                'UPDATE `pricing_plans` SET `price` = `price_ngn` WHERE `price_ngn` IS NOT NULL'
            );
        } catch (\Throwable $e) {
            error_log('SchemaGuard: plan price currency migration failed: ' . $e->getMessage());
        }

        self::installLeadPackProduct($pdo);
    }

    /**
     * Make sure the published lead-list product exists.
     *
     * The '1,000 Leads — ₦3,500' SKU is part of the price sheet, so a
     * deployment that never ran the seed script must still offer it. An
     * existing row is only repaired (product type / status / zero price),
     * never re-priced — an admin may have edited it deliberately.
     */
    private static function installLeadPackProduct(\PDO $pdo): void
    {
        $packName  = '1,000 Leads';
        $packPrice = 3500.0;
        $packCount = 1000;
        if (function_exists('get_setting')) {
            try {
                $name = trim((string)get_setting('leads_pack_product_name', $packName));
                if ($name !== '') {
                    $packName = $name;
                }
                $price = (float)get_setting('leads_pack_price_ngn', (string)$packPrice);
                if ($price > 0) {
                    $packPrice = $price;
                }
                $count = (int)get_setting('leads_pack_count', (string)$packCount);
                if ($count > 0) {
                    $packCount = $count;
                }
            } catch (\Throwable $e) {
                // Settings not ready — the published defaults are used.
            }
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM `pricing_plans`
                  WHERE `name` = ? AND `billing_cycle` = 'pay_as_you_go'
                  LIMIT 1"
            );
            $stmt->execute([$packName]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                // Repair only — never overwrite an administrator's price.
                $pdo->prepare(
                    "UPDATE `pricing_plans`
                        SET `product_type` = 'lead_pack',
                            `status` = 'active',
                            `price` = CASE WHEN `price` IS NULL OR `price` <= 0 THEN ? ELSE `price` END,
                            `price_ngn` = CASE WHEN `price_ngn` IS NULL OR `price_ngn` <= 0 THEN ? ELSE `price_ngn` END
                      WHERE `id` = ?"
                )->execute([$packPrice, $packPrice, (int)$existingId]);
                return;
            }

            $money = static function (float $ngn): string {
                return function_exists('price_ngn_fmt')
                    ? price_ngn_fmt($ngn)
                    : '₦' . number_format($ngn, 0);
            };

            $features = json_encode([
                number_format($packCount) . ' verified business leads (' . $money($packPrice) . ' per ' . number_format($packCount) . ')',
                'Name, business category, phone/WhatsApp and location columns',
                'Delivered as a downloadable CSV from your Billing page',
                'Sourced from the Adsbooster verified business directory',
                'Fulfilled by the Adsbooster team — usually within 48 hours',
                'Ads run on your budget: self-serve campaigns convert at ' . $money(3800.0) . ' per 1,000 leads, managed campaigns at ' . $money(4200.0),
            ], JSON_UNESCAPED_SLASHES);

            $pdo->prepare(
                "INSERT INTO `pricing_plans`
                    (`name`, `description`, `price`, `price_ngn`, `billing_cycle`,
                     `ad_credits_included`, `ai_credits_included`, `features_json`,
                     `is_featured`, `product_type`, `status`, `created_at`)
                 VALUES (?, ?, ?, ?, 'pay_as_you_go', 0, 0, ?, 1, 'lead_pack', 'active', NOW())"
            )->execute([
                $packName,
                'One thousand verified business leads from the Adsbooster directory, delivered as a ready-to-call list. Adsbooster fulfils every order manually.',
                $packPrice,
                $packPrice,
                $features,
            ]);
            error_log("SchemaGuard: installed lead product '{$packName}' at " . $packPrice);
        } catch (\Throwable $e) {
            error_log('SchemaGuard: lead product install failed: ' . $e->getMessage());
        }
    }

    /**
     * Naira per 1 USD (admin-tunable), formatted for safe SQL interpolation.
     * Falls back to the published rate.
     */
    private static function ngnPerUsdRate(): string
    {
        $rate = 1550.0;
        if (function_exists('exchange_rate_ngn_per_usd')) {
            try {
                $configured = exchange_rate_ngn_per_usd();
                if ($configured > 0) {
                    $rate = $configured;
                }
            } catch (\Throwable $e) {
                // Settings not ready — the published default is used.
            }
        }
        return number_format($rate, 4, '.', '');
    }

    private static function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
