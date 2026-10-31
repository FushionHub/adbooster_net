<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Terms of Service — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height: 80vh;">
    <div class="container max-w-1000-px">
        <div class="tw-mb-10 text-center">
            <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill tw-px-4 tw-py-2 tw-text-xs fw-bold text-uppercase tw-mb-3">
                Legal &amp; Governance
            </span>
            <h1 class="h2 text-white fw-bold">Terms of Service</h1>
            <p class="text-white-50 tw-mt-2">Last updated: October 2026 • Effective immediately for all registered users and visitors</p>
        </div>

        <div class="card bg-white-06 border border-neutral-800 tw-rounded-xl tw-p-8 text-white-50" style="line-height:1.7; font-size:14.5px;">
            <h3 class="h5 text-white fw-bold tw-mb-3">1. Acceptance of Terms</h3>
            <p>
                By creating an account, accessing, or using the services provided by <?= e(get_setting('site_name', 'Adsbooster.net')) ?> ("Adsbooster", "we", "our", or "us"), you agree to be bound by these Terms of Service and all applicable laws and regulations. If you do not agree with any of these terms, you are prohibited from using or accessing this platform.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">2. Description of Services</h3>
            <p>
                Adsbooster provides performance marketing infrastructure, including automated cross-platform ad campaign generation (Meta, TikTok, Google), AI UGC creative studio, Server-Side Conversions API (CAPI) routing, verified business directory listings, social media auto-scheduling, and peer-to-peer collaboration tools.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">3. Independent Platform &amp; Non-Affiliation</h3>
            <p>
                Adsbooster is an independent marketing software tool. We are <strong>not affiliated with, associated with, authorized by, endorsed by, or in any way officially connected</strong> with Meta Platforms, Inc. (Facebook, Instagram), ByteDance Ltd. / TikTok Inc., Alphabet Inc. / Google LLC, Shopify Inc., or WooCommerce. All product and company names, logos, and brands are trademarks™ or registered® trademarks of their respective holders. Use of them does not imply any affiliation with or endorsement by them.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">4. AI &amp; Synthetic Media Terms (FTC &amp; Legal Compliance)</h3>
            <p>
                Adsbooster utilizes generative artificial intelligence to produce marketing copy, synthetic talking avatars, voiceovers, and UGC-style creative assets. By using these features, you explicitly acknowledge and agree that:
            </p>
            <ul class="tw-mb-4 tw-ps-4">
                <li><strong>Synthetic Content:</strong> AI-generated avatars, voices, and testimonials are computer-generated simulations and do not represent real human endorsements unless you independently secure documented testimonial release agreements.</li>
                <li><strong>FTC Disclosure Compliance:</strong> When deploying commercial advertisements utilizing synthetic media, you are solely responsible for ensuring compliance with Federal Trade Commission (FTC) Endorsement Guides, European Consumer Protection Directives, and applicable local advertising disclosure laws (including using visible tags such as <code>#ad</code>, <code>#sponsored</code>, or synthetic media disclosures where mandated).</li>
                <li><strong>Prohibited Use:</strong> You strictly agree not to use Adsbooster to generate deceptive, fraudulent, defamatory, non-consensual deepfakes, sexually explicit, hate speech, political electioneering, or illegal content.</li>
                <li><strong>Intellectual Property:</strong> You certify that any prompts, product photos, brand logos, or assets uploaded for AI transformation are owned by you or you possess all necessary commercial licenses. You retain responsibility for third-party copyright and trademark claims.</li>
            </ul>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">5. No Return on Ad Spend (ROAS) or Profit Guarantees</h3>
            <p>
                Digital advertising performance depends on market demand, competitive bids, audience behavior, creative relevance, product pricing, and algorithmic delivery on third-party networks. <strong>Adsbooster makes no guarantees, representations, or warranties, express or implied, regarding Return on Ad Spend (ROAS), Cost Per Acquisition (CPA), conversion rates, traffic volume, or business profitability.</strong> Any ad spend paid to ad networks (Meta, Google, TikTok) is at your sole risk and is non-refundable by Adsbooster.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">6. User Accounts, Credentials &amp; Security</h3>
            <p>
                You are responsible for maintaining the confidentiality of your account credentials and API keys. You agree to notify us immediately of any unauthorized access or breach of security. Two-factor authentication (2FA) is strongly recommended for all accounts and is mandatory for administrative privileges.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">7. Subscriptions, Credits &amp; Refunds</h3>
            <p>
                Subscription plans (Starter, Growth, Agency Pro, Enterprise) are billed on a recurring monthly or annual basis. Credits purchased for AI video rendering, image generation, automated buying, or directory promotions are non-refundable once consumed. Subscriptions may be canceled at any time prior to the next billing cycle through the <a href="<?= url('billing') ?>" class="text-main-600 text-decoration-underline">Billing Center</a>.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">8. Marketplace, Escrow &amp; P2P Contracts</h3>
            <p>
                Users participating in the Adsbooster Marketplace, creator bounties, or peer-to-peer contract agreements do so under our escrow protection rules. Work deliverables must meet the agreed contract terms before escrow funds are released. Disputes are arbitrated by the Adsbooster Trust &amp; Safety board whose decisions are final.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">9. Limitation of Liability</h3>
            <p>
                To the fullest extent permitted by applicable law, in no event shall <?= e(get_setting('site_name', 'Adsbooster.net')) ?>, its directors, employees, partners, agents, suppliers, or affiliates be liable for any indirect, incidental, special, consequential, or punitive damages, including without limitation, loss of profits, ad spend incurred on external ad platforms, account suspensions by third-party networks, data loss, use, goodwill, or other intangible losses, resulting from: (i) your access to or use of or inability to access or use the platform; (ii) any conduct or content of any third party on the platform; (iii) any content obtained from the platform; and (iv) unauthorized access, use, or alteration of your transmissions or content. In no event shall our total aggregate liability exceed the total amount paid by you to Adsbooster in the three (3) months preceding the incident.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">10. Indemnification &amp; Hold Harmless</h3>
            <p>
                You agree to defend, indemnify, and hold harmless Adsbooster and its licensees and licensors, and their employees, contractors, agents, officers, and directors, from and against any and all claims, damages, obligations, losses, liabilities, costs or debt, and expenses (including but not limited to attorney's fees), resulting from or arising out of: (a) your use and access of the Service; (b) your violation of any term of these Terms; (c) your violation of any third-party right, including without limitation any copyright, property, or privacy right; or (d) any claim that your creatives or ad campaigns caused damage to a third party or violated ad network policies.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">11. Binding Arbitration &amp; Class Action Waiver</h3>
            <p>
                Any dispute, claim, or controversy arising out of or relating to these Terms or the breach, termination, enforcement, interpretation, or validity thereof shall be settled by binding individual arbitration rather than in court. <strong>You and Adsbooster agree that each may bring claims against the other only in your or its individual capacity and not as a plaintiff or class member in any purported class or representative proceeding.</strong>
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">12. Changes to Terms</h3>
            <p>
                We reserve the right to revise these Terms of Service at any time. Material changes will be communicated via your registered email address and displayed in your user dashboard. Continued use of the platform constitutes acceptance of revised terms.
            </p>

            <div class="tw-mt-8 pt-4 border-top border-neutral-800 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <span class="text-white-50 tw-text-xs">Questions or legal inquiries? Contact <a href="mailto:legal@adsbooster.net" class="text-main-600">legal@adsbooster.net</a></span>
                <a href="<?= url('privacy') ?>" class="text-main-600 fw-semibold tw-text-sm">Read Privacy Policy &rarr;</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
