<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Platform Reviews & Testimonials — ' . get_setting('site_name', 'Adsbooster.net');
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

$reviewCount = count($approvedReviews);
$avgRating   = $reviewCount > 0
    ? round(array_sum(array_column($approvedReviews, 'rating')) / $reviewCount, 1)
    : 5.0;

// Rating distribution (5 -> 1)
$distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($approvedReviews as $rev) {
    $r = (int)$rev['rating'];
    if (isset($distribution[$r])) {
        $distribution[$r]++;
    }
}

// Featured first, then the rest
$featuredReviews = array_values(array_filter($approvedReviews, static fn($r) => !empty($r['is_featured'])));
$otherReviews    = array_values(array_filter($approvedReviews, static fn($r) => empty($r['is_featured'])));

require_once __DIR__ . '/includes/header.php';
?>

<section class="section-padding" style="padding-top:140px !important;padding-bottom:90px;min-height:85vh;background:#080c14;">
  <div class="container max-w-1200-px">

    <!-- ── Hero ──────────────────────────────────────────────────────── -->
    <div class="text-center max-w-700-px mx-auto mb-5">
      <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-30 rounded-pill px-3 py-1 font-size-12 fw-bold text-uppercase mb-2 d-inline-block">
        Community Ratings & Trust
      </span>
      <h1 class="h2 text-white fw-bold mb-3">Client Reviews & Success Stories</h1>
      <p class="text-muted font-size-15">
        Real feedback from brand owners, performance media buyers and growth agencies scaling on Adsbooster.net.
      </p>
    </div>

    <!-- ── Rating summary ────────────────────────────────────────────── -->
    <div class="card-dark mb-5" style="padding:26px 28px;">
      <div class="row align-items-center g-4">
        <div class="col-lg-4 text-center">
          <div style="font-size:56px;font-weight:800;color:var(--fg);line-height:1;letter-spacing:-.03em;">
            <?= number_format($avgRating, 1) ?>
            <span style="font-size:20px;color:var(--fg3);font-weight:600;">/ 5</span>
          </div>
          <div class="d-flex justify-content-center gap-1 my-2" style="font-size:18px;color:#fbbf24;">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="<?= $i <= (int)round($avgRating) ? 'ph-fill ph-star' : 'ph ph-star' ?>" style="<?= $i <= (int)round($avgRating) ? '' : 'opacity:.3;' ?>"></i>
            <?php endfor; ?>
          </div>
          <p class="text-muted mb-0" style="font-size:13px;">
            <?= $reviewCount ?> verified review<?= $reviewCount === 1 ? '' : 's' ?>
          </p>
        </div>

        <div class="col-lg-8">
          <?php for ($s = 5; $s >= 1; $s--):
              $count = $distribution[$s] ?? 0;
              $pct   = $reviewCount > 0 ? round(($count / $reviewCount) * 100) : 0;
          ?>
            <div class="d-flex align-items-center gap-3 mb-2">
              <span class="text-muted" style="font-size:12px;min-width:52px;"><?= $s ?> <i class="ph-fill ph-star" style="color:#fbbf24;"></i></span>
              <div style="flex:1;height:8px;border-radius:999px;background:rgba(255,255,255,.06);overflow:hidden;">
                <div style="width:<?= $pct ?>%;height:100%;border-radius:999px;background:linear-gradient(90deg,#fbbf24,#f59e0b);"></div>
              </div>
              <span class="text-muted" style="font-size:11px;min-width:58px;text-align:right;">
                <?= $count ?> · <?= $pct ?>%
              </span>
            </div>
          <?php endfor; ?>
        </div>
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

    <!-- ── Write / edit review ───────────────────────────────────────── -->
    <?php if (is_logged_in()): ?>
      <div class="card-dark mb-5" style="padding:26px 28px;">
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

          <form method="POST" id="reviewForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_review">

            <!-- Rating selector (interactive stars) -->
            <div class="mb-4">
              <label class="form-label text-white fw-semibold d-block mb-2">Overall Rating</label>
              <div class="d-flex align-items-center gap-2 flex-wrap" id="starPicker">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                  <button type="button" class="star-btn" data-rating="<?= $s ?>"
                          aria-label="<?= $s ?> star<?= $s === 1 ? '' : 's' ?>">
                    <i class="ph ph-star"></i>
                  </button>
                <?php endfor; ?>
                <input type="hidden" name="rating" id="ratingInput" value="<?= (int)($existingReview['rating'] ?? 5) ?>">
                <span class="text-muted ms-2" id="ratingLabel" style="font-size:13px;font-weight:600;">
                  <?= (int)($existingReview['rating'] ?? 5) ?> / 5
                </span>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-8">
                <label class="form-label text-white fw-semibold" for="reviewTitle">Headline / Summary <span class="text-danger">*</span></label>
                <input type="text" name="title" id="reviewTitle" required class="form-control"
                       placeholder="e.g. Scaled our TikTok ad creative testing 3x faster"
                       value="<?= htmlspecialchars($existingReview['review_title'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label text-white fw-semibold" for="reviewRole">Your Professional Role / Industry</label>
                <input type="text" name="user_role" id="reviewRole" class="form-control"
                       placeholder="e.g. D2C Founder, Media Buyer"
                       value="<?= htmlspecialchars($existingReview['user_role'] ?? 'Growth Marketer') ?>">
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label text-white fw-semibold" for="reviewBody">Detailed Review <span class="text-danger">*</span></label>
              <textarea name="review_text" id="reviewBody" rows="4" required class="form-control"
                        placeholder="What specific features (AI UGC Studio, Multi-Network Ads, Verified Directory) have helped your business most?"><?= htmlspecialchars($existingReview['review_text'] ?? '') ?></textarea>
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap">
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
          <div class="text-center py-3">
            <i class="ph ph-hourglass-high text-main-600 mb-2" style="font-size:36px;"></i>
            <h4 class="text-white fw-bold mb-2">Review Eligibility in Progress</h4>
            <p class="text-muted font-size-14 max-w-600-px mx-auto mb-3"><?= e($eligibilityReason) ?></p>
            <a href="<?= url('pricing-plan') ?>" class="btn btn-outline-warning rounded-pill px-4 py-2 font-size-13">
              <i class="ph ph-lightning me-1"></i> Upgrade to a paid plan for instant review access
            </a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- ── Showcase ──────────────────────────────────────────────────── -->
    <?php if (empty($approvedReviews)): ?>
      <div class="card-dark text-center" style="padding:56px 24px;">
        <div class="stat-icon mx-auto mb-3" style="background:rgba(200,255,0,.08);width:56px;height:56px;">
          <i class="ph ph-chat-circle-dots" style="font-size:26px;color:var(--accent);"></i>
        </div>
        <h3 class="text-white fw-bold mb-2" style="font-size:1.15rem;">No reviews yet</h3>
        <p class="text-secondary mb-4" style="font-size:13px;">
          Be the first to tell other advertisers what you think of Adsbooster.net.
        </p>
        <?php if (is_logged_in() && $canReview): ?>
          <a href="#reviewForm" class="btn btn-main btn-sm"><i class="ph ph-star me-1"></i> Write the first review</a>
        <?php else: ?>
          <a href="<?= url('login') ?>" class="btn btn-main btn-sm">Log in to review</a>
        <?php endif; ?>
      </div>
    <?php else: ?>

      <?php if (!empty($featuredReviews)): ?>
        <div class="d-flex align-items-center gap-2 mb-3">
          <h4 class="text-white fw-bold mb-0" style="font-size:1.05rem;">
            <i class="ph-fill ph-medal" style="color:#fbbf24;"></i> Featured Stories
          </h4>
          <span class="text-muted" style="font-size:12px;"><?= count($featuredReviews) ?> highlighted</span>
        </div>

        <div class="row g-4 mb-5">
          <?php foreach ($featuredReviews as $rev): ?>
            <div class="col-md-6 col-lg-4">
              <div class="card-dark h-100 d-flex flex-column" style="padding:24px;border-color:rgba(251,191,36,.28);">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div style="font-size:15px;color:#fbbf24;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="<?= $i <= (int)$rev['rating'] ? 'ph-fill ph-star' : 'ph ph-star' ?>" style="<?= $i <= (int)$rev['rating'] ? '' : 'opacity:.25;' ?>"></i>
                    <?php endfor; ?>
                  </div>
                  <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill px-2 py-0 font-size-11">
                    <?= e(ucfirst((string)$rev['plan_type'])) ?> Member
                  </span>
                </div>

                <div class="d-inline-flex align-items-center gap-1 mb-2" style="background:rgba(234,179,8,.12);border:1px solid rgba(234,179,8,.3);color:#fbbf24;border-radius:999px;padding:2px 10px;font-size:11px;font-weight:700;align-self:flex-start;">
                  <i class="ph-fill ph-medal"></i> Featured Story
                </div>

                <h5 class="text-white fw-bold mb-2" style="font-size:15.5px;line-height:1.4;">
                  <?= e($rev['review_title'] ?? 'Verified Review') ?>
                </h5>
                <p class="text-muted mb-4" style="font-size:13.5px;line-height:1.65;">
                  &ldquo;<?= e($rev['review_text']) ?>&rdquo;
                </p>

                <div class="pt-3 border-top border-secondary border-opacity-10 d-flex align-items-center gap-3 mt-auto">
                  <div class="rounded-circle overflow-hidden bg-main-600 bg-opacity-20 d-flex align-items-center justify-content-center text-main-600 fw-bold font-size-14"
                       style="width:42px;height:42px;flex-shrink:0;">
                    <?php if (!empty($rev['avatar'])): ?>
                      <img src="<?= e($rev['avatar']) ?>" alt="" class="w-100 h-100 object-fit-cover">
                    <?php else: ?>
                      <?= strtoupper(substr((string)$rev['full_name'], 0, 2)) ?>
                    <?php endif; ?>
                  </div>
                  <div style="min-width:0;">
                    <div class="text-white fw-bold font-size-14"><?= e($rev['full_name']) ?></div>
                    <div class="text-white-50 font-size-12"><?= e((string)$rev['user_role']) ?></div>
                    <?php if (!empty($rev['created_at'])): ?>
                      <div class="text-white-50 font-size-11">
                        <?= date('M d, Y', strtotime((string)$rev['created_at'])) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($otherReviews)): ?>
        <div class="d-flex align-items-center gap-2 mb-3">
          <h4 class="text-white fw-bold mb-0" style="font-size:1.05rem;">All Reviews</h4>
          <span class="text-muted" style="font-size:12px;"><?= count($otherReviews) ?> more</span>
        </div>

        <div class="row g-4">
          <?php foreach ($otherReviews as $rev): ?>
            <div class="col-md-6 col-lg-4">
              <div class="card-dark h-100 d-flex flex-column" style="padding:22px;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div style="font-size:14px;color:#fbbf24;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="<?= $i <= (int)$rev['rating'] ? 'ph-fill ph-star' : 'ph ph-star' ?>" style="<?= $i <= (int)$rev['rating'] ? '' : 'opacity:.25;' ?>"></i>
                    <?php endfor; ?>
                  </div>
                  <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-25 rounded-pill px-2 py-0 font-size-11">
                    <?= e(ucfirst((string)$rev['plan_type'])) ?> Member
                  </span>
                </div>

                <h5 class="text-white fw-bold mb-2" style="font-size:15px;line-height:1.4;">
                  <?= e($rev['review_title'] ?? 'Verified Review') ?>
                </h5>
                <p class="text-muted mb-4" style="font-size:13px;line-height:1.65;">
                  &ldquo;<?= e($rev['review_text']) ?>&rdquo;
                </p>

                <div class="pt-3 border-top border-secondary border-opacity-10 d-flex align-items-center gap-3 mt-auto">
                  <div class="rounded-circle overflow-hidden bg-main-600 bg-opacity-20 d-flex align-items-center justify-content-center text-main-600 fw-bold font-size-13"
                       style="width:38px;height:38px;flex-shrink:0;">
                    <?php if (!empty($rev['avatar'])): ?>
                      <img src="<?= e($rev['avatar']) ?>" alt="" class="w-100 h-100 object-fit-cover">
                    <?php else: ?>
                      <?= strtoupper(substr((string)$rev['full_name'], 0, 2)) ?>
                    <?php endif; ?>
                  </div>
                  <div style="min-width:0;">
                    <div class="text-white fw-bold font-size-13"><?= e($rev['full_name']) ?></div>
                    <div class="text-white-50 font-size-11"><?= e((string)$rev['user_role']) ?></div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</section>

<script>
(function () {
    var picker = document.getElementById('starPicker');
    if (!picker) return;

    var input  = document.getElementById('ratingInput');
    var label  = document.getElementById('ratingLabel');
    var btns   = picker.querySelectorAll('.star-btn');

    function paint(value) {
        btns.forEach(function (btn) {
            var n = parseInt(btn.dataset.rating, 10);
            var icon = btn.querySelector('i');
            if (n <= value) {
                icon.className = 'ph-fill ph-star';
                icon.style.color = '#fbbf24';
            } else {
                icon.className = 'ph ph-star';
                icon.style.color = '';
            }
        });
        if (label) label.textContent = value + ' / 5';
        if (input) input.value = value;
    }

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            paint(parseInt(btn.dataset.rating, 10));
        });
        btn.addEventListener('mouseenter', function () {
            paint(parseInt(btn.dataset.rating, 10));
        });
    });

    picker.addEventListener('mouseleave', function () {
        paint(parseInt(input ? input.value : '5', 10));
    });

    paint(parseInt(input ? input.value : '5', 10));
})();
</script>

<style>
#starPicker .star-btn {
    background: transparent;
    border: 0;
    padding: 2px;
    font-size: 26px;
    line-height: 1;
    color: var(--fg3);
    cursor: pointer;
    transition: transform .15s ease, color .15s ease;
}
#starPicker .star-btn:hover { transform: scale(1.12); }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
