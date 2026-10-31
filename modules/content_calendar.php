<?php declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Content Calendar Module for Adsbooster.net
 * 
 * Combines scheduled posts and ad campaigns into a unified
 * calendar view with rescheduling capabilities.
 */
class ContentCalendar
{
    /**
     * Get calendar events for a user within a date range
     *
     * @param int $userId The user ID
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Combined array of scheduled posts and campaign events
     * @throws Exception On database error
     */
    public static function getEvents(int $userId, string $startDate, string $endDate): array
    {
        try {
            $db = db();
            $events = [];

            // Get scheduled posts
            $stmt = $db->prepare(
                "SELECT 
                    'post' as event_type,
                    sp.id,
                    sp.title,
                    sp.content as description,
                    sp.scheduled_time as start_time,
                    sp.platform,
                    sp.status,
                    sp.media_url
                 FROM scheduled_posts sp
                 WHERE sp.user_id = ?
                   AND sp.scheduled_time BETWEEN ? AND ?
                   AND sp.status IN ('scheduled', 'published', 'pending')
                 ORDER BY sp.scheduled_time ASC"
            );
            $stmt->execute([$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($posts as &$post) {
                $post['color'] = self::getPlatformColor($post['platform']);
                $post['icon'] = 'post';
            }
            $events = array_merge($events, $posts);

            // Get campaign events (start dates and any scheduled changes)
            $stmt = $db->prepare(
                "SELECT 
                    'campaign' as event_type,
                    c.id,
                    c.campaign_name as title,
                    CONCAT('Campaign: ', c.status) as description,
                    c.created_at as start_time,
                    c.campaign_type,
                    c.status,
                    NULL as media_url
                 FROM ad_campaigns c
                 WHERE c.user_id = ?
                   AND c.created_at BETWEEN ? AND ?
                   AND c.status IN ('active', 'pending', 'paused')
                 ORDER BY c.created_at ASC"
            );
            $stmt->execute([$userId, $startDate, $endDate]);
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($campaigns as &$campaign) {
                $campaign['color'] = self::getPlatformColor($campaign['campaign_type']);
                $campaign['icon'] = 'campaign';
            }
            $events = array_merge($events, $campaigns);

            // Sort all events by start_time
            usort($events, function ($a, $b) {
                return strtotime($a['start_time']) - strtotime($b['start_time']);
            });

            return $events;
        } catch (Exception $e) {
            error_log("ContentCalendar::getEvents Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reschedule a post to a new time
     *
     * @param int $postId The scheduled post ID
     * @param string $newTime New scheduled time (Y-m-d H:i:s format)
     * @return bool True if rescheduled successfully
     * @throws Exception On database error
     */
    public static function rescheduleEvent(int $postId, string $newTime): bool
    {
        try {
            $db = db();

            // Validate the new time format
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $newTime);
            if (!$dateTime) {
                throw new Exception("Invalid datetime format. Expected: Y-m-d H:i:s");
            }

            // Don't allow rescheduling to past times
            if ($dateTime < new DateTime()) {
                throw new Exception("Cannot reschedule to a past time");
            }

            $stmt = $db->prepare(
                "UPDATE scheduled_posts 
                 SET scheduled_time = ?, 
                     updated_at = NOW(),
                     status = 'scheduled'
                 WHERE id = ? AND status IN ('scheduled', 'pending')"
            );
            $stmt->execute([$newTime, $postId]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("ContentCalendar::rescheduleEvent Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get platform color mapping for calendar display
     *
     * @return array Associative array of platform => color codes
     */
    public static function getEventColors(): array
    {
        return [
            'meta' => '#1877F2',
            'instagram' => '#E4405F',
            'tiktok' => '#000000',
            'google_ads' => '#4285F4',
            'snapchat' => '#FFFC00',
            'google_calendar' => '#4285F4',
            'campaign' => '#8B5CF6',
            'post' => '#10B981',
            'default' => '#6B7280'
        ];
    }

    /**
     * Get color for a specific platform
     *
     * @param string $platform Platform name
     * @return string Hex color code
     */
    private static function getPlatformColor(string $platform): string
    {
        $colors = self::getEventColors();
        return $colors[strtolower($platform)] ?? '#6B7280';
    }
}
