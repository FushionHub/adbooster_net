<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Analytics — ' . get_setting('site_name', 'Adsbooster.net');

require_once __DIR__ . '/includes/auth_guard.php';

$userId = current_user_id();

$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo   = $_GET['to']   ?? date('Y-m-d');
$network  = $_GET['network'] ?? '';

$summary = db()->fetchOne(
    "SELECT
        COALESCE(SUM(impressions),0) as total_impressions,
        COALESCE(SUM(clicks),0) as total_clicks,
        COALESCE(SUM(spend),0) as total_spend,
        COALESCE(SUM(conversions),0) as total_conversions
     FROM campaign_metrics_daily m
     JOIN ad_campaigns c ON c.id = m.campaign_id
     WHERE c.user_id = ? AND m.date BETWEEN ? AND ?",
    [$userId, $dateFrom, $dateTo]
);

$platformStats = [];
$networkCondition = '';
$params = [$userId, $dateFrom, $dateTo];
if ($network !== '') {
    $networkCondition = " AND c.campaign_type = ?";
    $params[] = $network;
}

$platformRows = db()->fetchAll(
    "SELECT c.campaign_type,
            COALESCE(SUM(m.impressions),0) as impressions,
            COALESCE(SUM(m.clicks),0) as clicks,
            COALESCE(SUM(m.spend),0) as spend,
            COALESCE(SUM(m.conversions),0) as conversions
     FROM campaign_metrics_daily m
     JOIN ad_campaigns c ON c.id = m.campaign_id
     WHERE c.user_id = ? AND m.date BETWEEN ? AND ?
     {$networkCondition}
     GROUP BY c.campaign_type ORDER BY spend DESC",
    $params
);

$dailyData = db()->fetchAll(
    "SELECT m.date as stat_date, COALESCE(SUM(m.impressions),0) as impressions, COALESCE(SUM(m.clicks),0) as clicks, COALESCE(SUM(m.spend),0) as spend, COALESCE(SUM(m.conversions),0) as conversions
     FROM campaign_metrics_daily m
     JOIN ad_campaigns c ON c.id = m.campaign_id
     WHERE c.user_id = ? AND m.date BETWEEN ? AND ?
     GROUP BY m.date ORDER BY m.date ASC",
    [$userId, $dateFrom, $dateTo]
);

$bestCreative = db()->fetchOne(
    "SELECT c.headline, c.primary_text, c.campaign_type, c.creative_media_url,
            COALESCE(SUM(m.clicks),0) as total_clicks, COALESCE(SUM(m.conversions),0) as total_conv
     FROM campaign_metrics_daily m
     JOIN ad_campaigns c ON c.id = m.campaign_id
     WHERE c.user_id = ? AND m.date BETWEEN ? AND ?
     GROUP BY c.id ORDER BY total_clicks DESC LIMIT 1",
    [$userId, $dateFrom, $dateTo]
);

$needsAttention = db()->fetchAll(
    "SELECT c.id, c.campaign_name, c.campaign_type, c.status,
            COALESCE(SUM(m.spend),0) as spend,
            COALESCE(SUM(m.conversions),0) as conversions,
            CASE WHEN COALESCE(SUM(m.spend),0) > 0 THEN COALESCE(SUM(m.conversions),0) / COALESCE(SUM(m.spend),0) ELSE 0 END as roas
     FROM campaign_metrics_daily m
     JOIN ad_campaigns c ON c.id = m.campaign_id
     WHERE c.user_id = ? AND m.date BETWEEN ? AND ? AND c.status = 'active'
     GROUP BY c.id
     HAVING (spend > 10 AND conversions = 0) OR roas < 0.5
     ORDER BY spend DESC LIMIT 5",
    [$userId, $dateFrom, $dateTo]
);

// ── Ad Audit: score every campaign and rank best-first ──
$auditRows = db()->fetchAll(
    "SELECT c.id, c.campaign_name, c.campaign_type, c.status, c.headline, c.creative_media_url,
            COALESCE(SUM(m.impressions),0) as impressions,
            COALESCE(SUM(m.clicks),0) as clicks,
            COALESCE(SUM(m.spend),0) as spend,
            COALESCE(SUM(m.conversions),0) as conversions
     FROM campaign_metrics_daily m
     JOIN ad_campaigns c ON c.id = m.campaign_id
     WHERE c.user_id = ? AND m.date BETWEEN ? AND ?
     GROUP BY c.id
     ORDER BY clicks DESC, spend DESC
     LIMIT 25",
    [$userId, $dateFrom, $dateTo]
);

// Score each campaign 0-100 (CTR quality 55%, conversion efficiency 30%, volume 15%).
$audit = [];
foreach ($auditRows as $r) {
    $ctr  = $r['impressions'] > 0 ? ($r['clicks'] / $r['impressions']) * 100 : 0.0;
    $cpc  = $r['clicks'] > 0 ? $r['spend'] / $r['clicks'] : 0.0;
    $cpa  = $r['conversions'] > 0 ? $r['spend'] / $r['conversions'] : 0.0;
    $roas = $r['spend'] > 0 ? $r['conversions'] / $r['spend'] : 0.0;

    $ctrScore  = min(1.0, $ctr / 2.0);              // 2%+ CTR is excellent
    $convScore = $roas >= 0.15 ? 1.0 : ($roas / 0.15); // 0.15 conv/$ is solid
    $volScore  = min(1.0, $r['impressions'] / 5000);   // 5k+ impressions = signal
    $score     = (int)round(($ctrScore * 55 + $convScore * 30 + $volScore * 15) * 100);

    if ($r['impressions'] < 200) {
        $verdict = ['label' => 'Not enough data', 'class' => 'badge-pending', 'icon' => 'ph-hourglass', 'advice' => 'Let it gather 1,000+ impressions before judging.'];
    } elseif ($ctr >= 1.5 && $r['conversions'] > 0) {
        $verdict = ['label' => 'Winner — scale it', 'class' => 'badge-active', 'icon' => 'ph-trophy', 'advice' => 'Raise the budget 20% and clone this creative for other networks.'];
    } elseif ($ctr >= 0.8) {
        $verdict = ['label' => 'Healthy', 'class' => 'badge-active', 'icon' => 'ph-check-circle', 'advice' => 'Keep running. Test a new hook against it with A/B Test.'];
    } elseif ($r['conversions'] === 0 && $r['spend'] >= 5) {
        $verdict = ['label' => 'Kill or refresh', 'class' => 'badge-danger', 'icon' => 'ph-flame', 'advice' => 'Spending with zero conversions — pause it and regenerate the creative.'];
    } elseif ($ctr < 0.5) {
        $verdict = ['label' => 'Weak hook', 'class' => 'badge-pending', 'icon' => 'ph-magnifying-glass', 'advice' => 'People scroll past it — write a sharper hook with the AI copywriter.'];
    } else {
        $verdict = ['label' => 'Needs a tweak', 'class' => 'badge-pending', 'icon' => 'ph-wrench', 'advice' => 'Try tighter targeting or a brighter thumbnail.'];
    }

    $audit[] = array_merge($r, [
        'ctr' => round($ctr, 2), 'cpc' => round($cpc, 2), 'cpa' => round($cpa, 2), 'roas' => round($roas, 4),
        'score' => $score, 'verdict' => $verdict,
    ]);
}
usort($audit, fn($a, $b) => $b['score'] <=> $a['score']);
$auditWinners = array_slice(array_filter($audit, fn($a) => $a['verdict']['label'] === 'Winner — scale it'), 0, 3);

$maxImpressions = !empty($dailyData) ? max(1, ...array_column($dailyData, 'impressions')) : 1;
$maxClicks      = !empty($dailyData) ? max(1, ...array_column($dailyData, 'clicks')) : 1;

$csvData = '';
if ($_GET['export'] ?? '' === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="analytics_' . $dateFrom . '_to_' . $dateTo . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Platform', 'Impressions', 'Clicks', 'Spend', 'Conversions', 'CTR', 'CPC']);
    $exportRows = db()->fetchAll(
        "SELECT m.date as stat_date, c.campaign_type, m.impressions, m.clicks, m.spend, m.conversions
         FROM campaign_metrics_daily m JOIN ad_campaigns c ON c.id = m.campaign_id
         WHERE c.user_id = ? AND m.date BETWEEN ? AND ? ORDER BY m.date",
        [$userId, $dateFrom, $dateTo]
    );
    foreach ($exportRows as $r) {
        $ctr = $r['impressions'] > 0 ? round($r['clicks'] / $r['impressions'] * 100, 2) : 0;
        $cpc = $r['clicks'] > 0 ? round($r['spend'] / $r['clicks'], 2) : 0;
        fputcsv($out, [$r['stat_date'], $r['campaign_type'], $r['impressions'], $r['clicks'], $r['spend'], $r['conversions'], $ctr . '%', '$' . $cpc]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:32px">
            <div>
                <h1 class="page-title">Analytics Dashboard</h1>
                <p class="page-subtitle" style="margin-bottom:0">Track your campaign performance</p>
            </div>
            <a href="?from=<?= e($dateFrom) ?>&to=<?= e($dateTo) ?>&network=<?= e($network) ?>&export=csv"
               class="btn-outline-dark btn-sm">
                <i class="ph ph-download-simple"></i> Export CSV
            </a>
        </div>

        <!-- Filters -->
        <div class="card-dark" style="margin-bottom:32px">
            <form method="GET" action="<?= url('analytics') ?>" style="display:flex;align-items:flex-end;flex-wrap:wrap;gap:16px">
                <div>
                    <label class="form-label">From</label>
                    <input type="date" name="from" value="<?= e($dateFrom) ?>"
                           class="form-control">
                </div>
                <div>
                    <label class="form-label">To</label>
                    <input type="date" name="to" value="<?= e($dateTo) ?>"
                           class="form-control">
                </div>
                <div>
                    <label class="form-label">Network</label>
                    <select name="network"
                            class="form-select" style="min-width:160px;">
                        <option value="">All Networks</option>
                        <?php
                        $networkOpts = ['meta' => 'Meta', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'google_ads' => 'Google Ads', 'snapchat' => 'Snapchat'];
                        foreach ($networkOpts as $val => $label):
                        ?>
                            <option value="<?= $val ?>" <?= $network === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit"
                        class="btn-accent">
                    Apply Filters
                </button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;margin-bottom:32px">
            <?php
            $summaryCards = [
                ['icon' => 'ph-eye',             'label' => 'Total Impressions',  'value' => number_format((int)$summary['total_impressions']),  'color' => 'blue'],
                ['icon' => 'ph-cursor-click',    'label' => 'Total Clicks',       'value' => number_format((int)$summary['total_clicks']),       'color' => 'purple'],
                ['icon' => 'ph-currency-dollar', 'label' => 'Total Spend',        'value' => price_fmt((float)$summary['total_spend']), 'color' => 'green'],
                ['icon' => 'ph-chart-line-up',   'label' => 'Conversions',        'value' => number_format((int)$summary['total_conversions']),  'color' => 'blue'],
            ];
            $colorMap = ['blue' => 'var(--blue)', 'purple' => 'var(--purple)', 'green' => 'var(--green)', 'orange' => 'var(--orange)'];
            foreach ($summaryCards as $sc): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background:<?= $colorMap[$sc['color']] ?>15;color:<?= $colorMap[$sc['color']] ?>">
                        <i class="ph <?= $sc['icon'] ?>"></i>
                    </div>
                    <div class="stat-label"><?= e($sc['label']) ?></div>
                    <div class="stat-value"><?= $sc['value'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Trend Chart (SVG Sparkline) + Platform Breakdown -->
        <div class="grid-main-side" style="margin-bottom:32px">
            <div class="card-dark" style="height:100%">
                <h5 style="font-weight:700;margin-bottom:16px;color:var(--fg)">Day-over-Day Trends</h5>
                <?php if (empty($dailyData)): ?>
                    <p style="font-size:13px;color:var(--fg2)">No data for the selected period.</p>
                <?php else: ?>
                    <div style="margin-bottom:16px">
                        <svg viewBox="0 0 800 200" style="width:100%" preserveAspectRatio="none">
                            <!-- Grid lines -->
                            <line x1="0" y1="50" x2="800" y2="50" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
                            <line x1="0" y1="100" x2="800" y2="100" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
                            <line x1="0" y1="150" x2="800" y2="150" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>

                            <?php
                            $count = count($dailyData);
                            $step = $count > 1 ? 800 / ($count - 1) : 800;
                            $impressionPoints = [];
                            $clickPoints = [];
                            $spendPoints = [];
                            $maxSpend = !empty($dailyData) ? max(1, ...array_column($dailyData, 'spend')) : 1;

                            foreach ($dailyData as $i => $d) {
                                $x = $i * $step;
                                $yImp = 200 - (($d['impressions'] / $maxImpressions) * 180) - 10;
                                $yCli = 200 - (($d['clicks'] / $maxClicks) * 180) - 10;
                                $ySnd = 200 - (($d['spend'] / $maxSpend) * 180) - 10;
                                $impressionPoints[] = "{$x},{$yImp}";
                                $clickPoints[] = "{$x},{$yCli}";
                                $spendPoints[] = "{$x},{$ySnd}";
                            }
                            ?>
                            <!-- Impressions line -->
                            <polyline points="<?= implode(' ', $impressionPoints) ?>" fill="none" stroke="hsl(222, 90%, 51%)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <!-- Clicks line -->
                            <polyline points="<?= implode(' ', $clickPoints) ?>" fill="none" stroke="hsl(41, 99%, 55%)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <!-- Spend line -->
                            <polyline points="<?= implode(' ', $spendPoints) ?>" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="width:12px;height:12px;border-radius:50%;background:hsl(222,90%,51%)"></span>
                            <span style="font-size:13px;color:var(--fg2)">Impressions</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="width:12px;height:12px;border-radius:50%;background:hsl(41,99%,55%)"></span>
                            <span style="font-size:13px;color:var(--fg2)">Clicks</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="width:12px;height:12px;border-radius:50%;background:#22c55e"></span>
                            <span style="font-size:13px;color:var(--fg2)">Spend</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-dark" style="height:100%">
                <h5 style="font-weight:700;margin-bottom:16px;color:var(--fg)">By Network</h5>
                <?php if (empty($platformRows)): ?>
                    <p style="font-size:13px;color:var(--fg2)">No platform data.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:16px">
                        <?php foreach ($platformRows as $pr):
                            $totalSpend = max(1, (float)$summary['total_spend']);
                            $pct = round(((float)$pr['spend'] / $totalSpend) * 100);
                        ?>
                            <div>
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                                    <span style="font-weight:500;font-size:13px;color:var(--fg);text-transform:capitalize"><?= e($pr['campaign_type']) ?></span>
                                    <span style="font-size:13px;color:var(--fg2)"><?= price_fmt((float)$pr['spend']) ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" style="width:<?= $pct ?>%;background:var(--accent)"></div>
                                </div>
                                <div style="display:flex;align-items:center;gap:16px;margin-top:4px">
                                    <small style="font-size:12px;color:var(--fg3)"><?= number_format((int)$pr['impressions']) ?> imp</small>
                                    <small style="font-size:12px;color:var(--fg3)"><?= number_format((int)$pr['clicks']) ?> clicks</small>
                                    <small style="font-size:12px;color:var(--fg3)"><?= number_format((int)$pr['conversions']) ?> conv</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Best Performing Creative -->
        <div class="card-dark" style="margin-bottom:32px">
            <h5 style="font-weight:700;margin-bottom:16px;color:var(--fg)">Best Performing Creative</h5>
            <?php if (!$bestCreative): ?>
                <p style="font-size:13px;color:var(--fg2)">No data available for this period.</p>
            <?php else: ?>
                <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
                    <?php if (!empty($bestCreative['creative_media_url'])): ?>
                        <div style="border-radius:var(--radius-sm);overflow:hidden;width:200px;height:140px">
                            <img src="<?= ASSETS_URL . '/' . e($bestCreative['creative_media_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                        </div>
                    <?php endif; ?>
                    <div style="flex:1">
                        <h6 style="font-weight:700;color:var(--fg)"><?= e($bestCreative['headline'] ?? 'No headline') ?></h6>
                        <p style="font-size:13px;color:var(--fg2);margin-bottom:12px"><?= e(mb_substr($bestCreative['primary_text'] ?? '', 0, 200)) ?></p>
                        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                            <div>
                                <p style="font-size:11px;color:var(--fg3);margin:0">Clicks</p>
                                <p style="font-weight:700;color:var(--fg);margin:0"><?= number_format((int)$bestCreative['total_clicks']) ?></p>
                            </div>
                            <div>
                                <p style="font-size:11px;color:var(--fg3);margin:0">Conversions</p>
                                <p style="font-weight:700;color:var(--fg);margin:0"><?= number_format((int)$bestCreative['total_conv']) ?></p>
                            </div>
                            <span class="badge-active">
                                <i class="ph ph-crown" style="margin-right:4px"></i>Top Performer
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Ad Audit -->
        <div class="card-dark" style="margin-bottom:32px">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
                <div>
                    <h5 style="font-weight:700;margin:0;color:var(--fg)"><i class="ph-bold ph-detective text-warning me-1"></i> Ad Audit</h5>
                    <p style="font-size:12.5px;color:var(--fg3);margin:4px 0 0">Every campaign scored 0-100 (CTR quality, conversions, volume) — fix the red ones first.</p>
                </div>
                <a href="<?= url('create-ad') ?>" class="btn-outline-dark btn-sm"><i class="ph ph-rocket-launch"></i> Launch a fresh ad</a>
            </div>

            <?php if (empty($audit)): ?>
                <div class="empty-state">
                    <i class="ph ph-seal-check" style="color:var(--accent)"></i>
                    <p style="color:var(--fg2);margin:0">No campaign activity in this period yet — launch an ad and the audit will score it here.</p>
                </div>
            <?php else: ?>
                <?php if (!empty($auditWinners)): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:18px">
                    <?php foreach ($auditWinners as $w): ?>
                    <div style="background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.3);border-radius:12px;padding:14px 16px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                            <i class="ph-fill ph-trophy" style="color:#fbbf24"></i>
                            <span style="font-size:12px;font-weight:800;color:#4ade80;text-transform:uppercase;letter-spacing:.4px">Top Performer</span>
                        </div>
                        <div style="color:var(--fg);font-weight:700;font-size:13.5px;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($w['campaign_name']) ?></div>
                        <div style="font-size:12px;color:var(--fg2)">Score <b style="color:#4ade80"><?= (int)$w['score'] ?></b> · CTR <b><?= number_format($w['ctr'], 2) ?>%</b> · <?= (int)$w['conversions'] ?> conv</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="overflow-x:auto">
                    <table class="table-dark" style="width:100%;margin:0">
                        <thead>
                            <tr>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Score</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Campaign</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">CTR</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">CPC</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Spend</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Verdict</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Next step</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit as $a): ?>
                                <tr>
                                    <td>
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;font-weight:800;font-size:14px;
                                            background:<?= $a['score'] >= 70 ? 'rgba(34,197,94,.12);color:#4ade80' : ($a['score'] >= 40 ? 'rgba(234,179,8,.12);color:#fbbf24' : 'rgba(244,63,94,.12);color:#f87171') ?>">
                                            <?= (int)$a['score'] ?>
                                        </span>
                                    </td>
                                    <td style="font-weight:500;padding:12px 0;color:var(--fg);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($a['campaign_name']) ?></td>
                                    <td style="color:var(--fg)"><?= number_format($a['ctr'], 2) ?>%</td>
                                    <td style="color:var(--fg)"><?= $a['cpc'] > 0 ? price_fmt($a['cpc']) : '—' ?></td>
                                    <td style="color:var(--fg)"><?= price_fmt((float)$a['spend']) ?></td>
                                    <td>
                                        <span class="<?= $a['verdict']['class'] ?>">
                                            <i class="ph <?= $a['verdict']['icon'] ?>" style="margin-right:4px"></i><?= e($a['verdict']['label']) ?>
                                        </span>
                                    </td>
                                    <td style="color:var(--fg2);font-size:12.5px;max-width:260px"><?= e($a['verdict']['advice']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Needs Attention -->
        <div class="card-dark">
            <h5 style="font-weight:700;margin-bottom:16px;color:var(--fg)">Needs Attention</h5>
            <?php if (empty($needsAttention)): ?>
                <div class="empty-state">
                    <i class="ph ph-check-circle" style="color:var(--green)"></i>
                    <p style="color:var(--fg2);margin:0">All campaigns are performing well!</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="table-dark" style="width:100%;margin:0">
                        <thead>
                            <tr>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Campaign</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Platform</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Spend</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Conversions</th>
                                <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);padding-bottom:12px">Recommendation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($needsAttention as $na): ?>
                                <tr>
                                    <td style="font-weight:500;padding:12px 0;color:var(--fg)"><?= e($na['campaign_name']) ?></td>
                                    <td>
                                        <span class="badge-platform"><?= e($na['campaign_type']) ?></span>
                                    </td>
                                    <td style="color:var(--fg)"><?= price_fmt((float)$na['spend']) ?></td>
                                    <td style="color:var(--fg)"><?= (int)$na['conversions'] ?></td>
                                    <td>
                                        <?php if ((int)$na['conversions'] === 0 && (float)$na['spend'] > 10): ?>
                                            <span class="badge-danger">
                                                <i class="ph ph-warning" style="margin-right:4px"></i>Pause & Review
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-pending">
                                                <i class="ph ph-arrow-counter-clockwise" style="margin-right:4px"></i>Optimize Targeting
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>