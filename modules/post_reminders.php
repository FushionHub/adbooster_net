<?php declare(strict_types=1);

/**
 * Post Reminders — Adsbooster.net
 *
 * The content calendar lets each user choose how many reminders they get
 * before a scheduled post goes live (0–5, default 3). Reminders fire on a
 * fixed ladder of offsets before the publish time:
 *
 *      5 reminders → 7 days, 24 h, 3 h, 1 h, 15 min
 *      3 reminders → 24 h, 1 h, 15 min          (the default)
 *      1 reminder  → 24 h
 *      0 reminders → never
 *
 * The ladder is a simple ordered list; "how many reminders" means "send the
 * first N rungs that are still in the future". `reminders_sent` counts how
 * many rungs have already fired, which makes the job idempotent: the
 * every-minute worker can call dispatch() as often as it likes without ever
 * double-sending.
 *
 * Runs exclusively inside cron/queue_worker.php (CLI), never in a request.
 */

class PostReminders
{
    /** Ordered reminder ladder: minutes-before → label. */
    public const LADDER = [
        10080 => '1 week',
        1440  => '24 hours',
        180   => '3 hours',
        60    => '1 hour',
        15    => '15 minutes',
    ];

    /**
     * Scan due reminders and send them. Called once per worker cycle.
     *
     * @return int number of reminders actually sent
     */
    public static function dispatch(): int
    {
        $sent = 0;

        // Posts scheduled within the next week whose reminders are not done.
        $posts = db()->fetchAll(
            "SELECT id, user_id, platform, caption, scheduled_time, reminder_count, reminders_sent
             FROM scheduled_posts
             WHERE status = 'scheduled'
               AND reminder_count IS NOT NULL AND reminder_count > 0
               AND reminders_sent < reminder_count
               AND scheduled_time > NOW()
               AND scheduled_time <= DATE_ADD(NOW(), INTERVAL 8 DAY)
             LIMIT 100"
        );

        foreach ($posts as $post) {
            $sent += self::remindRow($post);
        }

        return $sent;
    }

    /**
     * Send every not-yet-sent reminder rung that is currently due for one row.
     *
     * @param array<string,mixed> $post
     */
    private static function remindRow(array $post): int
    {
        $postId   = (int)$post['id'];
        $userId   = (int)$post['user_id'];
        $want     = (int)$post['reminder_count'];
        $already  = (int)($post['reminders_sent'] ?? 0);
        $publishTs = strtotime((string)$post['scheduled_time']);
        if ($publishTs === false) {
            return 0;
        }

        $rungs = array_slice(array_keys(self::LADDER), 0, $want);
        $now   = time();
        $fired = 0;

        foreach ($rungs as $index => $minutesBefore) {
            if ($index < $already) {
                continue; // already sent on an earlier cycle
            }
            $dueAt = $publishTs - ($minutesBefore * 60);
            if ($now < $dueAt) {
                break; // this rung — and all later rungs — are not due yet
            }

            try {
                require_once __DIR__ . '/notifier.php';
                Notifier::user($userId, 'post_reminder', [
                    'platform'       => ucfirst((string)$post['platform']),
                    'reminder_for'   => self::LADDER[$minutesBefore],
                    'scheduled_time' => date('D d M Y, H:i', $publishTs),
                    'caption'        => mb_substr(strip_tags((string)$post['caption']), 0, 400),
                    'reminder_no'    => $index + 1,
                    'reminder_total' => $want,
                    'link'           => (defined('BASE_URL') ? BASE_URL : '') . '/calendar',
                ]);
                $fired++;
            } catch (\Throwable $e) {
                error_log("Post reminder email failed for post {$postId}: " . $e->getMessage());
                break; // stop this row on the first transport failure; retried next cycle
            }
        }

        if ($fired > 0) {
            try {
                db()->update('scheduled_posts', ['reminders_sent' => $already + $fired], 'id = ?', [$postId]);
            } catch (\Throwable $e) {
                error_log("Post reminder counter update failed for post {$postId}: " . $e->getMessage());
                return 0; // do not double-send next cycle
            }
        }

        return $fired;
    }
}
