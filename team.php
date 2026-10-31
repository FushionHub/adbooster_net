<?php
declare(strict_types=1);

$pageTitle = 'Team - Adsbooster.net';
require_once __DIR__ . '/includes/header.php';

$members = db()->fetchAll("SELECT * FROM users WHERE role != 'admin' ORDER BY id ASC");
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
                        <li> <span class="text-white"> Team </span> </li>
                    </ul>
                    <h2 class="text-center tw-mb-6 text-white tw-mt-6 cursor-big"> Team</h2>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ==================== Breadcrumb End Here ==================== -->

        <!-- =========================== Team page section start =============================== -->
        <section class="team-three py-120 bg-blue-light position-relative z-1">
            <div class="position-relative">
                <img src="<?= ASSETS_URL ?>/images/shapes/team-three-shape1.png" alt="Shape"
                    class="position-absolute top-0 tw-start-0 tw-mt-17 tw-pt-205 animated-upDown">
                <div class="container position-relative">
                    <img src="<?= ASSETS_URL ?>/images/shapes/team-three-shape2.png" alt="Shape"
                        class="position-absolute top-0 tw-end-0 tw-mt-17 tw-pt-7 left-right-animation">

                    <div class="tw-mb-14 max-w-890-px mx-auto text-center" data-aos="fade-up" data-aos-duration="800"
                        data-aos-delay="0">
                        <span
                            class="bg-main-100 text-main-600 fw-medium tw-text-base rounded-pill tw-px-5 tw-py-1 tw-mb-6">
                            Our Members</span>
                        <h2 class="splitTextStyleOne cursor-big">
                            Meet With Our Marketing
                            <span class="font-playfair fw-normal font-playfair fst-italic">Team Members</span>
                        </h2>
                        <p class="tw-text-lg text-neutral-500 tw-mt-6 max-w-590-px mx-auto">
                            Our team of advertising specialists and AI engineers builds the tools that power multi-network ad campaigns across Meta, TikTok, Google Ads, and Snapchat.
                        </p>
                    </div>

                    <div class="row gy-4">
                        <?php if (!empty($members)): ?>
                        <?php foreach ($members as $index => $member): ?>
                        <div class="col-lg-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="<?= ($index % 2 === 0) ? '100' : '300' ?>">
                            <div
                                class="team-three-item image-double-animation bg-white tw-rounded-xl tw-p-205 d-flex flex-sm-row align-items-sm-center flex-column tw-gap-6 justify-content-between">
                                <div class="tw-ps-705 tw-py-3 flex-grow-1">
                                    <h2 class="h5 tw-mb-2">
                                        <a href="<?= url('team-details') ?>?id=<?= $member['id'] ?>"
                                            class="text-heading fw-semibold hover-text-main-600 d-inline-flex align-items-center tw-gap-205 group-item element-double-animation">
                                            <span class="element-double-animation__element"
                                                data-content="<?= e($member['full_name'] ?? ($member['name'] ?? 'Team Member')) ?>"><?= e($member['full_name'] ?? ($member['name'] ?? 'Team Member')) ?></span>
                                        </a>
                                    </h2>
                                    <p class="tw-text-lg text-neutral-400">
                                        <?= e($member['role'] ?? 'Team Member') ?>
                                    </p>
                                    <ul class="d-flex align-items-center tw-gap-205 tw-mt-6">
                                        <?php if (!empty($member['facebook'])): ?>
                                        <li>
                                            <a href="<?= e($member['facebook']) ?>"
                                                class="text-heading tw-w-8 tw-h-8 border border-neutral-300 hover-border-main-two-600 rounded-circle d-flex justify-content-center align-items-center tw-text-base tw-leading-none hover-bg-main-two-600 hover-text-heading active-scale-09"><i
                                                    class="ph-fill ph-facebook-logo"></i></a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (!empty($member['twitter'])): ?>
                                        <li>
                                            <a href="<?= e($member['twitter']) ?>"
                                                class="text-heading tw-w-8 tw-h-8 border border-neutral-300 hover-border-main-two-600 rounded-circle d-flex justify-content-center align-items-center tw-text-base tw-leading-none hover-bg-main-two-600 hover-text-heading active-scale-09">
                                                <i class="ph-fill ph-twitter-logo"></i></a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (!empty($member['linkedin'])): ?>
                                        <li>
                                            <a href="<?= e($member['linkedin']) ?>"
                                                class="text-heading tw-w-8 tw-h-8 border border-neutral-300 hover-border-main-two-600 rounded-circle d-flex justify-content-center align-items-center tw-text-base tw-leading-none hover-bg-main-two-600 hover-text-heading active-scale-09"><i
                                                    class="ph-fill ph-linkedin-logo"></i></a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <div class="flex-grow-1">
                                    <a href="<?= url('team-details') ?>?id=<?= $member['id'] ?>"
                                        class="tw-rounded-xl overflow-hidden d-block position-relative view-cursor-show">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/<?= e($member['avatar'] ?? 'team-three-img' . (($index % 8) + 1) . '.png') ?>" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                        <img src="<?= ASSETS_URL ?>/images/thumbs/<?= e($member['avatar'] ?? 'team-three-img' . (($index % 8) + 1) . '.png') ?>" alt="Image"
                                            class="image-double-animation__element tw-rounded-xl w-100 object-fit-cover">
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="col-12">
                            <p class="text-center text-neutral-500">No team members found.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </section>
        <!-- =========================== Team page section end =============================== -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
