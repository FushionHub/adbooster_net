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
 * Autonomous AI Media Buyer Background Cron Daemon
 * Run via cron every 15-60 minutes:
 * php /path/to/cron/auto_buyer_daemon.php
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/auto_buyer_engine.php';

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting AI Media Buyer Rule Evaluation Daemon...\n";

try {
    $executed = AutoBuyerEngine::evaluateAllRules();
    $count = count($executed);
    echo "[" . date('Y-m-d H:i:s') . "] Rule execution finished. Processed {$count} rule actions.\n";
    foreach ($executed as $act) {
        echo "  -> Campaign #{$act['campaign_id']}: {$act['details']}\n";
    }
} catch (\Throwable $e) {
    echo "ERROR during evaluation: " . $e->getMessage() . "\n";
}

$elapsed = round(microtime(true) - $startTime, 4);
echo "[" . date('Y-m-d H:i:s') . "] Daemon completed in {$elapsed}s.\n";
