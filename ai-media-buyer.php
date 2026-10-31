<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/auto_buyer_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access the AI Media Buyer.');
    redirect(url('login'));
}

$pageTitle = 'Autonomous AI Media Buyer & Anti-Burn Kill-Switch — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('ai-media-buyer'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_rule') {
        AutoBuyerEngine::saveRule($userId, $_POST);
        set_flash('success', 'AI Media Buyer rule saved successfully.');
        redirect(url('ai-media-buyer'));
    }

    if ($action === 'delete_rule') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        AutoBuyerEngine::deleteRule($userId, $ruleId);
        set_flash('success', 'Rule deleted successfully.');
        redirect(url('ai-media-buyer'));
    }

    if ($action === 'load_default_template') {
        $templates = AutoBuyerEngine::getDefaultRulesTemplate();
        foreach ($templates as $tmpl) {
            AutoBuyerEngine::saveRule($userId, $tmpl);
        }
        set_flash('success', 'Standard Anti-Burn & Growth rules loaded into your account!');
        redirect(url('ai-media-buyer'));
    }

    if ($action === 'run_now') {
        $results = AutoBuyerEngine::evaluateAllRules($userId);
        $cnt = count($results);
        set_flash('success', "Evaluation completed! Triggered {$cnt} automated protection actions.");
        redirect(url('ai-media-buyer'));
    }
}

// Fetch active user campaigns
$campaigns = db()->fetchAll("SELECT * FROM ad_campaigns WHERE user_id = ? ORDER BY id DESC", [$userId]);
$rules = db()->fetchAll("SELECT * FROM ai_media_buyer_rules WHERE user_id = ? ORDER BY id DESC", [$userId]);
$logs = db()->fetchAll("SELECT * FROM ai_media_buyer_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$userId]);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">

    <!-- Page head -->
    <div class="fl-stagehead">
      <div>
        <div class="pill-row mb-2">
          <span class="pill good"><span class="dot-live"></span> Anti-burn shield armed</span>
          <span class="pill on"><i class="ph ph-cpu"></i> Autonomous 24/7 agent</span>
        </div>
        <h2 class="h3 fw-bold mb-1">Autonomous AI Media Buyer &amp; Kill-Switch</h2>
        <p class="text-secondary mb-0" style="max-width:80ch">Protects your ad spend while you sleep — auto-pauses fatigued ads, scales profitable winners and alerts you in real time.</p>
      </div>
      <div class="pill-row">
        <form method="POST" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="run_now">
          <button type="submit" class="fl-act"><i class="ph ph-play-circle"></i> Evaluate rules now</button>
        </form>
        <button type="button" class="fl-act pri" data-bs-toggle="modal" data-bs-target="#modal-new-rule">
          <i class="ph ph-plus-circle"></i> Create protection rule
        </button>
      </div>
    </div>

    <!-- Telemetry -->
    <div class="fl-stats">
      <div class="fl-stat accent">
        <div class="v"><?= count($rules) ?></div>
        <div class="k">Active rules</div>
      </div>
      <div class="fl-stat">
        <div class="v"><?= count($campaigns) ?></div>
        <div class="k">Monitored campaigns</div>
      </div>
      <div class="fl-stat warn">
        <div class="v"><?= count($logs) ?></div>
        <div class="k">Auto-interventions</div>
      </div>
      <div class="fl-stat good">
        <div class="v" style="font-size:19px;padding-top:3px">ARMED</div>
        <div class="k">Latency &lt; 60s per check</div>
      </div>
    </div>

    <div class="fl-shell" style="grid-template-columns:minmax(0,1.55fr) minmax(300px,1fr);margin-top:16px">

      <!-- ── Rules ───────────────────────────────────────────── -->
      <div style="display:grid;gap:16px;align-content:start">
        <div class="fl-panel" style="max-height:none">
          <div class="fl-stagehead">
            <div class="t"><i class="ph ph-sliders-horizontal" style="color:var(--accent,#c8ff00)"></i> Anti-burn &amp; auto-pilot rules</div>
            <?php if (empty($rules)): ?>
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="load_default_template">
              <button type="submit" class="fl-act"><i class="ph ph-magic-wand"></i> Load best-practice rules</button>
            </form>
            <?php else: ?>
            <div class="m"><?= count($rules) ?> armed</div>
            <?php endif; ?>
          </div>

          <?php if (empty($rules)): ?>
          <div class="fl-empty" style="max-width:420px;margin:8px auto 4px">
            <i class="ph ph-shield-check"></i>
            <h4>No custom rules yet</h4>
            <p>Load the standard CPA/ROAS kill-switches, or write your own threshold and autonomous action.</p>
            <form method="POST" style="margin-top:16px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="load_default_template">
              <button type="submit" class="fl-go" style="max-width:280px;margin:0 auto">
                <i class="ph ph-sparkle"></i> Load default anti-burn rules
              </button>
            </form>
          </div>
          <?php else: ?>
          <div style="display:grid;gap:9px">
            <?php foreach ($rules as $r): ?>
            <div style="display:flex;align-items:center;gap:11px;flex-wrap:wrap;padding:13px 14px;border-radius:13px;background:var(--bg2,#0f172a);border:1px solid var(--border,rgba(255,255,255,.08))">
              <div style="min-width:0;flex:1">
                <div style="font-size:13.5px;font-weight:700;color:var(--fg,#fff)"><?= e($r['rule_name']) ?></div>
                <div class="pill-row" style="margin-top:7px">
                  <span class="pill" style="font-family:'JetBrains Mono',monospace;font-size:10.5px">
                    <?= strtoupper(e($r['condition_metric'])) ?> <?= e($r['condition_operator']) ?> <?= (float)$r['condition_value'] ?>
                  </span>
                  <span class="pill info" style="font-size:10.5px">
                    <i class="ph ph-lightning"></i>
                    <?= ucwords(str_replace('_', ' ', e($r['action_type']))) ?><?= (float)$r['action_value'] > 0 ? ' (' . (float)$r['action_value'] . '%)' : '' ?>
                  </span>
                  <span class="pill <?= $r['is_active'] ? 'good' : '' ?>" style="font-size:10.5px">
                    <?= $r['is_active'] ? '<span class="dot-live"></span> Active' : 'Disabled' ?>
                  </span>
                </div>
              </div>
              <div style="text-align:right;flex:0 0 auto">
                <div style="font-size:11px;color:var(--fg3,#64748b);margin-bottom:6px">
                  <?= !empty($r['last_triggered_at']) ? 'Fired ' . date('M d, H:i', strtotime($r['last_triggered_at'])) : 'Never fired' ?>
                </div>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this rule?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_rule">
                  <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                  <button type="submit" class="fl-act danger" style="padding:5px 11px;font-size:11.5px">
                    <i class="ph ph-trash"></i> Delete
                  </button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Fatigue radar -->
        <div class="fl-panel" style="max-height:none">
          <div class="fl-stagehead">
            <div class="t"><i class="ph ph-radar" style="color:var(--accent,#c8ff00)"></i> Live campaign fatigue radar</div>
          </div>

          <?php if (empty($campaigns)): ?>
          <div class="fl-empty" style="max-width:400px;margin:6px auto">
            <i class="ph ph-megaphone"></i>
            <h4>Nothing to monitor yet</h4>
            <p>No active campaigns found. <a href="<?= url('create-ad') ?>" style="color:var(--accent,#c8ff00)">Launch a campaign</a> to start the fatigue radar.</p>
          </div>
          <?php else: ?>
          <div style="display:grid;gap:11px">
            <?php foreach ($campaigns as $camp):
              $fatigue = AutoBuyerEngine::predictFatigue((int)$camp['id']);
              $metrics = AutoBuyerEngine::getCampaignCalculatedMetrics((int)$camp['id']);
              $score = (int)$fatigue['fatigue_score'];
              $pillClass = $score >= 65 ? 'bad' : ($score >= 40 ? 'warn' : 'good');
            ?>
            <div style="padding:14px;border-radius:13px;background:var(--bg2,#0f172a);border:1px solid var(--border,rgba(255,255,255,.08))">
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div style="min-width:0">
                  <div style="font-size:13.5px;font-weight:700;color:var(--fg,#fff)"><?= e($camp['campaign_name']) ?></div>
                  <div style="font-size:11.5px;color:var(--fg3,#64748b);text-transform:capitalize;margin-top:2px"><?= e($camp['campaign_type'] ?? 'Meta') ?></div>
                </div>
                <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                  <span class="pill <?= $pillClass ?>" style="font-size:11px"><?= e($fatigue['risk_level']) ?></span>
                  <a href="<?= url('ugc-video-studio') ?>" class="fl-act" style="padding:5px 11px;font-size:11.5px">
                    <i class="ph ph-arrows-clockwise"></i> AI refresh
                  </a>
                </div>
              </div>

              <div class="meter" style="margin-top:12px">
                <div class="r"><span>Fatigue score</span><b><?= $score ?>%</b></div>
                <div class="fl-progress"><i style="width:<?= $score ?>%;background:<?= $score >= 65 ? 'linear-gradient(90deg,#f43f5e,#fb923c)' : ($score >= 40 ? 'linear-gradient(90deg,#fb923c,#facc15)' : 'linear-gradient(90deg,#10b981,#7ed957)') ?>"></i></div>
              </div>

              <div class="pill-row" style="margin-top:11px">
                <span class="pill" style="font-size:10.5px;font-family:'JetBrains Mono',monospace">$<?= number_format((float)$camp['budget_amount'], 2) ?> budget</span>
                <span class="pill info" style="font-size:10.5px;font-family:'JetBrains Mono',monospace"><?= $metrics['ctr'] ?>% CTR</span>
                <span class="pill warn" style="font-size:10.5px;font-family:'JetBrains Mono',monospace">$<?= $metrics['cpa'] ?> CPA</span>
              </div>
              <div class="fl-help" style="margin-top:8px"><i class="ph ph-info"></i> <?= e($fatigue['recommendation']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Intervention log ────────────────────────────────── -->
      <div class="fl-panel" style="max-height:none">
        <div class="fl-stagehead">
          <div class="t"><i class="ph ph-clock-counter-clockwise" style="color:var(--accent,#c8ff00)"></i> Real-time interventions</div>
          <div class="m"><?= count($logs) ?> recent</div>
        </div>

        <?php if (empty($logs)): ?>
        <div class="fl-empty" style="max-width:340px;margin:14px auto">
          <i class="ph ph-check-circle"></i>
          <h4>All clear</h4>
          <p>No protective actions needed yet. Every campaign is operating inside its thresholds.</p>
        </div>
        <?php else: ?>
        <div class="tl">
          <?php foreach ($logs as $lg): ?>
          <div class="tl-item">
            <div class="tl-h"><?= ucwords(str_replace('_', ' ', e($lg['action_taken']))) ?></div>
            <div class="tl-b"><?= e($lg['details']) ?></div>
            <div style="font-size:11px;color:var(--fg3,#64748b);margin-top:5px;font-family:'JetBrains Mono',monospace"><?= date('M d, H:i', strtotime($lg['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- Modal: New Rule -->
<div class="modal fade" id="modal-new-rule" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:var(--surface-card,#0d1524);border:1px solid var(--border,rgba(255,255,255,.1));border-radius:18px;color:var(--fg,#fff)">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_rule">
        <div style="padding:18px 20px 0">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
            <div style="font-weight:800;font-size:15px;display:flex;align-items:center;gap:8px">
              <i class="ph ph-shield-warning" style="color:var(--accent,#c8ff00)"></i> Create autonomous rule
            </div>
            <button type="button" class="cv-tbtn" data-bs-dismiss="modal" aria-label="Close"><i class="ph ph-x"></i></button>
          </div>
          <div class="fl-help">When the metric crosses your threshold, the agent takes the action automatically — no approval step.</div>
        </div>

        <div style="padding:14px 20px;display:grid;gap:14px">
          <div>
            <label class="cv-label" for="rule-name">Rule name</label>
            <input type="text" name="rule_name" id="rule-name" class="fl-in" placeholder="e.g. Pause ad if CPA exceeds $35" required>
          </div>

          <div>
            <div class="fl-lbl"><span class="step">1</span> Trigger condition</div>
            <div style="display:grid;grid-template-columns:1.6fr .7fr 1fr;gap:10px">
              <div>
                <label class="cv-label" for="cond-metric">Metric</label>
                <select name="condition_metric" id="cond-metric" class="fl-in">
                  <option value="cpa">Cost per acquisition (CPA)</option>
                  <option value="ctr">Click-through rate (CTR %)</option>
                  <option value="roas">Return on ad spend (ROAS)</option>
                  <option value="spend_without_conv">Spend without conversion ($)</option>
                  <option value="frequency">Ad frequency</option>
                </select>
              </div>
              <div>
                <label class="cv-label" for="cond-op">Operator</label>
                <select name="condition_operator" id="cond-op" class="fl-in">
                  <option value=">">&gt;</option>
                  <option value=">=">&gt;=</option>
                  <option value="<">&lt;</option>
                  <option value="<=">&lt;=</option>
                </select>
              </div>
              <div>
                <label class="cv-label" for="cond-val">Threshold</label>
                <input type="number" step="0.01" name="condition_value" id="cond-val" class="fl-in" placeholder="25.00" required>
              </div>
            </div>
          </div>

          <div>
            <div class="fl-lbl"><span class="step">2</span> Autonomous action</div>
            <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:10px">
              <div>
                <label class="cv-label" for="act-type">Action</label>
                <select name="action_type" id="act-type" class="fl-in">
                  <option value="pause_campaign">Emergency pause campaign</option>
                  <option value="scale_budget_pct">Auto-scale budget (+%)</option>
                  <option value="reduce_budget_pct">Reduce budget (-%)</option>
                  <option value="trigger_creative_refresh">Trigger AI creative refresh</option>
                  <option value="notify_only">Email &amp; alert only</option>
                </select>
              </div>
              <div>
                <label class="cv-label" for="act-val">Adjustment %</label>
                <input type="number" step="1" name="action_value" id="act-val" class="fl-in" placeholder="20" value="0">
              </div>
            </div>
          </div>
        </div>

        <div style="padding:0 20px 18px;display:flex;gap:9px;justify-content:flex-end">
          <button type="button" class="fl-act" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="fl-act pri"><i class="ph ph-shield-check"></i> Arm rule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
