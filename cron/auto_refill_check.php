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
 * Cron Job: Auto Refill Check
 * Adsbooster.net
 *
 * Checks users with auto_refill_enabled = 1.
 * If ad_credits < auto_refill_threshold, triggers payment.
 * Logs run to cron_run_logs.
 */

require_once __DIR__ . '/../config/db.php';

$jobName = 'auto_refill_check';
$logId = null;

try {
    $startedAt = date('Y-m-d H:i:s');
    $logId = db()->insert('cron_run_logs', [
        'job_name'   => $jobName,
        'started_at' => $startedAt,
        'status'     => 'running',
    ]);

    $users = db()->fetchAll(
        "SELECT id, full_name, email, ad_credits, auto_refill_threshold,
                auto_refill_amount, auto_refill_gateway
         FROM users
         WHERE auto_refill_enabled = 1
           AND status = 'active'
         ORDER BY id ASC"
    );

    $checked = 0;
    $triggered = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($users as $user) {
        try {
            $checked++;
            $currentCredits = (float) $user['ad_credits'];
            $threshold = (float) $user['auto_refill_threshold'];
            $refillAmount = (float) $user['auto_refill_amount'];
            $gateway = $user['auto_refill_gateway'] ?: 'paystack';

            if ($currentCredits >= $threshold) {
                $skipped++;
                continue;
            }

            if ($refillAmount <= 0) {
                $failed++;
                db()->insert('campaign_run_logs', [
                    'campaign_id' => 0,
                    'action'      => 'auto_refill_invalid_amount',
                    'details'     => json_encode(['user_id' => $user['id'], 'amount' => $refillAmount]),
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            $existingPending = db()->fetchOne(
                "SELECT id FROM transactions
                 WHERE user_id = ? AND type = 'ad_credit_topup' AND status = 'pending'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [$user['id']]
            );

            if ($existingPending) {
                $skipped++;
                continue;
            }

            $currency = 'USD';
            $reference = strtoupper('AR-' . bin2hex(random_bytes(8)));

            $transactionId = db()->insert('transactions', [
                'user_id'        => $user['id'],
                'gateway'        => $gateway,
                'type'           => 'ad_credit_topup',
                'amount'         => $refillAmount,
                'currency'       => $currency,
                'status'         => 'pending',
                'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                'gateway_reference' => $reference,
                'metadata'       => json_encode([
                    'auto_refill' => true,
                    'triggered_by' => 'cron',
                    'threshold'   => $threshold,
                    'current'     => $currentCredits,
                ]),
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            db()->insert('campaign_run_logs', [
                'campaign_id' => 0,
                'action'      => 'auto_refill_triggered',
                'details'     => json_encode([
                    'user_id'        => $user['id'],
                    'email'          => $user['email'],
                    'amount'         => $refillAmount,
                    'gateway'        => $gateway,
                    'transaction_id' => $transactionId,
                    'reason'         => "Credits ({$currentCredits}) below threshold ({$threshold})",
                ]),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            $triggered++;
        } catch (\Throwable $e) {
            error_log("auto_refill_check: Failed user {$user['id']}: " . $e->getMessage());
            $failed++;
        }
    }

    $finishedAt = date('Y-m-d H:i:s');
    db()->update('cron_run_logs', [
        'finished_at' => $finishedAt,
        'status'      => 'success',
        'notes'       => "Checked: {$checked}, Triggered: {$triggered}, Skipped: {$skipped}, Failed: {$failed}",
    ], 'id = ?', [$logId]);

    echo "auto_refill_check completed. Checked: {$checked}, Triggered: {$triggered}\n";

} catch (\Throwable $e) {
    error_log("auto_refill_check: " . $e->getMessage());
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
