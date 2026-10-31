<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Copyright & DMCA — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5" style="min-height:80vh;">
    <div class="container max-w-1000-px">
        <div class="tw-mb-10 text-center">
            <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill tw-px-4 tw-py-2 tw-text-xs fw-bold text-uppercase tw-mb-3">
                Legal &amp; Governance
            </span>
            <h1 class="h2 text-white fw-bold">Copyright &amp; DMCA Policy</h1>
            <p class="text-white-50 tw-mt-2">How to report content you believe infringes your copyright, and how we handle repeat infringers</p>
        </div>

        <div class="card bg-white-06 border border-neutral-800 tw-rounded-xl tw-p-8 text-white-50" style="line-height:1.7; font-size:14.5px;">

            <h3 class="h5 text-white fw-bold tw-mb-3">1. Our position</h3>
            <p>
                <?= e(get_setting('site_name', 'Adsbooster.net')) ?> ("Adsbooster") respects the intellectual property rights of others and expects its users to do the same. We respond to clear notices of alleged copyright infringement that comply with the U.S. Digital Millennium Copyright Act (DMCA) and, where applicable, the EU Directive on Copyright in the Digital Single Market.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">2. What you can report</h3>
            <p>You may submit a notice if material hosted on our platform infringes a copyright you own or control, including:</p>
            <ul class="tw-mb-4 tw-ps-4">
                <li>Images, video or audio you own that appear in a user's library, portfolio, listing or published post.</li>
                <li>Text, brand names, logos or trademarks used without authorisation.</li>
                <li>Any other work protected by copyright law that you reasonably believe is being used without permission.</li>
            </ul>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">3. Before you file — important</h3>
            <div class="alert bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning rounded-3 tw-p-4 tw-mb-4">
                <strong>AI-generated content is not automatically infringing.</strong> Material produced by our generative tools is synthesised from your own prompts. If your complaint concerns an AI-generated image or video, please explain precisely which protected element (for example a specific logo, photograph or distinctive character) is reproduced, so we can assess it quickly. Speculative or automated notices will be returned without action.
            </div>
            <p>
                Knowingly making a material misrepresentation in a takedown notice — including claiming ownership of work you do not own — may expose you to liability for damages, including costs and legal fees. Please be certain before you file.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">4. How to submit a notice</h3>
            <p>Send your notice to our designated agent at <a href="mailto:<?= e(get_setting('dmca_email', 'dmca@adsbooster.net')) ?>" class="text-main-600 text-decoration-underline"><?= e(get_setting('dmca_email', 'dmca@adsbooster.net')) ?></a>. Your notice must include all of the following, or we will be unable to process it:</p>
            <ol class="tw-mb-4 tw-ps-4">
                <li>A physical or electronic signature of the copyright owner, or a person authorised to act on their behalf.</li>
                <li>Identification of the copyrighted work claimed to have been infringed (and, if multiple works are covered, a representative list).</li>
                <li>The exact URL or other location on our platform where the material is located, so we can find it.</li>
                <li>Your full legal name, mailing address, telephone number and email address.</li>
                <li>A statement that you have a good-faith belief that the use complained of is not authorised by the copyright owner, its agent, or the law.</li>
                <li>A statement, made under penalty of perjury, that the information in the notice is accurate and that you are the copyright owner or authorised to act on the owner's behalf.</li>
            </ol>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">5. What happens next</h3>
            <ul class="tw-mb-4 tw-ps-4">
                <li>We acknowledge complete notices within <strong>2 business days</strong>.</li>
                <li>We assess the notice and, where it is valid, remove or disable access to the material, typically within <strong>10 business days</strong>.</li>
                <li>We notify the affected user, who may file a counter-notification if they believe the material was removed by mistake or misidentification.</li>
                <li>Valid counter-notifications are forwarded to the original complainant. If no court action is filed within 10–14 business days, we may restore the material.</li>
            </ul>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">6. Repeat infringers</h3>
            <p>
                We terminate the accounts of users who are found to repeatedly infringe copyright. A user is treated as a repeat infringer after two valid, unchallenged takedown notices concerning their account, or where the circumstances otherwise clearly warrant termination. Terminated users may not create new accounts.
            </p>

            <h3 class="h5 text-white fw-bold tw-mt-6 tw-mb-3">7. Counter-notification</h3>
            <p>
                If your content was removed and you believe this was a mistake or misidentification, you may send a counter-notification to the same address above. It must identify the material and its location before removal, state your contact details, include your physical or electronic signature, and include a statement under penalty of perjury that you have a good-faith belief the material was removed as a result of mistake or misidentification, plus your consent to the jurisdiction of the appropriate federal court and to accept service of process from the complainant.
            </p>

            <div class="tw-mt-8 pt-4 border-top border-neutral-800 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <span class="text-white-50 tw-text-xs">Designated agent: <a href="mailto:<?= e(get_setting('dmca_email', 'dmca@adsbooster.net')) ?>" class="text-main-600"><?= e(get_setting('dmca_email', 'dmca@adsbooster.net')) ?></a></span>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="<?= url('terms') ?>" class="text-main-600 fw-semibold tw-text-sm">Terms of Service &rarr;</a>
                    <a href="<?= url('privacy') ?>" class="text-main-600 fw-semibold tw-text-sm">Privacy Policy &rarr;</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
