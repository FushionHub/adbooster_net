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
 * Cron Job: Campaign Doctor
 * Adsbooster.net
 *
 * Scans active campaigns for issues: rising CPC, falling CTR, budget exhaustion.
 * Creates campaign_recommendations for flagged campaigns.
 * If autopilot_enabled, applies safe recommendations automatically.
 * Logs run to cron_run_logs.
 */

require_once __DIR__ . '/../config/db.php';
// The bootstrap carries the admin-tunable naira exchange rate and the
// currency helpers used by the health checks below.
require_once __DIR__ . '/../config/site_setting_and_config.php';

$jobName = 'campaign_doctor';
$logId = null;

try {
    $startedAt = date('Y-m-d H:i:s');
    $logId = db()->insert('cron_run_logs', [
        'job_name'   => $jobName,
        'started_at' => $startedAt,
        'status'     => 'running',
    ]);

    $campaigns = db()->fetchAll(
        "SELECT c.id, c.user_id, c.campaign_name, c.autopilot_enabled, c.budget_amount, c.budget_type
         FROM ad_campaigns c
         WHERE c.status = 'active'
         ORDER BY c.id ASC"
    );

    $flagged = 0;
    $autoApplied = 0;

    foreach ($campaigns as $campaign) {
        try {
            $recentMetrics = db()->fetchAll(
                "SELECT impressions, clicks, spend, ctr, cpc
                 FROM campaign_metrics_daily
                 WHERE campaign_id = ?
                 ORDER BY date DESC
                 LIMIT 7",
                [$campaign['id']]
            );

            if (count($recentMetrics) < 3) {
                continue;
            }

            $issues = analyzeCampaignHealth($campaign, $recentMetrics);

            foreach ($issues as $issue) {
                $existingRec = db()->fetchOne(
                    "SELECT id FROM campaign_recommendations
                     WHERE campaign_id = ? AND recommendation_type = ? AND status = 'pending'",
                    [$campaign['id'], $issue['type']]
                );

                if ($existingRec) {
                    continue;
                }

                db()->insert('campaign_recommendations', [
                    'campaign_id'          => $campaign['id'],
                    'recommendation_type'  => $issue['type'],
                    'message'              => $issue['message'],
                    'suggested_action'     => $issue['action'],
                    'status'               => 'pending',
                    'created_at'           => date('Y-m-d H:i:s'),
                ]);

                $flagged++;

                if ($campaign['autopilot_enabled'] && $issue['safe_for_autopilot']) {
                    applyAutopilotRecommendation($campaign['id'], $issue);
                    $autoApplied++;
                }
            }
        } catch (\Throwable $e) {
            error_log("campaign_doctor: Failed campaign {$campaign['id']}: " . $e->getMessage());
        }
    }

    $finishedAt = date('Y-m-d H:i:s');
    db()->update('cron_run_logs', [
        'finished_at' => $finishedAt,
        'status'      => 'success',
        'notes'       => "Scanned: " . count($campaigns) . " campaigns. Flagged: {$flagged}, Auto-applied: {$autoApplied}",
    ], 'id = ?', [$logId]);

    echo "campaign_doctor completed. Flagged: {$flagged}, Auto-applied: {$autoApplied}\n";

} catch (\Throwable $e) {
    error_log("campaign_doctor: " . $e->getMessage());
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

/**
 * Analyze campaign health based on recent metrics.
 *
 * @return array<int, array{type: string, message: string, action: string, safe_for_autopilot: bool}>
 */
function analyzeCampaignHealth(array $campaign, array $metrics): array
{
    $issues = [];

    $latestCpc = (float) end($metrics)['cpc'];
    $avgCpc = array_sum(array_column($metrics, 'cpc')) / count($metrics);

    if ($avgCpc > 0 && $latestCpc > $avgCpc * 1.5) {
        $issues[] = [
            'type'              => 'rising_cpc',
            'message'           => "CPC has risen by " . round((($latestCpc - $avgCpc) / $avgCpc) * 100, 1) . "% above average. Current: $" . number_format($latestCpc, 2),
            'action'            => 'Consider pausing and reviewing ad creative or targeting.',
            'safe_for_autopilot' => false,
        ];
    }

    $latestCtr = (float) end($metrics)['ctr'];
    $avgCtr = array_sum(array_column($metrics, 'ctr')) / count($metrics);

    if ($avgCtr > 0 && $latestCtr < $avgCtr * 0.5) {
        $issues[] = [
            'type'              => 'falling_ctr',
            'message'           => "CTR has dropped to " . round($latestCtr, 2) . "% (avg: " . round($avgCtr, 2) . "%). Engagement may be declining.",
            'action'            => 'Refresh ad creative or adjust audience targeting.',
            'safe_for_autopilot' => false,
        ];
    }

    // Unit split: campaign_metrics_daily.spend is reported by the ad
    // gateway in the gateway's currency (USD-canonical), while
    // ad_campaigns.budget_amount is NAIRA-native. Compare them in ONE unit
    // — convert the naira budget into the gateway's reporting currency —
    // otherwise a ₦15,000 budget looks "exhausted" against a $9 spend.
    $totalSpend = array_sum(array_column($metrics, 'spend'));
    $budgetNgn = (float) $campaign['budget_amount'];
    $rate = function_exists('exchange_rate_ngn_per_usd') ? exchange_rate_ngn_per_usd() : 1550.0;
    $budgetGateway = $budgetNgn / max(1.0, $rate);

    if ($budgetGateway > 0 && $totalSpend >= $budgetGateway * 0.9) {
        $issues[] = [
            'type'              => 'budget_exhaustion',
            'message'           => "Campaign has used " . round(($totalSpend / $budgetGateway) * 100, 1) . "% of its budget (₦" . number_format($totalSpend * $rate, 0) . " / ₦" . number_format($budgetNgn, 0) . ").",
            'action'            => 'Increase budget or pause campaign to prevent overspend.',
            'safe_for_autopilot' => false,
        ];
    }

    $totalImpressions = array_sum(array_column($metrics, 'impressions'));
    $totalClicks = array_sum(array_column($metrics, 'clicks'));

    if ($totalImpressions > 1000 && $totalClicks < 5) {
        $issues[] = [
            'type'              => 'low_engagement',
            'message'           => "Campaign received {$totalImpressions} impressions but only {$totalClicks} clicks. Very low engagement detected.",
            'action'            => 'Review headline, primary text, and creative for relevance.',
            'safe_for_autopilot' => false,
        ];
    }

    if ($totalSpend > 0 && $totalClicks > 0) {
        $avgCpcAll = $totalSpend / $totalClicks;
        if ($avgCpcAll > 2.0) {
            $issues[] = [
                'type'              => 'high_cpc',
                'message'           => "Average CPC is $" . number_format($avgCpcAll, 2) . " across recent metrics. Above recommended threshold.",
                'action'            => 'Optimize ad quality score or broaden targeting to reduce CPC.',
                'safe_for_autopilot' => false,
            ];
        }
    }

    return $issues;
}

/**
 * Apply a safe autopilot recommendation.
 */
function applyAutopilotRecommendation(int $campaignId, array $issue): void
{
    $rec = db()->fetchOne(
        "SELECT id FROM campaign_recommendations
         WHERE campaign_id = ? AND recommendation_type = ? AND status = 'pending'
         ORDER BY id DESC LIMIT 1",
        [$campaignId, $issue['type']]
    );

    if ($rec) {
        db()->update('campaign_recommendations', [
            'status' => 'auto_applied',
        ], 'id = ?', [$rec['id']]);

        db()->insert('campaign_run_logs', [
            'campaign_id' => $campaignId,
            'action'      => 'autopilot_' . $issue['type'],
            'details'     => json_encode([
                'type'    => $issue['type'],
                'message' => $issue['message'],
                'action'  => 'auto_applied',
            ]),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
