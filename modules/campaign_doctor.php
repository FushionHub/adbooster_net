<?php declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * AI Campaign Doctor Module for Adsbooster.net
 * 
 * Analyzes campaigns for performance issues and provides
 * actionable recommendations for improvement.
 */
class CampaignDoctor
{
    /**
     * Analyze a campaign and generate recommendations
     *
     * @param int $campaignId The campaign ID to analyze
     * @return array Analysis results with recommendations
     * @throws Exception On database error
     */
    public static function analyzeCampaign(int $campaignId): array
    {
        try {
            $db = db();

            // Get campaign details
            $stmt = $db->prepare(
                "SELECT c.*, u.id as user_id
                 FROM ad_campaigns c
                 JOIN users u ON c.user_id = u.id
                 WHERE c.id = ?"
            );
            $stmt->execute([$campaignId]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$campaign) {
                throw new Exception("Campaign not found: {$campaignId}");
            }

            // Get recent metrics (last 7 days)
            $stmt = $db->prepare(
                "SELECT 
                    date,
                    impressions,
                    clicks,
                    conversions,
                    spend,
                    CASE 
                        WHEN impressions > 0 
                        THEN ROUND((clicks / impressions) * 100, 2)
                        ELSE 0 
                    END as ctr,
                    CASE 
                        WHEN clicks > 0 
                        THEN ROUND(spend / clicks, 2)
                        ELSE 0 
                    END as cpc
                 FROM campaign_metrics_daily
                 WHERE campaign_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                 ORDER BY date ASC"
            );
            $stmt->execute([$campaignId]);
            $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get previous week metrics for comparison
            $stmt = $db->prepare(
                "SELECT 
                    AVG(CASE 
                        WHEN impressions > 0 
                        THEN (clicks / impressions) * 100
                        ELSE 0 
                    END) as avg_ctr,
                    AVG(CASE 
                        WHEN clicks > 0 
                        THEN spend / clicks
                        ELSE 0 
                    END) as avg_cpc,
                    SUM(impressions) as total_impressions,
                    SUM(spend) as total_spend
                 FROM campaign_metrics_daily
                 WHERE campaign_id = ? 
                   AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                   AND date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            );
            $stmt->execute([$campaignId]);
            $prevWeek = $stmt->fetch(PDO::FETCH_ASSOC);

            $recommendations = [];

            if (!empty($metrics)) {
                // Calculate current week averages
                $currentCtr = array_sum(array_column($metrics, 'ctr')) / count($metrics);
                $currentCpc = array_sum(array_column($metrics, 'cpc')) / count($metrics);
                $totalSpend = array_sum(array_column($metrics, 'spend'));

                // Check for rising CPC
                if ($prevWeek && $prevWeek['avg_cpc'] > 0) {
                    $cpcChange = (($currentCpc - $prevWeek['avg_cpc']) / $prevWeek['avg_cpc']) * 100;
                    if ($cpcChange > 20) {
                        $recommendations[] = self::createRecommendation(
                            $campaignId,
                            $campaign['user_id'],
                            'rising_cpc',
                            'critical',
                            'Rising CPC Detected',
                            "Your CPC has increased by " . round($cpcChange, 1) . "% compared to last week ($" . number_format($currentCpc, 2) . " vs $" . number_format($prevWeek['avg_cpc'], 2) . ").",
                            'Review ad creatives, test new audiences, or adjust bid strategy to reduce costs.'
                        );
                    }
                }

                // Check for falling CTR
                if ($prevWeek && $prevWeek['avg_ctr'] > 0) {
                    $ctrChange = (($currentCtr - $prevWeek['avg_ctr']) / $prevWeek['avg_ctr']) * 100;
                    if ($ctrChange < -15) {
                        $recommendations[] = self::createRecommendation(
                            $campaignId,
                            $campaign['user_id'],
                            'falling_ctr',
                            'warning',
                            'CTR Declining',
                            "Your CTR has dropped by " . round(abs($ctrChange), 1) . "% compared to last week.",
                            'Refresh your ad creatives, update headlines, or test different audience segments.'
                        );
                    }
                }

                // Check for ad fatigue (declining performance over time)
                if (count($metrics) >= 5) {
                    $firstHalf = array_slice($metrics, 0, floor(count($metrics) / 2));
                    $secondHalf = array_slice($metrics, floor(count($metrics) / 2));
                    $firstHalfCtr = array_sum(array_column($firstHalf, 'ctr')) / count($firstHalf);
                    $secondHalfCtr = array_sum(array_column($secondHalf, 'ctr')) / count($secondHalf);

                    if ($firstHalfCtr > 0 && $secondHalfCtr < $firstHalfCtr * 0.7) {
                        $recommendations[] = self::createRecommendation(
                            $campaignId,
                            $campaign['user_id'],
                            'ad_fatigue',
                            'warning',
                            'Potential Ad Fatigue',
                            'CTR has declined significantly over the past week, suggesting audience fatigue.',
                            'Create new ad variations or expand to new audience segments.'
                        );
                    }
                }

                // Budget exhaustion check
                if ($campaign['budget'] > 0) {
                    $dailyBudget = $campaign['budget'] / max(1, $campaign['duration_days'] ?? 7);
                    $avgDailySpend = $totalSpend / 7;
                    $daysRemaining = ($campaign['budget'] - $totalSpend) / max(1, $avgDailySpend);

                    if ($daysRemaining < 2 && $totalSpend < $campaign['budget'] * 0.8) {
                        $recommendations[] = self::createRecommendation(
                            $campaignId,
                            $campaign['user_id'],
                            'budget_exhaustion',
                            'critical',
                            'Budget Depleting Rapidly',
                            "At current spend rate, your budget will run out in approximately " . round($daysRemaining, 1) . " days.",
                            'Consider increasing budget or reducing bids to extend campaign duration.'
                        );
                    }
                }

                // Low conversion rate
                $totalConversions = array_sum(array_column($metrics, 'conversions'));
                $totalClicks = array_sum(array_column($metrics, 'clicks'));
                if ($totalClicks > 0) {
                    $conversionRate = ($totalConversions / $totalClicks) * 100;
                    if ($conversionRate < 1 && $totalClicks > 100) {
                        $recommendations[] = self::createRecommendation(
                            $campaignId,
                            $campaign['user_id'],
                            'low_conversions',
                            'warning',
                            'Low Conversion Rate',
                            "Your conversion rate is " . round($conversionRate, 2) . "%, which is below industry average.",
                            'Review your landing page experience and ensure ad messaging matches the landing page.'
                        );
                    }
                }
            } else {
                $recommendations[] = self::createRecommendation(
                    $campaignId,
                    $campaign['user_id'],
                    'no_data',
                    'info',
                    'Insufficient Data',
                    'Not enough data collected yet to provide recommendations.',
                    'Allow at least 3-5 days of data collection for accurate analysis.'
                );
            }

            return [
                'campaign_id' => $campaignId,
                'status' => $campaign['status'],
                'budget' => $campaign['budget'],
                'metrics_period' => '7_days',
                'recommendations' => $recommendations,
                'overall_health' => self::calculateHealthScore($recommendations),
                'analyzed_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("CampaignDoctor::analyzeCampaign Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Apply a recommendation by updating campaign settings
     *
     * @param int $recId The recommendation ID
     * @return bool True if applied successfully
     * @throws Exception On database error
     */
    public static function applyRecommendation(int $recId): bool
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT * FROM campaign_recommendations WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$recId]);
            $rec = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rec) {
                return false;
            }

            $db->beginTransaction();

            // Mark recommendation as applied
            $stmt = $db->prepare(
                "UPDATE campaign_recommendations SET status = 'applied', applied_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$recId]);

            // Apply specific actions based on recommendation type
            switch ($rec['type']) {
                case 'rising_cpc':
                    // Reduce bid by 10%
                    $stmt = $db->prepare(
                        "UPDATE ad_campaigns SET bid_amount = bid_amount * 0.9 WHERE id = ?"
                    );
                    $stmt->execute([$rec['campaign_id']]);
                    break;

                case 'budget_exhaustion':
                    // Pause campaign to prevent overspend
                    $stmt = $db->prepare(
                        "UPDATE ad_campaigns SET status = 'paused' WHERE id = ? AND status = 'active'"
                    );
                    $stmt->execute([$rec['campaign_id']]);
                    break;

                case 'ad_fatigue':
                    // Reset ad rotation
                    $stmt = $db->prepare(
                        "UPDATE ad_campaigns SET ad_rotation = 'optimal' WHERE id = ?"
                    );
                    $stmt->execute([$rec['campaign_id']]);
                    break;
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("CampaignDoctor::applyRecommendation Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Dismiss a recommendation
     *
     * @param int $recId The recommendation ID
     * @return bool True if dismissed successfully
     * @throws Exception On database error
     */
    public static function dismissRecommendation(int $recId): bool
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "UPDATE campaign_recommendations SET status = 'dismissed' WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$recId]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("CampaignDoctor::dismissRecommendation Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all recommendations for a user
     *
     * @param int $userId The user ID
     * @return array Array of recommendations across all campaigns
     * @throws Exception On database error
     */
    public static function getRecommendations(int $userId): array
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT r.*, c.campaign_name
                 FROM campaign_recommendations r
                 JOIN ad_campaigns c ON r.campaign_id = c.id
                 WHERE c.user_id = ?
                 ORDER BY 
                    CASE r.severity 
                        WHEN 'critical' THEN 1 
                        WHEN 'warning' THEN 2 
                        WHEN 'info' THEN 3 
                    END,
                    r.created_at DESC
                 LIMIT 50"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("CampaignDoctor::getRecommendations Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a recommendation record
     *
     * @param int $campaignId The campaign ID
     * @param int $userId The user ID
     * @param string $type Recommendation type
     * @param string $severity Severity level (critical, warning, info)
     * @param string $title Recommendation title
     * @param string $description Detailed description
     * @param string $action Suggested action
     * @return int The created recommendation ID
     */
    private static function createRecommendation(
        int $campaignId,
        int $userId,
        string $type,
        string $severity,
        string $title,
        string $description,
        string $action
    ): int {
        $db = db();

        $stmt = $db->prepare(
            "INSERT INTO campaign_recommendations 
             (campaign_id, user_id, type, severity, title, description, suggested_action, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([$campaignId, $userId, $type, $severity, $title, $description, $action]);

        return (int) $db->lastInsertId();
    }

    /**
     * Calculate overall health score based on recommendations
     *
     * @param array $recommendations Array of recommendations
     * @return string Health status (excellent, good, fair, poor, critical)
     */
    private static function calculateHealthScore(array $recommendations): string
    {
        if (empty($recommendations)) {
            return 'excellent';
        }

        $severityScores = [
            'critical' => 3,
            'warning' => 2,
            'info' => 1
        ];

        $totalScore = 0;
        foreach ($recommendations as $rec) {
            $totalScore += $severityScores[$rec['severity']] ?? 0;
        }

        if ($totalScore >= 6) return 'critical';
        if ($totalScore >= 4) return 'poor';
        if ($totalScore >= 2) return 'fair';
        return 'good';
    }
}
