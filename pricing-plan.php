<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/rate_sheet.php';

$pageTitle = 'Pricing Plans - ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/app_header.php';

$cur    = currency_current();
$symbol = currency_supported()[$cur] ?? '$';

/** Yearly billing = 12 months at a 25% discount. */
const PLAN_YEARLY_DISCOUNT = 0.75;

$plans   = [];
$oneOffs = [];
$leadPacks = [];
try {
    $plans = db()->fetchAll(
        "SELECT * FROM pricing_plans
         WHERE status = 'active' AND billing_cycle IN ('monthly','yearly')
         ORDER BY price ASC"
    );
    $oneOffs = db()->fetchAll(
        "SELECT * FROM pricing_plans
         WHERE status = 'active' AND billing_cycle = 'pay_as_you_go'
         ORDER BY price ASC"
    );
} catch (\Throwable $e) {
    $plans   = [];
    $oneOffs = [];
}

// The lead product gets its own section — it is not a platform plan, it is
// a delivered directory pack.
foreach ($oneOffs as $idx => $oneOff) {
    if (RateSheet::isLeadPackPlan($oneOff)) {
        $leadPacks[] = $oneOff;
        unset($oneOffs[$idx]);
    }
}
$oneOffs = array_values($oneOffs);

/**
 * Format a plan price. Prices are NGN-native (the platform currency) —
 * never run them through the USD-canonical price_convert().
 */
function plan_money(float $ngn, string $cur, string $symbol): string
{
    return price_ngn_fmt($ngn);
}

/**
 * The opposite-currency hint, e.g. "(charged in ₦ NGN)".
 */
function plan_alt_hint(float $ngn, string $cur): string
{
    return '(charged in ₦ NGN)';
}

/**
 * Render one subscription plan card.
 *
 * The card carries both the monthly and yearly price so the billing
 * toggle can swap them in place — no duplicated cards, no layout jump.
 */
function plan_card(array $plan, string $cur, string $symbol): void
{
    $ngn       = (float)($plan['price_ngn'] ?? $plan['price'] ?? 0);
    $yearlyNgn = round($ngn * 12 * PLAN_YEARLY_DISCOUNT, 2);
    $features  = json_decode((string)($plan['features_json'] ?? '[]'), true);
    if (!is_array($features)) {
        $features = [];
    }
    $featured = !empty($plan['is_featured']);
    $planId   = (int)$plan['id'];
    ?>
    <div class="rate-card<?= $featured ? ' is-featured' : '' ?> plan-card" data-plan="<?= $planId ?>">
        <?php if ($featured): ?>
            <span class="rate-tab"><i class="ph ph-star-four"></i> Most popular</span>
        <?php else: ?>
            <span class="rate-tab rate-tab-muted">Subscription</span>
        <?php endif; ?>

        <h3 class="rate-card-title"><?= e((string)($plan['name'] ?? 'Plan')) ?></h3>
        <?php if (!empty($plan['description'])): ?>
            <p class="rate-card-desc"><?= e((string)$plan['description']) ?></p>
        <?php else: ?>
            <p class="rate-card-desc">Direct-API publishing to every connected network.</p>
        <?php endif; ?>

        <div class="rate-price-row">
            <strong class="rate-price plan-price"><?= e(plan_money($ngn, $cur, $symbol)) ?></strong>
            <span class="rate-price-cycle plan-cycle-label">/ month</span>
        </div>
        <p class="rate-meta">
            <span class="plan-alt-hint"><?= e(plan_alt_hint($ngn, $cur)) ?></span>
            <span class="rate-save plan-yearly-save">Save 25% yearly</span>
        </p>

        <div class="rate-credits">
            <span class="rate-credits-label">Credits included</span>
            <span class="rate-credits-value">
                <span class="rate-credit-line"><?= e(plan_money((float)($plan['ad_credits_included'] ?? 0), $cur, $symbol)) ?> <span class="rate-credits-unit">ad credits</span></span>
                <span class="rate-credit-line"><?= (int)($plan['ai_credits_included'] ?? 0) ?> <span class="rate-credits-unit">AI credits</span></span>
            </span>
        </div>

        <ul class="rate-features">
            <?php foreach ($features as $feature): ?>
                <li>
                    <i class="ph ph-check"></i>
                    <span><?= e(is_string($feature) ? $feature : json_encode($feature)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="<?= url('billing') ?>?plan=<?= $planId ?>&cycle=monthly"
           class="rate-cta <?= $featured ? 'is-primary' : 'is-ghost' ?> plan-cta">
            <span class="plan-cta-label">Choose plan</span>
            <i class="ph ph-arrow-right"></i>
        </a>

        <script type="application/json" class="plan-cycle-data">
            {
                "monthlyPrice": "<?= e(plan_money($ngn, $cur, $symbol)) ?>",
                "yearlyPrice": "<?= e(plan_money($yearlyNgn, $cur, $symbol)) ?>",
                "monthlyAlt": "<?= e(plan_alt_hint($ngn, $cur)) ?>",
                "yearlyAlt": "<?= e(plan_alt_hint($yearlyNgn, $cur)) ?>",
                "monthlyHref": "<?= e(url('billing') . '?plan=' . $planId . '&cycle=monthly') ?>",
                "yearlyHref": "<?= e(url('billing') . '?plan=' . $planId . '&cycle=yearly') ?>"
            }
        </script>
    </div>
    <?php
}

/**
 * Render one pay-as-you-go card (fixed price, never renews).
 */
function one_off_card(array $plan, string $cur, string $symbol): void
{
    $ngn      = (float)($plan['price_ngn'] ?? $plan['price'] ?? 0);
    $features = json_decode((string)($plan['features_json'] ?? '[]'), true);
    if (!is_array($features)) {
        $features = [];
    }
    $featured = !empty($plan['is_featured']);
    $planId   = (int)$plan['id'];
    ?>
    <div class="rate-card is-once">
        <span class="rate-tab"><i class="ph ph-lightning"></i> Pay once</span>

        <h3 class="rate-card-title"><?= e((string)($plan['name'] ?? 'Plan')) ?></h3>
        <?php if (!empty($plan['description'])): ?>
            <p class="rate-card-desc"><?= e((string)$plan['description']) ?></p>
        <?php else: ?>
            <p class="rate-card-desc">One payment, no renewal, credits that never expire.</p>
        <?php endif; ?>

        <div class="rate-price-row">
            <strong class="rate-price"><?= e(plan_money($ngn, $cur, $symbol)) ?></strong>
            <span class="rate-price-cycle">one-time</span>
        </div>
        <p class="rate-meta">
            <span><?= e(plan_alt_hint($ngn, $cur)) ?></span>
        </p>

        <div class="rate-credits">
            <span class="rate-credits-label">Credits included</span>
            <span class="rate-credits-value">
                <span class="rate-credit-line"><?= e(plan_money((float)($plan['ad_credits_included'] ?? 0), $cur, $symbol)) ?> <span class="rate-credits-unit">ad credits</span></span>
                <span class="rate-credit-line"><?= (int)($plan['ai_credits_included'] ?? 0) ?> <span class="rate-credits-unit">AI credits</span></span>
            </span>
        </div>

        <ul class="rate-features">
            <?php foreach ($features as $feature): ?>
                <li>
                    <i class="ph ph-check"></i>
                    <span><?= e(is_string($feature) ? $feature : json_encode($feature)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="<?= url('billing') ?>?plan=<?= $planId ?>"
           class="rate-cta is-ghost">
            <span>Buy once</span>
            <i class="ph ph-arrow-right"></i>
        </a>
    </div>
    <?php
}

/**
 * Render the '1,000 Leads' product card — a delivered directory pack, not
 * a platform subscription. Price is NGN-native and comes from the rate
 * sheet so the SKU can never drift from the ads rates.
 */
function lead_pack_card(array $plan, string $cur, string $symbol): void
{
    $ngn      = (float)($plan['price_ngn'] ?? $plan['price'] ?? RateSheet::packPrice());
    $features = json_decode((string)($plan['features_json'] ?? '[]'), true);
    if (!is_array($features)) {
        $features = [];
    }
    $planId = (int)$plan['id'];
    $count  = RateSheet::leadPackCount();
    ?>
    <div class="rate-card is-once rate-lead-pack" data-plan="<?= $planId ?>">
        <span class="rate-tab"><i class="ph ph-address-book"></i> Directory product</span>

        <h3 class="rate-card-title"><?= e((string)($plan['name'] ?? RateSheet::leadPackName())) ?></h3>
        <?php if (!empty($plan['description'])): ?>
            <p class="rate-card-desc"><?= e((string)$plan['description']) ?></p>
        <?php else: ?>
            <p class="rate-card-desc">Verified business leads from the Adsbooster directory, delivered as a ready-to-call CSV.</p>
        <?php endif; ?>

        <div class="rate-price-row">
            <strong class="rate-price"><?= e(plan_money($ngn, $cur, $symbol)) ?></strong>
            <span class="rate-price-cycle">one-time</span>
        </div>
        <p class="rate-meta">
            <span><?= e(plan_alt_hint($ngn, $cur)) ?></span>
            <span class="rate-save"><?= number_format($count) ?> leads</span>
        </p>

        <ul class="rate-features">
            <?php foreach ($features as $feature): ?>
                <li>
                    <i class="ph ph-check"></i>
                    <span><?= e(is_string($feature) ? $feature : json_encode($feature)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="<?= url('billing') ?>?plan=<?= $planId ?>"
           class="rate-cta is-primary">
            <span>Order the lead list</span>
            <i class="ph ph-arrow-right"></i>
        </a>
    </div>
    <?php
}
?>

<div class="w-100">
    <div class="rate-head">
        <div>
            <h1>Pricing plans</h1>
            <p class="rate-sub">All prices in Nigerian naira (₦). Every plan includes direct-API publishing to all connected networks.</p>
        </div>
        <div class="rate-head-actions">
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-arrow-left"></i> Manage billing
            </a>
        </div>
    </div>

    <?php if (empty($plans) && empty($oneOffs) && empty($leadPacks)): ?>
        <div class="rate-empty">
            <i class="ph ph-package"></i>
            <h3>No plans available</h3>
            <p>Plans are being set up. Please check back soon.</p>
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm"><i class="ph ph-arrow-left"></i> Back to billing</a>
        </div>
    <?php else: ?>

    <!-- Ads rate sheet — the rates every ads workflow quotes -->
    <div class="rate-rate-sheet" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:0 0 26px">
        <div class="rate-rate-cell">
            <span class="rate-rate-k">Managed ads</span>
            <span class="rate-rate-v"><?= e(price_ngn_fmt(RateSheet::ratePer1kLeads('managed'))) ?></span>
            <span class="rate-rate-s">per 1,000 leads</span>
        </div>
        <div class="rate-rate-cell">
            <span class="rate-rate-k">Your own campaigns</span>
            <span class="rate-rate-v"><?= e(price_ngn_fmt(RateSheet::ratePer1kLeads('campaign'))) ?></span>
            <span class="rate-rate-s">per 1,000 leads</span>
        </div>
        <div class="rate-rate-cell">
            <span class="rate-rate-k">Wallet top-up</span>
            <span class="rate-rate-v"><?= e(price_ngn_fmt(RateSheet::minTopupNgn())) ?></span>
            <span class="rate-rate-s">minimum</span>
        </div>
    </div>

    <!-- Cycle toggle -->
    <?php if (!empty($plans)): ?>
    <div class="rate-toggle-wrap">
        <div class="rate-toggle" role="group" aria-label="Billing cycle">
            <button type="button" id="cycleMonthlyBtn" class="is-active" onclick="setCycle('monthly')" aria-pressed="true">Monthly</button>
            <button type="button" id="cycleYearlyBtn" onclick="setCycle('yearly')" aria-pressed="false">Yearly<span class="rate-toggle-save">&minus;25%</span></button>
        </div>
    </div>

    <!-- Subscription plans -->
    <div class="rate-grid">
        <?php foreach ($plans as $plan) {
            plan_card($plan, $cur, $symbol);
        } ?>
    </div>
    <?php endif; ?>

    <!-- Lead product -->
    <?php if (!empty($leadPacks)): ?>
    <div class="rate-section-head">
        <h2>Lead lists</h2>
        <p>Buy verified business leads from the Adsbooster directory. We deliver the finished list to your Billing page &mdash; you can call them the same day.</p>
    </div>
    <div class="rate-grid">
        <?php foreach ($leadPacks as $plan) {
            lead_pack_card($plan, $cur, $symbol);
        } ?>
    </div>
    <?php endif; ?>

    <!-- Pay-as-you-go plans -->
    <?php if (!empty($oneOffs)): ?>
    <div class="rate-section-head">
        <h2>Pay as you go</h2>
        <p>One payment unlocks the whole platform &mdash; credits stay lean so you only pay for what you use.</p>
    </div>
    <div class="rate-grid">
        <?php foreach ($oneOffs as $plan) {
            one_off_card($plan, $cur, $symbol);
        } ?>
    </div>
    <?php endif; ?>

    <!-- Budget & timeline calculator — every number comes from RateSheet -->
    <section class="rate-estimator" id="rateEstimator" aria-labelledby="rateEstimatorTitle"
             data-rate-campaign="<?= e((string)RateSheet::ratePer1kLeads('campaign')) ?>"
             data-rate-managed="<?= e((string)RateSheet::ratePer1kLeads('managed')) ?>"
             data-min-topup="<?= e((string)RateSheet::minTopupNgn()) ?>">
        <div class="rate-section-head">
            <h2 id="rateEstimatorTitle">Budget &amp; timeline</h2>
            <p>Pick a budget and how long you want it to last. Your money sets the leads; the timeline only sets the daily pace.</p>
        </div>

        <div class="rate-estimator-grid">
            <div class="rate-estimator-panel">
                <label class="rate-field-label" for="rateBudget">Campaign budget (₦)</label>
                <input id="rateBudget" class="rate-field" type="number" inputmode="numeric"
                       min="<?= (int)RateSheet::minTopupNgn() ?>" step="500" value="<?= (int)RateSheet::minTopupNgn() ?>">

                <div class="rate-channel-toggle" role="group" aria-label="Who runs the ads">
                    <button type="button" class="rate-channel is-active" data-channel="campaign" aria-pressed="true">
                        Self-serve <span><?= e(price_ngn_fmt(RateSheet::ratePer1kLeads('campaign'))) ?>/1k</span>
                    </button>
                    <button type="button" class="rate-channel" data-channel="managed" aria-pressed="false">
                        Managed <span><?= e(price_ngn_fmt(RateSheet::ratePer1kLeads('managed'))) ?>/1k</span>
                    </button>
                </div>

                <div class="rate-field-label" style="margin-top:14px">Timeline</div>
                <div class="rate-duration" role="group" aria-label="Campaign timeline in days">
                    <?php foreach (RateSheet::durationOptions() as $d): ?>
                        <button type="button" class="rate-day<?= $d === 30 ? ' is-active' : '' ?>"
                                data-days="<?= $d ?>" aria-pressed="<?= $d === 30 ? 'true' : 'false' ?>">
                            <?= $d === 1 ? '1 day' : $d . ' days' ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rate-estimator-readout" aria-live="polite">
                <div class="rate-readout-cell">
                    <span>Leads your budget buys</span>
                    <strong id="rateOutLeads">—</strong>
                </div>
                <div class="rate-readout-cell">
                    <span>Daily spend</span>
                    <strong id="rateOutDaily">—</strong>
                </div>
                <div class="rate-readout-cell">
                    <span>Cost per lead</span>
                    <strong id="rateOutCpl">—</strong>
                </div>
                <div class="rate-readout-cell">
                    <span>Total budget</span>
                    <strong id="rateOutBudget">—</strong>
                </div>
                <p class="rate-estimator-hint" id="rateEstimatorHint"></p>
            </div>
        </div>
    </section>

    <!-- Pricing transparency -->
    <section class="rate-transparency" aria-labelledby="rateTransparencyTitle">
        <h2 id="rateTransparencyTitle">Pricing transparency</h2>
        <p class="rate-transparency-lede">Here is exactly what you pay for — no hidden setup fees, no USD conversion, no surprise renewals.</p>
        <ul class="rate-transparency-list">
            <li>
                <i class="ph ph-currency-ngn"></i>
                <div><strong>Everything is priced in naira (₦).</strong> Prices are NGN-native — they are never converted from USD, so the amount you see is the amount you pay.</div>
            </li>
            <li>
                <i class="ph ph-users-three"></i>
                <div><strong>Lead rates.</strong> Ads managed and sold by Adsbooster cost <?= e(price_ngn_fmt(RateSheet::ratePer1kLeads('managed'))) ?> per 1,000 leads. Campaigns you run yourself cost <?= e(price_ngn_fmt(RateSheet::ratePer1kLeads('campaign'))) ?> per 1,000 leads — that is <?= e(price_ngn_fmt(RateSheet::costPerLead('campaign'))) ?> per lead.</div>
            </li>
            <li>
                <i class="ph ph-address-book"></i>
                <div><strong>The <?= e(RateSheet::leadPackName()) ?> list</strong> is a one-time <?= e(price_ngn_fmt(RateSheet::packPrice())) ?> for <?= number_format(RateSheet::leadPackCount()) ?> verified directory leads, delivered as a CSV on your Billing page. No subscription, no renewal.</div>
            </li>
            <li>
                <i class="ph ph-wallet"></i>
                <div><strong>Minimum top-up is <?= e(price_ngn_fmt(RateSheet::minTopupNgn())) ?>.</strong> Wallet credit never expires and can be split across campaigns. There is no separate platform fee on top of the media budget.</div>
            </li>
            <li>
                <i class="ph ph-clock-countdown"></i>
                <div><strong>Your campaign runs for as long as your money lasts.</strong> The budget is committed up front; the timeline you choose only sets the daily spend. Topping up extends the run. Nothing stops early unless you pause it or the budget is spent.</div>
            </li>
            <li>
                <i class="ph ph-chart-line-up"></i>
                <div><strong>Lead estimates are projections, not guarantees.</strong> Delivery depends on the ad auction and your targeting. You pay for the media budget you commit; the estimator shows what that budget buys at the published rate.</div>
            </li>
        </ul>
    </section>
    <?php endif; ?>
</div>

<script>
(function () {
    var wrap = document.getElementById('rateEstimator');
    if (!wrap) return;
    var rateCampaign = parseFloat(wrap.dataset.rateCampaign) || 0;
    var rateManaged  = parseFloat(wrap.dataset.rateManaged) || 0;
    var budgetEl = document.getElementById('rateBudget');
    var leadsEl  = document.getElementById('rateOutLeads');
    var dailyEl  = document.getElementById('rateOutDaily');
    var cplEl    = document.getElementById('rateOutCpl');
    var totalEl  = document.getElementById('rateOutBudget');
    var hintEl   = document.getElementById('rateEstimatorHint');
    var channel  = 'campaign';
    var days     = 30;

    function money(n, frac) {
        return '₦' + Number(n).toLocaleString('en-NG', {
            minimumFractionDigits: frac || 0,
            maximumFractionDigits: frac || 0
        });
    }

    function render() {
        var budget = Math.max(0, parseFloat(budgetEl.value) || 0);
        var rate = channel === 'managed' ? rateManaged : rateCampaign;
        var costPerLead = rate / 1000;
        var leads = rate > 0 ? Math.floor((budget / rate) * 1000) : 0;
        var daily = days > 0 ? budget / days : 0;

        leadsEl.textContent = leads.toLocaleString('en-NG') + ' leads';
        dailyEl.textContent = money(daily) + ' / day';
        cplEl.textContent = money(costPerLead, costPerLead < 10 ? 2 : 0);
        totalEl.textContent = money(budget);
        hintEl.textContent = budget > 0
            ? money(budget) + ' paced over ' + (days === 1 ? '1 day' : days + ' days') + ' keeps your ad live for ' + (days === 1 ? 'one day' : days + ' consecutive days') + ' at ' + money(daily) + ' per day.'
            : 'Enter a budget to see the projection.';
    }

    budgetEl.addEventListener('input', render);

    wrap.querySelectorAll('.rate-channel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            channel = btn.dataset.channel === 'managed' ? 'managed' : 'campaign';
            wrap.querySelectorAll('.rate-channel').forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', String(on));
            });
            render();
        });
    });

    wrap.querySelectorAll('.rate-day').forEach(function (btn) {
        btn.addEventListener('click', function () {
            days = parseInt(btn.dataset.days, 10) || 30;
            wrap.querySelectorAll('.rate-day').forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', String(on));
            });
            render();
        });
    });

    render();
})();
</script>

function setCycle(cycle) {
    var m = document.getElementById('cycleMonthlyBtn');
    var y = document.getElementById('cycleYearlyBtn');
    var yearly = cycle === 'yearly';
    if (m) { m.classList.toggle('is-active', !yearly); m.setAttribute('aria-pressed', String(!yearly)); }
    if (y) { y.classList.toggle('is-active', yearly);  y.setAttribute('aria-pressed', String(yearly)); }

    // Swap the price, hint, suffix and checkout link on every plan card.
    document.querySelectorAll('.plan-card').forEach(function (card) {
        var dataEl = card.querySelector('.plan-cycle-data');
        if (!dataEl) return;
        var data = JSON.parse(dataEl.textContent);
        var yearly = cycle === 'yearly';

        var price = card.querySelector('.plan-price');
        var label = card.querySelector('.plan-cycle-label');
        var alt   = card.querySelector('.plan-alt-hint');
        var save  = card.querySelector('.plan-yearly-save');
        var cta   = card.querySelector('.plan-cta');
        var ctaLbl= card.querySelector('.plan-cta-label');

        if (price) price.textContent = yearly ? data.yearlyPrice : data.monthlyPrice;
        if (label) label.textContent = yearly ? ' / year' : ' / month';
        if (alt)   alt.textContent   = yearly ? data.yearlyAlt   : data.monthlyAlt;
        if (save)  save.style.display = yearly ? 'none' : '';
        if (cta)   cta.href = yearly ? data.yearlyHref : data.monthlyHref;
        if (ctaLbl) ctaLbl.textContent = yearly ? 'Start yearly, save 25%' : 'Choose plan';
    });
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
