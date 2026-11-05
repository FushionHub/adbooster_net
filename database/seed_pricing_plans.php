<?php declare(strict_types=1);

/**
 * Pricing plan seed.
 *
 * Installs the platform's six plans:
 *   - 4 subscription plans (Starter, Growth, Pro, Scale) that can be
 *     billed monthly or yearly (yearly = 12 months at 25% off).
 *   - 2 pay-as-you-go plans (Launch Kit, Power Pack) that never renew.
 *
 * The credit numbers are derived from the live product rates:
 *   * 1 USD of ad credit = 1 USD of ad spend.
 *   * 100 AI credits per USD. One AI UGC image costs 5 AI credits,
 *     one AI script costs 1 AI credit.
 *
 * Idempotent: running it again updates the existing rows instead of
 * creating duplicates.
 *
 * Usage: php database/seed_pricing_plans.php
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

/** Yearly billing is 12 months at a 25% discount. */
const YEARLY_DISCOUNT = 0.75;

$plans = [
    // ── Subscription plans ─────────────────────────────────────────────
    [
        'name'        => 'Starter',
        'description' => 'Everything a solo creator needs to publish consistently and run small paid tests.',
        'price'       => 9.00,
        'ad_credits'  => 5.00,
        'ai_credits'  => 900,
        'featured'    => 0,
        'features'    => [
            '2 connected social accounts (Meta, Instagram, TikTok, X, LinkedIn, Pinterest, Snapchat)',
            '5 paid campaigns per month across every ad network',
            'Unlimited scheduled organic posts with the content calendar',
            'Image Studio — 180 AI UGC creatives per month (5 AI credits each)',
            'AI script writer — 900 scripts per month (1 AI credit each)',
            'Conversion tracking with the Server Tracking pixel + CAPI bridge',
            '1 team seat · developer API access at 60 requests/minute',
        ],
    ],
    [
        'name'        => 'Growth',
        'description' => 'For growing brands: more networks, A/B testing, audiences and a bigger creative budget.',
        'price'       => 24.00,
        'ad_credits'  => 15.00,
        'ai_credits'  => 2400,
        'featured'    => 1,
        'features'    => [
            '5 connected social accounts across every supported network',
            '25 paid campaigns per month — launch, pause and resume from one dashboard',
            'A/B creative testing with up to 4 variants per campaign',
            'Audience Builder — demographic, geo and interest cohorts',
            'Unlimited scheduled posts + content calendar rescheduling',
            'Image Studio — 480 AI UGC creatives per month',
            'AI storyboard video studio and script writer',
            'Server-side conversion tracking with the CAPI bridge',
            '3 team seats · developer API access at 60 requests/minute',
            'Priority email support',
        ],
    ],
    [
        'name'        => 'Pro',
        'description' => 'For agencies and scaling brands running multi-network funnels every single day.',
        'price'       => 59.00,
        'ad_credits'  => 45.00,
        'ai_credits'  => 5900,
        'featured'    => 0,
        'features'    => [
            '15 connected social accounts and unlimited ad accounts',
            'Unlimited paid campaigns with the Campaign Doctor recommendations',
            'A/B creative testing, Audience Builder and advanced targeting',
            'Unlimited scheduled posts across every connected profile',
            'Image Studio — 1,180 AI UGC creatives per month',
            'AI storyboard video studio, script writer and batch generation',
            'Server-side conversion tracking with the CAPI bridge',
            'Marketplace & directory listing + creator bounty participation',
            '10 team seats · developer API access at 60 requests/minute',
            'Webhooks for campaign, post and payment events',
            'Priority support with a 24-hour response target',
        ],
    ],
    [
        'name'        => 'Scale',
        'description' => 'The full platform for high-volume teams, agencies and white-label operations.',
        'price'       => 149.00,
        'ad_credits'  => 120.00,
        'ai_credits'  => 14900,
        'featured'    => 0,
        'features'    => [
            'Unlimited connected social accounts, ad accounts and team seats',
            'Unlimited paid campaigns with the Campaign Doctor and auto-optimisation',
            'Full A/B testing matrix, Audience Builder and advanced targeting',
            'Unlimited scheduled posts, bulk actions and content calendar',
            'Image Studio — 2,980 AI UGC creatives per month',
            'AI storyboard video studio, script writer and batch generation',
            'Server-side conversion tracking with the CAPI bridge',
            'Priority marketplace & directory listing + creator bounty management',
            'White-label reports and branded client portals',
            'Developer API access with the highest rate limit and webhooks',
            'Dedicated account manager and 4-hour response target',
        ],
    ],

    // ── Pay-as-you-go plans ───────────────────────────────────────────
    [
        'name'        => 'Launch Kit',
        'description' => 'A single payment that unlocks the whole platform for 30 days. No card on file, no renewal.',
        'price'       => 9.00,
        'ad_credits'  => 5.00,
        'ai_credits'  => 900,
        'featured'    => 0,
        'cycle'       => 'pay_as_you_go',
        'features'    => [
            '30 days of full platform access — every feature unlocked',
            '2 connected social accounts',
            '5 paid campaigns during the window',
            'Unlimited scheduled organic posts',
            'Image Studio — 180 AI UGC creatives',
            'AI script writer — 900 scripts',
            'Server-side conversion tracking + CAPI bridge',
            'Never renews — you are only charged once',
        ],
    ],
    [
        'name'        => 'Power Pack',
        'description' => 'Our most popular one-off pack: everything unlocked for 30 days with a serious creative budget.',
        'price'       => 29.00,
        'ad_credits'  => 20.00,
        'ai_credits'  => 2900,
        'featured'    => 1,
        'cycle'       => 'pay_as_you_go',
        'features'    => [
            '30 days of full platform access — every feature unlocked',
            '5 connected social accounts and 25 paid campaigns',
            'A/B creative testing and Audience Builder included',
            'Unlimited scheduled posts + content calendar',
            'Image Studio — 580 AI UGC creatives',
            'AI storyboard video studio and script writer',
            'Server-side conversion tracking + CAPI bridge',
            'Marketplace & directory listing for the full window',
            'Never renews — you are only charged once',
        ],
    ],
];

$pdo = db()->getConnection();
$inserted = 0;
$updated   = 0;

echo "Seeding pricing plans\n";
echo str_repeat('-', 66) . "\n";

foreach ($plans as $plan) {
    $cycle = $plan['cycle'] ?? 'monthly';
    $featuresJson = json_encode(array_values($plan['features']), JSON_UNESCAPED_SLASHES);

    $existing = db()->fetchOne(
        "SELECT id FROM pricing_plans WHERE name = ? AND billing_cycle = ? LIMIT 1",
        [$plan['name'], $cycle]
    );

    $data = [
        'description'         => $plan['description'],
        'price'               => $plan['price'],
        'billing_cycle'       => $cycle,
        'ad_credits_included' => $plan['ad_credits'],
        'ai_credits_included' => $plan['ai_credits'],
        'features_json'       => $featuresJson,
        'is_featured'         => $plan['featured'],
        'status'              => 'active',
    ];

    if ($existing) {
        db()->update('pricing_plans', $data, 'id = ?', [(int)$existing['id']]);
        echo "  [updated] {$plan['name']} ({$cycle})\n";
        $updated++;
    } else {
        db()->insert('pricing_plans', array_merge(['name' => $plan['name']], $data));
        echo "  [created] {$plan['name']} ({$cycle}) — $" . number_format($plan['price'], 2) . "\n";
        $inserted++;
    }
}

echo str_repeat('-', 66) . "\n";
echo "Created: {$inserted} | Updated: {$updated} | Total active plans: "
    . db()->count('pricing_plans', "status = 'active'") . "\n";

// ── Summary ─────────────────────────────────────────────────────────────
echo "\nPlan summary\n";
echo str_pad('Plan', 16) . str_pad('Cycle', 16) . str_pad('Monthly', 12)
    . str_pad('Yearly', 12) . str_pad('Ad $', 10) . "AI credits\n";
foreach (db()->fetchAll("SELECT * FROM pricing_plans WHERE status='active' ORDER BY billing_cycle DESC, price ASC") as $p) {
    $monthly = (float)$p['price'];
    $yearly  = $p['billing_cycle'] === 'pay_as_you_go' ? null : round($monthly * 12 * YEARLY_DISCOUNT, 2);
    echo str_pad((string)$p['name'], 16)
        . str_pad((string)$p['billing_cycle'], 16)
        . str_pad('$' . number_format($monthly, 2), 12)
        . str_pad($yearly === null ? '—' : '$' . number_format($yearly, 2), 12)
        . str_pad('$' . number_format((float)$p['ad_credits_included'], 2), 10)
        . (int)$p['ai_credits_included'] . "\n";
}
