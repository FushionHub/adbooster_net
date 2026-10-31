<?php
declare(strict_types=1);

$pageTitle = 'Service Details - Adsbooster.net'; require __DIR__ . '/includes/header.php'; ?>

        <!-- ==================== Breadcrumb Start Here ==================== -->
<section class="breadcrumb position-relative background-img bg-img z-1 mb-0" data-background-image="<?= ASSETS_URL ?>/images/thumbs/breadcrumb-bg-img.png">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center">
                    <ul class="d-inline-flex align-items-center justify-content-center tw-gap-2 bg-white-08 rounded-pill tw-px-5 tw-py-2">
                        <li><a href="<?= url('index') ?>" class="text-white hover--translate-y-1 hover-underline"> <i class="las la-home"></i> Home</a> </li>
                        <li class="text-white tw-text-lg">/</li>
                        <li> <span class="text-white"> Service Details </span> </li>
                    </ul>
                    <h2 class="text-center tw-mb-6 text-white tw-mt-6 cursor-big"> Service Details</h2>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ==================== Breadcrumb End Here ==================== -->


        <!-- ======================== Services Details Start ========================== -->
        <section class="service-details py-120">
            <div class="container">
                <div class="row gy-4">
                    <div class="col-xl-4 pe-lg-4">
                        <div class="d-flex flex-column tw-gap-4">
                            <a href="<?= url('create-ad') ?>"
                                class="bg-neutral-100 tw-py-3 tw-pe-205 tw-ps-6 text-heading hover-text-white hover-bg-main-600 group tw-text-lg fw-bold d-flex align-items-center justify-content-between tw-duration-200 tw-rounded">
                                Multi-Network Ad Automation
                                <span
                                    class="tw-px-205 tw-py-3 text-neutral-600 bg-neutral-100 group-hover-text-main-600 tw-rounded tw-duration-300 d-flex">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                            <a href="<?= url('ugc-generator') ?>"
                                class="bg-neutral-100 tw-py-3 tw-pe-205 tw-ps-6 text-heading hover-text-white hover-bg-main-600 group tw-text-lg fw-bold d-flex align-items-center justify-content-between tw-duration-200 tw-rounded">
                                AI UGC Video Studio
                                <span
                                    class="tw-px-205 tw-py-3 text-neutral-600 bg-neutral-100 group-hover-text-main-600 tw-rounded tw-duration-300 d-flex">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                            <a href="<?= url('post-creator') ?>"
                                class="bg-neutral-100 tw-py-3 tw-pe-205 tw-ps-6 text-heading hover-text-white hover-bg-main-600 group tw-text-lg fw-bold d-flex align-items-center justify-content-between tw-duration-200 tw-rounded">
                                Social Post Publishing Queue
                                <span
                                    class="tw-px-205 tw-py-3 text-neutral-600 bg-neutral-100 group-hover-text-main-600 tw-rounded tw-duration-300 d-flex">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                            <a href="<?= url('marketplace') ?>"
                                class="bg-neutral-100 tw-py-3 tw-pe-205 tw-ps-6 text-heading hover-text-white hover-bg-main-600 group tw-text-lg fw-bold d-flex align-items-center justify-content-between tw-duration-200 tw-rounded">
                                Verified Business Directory
                                <span
                                    class="tw-px-205 tw-py-3 text-neutral-600 bg-neutral-100 group-hover-text-main-600 tw-rounded tw-duration-300 d-flex">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                            <a href="<?= url('dashboard') ?>"
                                class="bg-neutral-100 tw-py-3 tw-pe-205 tw-ps-6 text-heading hover-text-white hover-bg-main-600 group tw-text-lg fw-bold d-flex align-items-center justify-content-between tw-duration-200 tw-rounded">
                                Heuristic Campaign Doctor
                                <span
                                    class="tw-px-205 tw-py-3 text-neutral-600 bg-neutral-100 group-hover-text-main-600 tw-rounded tw-duration-300 d-flex">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                            <a href="<?= url('send-email') ?>"
                                class="bg-neutral-100 tw-py-3 tw-pe-205 tw-ps-6 text-heading hover-text-white hover-bg-main-600 group tw-text-lg fw-bold d-flex align-items-center justify-content-between tw-duration-200 tw-rounded">
                                HTML Email Outreach Studio
                                <span
                                    class="tw-px-205 tw-py-3 text-neutral-600 bg-neutral-100 group-hover-text-main-600 tw-rounded tw-duration-300 d-flex">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                        </div>
                        <div
                            class="tw-mt-10 position-relative gradient-overlay-1 z-index-1 tw-rounded-xl overflow-hidden">
                            <img src="<?= ASSETS_URL ?>/images/thumbs/about-two-image.png" alt="Image"
                                class="h-100 w-100 object-fit-cover tw-rounded-xl">
                            <div class="position-absolute bottom-0 tw-start-0 tw-ms-9 tw-mb-13">
                                <img src="<?= ASSETS_URL ?>/images/icons/headphone-icon.png" alt="Icon" class="text-invert-white">
                                <h2 class="h5 fw-medium tw-mt-6">
                                    <a href="tel:+12(029)838994" class="text-white hover-underline">+12 (029) 838
                                        994</a>
                                </h2>
                                <h2 class="h5 fw-medium tw-mt-4">
                                    <a href="mailto:<?= e(get_setting('site_email', 'support@adsbooster.net')) ?>"
                                        class="text-white hover-underline"><?= e(get_setting('site_email', 'support@adsbooster.net')) ?></a>
                                </h2>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8 ps-xl-5">
                        <div class="">
                            <div class="hover-animation-white">
                                <img src="<?= ASSETS_URL ?>/images/thumbs/service-details-img1.png" alt="Thumb"
                                    class="tw-rounded-xl w-100 h-100 object-fit-cover">
                            </div>
                            <div class="tw-mt-8 d-flex flex-column tw-gap-12">
                                <div class="">
                                    <h2 class="h4 tw-mb-3 splitTextStyleOne">Omnichannel Multi-Network Advertising</h2>
                                    <p class="text-neutral-600">Adsbooster enables you to launch and manage ad campaigns across Meta, Instagram, TikTok, Google Ads, and Snapchat from a single powerful dashboard. Our AI-powered tools generate winning creatives, auto-schedule posts, and deliver real-time performance analytics to maximize your return on ad spend.</p>
                                </div>
                                <div class="">
                                    <h2 class="h5 tw-mb-3 splitTextStyleOne">Autonomous Growth &amp; Real-Time Performance Doctor</h2>
                                    <p class="text-neutral-600">Whether you are a solo entrepreneur or an agency managing dozens of clients, Adsbooster gives you the tools to launch, optimize, and scale campaigns across every major platform without switching tabs. Our AI engine continuously learns from campaign data to improve targeting, bidding, and creative performance in real time.</p>
                                    <div class="tw-mt-15 d-flex flex-column tw-gap-5">
                                        <div class="d-flex align-items-center tw-gap-4">
                                            <span
                                                class="tw-w-4 tw-h-4 bg-main-600 text-white tw-text-xs d-flex justify-content-center align-items-center rounded-circle">
                                                <i class="ph-bold ph-check"></i>
                                            </span>
                                            <p class="text-neutral-500">AI-generated ad creatives tailored to each platform and audience segment.</p>
                                        </div>
                                        <div class="d-flex align-items-center tw-gap-4">
                                            <span
                                                class="tw-w-4 tw-h-4 bg-main-600 text-white tw-text-xs d-flex justify-content-center align-items-center rounded-circle">
                                                <i class="ph-bold ph-check"></i>
                                            </span>
                                            <p class="text-neutral-500">Automated post scheduling across five major social networks.</p>
                                        </div>
                                        <div class="d-flex align-items-center tw-gap-4">
                                            <span
                                                class="tw-w-4 tw-h-4 bg-main-600 text-white tw-text-xs d-flex justify-content-center align-items-center rounded-circle">
                                                <i class="ph-bold ph-check"></i>
                                            </span>
                                            <p class="text-neutral-500">Real-time analytics and performance tracking in one unified dashboard.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="">
                                    <div class="row gy-4">
                                        <div class="col-sm-6">
                                            <div class="h-100 hover-animation-white">
                                                <img src="<?= ASSETS_URL ?>/images/thumbs/service-details-img2.png" alt="Thumbnail"
                                                    class="tw-rounded-xl w-100 h-100 object-fit-cover">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="h-100 hover-animation-white">
                                                <img src="<?= ASSETS_URL ?>/images/thumbs/service-details-img3.png" alt="Thumbnail"
                                                    class="tw-rounded-xl w-100 h-100 object-fit-cover">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="">
                                    <h2 class="text-22-px tw-mb-4">Have Your Any Questions About Services</h2>

                                </div>

                                <div class="accordion common-accordion d-flex flex-column tw-gap-5"
                                    id="accordionExample">
                                    <div class="accordion-item tw-py-3 tw-px-8 tw-rounded-xl bg-transparent mb-0 border border-neutral-200"
                                        data-aos="fade-up" data-aos-anchor-placement="top-bottom"
                                        data-aos-duration="800">
                                        <h2
                                            class="h5 accordion-header d-flex align-items-center justify-content-between tw-gap-3">
                                            <button
                                                class="accordion-button shadow-none p-0 line-clamp-3 bg-transparent text-22-px fw-semibold collapsed"
                                                type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo"
                                                aria-expanded="false" aria-controls="collapseTwo">
                                                1. What services does Adsbooster provide?
                                            </button>
                                        </h2>
                                        <div id="collapseTwo" class="accordion-collapse collapse"
                                            data-bs-parent="#accordionExample">
                                            <div
                                                class="accordion-body ps-0 pe-0 pb-0 tw-pt-5 max-w-620-px border-top border-neutral-200 tw-mt-5">
                                                <p class="text-neutral-500 tw-leading-212">Accelerate innovation with
                                                    world-class tech teams We'll match you to an entire remote team of
                                                    incredible freelance talent for all your software.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item tw-py-3 tw-px-8 tw-rounded-xl bg-transparent mb-0 border border-neutral-200"
                                        data-aos="fade-up" data-aos-anchor-placement="top-bottom"
                                        data-aos-duration="800">
                                        <h2
                                            class="h5 accordion-header d-flex align-items-center justify-content-between tw-gap-3">
                                            <button
                                                class="accordion-button shadow-none p-0 line-clamp-3 bg-transparent text-22-px fw-semibold"
                                                type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne"
                                                aria-expanded="true" aria-controls="collapseOne">
                                                2. How can I get started with Adsbooster?
                                            </button>
                                        </h2>
                                        <div id="collapseOne" class="accordion-collapse collapse show"
                                            data-bs-parent="#accordionExample">
                                            <div
                                                class="accordion-body ps-0 pe-0 pb-0 tw-pt-5 max-w-620-px border-top border-neutral-200 tw-mt-5">
                                                <p class="text-neutral-500 tw-leading-212">Accelerate innovation with
                                                    world-class tech teams We'll match you to an entire remote team of
                                                    incredible freelance talent for all your software.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item tw-py-3 tw-px-8 tw-rounded-xl bg-transparent mb-0 border border-neutral-200"
                                        data-aos="fade-up" data-aos-anchor-placement="top-bottom"
                                        data-aos-duration="800">
                                        <h2
                                            class="h5 accordion-header d-flex align-items-center justify-content-between tw-gap-3">
                                            <button
                                                class="accordion-button shadow-none p-0 line-clamp-3 bg-transparent text-22-px fw-semibold collapsed"
                                                type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree"
                                                aria-expanded="false" aria-controls="collapseThree">
                                                3. Do you provide real-time shipment tracking?
                                            </button>
                                        </h2>
                                        <div id="collapseThree" class="accordion-collapse collapse"
                                            data-bs-parent="#accordionExample">
                                            <div
                                                class="accordion-body ps-0 pe-0 pb-0 tw-pt-5 max-w-620-px border-top border-neutral-200 tw-mt-5">
                                                <p class="text-neutral-500 tw-leading-212">Accelerate innovation with
                                                    world-class tech teams We'll match you to an entire remote team of
                                                    incredible freelance talent for all your software.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item tw-py-3 tw-px-8 tw-rounded-xl bg-transparent mb-0 border border-neutral-200"
                                        data-aos="fade-up" data-aos-anchor-placement="top-bottom"
                                        data-aos-duration="800">
                                        <h2
                                            class="h5 accordion-header d-flex align-items-center justify-content-between tw-gap-3">
                                            <button
                                                class="accordion-button shadow-none p-0 line-clamp-3 bg-transparent text-22-px fw-semibold collapsed"
                                                type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour"
                                                aria-expanded="false" aria-controls="collapseFour">
                                                4. What areas does Adsbooster cover?
                                            </button>
                                        </h2>
                                        <div id="collapseFour" class="accordion-collapse collapse"
                                            data-bs-parent="#accordionExample">
                                            <div
                                                class="accordion-body ps-0 pe-0 pb-0 tw-pt-5 max-w-620-px border-top border-neutral-200 tw-mt-5">
                                                <p class="text-neutral-500 tw-leading-212">Accelerate innovation with
                                                    world-class tech teams We'll match you to an entire remote team of
                                                    incredible freelance talent for all your software.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item tw-py-3 tw-px-8 tw-rounded-xl bg-transparent mb-0 border border-neutral-200"
                                        data-aos="fade-up" data-aos-anchor-placement="top-bottom"
                                        data-aos-duration="800">
                                        <h2
                                            class="h5 accordion-header d-flex align-items-center justify-content-between tw-gap-3">
                                            <button
                                                class="accordion-button shadow-none p-0 line-clamp-3 bg-transparent text-22-px fw-semibold collapsed"
                                                type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive"
                                                aria-expanded="false" aria-controls="collapseFive">
                                                5. What services do you offer?
                                            </button>
                                        </h2>
                                        <div id="collapseFive" class="accordion-collapse collapse"
                                            data-bs-parent="#accordionExample">
                                            <div
                                                class="accordion-body ps-0 pe-0 pb-0 tw-pt-5 max-w-620-px border-top border-neutral-200 tw-mt-5">
                                                <p class="text-neutral-500 tw-leading-212">Accelerate innovation with
                                                    world-class tech teams We'll match you to an entire remote team of
                                                    incredible freelance talent for all your software.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item tw-py-3 tw-px-8 tw-rounded-xl bg-transparent mb-0 border border-neutral-200"
                                        data-aos="fade-up" data-aos-anchor-placement="top-bottom"
                                        data-aos-duration="800">
                                        <h2
                                            class="h5 accordion-header d-flex align-items-center justify-content-between tw-gap-3">
                                            <button
                                                class="accordion-button shadow-none p-0 line-clamp-3 bg-transparent text-22-px fw-semibold collapsed"
                                                type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix"
                                                aria-expanded="false" aria-controls="collapseSix">
                                                6. How do you measure success?
                                            </button>
                                        </h2>
                                        <div id="collapseSix" class="accordion-collapse collapse"
                                            data-bs-parent="#accordionExample">
                                            <div
                                                class="accordion-body ps-0 pe-0 pb-0 tw-pt-5 max-w-620-px border-top border-neutral-200 tw-mt-5">
                                                <p class="text-neutral-500 tw-leading-212">Accelerate innovation with
                                                    world-class tech teams We'll match you to an entire remote team of
                                                    incredible freelance talent for all your software.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- ======================== Services Details end ========================== -->

<?php require __DIR__ . '/includes/footer.php'; ?>
