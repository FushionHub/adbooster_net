<?php
declare(strict_types=1);

/**
 * Static team profile — the only team member is the founder, so this page
 * no longer looks users up by id (which exposed real accounts to anyone).
 */

$pageTitle = 'Jamachi Mauricennadi - Team - Adsbooster.net';
require_once __DIR__ . '/includes/header.php';

$portfolioUrl = 'https://jmn.morexweb.com';

// ── Phone numbers ───────────────────────────────────────────────────
$phonePrimary   = '+2349024428551';
$phoneSecondary = '+2348127560227';
$phonePrimaryDisplay   = '+234 902 442 8551';
$phoneSecondaryDisplay = '+234 812 756 0227';

// ── Direct contact channels ─────────────────────────────────────────
// WhatsApp number in international format, digits only (wa.me needs no
// "+" and no spaces).
$whatsappNumber = '2349024428551';
$whatsappText   = 'Hello Jamachi, I visited your Adsbooster.net team page and would like to talk.';
$whatsappUrl    = 'https://wa.me/' . preg_replace('/\D/', '', $whatsappNumber)
    . '?text=' . rawurlencode($whatsappText);

// Email — follows the site-wide support address configured in site_settings.
$contactEmail = get_setting('site_email', 'support@adsbooster.net');
$emailSubject = 'Hello Jamachi — from the Adsbooster team page';
$emailBody    = "Hello Jamachi,\n\nI visited the Adsbooster.net team page and would like to get in touch.\n\n";
$emailUrl     = 'mailto:' . rawurlencode($contactEmail)
    . '?subject=' . rawurlencode($emailSubject)
    . '&body=' . rawurlencode($emailBody);
?>

        <!-- ==================== Breadcrumb Start Here ==================== -->
<section class="breadcrumb position-relative background-img bg-img z-1 mb-0" data-background-image="<?= ASSETS_URL ?>/images/thumbs/breadcrumb-bg-img.png">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center">
                    <ul class="d-inline-flex align-items-center justify-content-center tw-gap-2 bg-white-08 rounded-pill tw-px-5 tw-py-2">
                        <li><a href="<?= url('index') ?>" class="text-white hover--translate-y-1 hover-underline"> <i class="las la-home"></i> Home</a> </li>
                        <li class="text-white tw-text-lg">/</li>
                        <li><a href="<?= url('team') ?>" class="text-white hover--translate-y-1 hover-underline"> Team</a></li>
                        <li class="text-white tw-text-lg">/</li>
                        <li> <span class="text-white"> Jamachi Mauricennadi </span> </li>
                    </ul>
                    <h2 class="text-center tw-mb-6 text-white tw-mt-6 cursor-big"> Team Details</h2>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ==================== Breadcrumb End Here ==================== -->

        <!-- =========================== Team Details section start =============================== -->
        <section class="team-details py-120">
            <div class="container">
                <div class="row gy-5">
                    <div class="col-lg-6">
                        <div class="hover-animation-white h-100">
                            <img src="<?= ASSETS_URL ?>/images/thumbs/jamachi-mauricennadi.jpg" alt="Jamachi Mauricennadi"
                                class="tw-rounded-xl w-100 h-100 object-fit-cover">
                        </div>
                    </div>
                    <div class="col-lg-6 align-self-center">
                        <div class="ps-xl-5 ps-lg-4">
                            <h2 class="">Jamachi Mauricennadi</h2>
                            <p class="text-neutral-600 tw-text-lg tw-mt-6 tw-pb-6 border-bottom border-neutral-200">FullStack Software Developer &amp; Lead Engineer of Adsbooster.net — the multi-network advertising, AI UGC creative and social publishing platform.</p>
                            <div class="tw-mt-8 d-flex flex-column tw-gap-6">
                                <div class="d-flex align-items-center tw-gap-4">
                                    <span class="fw-bold text-neutral-600 tw-text-lg tw-w-120-px ">Position:</span>
                                    <span class="fw-normal text-neutral-600 tw-text-lg flex-grow-1"> FullStack Software Developer &amp; Lead Engineer</span>
                                </div>
                                <div class="d-flex align-items-center tw-gap-4">
                                    <span class="fw-bold text-neutral-600 tw-text-lg tw-w-120-px ">Portfolio:</span>
                                    <span class="fw-normal text-neutral-600 tw-text-lg flex-grow-1">
                                        <a href="<?= $portfolioUrl ?>" target="_blank" rel="noopener noreferrer" class="text-main-600 hover-underline">jmn.morexweb.com</a>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center tw-gap-4">
                                    <span class="fw-bold text-neutral-600 tw-text-lg tw-w-120-px ">Social:</span>
                                    <div class="fw-normal text-neutral-600 tw-text-lg flex-grow-1">
                                        <ul class="d-flex align-items-center tw-gap-205">
                                            <li>
                                                <a href="<?= $portfolioUrl ?>" target="_blank" rel="noopener noreferrer" title="Portfolio"
                                                    class="text-main-600 tw-w-12 tw-h-12 border border-neutral-300 hover-border-main-600 rounded-circle d-flex justify-content-center align-items-center tw-text-xl tw-leading-none hover-bg-main-600 hover-text-white active-scale-09"><i
                                                        class="ph-fill ph-globe"></i></a>
                                            </li>
                                            <li>
                                                <a href="https://github.com/FushionHub" target="_blank" rel="noopener noreferrer" title="GitHub"
                                                    class="text-main-600 tw-w-12 tw-h-12 border border-neutral-300 hover-border-main-600 rounded-circle d-flex justify-content-center align-items-center tw-text-xl tw-leading-none hover-bg-main-600 hover-text-white active-scale-09">
                                                    <i class="ph-fill ph-github-logo"></i></a>
                                            </li>
                                            <li>
                                                <a href="https://www.linkedin.com" target="_blank" rel="noopener noreferrer" title="LinkedIn"
                                                    class="text-main-600 tw-w-12 tw-h-12 border border-neutral-300 hover-border-main-600 rounded-circle d-flex justify-content-center align-items-center tw-text-xl tw-leading-none hover-bg-main-600 hover-text-white active-scale-09"><i
                                                        class="ph-fill ph-linkedin-logo"></i></a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tw-mt-17">
                    <div class="row gy-5">
                        <div class="col-lg-6">
                            <div class="">
                                <h2 class="h5">About</h2>
                                <p class="text-neutral-600 tw-mt-9 tw-text-lg">Jamachi designs, builds and operates Adsbooster end to end: the cross-network ad campaign engine, the AI UGC Image and Video Studios, the marketplace and directory, and the payment infrastructure that verifies Flutterwave and Paystack transactions automatically. His full portfolio of work lives at <a href="<?= $portfolioUrl ?>" target="_blank" rel="noopener noreferrer" class="text-main-600 hover-underline">jmn.morexweb.com</a>.</p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="ps-xl-5 ps-lg-4">
                                <h2 class="h5 tw-mb-8">Get In Touch</h2>
                                <p class="text-neutral-600 tw-text-lg tw-mb-8">Reach Jamachi directly — replies usually come within a day.</p>
                                <div class="d-flex flex-wrap align-items-center tw-gap-6">
                                    <a href="<?= $whatsappUrl ?>" target="_blank" rel="noopener noreferrer"
                                        class="btn button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-3 rounded-pill tw-px-8 tw-py-4 fw-bold"
                                        style="background:#25D366;border-color:#25D366;color:#06251a;">
                                        <i class="ph-bold ph-whatsapp-logo tw-text-2xl"></i>
                                        <span>Message on WhatsApp</span>
                                    </a>
                                    <a href="tel:<?= preg_replace('/\D/', '', $phonePrimary) ?>"
                                        class="btn btn-main hover-style-one button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-3 rounded-pill tw-px-8 tw-py-4 fw-bold">
                                        <i class="ph-bold ph-phone tw-text-2xl"></i>
                                        <span><?= $phonePrimaryDisplay ?></span>
                                    </a>
                                    <a href="tel:<?= preg_replace('/\D/', '', $phoneSecondary) ?>"
                                        class="btn btn-outline-main button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-3 rounded-pill tw-px-8 tw-py-4 fw-bold text-heading">
                                        <i class="ph-bold ph-phone-call tw-text-2xl"></i>
                                        <span><?= $phoneSecondaryDisplay ?></span>
                                    </a>
                                    <a href="<?= $emailUrl ?>"
                                        class="btn btn-outline-main button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-3 rounded-pill tw-px-8 tw-py-4 fw-bold text-heading">
                                        <i class="ph-bold ph-envelope tw-text-2xl"></i>
                                        <span>Send an Email</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        <!-- =========================== Team Details section end =============================== -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
