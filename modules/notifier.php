<?php declare(strict_types=1);

/**
 * Adsbooster.net — Event Notifier (email-first architecture)
 *
 * One entry point for every lifecycle event the platform cares about:
 * registration, account connection, post scheduling, post publish
 * success/failure, ad launch success/failure, downtime and feature errors.
 *
 * Design rules:
 *  • A notification must never break the feature that triggered it —
 *    every send is wrapped and failures are written to the PHP error log.
 *  • Every user-facing event goes out twice: as a branded email AND as an
 *    in-app notification (the bell), so the user is never left guessing.
 *  • Developer-facing events (downtime, feature errors) go to the platform
 *    owner, configurable via the `developer_alert_email` setting.
 */

require_once __DIR__ . '/email_service.php';

class Notifier
{
    /** Platform owner — receives downtime and feature-error alerts. */
    public const DEVELOPER_EMAIL = 'jmauricennadi@gmail.com';

    /**
     * Events that are always sent to the user (these are the account-critical
     * lifecycle moments; a user who asked for an ad to go live must hear back
     * either way).
     */
    private const USER_EVENTS = [
        'account_registered',
        'account_connected',
        'post_scheduled',
        'post_published',
        'post_failed',
        'ad_launched',
        'ad_published',
        'ad_failed',
    ];

    /**
     * Send a transactional email to a user, and mirror it in-app.
     *
     * @param int    $userId Account the event belongs to.
     * @param string $type   Template key in EmailService (e.g. post_published).
     * @param array  $data   Template placeholders.
     * @return bool  True when the email was handed to the transport.
     */
    public static function user(int $userId, string $type, array $data = []): bool
    {
        if ($userId <= 0 || $type === '') {
            return false;
        }

        try {
            $user = db()->fetchOne("SELECT id, email, full_name FROM users WHERE id = ?", [$userId]);
            if (!$user || empty($user['email'])) {
                return false;
            }

            $data = array_merge([
                'name'  => (string)($user['full_name'] ?: 'there'),
                'email' => (string)$user['email'],
            ], $data);

            // In-app twin so the bell shows the same information.
            self::inApp($userId, $type, (string)($data['subject'] ?? self::titleFor($type)), (string)($data['summary'] ?? ''), (string)($data['link'] ?? ''));

            $res = EmailService::sendTransactional((string)$user['email'], $type, $data);
            if (empty($res['success'])) {
                error_log('Notifier::user failed to send ' . $type . ' to user #' . $userId . ': ' . (string)($res['message'] ?? 'unknown error'));
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            error_log('Notifier::user exception for ' . $type . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an alert to the platform developer (downtime, feature errors).
     */
    public static function developer(string $type, array $data = []): bool
    {
        try {
            // get_setting() lives in the site config; CLI workers (cron, queue
            // worker) may not have loaded it, so fall back to the constant.
            $configured = function_exists('get_setting')
                ? (string)get_setting('developer_alert_email', self::DEVELOPER_EMAIL)
                : '';
            $to = ($configured !== '' ? $configured : self::DEVELOPER_EMAIL);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $to = self::DEVELOPER_EMAIL;
            }

            $siteName = function_exists('get_setting')
                ? (string)get_setting('site_name', 'Adsbooster.net')
                : 'Adsbooster.net';

            $data = array_merge([
                'time'     => date('c'),
                'platform' => $siteName,
            ], $data);

            $res = EmailService::sendTransactional($to, $type, $data);
            if (empty($res['success'])) {
                error_log('Notifier::developer failed to send ' . $type . ': ' . (string)($res['message'] ?? 'unknown error'));
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            error_log('Notifier::developer exception for ' . $type . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Write an in-app notification row (bell menu).
     */
    public static function inApp(int $userId, string $type, string $title, string $body = '', string $link = ''): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            db()->insert('notifications', [
                'user_id'    => $userId,
                'type'       => mb_substr($type, 0, 50),
                'title'      => mb_substr($title, 0, 190),
                'body'       => $body !== '' ? mb_substr($body, 0, 2000) : null,
                'link'       => $link !== '' ? mb_substr($link, 0, 255) : null,
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            error_log('Notifier::inApp failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Human title for the in-app twin of an event.
     */
    private static function titleFor(string $type): string
    {
        return match ($type) {
            'account_registered' => 'Welcome to Adsbooster',
            'account_connected'  => 'Account connected',
            'post_scheduled'     => 'Post scheduled',
            'post_published'     => 'Post published',
            'post_failed'        => 'Post failed to publish',
            'ad_launched'        => 'Ad campaign queued',
            'ad_published'       => 'Ad campaign is live',
            'ad_failed'          => 'Ad campaign failed to launch',
            default              => 'Notification',
        };
    }
}
