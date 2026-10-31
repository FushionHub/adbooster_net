<?php declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * A/B Split Testing Module for Adsbooster.net
 * 
 * Manages campaign variant creation, performance tracking,
 * and automatic winner declaration with budget reallocation.
 */
class ABTest
{
    /**
     * Create A/B test variants for a campaign
     *
     * @param int $campaignId The campaign ID
     * @param array $variants Array of variant data (headline, description, media_url, budget_share)
     * @return int Number of variants created
     * @throws Exception On database error
     */
    public static function createTest(int $campaignId, array $variants): int
    {
        try {
            $db = db();
            $created = 0;

            foreach ($variants as $variant) {
                $stmt = $db->prepare(
                    "INSERT INTO ad_campaign_variants 
                     (campaign_id, variant_name, headline, description, media_url, budget_share, status) 
                     VALUES (?, ?, ?, ?, ?, ?, 'active')"
                );
                $stmt->execute([
                    $campaignId,
                    $variant['variant_name'] ?? 'Variant ' . ($created + 1),
                    $variant['headline'] ?? '',
                    $variant['description'] ?? '',
                    $variant['media_url'] ?? null,
                    $variant['budget_share'] ?? (100 / count($variants))
                ]);
                $created++;
            }

            return $created;
        } catch (Exception $e) {
            error_log("ABTest::createTest Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get performance results for all variants of a campaign
     *
     * @param int $campaignId The campaign ID
     * @return array Array of variant performance data with metrics
     * @throws Exception On database error
     */
    public static function getTestResults(int $campaignId): array
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT v.id, v.variant_name, v.headline, v.description, v.budget_share, v.status,
                        COALESCE(SUM(m.impressions), 0) as impressions,
                        COALESCE(SUM(m.clicks), 0) as clicks,
                        COALESCE(SUM(m.conversions), 0) as conversions,
                        COALESCE(SUM(m.spend), 0) as spend,
                        CASE 
                            WHEN COALESCE(SUM(m.impressions), 0) > 0 
                            THEN ROUND((COALESCE(SUM(m.clicks), 0) / SUM(m.impressions)) * 100, 2)
                            ELSE 0 
                        END as ctr,
                        CASE 
                            WHEN COALESCE(SUM(m.clicks), 0) > 0 
                            THEN ROUND(COALESCE(SUM(m.spend), 0) / SUM(m.clicks), 2)
                            ELSE 0 
                        END as cpc,
                        CASE 
                            WHEN COALESCE(SUM(m.clicks), 0) > 0 
                            THEN ROUND((COALESCE(SUM(m.conversions), 0) / SUM(m.clicks)) * 100, 2)
                            ELSE 0 
                        END as conversion_rate
                 FROM ad_campaign_variants v
                 LEFT JOIN campaign_metrics_daily m ON v.id = m.variant_id
                 WHERE v.campaign_id = ?
                 GROUP BY v.id, v.variant_name, v.headline, v.description, v.budget_share, v.status
                 ORDER BY ctr DESC"
            );
            $stmt->execute([$campaignId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ABTest::getTestResults Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Declare a winner based on CTR and reallocate budget
     *
     * @param int $campaignId The campaign ID
     * @return int The winning variant ID
     * @throws Exception On database error or if no variants exist
     */
    public static function declareWinner(int $campaignId): int
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT v.id, v.variant_name,
                        COALESCE(SUM(m.impressions), 0) as impressions,
                        COALESCE(SUM(m.clicks), 0) as clicks,
                        CASE 
                            WHEN COALESCE(SUM(m.impressions), 0) > 0 
                            THEN ROUND((COALESCE(SUM(m.clicks), 0) / SUM(m.impressions)) * 100, 2)
                            ELSE 0 
                        END as ctr
                 FROM ad_campaign_variants v
                 LEFT JOIN campaign_metrics_daily m ON v.id = m.variant_id
                 WHERE v.campaign_id = ? AND v.status = 'active'
                 GROUP BY v.id, v.variant_name
                 HAVING impressions >= 100
                 ORDER BY ctr DESC
                 LIMIT 1"
            );
            $stmt->execute([$campaignId]);
            $winner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$winner) {
                throw new Exception("No eligible variants found for campaign {$campaignId}");
            }

            $db->beginTransaction();

            // Deactivate all variants
            $stmt = $db->prepare(
                "UPDATE ad_campaign_variants SET status = 'inactive' WHERE campaign_id = ?"
            );
            $stmt->execute([$campaignId]);

            // Mark winner
            $stmt = $db->prepare(
                "UPDATE ad_campaign_variants SET status = 'winner', budget_share = 100 WHERE id = ?"
            );
            $stmt->execute([$winner['id']]);

            // Update campaign to use winning variant
            $stmt = $db->prepare(
                "UPDATE ad_campaigns SET winning_variant_id = ? WHERE id = ?"
            );
            $stmt->execute([$winner['id'], $campaignId]);

            $db->commit();

            return (int) $winner['id'];
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("ABTest::declareWinner Error: " . $e->getMessage());
            throw $e;
        }
    }
}
