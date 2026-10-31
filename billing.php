<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Billing & Wallet — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/payments.php';

$userId = current_user_id();
$user = current_user();
$message = '';
$selectedPlanId = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;
$selectedCycle  = ($_GET['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
$cur       = currency_current();
$minUsd    = 2.5;                                  // lowest top-up, USD
$minLocal  = price_convert($minUsd, $cur);         // lowest top-up in display currency

// Handle top-up + plan subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid security token.</div>';
    } else {
        $type = $_POST['action'];
        $gateway = strtolower(trim($_POST['gateway'] ?? 'paystack'));

        if (!in_array($gateway, ['paystack', 'flutterwave'])) {
            $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid payment gateway.</div>';
        } elseif ($type === 'subscribe_plan') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $cycle  = ($_POST['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
            $plan = $planId > 0 ? db()->fetchOne("SELECT * FROM pricing_plans WHERE id = ? AND status = 'active'", [$planId]) : null;
            if ($plan === null) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Selected plan is not available.</div>';
            } else {
                $isOneOff = ($plan['billing_cycle'] ?? '') === 'pay_as_you_go';
                $usdPrice = (float)($plan['price'] ?? 0);
                if ($cycle === 'yearly' && !$isOneOff) {
                    $usdPrice = round($usdPrice * 12 * 0.75, 2);   // 25% yearly discount
                }
                if ($usdPrice <= 0) {
                    $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid plan price.</div>';
                } else {
                    // Charge in the user's display currency; keep the USD value pinned.
                    $charge    = price_convert($usdPrice, $cur);
                    $usdPinned = $usdPrice;
                    $chargeCycle = $isOneOff ? 'monthly' : $cycle; // one-offs never renew
                    $result = PaymentProcessor::initiatePayment(
                        $userId,
                        'plan_subscription',
                        $charge,
                        $cur,
                        $gateway,
                        ['plan_id' => $planId, 'plan_name' => (string)($plan['name'] ?? ''), 'cycle' => $chargeCycle, 'amount_usd' => $usdPinned]
                    );
                    if ($result['success'] && isset($result['redirect_url'])) {
                        header('Location: ' . $result['redirect_url']);
                        exit;
                    } else {
                        $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">' . e($result['message'] ?? 'Payment failed.') . '</div>';
                    }
                }
            }
        } else {
            $amount = (float)($_POST['amount'] ?? 0);
            $usdAmount = $cur === 'NGN' ? round($amount / max(1, exchange_rate_ngn_per_usd()), 2) : $amount;
            if ($amount <= 0) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid amount.</div>';
            } elseif ($usdAmount < $minUsd) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Minimum top-up is ' . e(price_fmt($minUsd)) . '.</div>';
            } else {
                $creditType = ($type === 'topup_ai') ? 'ai_credit_topup' : 'ad_credit_topup';
                $result = PaymentProcessor::initiatePayment(
                    $userId,
                    $creditType,
                    $amount,
                    $cur,
                    $gateway,
                    ['amount_usd' => $usdAmount]
                );
                if ($result['success'] && isset($result['redirect_url'])) {
                    header('Location: ' . $result['redirect_url']);
                    exit;
                } else {
                    $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">' . e($result['message'] ?? 'Payment failed.') . '</div>';
                }
            }
        }
    }
}

$status = function_exists('integration_status') ? integration_status() : [];
$paystackLive = !empty($status['paystack']['configured']);
$flutterLive  = !empty($status['flutterwave']['configured']);
$anyLive = $paystackLive || $flutterLive;

$plans = [];
try {
    $plans = db()->fetchAll("SELECT * FROM pricing_plans WHERE status = 'active' ORDER BY price ASC");
} catch (\Throwable $e) {
    $plans = [];
}

$transactions = db()->fetchAll(
    "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
    [$userId]
);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <?= $message ?>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
            <h1 class="page-title">Billing & Wallet</h1>
            <p class="page-subtitle">Top up credits, upgrade your plan, and track every transaction.</p>
        </div>
        <div style="display:flex;border:1px solid var(--border);border-radius:999px;overflow:hidden;">
            <?php foreach (currency_supported() as $code => $sym): ?>
                <a href="<?= currency_toggle_url($code) ?>" style="padding:7px 16px;font-size:13px;font-weight:700;text-decoration:none;<?= $cur === $code ? 'background:var(--accent);color:#0a0f1a;' : 'color:var(--fg2);' ?>">
                    <?= $sym ?> <?= $code ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$anyLive): ?>
        <div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--orange);display:flex;gap:12px;align-items:flex-start;">
            <i class="ph ph-warning-circle" style="font-size:20px;color:var(--orange);flex-shrink:0;margin-top:2px;"></i>
            <div style="font-size:13px;color:var(--fg2);">
                <strong style="color:var(--fg);">Test mode:</strong> no live payment keys detected.
                Checkout runs in test mode until keys are added in <strong style="color:var(--fg);">config/apikeys.php</strong>.
            </div>
        </div>
    <?php endif; ?>

    <!-- Gateway selector -->
    <div class="card-dark" style="padding:20px 22px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <div class="stat-icon" style="background:rgba(34,197,94,.1);">
                <i class="ph ph-shield-check" style="font-size:18px;color:var(--green);"></i>
            </div>
            <div>
                <h3 style="font-weight:700;color:var(--fg);margin:0;font-size:15px;">Payment Gateway</h3>
                <p style="font-size:12px;color:var(--fg3);margin:0;">Selected gateway applies to top-ups and plan subscriptions.</p>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            <label id="gwPaystackCard" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--accent);border-radius:var(--radius);cursor:pointer;background:var(--bg2);">
                <input type="radio" name="gateway_choice" value="paystack" checked style="accent-color:var(--accent);">
                <i class="ph ph-bank" style="font-size:20px;color:var(--accent);"></i>
                <span style="flex:1;">
                    <strong style="display:block;color:var(--fg);font-size:14px;">Paystack</strong>
                    <span style="font-size:12px;color:var(--fg3);">Cards, transfers &amp; mobile money</span>
                </span>
                <?php if ($paystackLive): ?>
                    <span class="badge-active">Live</span>
                <?php else: ?>
                    <span class="badge-pending">Test mode</span>
                <?php endif; ?>
            </label>
            <label id="gwFlutterwaveCard" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;background:var(--bg2);">
                <input type="radio" name="gateway_choice" value="flutterwave" style="accent-color:var(--accent);">
                <i class="ph ph-wallet" style="font-size:20px;color:var(--blue);"></i>
                <span style="flex:1;">
                    <strong style="display:block;color:var(--fg);font-size:14px;">Flutterwave</strong>
                    <span style="font-size:12px;color:var(--fg3);">Cards, USSD &amp; bank payments</span>
                </span>
                <?php if ($flutterLive): ?>
                    <span class="badge-active">Live</span>
                <?php else: ?>
                    <span class="badge-pending">Test mode</span>
                <?php endif; ?>
            </label>
        </div>
    </div>

    <!-- Smart top-up suggestions -->
    <?php
    $aiNeeded = max(0, 400 - (int)($user['ai_credits'] ?? 0));      // ~a month of daily creating
    $adNeeded = max(0.0, 20.0 - (float)($user['ad_credits'] ?? 0)); // ~a small test campaign
    $suggestions = [];
    if ((int)($user['ai_credits'] ?? 0) < 100) {
        $suggestions[] = ['icon' => 'ph-sparkle', 'text' => 'AI credits are low — ' . price_fmt(5) . ' adds 500 credits (≈100 images or 500 scripts).', 'amt' => 5, 'target' => 'aiAmount'];
    } elseif ($aiNeeded > 0) {
        $suggestions[] = ['icon' => 'ph-sparkle', 'text' => 'A month of daily creating needs ~400 AI credits — you have ' . number_format((int)($user['ai_credits'] ?? 0)) . '.', 'amt' => ceil($aiNeeded / 100), 'target' => 'aiAmount'];
    }
    if ($adNeeded > 0) {
        $suggestions[] = ['icon' => 'ph-megaphone', 'text' => 'Most first campaigns test with ' . price_fmt(20) . '–' . price_fmt(50) . ' of ad credit across 2–3 networks.', 'amt' => 20, 'target' => 'adAmount'];
    }
    if ((int)($user['plan_id'] ?? 0) === 0) {
        $suggestions[] = ['icon' => 'ph-lightning', 'text' => 'The One-Off Power Pack unlocks every pro feature for a single payment — no subscription.', 'amt' => null, 'target' => null];
    }
    ?>
    <?php if (!empty($suggestions)): ?>
    <div class="card-dark" style="padding:16px 20px;margin-bottom:20px;border-left:3px solid var(--accent);">
        <div style="font-size:12px;font-weight:800;color:var(--accent);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
            <i class="ph-bold ph-lightbulb me-1"></i> Smart Suggestions
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($suggestions as $sg): ?>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <i class="ph <?= $sg['icon'] ?>" style="color:var(--fg2);font-size:18px;"></i>
                <span style="font-size:13px;color:var(--fg2);flex:1;min-width:220px;"><?= e($sg['text']) ?></span>
                <?php if ($sg['amt'] !== null && $sg['target'] !== null): ?>
                    <button type="button" class="btn-outline-dark btn-sm quick-amt" data-target="<?= e($sg['target']) ?>" data-amt="<?= price_convert((float)$sg['amt'], $cur) ?>">
                        Add <?= e(price_fmt((float)$sg['amt'], $cur)) ?>
                    </button>
                <?php else: ?>
                    <a href="<?= url('pricing-plan') ?>" class="btn-outline-dark btn-sm">See plans</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Wallet top-up cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:20px;">
        <!-- Ad Credits -->
        <div class="card-dark stat-card" style="padding:22px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div class="stat-icon" style="background:rgba(200,255,0,.1);">
                    <i class="ph ph-megaphone" style="font-size:20px;color:var(--accent);"></i>
                </div>
                <div>
                    <p class="stat-label" style="margin:0;">Ad Credits</p>
                    <h3 class="stat-value" style="margin:0;">$<?= number_format((float)($user['ad_credits'] ?? 0), 2) ?></h3>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <?php foreach ([2.5, 5, 10, 25, 50, 100] as $q): ?>
                    <button type="button" class="btn-outline-dark btn-sm quick-amt" data-target="adAmount" data-amt="<?= price_convert((float)$q, $cur) ?>"><?= e(price_fmt((float)$q, $cur)) ?></button>
                <?php endforeach; ?>
            </div>
            <form method="POST" style="display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="topup_ad">
                <input type="hidden" name="gateway" value="paystack" class="gateway-field">
                <input type="number" id="adAmount" name="amount" class="form-control" placeholder="Amount (<?= e($cur) ?>)" min="<?= e((string)$minLocal) ?>" step="0.01" required style="flex:1;">
                <button type="submit" class="btn-accent btn-sm"><i class="ph ph-plus"></i> Top Up</button>
            </form>
            <p style="font-size:11px;color:var(--fg3);margin:8px 0 0;">Min <?= e(price_fmt($minUsd, $cur)) ?> · 100 AI credits per $1</p>
        </div>
        <!-- AI Credits -->
        <div class="card-dark stat-card" style="padding:22px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div class="stat-icon" style="background:rgba(96,165,250,.12);">
                    <i class="ph ph-sparkle" style="font-size:20px;color:var(--blue);"></i>
                </div>
                <div>
                    <p class="stat-label" style="margin:0;">AI Credits</p>
                    <h3 class="stat-value" style="margin:0;"><?= number_format((int)($user['ai_credits'] ?? 0)) ?></h3>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <?php foreach ([2.5, 5, 10, 25, 50, 100] as $q): ?>
                    <button type="button" class="btn-outline-dark btn-sm quick-amt" data-target="aiAmount" data-amt="<?= price_convert((float)$q, $cur) ?>"><?= e(price_fmt((float)$q, $cur)) ?></button>
                <?php endforeach; ?>
            </div>
            <form method="POST" style="display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="topup_ai">
                <input type="hidden" name="gateway" value="paystack" class="gateway-field">
                <input type="number" id="aiAmount" name="amount" class="form-control" placeholder="Amount (<?= e($cur) ?>)" min="<?= e((string)$minLocal) ?>" step="0.01" required style="flex:1;">
                <button type="submit" class="btn-accent btn-sm"><i class="ph ph-plus"></i> Top Up</button>
            </form>
            <p style="font-size:11px;color:var(--fg3);margin:8px 0 0;">Min <?= e(price_fmt($minUsd, $cur)) ?> · text 1 credit · image/video 5 credits</p>
        </div>
    </div>

    <!-- Plans upgrade -->
    <div class="card-dark" style="padding:22px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="stat-icon" style="background:rgba(200,255,0,.1);">
                    <i class="ph ph-crown" style="font-size:18px;color:var(--accent);"></i>
                </div>
                <div>
                    <h3 style="font-weight:700;color:var(--fg);margin:0;font-size:15px;">Upgrade Your Plan</h3>
                    <p style="font-size:12px;color:var(--fg3);margin:0;">Unlock higher limits and premium features.</p>
                </div>
            </div>
            <a href="<?= url('pricing-plan') ?>" class="btn-outline-dark btn-sm"><i class="ph ph-grid-four"></i> View all plans</a>
        </div>

        <?php if (empty($plans)): ?>
            <div style="text-align:center;padding:32px 16px;border:1px dashed var(--border);border-radius:var(--radius);">
                <i class="ph ph-package" style="font-size:32px;color:var(--fg3);"></i>
                <p style="color:var(--fg);font-weight:600;margin:10px 0 4px;">No plans available right now</p>
                <p style="color:var(--fg2);font-size:13px;margin:0;">Check back soon — new plans are on the way.</p>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px;">
                <?php foreach ($plans as $plan):
                    $pFeatures = json_decode($plan['features_json'] ?? $plan['features'] ?? '[]', true);
                    if (!is_array($pFeatures)) $pFeatures = [];
                    $isSel = $selectedPlanId > 0 && (int)$plan['id'] === $selectedPlanId;
                ?>
                    <div class="card-dark" style="padding:20px;<?= $isSel ? 'border-color:var(--accent);' : '' ?>">
                        <?php if (!empty($plan['is_featured'])): ?>
                            <span class="badge-active" style="margin-bottom:10px;display:inline-block;">Most Popular</span>
                        <?php endif; ?>
                        <h4 style="font-weight:700;color:var(--fg);margin:0 0 4px;font-size:16px;"><?= e((string)($plan['name'] ?? 'Plan')) ?></h4>
                        <?php $isOneOffPlan = ($plan['billing_cycle'] ?? '') === 'pay_as_you_go'; ?>
                        <p style="margin:0 0 10px;">
                            <strong style="font-size:24px;font-weight:800;color:var(--fg);"><?= e(price_fmt((float)($plan['price'] ?? 0), $cur)) ?></strong>
                            <span style="font-size:12px;color:var(--fg3);"> / <?= $isOneOffPlan ? 'one-time' : e((string)($plan['billing_cycle'] ?? 'month')) ?></span>
                        </p>
                        <p style="margin:0 0 10px;font-size:11.5px;color:var(--fg3);">(≈ <?= $cur === 'NGN' ? '$' . number_format((float)($plan['price'] ?? 0), 2) . ' USD' : '₦' . number_format(price_convert((float)($plan['price'] ?? 0), 'NGN'), 0) . ' NGN' ?>)</p>

                        <!-- Bundled Credits Pill -->
                        <div style="background:rgba(200,255,0,0.08);border:1px solid rgba(200,255,0,0.25);border-radius:8px;padding:8px 10px;margin-bottom:12px;font-size:12px;">
                            <div style="color:var(--accent);font-weight:700;display:flex;align-items:center;gap:5px;">
                                <i class="ph ph-gift"></i> Bundled Credits Included:
                            </div>
                            <div style="color:var(--fg);margin-top:3px;font-weight:600;">
                                <?= e(price_fmt((float)($plan['ad_credits_included'] ?? 0), $cur)) ?> Ad Credits • <?= (int)($plan['ai_credits_included'] ?? 0) ?> AI Credits
                            </div>
                        </div>

                        <?php if (!empty($pFeatures)): ?>
                            <ul style="list-style:none;padding:0;margin:12px 0;display:flex;flex-direction:column;gap:8px;">
                                <?php foreach (array_slice($pFeatures, 0, 5) as $f): ?>
                                    <li style="display:flex;gap:8px;align-items:flex-start;font-size:13px;color:var(--fg2);">
                                        <i class="ph ph-check-circle" style="color:var(--green);font-size:15px;margin-top:1px;"></i>
                                        <span><?= e(is_string($f) ? $f : json_encode($f)) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <form method="POST" style="margin-top:14px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="subscribe_plan">
                            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                            <input type="hidden" name="gateway" value="paystack" class="gateway-field">
                            <?php if ($isOneOffPlan): ?>
                                <input type="hidden" name="cycle" value="monthly">
                            <?php else: ?>
                                <select name="cycle" class="form-control" style="background:#0d1524;border:1px solid var(--border);color:var(--fg);font-size:12.5px;margin-bottom:8px;">
                                    <option value="monthly" <?= $selectedCycle === 'monthly' ? 'selected' : '' ?>>Monthly — <?= e(price_fmt((float)($plan['price'] ?? 0), $cur)) ?></option>
                                    <option value="yearly" <?= $selectedCycle === 'yearly' ? 'selected' : '' ?>>Yearly — <?= e(price_fmt(round((float)($plan['price'] ?? 0) * 12 * 0.75, 2), $cur)) ?> (save 25%)</option>
                                </select>
                            <?php endif; ?>
                            <button type="submit" class="btn-accent btn-sm" style="width:100%;justify-content:center;">
                                <i class="ph ph-<?= $isOneOffPlan ? 'lightning' : 'crown' ?>"></i>
                                <?= $isOneOffPlan ? 'Buy once — ' : 'Subscribe — ' ?><?= e(price_fmt((float)($plan['price'] ?? 0), $cur)) ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Transaction history -->
    <div class="card-dark" style="padding:22px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <div class="stat-icon" style="background:rgba(34,197,94,.1);">
                <i class="ph ph-receipt" style="font-size:18px;color:var(--green);"></i>
            </div>
            <h3 style="font-weight:700;margin:0;color:var(--fg);font-size:15px;">Transaction History</h3>
        </div>
        <?php if (empty($transactions)): ?>
            <div style="text-align:center;padding:28px 16px;border:1px dashed var(--border);border-radius:var(--radius);">
                <i class="ph ph-receipt" style="font-size:28px;color:var(--fg3);"></i>
                <p style="color:var(--fg2);font-size:13px;margin:8px 0 0;">No transactions yet. Your top-ups and subscriptions will appear here.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table-dark" style="width:100%;margin:0;min-width:640px;">
                    <thead>
                        <tr>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Type</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Gateway</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Amount</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Status</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t):
                            $sClass = match ($t['status'] ?? '') {
                                'successful', 'completed', 'success' => 'badge-active',
                                'pending'    => 'badge-pending',
                                'failed'     => 'badge-danger',
                                default      => 'badge-inactive',
                            };
                        ?>
                            <tr>
                                <td style="font-size:13px;color:var(--fg);text-transform:capitalize;padding:10px 12px;"><?= e(str_replace('_', ' ', (string)($t['type'] ?? ''))) ?></td>
                                <td style="font-size:13px;color:var(--fg);text-transform:capitalize;padding:10px 12px;"><?= e((string)($t['gateway'] ?? '—')) ?></td>
                                <td style="font-weight:600;color:var(--fg);padding:10px 12px;">$<?= number_format((float)($t['amount'] ?? 0), 2) ?></td>
                                <td style="padding:10px 12px;">
                                    <span class="<?= $sClass ?>" style="text-transform:capitalize;"><?= e((string)($t['status'] ?? 'unknown')) ?></span>
                                </td>
                                <td style="font-size:13px;color:var(--fg2);padding:10px 12px;"><?= !empty($t['created_at']) ? date('M d, Y H:i', strtotime((string)$t['created_at'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.quick-amt').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.getElementById(btn.getAttribute('data-target'));
        if (el) { el.value = btn.getAttribute('data-amt'); el.focus(); }
    });
});
function syncGateway() {
    var sel = document.querySelector('input[name="gateway_choice"]:checked');
    var gw = sel ? sel.value : 'paystack';
    document.querySelectorAll('.gateway-field').forEach(function (f) { f.value = gw; });
    var p = document.getElementById('gwPaystackCard');
    var fl = document.getElementById('gwFlutterwaveCard');
    if (p) p.style.borderColor = gw === 'paystack' ? 'var(--accent)' : 'var(--border)';
    if (fl) fl.style.borderColor = gw === 'flutterwave' ? 'var(--accent)' : 'var(--border)';
}
document.querySelectorAll('input[name="gateway_choice"]').forEach(function (r) {
    r.addEventListener('change', syncGateway);
});
syncGateway();
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
