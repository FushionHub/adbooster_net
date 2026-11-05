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
    return $cur === 'NGN'
        ? '(≈ $' . number_format($usd, 2) . ' USD)'
        : '(≈ ' . price_convert($usd, 'NGN') . ' NGN)';
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
    <div class="card-dark plan-card" data-plan="<?= $planId ?>" style="padding:24px;display:flex;flex-direction:column;position:relative;<?= $featured ? 'border-color:var(--accent);box-shadow:0 0 0 1px var(--accent);' : '' ?>">
        <?php if ($featured): ?>
            <span class="badge-active" style="position:absolute;top:-11px;left:20px;"><i class="ph ph-crown"></i> Most Popular</span>
        <?php endif; ?>

        <h3 style="font-weight:700;color:var(--fg);margin:0 0 4px;font-size:17px;"><?= e((string)($plan['name'] ?? 'Plan')) ?></h3>
        <?php if (!empty($plan['description'])): ?>
            <p style="font-size:13px;color:var(--fg2);margin:0 0 12px;"><?= e((string)$plan['description']) ?></p>
        <?php endif; ?>

        <p style="margin:0 0 4px;">
            <strong class="plan-price" style="font-size:30px;font-weight:800;color:var(--fg);"><?= e(plan_money($usd, $cur, $symbol)) ?></strong>
            <span class="plan-cycle-label" style="font-size:12px;color:var(--fg3);"> / month</span>
        </p>
        <p style="margin:0 0 12px;font-size:11.5px;color:var(--fg3);">
            <span class="plan-alt-hint"><?= e(plan_alt_hint($usd, $cur)) ?></span>
            <span class="plan-yearly-save" style="color:var(--green);font-weight:700;">Save 25% on yearly</span>
        </p>

        <div style="background:rgba(200,255,0,0.08);border:1px solid rgba(200,255,0,0.25);border-radius:8px;padding:8px 10px;margin-bottom:14px;font-size:12px;">
            <div style="color:var(--accent);font-weight:700;display:flex;align-items:center;gap:5px;">
                <i class="ph ph-gift"></i> Credits included:
            </div>
            <div style="color:var(--fg);margin-top:3px;font-weight:600;">
                <?= e(plan_money((float)($plan['ad_credits_included'] ?? 0), $cur, $symbol)) ?> ad credits
                &middot; <?= (int)($plan['ai_credits_included'] ?? 0) ?> AI credits
            </div>
        </div>

        <ul style="list-style:none;padding:0;margin:0 0 20px;display:flex;flex-direction:column;gap:9px;flex:1;">
            <?php foreach ($features as $feature): ?>
                <li style="display:flex;gap:9px;align-items:flex-start;font-size:13px;color:var(--fg2);">
                    <i class="ph ph-check-circle" style="color:var(--green);font-size:16px;margin-top:1px;flex-shrink:0;"></i>
                    <span><?= e(is_string($feature) ? $feature : json_encode($feature)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="<?= url('billing') ?>?plan=<?= $planId ?>&cycle=monthly"
           class="plan-cta <?= $featured ? 'btn-accent' : 'btn-outline-dark' ?> btn-sm"
           style="width:100%;justify-content:center;text-decoration:none;">
            <i class="ph ph-check"></i>
            <span class="plan-cta-label">Choose Plan</span>
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
    <div class="card-dark" style="padding:24px;display:flex;flex-direction:column;position:relative;<?= $featured ? 'border-color:var(--accent);box-shadow:0 0 0 1px var(--accent);' : '' ?>">
        <span class="badge-pending" style="position:absolute;top:-11px;left:20px;"><i class="ph ph-lightning"></i> Pay once &mdash; never renews</span>

        <h3 style="font-weight:700;color:var(--fg);margin:0 0 4px;font-size:17px;"><?= e((string)($plan['name'] ?? 'Plan')) ?></h3>
        <?php if (!empty($plan['description'])): ?>
            <p style="font-size:13px;color:var(--fg2);margin:0 0 12px;"><?= e((string)$plan['description']) ?></p>
        <?php endif; ?>

        <p style="margin:0 0 4px;">
            <strong style="font-size:30px;font-weight:800;color:var(--fg);"><?= e(plan_money($usd, $cur, $symbol)) ?></strong>
            <span style="font-size:12px;color:var(--fg3);"> one-time</span>
        </p>
        <p style="margin:0 0 12px;font-size:11.5px;color:var(--fg3);"><?= e(plan_alt_hint($usd, $cur)) ?></p>

        <div style="background:rgba(200,255,0,0.08);border:1px solid rgba(200,255,0,0.25);border-radius:8px;padding:8px 10px;margin-bottom:14px;font-size:12px;">
            <div style="color:var(--accent);font-weight:700;display:flex;align-items:center;gap:5px;">
                <i class="ph ph-gift"></i> Credits included:
            </div>
            <div style="color:var(--fg);margin-top:3px;font-weight:600;">
                <?= e(plan_money((float)($plan['ad_credits_included'] ?? 0), $cur, $symbol)) ?> ad credits
                &middot; <?= (int)($plan['ai_credits_included'] ?? 0) ?> AI credits
            </div>
        </div>

        <ul style="list-style:none;padding:0;margin:0 0 20px;display:flex;flex-direction:column;gap:9px;flex:1;">
            <?php foreach ($features as $feature): ?>
                <li style="display:flex;gap:9px;align-items:flex-start;font-size:13px;color:var(--fg2);">
                    <i class="ph ph-check-circle" style="color:var(--green);font-size:16px;margin-top:1px;flex-shrink:0;"></i>
                    <span><?= e(is_string($feature) ? $feature : json_encode($feature)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="<?= url('billing') ?>?plan=<?= $planId ?>"
           class="<?= $featured ? 'btn-accent' : 'btn-outline-dark' ?> btn-sm"
           style="width:100%;justify-content:center;text-decoration:none;">
            <i class="ph ph-lightning"></i> Buy once
        </a>
    </div>
    <?php
}
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:20px">
        <div>
            <h1 class="page-title">Pricing Plans</h1>
            <p class="page-subtitle">Pay in your currency, switch anytime. Every plan includes direct-API publishing to all connected networks.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <!-- Currency switcher -->
            <div style="display:flex;border:1px solid var(--border);border-radius:999px;overflow:hidden;">
                <?php foreach (currency_supported() as $code => $sym): ?>
                    <a href="<?= currency_toggle_url($code) ?>" style="padding:7px 16px;font-size:13px;font-weight:700;text-decoration:none;<?= $cur === $code ? 'background:var(--accent);color:#0a0f1a;' : 'color:var(--fg2);' ?>">
                        <?= $sym ?> <?= $code ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-arrow-left"></i> Manage Billing
            </a>
        </div>
    </div>

    <?php if (empty($plans) && empty($oneOffs)): ?>
        <div class="card-dark" style="padding:48px 24px;text-align:center;">
            <div class="stat-icon" style="margin:0 auto 14px;background:rgba(200,255,0,.08);">
                <i class="ph ph-package" style="font-size:24px;color:var(--accent);"></i>
            </div>
            <h3 style="color:var(--fg);font-weight:700;margin:0 0 6px;">No plans available</h3>
            <p style="color:var(--fg2);font-size:13px;margin:0 0 16px;">Plans are being set up. Please check back soon.</p>
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm"><i class="ph ph-arrow-left"></i> Back to billing</a>
        </div>
    <?php else: ?>

    <!-- Cycle toggle -->
    <?php if (!empty($plans)): ?>
    <div style="display:flex;justify-content:center;margin-bottom:22px;">
        <div style="display:flex;border:1px solid var(--border);border-radius:999px;overflow:hidden;background:var(--bg);">
            <button type="button" id="cycleMonthlyBtn" onclick="setCycle('monthly')" style="padding:9px 26px;font-size:13.5px;font-weight:700;border:none;cursor:pointer;background:var(--accent);color:#0a0f1a;">Monthly</button>
            <button type="button" id="cycleYearlyBtn" onclick="setCycle('yearly')" style="padding:9px 26px;font-size:13.5px;font-weight:700;border:none;cursor:pointer;background:transparent;color:var(--fg2);">Yearly <span style="font-size:11px;color:var(--green);">&minus;25%</span></button>
        </div>
    </div>

    <!-- Subscription plans -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px;margin-bottom:26px;">
        <?php foreach ($plans as $plan) {
            plan_card($plan, $cur, $symbol);
        } ?>
    </div>
    <?php endif; ?>

    <!-- Pay-as-you-go plans -->
    <?php if (!empty($oneOffs)): ?>
    <div style="display:flex;align-items:center;gap:10px;margin:8px 0 14px;flex-wrap:wrap;">
        <h2 style="color:var(--fg);font-weight:800;font-size:18px;margin:0;"><i class="ph-bold ph-lightning text-warning me-1"></i> Pay As You Go</h2>
        <span style="font-size:12px;color:var(--fg3);">One payment unlocks the whole platform &mdash; credits stay lean so you only pay for what you use.</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px;">
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
    var on  = 'background:var(--accent);color:#0a0f1a;';
    var off = 'background:transparent;color:var(--fg2);';
    if (cycle === 'yearly') {
        y.style.cssText += ';' + on; m.style.cssText += ';' + off;
    } else {
        m.style.cssText += ';' + on; y.style.cssText += ';' + off;
    }

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
        if (ctaLbl) ctaLbl.textContent = yearly ? 'Start yearly - save 25%' : 'Choose Plan';
    });
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
