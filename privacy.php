<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Privacy Policy — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height: 80vh;">
    <div class="container max-w-1000-px">
        <div class="tw-mb-10 text-center">
            <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill tw-px-4 tw-py-2 tw-text-xs fw-bold text-uppercase tw-mb-3">
                Data Protection &amp; GDPR / CCPA
            </span>
            <h1 class="h2 text-white fw-bold">Privacy Policy</h1>
            <p class="text-white-50 tw-mt-2">Last updated: October 2026 • Commitment to transparency and security</p>
        </div>

        <div class="card bg-white-06 border border-neutral-800 tw-rounded-xl tw-p-8 text-white-50" style="line-height:1.7; font-size:14.5px;">
            <h3 class="h5 text-white fw-bold tw-mb-3">1. Information We Collect</h3>
            <p>
                We collect personal information you provide directly to us when registering for an account, subscribing to services, submitting business directory listings, or configuring social connections. This includes name, email address, phone number, payment transaction records, and API credentials for third-party platforms.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">2. How We Use Your Information</h3>
            <p>
                Your information is used strictly to:
            </p>
            <ul class="tw-mb-4 tw-ps-4">
                <li>Deliver, maintain, and enhance the Adsbooster campaign automation and creative studio features.</li>
                <li>Facilitate Server-Side Conversions API (CAPI) event forwarding to Meta, TikTok, and Google ad servers.</li>
                <li>Process billing, invoices, and referral commissions.</li>
                <li>Send transactional notifications, security alerts (such as login alerts or 2FA codes), and critical service updates.</li>
            </ul>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">3. Data Sharing &amp; Third Parties</h3>
            <p>
                We do not sell, rent, or trade your personal data. Data is shared with third-party service providers solely to perform essential infrastructure operations (e.g. Stripe/PayPal for payment processing, SendGrid/SMTP for transactional emails, and Meta/TikTok/Google APIs when explicitly authorized by you).
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">4. Cookies &amp; Tracking Technologies</h3>
            <p>
                We use strictly necessary session cookies to maintain your login session and secure CSRF verification. Analytics and marketing cookies are strictly optional and can be managed at any time via our Cookie Consent preference panel.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">5. User Data Rights (GDPR &amp; CCPA)</h3>
            <p>
                Under applicable privacy laws, you have the right to access, rectify, export, or delete your personal data. Registered users can download a complete JSON archive of all personal records or request immediate account purging at any time via the <a href="<?= url('privacy-center') ?>" class="text-main-600 text-decoration-underline">Privacy &amp; Data Center</a>.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">6. Security Measures</h3>
            <p>
                We employ industry-standard encryption protocols (TLS/SSL in transit, bcrypt password hashing, and encrypted credential storage) to protect your account against unauthorized disclosure or alteration.
            </p>

            <div class="tw-mt-8 pt-4 border-top border-neutral-800 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <span class="text-white-50 tw-text-xs">Data Protection Officer: <a href="mailto:dpo@adsbooster.net" class="text-main-600">privacy@adsbooster.net</a></span>
                <a href="<?= url('terms') ?>" class="text-main-600 fw-semibold tw-text-sm">&larr; Read Terms of Service</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
