<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/ad_spy_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access the Competitor Ad Spy Engine.');
    redirect(url('login'));
}

$pageTitle = 'Competitor Ad Spy & Reverse-Engineering Engine — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

$latestAnalysis = null;

// Handle Form Analysis Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('ad-spy'));
    }

    $compName = trim($_POST['competitor_name'] ?? '');
    $compUrl = trim($_POST['competitor_url'] ?? '');
    $platform = trim($_POST['platform'] ?? 'meta');

    if (!empty($compUrl) || !empty($compName)) {
        $latestAnalysis = AdSpyEngine::analyzeCompetitor($userId, $compName, $compUrl, $platform);
        set_flash('success', "Competitor brief generated for '" . $latestAnalysis['competitor_name'] . "' - 5 counter-creative angles ready.");
    }
}

// Fetch historical analyses
$history = db()->fetchAll("SELECT * FROM competitor_ad_analyses WHERE user_id = ? ORDER BY id DESC LIMIT 10", [$userId]);
if (!$latestAnalysis && !empty($history)) {
    $row = $history[0];
    $latestAnalysis = [
        'id' => $row['id'],
        'competitor_name' => $row['competitor_name'],
        'competitor_url' => $row['competitor_url'],
        'platform' => $row['platform'],
        'scraped_ads_count' => $row['scraped_ads_count'],
        'extracted_hooks' => json_decode($row['extracted_hooks'] ?? '[]', true),
        'extracted_angles' => json_decode($row['extracted_angles'] ?? '[]', true),
        'target_audience_insights' => json_decode($row['target_audience_insights'] ?? '[]', true),
        'counter_creatives' => json_decode($row['counter_creatives'] ?? '[]', true)
    ];
}

// Older rows stored a random "scraped_ads_count". Recompute from the brief
// that is actually stored so the badge always describes real content.
if (is_array($latestAnalysis)) {
    $latestAnalysis['scraped_ads_count'] =
        count($latestAnalysis['extracted_hooks'] ?? [])
        + count($latestAnalysis['extracted_angles'] ?? [])
        + count($latestAnalysis['counter_creatives'] ?? []);
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">

    <!-- Page head -->
    <div class="fl-stagehead">
      <div>
        <div class="pill-row mb-2">
          <span class="pill bad"><i class="ph ph-detective"></i> Intelligence Suite V2.0</span>
          <span class="pill on"><i class="ph ph-lightning"></i> Steal &amp; Improve Engine</span>
        </div>
        <h2 class="h3 fw-bold mb-1">Competitor Ad Spy &amp; Reverse-Engineering</h2>
        <p class="text-secondary mb-0" style="max-width:78ch">Deconstruct a competitor's winning hooks, formats and value propositions, then launch superior counter-campaigns in one click.</p>
      </div>
      <div class="pill-row">
        <a href="<?= url('ugc-video-studio') ?>" class="fl-act"><i class="ph ph-video-camera"></i> AI Video Studio</a>
        <a href="<?= url('create-ad') ?>" class="fl-act pri"><i class="ph ph-rocket-launch"></i> Ad Creator</a>
      </div>
    </div>

    <!-- ── Spy input ─────────────────────────────────────────── -->
    <div class="fl-panel" style="max-height:none;position:relative">
      <form method="POST">
        <?= csrf_field() ?>

        <div class="fl-lbl"><span class="step">1</span> Target the competitor</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">
          <div>
            <label class="cv-label" for="spy-name">Brand name</label>
            <input type="text" name="competitor_name" id="spy-name" class="fl-in" placeholder="e.g. Gymshark, Notion, Shopify" required>
          </div>
          <div>
            <label class="cv-label" for="spy-url">Website or social handle</label>
            <input type="text" name="competitor_url" id="spy-url" class="fl-in" placeholder="https://competitor.com or @handle">
          </div>
          <div>
            <label class="cv-label" for="spy-net">Network focus</label>
            <select name="platform" id="spy-net" class="fl-in">
              <option value="meta">Meta (FB &amp; IG)</option>
              <option value="tiktok">TikTok Ads</option>
              <option value="google">Google Ads</option>
            </select>
          </div>
        </div>

        <div class="fl-lbl"><span class="step">2</span> What the brief contains</div>
        <div class="pill-row">
          <span class="pill"><i class="ph ph-anchor"></i> Hook templates</span>
          <span class="pill"><i class="ph ph-scales"></i> Angle split</span>
          <span class="pill"><i class="ph ph-film-strip"></i> Format mix</span>
          <span class="pill"><i class="ph ph-crosshair"></i> 5 counter-campaigns</span>
        </div>

        <button type="submit" class="fl-go">
          <i class="ph ph-magnifying-glass"></i> Spy &amp; Analyze
        </button>
      </form>

      <div class="fl-overlay" id="spyBusy">
        <div class="spin"></div>
        <div class="t">Reverse-engineering the brief…</div>
        <div class="s">Extracting hooks, scoring angles and drafting counter-campaigns.</div>
      </div>
    </div>

    <?php if ($latestAnalysis): ?>
    <!-- ── Overview ─────────────────────────────────────────── -->
    <div class="fl-stagehead" style="margin-top:22px">
      <div class="t"><i class="ph ph-buildings" style="color:var(--accent,#c8ff00)"></i> Brief: <?= e($latestAnalysis['competitor_name']) ?></div>
      <div class="m"><?= e($latestAnalysis['competitor_url'] ?? '') ?></div>
    </div>
    <div class="fl-stats">
      <div class="fl-stat accent">
        <div class="v"><?= (int)$latestAnalysis['scraped_ads_count'] ?></div>
        <div class="k">Insights generated</div>
      </div>
      <div class="fl-stat">
        <div class="v"><?= count($latestAnalysis['extracted_hooks'] ?? []) ?></div>
        <div class="k">Hook templates</div>
      </div>
      <div class="fl-stat">
        <div class="v"><?= count($latestAnalysis['extracted_angles'] ?? []) ?></div>
        <div class="k">Angle buckets</div>
      </div>
      <div class="fl-stat good">
        <div class="v"><?= count($latestAnalysis['counter_creatives'] ?? []) ?></div>
        <div class="k">Counter-campaigns</div>
      </div>
      <div class="fl-stat">
        <div class="v" style="text-transform:capitalize;font-size:15px;padding-top:5px"><?= e($latestAnalysis['platform']) ?></div>
        <div class="k">Network focus</div>
      </div>
    </div>

    <details class="fl-disc" style="margin-top:14px">
      <summary>
        <i class="ph ph-info"></i> How this brief is produced
        <span class="caret"><i class="ph ph-caret-down"></i></span>
      </summary>
      <div class="body">
        <div class="fl-alert info" style="margin:0">
          <i class="ph ph-shield-check"></i>
          <div>
            Written brief generated from proven-response templates. This tool does <strong>not</strong> scrape the Meta Ad Library or TikTok Creative Center,
            so the figures below describe this brief — not observed ad performance. Always validate against your own campaign data before scaling spend.
          </div>
        </div>
      </div>
    </details>

    <!-- ── Angles + hooks ───────────────────────────────────── -->
    <div class="fl-shell" style="grid-template-columns:minmax(300px,380px) 1fr;margin-top:16px">

      <div class="fl-panel" style="max-height:none">
        <div class="fl-stagehead" style="margin-bottom:16px">
          <div class="t"><i class="ph ph-scales" style="color:var(--accent,#c8ff00)"></i> Angle focus</div>
        </div>

        <?php if (!empty($latestAnalysis['extracted_angles'])): ?>
          <?php foreach ($latestAnalysis['extracted_angles'] as $ang): ?>
          <div class="meter" style="margin-bottom:13px">
            <div class="r">
              <span><?= e($ang['angle']) ?></span>
              <b><?= (int)$ang['share_pct'] ?>%</b>
            </div>
            <div class="fl-progress"><i style="width:<?= (int)$ang['share_pct'] ?>%"></i></div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="fl-help">No angle buckets in this brief.</div>
        <?php endif; ?>

        <div class="fl-lbl">Suggested format mix <span style="font-weight:500;text-transform:none;letter-spacing:0">(guidance, not measured)</span></div>
        <div class="pill-row">
          <?php foreach ($latestAnalysis['target_audience_insights']['format_split'] ?? [] as $fmt => $pct): ?>
          <span class="pill"><span style="text-transform:capitalize"><?= e($fmt) ?></span> <b style="color:var(--accent,#c8ff00)"><?= e($pct) ?></b></span>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="fl-panel" style="max-height:none">
        <div class="fl-stagehead" style="margin-bottom:14px">
          <div class="t"><i class="ph ph-anchor" style="color:var(--accent,#c8ff00)"></i> Hook templates</div>
          <div class="m"><?= count($latestAnalysis['extracted_hooks'] ?? []) ?> ready to paste</div>
        </div>

        <?php if (!empty($latestAnalysis['extracted_hooks'])): ?>
        <div class="fl-lib">
          <?php foreach ($latestAnalysis['extracted_hooks'] as $idx => $hk): ?>
          <div class="fl-card">
            <div class="bd">
              <div class="pill-row" style="margin-bottom:8px">
                <span class="pill on" style="font-size:10.5px">Hook #<?= $idx + 1 ?></span>
              </div>
              <h5 style="font-weight:600;font-size:13px;line-height:1.5;color:var(--fg,#fff)">“<?= e($hk) ?>”</h5>
            </div>
            <div class="ft">
              <button type="button" class="copy-hook" data-copy="<?= e($hk) ?>"><i class="ph ph-copy"></i> Copy</button>
              <a href="<?= url('create-ad', ['headline' => '', 'text' => $hk]) ?>" class="pri"><i class="ph ph-rocket-launch"></i> Use</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <div class="fl-empty"><i class="ph ph-anchor"></i><h4>No hooks in this brief</h4><p>Run a new analysis to extract hook templates.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Counter-campaigns ────────────────────────────────── -->
    <div style="margin-top:22px">
      <div class="fl-stagehead">
        <div class="t"><i class="ph ph-sparkle" style="color:var(--accent,#c8ff00)"></i> Steal &amp; Improve counter-campaigns</div>
        <div class="m">1-click launch into Ad Creator or Video Studio</div>
      </div>

      <?php if (!empty($latestAnalysis['counter_creatives'])): ?>
      <div class="fl-lib">
        <?php foreach ($latestAnalysis['counter_creatives'] as $cIdx => $cc): ?>
        <div class="fl-card" style="min-height:230px">
          <div class="bd">
            <div class="pill-row" style="margin-bottom:9px">
              <span class="pill on" style="font-size:10.5px"><?= e($cc['title']) ?></span>
              <span class="pill" style="font-size:10.5px">#<?= $cIdx + 1 ?></span>
            </div>
            <h5 style="margin:0 0 8px;font-size:14px;font-weight:800;color:var(--fg,#fff);line-height:1.35"><?= e($cc['headline']) ?></h5>
            <div class="fl-alert warn" style="margin:0 0 10px;padding:9px 12px;font-size:12.5px">
              <i class="ph ph-anchor"></i>
              <div>“<?= e($cc['hook']) ?>”</div>
            </div>
            <p style="margin:0;color:var(--fg2,#94a3b8);font-size:12.5px;line-height:1.55"><?= e($cc['body']) ?></p>
          </div>
          <div class="ft" style="flex-direction:column;align-items:stretch;gap:6px">
            <a href="<?= url('create-ad', ['headline' => $cc['headline'], 'text' => $cc['hook'] . ' ' . $cc['body']]) ?>" class="pri" style="text-align:center;padding:9px">
              <i class="ph ph-rocket-launch"></i> Deploy counter-ad now
            </a>
            <a href="<?= url('ugc-video-studio') ?>" style="text-align:center"><i class="ph ph-video-camera"></i> Talking video for this angle</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="fl-empty"><i class="ph ph-crosshair"></i><h4>No counter-campaigns yet</h4><p>Analyze a competitor to draft five ready-to-deploy responses.</p></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
(function () {
  var form = document.querySelector('form[method="POST"]');
  var busy = document.getElementById('spyBusy');
  if (form && busy) {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) return;
      busy.classList.add('show');
      busy.querySelector('.spin').style.animationPlayState = 'running';
    });
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.copy-hook');
    if (!b) return;
    var t = b.dataset.copy || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(function () { toast('Hook copied'); }, function () { toast('Could not copy', true); });
    } else {
      var ta = document.createElement('textarea');
      ta.value = t; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); toast('Hook copied'); } catch (err) { toast('Could not copy', true); }
      ta.remove();
    }
  });

  var toastEl;
  function toast(msg, bad) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'copy-toast'; document.body.appendChild(toastEl); }
    toastEl.textContent = msg;
    toastEl.style.background = bad ? '#f43f5e' : '';
    toastEl.style.color = bad ? '#fff' : '';
    toastEl.classList.add('show');
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
  }
})();
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
