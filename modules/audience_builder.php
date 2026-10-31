<?php declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Smart Audience Builder Module for Adsbooster.net
 * 
 * Manages audience segments, lookalike suggestions,
 * and audience targeting criteria.
 */
class AudienceBuilder
{
    /**
     * Create a new audience segment
     *
     * @param int $userId The user ID
     * @param string $name Segment name
     * @param array $criteria Targeting criteria (age_min, age_max, locations, interests, etc.)
     * @return int The created segment ID
     * @throws Exception On database error
     */
    public static function createSegment(int $userId, string $name, array $criteria): int
    {
        try {
            $db = db();
            $json = json_encode($criteria);

            $stmt = $db->prepare(
                "INSERT INTO audience_segments (user_id, name, criteria, criteria_json, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId,
                $name,
                $json,
                $json
            ]);

            return (int) $db->lastInsertId();
        } catch (Exception $e) {
            error_log("AudienceBuilder::createSegment Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all segments for a user
     *
     * @param int $userId The user ID
     * @return array Array of audience segments
     * @throws Exception On database error
     */
    public static function getSegments(int $userId): array
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT id, name, COALESCE(criteria_json, criteria) as criteria_json, created_at 
                 FROM audience_segments 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC"
            );
            $stmt->execute([$userId]);
            $segments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($segments as &$segment) {
                $segment['criteria'] = json_decode($segment['criteria_json'] ?? '{}', true) ?: [];
            }

            return $segments;
        } catch (Exception $e) {
            error_log("AudienceBuilder::getSegments Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Suggest a lookalike audience based on best campaign's performance
     *
     * @param int $userId The user ID
     * @param int $bestCampaignId The campaign ID with best performance
     * @return array Suggested targeting criteria with reasoning
     * @throws Exception On database error
     */
    public static function suggestLookalike(int $userId, int $bestCampaignId): array
    {
        try {
            $db = db();

            // Get campaign metrics
            $stmt = $db->prepare(
                "SELECT 
                    c.id,
                    c.targeting_spec,
                    c.campaign_type,
                    c.headline,
                    c.primary_text
                 FROM ad_campaigns c
                 WHERE c.id = ? AND c.user_id = ?"
            );
            $stmt->execute([$bestCampaignId, $userId]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$campaign) {
                return [
                    'success' => false,
                    'age_min' => 20,
                    'age_max' => 55,
                    'interests' => 'Broad Marketing, Growth Enthusiasts',
                    'geo' => 'Global',
                    'message' => 'Campaign not found'
                ];
            }

            $targetAudience = json_decode($campaign['targeting_spec'] ?? '{}', true) ?: [];

            $ageMin = max(18, (int)($targetAudience['age_min'] ?? 18) - 2);
            $ageMax = min(65, (int)($targetAudience['age_max'] ?? 55) + 5);
            $interests = !empty($targetAudience['interests']) ? $targetAudience['interests'] . ', Lookalike Cohort, High Spenders' : 'E-commerce, Digital Marketing, Tech Founders';
            $geo = $targetAudience['geo'] ?? $targetAudience['locations'] ?? 'Global';
            if (is_array($geo)) $geo = implode(', ', $geo);

            return [
                'success' => true,
                'age_min' => $ageMin,
                'age_max' => $ageMax,
                'interests' => $interests,
                'geo' => $geo,
                'platform' => $campaign['campaign_type'],
                'message' => 'Lookalike generated successfully'
            ];
        } catch (Exception $e) {
            error_log("AudienceBuilder::suggestLookalike Error: " . $e->getMessage());
            return [
                'success' => false,
                'age_min' => 20,
                'age_max' => 55,
                'interests' => 'Tech, Marketing, Scale',
                'geo' => 'Global',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete an audience segment
     *
     * @param int $segmentId The segment ID
     * @return bool True if deleted successfully
     * @throws Exception On database error
     */
    public static function deleteSegment(int $segmentId): bool
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "DELETE FROM audience_segments WHERE id = ?"
            );
            $stmt->execute([$segmentId]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("AudienceBuilder::deleteSegment Error: " . $e->getMessage());
            throw $e;
        }
    }
}
