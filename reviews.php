<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Platform Reviews & Testimonials â€” ' . get_setting('site_name', 'Adsbooster.net');
$currentUserId = is_logged_in() ? current_user_id() : 0;
$currentUser   = is_logged_in() ? current_user() : null;

// Determine review eligibility
$canReview = false;
$eligibilityReason = '';
$daysSinceSignup = 0;

if ($currentUser) {
    $createdAt = strtotime($currentUser['created_at'] ?? 'now');
    $daysSinceSignup = (int)floor((time() - $createdAt) / 86400);

    // Check if user is on Pro Plan (any active paid plan qualifies)
    $isPro = in_array($currentUser['role'] ?? '', ['admin', 'pro', 'agency'], true)
        || (int)($currentUser['plan_id'] ?? 0) > 0;
    if (!$isPro) {
        // Check subscriptions table
        try {
            $activeSub = db()->fetchOne(
                "SELECT * FROM user_subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1",
                [$currentUserId]
            );
            if ($activeSub) {
                $isPro = true;
            }
        } catch (\Throwable $e) {}
    }

    if ($isPro) {
        $canReview = true;
    } elseif ($daysSinceSignup >= 5) {
        $canReview = true;
    } else {
        $daysRemaining = 5 - $daysSinceSignup;
        $eligibilityReason = "Free account members are invited to review after 5 days of platform usage ({$daysRemaining} day(s) remaining). Pro Plan members can review anytime.";
    }
}

// Check if user already submitted a review
$existingReview = null;
if ($currentUserId) {
    $existingReview = db()->fetchOne("SELECT * FROM site_reviews WHERE user_id = ?", [$currentUserId]);
}

// Check if user clicked skip from dashboard prompt
if (isset($_GET['skip']) && $_GET['skip'] === '1') {
    $_SESSION['review_prompt_dismissed'] = time() + (86400 * 7);
    setcookie('review_prompt_dismissed', (string)(time() + (86400 * 7)), time() + (86400 * 7), '/');
    redirect(url('dashboard'));
}

// Handle Submission
$errors = [];
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify() && is_logged_in()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_review' && $canReview) {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $title = trim($_POST['title'] ?? '');
        $reviewText = trim($_POST['review_text'] ?? '');
        $roleTitle = trim($_POST['user_role'] ?? 'Verified Advertiser');

        if (empty($title)) {
            $errors[] = 'Please provide a short headline for your review.';
        }
        if (empty($reviewText) || strlen($reviewText) < 20) {
            $errors[] = 'Review must be at least 20 characters in length.';
        }

        if (empty($errors)) {
            $planType = $isPro ? 'pro' : 'free';
            if ($existingReview) {
                db()->update('site_reviews', [
                    'rating'       => $rating,
                    'review_title' => $title,
                    'review_text'  => $reviewText,
                    'user_role'    => $roleTitle,
                    'status'       => 'approved',
                ], 'id = ?', [$existingReview['id']]);
                $success = 'Your review has been updated and published!';
            } else {
                db()->insert('site_reviews', [
                    'user_id'      => $currentUserId,
                    'rating'       => $rating,
                    'review_title' => $title,
                    'review_text'  => $reviewText,
                    'plan_type'    => $planType,
                    'user_role'    => $roleTitle,
                    'status'       => 'approved',
                    'is_featured'  => 1,
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
                $success = 'Thank you for your review! It has been posted to our community showcase.';
            }
            $existingReview = db()->fetchOne("SELECT * FROM site_reviews WHERE user_id = ?", [$currentUserId]);
        }
    } elseif ($action === 'skip_review') {
        $_SESSION['review_prompt_dismissed'] = time() + (86400 * 7); // dismiss for 7 days
        redirect(url('dashboard'));

    }
}

// Fetch all approved reviews for showcase
$approvedReviews = db()->fetchAll(
    "SELECT r.*, u.full_name, u.avatar, u.company 
     FROM site_reviews r 
     JOIN users u ON u.id = r.user_id 
     WHERE r.status = 'approved' 
     ORDER BY r.is_featured DESC, r.created_at DESC"
);

$avgRating = count($approvedReviews) > 0 
    ? round(array_sum(array_column($approvedReviews, 'rating')) / count($approvedReviews), 1)
    : 5.0;

require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 140px !important; padding-bottom: 90px; min-height: 85vh; background: #080c14;">
    <div class="container max-w-1200-px">

        <!-- Top Header Banner -->
        <div class="text-center max-w-700-px mx-auto mb-5">
            <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-30 rounded-pill px-3 py-1 font-size-12 fw-bold text-uppercase mb-2 d-inline-block">
                Community Ratings &amp; Trust
            </span>
            <h1 class="h2 text-white fw-bold mb-3">Client Reviews &amp; Success Stories</h1>
            <p class="text-muted font-size-15">
                Real feedback from brand owners, performance media buyers, and growth agencies scaling on Adsbooster.net.
            </p>
            <div class="d-inline-flex align-items-center gap-2 bg-card border border-secondary border-opacity-15 px-4 py-2 rounded-pill mt-2" style="background:#0d1524;">
                <div class="text-warning font-size-16">
                    <i class="ph-fill ph-star"></i>
                    <i class="ph-fill ph-star"></i>
                    <i class="ph-fill ph-star"></i>
                    <i class="ph-fill ph-star"></i>
                    <i class="ph-fill ph-star"></i>
                </div>
                <span class="text-white fw-bold font-size-15"><?= $avgRating ?> / 5.0</span>
                <span class="text-white-50 font-size-13">(<?= count($approvedReviews) ?> Verified Reviews)</span>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-4 p-3 mb-4 text-center">
                <i class="ph ph-check-circle me-1"></i> <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger rounded-4 p-3 mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Write / Edit Review Modal or Card -->
        <?php if (is_logged_in()): ?>
            <div class="card bg-card border border-secondary border-opacity-15 rounded-4 p-4 p-md-5 mb-5 shadow-sm" style="background: #0d1524;">
                <?php if ($canReview): ?>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                        <div>
                            <h3 class="h4 text-white fw-bold mb-1">
                                <?= $existingReview ? 'Update Your Platform Review' : 'Share Your Experience' ?>
                            </h3>
                            <p class="text-muted mb-0 font-size-14">Your review helps independent advertisers and merchants choose the best growth tools.</p>
                        </div>
                        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 font-size-12 fw-bold">
                            <i class="ph ph-shield-check me-1"></i> Review Verified Eligible
                        </span>
                    </div>

                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="submit_review">

                        <!-- Rating Selector -->
                        <div class="mb-3">
                            <label class="form-label text-white fw-semibold">Overall Rating</label>
                            <div class="d-flex gap-2">
                                <?php for ($s = 5; $s >= 1; $s--): ?>
                                    <div class="form-check form-check-inline m-0">
                                        <input class="btn-check" type="radio" name="rating" id="star_<?= $s ?>" value="<?= $s ?>" <?= ($existingReview['rating'] ?? 5) == $s ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-warning rounded-pill px-3 py-1 font-size-13 d-flex align-items-center gap-1" for="star_<?= $s ?>">
                                            <?= $s ?> <i class="ph-fill ph-star"></i>
                                        </label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Title -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label text-white fw-semibold">Headline / Summary <span class="text-danger">*</span></label>
                                <input type="text" name="title" required class="form-control bg-dark border-secondary border-opacity-25 text-white" placeholder="e.g. Scaled our TikTok ad creative testing 3x faster" value="<?= htmlspecialchars($existingReview['review_title'] ?? $existingReview['title'] ?? '') ?>">

                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white fw-semibold">Your Professional Role / Industry</label>
                                <input type="text" name="user_role" class="form-control bg-dark border-secondary border-opacity-25 text-white" placeholder="e.g. D2C Founder, Media Buyer" value="<?= htmlspecialchars($existingReview['user_role'] ?? 'Growth Marketer') ?>">
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="mb-4">
                            <label class="form-label text-white fw-semibold">Detailed Review <span class="text-danger">*</span></label>
                            <textarea name="review_text" rows="4" required class="form-control bg-dark border-secondary border-opacity-25 text-white" placeholder="What specific features (AI UGC Studio, Multi-Network Ads, Verified Directory) have helped your business most?"><?= htmlspecialchars($existingReview['review_text'] ?? '') ?></textarea>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" class="btn btn-main-600 text-dark fw-bold rounded-pill px-4 py-2">
                                <i class="ph ph-check me-1"></i> <?= $existingReview ? 'Update Review' : 'Submit Review' ?>
                            </button>
                            <?php if (!$existingReview): ?>
                                <button type="submit" name="action" value="skip_review" class="btn btn-outline-light rounded-pill px-3 py-2 font-size-13">
                                    Skip for Now
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Locked / Waiting Message -->
                    <div class="text-center py-4">
                        <i class="ph ph-hourglass-high text-main-600 mb-2" style="font-size: 36px;"></i>
                        <h4 class="text-white fw-bold mb-2">Review Eligibility in Progress</h4>
                        <p class="text-muted font-size-14 max-w-600-px mx-auto mb-3">
                            <?= e($eligibilityReason) ?>
                        </p>
                        <a href="<?= url('pricing-plan') ?>" class="btn btn-outline-warning rounded-pill px-4 py-2 font-size-13">
                            <i class="ph ph-lightning me-1"></i> Upgrade to Pro Plan for Instant Review Access
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Public Reviews Grid -->
        <div class="row g-4">
            <?php foreach ($approvedReviews as $rev): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-card border border-secondary border-opacity-15 rounded-4 p-4 h-100 d-flex flex-column justify-content-between" style="background:#0d1524;">
                        <div>
                            <!-- Star Rating -->
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="text-warning font-size-14">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?= $i <= (int)$rev['rating'] ? 'ph-fill ph-star' : 'ph ph-star text-white-50' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill px-2 py-0 font-size-11">
                                    <?= ucfirst(e($rev['plan_type'])) ?> Member
                                </span>
                            </div>
                            <?php if (!empty($rev['is_featured'])): ?>
                                <div style="display:inline-flex;align-items:center;gap:5px;background:rgba(234,179,8,.12);border:1px solid rgba(234,179,8,.3);color:#fbbf24;border-radius:999px;padding:2px 10px;font-size:11px;font-weight:700;margin-bottom:10px;">
                                    <i class="ph-fill ph-medal"></i> Featured Story
                                </div>
                            <?php endif; ?>
                            <!-- Review Title -->
                            <h4 class="text-white fw-bold font-size-16 mb-2">
                                <?= e($rev['review_title'] ?? $rev['title'] ?? 'Verified Review') ?>
                            </h4>


                            <!-- Review Body -->
                            <p class="text-muted font-size-14 mb-4" style="line-height: 1.6;">
                                "<?= e($rev['review_text']) ?>"
                            </p>
                        </div>

                        <!-- Reviewer Info -->
                        <div class="pt-3 border-top border-secondary border-opacity-10 d-flex align-items-center gap-3">
                            <div class="rounded-circle overflow-hidden bg-main-600 bg-opacity-20 d-flex align-items-center justify-content-center text-main-600 fw-bold font-size-14" style="width: 42px; height: 42px;">
                                <?php if (!empty($rev['avatar'])): ?>
                                    <img src="<?= e($rev['avatar']) ?>" alt="Avatar" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <?= strtoupper(substr($rev['full_name'], 0, 2)) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="text-white fw-bold font-size-14"><?= e($rev['full_name']) ?></div>
                                <div class="text-white-50 font-size-12"><?= e($rev['user_role']) ?></div>
                                <?php if (!empty($rev['created_at'])): ?>
                                    <div class="text-white-50 font-size-11" style="opacity:.75;"><?= date('M d, Y', strtotime($rev['created_at'])) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
