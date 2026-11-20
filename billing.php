<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Billing & Wallet — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/payments.php';
require_once __DIR__ . '/modules/rate_sheet.php';

$userId = current_user_id();
$user = current_user();
$message = '';
$selectedPlanId = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;
$selectedCycle  = ($_GET['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
$cur       = currency_current();
$minNgn    = RateSheet::minTopupNgn();   // ₦15,000 minimum top-up (ads/AI credits)
$minLocal  = $minNgn;

// ── Email an existing invoice (owner only) ──────────────────────────────
// Re-sends the generated invoice for a PAID transaction to the account
// email. The invoice is (re)generated first so the mail always links a file
// that exists on disk.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'email_invoice') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token. Please refresh and try again.');
        redirect(url('billing'));
    }
    $txId = (int)($_POST['transaction_id'] ?? 0);
    $tx = db()->fetchOne(
        "SELECT * FROM transactions
          WHERE id = ? AND user_id = ? AND status IN ('successful','completed','success')",
        [$txId, $userId]
    );
    if ($tx === null) {
        set_flash('error', 'That paid transaction was not found on your account.');
        redirect(url('billing'));
    }
    try {
        if (!class_exists('InvoiceGenerator')) {
            require_once __DIR__ . '/modules/invoice_generator.php';
        }
        if (!class_exists('Notifier')) {
            require_once __DIR__ . '/modules/notifier.php';
        }
        $invoicePath = InvoiceGenerator::generate($txId);
        db()->update('transactions', ['invoice_path' => $invoicePath], 'id = ?', [$txId]);

        $invNumber = (string)($tx['invoice_number'] ?? ('INV-' . $txId));
        $sent = Notifier::user($userId, 'invoice_ready', [
            'invoice_number' => $invNumber,
            'amount'          => txn_money((float)($tx['amount'] ?? 0), $tx['currency'] ?? null),
            'payment_method'  => ucfirst((string)($tx['gateway'] ?? 'wallet')),
            'invoice_link'    => BASE_URL . '/invoice?id=' . $txId,
            'subject'         => 'Invoice ' . $invNumber . ' is ready',
            'summary'         => 'Invoice ' . $invNumber . ' for ' . txn_money((float)($tx['amount'] ?? 0), $tx['currency'] ?? null) . ' was issued for your verified payment.',
            'link'            => '/invoice.php?id=' . $txId,
        ]);
        if ($sent) {
            set_flash('success', 'Invoice ' . $invNumber . ' was emailed to ' . e((string)($user['email'] ?? 'your account email')) . '.');
        } else {
            set_flash('error', 'The invoice was generated, but the email could not be sent. Open the invoice from the table and download it instead.');
        }
    } catch (\Throwable $e) {
        error_log('Invoice email failed for txn ' . $txId . ': ' . $e->getMessage());
        set_flash('error', 'Could not email that invoice: ' . $e->getMessage());
    }
    redirect(url('billing'));
}

// ── Lead-list download (owner only) ─────────────────────────────────────
// Delivered lead packs download straight from the Billing page.
if (isset($_GET['download_lead'])) {
    $orderId = (int)$_GET['download_lead'];
    $order = db()->fetchOne(
        "SELECT * FROM lead_orders WHERE id = ? AND user_id = ? AND status = 'delivered'",
        [$orderId, $userId]
    );
    if ($order === null || empty($order['deliverable_path'])) {
        set_flash('error', 'That lead list is not available for download yet.');
        redirect(url('billing'));
    }
    $file = ROOT_PATH . '/' . ltrim((string)$order['deliverable_path'], '/');
    $real = realpath($file);
    $base = realpath(ROOT_PATH . '/uploads/leads');
    if ($real === false || $base === false || !str_starts_with($real, $base) || !is_file($real)) {
        set_flash('error', 'The lead list file is missing — contact support with order #' . $orderId . '.');
        redirect(url('billing'));
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="adsbooster-leads-' . (int)$order['id'] . '.csv"');
    header('Content-Length: ' . (string)filesize($real));
    readfile($real);
    exit;
}

// Handle top-up + plan subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid security token.</div>';
    } else {
        $type = $_POST['action'];
        $gateway = strtolower(trim($_POST['gateway'] ?? ''));

        $enabledGateways = PaymentGateways::enabled();
        $defaultGateway  = PaymentGateways::defaultGateway();

        if ($gateway === '' && $defaultGateway !== null) {
            $gateway = $defaultGateway;
        }

        if (!in_array($gateway, $enabledGateways, true)) {
            $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">'
                . e($gateway !== '' && isset(PaymentGateways::REGISTRY[$gateway])
                    ? PaymentGateways::config($gateway)['label'] . ' is not available. ' . PaymentGateways::unavailableReason($gateway)
                    : 'No payment method is currently available. Please contact support.')
                . '</div>';
        } elseif ($type === 'subscribe_plan') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $cycle  = ($_POST['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
            $plan = $planId > 0 ? db()->fetchOne("SELECT * FROM pricing_plans WHERE id = ? AND status = 'active'", [$planId]) : null;
            if ($plan === null) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Selected plan is not available.</div>';
            } else {
                $isOneOff = ($plan['billing_cycle'] ?? '') === 'pay_as_you_go';
                // Plan prices are NGN-native (the platform currency) — the
                // `price` column holds naira, never USD-canonical dollars.
                $planNgn = (float)($plan['price_ngn'] ?? $plan['price'] ?? 0);
                if ($cycle === 'yearly' && !$isOneOff) {
                    $planNgn = round($planNgn * 12 * 0.75, 2);   // 25% yearly discount
                }
                if ($planNgn <= 0) {
                    $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid plan price.</div>';
                } else {
                    // Charge in naira; keep the USD-equivalent pinned for the
                    // ledger so later settlements never depend on a moving
                    // exchange rate.
                    $charge    = $planNgn;
                    $usdPinned = round($planNgn / max(1.0, exchange_rate_ngn_per_usd()), 2);
                    $isLeadPack = RateSheet::isLeadPackPlan($plan);
                    $chargeCycle = ($isOneOff || $isLeadPack) ? 'monthly' : $cycle; // one-offs never renew

                    // The '1,000 Leads' product is NOT a subscription — it must
                    // open a fulfilment order (lead_orders), not a plan.
                    $txType = $isLeadPack ? 'lead_pack' : 'plan_subscription';
                    $meta = [
                        'plan_id'    => $planId,
                        'plan_name'  => (string)($plan['name'] ?? ''),
                        'cycle'      => $chargeCycle,
                        'amount_usd' => $usdPinned,
                    ];
                    if ($isLeadPack) {
                        $meta['leads_count'] = RateSheet::leadPackCount();
                    }

                    $result = PaymentProcessor::initiatePayment(
                        $userId,
                        $txType,
                        $charge,
                        $cur,
                        $gateway,
                        $meta
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
            if ($amount <= 0) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid amount.</div>';
            } elseif ($amount < $minNgn) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Minimum top-up is ' . e(price_ngn_fmt($minNgn)) . '.</div>';
            } else {
                $creditType = ($type === 'topup_ai') ? 'ai_credit_topup' : 'ad_credit_topup';
                $result = PaymentProcessor::initiatePayment(
                    $userId,
                    $creditType,
                    $amount,
                    $cur,
                    $gateway,
                    ['amount_usd' => round($amount / max(1, exchange_rate_ngn_per_usd()), 2)]
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

// ── Gateway registry ─────────────────────────────────────────────────────
// Only fully configured gateways are shown to the customer. A gateway with
// missing credentials (e.g. Paystack on this deployment) is switched off
// automatically and never reaches the checkout screen.
$gatewayConfigs = PaymentGateways::all();
$enabledGateways = PaymentGateways::enabled();
$defaultGateway  = PaymentGateways::defaultGateway();
$anyLive = $enabledGateways !== [];

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

// Lead-list orders for the '1,000 Leads' product. The table is created by
// SchemaGuard, but a deployment that cannot ALTER the schema must still
// render the rest of the Billing page.
$leadOrders = [];
try {
    $leadOrders = db()->fetchAll(
        "SELECT * FROM lead_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
        [$userId]
    );
} catch (\Throwable $e) {
    error_log('Billing: lead_orders unavailable — ' . $e->getMessage());
    $leadOrders = [];
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <?= $message ?>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
            <h1 class="page-title">Billing &amp; Wallet</h1>
            <p class="page-subtitle">Top up credits, upgrade your plan, and track every transaction. All amounts in Nigerian Naira (₦).</p>
        </div>
    </div>

    <?php if (!$anyLive): ?>
        <div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--orange);display:flex;gap:12px;align-items:flex-start;">
            <i class="ph ph-warning-circle" style="font-size:20px;color:var(--orange);flex-shrink:0;margin-top:2px;"></i>
            <div style="font-size:13px;color:var(--fg2);">
                <strong style="color:var(--fg);">Payments are offline:</strong> no payment gateway is fully configured on this deployment.
                Top-ups and plan upgrades are paused until an administrator adds gateway keys in
                <strong style="color:var(--fg);">config/apikeys.php</strong> or <strong style="color:var(--fg);">Admin &rarr; Settings</strong>.
            </div>
        </div>
    <?php endif; ?>

    <!-- Gateway selector — only fully configured gateways are offered -->
    <?php if ($anyLive): ?>
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
            <?php foreach ($enabledGateways as $gatewayKey): ?>
                <?php $gw = $gatewayConfigs[$gatewayKey]; ?>
                <label id="gw-<?= e($gatewayKey) ?>Card"
                       style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;background:var(--bg2);">
                    <input type="radio" name="gateway_choice" value="<?= e($gatewayKey) ?>"
                           <?= $gatewayKey === $defaultGateway ? 'checked' : '' ?> style="accent-color:var(--accent);">
                    <i class="ph <?= e($gw['icon']) ?>" style="font-size:20px;color:<?= e($gw['colour']) ?>;"></i>
                    <span style="flex:1;">
                        <strong style="display:block;color:var(--fg);font-size:14px;"><?= e($gw['label']) ?></strong>
                        <span style="font-size:12px;color:var(--fg3);"><?= e($gw['blurb']) ?></span>
                    </span>
                    <span class="badge-active">Live</span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Smart top-up suggestions -->
    <?php
    $aiNeeded = max(0, 400 - (int)($user['ai_credits'] ?? 0));      // ~a month of daily creating
    $adNeeded = max(0.0, RateSheet::minTopupNgn() - (float)($user['ad_credits'] ?? 0));
    $suggestions = [];
    if ((int)($user['ai_credits'] ?? 0) < 100) {
        $suggestions[] = ['icon' => 'ph-sparkle', 'text' => 'AI credits are low — ' . price_ngn_fmt(RateSheet::minTopupNgn()) . ' adds ' . number_format((int)round(RateSheet::minTopupNgn() / max(1.0, exchange_rate_ngn_per_usd()) * 100)) . ' credits (≈100 images or 500 scripts).', 'amt' => RateSheet::minTopupNgn(), 'target' => 'aiAmount'];
    } elseif ($aiNeeded > 0) {
        $suggestions[] = ['icon' => 'ph-sparkle', 'text' => 'A month of daily creating needs ~400 AI credits — you have ' . number_format((int)($user['ai_credits'] ?? 0)) . '.', 'amt' => RateSheet::minTopupNgn(), 'target' => 'aiAmount'];
    }
    if ($adNeeded > 0) {
        $suggestions[] = ['icon' => 'ph-megaphone', 'text' => 'A first campaign runs on your money — top up ' . price_ngn_fmt(RateSheet::minTopupNgn()) . ' and it keeps running until the budget is spent (' . number_format(RateSheet::leadsForBudget(RateSheet::minTopupNgn(), 'campaign')) . ' leads at ' . price_ngn_fmt(RateSheet::ratePer1kLeads('campaign')) . ' per 1,000).', 'amt' => RateSheet::minTopupNgn(), 'target' => 'adAmount'];
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
                    <button type="button" class="btn-outline-dark btn-sm quick-amt" data-target="<?= e($sg['target']) ?>" data-amt="<?= e((string)(float)$sg['amt']) ?>">
                        Add <?= e(price_ngn_fmt((float)$sg['amt'])) ?>
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
                    <h3 class="stat-value" style="margin:0;"><?= e(price_ngn_fmt((float)($user['ad_credits'] ?? 0))) ?></h3>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <?php foreach ([15000, 25000, 50000, 100000, 250000, 500000] as $q): ?>
                    <button type="button" class="btn-outline-dark btn-sm quick-amt" data-target="adAmount" data-amt="<?= e((string)(float)$q) ?>"><?= e(price_ngn_fmt((float)$q)) ?></button>
                <?php endforeach; ?>
            </div>
            <form method="POST" style="display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="topup_ad">
                <input type="hidden" name="gateway" value="<?= e($defaultGateway ?? '') ?>" class="gateway-field">
                <input type="number" id="adAmount" name="amount" class="form-control" placeholder="Amount (<?= e($cur) ?>)" min="<?= e((string)$minLocal) ?>" step="0.01" required style="flex:1;">
                <button type="submit" class="btn-accent btn-sm"><i class="ph ph-plus"></i> Top Up</button>
            </form>
            <p style="font-size:11px;color:var(--fg3);margin:8px 0 0;">Min <?= e(price_ngn_fmt($minNgn)) ?> · ads convert at <?= e(price_ngn_fmt(RateSheet::ratePer1kLeads('campaign'))) ?> per 1,000 leads</p>
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
                <?php foreach ([15000, 25000, 50000, 100000, 250000, 500000] as $q): ?>
                    <button type="button" class="btn-outline-dark btn-sm quick-amt" data-target="aiAmount" data-amt="<?= e((string)(float)$q) ?>"><?= e(price_ngn_fmt((float)$q)) ?></button>
                <?php endforeach; ?>
            </div>
            <form method="POST" style="display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="topup_ai">
                <input type="hidden" name="gateway" value="<?= e($defaultGateway ?? '') ?>" class="gateway-field">
                <input type="number" id="aiAmount" name="amount" class="form-control" placeholder="Amount (<?= e($cur) ?>)" min="<?= e((string)$minLocal) ?>" step="0.01" required style="flex:1;">
                <button type="submit" class="btn-accent btn-sm"><i class="ph ph-plus"></i> Top Up</button>
            </form>
            <p style="font-size:11px;color:var(--fg3);margin:8px 0 0;">Min <?= e(price_ngn_fmt($minNgn)) ?> · text 1 credit · image/video 5 credits</p>
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
                        <?php $planNgn = (float)($plan['price_ngn'] ?? $plan['price'] ?? 0); ?>
                        <p style="margin:0 0 10px;">
                            <strong style="font-size:24px;font-weight:800;color:var(--fg);"><?= e(price_ngn_fmt($planNgn)) ?></strong>
                            <span style="font-size:12px;color:var(--fg3);"> / <?= $isOneOffPlan ? 'one-time' : e((string)($plan['billing_cycle'] ?? 'month')) ?></span>
                        </p>
                        <p style="margin:0 0 10px;font-size:11.5px;color:var(--fg3);">(charged in ₦ NGN)</p>

                        <!-- Bundled Credits Pill -->
                        <div style="background:rgba(200,255,0,0.08);border:1px solid rgba(200,255,0,0.25);border-radius:8px;padding:8px 10px;margin-bottom:12px;font-size:12px;">
                            <div style="color:var(--accent);font-weight:700;display:flex;align-items:center;gap:5px;">
                                <i class="ph ph-gift"></i> Bundled Credits Included:
                            </div>
                            <div style="color:var(--fg);margin-top:3px;font-weight:600;">
                                <?= e(price_ngn_fmt((float)($plan['ad_credits_included'] ?? 0))) ?> Ad Credits • <?= (int)($plan['ai_credits_included'] ?? 0) ?> AI Credits
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
                            <input type="hidden" name="gateway" value="<?= e($defaultGateway ?? '') ?>" class="gateway-field">
                            <?php if ($isOneOffPlan): ?>
                                <input type="hidden" name="cycle" value="monthly">
                            <?php else: ?>
                                <select name="cycle" class="form-control" style="background:#0d1524;border:1px solid var(--border);color:var(--fg);font-size:12.5px;margin-bottom:8px;">
                                    <option value="monthly" <?= $selectedCycle === 'monthly' ? 'selected' : '' ?>>Monthly — <?= e(price_ngn_fmt($planNgn)) ?></option>
                                    <option value="yearly" <?= $selectedCycle === 'yearly' ? 'selected' : '' ?>>Yearly — <?= e(price_ngn_fmt(round($planNgn * 12 * 0.75, 2))) ?> (save 25%)</option>
                                </select>
                            <?php endif; ?>
                            <button type="submit" class="btn-accent btn-sm" style="width:100%;justify-content:center;">
                                <i class="ph ph-<?= $isOneOffPlan ? 'lightning' : 'crown' ?>"></i>
                                <?= $isOneOffPlan ? 'Buy once — ' : 'Subscribe — ' ?><?= e(price_ngn_fmt($planNgn)) ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lead-list orders -->
    <div class="card-dark" style="padding:22px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <div class="stat-icon" style="background:rgba(200,255,0,.1);">
                <i class="ph ph-users-three" style="font-size:18px;color:var(--accent);"></i>
            </div>
            <div>
                <h3 style="font-weight:700;margin:0;color:var(--fg);font-size:15px;"><?= e(RateSheet::leadPackName()) ?> — Lead List Orders</h3>
                <p style="font-size:12px;color:var(--fg3);margin:0;">
                    <?= e(price_ngn_fmt(RateSheet::packPrice())) ?> per <?= number_format(RateSheet::leadPackCount()) ?> leads.
                    Every order is fulfilled manually by the Adsbooster team — you get a download link the moment it ships.
                </p>
            </div>
        </div>
        <?php if (empty($leadOrders)): ?>
            <div style="text-align:center;padding:26px 16px;border:1px dashed var(--border);border-radius:var(--radius);">
                <i class="ph ph-list-bullets" style="font-size:26px;color:var(--fg3);"></i>
                <p style="color:var(--fg2);font-size:13px;margin:8px 0 0;">
                    No lead-list orders yet.
                    <a href="<?= url('pricing-plan') ?>" style="color:var(--accent);">Order <?= e(RateSheet::leadPackName()) ?> →</a>
                </p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table-dark" style="width:100%;margin:0;min-width:560px;">
                    <thead>
                        <tr>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Order</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Leads</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Paid</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Status</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Ordered</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:right;padding:10px 12px;">File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leadOrders as $lo):
                            $loStatus = (string)($lo['status'] ?? 'pending');
                            $loClass = match ($loStatus) {
                                'delivered'  => 'badge-active',
                                'processing' => 'badge-pending',
                                'refunded'   => 'badge-danger',
                                default      => 'badge-inactive',
                            };
                            $loLabels = [
                                'pending'    => 'Awaiting fulfilment',
                                'processing' => 'Being prepared',
                                'delivered'  => 'Delivered',
                                'refunded'   => 'Refunded',
                            ];
                        ?>
                            <tr>
                                <td style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:var(--fg2);padding:10px 12px;">#<?= (int)$lo['id'] ?></td>
                                <td style="font-size:13px;color:var(--fg);padding:10px 12px;"><?= number_format((int)($lo['leads_count'] ?? 0)) ?></td>
                                <td style="font-weight:600;color:var(--fg);padding:10px 12px;"><?= e(price_ngn_fmt((float)($lo['price_ngn'] ?? 0))) ?></td>
                                <td style="padding:10px 12px;">
                                    <span class="<?= $loClass ?>" style="text-transform:capitalize;"><?= e($loLabels[$loStatus] ?? $loStatus) ?></span>
                                    <?php if (!empty($lo['notes'])): ?>
                                        <div style="font-size:11px;color:var(--fg3);margin-top:4px;"><?= e((string)$lo['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:13px;color:var(--fg2);padding:10px 12px;"><?= !empty($lo['created_at']) ? date('M d, Y', strtotime((string)$lo['created_at'])) : '—' ?></td>
                                <td style="padding:10px 12px;text-align:right;white-space:nowrap;">
                                    <?php if ($loStatus === 'delivered' && !empty($lo['deliverable_path'])): ?>
                                        <a href="<?= e(url('billing', ['download_lead' => (int)$lo['id']])) ?>"
                                           style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--accent);text-decoration:none;border-bottom:1px solid var(--border2);padding-bottom:1px;"
                                           title="Download the delivered lead list (CSV)">
                                            <i class="ph ph-download-simple" style="font-size:12px;"></i> Download CSV
                                        </a>
                                    <?php elseif ($loStatus === 'delivered'): ?>
                                        <span style="font-size:11px;color:var(--fg3);">File missing — contact support</span>
                                    <?php else: ?>
                                        <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--fg3);">Not ready yet</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:right;padding:10px 12px;">Invoice</th>
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
                                <td style="font-weight:600;color:var(--fg);padding:10px 12px;"><?= txn_money((float)($t['amount'] ?? 0), $t['currency'] ?? null) ?></td>
                                <td style="padding:10px 12px;">
                                    <span class="<?= $sClass ?>" style="text-transform:capitalize;"><?= e((string)($t['status'] ?? 'unknown')) ?></span>
                                </td>
                                <td style="font-size:13px;color:var(--fg2);padding:10px 12px;"><?= !empty($t['created_at']) ? date('M d, Y H:i', strtotime((string)$t['created_at'])) : '—' ?></td>
                                <td style="padding:10px 12px;text-align:right;white-space:nowrap;">
                                    <?php $isPaid = in_array((string)($t['status'] ?? ''), ['successful', 'completed', 'success'], true); ?>
                                    <?php if ($isPaid): ?>
                                        <a href="<?= e(url('invoice', ['id' => (int)$t['id']])) ?>" target="_blank" rel="noopener"
                                           style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--fg2);text-decoration:none;border-bottom:1px solid var(--border2);padding-bottom:1px;"
                                           title="<?= !empty($t['invoice_path']) ? 'Open the invoice for this transaction' : 'Generate and open the invoice for this transaction' ?>">
                                            <i class="ph ph-file-text" style="font-size:12px;"></i> View
                                        </a>
                                        <form method="post" style="display:inline-block;margin-left:10px" onsubmit="return confirm('Email this invoice to your account address?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="email_invoice">
                                            <input type="hidden" name="transaction_id" value="<?= (int)$t['id'] ?>">
                                            <button type="submit"
                                                    style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--fg2);background:none;border:0;border-bottom:1px solid var(--border2);padding:0 0 1px;cursor:pointer"
                                                    title="Email this invoice to your account address">
                                                <i class="ph ph-envelope-simple" style="font-size:12px;"></i> Email
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--fg3);">—</span>
                                    <?php endif; ?>
                                </td>
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
    var gw = sel ? sel.value : '';
    document.querySelectorAll('.gateway-field').forEach(function (f) { f.value = gw; });
    document.querySelectorAll('[id^="gw-"][id$="Card"]').forEach(function (card) {
        var key = card.id.replace(/^gw-/, '').replace(/Card$/, '');
        card.style.borderColor = key === gw ? 'var(--accent)' : 'var(--border)';
    });
}
document.querySelectorAll('input[name="gateway_choice"]').forEach(function (r) {
    r.addEventListener('change', syncGateway);
});
syncGateway();
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
