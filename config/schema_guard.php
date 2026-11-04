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

    private static bool $done = false;

    /**
     * Ensure every declared column exists. Safe to call on every request.
     *
     * @return array{added:string[], skipped:bool, error:?string}
     */
    public static function ensure(): array
    {
        if (self::$done) {
            return ['added' => [], 'skipped' => true, 'error' => null];
        }
        self::$done = true;

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
        } catch (\Throwable $e) {
            error_log('SchemaGuard: ' . $e->getMessage());
            return ['added' => $added, 'skipped' => false, 'error' => $e->getMessage()];
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
