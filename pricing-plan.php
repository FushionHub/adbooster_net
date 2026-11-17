<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Pricing Plans - ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/app_header.php';

$cur    = currency_current();
$symbol = currency_supported()[$cur] ?? '$';

/** Yearly billing = 12 months at a 25% discount. */
const PLAN_YEARLY_DISCOUNT = 0.75;

$plans   = [];
$oneOffs = [];
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

/**
 * Format a USD amount for the active display currency.
 */
function plan_money(float $usd, string $cur, string $symbol): string
{
    return $symbol . number_format(price_convert($usd, $cur), $cur === 'NGN' ? 0 : 2);
}

/**
 * The opposite-currency hint, e.g. "(≈ $24.00 USD)".
 */
function plan_alt_hint(float $usd, string $cur): string
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
    $usd       = (float)($plan['price'] ?? 0);
    $yearlyUsd = round($usd * 12 * PLAN_YEARLY_DISCOUNT, 2);
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
            <strong class="rate-price plan-price"><?= e(plan_money($usd, $cur, $symbol)) ?></strong>
            <span class="rate-price-cycle plan-cycle-label">/ month</span>
        </div>
        <p class="rate-meta">
            <span class="plan-alt-hint"><?= e(plan_alt_hint($usd, $cur)) ?></span>
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
                "monthlyPrice": "<?= e(plan_money($usd, $cur, $symbol)) ?>",
                "yearlyPrice": "<?= e(plan_money($yearlyUsd, $cur, $symbol)) ?>",
                "monthlyAlt": "<?= e(plan_alt_hint($usd, $cur)) ?>",
                "yearlyAlt": "<?= e(plan_alt_hint($yearlyUsd, $cur)) ?>",
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
    $usd      = (float)($plan['price'] ?? 0);
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
            <strong class="rate-price"><?= e(plan_money($usd, $cur, $symbol)) ?></strong>
            <span class="rate-price-cycle">one-time</span>
        </div>
        <p class="rate-meta">
            <span><?= e(plan_alt_hint($usd, $cur)) ?></span>
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

    <?php if (empty($plans) && empty($oneOffs)): ?>
        <div class="rate-empty">
            <i class="ph ph-package"></i>
            <h3>No plans available</h3>
            <p>Plans are being set up. Please check back soon.</p>
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm"><i class="ph ph-arrow-left"></i> Back to billing</a>
        </div>
    <?php else: ?>

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
    <?php endif; ?>
</div>

<script>
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
