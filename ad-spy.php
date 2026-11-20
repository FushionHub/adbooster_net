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
        $observed = $latestAnalysis['target_audience_insights']['observed'] ?? [];
        if (($latestAnalysis['data_source'] ?? '') === 'observed_page') {
            set_flash('success', "Brief built from " . ($observed['source_url'] ?: $latestAnalysis['competitor_url'])
                . ' — ' . (int)$latestAnalysis['observed_line_count'] . ' live copy lines quoted.');
        } else {
            set_flash('warning', 'Draft brief created, but nothing was observed: '
                . ($observed['message'] ?: 'no page was fetched')
                . ' The drafts below are templates only.');
        }
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
        'data_source' => 'template_only',
        'observed_line_count' => 0,
        'extracted_hooks' => json_decode($row['extracted_hooks'] ?? '[]', true) ?: [],
        'extracted_angles' => json_decode($row['extracted_angles'] ?? '[]', true) ?: [],
        'target_audience_insights' => json_decode($row['target_audience_insights'] ?? '[]', true) ?: [],
        'counter_creatives' => json_decode($row['counter_creatives'] ?? '[]', true) ?: []
    ];
}

// Normalize for rendering. Briefs written before live observation existed
// carry template-only content — they are marked as such and their
// unverifiable claims are withheld instead of being shown as ready to deploy.
if (is_array($latestAnalysis)) {
    $audience = is_array($latestAnalysis['target_audience_insights'] ?? null)
        ? $latestAnalysis['target_audience_insights']
        : [];
    $observation = is_array($audience['observed'] ?? null) ? $audience['observed'] : [];
    $latestAnalysis['observation'] = $observation;
    $latestAnalysis['observed_line_count'] = (int)($latestAnalysis['observed_line_count']
        ?? ($observation['copy_line_count'] ?? 0));
    $latestAnalysis['is_observed'] = ($latestAnalysis['data_source'] ?? '') === 'observed_page' && $observation !== [];
    $latestAnalysis['format_recommendations'] = $audience['format_recommendations'] ?? [];
    $latestAnalysis['legacy_format_split'] = $audience['format_split'] ?? [];

    $hookText = static fn($hook): string => is_array($hook) ? (string)($hook['text'] ?? '') : (string)$hook;

    if (!$latestAnalysis['is_observed']) {
        $safeHooks = [];
        $withheld = 0;
        foreach ($latestAnalysis['extracted_hooks'] as $hook) {
            $text = $hookText($hook);
            if ($text === '' || AdSpyEngine::containsUnverifiableClaim($text)) {
                $withheld++;
                continue;
            }
            $safeHooks[] = $hook;
        }
        $latestAnalysis['extracted_hooks'] = $safeHooks;
        $latestAnalysis['withheld_hooks'] = $withheld;

        $safeCreatives = [];
        $withheldCreatives = 0;
        foreach ($latestAnalysis['counter_creatives'] as $creative) {
            $copy = is_array($creative) ? implode(' ', array_map('strval', $creative)) : (string)$creative;
            if (AdSpyEngine::containsUnverifiableClaim($copy)) {
                $withheldCreatives++;
                continue;
            }
            $safeCreatives[] = $creative;
        }
        $latestAnalysis['counter_creatives'] = $safeCreatives;
        $latestAnalysis['withheld_creatives'] = $withheldCreatives;
    }
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">

    <!-- Page head -->
    <div class="fl-stagehead">
      <div>
        <div class="pill-row mb-2">
          <span class="pill bad"><i class="ph ph-detective"></i> Live-Copy Intelligence</span>
          <span class="pill on"><i class="ph ph-lightning"></i> Evidence-Grounded Engine</span>
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
            <label class="cv-label" for="spy-url">Competitor website URL</label>
            <input type="text" name="competitor_url" id="spy-url" class="fl-in" placeholder="https://competitor.com">
            <div class="fl-help" style="margin-top:5px">A public website URL lets the brief quote their live copy. Social handles are not fetched.</div>
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
          <span class="pill"><i class="ph ph-anchor"></i> Live copy quotes</span>
          <span class="pill"><i class="ph ph-scales"></i> Messaging signals</span>
          <span class="pill"><i class="ph ph-film-strip"></i> Suggested formats</span>
          <span class="pill"><i class="ph ph-crosshair"></i> 5 draft campaigns</span>
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
        <div class="v"><?= $latestAnalysis['is_observed'] ? (int)$latestAnalysis['observed_line_count'] : '—' ?></div>
        <div class="k">Copy lines observed</div>
      </div>
      <div class="fl-stat">
        <div class="v"><?= count($latestAnalysis['extracted_hooks'] ?? []) ?></div>
        <div class="k"><?= $latestAnalysis['is_observed'] ? 'Quoted hooks' : 'Template hooks' ?></div>
      </div>
      <div class="fl-stat">
        <div class="v"><?= count($latestAnalysis['extracted_angles'] ?? []) ?></div>
        <div class="k">Messaging signals</div>
      </div>
      <div class="fl-stat good">
        <div class="v"><?= count($latestAnalysis['counter_creatives'] ?? []) ?></div>
        <div class="k">Draft campaigns</div>
      </div>
      <div class="fl-stat">
        <div class="v" style="text-transform:capitalize;font-size:15px;padding-top:5px"><?= e($latestAnalysis['platform']) ?></div>
        <div class="k">Network focus</div>
      </div>
    </div>

    <?php if ($latestAnalysis['is_observed']): ?>
      <div class="fl-alert info" style="margin:14px 0 0">
        <i class="ph ph-check-circle"></i>
        <div>
          Observed <strong><?= e($latestAnalysis['observation']['source_url'] ?: $latestAnalysis['competitor_url']) ?></strong>
          <?php if (!empty($latestAnalysis['observation']['scraped_at'])): ?>
            at <?= e($latestAnalysis['observation']['scraped_at']) ?>
          <?php endif; ?>.
          Every hook below is a verbatim quote from that page and every signal shows the line that proves it.
          <?php if (!empty($latestAnalysis['observation']['themes'])): ?>
            <br>Recurring themes in their copy: <strong><?= e(implode(', ', $latestAnalysis['observation']['themes'])) ?></strong>.
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="fl-alert warn" style="margin:14px 0 0">
        <i class="ph ph-warning"></i>
        <div>
          <strong>Nothing was observed for this brief.</strong>
          <?= e($latestAnalysis['observation']['message'] ?: 'No page was fetched, so no competitor copy was collected.') ?>
          <?php if (!empty($latestAnalysis['withheld_hooks'])): ?>
            <?= (int)$latestAnalysis['withheld_hooks'] ?> older template hook(s) and
            <?= (int)($latestAnalysis['withheld_creatives'] ?? 0) ?> older draft(s) were withheld because they contained claims we cannot verify.
          <?php endif; ?>
          Run a new analysis with a public website URL to get a brief grounded in live copy.
        </div>
      </div>
    <?php endif; ?>

    <details class="fl-disc" style="margin-top:14px">
      <summary>
        <i class="ph ph-info"></i> How this brief is produced
        <span class="caret"><i class="ph ph-caret-down"></i></span>
      </summary>
      <div class="body">
        <div class="fl-alert info" style="margin:0">
          <i class="ph ph-shield-check"></i>
          <div>
            When you supply a website URL, the tool fetches that page and quotes it. It does <strong>not</strong> read the Meta Ad Library,
            TikTok Creative Center or Google Ads, and it does not report impressions, spend or performance — those numbers only exist in
            your own campaign data. The counter-campaigns are <strong>drafts with placeholders</strong>: every claim we cannot verify for your
            product stays bracketed so you can replace it with real proof before publishing.
          </div>
        </div>
      </div>
    </details>

    <!-- ── Angles + hooks ───────────────────────────────────── -->
    <div class="fl-shell" style="grid-template-columns:minmax(300px,380px) 1fr;margin-top:16px">

      <div class="fl-panel" style="max-height:none">
        <div class="fl-stagehead" style="margin-bottom:16px">
          <div class="t"><i class="ph ph-scales" style="color:var(--accent,#c8ff00)"></i> Messaging signals</div>
          <div class="m"><?= $latestAnalysis['is_observed'] ? 'detected in their copy' : 'no copy observed' ?></div>
        </div>

        <?php if (!empty($latestAnalysis['extracted_angles'])): ?>
          <?php foreach ($latestAnalysis['extracted_angles'] as $ang):
              $evidence = is_array($ang['evidence'] ?? null) ? $ang['evidence'] : [];
              $isObserved = ($ang['basis'] ?? '') === 'observed';
          ?>
          <div class="meter" style="margin-bottom:13px">
            <div class="r">
              <span><?= e($ang['angle']) ?></span>
              <b><?= $isObserved ? count($evidence) . ' observed line' . (count($evidence) === 1 ? '' : 's') : 'template' ?></b>
            </div>
            <?php if ($isObserved): ?>
              <div style="margin-top:7px;border-left:2px solid var(--accent,#c8ff00);padding-left:10px">
                <?php foreach ($evidence as $ev): ?>
                  <div style="font-size:12px;color:var(--fg2,#94a3b8);line-height:1.5;margin-bottom:4px">“<?= e($ev) ?>”</div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="fl-help" style="margin-top:5px"><?= e($ang['desc'] ?? '') ?> — template guidance, no matching copy was observed.</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="fl-empty"><i class="ph ph-scales"></i><h4>No signals detected</h4><p>Nothing was observed that we can quote as a competitor signal.</p></div>
        <?php endif; ?>

        <div class="fl-lbl">Suggested formats <span style="font-weight:500;text-transform:none;letter-spacing:0">(our recommendation — not their mix)</span></div>
        <div class="pill-row">
          <?php if (!empty($latestAnalysis['format_recommendations'])): ?>
            <?php foreach ($latestAnalysis['format_recommendations'] as $fmt): ?>
              <span class="pill" title="<?= e($fmt['why'] ?? '') ?>"><?= e($fmt['format'] ?? '') ?></span>
            <?php endforeach; ?>
          <?php elseif (!empty($latestAnalysis['legacy_format_split'])): ?>
            <?php foreach ($latestAnalysis['legacy_format_split'] as $fmt => $pct): ?>
              <span class="pill"><span style="text-transform:capitalize"><?= e($fmt) ?></span></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="fl-panel" style="max-height:none">
        <div class="fl-stagehead" style="margin-bottom:14px">
          <div class="t"><i class="ph ph-anchor" style="color:var(--accent,#c8ff00)"></i> <?= $latestAnalysis['is_observed'] ? 'Quoted hooks' : 'Template hooks' ?></div>
          <div class="m"><?= count($latestAnalysis['extracted_hooks'] ?? []) ?> <?= $latestAnalysis['is_observed'] ? 'verbatim lines' : 'writing templates' ?></div>
        </div>

        <?php if (!empty($latestAnalysis['extracted_hooks'])): ?>
        <div class="fl-lib">
          <?php foreach ($latestAnalysis['extracted_hooks'] as $idx => $hk):
              $hkText = is_array($hk) ? (string)($hk['text'] ?? '') : (string)$hk;
          ?>
          <div class="fl-card">
            <div class="bd">
              <div class="pill-row" style="margin-bottom:8px">
                <span class="pill on" style="font-size:10.5px">Quote #<?= $idx + 1 ?></span>
                <?php if ($latestAnalysis['is_observed']): ?>
                  <span class="pill" style="font-size:10.5px"><i class="ph ph-quotes"></i> observed</span>
                <?php endif; ?>
              </div>
              <h5 style="font-weight:600;font-size:13px;line-height:1.5;color:var(--fg,#fff)">“<?= e($hkText) ?>”</h5>
            </div>
            <div class="ft">
              <button type="button" class="copy-hook" data-copy="<?= e($hkText) ?>"><i class="ph ph-copy"></i> Copy</button>
              <a href="<?= url('create-ad', ['headline' => '', 'text' => $hkText]) ?>" class="pri"><i class="ph ph-rocket-launch"></i> Use</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <div class="fl-empty"><i class="ph ph-anchor"></i><h4>No hooks to show</h4><p>Run an analysis with a public website URL to quote their live copy.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Counter-campaigns ────────────────────────────────── -->
    <div style="margin-top:22px">
      <div class="fl-stagehead">
        <div class="t"><i class="ph ph-sparkle" style="color:var(--accent,#c8ff00)"></i> Draft counter-campaigns</div>
        <div class="m">Placeholders must be filled with your own proof before publishing</div>
      </div>

      <?php if (!empty($latestAnalysis['counter_creatives'])): ?>
      <div class="fl-lib">
        <?php foreach ($latestAnalysis['counter_creatives'] as $cIdx => $cc): ?>
        <div class="fl-card" style="min-height:230px">
          <div class="bd">
            <div class="pill-row" style="margin-bottom:9px">
              <span class="pill on" style="font-size:10.5px"><?= e($cc['title'] ?? ('Draft ' . ($cIdx + 1))) ?></span>
              <span class="pill" style="font-size:10.5px">#<?= $cIdx + 1 ?></span>
              <?php if (($cc['basis'] ?? '') === 'observed'): ?>
                <span class="pill on" style="font-size:10.5px"><i class="ph ph-crosshair"></i> answers an observed signal</span>
              <?php endif; ?>
            </div>
            <h5 style="margin:0 0 8px;font-size:14px;font-weight:800;color:var(--fg,#fff);line-height:1.35"><?= e($cc['headline'] ?? '') ?></h5>
            <?php if (!empty($cc['hook'])): ?>
            <div class="fl-alert warn" style="margin:0 0 10px;padding:9px 12px;font-size:12.5px">
              <i class="ph ph-anchor"></i>
              <div>“<?= e($cc['hook']) ?>”</div>
            </div>
            <?php endif; ?>
            <p style="margin:0;color:var(--fg2,#94a3b8);font-size:12.5px;line-height:1.55"><?= e($cc['body'] ?? '') ?></p>
            <?php if (!empty($cc['requires'])): ?>
            <div class="fl-alert info" style="margin:10px 0 0;padding:9px 12px;font-size:12px">
              <i class="ph ph-warning"></i>
              <div><strong>Fill before publishing:</strong> <?= e(implode(' · ', $cc['requires'])) ?></div>
            </div>
            <?php endif; ?>
          </div>
          <div class="ft" style="flex-direction:column;align-items:stretch;gap:6px">
            <a href="<?= url('create-ad', ['headline' => ($cc['headline'] ?? ''), 'text' => trim(($cc['hook'] ?? '') . ' ' . ($cc['body'] ?? ''))]) ?>" class="pri" style="text-align:center;padding:9px">
              <i class="ph ph-rocket-launch"></i> Open in Ad Creator
            </a>
            <a href="<?= url('ugc-video-studio') ?>" style="text-align:center"><i class="ph ph-video-camera"></i> Talking video for this angle</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="fl-empty"><i class="ph ph-crosshair"></i><h4>No drafts to show</h4><p>
        <?php if (!empty($latestAnalysis['withheld_creatives'])): ?>
          The <?= (int)$latestAnalysis['withheld_creatives'] ?> draft(s) stored with this brief were withheld because they contained claims we cannot verify. Run a new analysis for drafts you can publish.
        <?php else: ?>
          Analyze a competitor with a public website URL to draft responses to their live copy.
        <?php endif; ?>
      </p></div>
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
