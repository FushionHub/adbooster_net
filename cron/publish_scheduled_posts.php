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
 * Cron Job: Publish Scheduled Posts (Async Queue Producer)
 * Adsbooster.net
 *
 * ASYNCHRONOUS ROLE: this job no longer performs any gateway call itself.
 * It scans scheduled_posts rows that are due, and enqueues one
 * `bundle_publish_post` job per row into the async queue
 * (subject key 'post:{id}' prevents duplicate dispatches). The actual
 * Bundle.social dispatch — with retries/backoff and gateway-confirmed
 * row updates — is executed exclusively by cron/queue_worker.php.
 *
 * It is a SAFETY NET for rows that were created before the queue existed
 * or whose queue insert failed (the UI enqueues directly on save now).
 * Logs run to cron_run_logs.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/AsyncQueue.php';

$jobName = 'publish_scheduled_posts';
$logId   = null;

try {
    $logId = db()->insert('cron_run_logs', [
        'job_name'   => $jobName,
        'started_at' => date('Y-m-d H:i:s'),
        'status'     => 'running',
    ]);

    $posts = db()->fetchAll(
        "SELECT id, user_id FROM scheduled_posts
         WHERE status = 'scheduled' AND scheduled_time <= NOW()
         ORDER BY scheduled_time ASC
         LIMIT 50"
    );

    $queued = 0;
    $rows   = [];

    foreach ($posts as $post) {
        $postId = (int)$post['id'];
        $jobId = AsyncQueue::push((int)$post['user_id'], 'bundle_publish_post', ['post_id' => $postId], null, 'post:' . $postId);
        if ($jobId > 0) {
            $queued++;
            $rows[] = $jobId;
        } else {
            error_log("publish_scheduled_posts: queue insert failed for post {$postId}");
        }
    }

    $note = sprintf(
        'Found %d due post(s). Queued %d bundle_publish_post job(s) (worker executes them: %s).',
        count($posts),
        $queued,
        implode(',', array_slice($rows, 0, 12))
    );

    db()->update('cron_run_logs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status'      => $queued === 0 && $posts ? 'failed' : 'success',
        'notes'       => $note,
    ], 'id = ?', [$logId]);

    echo "publish_scheduled_posts: {$note}\n";
    exit($queued === 0 && $posts ? 1 : 0);
} catch (\Throwable $e) {
    error_log('publish_scheduled_posts: ' . $e->getMessage());
    if ($logId !== null) {
        try {
            db()->update('cron_run_logs', [
                'finished_at' => date('Y-m-d H:i:s'),
                'status'      => 'failed',
                'notes'       => mb_substr($e->getMessage(), 0, 900),
            ], 'id = ?', [$logId]);
        } catch (\Throwable $ignore) {}
    }
    echo 'publish_scheduled_posts failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
