<?php
declare(strict_types=1);

/**
 * Onboarding Popup + Social Media Connector — Adsbooster.net
 *
 * Two responsive modal popups injected on every app page for users who have
 * not finished onboarding (users.onboarding_completed = 0):
 *
 *   1. obWizardModal   — a compact 3-step "welcome" wizard. Auto-opens for
 *                        users who have not dismissed it ("Skip for later"
 *                        is session-scoped; the "Don't show again" checkbox
 *                        persists to users.onboarding_popup_dismissed).
 *                        Dismissed users get a small floating "Finish setup"
 *                        chip to reopen it.
 *   2. obConnectorModal — the social connector: every platform starts the REAL
 *                        gateway OAuth flow (Bundle.social for organic, Zernio
 *                        for ads), exactly like onboarding.php / social-connect.
 *
 * Both popups are mobile/tablet friendly: fluid width, scrollable body,
 * large touch targets, Escape + backdrop dismissal.
 *
 * This partial is included from includes/app_footer.php AFTER </body> markup
 * is closed — it renders only its own fixed-position overlays.
 */

// Never double-show on the full onboarding page, and never for guests.
$obSkip = !is_logged_in() || (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'onboarding.php');

if (!$obSkip) {
    $obUser      = current_user();
    $obDone      = !empty($obUser['onboarding_completed']);
    $obUserId    = current_user_id();

    // Dismissal gates: "Skip for later" (session-scoped) or the permanent
    // "Don't show again" checkbox (users.onboarding_popup_dismissed).
    $obDismissed = !empty($_SESSION['ob_popup_dismissed'])
        || !empty($obUser['onboarding_popup_dismissed']);

    $obConnected = [];
    if (!$obDone) {
        try {
            $obConnected = array_values(array_filter(array_column(
                db()->fetchAll("SELECT platform_name FROM connected_accounts WHERE user_id = ? AND status = 'connected'", [$obUserId]),
                'platform_name'
            )));
        } catch (\Throwable $e) {
            $obConnected = [];
        }
    }
}

if (!$obSkip && !$obDone && !$obDismissed):
    $obCsrf = csrf_token();
    $obBase = url('onboarding');
    $obPlatforms = [
        ['id' => 'meta',       'icon' => 'ph-facebook-logo',  'label' => 'Meta (Facebook)', 'color' => '#7fa7f5', 'connect' => 'facebook'],
        ['id' => 'instagram',  'icon' => 'ph-instagram-logo', 'label' => 'Instagram',       'color' => '#e8a1b8', 'connect' => 'instagram'],
        ['id' => 'tiktok',     'icon' => 'ph-tiktok-logo',    'label' => 'TikTok',          'color' => '#7ee3f0', 'connect' => 'tiktok'],
        ['id' => 'twitter',    'icon' => 'ph-twitter-logo',   'label' => 'Twitter / X',     'color' => '#a5c8ef', 'connect' => 'twitter'],
        ['id' => 'linkedin',   'icon' => 'ph-linkedin-logo',  'label' => 'LinkedIn',        'color' => '#9db8e8', 'connect' => 'linkedin'],
        ['id' => 'google_ads', 'icon' => 'ph-google-logo',    'label' => 'Google Ads',      'color' => '#f5b8a0', 'connect' => 'google_ads'],
    ];
?>
<!-- ── Onboarding wizard popup ── -->
<div id="obWizardModal" class="ob-overlay" role="dialog" aria-modal="true" aria-labelledby="obWizardTitle">
  <div class="ob-dialog">
    <button type="button" class="ob-x" onclick="obCloseWizard()" aria-label="Close">&times;</button>

    <div class="ob-head">
      <div class="ob-ava"><i class="ph-bold ph-rocket-launch"></i></div>
      <h3 id="obWizardTitle">Welcome to <?= e(get_setting('site_name', 'Adsbooster.net')) ?>, <?= e(explode(' ', trim((string)($obUser['full_name'] ?? '')))[0] ?: 'creator') ?>!</h3>
      <p>Three quick steps and you're ready to publish everywhere.</p>
    </div>

    <div class="ob-dots" id="obDots">
      <span class="on"></span><span></span><span></span>
    </div>

    <!-- Step 1 -->
    <div class="ob-step" data-step="1">
      <div class="ob-row" onclick="openConnectorPopup()">
        <span class="ob-rico" style="color:#7fa7f5"><i class="ph-bold ph-plugs-connected"></i></span>
        <div class="flex-grow-1 min-w-0">
          <b>Connect your accounts</b>
          <small>Instagram, TikTok, X, LinkedIn &amp; Google Ads — official OAuth, 20 seconds each.</small>
        </div>
        <i class="ph ph-caret-right"></i>
      </div>
      <div class="ob-connline" id="obConnLine">
        <i class="ph-bold ph-check-circle"></i>
        <span id="obConnText"><?= count($obConnected) ? count($obConnected) . ' account(s) connected already — nice.' : 'Nothing connected yet — totally optional, but it unlocks publishing.' ?></span>
      </div>
      <button type="button" class="ob-btn pri w-100" onclick="obGoStep(2)">Next: take a look around</button>
    </div>

    <!-- Step 2 -->
    <div class="ob-step" data-step="2" style="display:none">
      <div class="ob-grid3">
        <a href="<?= url('post-creator') ?>" class="ob-tile" onclick="obCloseWizard()"><i class="ph-bold ph-paper-plane-tilt"></i><span>Post Creator</span></a>
        <a href="<?= url('calendar') ?>" class="ob-tile" onclick="obCloseWizard()"><i class="ph-bold ph-calendar-check"></i><span>Content Calendar</span></a>
        <a href="<?= url('ugc-generator') ?>" class="ob-tile" onclick="obCloseWizard()"><i class="ph-bold ph-sparkle"></i><span>AI Studio</span></a>
      </div>
      <p class="ob-note">AI writes the copy, paints the images, renders the video — you just pick the channels.</p>
      <div class="d-flex gap-2">
        <button type="button" class="ob-btn ghost flex-fill" onclick="obGoStep(1)">Back</button>
        <button type="button" class="ob-btn pri flex-fill" onclick="obGoStep(3)">Next</button>
      </div>
    </div>

    <!-- Step 3 -->
    <div class="ob-step" data-step="3" style="display:none">
      <p class="ob-note" style="margin-bottom:16px">That's it! Mark onboarding complete and jump straight in — you can always connect more accounts later from <b>Social Connect</b>.</p>
      <button type="button" class="ob-btn pri w-100" id="obFinishBtn" onclick="obComplete()"><i class="ph-bold ph-check-circle"></i> Finish setup</button>
      <label class="ob-dontshow">
        <input type="checkbox" id="obDontShow"> Don't show again
      </label>
      <button type="button" class="ob-btn ghost w-100 mt-2" onclick="obSkipLater()">Skip for later</button>
    </div>
  </div>
</div>

<!-- Floating chip to reopen the wizard after dismissal -->
<a href="#" id="obReopenChip" onclick="openOnboardingPopup();return false;" title="Finish setting up your account">
  <i class="ph-bold ph-rocket-launch"></i> Finish setup
</a>

<!-- ── Social media connector popup ── -->
<div id="obConnectorModal" class="ob-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="obConnTitle">
  <div class="ob-dialog">
    <button type="button" class="ob-x" onclick="closeConnectorPopup()" aria-label="Close">&times;</button>
    <div class="ob-head">
      <div class="ob-ava" style="background:rgba(127,167,245,.12);color:#7fa7f5"><i class="ph-bold ph-plugs-connected"></i></div>
      <h3 id="obConnTitle">Connect your accounts</h3>
      <p>Every connection runs through the real <?= e(get_setting('site_name', 'Adsbooster.net')) ?> OAuth gateway — revocable any time from Social Connect.</p>
    </div>
    <div class="ob-conngrid">
      <?php foreach ($obPlatforms as $p):
          $isConn   = in_array($p['id'], $obConnected, true);
          $connHref = $obBase . '?action=connect_bundle&platform=' . rawurlencode($p['connect']);
      ?>
        <a href="<?= e($connHref) ?>" class="ob-plat <?= $isConn ? 'done' : '' ?>">
          <i class="ph-bold <?= $p['icon'] ?>" style="color:<?= $p['color'] ?>"></i>
          <div class="min-w-0 flex-grow-1">
            <b><?= e($p['label']) ?></b>
            <small><?= $isConn ? 'Connected' : 'Bundle.social OAuth' ?></small>
          </div>
          <?php if ($isConn): ?>
            <i class="ph-bold ph-check-circle" style="color:#4ade80"></i>
          <?php else: ?>
            <i class="ph ph-caret-right"></i>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="ob-adsnote">
      <i class="ph-bold ph-info"></i>
      <span>Google Ads connects through the <b>Zernio Ad Gateway</b> — manage paid campaigns from <?= url('create-ad') ?> after linking.</span>
    </div>
    <button type="button" class="ob-btn ghost w-100" onclick="closeConnectorPopup()">Done for now</button>
  </div>
</div>

<input type="hidden" id="obCsrf" value="<?= e($obCsrf) ?>">

<style>
.ob-overlay{position:fixed;inset:0;background:rgba(5,9,18,.72);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);z-index:100001;display:flex;align-items:center;justify-content:center;padding:16px}
.ob-dialog{background:#101a2b;border:1px solid rgba(255,255,255,.09);border-radius:22px;width:100%;max-width:440px;padding:26px 22px 20px;position:relative;box-shadow:0 30px 80px rgba(0,0,0,.55);max-height:calc(100vh - 32px);overflow:auto}
.ob-x{position:absolute;top:12px;right:12px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#9aa8c4;cursor:pointer;font-size:16px;line-height:1}
.ob-x:hover{color:#fff;border-color:rgba(255,255,255,.25)}
.ob-head{text-align:center;margin-bottom:16px}
.ob-ava{width:54px;height:54px;border-radius:16px;background:rgba(200,255,0,.12);color:#d7f36a;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 10px}
.ob-head h3{color:#f1f5f9;font-weight:800;font-size:18px;margin:0 0 5px}
.ob-head p{color:#9aa8c4;font-size:12.5px;margin:0;line-height:1.5}
.ob-dots{display:flex;gap:5px;justify-content:center;margin-bottom:16px}
.ob-dots span{width:22px;height:4px;border-radius:99px;background:rgba(255,255,255,.12);transition:.2s}
.ob-dots span.on{background:#c8ff00}
.ob-step{animation:obIn .22s ease}
@keyframes obIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.ob-row{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:14px;cursor:pointer;transition:border-color .15s;margin-bottom:10px}
.ob-row:hover{border-color:rgba(200,255,0,.4)}
.ob-rico{width:38px;height:38px;border-radius:11px;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;font-size:19px;flex:none}
.ob-row b{display:block;color:#f1f5f9;font-size:13.5px;font-weight:700}
.ob-row small{display:block;color:#9aa8c4;font-size:11.5px;line-height:1.4;margin-top:2px}
.ob-row .ph-caret-right{color:#6b7893;flex:none}
.ob-connline{display:flex;align-items:center;gap:7px;font-size:11.5px;color:#9aa8c4;margin:2px 2px 16px}
.ob-connline i{color:#4ade80}
.ob-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:999px;padding:12px 20px;font-size:.85rem;font-weight:800;cursor:pointer;transition:.15s;text-decoration:none}
.ob-btn.pri{background:linear-gradient(135deg,#d3fa50,#9fe60b);color:#0a1220}
.ob-btn.pri:hover{filter:brightness(1.04)}
.ob-btn.ghost{background:transparent;border:1px solid rgba(255,255,255,.15);color:#cdd9ee}
.ob-btn.ghost:hover{border-color:rgba(200,255,0,.35);color:#fff}
.ob-btn:disabled{opacity:.6;cursor:not-allowed}
.ob-dontshow{display:flex;align-items:center;justify-content:center;gap:7px;color:#9aa8c4;font-size:12px;margin:14px 0 2px;cursor:pointer;user-select:none}
.ob-dontshow input{accent-color:#c8ff00;width:15px;height:15px;cursor:pointer}
.ob-grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:12px}
.ob-tile{display:flex;flex-direction:column;align-items:center;gap:7px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:16px 8px;text-decoration:none;transition:border-color .15s}
.ob-tile:hover{border-color:rgba(200,255,0,.4)}
.ob-tile i{font-size:21px;color:#d7f36a}
.ob-tile span{color:#e5ecf7;font-size:11px;font-weight:700;text-align:center}
.ob-note{color:#9aa8c4;font-size:12px;line-height:1.55;margin:0 0 14px}
.ob-note b{color:#e5ecf7}
.ob-conngrid{display:flex;flex-direction:column;gap:8px;margin-bottom:12px}
.ob-plat{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.09);border-radius:13px;padding:12px 14px;text-decoration:none;transition:border-color .15s}
.ob-plat:hover{border-color:rgba(200,255,0,.35)}
.ob-plat i{font-size:19px;flex:none}
.ob-plat b{display:block;color:#f1f5f9;font-size:13px;font-weight:700}
.ob-plat small{display:block;color:#9aa8c4;font-size:11px;margin-top:1px}
.ob-plat .ph-caret-right{color:#6b7893;margin-left:auto;font-size:14px}
.ob-plat.done{border-color:rgba(74,222,128,.3)}
.ob-adsnote{display:flex;gap:9px;background:rgba(127,167,245,.07);border:1px solid rgba(127,167,245,.2);border-radius:12px;padding:10px 13px;font-size:11.5px;color:#a5c8ef;line-height:1.45;margin-bottom:14px}
.ob-adsnote i{flex:none;margin-top:1px}
#obReopenChip{position:fixed;left:16px;bottom:16px;z-index:99998;display:none;align-items:center;gap:8px;background:#101a2b;border:1px solid rgba(200,255,0,.35);color:#d7f36a;border-radius:999px;padding:10px 18px;font-size:12.5px;font-weight:700;text-decoration:none;box-shadow:0 12px 34px rgba(0,0,0,.45)}
#obReopenChip:hover{border-color:rgba(200,255,0,.6)}
#obReopenChip.show{display:inline-flex}
@media (max-width:480px){
  .ob-overlay{padding:10px;align-items:flex-end}
  .ob-dialog{max-width:100%;border-radius:20px 20px 0 0;padding:22px 16px 16px;margin-bottom:0}
  .ob-grid3{gap:7px}
  .ob-tile{padding:13px 6px}
}
</style>

<script>
function openOnboardingPopup() {
  var m = document.getElementById('obWizardModal');
  if (m) { m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
  var chip = document.getElementById('obReopenChip');
  if (chip) chip.classList.remove('show');
}
/* Dismissal is tracked server-side: "Skip for later" is session-scoped,
   "Don't show again" persists to users.onboarding_popup_dismissed. */
async function obDismiss(permanent) {
  var w = document.getElementById('obWizardModal');
  if (w) w.style.display = 'none';
  var c = document.getElementById('obConnectorModal');
  if (c) c.style.display = 'none';
  document.body.style.overflow = '';
  var chip = document.getElementById('obReopenChip');
  if (chip) chip.classList.add('show');
  try {
    var fd = new FormData();
    fd.append('action', 'dismiss_popup');
    fd.append('permanent', permanent ? '1' : '0');
    fd.append('csrf_token', document.getElementById('obCsrf')?.value || '');
    await fetch('<?= $obBase ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
  } catch (e) { /* popup stays closed for this page either way */ }
}
function obSkipLater() {
  var cb = document.getElementById('obDontShow');
  obDismiss(cb && cb.checked);
}
function obCloseWizard() {
  obDismiss(false);
}
function obGoStep(n) {
  document.querySelectorAll('#obWizardModal .ob-step').forEach(function (s) {
    s.style.display = (s.dataset.step === String(n)) ? 'block' : 'none';
  });
  var dots = document.querySelectorAll('#obDots span');
  dots.forEach(function (d, i) { d.classList.toggle('on', i < n); });
}
function openConnectorPopup() {
  var w = document.getElementById('obWizardModal');
  if (w) w.style.display = 'none';
  var m = document.getElementById('obConnectorModal');
  if (m) m.style.display = 'flex';
}
function closeConnectorPopup() {
  obDismiss(false);
}
async function obComplete() {
  var btn = document.getElementById('obFinishBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ph-bold ph-circle-notch" style="animation:spin 1s linear infinite"></i> Finishing…'; }
  try {
    var fd = new FormData();
    fd.append('action', 'complete');
    fd.append('csrf_token', document.getElementById('obCsrf')?.value || '');
    await fetch('<?= $obBase ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
  } catch (e) { /* even if the call fails, send them on their way */ }
  window.location.href = '<?= url('dashboard') ?>';
}
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  var c = document.getElementById('obConnectorModal');
  var w = document.getElementById('obWizardModal');
  if (c && c.style.display !== 'none') { closeConnectorPopup(); return; }
  if (w && w.style.display !== 'none') obCloseWizard();
});
/* Backdrop click closes (clicks on the dialog itself don't) */
['obWizardModal', 'obConnectorModal'].forEach(function (id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener('click', function (e) {
    if (e.target === el) { id === 'obConnectorModal' ? closeConnectorPopup() : obCloseWizard(); }
  });
});
(function () {
  /* The partial only renders for users who have NOT dismissed the popup
     (server-side gate), so auto-open unless something is already open. */
  setTimeout(openOnboardingPopup, 600);
})();
</script>

<?php endif; ?>
