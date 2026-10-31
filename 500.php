<?php declare(strict_types=1);

http_response_code(500);
$pageTitle = '500 - Internal Server Error — Adsbooster.net';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5 position-relative z-1" style="min-height:75vh; display:flex; align-items:center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4" 
                     style="width:100px; height:100px; background:rgba(239,68,68,0.1); border:2px solid rgba(239,68,68,0.3); color:#ef4444; font-size:42px;">
                    <i class="ph-bold ph-gear-fine"></i>
                </div>
                <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-30 rounded-pill px-3 py-2 font-size-12 fw-bold text-uppercase mb-3 d-inline-block">
                    Server Error 500
                </span>
                <h1 class="text-white fw-bold display-4 mb-3">Something Went Wrong</h1>
                <p class="text-white-50 font-size-16 mx-auto mb-4" style="max-width:540px; line-height:1.6;">
                    Our server encountered an internal condition and was unable to complete your request. Our automated telemetry has logged the issue and engineers are on it.
                </p>

                <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                    <a href="<?= url('') ?>" class="btn btn-main rounded-pill px-4 py-2 fw-bold font-size-14">
                        <i class="ph ph-house me-1"></i> Return Home
                    </a>
                    <a href="<?= url('status') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-14">
                        <i class="ph ph-activity me-1"></i> System Status
                    </a>
                    <a href="<?= url('tickets') ?>" class="btn btn-dark border border-neutral-700 rounded-pill px-4 py-2 font-size-14 text-white-50 hover-text-white">
                        <i class="ph ph-ticket me-1"></i> Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
