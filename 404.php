<?php declare(strict_types=1);

http_response_code(404);
$pageTitle = '404 - Page Not Found — Adsbooster.net';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5 position-relative z-1" style="min-height:75vh; display:flex; align-items:center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4" 
                     style="width:110px; height:110px; background:rgba(200,255,0,0.1); border:2px solid rgba(200,255,0,0.3); color:var(--accent); font-size:46px;">
                    <i class="ph-bold ph-compass"></i>
                </div>
                <span class="badge bg-main-900 bg-opacity-10 text-main-950 border border-main-600 border-opacity-30 rounded-pill px-3 py-2 font-size-12 fw-bold text-uppercase mb-3 d-inline-block">
                    Error 404 — Missing Resource
                </span>
                <h1 class="text-white fw-bold display-4 mb-3">Page Not Found</h1>
                <p class="text-white-50 font-size-16 mx-auto mb-4" style="max-width:540px; line-height:1.6;">
                    The page or listing you are looking for does not exist, has been archived, or was moved to a new destination.
                </p>

                <!-- Search form -->
                <form action="<?= url('marketplace') ?>" method="GET" class="mx-auto mb-4" style="max-width:480px;">
                    <div class="position-relative">
                        <input type="text" name="q" class="form-control rounded-pill bg-dark border border-neutral-700 text-white px-4 py-3 font-size-14" 
                               placeholder="Search campaigns, listings, blog posts..." required>
                        <button type="submit" class="btn btn-main rounded-circle position-absolute top-50 translate-middle-y end-0 me-2" 
                                style="width:38px; height:38px; display:flex; align-items:center; justify-content:center; padding:0;">
                            <i class="ph ph-magnifying-glass"></i>
                        </button>
                    </div>
                </form>

                <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                    <a href="<?= url('') ?>" class="btn btn-main rounded-pill px-4 py-2 fw-bold font-size-14">
                        <i class="ph ph-house me-1"></i> Return Home
                    </a>
                    <a href="<?= url('marketplace') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-14">
                        <i class="ph ph-storefront me-1"></i> Browse Marketplace
                    </a>
                    <a href="<?= url('blog') ?>" class="btn btn-dark border border-neutral-700 rounded-pill px-4 py-2 font-size-14 text-white-50 hover-text-white">
                        <i class="ph ph-article me-1"></i> Growth Blog
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
