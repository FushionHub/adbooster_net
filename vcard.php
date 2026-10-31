<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$identifier = trim((string)($_GET['u'] ?? ''));
$userId     = (int)($_GET['id'] ?? 0);
$currentUserId = is_logged_in() ? current_user_id() : 0;

$portfolio = null;
$vcardRecord = null;
$userRecord  = null;

// 1. Resolve Portfolio & vCard Data
if ($identifier !== '') {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.username = ? OR u.email = ? LIMIT 1",
        [$identifier, $identifier]
    );
} elseif ($userId > 0) {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.user_id = ? LIMIT 1",
        [$userId]
    );
} elseif ($currentUserId > 0) {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.user_id = ? LIMIT 1",
        [$currentUserId]
    );
}

// 2. Fetch extended user_vcards table record
$targetUserId = (int)($portfolio['user_id'] ?? $userId ?: $currentUserId);
if ($targetUserId > 0) {
    $vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ? LIMIT 1", [$targetUserId]);
    $userRecord  = db()->fetchOne("SELECT * FROM users WHERE id = ? LIMIT 1", [$targetUserId]);
}

// 3. Fallback synthesis if no portfolio row exists
if (!$portfolio && $userRecord) {
    $portfolio = [
        'user_id'        => $userRecord['id'],
        'username'       => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $userRecord['email'])[0]))),
        'display_name'   => $userRecord['full_name'] ?: 'Verified Creator',
        'job_title'      => 'Performance Marketer & Growth Creator',
        'company'        => $userRecord['company'] ?? 'Adsbooster.net Partner Network',
        'email'          => $userRecord['email'],
        'phone'          => $userRecord['phone'] ?? '+1 (555) 019-2831',
        'location'       => 'Worldwide / Remote',
        'headline'       => $userRecord['headline'] ?? 'Scaling omnichannel ad campaigns with creative testing.',
        'bio'            => $userRecord['bio'] ?? 'Verified digital advertiser and creator on Adsbooster.net',
        'template_style' => 'dark_luxury',
        'website'        => BASE_URL . '/portfolio.php?u=' . urlencode($userRecord['email']),
        'avatar_url'     => $userRecord['avatar'] ?? '',
        'views_count'    => 1,
    ];
}

if (!$portfolio) {
    http_response_code(404);
    die('Digital vCard not found. Please verify the URL or ensure the user profile is active.');
}

// Increment views
if (!empty($vcardRecord['id'])) {
    try {
        db()->query("UPDATE user_vcards SET views_count = views_count + 1 WHERE id = ?", [$vcardRecord['id']]);
    } catch (\Throwable $e) {}
}

$name      = trim((string)($portfolio['display_name'] ?? $portfolio['user_full_name'] ?? $userRecord['full_name'] ?? 'Advertiser'));
$jobTitle  = trim((string)($portfolio['job_title'] ?? $vcardRecord['job_title'] ?? 'Growth Marketer & Media Buyer'));
$headline  = trim((string)($portfolio['headline'] ?? $vcardRecord['headline'] ?? ''));
$company   = trim((string)($portfolio['company'] ?? $vcardRecord['company'] ?? $userRecord['company'] ?? 'Adsbooster.net'));
$email     = trim((string)($portfolio['email'] ?? $portfolio['user_email'] ?? $userRecord['email'] ?? ''));
$phone     = trim((string)($portfolio['phone'] ?? $portfolio['user_phone'] ?? $vcardRecord['phone'] ?? $userRecord['phone'] ?? ''));
$whatsapp  = trim((string)($vcardRecord['whatsapp'] ?? $phone));
$location  = trim((string)($portfolio['location'] ?? $vcardRecord['location'] ?? 'Worldwide / Remote'));
$avatarUrl = trim((string)($portfolio['avatar_url'] ?? $portfolio['user_avatar'] ?? $userRecord['avatar'] ?? ''));
$username  = trim((string)($portfolio['username'] ?? $vcardRecord['username'] ?? (string)$portfolio['user_id']));
$template  = trim((string)($portfolio['template_style'] ?? $vcardRecord['template'] ?? 'dark_luxury'));

$vcfUrl      = BASE_URL . '/vcard-download.php?u=' . urlencode($username);
$cardUrl     = BASE_URL . '/vcard.php?u=' . urlencode($username);
$portfolioUrl = BASE_URL . '/portfolio.php?u=' . urlencode($username);
$qrCodeUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=12&data=' . rawurlencode($vcfUrl);

// Social profiles
$socials = [];
if (!empty($portfolio['social_links_json'])) {
    $socials = json_decode((string)$portfolio['social_links_json'], true) ?: [];
}
if (!empty($vcardRecord['linkedin']))  $socials['linkedin']  = $vcardRecord['linkedin'];
if (!empty($vcardRecord['twitter']))   $socials['twitter']   = $vcardRecord['twitter'];
if (!empty($vcardRecord['github']))    $socials['github']    = $vcardRecord['github'];
if (!empty($vcardRecord['instagram'])) $socials['instagram'] = $vcardRecord['instagram'];

$cleanPhoneNum = preg_replace('/[^0-9]/', '', $whatsapp ?: $phone);
$isOwner = $currentUserId > 0 && (int)$portfolio['user_id'] === $currentUserId;

$pageTitle = e($name) . ' — Interactive Digital NFC vCard — ' . get_setting('site_name', 'Adsbooster.net');
$pageDescription = e($jobTitle . ' | ' . $company . ' | ' . $headline);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Theme Customization Variables -->
<style>
:root {
  --nfc-bg: #080c14;
  --nfc-card-bg: linear-gradient(135deg, rgba(13, 21, 36, 0.95) 0%, rgba(8, 12, 20, 0.98) 100%);
  --nfc-border: rgba(200, 255, 0, 0.35);
  --nfc-accent: #c8ff00;
  --nfc-glow: rgba(200, 255, 0, 0.15);
  --nfc-text: #ffffff;
}

<?php if ($template === 'creative_neon'): ?>
:root {
  --nfc-bg: #090314;
  --nfc-card-bg: linear-gradient(135deg, rgba(26, 16, 48, 0.95) 0%, rgba(12, 6, 26, 0.98) 100%);
  --nfc-border: rgba(168, 85, 247, 0.45);
  --nfc-accent: #a855f7;
  --nfc-glow: rgba(168, 85, 247, 0.25);
}
<?php elseif ($template === 'executive_minimal'): ?>
:root {
  --nfc-bg: #0a0f18;
  --nfc-card-bg: linear-gradient(135deg, rgba(19, 25, 36, 0.95) 0%, rgba(11, 17, 28, 0.98) 100%);
  --nfc-border: rgba(56, 189, 248, 0.4);
  --nfc-accent: #38bdf8;
  --nfc-glow: rgba(56, 189, 248, 0.2);
}
<?php elseif ($template === 'minimalist_aurora'): ?>
:root {
  --nfc-bg: #071512;
  --nfc-card-bg: linear-gradient(135deg, rgba(16, 36, 30, 0.95) 0%, rgba(8, 20, 16, 0.98) 100%);
  --nfc-border: rgba(16, 185, 129, 0.45);
  --nfc-accent: #10b981;
  --nfc-glow: rgba(16, 185, 129, 0.2);
}
<?php endif; ?>

.vcard-page-wrap {
  min-height: 100vh;
  padding-top: 130px;
  padding-bottom: 80px;
  background: var(--nfc-bg);
  position: relative;
  overflow: hidden;
}

/* Ambient Radial Glows */
.vcard-page-wrap::before {
  content: '';
  position: absolute;
  top: 15%;
  left: 50%;
  transform: translateX(-50%);
  width: 650px;
  height: 650px;
  background: radial-gradient(circle, var(--nfc-glow) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

/* 3D Smart Card Container */
.smart-card-viewport {
  perspective: 1400px;
  width: 100%;
  max-width: 480px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}

.smart-card {
  width: 100%;
  min-height: 300px;
  border-radius: 24px;
  position: relative;
  transform-style: preserve-3d;
  transition: transform 0.75s cubic-bezier(0.2, 0.8, 0.2, 1);
  cursor: pointer;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 30px var(--nfc-glow);
}

.smart-card.is-flipped {
  transform: rotateY(180deg);
}

.card-face {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 24px;
  background: var(--nfc-card-bg);
  border: 1.5px solid var(--nfc-border);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  padding: 30px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
}

/* Holographic Metallic Sheen Effect */
.card-face::after {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: linear-gradient(
    45deg,
    transparent 35%,
    rgba(255, 255, 255, 0.08) 45%,
    rgba(255, 255, 255, 0.18) 50%,
    rgba(255, 255, 255, 0.08) 55%,
    transparent 65%
  );
  transform: rotate(35deg);
  pointer-events: none;
  transition: transform 0.6s ease;
}

.smart-card:hover .card-face::after {
  transform: rotate(35deg) translate(20%, 20%);
}

.card-face-back {
  transform: rotateY(180deg);
  text-align: center;
  justify-content: center;
  align-items: center;
  gap: 16px;
}

/* EMV Contact Chip Graphic */
.emv-chip {
  width: 44px;
  height: 32px;
  background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #aa820a 100%);
  border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.4);
  position: relative;
  overflow: hidden;
  box-shadow: inset 0 1px 2px rgba(255,255,255,0.5), 0 2px 4px rgba(0,0,0,0.4);
}
.emv-chip::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 0;
  width: 100%;
  height: 1px;
  background: rgba(0,0,0,0.3);
}
.emv-chip::after {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  width: 1px;
  height: 100%;
  background: rgba(0,0,0,0.3);
}

/* NFC Wave Icon */
.nfc-wave {
  color: var(--nfc-accent);
  font-size: 24px;
  opacity: 0.9;
}

.card-action-btn {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #fff;
  transition: all 0.25s ease;
}
.card-action-btn:hover {
  background: var(--nfc-accent);
  color: #080c14 !important;
  border-color: var(--nfc-accent);
  transform: translateY(-2px);
}
</style>

<div class="vcard-page-wrap">
  <div class="container max-w-900-px">

    <!-- Top Toolbar -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
      <a href="<?= url('portfolio') ?>?u=<?= urlencode($username) ?>" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13 d-flex align-items-center gap-2">
        <i class="ph ph-arrow-left"></i> View Public Portfolio
      </a>
      <div class="d-flex align-items-center gap-2">
        <?php if ($isOwner): ?>
          <a href="<?= url('portfolio-builder') ?>" class="btn btn-sm btn-main-600 rounded-pill px-3 py-2 font-size-13 text-dark fw-bold">
            <i class="ph ph-sliders-horizontal me-1"></i> Edit Card
          </a>
        <?php endif; ?>
        <button type="button" onclick="shareVCard()" class="btn btn-outline-light rounded-pill px-3 py-2 font-size-13 d-flex align-items-center gap-1">
          <i class="ph ph-share-network"></i> Share
        </button>
      </div>
    </div>

    <!-- Smart Card Container with 3D Interaction -->
    <div class="smart-card-viewport my-4">
      <div class="smart-card" id="smartNfcCard" onclick="toggleCardFlip(event)" title="Click or tap to flip card">

        <!-- FRONT OF SMART CARD -->
        <div class="card-face card-face-front">
          <!-- Top Row: Chip, Contactless Wave, and Pro Badge -->
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <div class="emv-chip" title="Smart NFC Business Card"></div>
              <div class="nfc-wave" title="Contactless NFC Enabled">
                <i class="ph-bold ph-wifi-high"></i>
              </div>
            </div>
            <span class="badge rounded-pill px-3 py-1 font-size-11 fw-bold" style="background: rgba(200,255,0,0.12); color: var(--nfc-accent); border: 1px solid var(--nfc-border);">
              <i class="ph-fill ph-seal-check me-1"></i> VERIFIED NFC
            </span>
          </div>

          <!-- Middle Row: Avatar & Identity -->
          <div class="my-4 d-flex align-items-center gap-3">
            <div class="rounded-circle overflow-hidden flex-shrink-0" style="width: 72px; height: 72px; border: 2.5px solid var(--nfc-accent); box-shadow: 0 0 16px var(--nfc-glow); background: rgba(0,0,0,0.4);">
              <?php if (!empty($avatarUrl)): ?>
                <img src="<?= e($avatarUrl) ?>" alt="<?= e($name) ?>" class="w-100 h-100 object-fit-cover">
              <?php else: ?>
                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold font-size-22" style="background: rgba(200,255,0,0.15); color: var(--nfc-accent);">
                  <?= strtoupper(substr($name, 0, 2)) ?>
                </div>
              <?php endif; ?>
            </div>
            <div>
              <h2 class="h4 text-white fw-bold mb-1" style="letter-spacing: -0.02em;"><?= e($name) ?></h2>
              <div class="font-size-13 fw-semibold" style="color: var(--nfc-accent);"><?= e($jobTitle) ?></div>
              <?php if (!empty($company)): ?>
                <div class="text-white-50 font-size-12 mt-1"><i class="ph ph-buildings me-1"></i> <?= e($company) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Bottom Row: Location & Flip Hint -->
          <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary border-opacity-20 font-size-12 text-white-50">
            <div>
              <i class="ph ph-map-pin me-1 text-white-75"></i> <?= e($location) ?>
            </div>
            <div class="d-flex align-items-center gap-1 font-size-11 fw-semibold text-white-75">
              <span>Tap to Flip for QR</span>
              <i class="ph-bold ph-arrows-clockwise" style="color: var(--nfc-accent);"></i>
            </div>
          </div>
        </div>

        <!-- BACK OF SMART CARD (Live QR & Direct Actions) -->
        <div class="card-face card-face-back">
          <div class="font-size-12 text-white-50 mb-1 fw-semibold text-uppercase tracking-wider">
            Scan to Add Contact (.vcf)
          </div>

          <!-- Live QR Code -->
          <div class="bg-white p-2 rounded-4 shadow d-inline-block position-relative" style="width: 148px; height: 148px;">
            <img src="<?= e($qrCodeUrl) ?>" alt="vCard QR Code" class="w-100 h-100 object-fit-contain" id="qrImgBack" onerror="renderJsQr('qrCanvasBack', '<?= e($vcfUrl) ?>')">
            <div id="qrCanvasBack" style="display:none; width: 132px; height: 132px;"></div>
          </div>

          <div class="font-size-12 text-white-75">
            Point camera at screen to save contact
          </div>

          <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 font-size-11" onclick="event.stopPropagation(); toggleCardFlip();">
            <i class="ph ph-arrow-u-up-left me-1"></i> Flip Back
          </button>
        </div>

      </div>
    </div>

    <!-- Quick Action Contact Grid -->
    <div class="max-w-480-px mx-auto mt-4">
      <div class="row g-2 mb-3">
        <!-- 1-Click VCF Download -->
        <div class="col-6">
          <a href="<?= e($vcfUrl) ?>" class="btn card-action-btn w-100 py-3 rounded-4 font-size-13 fw-bold d-flex align-items-center justify-content-center gap-2">
            <i class="ph-bold ph-address-book font-size-18" style="color: var(--nfc-accent);"></i> Save to Contacts
          </a>
        </div>

        <!-- WhatsApp Direct -->
        <div class="col-6">
          <?php if (!empty($cleanPhoneNum)): ?>
            <a href="https://wa.me/<?= e($cleanPhoneNum) ?>?text=<?= urlencode("Hello {$name}, I connected with you via your Adsbooster Digital vCard!") ?>" target="_blank" class="btn card-action-btn w-100 py-3 rounded-4 font-size-13 fw-bold d-flex align-items-center justify-content-center gap-2">
              <i class="ph-bold ph-whatsapp-logo font-size-18 text-success"></i> WhatsApp Chat
            </a>
          <?php else: ?>
            <button disabled class="btn card-action-btn w-100 py-3 rounded-4 font-size-13 fw-bold opacity-50 d-flex align-items-center justify-content-center gap-2">
              <i class="ph-bold ph-whatsapp-logo font-size-18"></i> WhatsApp
            </button>
          <?php endif; ?>
        </div>

        <!-- Direct Phone Call -->
        <div class="col-6">
          <?php if (!empty($phone)): ?>
            <a href="tel:<?= e($phone) ?>" class="btn card-action-btn w-100 py-3 rounded-4 font-size-13 fw-bold d-flex align-items-center justify-content-center gap-2">
              <i class="ph-bold ph-phone-call font-size-18 text-info"></i> Direct Call
            </a>
          <?php else: ?>
            <button disabled class="btn card-action-btn w-100 py-3 rounded-4 font-size-13 fw-bold opacity-50 d-flex align-items-center justify-content-center gap-2">
              <i class="ph-bold ph-phone font-size-18"></i> Call
            </button>
          <?php endif; ?>
        </div>

        <!-- Direct Email -->
        <div class="col-6">
          <?php if (!empty($email)): ?>
            <a href="mailto:<?= e($email) ?>?subject=<?= urlencode("Collaboration Inquiry via Adsbooster") ?>" class="btn card-action-btn w-100 py-3 rounded-4 font-size-13 fw-bold d-flex align-items-center justify-content-center gap-2">
              <i class="ph-bold ph-envelope-simple font-size-18 text-warning"></i> Send Email
            </a>
          <?php else: ?>
            <button disabled class="btn card-action-btn w-100 py-3 rounded-4 font-size-13 fw-bold opacity-50 d-flex align-items-center justify-content-center gap-2">
              <i class="ph-bold ph-envelope font-size-18"></i> Email
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Social & Extended Links -->
      <?php if (!empty($socials)): ?>
        <div class="card-dark rounded-4 p-3 mb-3 border border-secondary border-opacity-15 text-center" style="background:#0d1524;">
          <div class="font-size-12 text-white-50 mb-2">Connect on Social Networks</div>
          <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
            <?php if (!empty($socials['linkedin'])): ?>
              <a href="<?= e($socials['linkedin']) ?>" target="_blank" class="btn btn-outline-light rounded-circle p-2" title="LinkedIn" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                <i class="ph-bold ph-linkedin-logo font-size-18"></i>
              </a>
            <?php endif; ?>
            <?php if (!empty($socials['twitter'])): ?>
              <a href="<?= e($socials['twitter']) ?>" target="_blank" class="btn btn-outline-light rounded-circle p-2" title="Twitter / X" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                <i class="ph-bold ph-x-logo font-size-18"></i>
              </a>
            <?php endif; ?>
            <?php if (!empty($socials['instagram'])): ?>
              <a href="<?= e($socials['instagram']) ?>" target="_blank" class="btn btn-outline-light rounded-circle p-2" title="Instagram" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                <i class="ph-bold ph-instagram-logo font-size-18"></i>
              </a>
            <?php endif; ?>
            <?php if (!empty($socials['github'])): ?>
              <a href="<?= e($socials['github']) ?>" target="_blank" class="btn btn-outline-light rounded-circle p-2" title="GitHub" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                <i class="ph-bold ph-github-logo font-size-18"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Full Portfolio Link -->
      <a href="<?= e($portfolioUrl) ?>" class="btn btn-accent rounded-pill w-100 py-3 fw-bold text-dark font-size-14 d-flex align-items-center justify-content-center gap-2">
        <i class="ph-bold ph-arrow-square-out"></i> Explore Full Portfolio, Case Studies &amp; Rates
      </a>

      <!-- Share Card Feedback Modal / Toast -->
      <div id="vcardToast" class="alert alert-success mt-3 rounded-4 py-2 px-3 font-size-13 text-center d-none" style="background: rgba(200,255,0,0.15); color: var(--nfc-accent); border: 1px solid var(--nfc-border);">
        <i class="ph-bold ph-check-circle me-1"></i> Digital vCard link copied to clipboard!
      </div>
    </div>

  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function toggleCardFlip(event) {
  const card = document.getElementById('smartNfcCard');
  if (card) {
    card.classList.toggle('is-flipped');
  }
}

// 3D Tilt Effect on mouse movement
(function() {
  const card = document.getElementById('smartNfcCard');
  const viewport = document.querySelector('.smart-card-viewport');
  if (!card || !viewport) return;

  viewport.addEventListener('mousemove', function(e) {
    if (card.classList.contains('is-flipped')) return;
    const rect = viewport.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const rotateX = ((y - centerY) / centerY) * -12;
    const rotateY = ((x - centerX) / centerX) * 12;

    card.style.transform = `perspective(1400px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
  });

  viewport.addEventListener('mouseleave', function() {
    if (!card.classList.contains('is-flipped')) {
      card.style.transform = 'perspective(1400px) rotateX(0deg) rotateY(0deg)';
    }
  });
})();

function renderJsQr(containerId, url) {
  const img = document.getElementById('qrImgBack');
  const container = document.getElementById(containerId);
  if (img) img.style.display = 'none';
  if (container) {
    container.style.display = 'block';
    container.innerHTML = '';
    new QRCode(container, {
      text: url,
      width: 132,
      height: 132,
      colorDark: '#080c14',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  }
}

async function shareVCard() {
  const shareData = {
    title: '<?= addslashes($name) ?> — Digital Business Card',
    text: 'Save <?= addslashes($name) ?>\'s contact card or view portfolio on Adsbooster.',
    url: '<?= e($cardUrl) ?>'
  };

  if (navigator.share) {
    try {
      await navigator.share(shareData);
      return;
    } catch (e) {}
  }

  // Fallback to clipboard
  if (navigator.clipboard) {
    navigator.clipboard.writeText('<?= e($cardUrl) ?>');
    const toast = document.getElementById('vcardToast');
    if (toast) {
      toast.classList.remove('d-none');
      setTimeout(() => toast.classList.add('d-none'), 3500);
    }
  } else {
    alert('Digital vCard Link: ' + '<?= e($cardUrl) ?>');
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
