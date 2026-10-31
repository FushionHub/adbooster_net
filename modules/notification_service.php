<?php declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * In-App Notification Service Module for Adsbooster.net
 * 
 * Manages creation, retrieval, and management of user notifications.
 */
class NotificationService
{
    /**
     * Create a new notification for a user
     *
     * @param int $userId The user ID
     * @param string $type Notification type (campaign, achievement, system, referral, etc.)
     * @param string $title Notification title
     * @param string $body Notification body text
     * @param string|null $link Optional link to navigate to
     * @return int The created notification ID
     * @throws Exception On database error
     */
    public static function create(int $userId, string $type, string $title, string $body, ?string $link = null, bool $sendEmail = true): int
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "INSERT INTO notifications (user_id, type, title, body, link, is_read, created_at) 
                 VALUES (?, ?, ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$userId, $type, $title, $body, $link]);

            $id = (int) $db->lastInsertId();

            // Email-first: every in-app notification also sends a branded email.
            if ($sendEmail) {
                try {
                    $u = $db->prepare("SELECT email, full_name FROM users WHERE id = ?");
                    $u->execute([$userId]);
                    $row = $u->fetch(PDO::FETCH_ASSOC);
                    if ($row && !empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                        if (!class_exists('EmailService')) {
                            require_once __DIR__ . '/email_service.php';
                        }
                        $safeLink = $link ? '<p><a href="' . htmlspecialchars($link) . '">View in dashboard →</a></p>' : '';
                        EmailService::sendBrandedMail(
                            (string)$row['email'],
                            (string)$title,
                            '<p>Hi ' . htmlspecialchars((string)($row['full_name'] ?? 'there')) . ',</p><p>' . nl2br(htmlspecialchars($body)) . '</p>' . $safeLink
                        );
                    }
                } catch (\Throwable $e) {
                    error_log("NotificationService email fallback failed: " . $e->getMessage());
                }
            }

            return $id;
        } catch (Exception $e) {
            error_log("NotificationService::create Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get unread notification count for a user
     *
     * @param int $userId The user ID
     * @return int Number of unread notifications
     * @throws Exception On database error
     */
    public static function getUnreadCount(int $userId): int
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
            );
            $stmt->execute([$userId]);

            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("NotificationService::getUnreadCount Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get notifications for a user with pagination
     *
     * @param int $userId The user ID
     * @param int $limit Maximum number of notifications to return
     * @return array Array of notification records
     * @throws Exception On database error
     */
    public static function getNotifications(int $userId, int $limit = 20): array
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT id, type, title, body, link, is_read, created_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add relative time for each notification
            foreach ($notifications as &$notification) {
                $notification['time_ago'] = self::getTimeAgo($notification['created_at']);
            }

            return $notifications;
        } catch (Exception $e) {
            error_log("NotificationService::getNotifications Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark a notification as read
     *
     * @param int $notifId The notification ID
     * @return bool True if marked successfully
     * @throws Exception On database error
     */
    public static function markAsRead(int $notifId): bool
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND is_read = 0"
            );
            $stmt->execute([$notifId]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("NotificationService::markAsRead Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark all notifications for a user as read
     *
     * @param int $userId The user ID
     * @return bool True if any notifications were updated
     * @throws Exception On database error
     */
    public static function markAllAsRead(int $userId): bool
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0"
            );
            $stmt->execute([$userId]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("NotificationService::markAllAsRead Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convert datetime to relative time string
     *
     * @param string $datetime The datetime string
     * @return string Human-readable time ago
     */
    private static function getTimeAgo(string $datetime): string
    {
        $now = new DateTime();
        $past = new DateTime($datetime);
        $diff = $now->diff($past);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'Just now';
        }
    }
}
