<?php
declare(strict_types=1);

/**
 * Static team profile — the only team member is the founder, so this page
 * no longer looks users up by id (which exposed real accounts to anyone).
 */

$pageTitle = 'Jamachi Mauricennadi - Team - Adsbooster.net';
require_once __DIR__ . '/includes/header.php';

$portfolioUrl = 'https://jmn.morexweb.com';
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
                            <img src="<?= ASSETS_URL ?>/images/thumbs/team-details-img.png" alt="Jamachi Mauricennadi"
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
                                <form action="#" class="form-submit d-flex flex-column tw-gap-10">
                                    <div class="">
                                        <input type="text"
                                            class="focus-outline-0 bg-transparent border-0 tw-pb-5 w-100 border-bottom border-neutral-200 focus-border-main-600"
                                            placeholder="Your name" required>
                                    </div>
                                    <div class="">
                                        <input type="email"
                                            class="focus-outline-0 bg-transparent border-0 tw-pb-5 w-100 border-bottom border-neutral-200 focus-border-main-600"
                                            placeholder="Your email" required>
                                    </div>
                                    <div class="">
                                        <textarea
                                            class="focus-outline-0 bg-transparent border-0 tw-pb-5 w-100 border-bottom border-neutral-200 focus-border-main-600 tw-h-120-px"
                                            placeholder="Your message..." required></textarea>
                                    </div>
                                    <div class="">
                                        <button type="submit"
                                            class="btn btn-main hover-style-one button--stroke active-scale-094 tw-duration-100 d-inline-flex align-items-center justify-content-center tw-gap-705 group active--translate-y-2 tw-p-1 tw-ps-8 rounded-pill group-item"
                                            data-block="button">
                                            <span class="button__flair"></span>
                                            <span class="button__label">Send Message</span>
                                            <span
                                                class="tw-w-12 tw-h-12 bg-white text-main-600 tw-text-xl rounded-circle d-flex justify-content-center align-items-center position-relative tw-duration-500">
                                                <i
                                                    class="ph-bold ph-arrow-right tw-rotate--45-deg group-hover-item-rotate-0-deg tw-duration-300"></i>
                                            </span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
        <!-- =========================== Team Details section end =============================== -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
