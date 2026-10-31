<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Pricing Plans - ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/app_header.php';

$cur    = currency_current();
$symbol = currency_supported()[$cur];

$plans = [];
try {
    $plans = db()->fetchAll("SELECT * FROM pricing_plans WHERE status = 'active' AND billing_cycle IN ('monthly','yearly') ORDER BY price ASC");
    $oneOffs = db()->fetchAll("SELECT * FROM pricing_plans WHERE status = 'active' AND billing_cycle = 'pay_as_you_go' ORDER BY price ASC");
} catch (\Throwable $e) {
    $plans = [];
    $oneOffs = [];
}

/**
 * Render one plan card.
 * $cycle: 'monthly' shows the base price; 'yearly' shows 25% off (billed yearly).
 */
function plan_card(array $plan, string $cycle, string $cur, string $symbol): void
{
    $usd     = (float)($plan['price'] ?? 0);
    $monthly = $cycle === 'yearly' ? round($usd * 12 * 0.75, 2) : $usd;   // 25% off, billed yearly
    $features = json_decode((string)($plan['features_json'] ?? '[]'), true);
    if (!is_array($features)) {
        $features = [];
    }
    $featured = !empty($plan['is_featured']) && $cycle === 'monthly';
    $isOneOff = ($plan['billing_cycle'] ?? '') === 'pay_as_you_go';

    $priceLabel = $isOneOff
        ? 'one-time'
        : ($cycle === 'yearly' ? '/ year' : '/ month');

    $cycleParam = $isOneOff ? '' : '&cycle=' . $cycle;
    ?>
    <div class="card-dark" style="padding:24px;display:flex;flex-direction:column;position:relative;<?= $featured ? 'border-color:var(--accent);box-shadow:0 0 0 1px var(--accent);' : '' ?>">
        <?php if ($featured): ?>
            <span class="badge-active" style="position:absolute;top:-11px;left:20px;"><i class="ph ph-crown"></i> Most Popular</span>
        <?php endif; ?>
        <?php if ($isOneOff): ?>
            <span class="badge-pending" style="position:absolute;top:-11px;left:20px;"><i class="ph ph-lightning"></i> Pay once — never renews</span>
        <?php endif; ?>
        <h3 style="font-weight:700;color:var(--fg);margin:0 0 4px;font-size:17px;"><?= e((string)($plan['name'] ?? 'Plan')) ?></h3>
        <?php if (!empty($plan['description'])): ?>
            <p style="font-size:13px;color:var(--fg2);margin:0 0 12px;"><?= e((string)$plan['description']) ?></p>
        <?php endif; ?>
        <p style="margin:0 0 4px;">
            <strong style="font-size:30px;font-weight:800;color:var(--fg);"><?= e($symbol . number_format(price_convert($monthly, $cur), $cur === 'NGN' ? 0 : 2)) ?></strong>
            <span style="font-size:12px;color:var(--fg3);"> <?= e($priceLabel) ?></span>
        </p>
        <p style="margin:0 0 12px;font-size:11.5px;color:var(--fg3);">
            <?= $cur === 'NGN'
                ? '(≈ $' . number_format($monthly, 2) . ' USD)'
                : '(≈ ₦' . number_format(price_convert($monthly, 'NGN'), 0) . ' NGN)' ?>
            <?php if ($cycle === 'yearly' && !$isOneOff): ?>
                · <span style="color:var(--green);font-weight:700;">25% saved vs monthly</span>
            <?php endif; ?>
        </p>

        <div style="background:rgba(200,255,0,0.08);border:1px solid rgba(200,255,0,0.25);border-radius:8px;padding:8px 10px;margin-bottom:14px;font-size:12px;">
            <div style="color:var(--accent);font-weight:700;display:flex;align-items:center;gap:5px;">
                <i class="ph ph-gift"></i> Credits included:
            </div>
            <div style="color:var(--fg);margin-top:3px;font-weight:600;">
                <?= price_fmt((float)($plan['ad_credits_included'] ?? 0), $cur) ?> ad credits · <?= (int)($plan['ai_credits_included'] ?? 0) ?> AI credits
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

        <a href="<?= url('billing') ?>?plan=<?= (int)$plan['id'] ?><?= e($cycleParam) ?>"
           class="<?= $featured ? 'btn-accent btn-sm' : 'btn-outline-dark btn-sm' ?>"
           style="width:100%;justify-content:center;text-decoration:none;">
            <i class="ph ph-<?= $isOneOff ? 'lightning' : 'check' ?>"></i>
            <?= $isOneOff ? 'Buy once' : ($cycle === 'yearly' ? 'Start yearly — save 25%' : 'Choose Plan') ?>
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
    <div style="display:flex;justify-content:center;margin-bottom:22px;">
        <div style="display:flex;border:1px solid var(--border);border-radius:999px;overflow:hidden;background:var(--bg);">
            <button type="button" id="cycleMonthlyBtn" onclick="setCycle('monthly')" style="padding:9px 26px;font-size:13.5px;font-weight:700;border:none;cursor:pointer;background:var(--accent);color:#0a0f1a;">Monthly</button>
            <button type="button" id="cycleYearlyBtn" onclick="setCycle('yearly')" style="padding:9px 26px;font-size:13.5px;font-weight:700;border:none;cursor:pointer;background:transparent;color:var(--fg2);">Yearly <span style="font-size:11px;color:var(--green);">−25%</span></button>
        </div>
    </div>

    <!-- Recurring plans -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px;margin-bottom:26px;">
        <?php foreach ($plans as $plan) {
            plan_card($plan, 'monthly', $cur, $symbol);
            plan_card($plan, 'yearly', $cur, $symbol);
        } ?>
    </div>

    <!-- One-off plans -->
    <?php if (!empty($oneOffs)): ?>
    <div style="display:flex;align-items:center;gap:10px;margin:8px 0 14px;">
        <h2 style="color:var(--fg);font-weight:800;font-size:18px;margin:0;"><i class="ph-bold ph-lightning text-warning me-1"></i> One-Off Plans</h2>
        <span style="font-size:12px;color:var(--fg3);">Most features unlocked forever — credits stay lean so you only pay for what you use.</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px;">
        <?php foreach ($oneOffs as $plan) {
            plan_card($plan, 'monthly', $cur, $symbol);
        } ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function setCycle(cycle) {
    const m = document.getElementById('cycleMonthlyBtn');
    const y = document.getElementById('cycleYearlyBtn');
    const on  = 'background:var(--accent);color:#0a0f1a;';
    const off = 'background:transparent;color:var(--fg2);';
    if (cycle === 'yearly') {
        y.style.cssText += ';' + on; m.style.cssText += ';' + off;
    } else {
        m.style.cssText += ';' + on; y.style.cssText += ';' + off;
    }
    document.querySelectorAll('[data-cycle-card]').forEach(el => {
        el.style.display = el.dataset.cycleCard === cycle ? '' : 'none';
    });
}
// Tag cards by cycle so the toggle can show/hide them.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.card-dark a[href*="cycle=yearly"]').forEach(a => {
        const card = a.closest('.card-dark');
        if (card) card.dataset.cycleCard = 'yearly';
    });
    document.querySelectorAll('.card-dark a[href*="plan="]:not([href*="cycle=yearly"])').forEach(a => {
        const card = a.closest('.card-dark');
        if (card && !card.dataset.cycleCard) card.dataset.cycleCard = 'monthly';
    });
    document.querySelectorAll('[data-cycle-card="yearly"]').forEach(el => el.style.display = 'none');
});
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
