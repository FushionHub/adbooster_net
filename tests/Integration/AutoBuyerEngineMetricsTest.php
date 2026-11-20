<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/site_setting_and_config.php';
require_once __DIR__ . '/../../modules/auto_buyer_engine.php';

/**
 * Integration tests: the AI Media Buyer's metric semantics.
 *
 * HONESTY CONTRACT under test
 * ----------------------------
 * A metric the ad gateway never reported is `null` (unavailable) — NOT 0.
 * The previous implementation read a missing revenue as 0, which produced a
 * fake ROAS of 0, let "spend without conversion" rules fire on campaigns
 * that had no conversions at all, and scored empty campaigns 15/100 "Low
 * fatigue" — telling the user their ads were healthy when nothing had been
 * measured.
 *
 * Recreate the test DB with:
 *   php -d extension=php_pdo_mysql.dll tests/make-test-db.php
 */
#[CoversClass(AutoBuyerEngine::class)]
#[RequiresPhpExtension('pdo_mysql')]
final class AutoBuyerEngineMetricsTest extends TestCase
{
    private int $userId;
    private int $campaignId;

    protected function setUp(): void
    {
        $this->userId = db()->insert('users', [
            'email'      => 'autobuyer_' . bin2hex(random_bytes(6)) . '@test.local',
            'full_name'  => 'Auto Buyer Tester',
            'status'     => 'active',
            'ad_credits' => 0,
            'ai_credits' => 0,
        ]);

        $this->campaignId = db()->insert('ad_campaigns', [
            'user_id'       => $this->userId,
            'campaign_name' => 'Auto Buyer Fixture',
            'campaign_type' => 'meta',
            'status'        => 'active',
            'budget_amount' => 15000,
        ]);
    }

    protected function tearDown(): void
    {
        db()->delete('campaign_metrics_daily', 'campaign_id = ?', [$this->campaignId]);
        db()->delete('ai_media_buyer_rules', 'user_id = ?', [$this->userId]);
        db()->delete('ai_media_buyer_logs', 'user_id = ?', [$this->userId]);
        db()->delete('ad_campaigns', 'id = ?', [$this->campaignId]);
        db()->delete('users', 'id = ?', [$this->userId]);
    }

    private function insertMetric(array $row): void
    {
        db()->insert('campaign_metrics_daily', array_merge([
            'campaign_id' => $this->campaignId,
            'date'        => date('Y-m-d'),
            'variant_id'  => null,
            'impressions' => 0,
            'clicks'      => 0,
            'spend'       => 0,
            'conversions' => 0,
            'ctr'         => 0,
            'cpc'         => 0,
        ], $row));
    }

    public function testCampaignWithoutMetricsReportsNoData(): void
    {
        $metrics = AutoBuyerEngine::getCampaignCalculatedMetrics($this->campaignId);

        $this->assertFalse($metrics['has_data'], 'A campaign with no metric rows has no observed data');
        $this->assertNull($metrics['revenue'], 'Revenue is unavailable, not zero');
        $this->assertNull($metrics['ctr']);
        $this->assertNull($metrics['cpa']);
        $this->assertNull($metrics['roas']);
        $this->assertNull($metrics['frequency']);
    }

    public function testMissingRevenueLeavesRoasUnavailable(): void
    {
        $this->insertMetric([
            'impressions' => 1000,
            'clicks'      => 50,
            'spend'       => 100,
            'conversions' => 2,
        ]);

        $metrics = AutoBuyerEngine::getCampaignCalculatedMetrics($this->campaignId);

        $this->assertTrue($metrics['has_data']);
        $this->assertSame(1000, $metrics['impressions']);
        $this->assertSame(50.0, $metrics['cpa'], 'CPA = spend / conversions');
        $this->assertSame(5.0, $metrics['ctr'], 'CTR = clicks / impressions * 100');
        $this->assertNull($metrics['revenue'], 'No revenue reported → null');
        $this->assertNull($metrics['roas'], 'ROAS cannot be computed without revenue');
    }

    public function testReportedRevenueProducesRoas(): void
    {
        $this->insertMetric([
            'impressions' => 1000,
            'clicks'      => 50,
            'spend'       => 100,
            'conversions' => 2,
            'revenue'     => 250,
        ]);

        $metrics = AutoBuyerEngine::getCampaignCalculatedMetrics($this->campaignId);

        $this->assertSame(250.0, $metrics['revenue']);
        $this->assertSame(2.5, $metrics['roas'], 'ROAS = revenue / spend');
    }

    public function testFrequencyNeedsBothReachAndImpressions(): void
    {
        $this->insertMetric([
            'impressions' => 1000,
            'clicks'      => 50,
            'spend'       => 100,
            'conversions' => 2,
            'reach'       => 500,
        ]);

        $metrics = AutoBuyerEngine::getCampaignCalculatedMetrics($this->campaignId);
        $this->assertSame(2.0, $metrics['frequency'], 'Frequency = impressions / reach');

        // Reach missing → frequency unavailable even though impressions exist.
        db()->delete('campaign_metrics_daily', 'campaign_id = ?', [$this->campaignId]);
        $this->insertMetric([
            'impressions' => 1000,
            'clicks'      => 50,
            'spend'       => 100,
            'conversions' => 2,
        ]);
        $metrics = AutoBuyerEngine::getCampaignCalculatedMetrics($this->campaignId);
        $this->assertNull($metrics['frequency']);
    }

    public function testFatigueIsNotScoredWithoutObservedData(): void
    {
        $fatigue = AutoBuyerEngine::predictFatigue($this->campaignId);

        $this->assertNull($fatigue['fatigue_score'], 'No data → no fatigue score');
        $this->assertSame('No data', $fatigue['risk_level']);
    }

    public function testRuleDoesNotFireOnUnavailableMetric(): void
    {
        // Real spend, no conversions — a "roas >= 1" rule must NOT fire,
        // because revenue was never reported (roas is unavailable).
        $this->insertMetric([
            'impressions' => 1000,
            'clicks'      => 50,
            'spend'       => 100,
            'conversions' => 0,
        ]);

        $ruleId = db()->insert('ai_media_buyer_rules', [
            'user_id'            => $this->userId,
            'campaign_id'        => $this->campaignId,
            'rule_name'          => 'Scale on ROAS',
            'condition_metric'   => 'roas',
            'condition_operator' => '>=',
            'condition_value'    => 1,
            'action_type'        => 'notify_only',
            'action_value'       => 0,
            'is_active'          => 1,
        ]);

        $logs = AutoBuyerEngine::evaluateAllRules($this->userId);

        $this->assertSame([], $logs, 'An unavailable metric must never trigger a rule');
        $this->assertSame(0, (int)db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ai_media_buyer_logs WHERE rule_id = ?",
            [$ruleId]
        )['cnt']);
    }

    public function testRuleFiresWhenSpendIsObservedWithoutConversions(): void
    {
        // spend_without_conv IS evaluable from observed data: spend > 0 and
        // zero conversions is a real condition, not a missing metric.
        $this->insertMetric([
            'impressions' => 1000,
            'clicks'      => 20,
            'spend'       => 120,
            'conversions' => 0,
        ]);

        db()->insert('ai_media_buyer_rules', [
            'user_id'            => $this->userId,
            'campaign_id'        => $this->campaignId,
            'rule_name'          => 'Stop burning money',
            'condition_metric'   => 'spend_without_conv',
            'condition_operator' => '>=',
            'condition_value'    => 100,
            'action_type'        => 'notify_only',
            'action_value'       => 0,
            'is_active'          => 1,
        ]);

        $logs = AutoBuyerEngine::evaluateAllRules($this->userId);

        $this->assertCount(1, $logs, 'Observed spend without conversions is a real trigger');
    }
}
