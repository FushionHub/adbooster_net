<?php
declare(strict_types=1);

$pageTitle = 'Projects - Adsbooster.net'; require __DIR__ . '/includes/header.php'; ?>

        <!-- ==================== Breadcrumb Start Here ==================== -->
<section class="breadcrumb position-relative background-img bg-img z-1 mb-0" data-background-image="<?= ASSETS_URL ?>/images/thumbs/breadcrumb-bg-img.png">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center">
                    <ul class="d-inline-flex align-items-center justify-content-center tw-gap-2 bg-white-08 rounded-pill tw-px-5 tw-py-2">
                        <li><a href="<?= url('index') ?>" class="text-white hover--translate-y-1 hover-underline"> <i class="las la-home"></i> Home</a> </li>
                        <li class="text-white tw-text-lg">/</li>
                        <li> <span class="text-white"> Our Projects </span> </li>
                    </ul>
                    <h2 class="text-center tw-mb-6 text-white tw-mt-6 cursor-big"> Our Projects</h2>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ==================== Breadcrumb End Here ==================== -->

        <!-- ======================== Project Page section Start ========================== -->
        <section class="project py-120">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xl-10">
                        <div class="row gy-4">
                            <div class="col-md-6">
                                <div class="bg-white tw-rounded-xl tw-p-6 image-double-animation h-100 common-shadow-5"
                                    data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                                    <a href="<?= url('project-details') ?>"
                                        class="tw-rounded-xl overflow-hidden d-block position-relative view-cursor-show">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img1.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img1.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <span
                                            class="bg-main-two-600 text-heading tw-text-sm fw-medium tw-py-105 tw-px-5 rounded-pill position-absolute top-0 tw-start-0 tw-mt-3 tw-ms-3 z-1">
                                            Marketing
                                        </span>
                                    </a>
                                    <div class="tw-pt-6">
                                        <h2 class="h6 tw-mb-4">
                                            <a href="<?= url('project-details') ?>"
                                                class="text-heading hover-text-main-600">Omnichannel ROAS Scaling</a>
                                        </h2>
                                        <p class="text-neutral-500 fw-normal tw-text-lg">
                                            Cross-platform Meta &amp; TikTok combo campaigns scaling direct-to-consumer revenue with automated pixel synchronization.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-white tw-rounded-xl tw-p-6 image-double-animation h-100 common-shadow-5"
                                    data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                                    <a href="<?= url('project-details') ?>"
                                        class="tw-rounded-xl overflow-hidden d-block position-relative view-cursor-show">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img2.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img2.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <span
                                            class="bg-main-two-600 text-heading tw-text-sm fw-medium tw-py-105 tw-px-5 rounded-pill position-absolute top-0 tw-start-0 tw-mt-3 tw-ms-3 z-1">
                                            AI Creative
                                        </span>
                                    </a>
                                    <div class="tw-pt-6">
                                        <h2 class="h6 tw-mb-4">
                                            <a href="<?= url('ugc-generator') ?>" class="text-heading hover-text-main-600">
                                                AI UGC Video Creative Engine
                                            </a>
                                        </h2>
                                        <p class="text-neutral-500 fw-normal tw-text-lg">
                                            Deploying viral 3-second hooks, multi-scene storyboards, and interactive TikTok reel scripts via Groq AI in seconds.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-white tw-rounded-xl tw-p-6 image-double-animation h-100 common-shadow-5"
                                    data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                                    <a href="<?= url('marketplace') ?>"
                                        class="tw-rounded-xl overflow-hidden d-block position-relative view-cursor-show">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img3.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img3.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <span
                                            class="bg-main-two-600 text-heading tw-text-sm fw-medium tw-py-105 tw-px-5 rounded-pill position-absolute top-0 tw-start-0 tw-mt-3 tw-ms-3 z-1">
                                            Marketplace
                                        </span>
                                    </a>
                                    <div class="tw-pt-6">
                                        <h2 class="h6 tw-mb-4">
                                            <a href="<?= url('marketplace') ?>"
                                                class="text-heading hover-text-main-600">Verified Business Directory</a>
                                        </h2>
                                        <p class="text-neutral-500 fw-normal tw-text-lg">
                                            Connecting local clinics, hotels, eateries, and enterprises with direct inbound WhatsApp, phone, and maps traffic.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-white tw-rounded-xl tw-p-6 image-double-animation h-100 common-shadow-5"
                                    data-aos="fade-up" data-aos-duration="800" data-aos-delay="400">
                                    <a href="<?= url('create-ad') ?>"
                                        class="tw-rounded-xl overflow-hidden d-block position-relative view-cursor-show">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img4.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img4.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <span
                                            class="bg-main-two-600 text-heading tw-text-sm fw-medium tw-py-105 tw-px-5 rounded-pill position-absolute top-0 tw-start-0 tw-mt-3 tw-ms-3 z-1">
                                            Google Ads
                                        </span>
                                    </a>
                                    <div class="tw-pt-6">
                                        <h2 class="h6 tw-mb-4">
                                            <a href="<?= url('create-ad') ?>"
                                                class="text-heading hover-text-main-600">Performance Max Automation</a>
                                        </h2>
                                        <p class="text-neutral-500 fw-normal tw-text-lg">
                                            High-intent search keyword discovery, bid doctor automation, and YouTube video ad placement across 180+ countries.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-white tw-rounded-xl tw-p-6 image-double-animation h-100 common-shadow-5"
                                    data-aos="fade-up" data-aos-duration="800" data-aos-delay="500">
                                    <a href="<?= url('project-details') ?>"
                                        class="tw-rounded-xl overflow-hidden d-block position-relative view-cursor-show">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img1.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img1.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <span
                                            class="bg-main-two-600 text-heading tw-text-sm fw-medium tw-py-105 tw-px-5 rounded-pill position-absolute top-0 tw-start-0 tw-mt-3 tw-ms-3 z-1">
                                            Marketing
                                        </span>
                                    </a>
                                    <div class="tw-pt-6">
                                        <h2 class="h6 tw-mb-4">
                                            <a href="<?= url('dashboard') ?>" class="text-heading hover-text-main-600">Autonomous Campaign Doctor</a>
                                        </h2>
                                        <p class="text-neutral-500 fw-normal tw-text-lg">
                                            24/7 algorithmic monitoring detecting CPA spikes, shifting budgets dynamically, and pausing fatigued ad sets.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-white tw-rounded-xl tw-p-6 image-double-animation h-100 common-shadow-5"
                                    data-aos="fade-up" data-aos-duration="800" data-aos-delay="600">
                                    <a href="<?= url('send-email') ?>"
                                        class="tw-rounded-xl overflow-hidden d-block position-relative view-cursor-show">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img2.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/project-two-img2.png" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <span
                                            class="bg-main-two-600 text-heading tw-text-sm fw-medium tw-py-105 tw-px-5 rounded-pill position-absolute top-0 tw-start-0 tw-mt-3 tw-ms-3 z-1">
                                            Outreach
                                        </span>
                                    </a>
                                    <div class="tw-pt-6">
                                        <h2 class="h6 tw-mb-4">
                                            <a href="<?= url('send-email') ?>" class="text-heading hover-text-main-600">HTML Email Outreach Studio</a>
                                        </h2>
                                        <p class="text-neutral-500 fw-normal tw-text-lg">
                                            Integrated PHPMailer transport and CKEditor 5 for direct B2B partnership outreach and automated subscriber engagement.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <ul class="pagination d-flex align-items-center tw-gap-4 justify-content-center tw-mt-15">
                    <li>
                        <a href="javascript:void(0)"
                            class="tw-w-12 tw-h-12 rounded-circle text-heading border border-neutral-400 hover-bg-main-600 hover-text-white hover-border-main-600 d-flex justify-content-center align-items-center tw-text-lg fw-medium">01</a>
                    </li>
                    <li>
                        <a href="javascript:void(0)"
                            class="tw-w-12 tw-h-12 rounded-circle text-heading border border-neutral-400 hover-bg-main-600 hover-text-white hover-border-main-600 d-flex justify-content-center align-items-center tw-text-lg fw-medium">02</a>
                    </li>
                    <li>
                        <a href="javascript:void(0)"
                            class="tw-w-12 tw-h-12 rounded-circle text-heading border border-neutral-400 hover-bg-main-600 hover-text-white hover-border-main-600 d-flex justify-content-center align-items-center tw-text-2xl fw-medium"><i
                                class="ph ph-arrow-right"></i></a>
                    </li>
                </ul>
            </div>
        </section>
        <!-- ======================== Project Page section end ========================== -->

<?php require __DIR__ . '/includes/footer.php'; ?>
