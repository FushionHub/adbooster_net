<?php declare(strict_types=1);

http_response_code(503);
$pageTitle = '503 - Service Unavailable — Adsbooster.net';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5 position-relative z-1" style="min-height:75vh; display:flex; align-items:center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4" 
                     style="width:100px; height:100px; background:rgba(234,179,8,0.1); border:2px solid rgba(234,179,8,0.3); color:#eab308; font-size:42px;">
                    <i class="ph-bold ph-wrench"></i>
                </div>
                <span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30 rounded-pill px-3 py-2 font-size-12 fw-bold text-uppercase mb-3 d-inline-block">
                    Maintenance Mode · HTTP 503
                </span>
                <h1 class="text-white fw-bold display-4 mb-3">Service Temporarily Unavailable</h1>
                <p class="text-white-50 font-size-16 mx-auto mb-4" style="max-width:540px; line-height:1.6;">
                    The Adsbooster engine is undergoing scheduled database indexing, infrastructure scaling, or cluster synchronization. Normal service will resume shortly.
                </p>

                <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                    <button onclick="window.location.reload()" class="btn btn-main rounded-pill px-4 py-2 fw-bold font-size-14">
                        <i class="ph ph-arrows-clockwise me-1"></i> Try Reloading
                    </button>
                    <a href="<?= url('status') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-14">
                        <i class="ph ph-activity me-1"></i> Live Status Page
                    </a>
                    <a href="<?= url('contact') ?>" class="btn btn-dark border border-neutral-700 rounded-pill px-4 py-2 font-size-14 text-white-50 hover-text-white">
                        <i class="ph ph-envelope me-1"></i> Support Desk
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
