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
 * Cron Job: Pull Campaign Metrics — Zernio Ad Gateway Edition
 * Adsbooster.net
 *
 * Writes real per-day performance rows into campaign_metrics_daily for every
 * active campaign that carries a `zernio_campaign_id` mapping, using the
 * unified ThirdPartyGateway (Zernio API /campaigns/{id}/metrics).
 *
 * HARD RULE: this job deliberately writes NOTHING unless a genuine metrics
 * source supplies the numbers. A historical version fabricated impressions,
 * clicks and spend with rand(); those invented figures fed dashboards, A/B
 * winner selection and achievements. Inventing ad performance data is worse
 * than showing none.
 *
 * Runtime simulation: when the Zernio API key is still a placeholder, the
 * gateway returns a SIMULATED payload flagged with `simulated: true`.
 * This cron refuses to persist rows flagged as simulated and reports them
 * separately, so operators can see the difference between live metrics and
 * placeholder-key runs.
 *
 * Logs run to cron_run_logs.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/ThirdPartyGateway.php';

$jobName = 'pull_campaign_metrics';
$logId   = null;

try {
    $logId = db()->insert('cron_run_logs', [
        'job_name'   => $jobName,
        'started_at' => date('Y-m-d H:i:s'),
        'status'     => 'running',
    ]);

    $campaigns = db()->fetchAll(
        "SELECT id, user_id, campaign_name, zernio_campaign_id
         FROM ad_campaigns
         WHERE status = 'active'
           AND zernio_campaign_id IS NOT NULL
           AND zernio_campaign_id <> ''
         ORDER BY id ASC"
    );

    if (!$campaigns) {
        finish($logId, 'success', 'No active campaigns carry a Zernio campaign mapping; nothing to pull. Launch or link campaigns via the Zernio Ad Engine to receive performance data.');
        echo "pull_campaign_metrics: no active campaigns with zernio_campaign_id.\n";
        exit(0);
    }

    $today            = date('Y-m-d');
    $written          = 0;
    $failed           = 0;
    $simulatedSkipped = 0;
    $noData           = 0;

    foreach ($campaigns as $campaign) {
        $zernioId = trim((string)$campaign['zernio_campaign_id']);

        if ($zernioId === '') {
            $noData++;
            continue;
        }

        try {
            $res = ThirdPartyGateway::getCampaignMetrics($zernioId);
        } catch (\Throwable $gw) {
            $failed++;
            error_log("pull_campaign_metrics: gateway error for campaign {$campaign['id']} ({$zernioId}): " . $gw->getMessage());
            continue;
        }

        $payload = is_array($res['data'] ?? null) ? $res['data'] : [];

        // Fabrication guard: never persist metrics the gateway simulates
        // (placeholder API key or unknown endpoint).
        if (!empty($res['success']) && !empty($payload['simulated'])) {
            $simulatedSkipped++;
            continue;
        }

        if (empty($res['success'])) {
            $failed++;
            error_log('pull_campaign_metrics: Zernio HTTP ' . (int)($res['status_code'] ?? 0)
                . ' for campaign ' . $campaign['id'] . ': ' . mb_substr(json_encode($payload) ?: '', 0, 200));
            continue;
        }

        $impressions = (int)($payload['impressions'] ?? 0);
        $clicks      = (int)($payload['clicks'] ?? 0);
        $spend       = round((float)($payload['spend'] ?? 0), 2);

        // Gateway may omit derived fields; derive only from real numbers above.
        $conversions = (int)($payload['conversions'] ?? 0);
        $ctr         = isset($payload['ctr']) ? (float)$payload['ctr'] : ($impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0);
        $cpc         = isset($payload['cpc']) ? (float)$payload['cpc'] : ($clicks > 0 ? round($spend / $clicks, 2) : 0.0);

        try {
            $existing = db()->fetchOne(
                "SELECT id FROM campaign_metrics_daily
                 WHERE campaign_id = ? AND variant_id IS NULL AND date = ?",
                [$campaign['id'], $today]
            );

            $row = [
                'campaign_id' => $campaign['id'],
                'date'        => $today,
                'impressions' => $impressions,
                'clicks'      => $clicks,
                'spend'       => $spend,
                'conversions' => $conversions,
                'ctr'         => $ctr,
                'cpc'         => $cpc,
            ];

            if ($existing) {
                db()->update('campaign_metrics_daily', $row, 'id = ?', [$existing['id']]);
            } else {
                db()->insert('campaign_metrics_daily', $row);
            }
            $written++;
        } catch (\Throwable $e) {
            $failed++;
            error_log("pull_campaign_metrics: write failed for campaign {$campaign['id']}: " . $e->getMessage());
        }
    }

    $note = sprintf(
        'Wrote %d Zernio metric row(s); %d campaign(s) had no usable data; %d run(s) refused (simulated payload, not persisted — set a live Zernio API key for real metrics); %d failed.',
        $written,
        $noData,
        $simulatedSkipped,
        $failed
    );
    if ($written === 0 && $failed === 0) {
        $note .= ' Nothing was invented.';
    }

    finish($logId, $failed > 0 && $written === 0 ? 'failed' : 'success', $note);
    echo "pull_campaign_metrics: {$note}\n";
    exit($failed > 0 && $written === 0 ? 1 : 0);
} catch (\Throwable $e) {
    error_log('pull_campaign_metrics: ' . $e->getMessage());
    if ($logId !== null) {
        try {
            db()->update('cron_run_logs', [
                'finished_at' => date('Y-m-d H:i:s'),
                'status'      => 'failed',
                'notes'       => $e->getMessage(),
            ], 'id = ?', [$logId]);
        } catch (\Throwable $logError) {
            error_log('Failed to log cron error: ' . $logError->getMessage());
        }
    }
    exit(1);
}

function finish(?int $logId, string $status, string $note): void
{
    if ($logId === null) {
        return;
    }
    db()->update('cron_run_logs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status'      => $status,
        'notes'       => $note,
    ], 'id = ?', [$logId]);
}
