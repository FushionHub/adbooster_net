<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/site_setting_and_config.php';
require_once __DIR__ . '/../../modules/rate_sheet.php';

/**
 * Unit tests: the published Adsbooster rate sheet.
 *
 * The rate sheet is the single source of truth for lead-based pricing:
 *
 *   • ₦4,200 per 1,000 leads — Adsbooster-managed ads
 *   • ₦3,800 per 1,000 leads — self-serve campaigns
 *   • ₦3,500           — the '1,000 Leads' product
 *   • ₦15,000          — minimum wallet top-up
 *
 * Campaign runtime is budget-driven: an ad runs for exactly as long as the
 * committed money allows. No number here may ever be hardcoded twice.
 */
#[CoversClass(RateSheet::class)]
final class RateSheetTest extends TestCase
{
    public function testLaunchRatesMatchThePublishedSheet(): void
    {
        $this->assertSame(4200.0, RateSheet::ratePer1kLeads('managed'));
        $this->assertSame(3800.0, RateSheet::ratePer1kLeads('campaign'));
        $this->assertSame(3500.0, RateSheet::packPrice());
        $this->assertSame(15000.0, RateSheet::minTopupNgn());
        $this->assertSame('1,000 Leads', RateSheet::leadPackName());
        $this->assertSame(1000, RateSheet::leadPackCount());
    }

    public function testUnknownChannelFallsBackToTheSelfServeRate(): void
    {
        $this->assertSame(3800.0, RateSheet::ratePer1kLeads('nonsense'));
    }

    public function testLeadsForBudgetNeverPromisesMoreThanTheMoneyPaysFor(): void
    {
        // ₦3,800 buys exactly 1,000 leads.
        $this->assertSame(1000, RateSheet::leadsForBudget(3800.0, 'campaign'));

        // ₦3,799 buys 999 — rounded DOWN, never up.
        $this->assertSame(999, RateSheet::leadsForBudget(3799.0, 'campaign'));

        // ₦15,000 at the managed rate = 3,571 leads.
        $this->assertSame(3571, RateSheet::leadsForBudget(15000.0, 'managed'));

        $this->assertSame(0, RateSheet::leadsForBudget(0));
        $this->assertSame(0, RateSheet::leadsForBudget(-100));
    }

    public function testBudgetForLeadsIsTheInverseOfLeadsForBudget(): void
    {
        $this->assertSame(3800.0, RateSheet::budgetForLeads(1000, 'campaign'));
        $this->assertSame(4200.0, RateSheet::budgetForLeads(1000, 'managed'));
        $this->assertSame(1900.0, RateSheet::budgetForLeads(500, 'campaign'));
        $this->assertSame(0.0, RateSheet::budgetForLeads(0));
        $this->assertSame(0.0, RateSheet::budgetForLeads(-5));
    }

    public function testRunDaysIsBudgetDividedByDailyBudget(): void
    {
        // An ad runs for exactly as long as its money allows.
        $this->assertSame(30, RateSheet::runDays(30000.0, 1000.0));
        $this->assertSame(1, RateSheet::runDays(500.0, 1000.0));
        $this->assertSame(0, RateSheet::runDays(0.0, 1000.0));
        $this->assertSame(0, RateSheet::runDays(1000.0, 0.0));
    }

    public function testProjectionIsSelfConsistent(): void
    {
        $projection = RateSheet::projection(15000.0, 500.0, 'campaign');

        $this->assertSame(15000.0, $projection['budget_ngn']);
        $this->assertSame(3800.0, $projection['rate_ngn_per_1k']);
        $this->assertSame(RateSheet::leadsForBudget(15000.0, 'campaign'), $projection['leads']);
        $this->assertSame(30, $projection['run_days']);
    }

    public function testCostPerLeadIsTheRateDividedByOneThousand(): void
    {
        $this->assertSame(3.8, RateSheet::costPerLead('campaign'));
        $this->assertSame(4.2, RateSheet::costPerLead('managed'));
    }

    public function testDailyBudgetSpreadsTheCommittedMoneyOverTheTimeline(): void
    {
        $this->assertSame(15000.0, RateSheet::dailyBudget(15000.0, 1));
        $this->assertSame(5000.0, RateSheet::dailyBudget(15000.0, 3));
        $this->assertSame(500.0, RateSheet::dailyBudget(15000.0, 30));
        $this->assertSame(41.1, RateSheet::dailyBudget(15000.0, 365));
        $this->assertSame(0.0, RateSheet::dailyBudget(0.0, 30));
        $this->assertSame(0.0, RateSheet::dailyBudget(15000.0, 0));
    }

    public function testDurationOptionsCoverTheOfferedTimelines(): void
    {
        $this->assertSame([1, 3, 7, 14, 21, 30, 60, 90, 120, 365], RateSheet::durationOptions());
    }

    public function testProjectionForDurationKeepsLeadsRateDriven(): void
    {
        // The same money buys the same leads no matter the pacing.
        $oneDay   = RateSheet::projectionForDuration(15000.0, 1, 'campaign');
        $thirty   = RateSheet::projectionForDuration(15000.0, 30, 'campaign');
        $this->assertSame($oneDay['leads'], $thirty['leads']);
        $this->assertSame(3947, $thirty['leads']); // 15000 / 3.80 per lead, floored
        $this->assertSame(500.0, $thirty['daily_budget_ngn']);
        $this->assertSame(15000.0, $oneDay['daily_budget_ngn']);
        $this->assertSame(3.8, $thirty['cost_per_lead']);
        $this->assertSame(30, $thirty['days']);
        // Duration is clamped to at least one day.
        $this->assertSame(1, RateSheet::projectionForDuration(15000.0, 0)['days']);
    }

    public function testLeadPackDetectionUsesProductType(): void
    {
        $this->assertTrue(RateSheet::isLeadPackPlan([
            'product_type' => 'lead_pack',
            'name'         => 'Anything',
        ]));
        $this->assertFalse(RateSheet::isLeadPackPlan([
            'product_type' => 'plan',
            'name'         => '1,000 Leads',
        ]));
        // Legacy rows without the column are matched by name.
        $this->assertTrue(RateSheet::isLeadPackPlan([
            'product_type' => '',
            'name'         => '1,000 Leads',
        ]));
        $this->assertFalse(RateSheet::isLeadPackPlan([
            'product_type' => '',
            'name'         => 'Starter',
        ]));
    }

    public function testCommittedCampaignCostIsTheWalletDebit(): void
    {
        // A daily budget commits daily × timeline days — the money the ads
        // may spend before the campaign stops.
        $this->assertSame(15000.0, RateSheet::committedCampaignCost(500.0, 'daily', 30));
        $this->assertSame(15000.0, RateSheet::committedCampaignCost(500.0, 'daily', 30));
        $this->assertSame(1500.0, RateSheet::committedCampaignCost(500.0, 'daily', 3));

        // A lifetime budget commits its full amount, whatever the timeline.
        $this->assertSame(15000.0, RateSheet::committedCampaignCost(15000.0, 'lifetime', 30));
        $this->assertSame(15000.0, RateSheet::committedCampaignCost(15000.0, 'lifetime', 1));

        // A timeline of zero days still commits one day — never zero.
        $this->assertSame(500.0, RateSheet::committedCampaignCost(500.0, 'daily', 0));

        // Nothing to commit.
        $this->assertSame(0.0, RateSheet::committedCampaignCost(0.0, 'daily', 30));
        $this->assertSame(0.0, RateSheet::committedCampaignCost(-100.0, 'daily', 30));
    }

    public function testCommittedCampaignCostMatchesTheLauncherMaths(): void
    {
        // create-ad.php and the REST API both derive the debit from this
        // helper — a daily budget of ₦500 over 30 days is a ₦15,000
        // commitment, and a lifetime budget of ₦15,000 is also ₦15,000.
        $daily = 500.0 * 30;
        $this->assertSame(
            RateSheet::committedCampaignCost($daily / 30, 'daily', 30),
            RateSheet::committedCampaignCost($daily, 'lifetime', 30)
        );
    }
}
