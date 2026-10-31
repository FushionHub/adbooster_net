<?php
declare(strict_types=1);

// CLI-only: these scripts run privileged cross-user jobs and must never be
// reachable over HTTP. Scheduled from crontab / Task Scheduler only.
if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); }
    echo 'Forbidden: cron jobs are CLI-only.' . PHP_EOL;
    exit;
}


/**
 * Cron Job: A/B Test Winner Declaration
 * Adsbooster.net
 *
 * Checks ad_campaign_variants with enough data.
 * Declares winner, reallocates budget, notifies user.
 * Logs run to cron_run_logs.
 */

require_once __DIR__ . '/../config/db.php';

$jobName = 'ab_test_winner';
$logId = null;

try {
    $startedAt = date('Y-m-d H:i:s');
    $logId = db()->insert('cron_run_logs', [
        'job_name'   => $jobName,
        'started_at' => $startedAt,
        'status'     => 'running',
    ]);

    $campaigns = db()->fetchAll(
        "SELECT c.id, c.user_id, c.campaign_name, c.budget_amount
         FROM ad_campaigns c
         WHERE c.status = 'active'
         ORDER BY c.id ASC"
    );

    $winnerCount = 0;
    $skipped = 0;

    foreach ($campaigns as $campaign) {
        try {
            $existingWinner = db()->fetchOne(
                "SELECT id FROM ad_campaign_variants WHERE campaign_id = ? AND is_winner = 1",
                [$campaign['id']]
            );

            if ($existingWinner) {
                $skipped++;
                continue;
            }

            $variants = db()->fetchAll(
                "SELECT id, variant_label, headline, traffic_split_pct
                 FROM ad_campaign_variants
                 WHERE campaign_id = ?
                 ORDER BY id ASC",
                [$campaign['id']]
            );

            if (count($variants) < 2) {
                continue;
            }

            $variantMetrics = [];
            foreach ($variants as $variant) {
                $metrics = db()->fetchOne(
                    "SELECT
                        COALESCE(SUM(impressions), 0) as total_impressions,
                        COALESCE(SUM(clicks), 0) as total_clicks,
                        COALESCE(SUM(conversions), 0) as total_conversions,
                        COALESCE(SUM(spend), 0) as total_spend
                     FROM campaign_metrics_daily
                     WHERE campaign_id = ? AND variant_id = ?
                       AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)",
                    [$campaign['id'], $variant['id']]
                );

                $totalClicks = (int) $metrics['total_clicks'];
                $totalImpressions = (int) $metrics['total_impressions'];

                $ctr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;
                $cpc = $totalClicks > 0 ? (float) $metrics['total_spend'] / $totalClicks : 999;
                $score = $ctr * 10 - $cpc;

                $variantMetrics[] = [
                    'variant'           => $variant,
                    'total_impressions' => $totalImpressions,
                    'total_clicks'      => $totalClicks,
                    'total_conversions' => (int) $metrics['total_conversions'],
                    'total_spend'       => (float) $metrics['total_spend'],
                    'ctr'               => round($ctr, 2),
                    'cpc'               => round($cpc, 2),
                    'score'             => round($score, 2),
                ];
            }

            $hasMinimumData = false;
            foreach ($variantMetrics as $vm) {
                if ($vm['total_impressions'] >= 100) {
                    $hasMinimumData = true;
                    break;
                }
            }

            if (!$hasMinimumData) {
                $skipped++;
                continue;
            }

            usort($variantMetrics, fn($a, $b) => $b['score'] <=> $a['score']);
            $winner = $variantMetrics[0];

            db()->query(
                "UPDATE ad_campaign_variants SET is_winner = 0 WHERE campaign_id = ?",
                [$campaign['id']]
            );

            db()->update('ad_campaign_variants', [
                'is_winner' => 1,
            ], 'id = ?', [$winner['variant']['id']]);

            db()->update('ad_campaign_variants', [
                'traffic_split_pct' => 100,
            ], 'id = ?', [$winner['variant']['id']]);

            foreach (array_slice($variantMetrics, 1) as $loser) {
                db()->update('ad_campaign_variants', [
                    'traffic_split_pct' => 0,
                ], 'id = ?', [$loser['variant']['id']]);
            }

            db()->insert('notifications', [
                'user_id'    => $campaign['user_id'],
                'type'       => 'ab_test_winner',
                'title'      => 'A/B Test Winner Declared',
                'body'       => sprintf(
                    'Variant "%s" won the A/B test for campaign "%s" with %.2f%% CTR and $%.2f CPC. Budget reallocated to winner.',
                    $winner['variant']['variant_label'],
                    $campaign['campaign_name'],
                    $winner['ctr'],
                    $winner['cpc']
                ),
                'link'       => "/campaigns/{$campaign['id']}",
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $winnerCount++;
        } catch (\Throwable $e) {
            error_log("ab_test_winner: Failed campaign {$campaign['id']}: " . $e->getMessage());
        }
    }

    $finishedAt = date('Y-m-d H:i:s');
    db()->update('cron_run_logs', [
        'finished_at' => $finishedAt,
        'status'      => 'success',
        'notes'       => "Scanned: " . count($campaigns) . " campaigns. Winners declared: {$winnerCount}, Skipped: {$skipped}",
    ], 'id = ?', [$logId]);

    echo "ab_test_winner completed. Winners declared: {$winnerCount}\n";

} catch (\Throwable $e) {
    error_log("ab_test_winner: " . $e->getMessage());
    if ($logId !== null) {
        try {
            db()->update('cron_run_logs', [
                'finished_at' => date('Y-m-d H:i:s'),
                'status'      => 'failed',
                'notes'       => $e->getMessage(),
            ], 'id = ?', [$logId]);
        } catch (\Throwable $logError) {
            error_log("Failed to log cron error: " . $logError->getMessage());
        }
    }
    exit(1);
}
