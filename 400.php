<?php declare(strict_types=1);

http_response_code(400);
$pageTitle = '400 - Bad Request — Adsbooster.net';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding py-5 position-relative z-1" style="min-height:75vh; display:flex; align-items:center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4" 
                     style="width:100px; height:100px; background:rgba(244,63,94,0.1); border:2px solid rgba(244,63,94,0.3); color:#f43f5e; font-size:42px;">
                    <i class="ph-bold ph-warning-circle"></i>
                </div>
                <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-30 rounded-pill px-3 py-2 font-size-12 fw-bold text-uppercase mb-3 d-inline-block">
                    HTTP Error 400
                </span>
                <h1 class="text-white fw-bold display-4 mb-3">Bad Request</h1>
                <p class="text-white-50 font-size-16 mx-auto mb-4" style="max-width:540px; line-height:1.6;">
                    The server could not understand your request due to malformed syntax, invalid parameters, or corrupted request headers.
                </p>

                <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                    <a href="<?= url('') ?>" class="btn btn-main rounded-pill px-4 py-2 fw-bold font-size-14">
                        <i class="ph ph-house me-1"></i> Return Home
                    </a>
                    <a href="<?= url('dashboard') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-14">
                        <i class="ph ph-squares-four me-1"></i> Go to Dashboard
                    </a>
                    <a href="<?= url('status') ?>" class="btn btn-dark border border-neutral-700 rounded-pill px-4 py-2 font-size-14 text-white-50 hover-text-white">
                        <i class="ph ph-activity me-1"></i> System Status
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
