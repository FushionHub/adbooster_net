<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Cookie Policy — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';

$cookies = [
    ['PHPSESSID', 'Strictly necessary', 'Session', 'Keeps you logged in while you browse. Without it the site cannot function.'],
    ['remember_token', 'Strictly necessary', 'Up to 30 days', 'Set only if you tick "Remember me" at login, so you do not have to sign in again on that device.'],
    ['adsbooster_consent', 'Strictly necessary', '1 year', 'Records that you have made a choice about cookies.'],
    ['adsbooster_consent_uuid', 'Strictly necessary', '1 year', 'An anonymous identifier for your consent record, so we can honour your choice next visit.'],
    ['adsbooster_consent_prefs', 'Strictly necessary', '1 year', 'Stores which optional cookie categories you accepted or refused.'],
    ['ref_code', 'Analytics', '30 days', 'Remembers which referral link brought you to the site.'],
    ['consent_analytics', 'Analytics', '1 year', 'Set when you adjust analytics consent in the Privacy Center.'],
    ['consent_marketing', 'Marketing', '1 year', 'Set when you adjust marketing consent in the Privacy Center.'],
    ['review_prompt_dismissed', 'Functionality', '7 days', 'Stops the review prompt reappearing after you dismiss it.'],
];
?>

<section class="section-padding py-5" style="min-height:80vh;">
    <div class="container max-w-1000-px">
        <div class="tw-mb-10 text-center">
            <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill tw-px-4 tw-py-2 tw-text-xs fw-bold text-uppercase tw-mb-3">
                Legal &amp; Governance
            </span>
            <h1 class="h2 text-white fw-bold">Cookie Policy</h1>
            <p class="text-white-50 tw-mt-2">The short version: we use a small number of cookies, mostly to keep you logged in and to remember your choices. Nothing is sold to advertisers.</p>
        </div>

        <div class="card bg-white-06 border border-neutral-800 tw-rounded-xl tw-p-8 text-white-50" style="line-height:1.7; font-size:14.5px;">

            <h3 class="h5 text-white fw-bold tw-mb-3">1. What cookies are</h3>
            <p>
                Cookies are small text files placed on your device by a website. They let a site remember who you are between page loads, remember your settings, and — where you allow it — measure how the site is used.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">2. Categories we use</h3>
            <ul class="tw-mb-4 tw-ps-4">
                <li><strong>Strictly necessary</strong> — required for login, security and remembering your cookie choice. These cannot be switched off and do not require consent.</li>
                <li><strong>Analytics</strong> — helps us understand which pages are useful. Optional; off until you agree.</li>
                <li><strong>Marketing</strong> — used to measure ad campaigns where you have connected an ad account. Optional; off until you agree.</li>
                <li><strong>Functionality</strong> — remembers small preferences so you are not asked twice.</li>
            </ul>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">3. Every cookie we set</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle" style="color:#cbd5e1;font-size:13.5px;">
                    <thead>
                        <tr style="color:#94a3b8;">
                            <th>Cookie</th><th>Category</th><th>How long</th><th>What it does</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cookies as [$name, $cat, $life, $what]): ?>
                            <tr>
                                <td class="text-white fw-semibold"><code><?= e($name) ?></code></td>
                                <td><span class="badge bg-dark border border-secondary border-opacity-25 rounded-pill px-2 py-1 tw-text-xs"><?= e($cat) ?></span></td>
                                <td><?= e($life) ?></td>
                                <td class="text-white-50"><?= e($what) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">4. Third parties</h3>
            <p>
                Our own site does not set advertising cookies. When you connect a third-party account (Meta, Google, TikTok) or embed their content, that platform sets its own cookies under its own policy. We do not control those, and we recommend you read their policies before connecting an account.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">5. Changing your choice</h3>
            <p>
                You can change your cookie preferences at any time in the <a href="<?= url('privacy-center') ?>" class="text-main-600 text-decoration-underline">Privacy Center</a>, or clear cookies through your browser settings. Turning off strictly necessary cookies will sign you out and break login.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">6. Do Not Track and Global Privacy Control</h3>
            <p>
                Where your browser sends a Global Privacy Control (GPC) signal, we treat it as an opt-out of optional analytics and marketing cookies, without requiring you to interact with our banner.
            </p>

            <div class="tw-mt-8 pt-4 border-top border-neutral-800 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <span class="text-white-50 tw-text-xs">Questions about privacy? <a href="mailto:<?= e(get_setting('site_email', 'support@adsbooster.net')) ?>" class="text-main-600"><?= e(get_setting('site_email', 'support@adsbooster.net')) ?></a></span>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="<?= url('privacy') ?>" class="text-main-600 fw-semibold tw-text-sm">Privacy Policy &rarr;</a>
                    <a href="<?= url('privacy-center') ?>" class="text-main-600 fw-semibold tw-text-sm">Privacy Center &rarr;</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
