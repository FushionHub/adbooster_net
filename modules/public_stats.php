<?php
declare(strict_types=1);

/**
 * Public Real-Time Stats Engine
 * Adsbooster.net
 *
 * Computes the aggregate numbers shown on the homepage achievements section.
 * Everything returned here is safe to display publicly: counts, percentages
 * and platform names only — never per-user details.
 *
 * All figures come straight from live tables so the homepage reflects
 * current platform reality instead of hardcoded marketing numbers.
 */

require_once __DIR__ . '/../config/db.php';

final class PublicStats
{
    /** Cache lifetime for the aggregate snapshot (seconds). */
    private const CACHE_TTL = 60;

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;
    /** @var array<string,int> */
    private static array $cacheTimes = [];

    /**
     * Full live snapshot for the homepage achievements + analytics modal.
     *
     * @return array{
     *   campaigns_launched:int, active_users:int, platforms_supported:int,
     *   success_rate_pct:int, monitoring:string, queue_depth:int,
     *   campaigns_active:int, campaigns_pending:int, impressions:int,
     *   clicks:int, conversions:int, spend:float, connected_accounts:int,
     *   posts_scheduled:int
     * }
     */
    public static function snapshot(): array
    {
        $now = time();
        if (self::$cache !== null && ($now - (self::$cacheTimes['snapshot'] ?? 0)) < self::CACHE_TTL) {
            return self::$cache;
        }

        $db = db();

        $counts = $db->fetchOne("
            SELECT
              (SELECT COUNT(*) FROM ad_campaigns)                                            AS campaigns_launched,
              (SELECT COUNT(*) FROM ad_campaigns WHERE status = 'active')                    AS campaigns_active,
              (SELECT COUNT(*) FROM ad_campaigns WHERE status IN ('active','pending'))       AS campaigns_pending,
              (SELECT COUNT(*) FROM users WHERE status = 'active')                           AS active_users,
              (SELECT COUNT(DISTINCT platform_name) FROM connected_accounts
                  WHERE status = 'connected')                                                AS connected_platforms,
              (SELECT COUNT(*) FROM connected_accounts)                                      AS connected_accounts,
              (SELECT COUNT(*) FROM async_job_queue
                  WHERE status IN ('pending','processing'))                                  AS queue_depth,
              (SELECT COUNT(*) FROM scheduled_posts)                                         AS posts_scheduled,
              (SELECT COALESCE(SUM(impressions),0) FROM campaign_metrics_daily)              AS impressions,
              (SELECT COALESCE(SUM(clicks),0) FROM campaign_metrics_daily)                   AS clicks,
              (SELECT COALESCE(SUM(conversions),0) FROM campaign_metrics_daily)              AS conversions,
              (SELECT COALESCE(SUM(spend),0) FROM campaign_metrics_daily)                    AS spend
        ") ?: [];

        // Supported platforms = directly connected + the two gateway routes
        // (Bundle.social organic & Zernio ads count as always-available routes).
        $gatewayRoutes = 2;
        $platforms = max((int)($counts['connected_platforms'] ?? 0), 0) + $gatewayRoutes;

        // Success rate = campaigns that reached 'active' (or beyond) out of all
        // non-pending campaigns that went through launch attempt.
        $launched = (int)($counts['campaigns_launched'] ?? 0);
        $active   = (int)($counts['campaigns_active'] ?? 0);
        $pending  = (int)($counts['campaigns_pending'] ?? 0);
        $attempted = max(1, $launched - $pending);
        $successRate = $launched === 0 ? 0 : (int)round(min(100, max(0, ($active / $attempted) * 100)));

        self::$cache = [
            'campaigns_launched' => $launched,
            'active_users'       => (int)($counts['active_users'] ?? 0),
            'platforms_supported'=> $platforms,
            'success_rate_pct'   => $successRate,
            'monitoring'         => '24/7',
            'queue_depth'        => (int)($counts['queue_depth'] ?? 0),
            'campaigns_active'   => $active,
            'campaigns_pending'  => $pending,
            'impressions'        => (int)($counts['impressions'] ?? 0),
            'clicks'             => (int)($counts['clicks'] ?? 0),
            'conversions'        => (int)($counts['conversions'] ?? 0),
            'spend'              => (float)($counts['spend'] ?? 0.0),
            'connected_accounts' => (int)($counts['connected_accounts'] ?? 0),
            'posts_scheduled'    => (int)($counts['posts_scheduled'] ?? 0),
        ];
        self::$cacheTimes['snapshot'] = $now;

        return self::$cache;
    }

    /**
     * Display variants for the homepage achievements section.
     * Small counts show absolute truth; larger counts fall back to
     * honest formatting (e.g. 1250 -> "1.2K+") so growth stays presentable.
     */
    public static function homepage(): array
    {
        $s = self::snapshot();
        return [
            'campaigns'    => self::formatCount($s['campaigns_launched'], 'K+'),
            'users'        => self::formatCount($s['active_users'], 'K+'),
            'platforms'    => (string)$s['platforms_supported'],
            'success_rate' => $s['success_rate_pct'] . '%',
            'monitoring'   => $s['monitoring'],
            'raw'          => $s,
        ];
    }

    /** Aggregate-only analytics payload consumed by the homepage modal & the public API. */
    public static function analytics(): array
    {
        $s = self::snapshot();
        $db = db();

        // 14-day aggregate trend (impressions / clicks per day). No users.
        $trend = $db->fetchAll(
            "SELECT date,
                    SUM(impressions) AS impressions,
                    SUM(clicks)      AS clicks,
                    SUM(conversions) AS conversions
               FROM campaign_metrics_daily
              WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
              GROUP BY date
              ORDER BY date ASC"
        ) ?: [];
        $trendOut = array_map(static fn(array $r): array => [
            'date'        => (string)$r['date'],
            'impressions' => (int)$r['impressions'],
            'clicks'      => (int)$r['clicks'],
            'conversions' => (int)$r['conversions'],
            'ctr_pct'     => ((int)$r['impressions'] > 0)
                ? round(((int)$r['clicks'] / (int)$r['impressions']) * 100, 2)
                : 0.0,
        ], $trend);

        // Platform mix — connected accounts only (pure counts).
        $platforms = $db->fetchAll(
            "SELECT platform_name, COUNT(*) AS accounts
               FROM connected_accounts
              WHERE status = 'connected'
              GROUP BY platform_name
              ORDER BY accounts DESC"
        ) ?: [];

        // Campaign status mix.
        $statusRows = $db->fetchAll(
            "SELECT status, COUNT(*) AS n FROM ad_campaigns GROUP BY status ORDER BY n DESC"
        ) ?: [];
        $statusMix = [];
        foreach ($statusRows as $r) {
            $statusMix[(string)$r['status']] = (int)$r['n'];
        }

        $ctr = $s['impressions'] > 0 ? round(($s['clicks'] / $s['impressions']) * 100, 2) : 0.0;
        $cpc = $s['clicks'] > 0 ? round($s['spend'] / $s['clicks'], 2) : 0.0;
        $cvr = $s['clicks'] > 0 ? round(($s['conversions'] / $s['clicks']) * 100, 2) : 0.0;

        return [
            'generated_at'  => gmdate('c'),
            'note'          => 'Aggregate analytics only. No user-level data is exposed.',
            'totals' => [
                'campaigns'         => $s['campaigns_launched'],
                'campaigns_active'  => $s['campaigns_active'],
                'active_users'      => $s['active_users'],
                'platforms'         => $s['platforms_supported'],
                'connected_accounts'=> $s['connected_accounts'],
                'posts_scheduled'   => $s['posts_scheduled'],
                'impressions'       => $s['impressions'],
                'clicks'            => $s['clicks'],
                'conversions'       => $s['conversions'],
                'spend'             => $s['spend'],
            ],
            'performance' => [
                'success_rate_pct' => $s['success_rate_pct'],
                'ctr_pct'          => $ctr,
                'cvr_pct'          => $cvr,
                'avg_cpc'          => $cpc,
            ],
            'queue' => [
                'depth' => $s['queue_depth'],
                'mode'  => 'async_dual_gateway',
            ],
            'trend_14d' => $trendOut,
            'platform_mix' => $platforms,
            'campaign_status_mix' => $statusMix,
        ];
    }

    private static function formatCount(int $n, string $suffix = ''): string
    {
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . $suffix;
        }
        return (string)$n;
    }
}
