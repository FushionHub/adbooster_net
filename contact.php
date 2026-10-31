<?php
declare(strict_types=1);

$pageTitle = 'Contact Us - Adsbooster.net';
require __DIR__ . '/includes/header.php';

$contact = get_setting('contact');
$phone = get_setting('phone');
$email = get_setting('email');
$address = get_setting('address');
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
                        <li> <span class="text-white"> Contact Us </span> </li>
                    </ul>
                    <h2 class="text-center tw-mb-6 text-white tw-mt-6 cursor-big"> Contact Us</h2>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- ==================== Breadcrumb End Here ==================== -->

        <!-- =========================== Contact section start =============================== -->
        <section class="contact py-120">
            <div class="container">
                <div class="row gy-5 align-items-center">
                    <div class="col-lg-6">
                        <div class="">
                            <div class="tw-mb-16 max-w-890-px" data-aos="fade-up" data-aos-duration="800"
                                data-aos-delay="0">
                                <span
                                    class="bg-main-100 text-main-600 fw-medium tw-text-base rounded-pill tw-px-5 tw-py-1 tw-mb-6">
                                    Contact Us</span>
                                <h2 class="splitTextStyleOne cursor-big">
                                    Contact With Us for
                                    <span class="font-playfair fw-normal font-playfair fst-italic">Your Any Help</span>
                                </h2>
                                <p class="tw-text-lg text-neutral-500 tw-mt-6">
                                    Meet our talented members, a group of creative minds who bring innovative ideas to
                                    life. Each team member excels in their field, driving excellence and creativity in
                                    every project.
                                </p>
                            </div>

                            <div class="row gy-5">
                                <div class="col-sm-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                                    <div class="">
                                        <h2 class="h5 tw-mb-8">Call Center: </h2>
                                        <div class="d-flex flex-column tw-gap-2">
                                            <a href="tel:<?= e($phone) ?>"
                                                class="text-neutral-500 hover-text-main-600 hover-underline"><?= e($phone) ?></a>
                                            <a href="tel:<?= e($phone) ?>"
                                                class="text-neutral-500 hover-text-main-600 hover-underline"><?= e($phone) ?></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                                    <div class="">
                                        <h2 class="h5 tw-mb-8">Our Location: </h2>
                                        <div class="d-flex flex-column tw-gap-2">
                                            <p class="text-neutral-500"><?= e($address) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                                    <div class="">
                                        <h2 class="h5 tw-mb-8">Our social: </h2>
                                        <ul
                                            class="pagination d-flex align-items-center tw-gap-7 justify-content-start flex-wrap">
                                            <li>
                                                <a href="javascript:void(0)"
                                                    class="tw-text-2xl text-heading hover-text-main-600">
                                                    <i class="ph-bold ph-facebook-logo"></i>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="javascript:void(0)"
                                                    class="tw-text-2xl text-heading hover-text-main-600">
                                                    <i class="ph-bold ph-twitter-logo"></i>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="javascript:void(0)"
                                                    class="tw-text-2xl text-heading hover-text-main-600">
                                                    <i class="ph-bold ph-instagram-logo"></i>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="javascript:void(0)"
                                                    class="tw-text-2xl text-heading hover-text-main-600">
                                                    <i class="ph-bold ph-pinterest-logo"></i>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-sm-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                                    <div class="">
                                        <h2 class="h5 tw-mb-8">Our Email: </h2>
                                        <div class="d-flex flex-column tw-gap-2">
                                            <a href="mailto:<?= e($email) ?>"
                                                class="text-neutral-500 hover-text-main-600 hover-underline"><?= e($email) ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="bg-main-50 tw-p-48-px tw-rounded-xl">
                            <h2 class="h5 tw-mb-5">Send Your Message</h2>
                            <form action="#" class="form-submit d-flex flex-column tw-gap-5">
                                <div class="">
                                    <input type="text"
                                        class="bg-white tw-px-6 tw-py-3 tw-h-13 tw-rounded-md w-100 focus-outline-0 border border-white focus-border-main-600"
                                        placeholder="Your name">
                                </div>
                                <div class="">
                                    <input type="email"
                                        class="bg-white tw-px-6 tw-py-3 tw-h-13 tw-rounded-md w-100 focus-outline-0 border border-white focus-border-main-600"
                                        placeholder="Enter email">
                                </div>
                                <div class="">
                                    <input type="tel"
                                        class="bg-white tw-px-6 tw-py-3 tw-h-13 tw-rounded-md w-100 focus-outline-0 border border-white focus-border-main-600"
                                        placeholder="Phone">
                                </div>
                                <div class="">
                                    <select
                                        class="bg-white tw-px-6 tw-py-3 tw-h-13 tw-rounded-md w-100 focus-outline-0 border border-white focus-border-main-600 text-neutral-500 form-select shadow-none">
                                        <option value="Your Subject" selected hidden>Your Subject</option>
                                        <option value="SEO Marketing">SEO Marketing</option>
                                        <option value="Digital Marketing">Digital Marketing</option>
                                        <option value="Digital Agency">Digital Agency</option>
                                    </select>
                                </div>
                                <div class="">
                                    <input type="text"
                                        class="bg-white tw-px-6 tw-py-3 tw-h-13 tw-rounded-md w-100 focus-outline-0 border border-white focus-border-main-600"
                                        placeholder="Your name">
                                </div>
                                <div class="">
                                    <textarea
                                        class="bg-white tw-px-6 tw-py-3 tw-h-120-px tw-rounded-md w-100 focus-outline-0 border border-white focus-border-main-600"
                                        placeholder="Type your message..."></textarea>
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
        </section>
        <!-- =========================== Contact section end =============================== -->


        <!-- Google Map start -->
        <div class="tw-h-402-px">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m16!1m12!1m3!1d18594861.431429405!2d25.097591251485795!3d54.811518433582705!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!2m1!1ssea!5e0!3m2!1sen!2sbd!4v1752124108423!5m2!1sen!2sbd"
                class="w-100 h-100"></iframe>
        </div>
        <!-- Google Map end -->

<?php require __DIR__ . '/includes/footer.php'; ?>
