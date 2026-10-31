<?php
declare(strict_types=1);

/**
 * CLI Asynchronous Queue Worker Daemon — Adsbooster.net
 *
 * The ONLY component allowed to talk to the Bundle.social / Zernio gateways
 * directly. Every user-facing HTTP path (dashboard, create-ad, post-creator,
 * REST API v1) enqueues jobs via modules/AsyncQueue.php and answers instantly;
 * this daemon processes them out-of-band with exponential backoff retries.
 *
 * Usage:
 *   php cron/queue_worker.php            # daemon loop (2s poll cadence)
 *   php cron/queue_worker.php --once     # one batch, exit (Task Scheduler mode)
 *   php cron/queue_worker.php --retry-failed [--type=zernio_launch_ad]
 *                                        # re-queue failed sandbox-simulated holds
 *
 * Schedule examples:
 *   Linux   : * * * * * php /path/to/cron/queue_worker.php --once
 *   Windows : every minute run the above command (Task Scheduler)
 */

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Forbidden: cron jobs are CLI-only.' . PHP_EOL;
    exit;
}

require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/AsyncQueue.php';

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    $kv = explode('=', $arg, 2);
    $args[ltrim($kv[0], '-')] = $kv[1] ?? true;
}

$logger = static function (string $line): void {
    echo '[' . date('H:i:s') . '] ' . $line . PHP_EOL;
};

// --retry-failed: re-queue failed sandbox-simulated holds for a re-run.
if (isset($args['retry-failed'])) {
    $jobType = is_string($args['retry-failed'] ?? null) ? $args['retry-failed'] : ($args['type'] ?? null);
    $n = AsyncQueue::retryFailed(is_string($jobType) ? $jobType : null);
    $logger("Re-queued {$n} failed job(s) into the pending queue.");
    // Fall through and also process one batch right away.
}

if (isset($args['once'])) {
    $stats = AsyncQueue::processBatch(10, $logger);
    $logger('Batch done. Claimed: ' . $stats['claimed'] . ' | Completed: ' . $stats['completed'] . ' | Failed/backoff: ' . $stats['failed']);
    exit($stats['failed'] > 0 ? 1 : 0);
}

$logger('Starting Async Queue Daemon (dual gateway out-of-band processor)...');
while (true) {
    $stats = AsyncQueue::processBatch(10, $logger);
    if ($stats['claimed'] > 0) {
        $logger('Batch: claimed=' . $stats['claimed'] . ' completed=' . $stats['completed'] . ' failed=' . $stats['failed']);
    }
    sleep(2); // Rest worker cycle
}
