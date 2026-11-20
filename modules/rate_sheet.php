<?php
declare(strict_types=1);

/**
 * Ads Rate Sheet — the single source of truth for lead-based pricing.
 * Adsbooster.net
 *
 * EVERY ads/credits workflow (ad launcher, wallet top-up, pricing page,
 * dashboard projections, invoices) must reference THESE rates — never a
 * hardcoded copy. All values are admin-tunable site settings; the class
 * defaults below are the launch rate sheet:
 *
 *   • ₦4,200 per 1,000 leads — ads managed/sold by Adsbooster (done-for-you)
 *   • ₦3,800 per 1,000 leads — self-serve campaigns run by the customer
 *   • ₦3,500 — the '1,000 Leads' product (directory/lead-list delivery)
 *   • ₦15,000 — minimum wallet top-up for ads/AI credits
 *
 * Campaign runtime is BUDGET-DRIVEN: an ad runs as long and as much as the
 * user's money allows. Estimated runtime = budget ÷ daily budget; there is
 * no fixed campaign duration anywhere in the platform.
 */
class RateSheet
{
    /** site_settings keys backing each rate. */
    public const SETTING_MANAGED_RATE = 'leads_rate_managed_ngn';
    public const SETTING_CAMPAIGN_RATE = 'leads_rate_campaign_ngn';
    public const SETTING_PACK_PRICE = 'leads_pack_price_ngn';
    public const SETTING_MIN_TOPUP = 'min_topup_ngn';
    public const SETTING_PACK_NAME = 'leads_pack_product_name';
    public const SETTING_PACK_COUNT = 'leads_pack_count';

    /** Default launch rate sheet (used when the setting is absent). */
    private const DEFAULT_MANAGED_RATE = 4200.0;
    private const DEFAULT_CAMPAIGN_RATE = 3800.0;
    private const DEFAULT_PACK_PRICE = 3500.0;
    private const DEFAULT_MIN_TOPUP = 15000.0;
    private const DEFAULT_PACK_NAME = '1,000 Leads';
    private const DEFAULT_PACK_COUNT = 1000;

    /**
     * ₦ per 1,000 leads for a channel.
     *
     * @param string $channel 'managed' (Adsbooster-sold/managed ads) or
     *                        'campaign' (self-serve campaign).
     */
    public static function ratePer1kLeads(string $channel = 'campaign'): float
    {
        $key = strtolower(trim($channel)) === 'managed'
            ? self::SETTING_MANAGED_RATE
            : self::SETTING_CAMPAIGN_RATE;
        $default = strtolower(trim($channel)) === 'managed'
            ? self::DEFAULT_MANAGED_RATE
            : self::DEFAULT_CAMPAIGN_RATE;
        $rate = (float)get_setting($key, (string)$default);
        return $rate > 0 ? $rate : $default;
    }

    /** ₦ price of the '1,000 Leads' product. */
    public static function packPrice(): float
    {
        $price = (float)get_setting(self::SETTING_PACK_PRICE, (string)self::DEFAULT_PACK_PRICE);
        return $price > 0 ? $price : self::DEFAULT_PACK_PRICE;
    }

    /** Display name of the lead product ('1,000 Leads'). */
    public static function leadPackName(): string
    {
        $name = trim((string)get_setting(self::SETTING_PACK_NAME, self::DEFAULT_PACK_NAME));
        return $name !== '' ? $name : self::DEFAULT_PACK_NAME;
    }

    /** How many leads one pack contains. */
    public static function leadPackCount(): int
    {
        $count = (int)get_setting(self::SETTING_PACK_COUNT, (string)self::DEFAULT_PACK_COUNT);
        return $count > 0 ? $count : self::DEFAULT_PACK_COUNT;
    }

    /**
     * Is this pricing_plans row the lead product?
     *
     * The marker is pricing_plans.product_type = 'lead_pack'. Rows created
     * before that column existed are matched by name.
     *
     * @param array<string,mixed> $plan
     */
    public static function isLeadPackPlan(array $plan): bool
    {
        $type = strtolower(trim((string)($plan['product_type'] ?? '')));
        if ($type !== '') {
            return $type === 'lead_pack';
        }
        return strcasecmp(trim((string)($plan['name'] ?? '')), self::leadPackName()) === 0;
    }

    /** ₦ minimum wallet top-up for ads/AI credits. */
    public static function minTopupNgn(): float
    {
        $min = (float)get_setting(self::SETTING_MIN_TOPUP, (string)self::DEFAULT_MIN_TOPUP);
        return $min > 0 ? $min : self::DEFAULT_MIN_TOPUP;
    }

    /**
     * Leads a budget buys at the channel rate (rounded down — never promise
     * more leads than the money pays for).
     */
    public static function leadsForBudget(float $budgetNgn, string $channel = 'campaign'): int
    {
        if ($budgetNgn <= 0) {
            return 0;
        }
        return (int)floor(($budgetNgn / self::ratePer1kLeads($channel)) * 1000);
    }

    /** Budget (₦) required to buy a number of leads at the channel rate. */
    public static function budgetForLeads(int $leads, string $channel = 'campaign'): float
    {
        if ($leads <= 0) {
            return 0.0;
        }
        return round(($leads / 1000) * self::ratePer1kLeads($channel), 2);
    }

    /**
     * Estimated campaign runtime in days: budget ÷ daily budget, rounded up.
     * An ad runs for exactly as long as its money allows.
     */
    public static function runDays(float $budgetNgn, float $dailyBudgetNgn): int
    {
        if ($budgetNgn <= 0 || $dailyBudgetNgn <= 0) {
            return 0;
        }
        return (int)max(1, (int)ceil($budgetNgn / $dailyBudgetNgn));
    }

    /** Cost of ONE lead in naira at the channel rate. */
    public static function costPerLead(string $channel = 'campaign'): float
    {
        return round(self::ratePer1kLeads($channel) / 1000, 4);
    }

    /**
     * The budget pacing options offered to users (days). A campaign runs for
     * exactly as long as its money lasts, so the timeline only sets the daily
     * spend — it never changes how many leads the budget buys.
     *
     * @return list<int>
     */
    public static function durationOptions(): array
    {
        return [1, 3, 7, 14, 21, 30, 60, 90, 120, 365];
    }

    /** Daily spend for a budget spread over a number of days. */
    public static function dailyBudget(float $budgetNgn, int $days): float
    {
        if ($budgetNgn <= 0 || $days <= 0) {
            return 0.0;
        }
        return round($budgetNgn / $days, 2);
    }

    /**
     * The wallet amount a campaign commits (₦).
     *
     *   • lifetime budget → the budget itself
     *   • daily budget     → the daily amount × the timeline days
     *
     * This is the number debited from the naira wallet when a campaign is
     * created and the number refunded if the gateway permanently rejects the
     * launch — one definition, so the debit and the refund can never drift.
     */
    public static function committedCampaignCost(float $budgetAmount, string $budgetType, int $campaignDays): float
    {
        if ($budgetAmount <= 0) {
            return 0.0;
        }
        if (strtolower(trim($budgetType)) === 'lifetime') {
            return round($budgetAmount, 2);
        }
        return round($budgetAmount * max(1, $campaignDays), 2);
    }

    /**
     * Full projection block for one campaign budget — used by the ad
     * launcher estimator, the dashboard and the rate sheet copy.
     *
     * @return array{budget_ngn:float, channel:string, rate_ngn_per_1k:float,
     *               leads:int, daily_budget_ngn:float, run_days:int}
     */
    public static function projection(float $budgetNgn, float $dailyBudgetNgn, string $channel = 'campaign'): array
    {
        return [
            'budget_ngn'       => round($budgetNgn, 2),
            'channel'          => strtolower(trim($channel)) === 'managed' ? 'managed' : 'campaign',
            'rate_ngn_per_1k'  => self::ratePer1kLeads($channel),
            'leads'            => self::leadsForBudget($budgetNgn, $channel),
            'daily_budget_ngn' => round($dailyBudgetNgn, 2),
            'run_days'         => self::runDays($budgetNgn, $dailyBudgetNgn),
        ];
    }

    /**
     * Projection for a committed budget paced over a chosen number of days.
     * Leads depend on the RATE (money ÷ rate), while the chosen timeline only
     * decides the daily pacing. This keeps the promise honest: you never get
     * more leads by stretching the same money over more days.
     *
     * @return array{budget_ngn:float, channel:string, rate_ngn_per_1k:float,
     *               cost_per_lead:float, leads:int, days:int, daily_budget_ngn:float}
     */
    public static function projectionForDuration(float $budgetNgn, int $days, string $channel = 'campaign'): array
    {
        $channel = strtolower(trim($channel)) === 'managed' ? 'managed' : 'campaign';
        return [
            'budget_ngn'       => round($budgetNgn, 2),
            'channel'          => $channel,
            'rate_ngn_per_1k'  => self::ratePer1kLeads($channel),
            'cost_per_lead'    => self::costPerLead($channel),
            'leads'            => self::leadsForBudget($budgetNgn, $channel),
            'days'             => max(1, $days),
            'daily_budget_ngn' => self::dailyBudget($budgetNgn, max(1, $days)),
        ];
    }
}
