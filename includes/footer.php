<?php declare(strict_types=1); ?>

<!-- ======================= Footer New section start =============================== -->
<section class="footer-new bg-black overflow-hidden">
    <div class="pt-140">
        <div class="container max-w-1700-px">
            <div class="row gy-4 align-items-start">
                <div class="col-lg-4 col-md-5 col-12" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="text-md-start">
                        <a href="<?= url('index') ?>" class="d-inline-flex align-items-center tw-gap-2">
                            <div class="tw-w-10 tw-h-10 bg-main-600 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="ph-bold ph-arrow-up-right text-black tw-text-lg"></i>
                            </div>
                            <span class="text-white fw-bold tw-text-xl"><?= e(get_setting('site_name', 'Adsbooster.net')) ?></span>
                        </a>
                        <div class="d-flex flex-column tw-gap-2 tw-mt-4">
                            <a href="mailto:<?= e(get_setting('site_email', 'support@adsbooster.net')) ?>"
                                class="h3 text-white hover-text-main-600 mb-0 text-break"><?= e(get_setting('site_email', 'support@adsbooster.net')) ?></a>
                            <a href="tel:<?= e(get_setting('site_phone', '+234 902 442 8551')) ?>" class="h3 text-white hover-text-main-600 mb-0 text-break"><?= e(get_setting('site_phone', '+234 902 442 8551')) ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4 col-12" data-aos="fade-up" data-aos-duration="800" data-aos-delay="400">
                    <span class="d-block fw-semibold tw-text-xl text-decoration-underline text-white">How to find
                        us?</span>
                    <p class="tw-mt-8 fw-normal tw-text-lg text-white-75 mb-0"><?= e(get_setting('site_address', 'Run ads across Meta, Instagram, TikTok, Google Ads, and Snapchat from one dashboard.')) ?></p>
                </div>
                <div class="col-lg-4 col-md-3 col-12 text-md-end text-start" data-aos="fade-up" data-aos-duration="800" data-aos-delay="600">
                    <ul class="d-flex align-items-center justify-content-md-end tw-gap-305 mb-0" style="list-style:none;padding:0;">
                        <li>
                            <a href="<?= e(get_setting('social_facebook', 'https://www.facebook.com')) ?>"
                                class="text-white tw-w-10 tw-h-10 bg-yellow-950 rounded-circle d-flex justify-content-center align-items-center tw-text-base tw-leading-none hover-bg-main-600 hover-text-heading active-scale-09" aria-label="Facebook"><i
                                    class="ph-bold ph-facebook-logo"></i></a>
                        </li>
                        <li>
                            <a href="<?= e(get_setting('social_twitter', 'https://www.twitter.com')) ?>"
                                class="text-white tw-w-10 tw-h-10 bg-yellow-950 rounded-circle d-flex justify-content-center align-items-center tw-text-base tw-leading-none hover-bg-main-600 hover-text-heading active-scale-09" aria-label="Twitter"><i
                                    class="ph-bold ph-twitter-logo"></i></a>
                        </li>
                        <li>
                            <a href="<?= e(get_setting('social_linkedin', 'https://www.linkedin.com')) ?>"
                                class="text-white tw-w-10 tw-h-10 bg-yellow-950 rounded-circle d-flex justify-content-center align-items-center tw-text-base tw-leading-none hover-bg-main-600 hover-text-heading active-scale-09" aria-label="LinkedIn"><i
                                    class="ph-bold ph-instagram-logo"></i></a>
                        </li>
                        <li>
                            <a href="<?= e(get_setting('social_pinterest', 'https://www.pinterest.com')) ?>"
                                class="text-white tw-w-10 tw-h-10 bg-yellow-950 rounded-circle d-flex justify-content-center align-items-center tw-text-base tw-leading-none hover-bg-main-600 hover-text-heading active-scale-09" aria-label="Pinterest"><i
                                    class="ph-bold ph-pinterest-logo"></i></a>
                        </li>
                    </ul>
                </div>
            </div>
            <!-- Legal links — required for Terms / Privacy / DMCA discoverability -->
            <div class="tw-pt-10 mt-4" style="border-top:1px solid rgba(255,255,255,.08);">
                <ul class="d-flex align-items-center justify-content-center flex-wrap tw-gap-x-4 tw-gap-y-2 mb-0 text-center" style="list-style:none;padding:0;">
                    <li><a href="<?= url('terms') ?>" class="tw-text-sm text-white-50 hover-text-main-600 text-decoration-none">Terms of Service</a></li>
                    <li><a href="<?= url('privacy') ?>" class="tw-text-sm text-white-50 hover-text-main-600 text-decoration-none">Privacy Policy</a></li>
                    <li><a href="<?= url('ai-disclaimer') ?>" class="tw-text-sm text-white-50 hover-text-main-600 text-decoration-none">AI &amp; Advertising Disclaimer</a></li>
                    <li><a href="<?= url('dmca') ?>" class="tw-text-sm text-white-50 hover-text-main-600 text-decoration-none">Copyright &amp; DMCA</a></li>
                    <li><a href="<?= url('refund-policy') ?>" class="tw-text-sm text-white-50 hover-text-main-600 text-decoration-none">Billing &amp; Refunds</a></li>
                    <li><a href="<?= url('cookie-policy') ?>" class="tw-text-sm text-white-50 hover-text-main-600 text-decoration-none">Cookie Policy</a></li>
                </ul>
            </div>
            <div class="tw-pt-8 tw-pb-12 text-center">
                <p class="text-white text-line-1 fw-normal mb-0">
                    Copyright &copy; <?= date('Y') ?> <?= e(get_setting('site_name', 'Adsbooster.net')) ?>. All rights reserved.
                </p>
            </div>
        </div>

        <h2 class="display-1 animated-title text-white-15 text-center text-hover-animation-scale fw-bold mb-0"><?= e(get_setting('site_name', 'Adsbooster.net')) ?></h2>
    </div>
    <!-- bottom Footer -->
</section>
<!-- ======================= Footer New section end =============================== -->

    </div>
</div>

<?php require_once __DIR__ . '/cookie_consent.php'; ?>

<!-- Jquery js -->
<script src="<?= BASE_URL ?>/assets/js/jquery-3.7.1.min.js" defer></script>
<!-- phosphor Js -->
<script src="<?= BASE_URL ?>/assets/js/phosphor-icon.js" defer></script>
<!-- Bootstrap Bundle Js -->
<script src="<?= BASE_URL ?>/assets/js/boostrap.bundle.min.js" defer></script>
<!-- aos -->
<script src="<?= BASE_URL ?>/assets/js/aos.js" defer></script>

<!-- GSAP js -->
<script src="<?= BASE_URL ?>/assets/js/gsap.min.js" defer></script>
<!-- SplitText -->
<script src="<?= BASE_URL ?>/assets/js/SplitText.min.js" defer></script>
<!-- Scroll Trigger -->
<script src="<?= BASE_URL ?>/assets/js/ScrollTrigger.min.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/ScrollSmoother.min.js" defer></script>
<!-- custom GSAP -->
<script src="<?= BASE_URL ?>/assets/js/custom-gsap.js" defer></script>

<!-- flowmap effect -->
<script src="<?= BASE_URL ?>/assets/js/flowmap-effect.min.js" defer></script>
<!-- counterup -->
<script src="<?= BASE_URL ?>/assets/js/counterup.min.js" defer></script>
<!-- swiper slider -->
<script src="<?= BASE_URL ?>/assets/js/swiper-bundle.min.js" defer></script>
<!-- marquee -->
<script src="<?= BASE_URL ?>/assets/js/jquery.marquee.min.js" defer></script>
<!-- magnific popup -->
<script src="<?= BASE_URL ?>/assets/js/magnific-popup.min.js" defer></script>
<!-- parallax -->
<script src="<?= BASE_URL ?>/assets/js/parallax.min.js" defer></script>

<!-- main js -->
<script src="<?= BASE_URL ?>/assets/js/main.js" defer></script>
<script>
// Fallback: ensure preloader hides even if window load event is blocked by 404s
setTimeout(function(){ document.querySelectorAll('.loader-mask').forEach(function(el){ el.style.display='none'; }); }, 3000);
</script>

<?php if (is_logged_in()): ?>
<script>
// Poll notifications every 25 seconds
if (document.querySelector('.ph-bell')) {
    setInterval(function() {
        fetch('<?= url('notifications-poll') ?>')
            .then(function(r){ return r.json(); })
            .then(function(data){
                var badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.textContent = data.unread_count;
                    badge.style.display = data.unread_count > 0 ? 'flex' : 'none';
                }
            }).catch(function(){});
    }, 25000);
}
</script>
<?php endif; ?>

</body>

</html>
