<?php declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Gamification & Achievement Service Module for Adsbooster.net
 * 
 * Manages user achievements, badges, and milestone tracking.
 */
class AchievementService
{
    /**
     * Check and award achievements for a user based on their activity
     *
     * @param int $userId The user ID
     * @return void
     * @throws Exception On database error
     */
    public static function checkAndAward(int $userId): void
    {
        try {
            $db = db();

            // Get all available achievements
            $stmt = $db->query("SELECT * FROM achievements ORDER BY id");
            $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get user's already earned achievements
            $stmt = $db->prepare(
                "SELECT achievement_id FROM user_achievements WHERE user_id = ?"
            );
            $stmt->execute([$userId]);
            $earned = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $earned = array_map('intval', $earned);

            foreach ($achievements as $achievement) {
                if (in_array((int) $achievement['id'], $earned)) {
                    continue; // Already earned
                }

                if (self::checkAchievement($userId, $achievement['slug'])) {
                    self::awardAchievement($userId, (int) $achievement['id']);
                }
            }
        } catch (Exception $e) {
            error_log("AchievementService::checkAndAward Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get achievements earned by a user
     *
     * @param int $userId The user ID
     * @return array Array of earned achievements with details
     * @throws Exception On database error
     */
    public static function getUserAchievements(int $userId): array
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT a.id, a.name, a.slug, a.description, a.icon, a.points,
                        ua.earned_at
                 FROM user_achievements ua
                 JOIN achievements a ON ua.achievement_id = a.id
                 WHERE ua.user_id = ?
                 ORDER BY ua.earned_at DESC"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AchievementService::getUserAchievements Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all available achievements
     *
     * @return array Array of all achievements
     * @throws Exception On database error
     */
    public static function getAllAchievements(): array
    {
        try {
            $db = db();

            $stmt = $db->query(
                "SELECT id, name, slug, description, icon, points 
                 FROM achievements 
                 ORDER BY points ASC, name ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AchievementService::getAllAchievements Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a specific achievement condition is met
     *
     * @param int $userId The user ID
     * @param string $slug Achievement slug identifier
     * @return bool True if condition is met
     */
    private static function checkAchievement(int $userId, string $slug): bool
    {
        try {
            $db = db();

            switch ($slug) {
                case 'first_campaign':
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) FROM ad_campaigns WHERE user_id = ? AND status != 'draft'"
                    );
                    $stmt->execute([$userId]);
                    return $stmt->fetchColumn() >= 1;

                case 'first_combo_pack':
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) FROM combo_purchases WHERE user_id = ? AND status = 'completed'"
                    );
                    $stmt->execute([$userId]);
                    return $stmt->fetchColumn() >= 1;

                case 'ten_posts_scheduled':
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) FROM scheduled_posts WHERE user_id = ? AND status IN ('scheduled', 'published')"
                    );
                    $stmt->execute([$userId]);
                    return $stmt->fetchColumn() >= 10;

                case 'first_referral_reward':
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'completed'"
                    );
                    $stmt->execute([$userId]);
                    return $stmt->fetchColumn() >= 1;

                case 'verified_seller':
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) FROM seller_verifications WHERE user_id = ? AND status = 'verified'"
                    );
                    $stmt->execute([$userId]);
                    return $stmt->fetchColumn() >= 1;

                case 'policy_scan_streak':
                    // Check for 7 consecutive days of policy scans
                    $stmt = $db->prepare(
                        "SELECT COUNT(DISTINCT DATE(scanned_at)) as scan_days
                         FROM policy_scan_logs
                         WHERE user_id = ?
                           AND scanned_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                         HAVING scan_days >= 7"
                    );
                    $stmt->execute([$userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result && (int) $result['scan_days'] >= 7;

                case 'campaign_master':
                    // 10 successful campaigns
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) FROM ad_campaigns 
                         WHERE user_id = ? AND status = 'completed' AND winning_variant_id IS NOT NULL"
                    );
                    $stmt->execute([$userId]);
                    return $stmt->fetchColumn() >= 10;

                case 'budget_optimizer':
                    // Maintain CPC below $0.50 for 5 campaigns
                    $stmt = $db->prepare(
                        "SELECT COUNT(DISTINCT c.id)
                         FROM ad_campaigns c
                         JOIN campaign_metrics_daily m ON c.id = m.campaign_id
                         WHERE c.user_id = ?
                         GROUP BY c.id
                         HAVING AVG(CASE WHEN m.clicks > 0 THEN m.spend / m.clicks ELSE 999 END) < 0.50
                         LIMIT 1 OFFSET 4"
                    );
                    $stmt->execute([$userId]);
                    return $stmt->fetch() !== false;

                case 'viral_referrer':
                    // 25 successful referrals
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'completed'"
                    );
                    $stmt->execute([$userId]);
                    return $stmt->fetchColumn() >= 25;

                default:
                    return false;
            }
        } catch (Exception $e) {
            error_log("AchievementService::checkAchievement Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Award an achievement to a user
     *
     * @param int $userId The user ID
     * @param int $achievementId The achievement ID
     * @return void
     * @throws Exception On database error
     */
    private static function awardAchievement(int $userId, int $achievementId): void
    {
        try {
            $db = db();

            $db->beginTransaction();

            // Record the achievement
            $stmt = $db->prepare(
                "INSERT IGNORE INTO user_achievements (user_id, achievement_id, earned_at) 
                 VALUES (?, ?, NOW())"
            );
            $stmt->execute([$userId, $achievementId]);

            // Award points to user
            $stmt = $db->prepare(
                "SELECT points FROM achievements WHERE id = ?"
            );
            $stmt->execute([$achievementId]);
            $points = $stmt->fetchColumn();

            if ($points) {
                $stmt = $db->prepare(
                    "UPDATE users SET achievement_points = COALESCE(achievement_points, 0) + ? WHERE id = ?"
                );
                $stmt->execute([$points, $userId]);
            }

            // Get achievement details for notification
            $stmt = $db->prepare(
                "SELECT name, description FROM achievements WHERE id = ?"
            );
            $stmt->execute([$achievementId]);
            $achievement = $stmt->fetch(PDO::FETCH_ASSOC);

            // Create notification
            if ($achievement) {
                NotificationService::create(
                    $userId,
                    'achievement',
                    'Achievement Unlocked!',
                    "You've earned the \"{$achievement['name']}\" badge! {$achievement['description']}",
                    '/achievements'
                );
            }

            $db->commit();
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("AchievementService::awardAchievement Error: " . $e->getMessage());
            throw $e;
        }
    }
}
