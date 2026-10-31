<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'AI Content & Advertising Disclaimer — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height:80vh;">
    <div class="container max-w-1000-px">
        <div class="tw-mb-10 text-center">
            <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill tw-px-4 tw-py-2 tw-text-xs fw-bold text-uppercase tw-mb-3">
                Legal &amp; Governance
            </span>
            <h1 class="h2 text-white fw-bold">AI Content &amp; Advertising Disclaimer</h1>
            <p class="text-white-50 tw-mt-2">What our AI tools produce, what you are responsible for, and how to stay on the right side of advertising law</p>
        </div>

        <div class="card bg-white-06 border border-neutral-800 tw-rounded-xl tw-p-8 text-white-50" style="line-height:1.7; font-size:14.5px;">

            <div class="alert bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning rounded-3 tw-p-4 tw-mb-5">
                <strong>Read this before you publish anything.</strong> Adsbooster gives you powerful AI tools. You remain responsible for what you publish with them. This page explains the split in plain language.
            </div>

            <h3 class="h5 text-white fw-bold tw-mb-3">1. What our AI actually produces</h3>
            <p>Adsbooster uses generative models to create images, video scenes, scripts, captions and voiceovers. These are <strong>computer-generated simulations</strong>. Specifically:</p>
            <ul class="tw-mb-4 tw-ps-4">
                <li>AI avatars, presenters and "customers" are synthetic. They are not real people and they are not giving real opinions.</li>
                <li>AI voices are synthesised. They do not belong to a real speaker unless you separately licence that speaker's voice.</li>
                <li>AI-generated scenes depict fictional settings. They do not document real product use, real results or real customers.</li>
                <li>AI-written copy is drafted from the description you supply. It has not been fact-checked by us.</li>
            </ul>
            <p><strong>Nothing produced by Adsbooster is a real endorsement, testimonial, review, or guarantee of results unless you independently obtain it.</strong></p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">2. Your obligations when you publish</h3>
            <p>When you use AI-generated creative in advertising, you are responsible for complying with the advertising rules that apply to you, including in the United States (FTC Act, FTC Endorsement Guides, Made in USA and health-claim rules), the European Union (Unfair Commercial Practices Directive, Digital Services Act), the United Kingdom (CAP/ASA Code), and the equivalent rules in your market. In practice this means:</p>
            <ul class="tw-mb-4 tw-ps-4">
                <li><strong>Disclose that it is an ad.</strong> Use a clear and conspicuous disclosure such as <code>#ad</code> or <code>#sponsored</code> where required.</li>
                <li><strong>Disclose synthetic media where mandated.</strong> Several jurisdictions and every major ad network now require AI-generated people, voices or events to be labelled.</li>
                <li><strong>Do not present AI output as a real person's experience.</strong> A synthetic "customer testimonial" that implies a genuine user review is misleading.</li>
                <li><strong>Do not make claims you cannot prove.</strong> Health, financial and performance claims need substantiation you must hold.</li>
                <li><strong>Respect third-party rights.</strong> Do not recreate real people, celebrities, protected characters, logos or trademarks without permission.</li>
            </ul>
            <p>Every major ad network — Meta, Google, TikTok and others — has its own policy on AI and synthetic content, which is stricter than the law in places. You are responsible for reading and following those policies before you spend.</p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">3. What we do not guarantee</h3>
            <p>Advertising outcomes depend on your product, pricing, budget, targeting, competition and the algorithms of third-party networks. <strong>We make no guarantee of return on ad spend, cost per acquisition, conversion rate, traffic volume or profitability.</strong> Any figure shown in our analytics or forecasting tools is an estimate, not a promise. Money you spend on external ad platforms is spent with those platforms, not with us, and is subject to their refund rules.</p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">4. Prohibited uses</h3>
            <p>You may not use Adsbooster to create or distribute content that is:</p>
            <ul class="tw-mb-4 tw-ps-4">
                <li>Deceptive, fraudulent, or designed to impersonate a real person or organisation;</li>
                <li>A non-consensual deepfake of an identifiable person;</li>
                <li>Sexually explicit, or sexualised depictions of any person;</li>
                <li>Hate speech, harassment, or incitement to violence;</li>
                <li>Political electioneering or issue advertising that breaks applicable transparency and disclosure rules;</li>
                <li>Illegal, or that promotes illegal goods, services or activity.</li>
            </ul>
            <p>We may suspend or terminate accounts and report activity to the relevant authorities where we reasonably believe such content has been created or distributed through the platform.</p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">5. Your content stays yours</h3>
            <p>You keep ownership of the prompts, product photos, brand assets and copy you upload. You grant us only the limited licence needed to run the tools you have asked us to run. Generated output is provided to you for commercial use, but you are the one who must clear any third-party rights in it.</p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">6. Third-party platforms</h3>
            <p>Adsbooster is an independent tool. We are not affiliated with, endorsed by, or connected to Meta Platforms, ByteDance/TikTok, Alphabet/Google, Shopify or any other advertising network. Their names are used only to describe compatibility. Connecting your account to a third-party platform is governed by that platform's terms as well as ours.</p>

            <div class="tw-mt-8 pt-4 border-top border-neutral-800 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <span class="text-white-50 tw-text-xs">Questions? <a href="mailto:legal@adsbooster.net" class="text-main-600">legal@adsbooster.net</a></span>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="<?= url('terms') ?>" class="text-main-600 fw-semibold tw-text-sm">Terms &rarr;</a>
                    <a href="<?= url('dmca') ?>" class="text-main-600 fw-semibold tw-text-sm">DMCA &rarr;</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
