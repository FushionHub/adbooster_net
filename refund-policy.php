<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Billing, Credits & Refunds — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height:80vh;">
    <div class="container max-w-1000-px">
        <div class="tw-mb-10 text-center">
            <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill tw-px-4 tw-py-2 tw-text-xs fw-bold text-uppercase tw-mb-3">
                Legal &amp; Governance
            </span>
            <h1 class="h2 text-white fw-bold">Billing, Credits &amp; Refunds</h1>
            <p class="text-white-50 tw-mt-2">How subscriptions and credits work, when a refund is available, and how to cancel</p>
        </div>

        <div class="card bg-white-06 border border-neutral-800 tw-rounded-xl tw-p-8 text-white-50" style="line-height:1.7; font-size:14.5px;">

            <h3 class="h5 text-white fw-bold tw-mb-3">1. Two things you can buy</h3>
            <ul class="tw-mb-4 tw-ps-4">
                <li><strong>A subscription</strong> — monthly or annual access to a plan tier (Starter, Growth, Agency Pro, Enterprise). This unlocks features and includes a credit allowance each billing period.</li>
                <li><strong>Credits</strong> — the units consumed by AI image generation, video rendering, automated media buying and directory promotions. Each action shows its credit cost before you confirm it.</li>
            </ul>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">2. When credits are used</h3>
            <p>A credit is deducted only when an action actually completes. If a generation fails or is cancelled before a result is produced, <strong>no credit is taken</strong>. If a charge was taken in error, contact support and we will restore it after verifying the failure.</p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">3. What is refundable</h3>
            <div class="row g-3 tw-mb-4">
                <div class="col-md-6">
                    <div class="alert bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-3 tw-p-3 tw-mb-0 h-100">
                        <strong class="d-block tw-mb-2">Refundable</strong>
                        <ul class="tw-mb-0 tw-ps-3 small">
                            <li>A first subscription, cancelled within 14 days of the first charge and with under 10% of the included credits used.</li>
                            <li>A duplicate or accidental charge.</li>
                            <li>A charge taken for an action that demonstrably failed.</li>
                            <li>Where consumer law in your country gives you a statutory right of withdrawal.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger rounded-3 tw-p-3 tw-mb-0 h-100">
                        <strong class="d-block tw-mb-2">Not refundable</strong>
                        <ul class="tw-mb-0 tw-ps-3 small">
                            <li>Credits that have already been consumed.</li>
                            <li>Subscription time already used, after the 14-day first-charge window.</li>
                            <li>Ad budget you paid to Meta, Google, TikTok or any other ad network.</li>
                            <li>Accounts closed for violating our Terms or AI disclaimer.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">4. How to cancel</h3>
            <p>
                You can cancel at any time from the <a href="<?= url('billing') ?>" class="text-main-600 text-decoration-underline">Billing Center</a>. Cancelling stops the next renewal; it does not refund the current period. Unused credits stay on your account until the end of the paid period, then expire. Export anything you need before then.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">5. How to request a refund</h3>
            <p>Email <a href="mailto:<?= e(get_setting('support_email', 'support@adsbooster.net')) ?>" class="text-main-600 text-decoration-underline"><?= e(get_setting('support_email', 'support@adsbooster.net')) ?></a> with:</p>
            <ol class="tw-mb-4 tw-ps-4">
                <li>The email address on your account.</li>
                <li>The date and amount of the charge.</li>
                <li>A short description of the problem, and any error message you saw.</li>
            </ol>
            <p>We acknowledge requests within <strong>2 business days</strong> and aim to decide within <strong>10 business days</strong>. Approved refunds are returned to the original payment method; your bank may take a further 5–10 business days to post it.</p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">6. Price changes</h3>
            <p>If we change a plan price, we give at least <strong>30 days' notice</strong> by email. The new price applies from your next renewal after that notice. You can cancel before it takes effect at the old price.</p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">7. Taxes and currency</h3>
            <p>Prices are shown in USD and exclude any sales tax, VAT or similar charge that your country requires us to collect. Where we must collect it, it is added at checkout. You are responsible for any tax your own jurisdiction levies on your purchase.</p>

            <div class="tw-mt-8 pt-4 border-top border-neutral-800 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <span class="text-white-50 tw-text-xs">Billing questions? <a href="mailto:<?= e(get_setting('support_email', 'support@adsbooster.net')) ?>" class="text-main-600"><?= e(get_setting('support_email', 'support@adsbooster.net')) ?></a></span>
                <a href="<?= url('terms') ?>" class="text-main-600 fw-semibold tw-text-sm">Terms of Service &rarr;</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
