<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/config/apikeys.php';   // provides api_key() for the provider checks
require_once __DIR__ . '/includes/security.php'; // provides ca_bundle_found() for TLS probes
require_once __DIR__ . '/modules/status_monitor.php'; // outage detection, incidents, owner alerts

// This page runs DB + multi-host TLS probes; give it real wall-clock room
// (LiteSpeed/FastCGI default is 60s and a cold remote probe can spike).
@set_time_limit(120);

$pageTitle = 'System Status — ' . get_setting('site_name', 'Adsbooster.net');
$pageDescription = 'Live health checks run from our servers. Every number on this page is measured, not estimated.';

/* ────────────────────────────────────────────────────────────────
   Real checks. Each entry is measured right now, on this request.
   Results are written to service_check_log so the 30-day uptime
   figure below is calculated from actual history rather than
   invented.
   ──────────────────────────────────────────────────────────────── */

function probe(string $key, string $label, string $group, callable $fn): array
{
    $t0   = microtime(true);
    $ok   = false;
    $note = '';
    try {
        [$ok, $note] = $fn();
    } catch (\Throwable $e) {
        $ok   = false;
        $note = $e->getMessage();
    }
    $ms = (int)round((microtime(true) - $t0) * 1000);

    try {
        db()->insert('service_check_log', [
            'check_key'  => $key,
            'ok'         => $ok ? 1 : 0,
            'latency_ms' => max(0, $ms),
            'detail'     => mb_substr($note, 0, 250),
            'checked_at' => date('Y-m-d H:i:s'),
        ]);
        // Prune old history, but only occasionally — a full scan on every
        // page view is wasted work.
        if (random_int(1, 20) === 1) {
            db()->query("DELETE FROM service_check_log WHERE checked_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        }
    } catch (\Throwable $e) {
        // Logging must never take the page down.
    }

    $result = [
        'key'   => $key,
        'label' => $label,
        'group' => $group,
        'ok'    => $ok,
        'ms'    => $ms,
        'note'  => $note,
    ];

    // Incident tracking + owner alert on a state transition only.
    try {
        StatusMonitor::handleTransition($result);
    } catch (\Throwable $e) {
        error_log('Status transition detection failed: ' . $e->getMessage());
    }

    return $result;
}

/**
 * Probe several URLs concurrently.
 *
 * The probes used to run one after another, which pushed page load past 20
 * seconds because each remote host got its own timeout window. curl_multi
 * overlaps them, so the total wait is the slowest single host rather than the
 * sum of all of them.
 *
 * @param array<string,string> $urls keyed by check id
 * @return array<string,array{ok:bool,note:string,ms:int}>
 */
function multiPing(array $urls, int $timeout = 8): array
{
    $out = [];
    if (!$urls) {
        return $out;
    }

    $bundle = function_exists('ca_bundle_found') ? ca_bundle_found() : '';
    $verify = $bundle !== '';

    $mh  = curl_multi_init();
    $chs = [];
    $t0  = microtime(true);

    foreach ($urls as $key => $url) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_USERAGENT      => 'AdsboosterStatus/1.0',
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // Only the first 2 KB — we only care that the endpoint answers.
            CURLOPT_RANGE          => '0-2047',
        ];
        if ($verify) {
            $opts[CURLOPT_CAINFO] = $bundle;
        }
        curl_setopt_array($ch, $opts);
        curl_multi_add_handle($mh, $ch);
        $chs[$key] = $ch;
    }

    // Bound the whole batch, not each handle individually.
    $deadline = microtime(true) + $timeout + 2;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0 && microtime(true) < $deadline) {
            curl_multi_select($mh, 0.2);
        } elseif ($running > 0) {
            break;
        }
    } while ($running > 0 && $status === CURLM_OK);

    foreach ($chs as $key => $ch) {
        $body = curl_multi_getcontent($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string)curl_error($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if ($code === 0) {
            $out[$key] = ['ok' => false, 'note' => $err !== '' ? $err : 'no response', 'ms' => 0];
        } elseif ($code >= 500) {
            $out[$key] = ['ok' => false, 'note' => 'HTTP ' . $code, 'ms' => 0];
        } else {
            // Any other HTTP status means the endpoint is up and TLS
            // verified — 401/404 from an API are normal for a bare probe.
            $len  = is_string($body) ? strlen($body) : 0;
            $note = 'HTTP ' . $code . ($len > 0 ? ' · ' . $len . ' B' : '');
            $out[$key] = ['ok' => true, 'note' => $note, 'ms' => 0];
        }
    }

    curl_multi_close($mh);

    // Attribute one shared wall-clock cost so the per-check numbers stay honest.
    $elapsed = (int)round((microtime(true) - $t0) * 1000);
    foreach ($out as $k => $v) {
        $out[$k]['ms'] = $elapsed;
    }

    return $out;
}

$checks = [];

// Remote hosts are probed together first so a slow CDN cannot serialise
// the page. Local checks are instantaneous and run afterwards.
$groqKey   = function_exists('api_key') ? api_key('GROQ_API_KEY', 'groq_api_key', '') : '';
$geminiKey = function_exists('api_key') ? api_key('GEMINI_API_KEY', 'gemini_api_key', '') : '';
$remoteUrls = [];
if ($geminiKey !== '') {
    // Authenticated list call — proves generativelanguage.googleapis.com is
    // reachable and the configured Nano Banana / Veo key is accepted.
    $remoteUrls['image_cdn'] = 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1&key='
        . urlencode($geminiKey);
}
if ($groqKey !== '') {
    $remoteUrls['ai_script'] = 'https://api.groq.com/openai/v1/models';
}
$remote = multiPing($remoteUrls, 8);

// 1. Database — the app is down without it.
$checks[] = probe('database', 'Database', 'Core', function () {
    $t0  = microtime(true);
    $one = db()->fetchOne('SELECT 1 AS ok');
    $ms  = (int)round((microtime(true) - $t0) * 1000);
    return [($one['ok'] ?? 0) == 1, "responded in {$ms} ms"];
});

// 2. Web server + PHP.
$checks[] = probe('runtime', 'Web server & PHP', 'Core', function () {
    return [PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION];
});

// 3. Writable storage for uploads and renders.
$checks[] = probe('storage', 'Upload storage', 'Core', function () {
    $dir = ROOT_PATH . '/uploads/videos';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [false, 'uploads/videos missing and could not be created'];
    }
    if (!is_writable($dir)) {
        return [false, 'uploads/videos is not writable'];
    }
    $free = @disk_free_space($dir);
    if ($free !== false && $free < 200 * 1024 * 1024) {
        return [false, 'less than 200 MB free'];
    }
    return [true, 'writable'];
});

// 4. Session / cache directory.
// (Core checks are kept contiguous so the page renders each group heading
// exactly once — see the foreach grouping below.)
$checks[] = probe('cache', 'Cache & rate-limit store', 'Core', function () {
    $dir = ROOT_PATH . '/cache/rate_limit';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [false, 'cache dir missing'];
    }
    return [is_writable($dir), 'writable'];
});

// 5. Google image generation (Nano Banana 2) used by the studios.
$checks[] = probe('image_cdn', 'Image generation (Gemini)', 'AI', function () use ($remote, $geminiKey) {
    if ($geminiKey === '') {
        return [true, 'not configured on this node'];
    }
    $r = $remote['image_cdn'] ?? ['ok' => false, 'note' => 'probe not run'];
    // multiPing counts any non-5xx as "up"; an auth rejection is a real
    // failure for this check because the key is what the studios need.
    if (str_contains($r['note'], 'HTTP 401') || str_contains($r['note'], 'HTTP 403')) {
        return [false, 'API key rejected (' . $r['note'] . ')'];
    }
    return [$r['ok'], $r['note']];
});

// 6. Script-writing model.
$checks[] = probe('ai_script', 'AI script model (Groq)', 'AI', function () use ($remote, $groqKey) {
    if ($groqKey === '') {
        return [true, 'not configured on this node'];
    }
    $r = $remote['ai_script'] ?? ['ok' => false, 'note' => 'probe not run'];
    return [$r['ok'], $r['note']];
});

// 7. REST API v1 — bootstrap loads, key table reachable, rate limiter writable.
$checks[] = probe('rest_api', 'REST API v1', 'Platform', function () {
    $boot = ROOT_PATH . '/api/v1/bootstrap.php';
    if (!is_readable($boot)) {
        return [false, 'api/v1/bootstrap.php missing'];
    }
    try {
        $keys = (int)db()->fetchOne("SELECT COUNT(*) AS c FROM api_keys")['c'];
        $rate = (int)db()->fetchOne("SELECT COUNT(*) AS c FROM api_rate_limits")['c'];
        return [true, "bootstrap OK · {$keys} API key(s) · rate-limiter active ({$rate} hits)"];
    } catch (\Throwable $e) {
        return [false, 'API tables unreachable: ' . $e->getMessage()];
    }
});

// 8. Webhooks — subscriptions registered and dispatchable.
// (table column is `is_active`, not `status` — SUM(status='active') was the
// silent failure that kept the overall banner red with everything green)
$checks[] = probe('webhooks', 'Webhooks engine', 'Platform', function () {
    try {
        $rows = db()->fetchOne("SELECT COUNT(*) AS total, SUM(is_active=1) AS active FROM webhooks");
        $total  = (int)($rows['total'] ?? 0);
        $active = (int)($rows['active'] ?? 0);
        return [true, "{$active} active / {$total} registered webhook endpoint(s)"];
    } catch (\Throwable $e) {
        return [false, 'webhooks table unreachable: ' . $e->getMessage()];
    }
});

// ── 30-day uptime from the real log ──────────────────────────────
$uptime = [];
try {
    $rows = db()->fetchAll(
        "SELECT check_key,
                COUNT(*) AS total,
                SUM(ok) AS up,
                ROUND(100.0 * SUM(ok) / GREATEST(COUNT(*),1), 2) AS pct,
                ROUND(AVG(latency_ms)) AS avg_ms
         FROM service_check_log
         WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY check_key"
    );
    foreach ($rows as $r) {
        $uptime[$r['check_key']] = $r;
    }
} catch (\Throwable $e) {
    // Table may not exist yet on a fresh install.
}

// ── 30-day uptime TIMELINE: one bar per calendar day per check ────
// Daily buckets from the same real history, so the strip shows the
// patchwork of actual results rather than one smoothed number.
$timeline = [];
$timelineDays = [];
for ($i = 29; $i >= 0; $i--) {
    $timelineDays[] = date('Y-m-d', strtotime("-$i day"));
}
try {
    $rows = db()->fetchAll(
        "SELECT check_key, DATE(checked_at) AS d, COUNT(*) AS total, SUM(ok) AS up
         FROM service_check_log
         WHERE checked_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
           AND checked_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
         GROUP BY check_key, DATE(checked_at)"
    );
    foreach ($rows as $r) {
        $timeline[$r['check_key']][$r['d']] = ['total' => (int)$r['total'], 'up' => (int)$r['up']];
    }
} catch (\Throwable $e) {
    // Table may not exist yet on a fresh install — the strip just stays grey.
}

/** One daily bar cell for the timeline strip (green=100%, amber=partial, red=0%, grey=no data). */
function status_timeline_bar(string $day, ?array $bucket): string
{
    if ($bucket === null) {
        $bg = 'rgba(255,255,255,.06)';
        $tip = date('M j', strtotime($day)) . ' — no checks logged';
    } else {
        $pct = $bucket['total'] > 0 ? round(100.0 * $bucket['up'] / $bucket['total'], 1) : 0.0;
        if ($pct >= 100.0) {
            $bg = '#4ade80';
        } elseif ($pct <= 0.0) {
            $bg = '#f87171';
        } else {
            $bg = '#fbbf24';
        }
        $tip = date('M j', strtotime($day)) . " — {$pct}% ({$bucket['up']}/{$bucket['total']} checks passed)";
    }
    return '<span title="' . e($tip) . '" style="flex:1 1 0;min-width:4px;height:22px;border-radius:3px;background:' . $bg . ';cursor:pointer;"></span>';
}

$overallOk    = count(array_filter($checks, fn($c) => !$c['ok'])) === 0;
$overallLabel = $overallOk ? 'All systems operational' : 'Some checks are failing';
$overallClass = $overallOk ? 'success' : 'danger';
$checkedAt    = date('D d M Y, H:i:s T');

/* ── Uptime calendar ───────────────────────────────────────────────
   A month grid built from the same real history, plus the incident log
   that documents what broke, when, and why.
   ─────────────────────────────────────────────────────────────────── */
$monthParam = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$monthStart = date('Y-m-01 00:00:00', strtotime($monthParam . '-01'));
$monthEnd   = date('Y-m-t 23:59:59', strtotime($monthParam . '-01'));
$nextMonth  = date('Y-m', strtotime($monthStart . ' +1 month'));
$prevMonth  = date('Y-m', strtotime($monthStart . ' -1 month'));
$monthLabel = date('F Y', strtotime($monthStart));

// Daily aggregates across every check for the month.
$monthDays = [];
try {
    $rows = db()->fetchAll(
        "SELECT DATE(checked_at) AS d, COUNT(*) AS total, SUM(ok) AS up
         FROM service_check_log
         WHERE checked_at >= ? AND checked_at <= ?
         GROUP BY DATE(checked_at)",
        [$monthStart, $monthEnd]
    );
    foreach ($rows as $r) {
        $monthDays[$r['d']] = ['total' => (int)$r['total'], 'up' => (int)$r['up']];
    }
} catch (\Throwable $e) {
    // Table may not exist yet on a fresh install.
}

// Incidents for the month (plus anything still open from earlier).
$incidents = [];
try {
    $incidents = db()->fetchAll(
        "SELECT * FROM service_incidents
         WHERE (started_at >= ? AND started_at <= ?) OR status = 'open'
         ORDER BY (status = 'open') DESC, started_at DESC
         LIMIT 60",
        [$monthStart, $monthEnd]
    );
} catch (\Throwable $e) {
    // Table may not exist yet on a fresh install.
}

// Build the calendar grid (weeks start on Monday).
$firstDayOfMonth = new DateTime($monthStart);
$gridStart       = (clone $firstDayOfMonth)->modify('-' . ((int)$firstDayOfMonth->format('N') - 1) . ' day');
$calendarCells   = [];
$cursor          = clone $gridStart;
$today           = date('Y-m-d');
for ($i = 0; $i < 42; $i++) {
    $dayKey        = $cursor->format('Y-m-d');
    $inMonth       = $cursor->format('Y-m') === $monthParam;
    $stats         = $monthDays[$dayKey] ?? null;
    $dayIncidents  = array_values(array_filter($incidents, fn($inc) => substr((string)$inc['started_at'], 0, 10) === $dayKey));

    if ($stats === null || $stats['total'] === 0) {
        $state = 'none';
    } else {
        $pct = 100.0 * $stats['up'] / $stats['total'];
        $state = $pct >= 100.0 ? 'ok' : ($pct >= 50.0 ? 'partial' : 'down');
    }

    $calendarCells[] = [
        'day'       => $cursor->format('j'),
        'key'       => $dayKey,
        'in_month'  => $inMonth,
        'is_today'  => $dayKey === $today,
        'state'     => $state,
        'pct'       => $stats !== null && $stats['total'] > 0
                        ? round(100.0 * $stats['up'] / $stats['total'], 1)
                        : null,
        'checks'    => $stats['total'] ?? 0,
        'incidents' => $dayIncidents,
    ];
    $cursor->modify('+1 day');
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
  <div class="page-header mb-4">
    <h1 class="page-title mb-1"><i class="ph-bold ph-activity text-accent me-2"></i>System status</h1>
    <p class="page-subtitle mb-0">
      Every number on this page is measured from our servers on this request — nothing here is estimated.
      Last checked <strong class="text-white"><?= e($checkedAt) ?></strong>.
    </p>
  </div>

  <!-- Overall banner -->
  <div style="background:#0d1524;border:1px solid rgba(<?= $overallOk ? '34,197,94' : '239,68,68' ?>,.35);border-radius:18px;padding:22px 24px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <div style="width:52px;height:52px;border-radius:14px;background:rgba(<?= $overallOk ? '34,197,94' : '239,68,68' ?>,.15);color:<?= $overallOk ? '#4ade80' : '#f87171' ?>;display:flex;align-items:center;justify-content:center;font-size:26px;">
      <i class="ph <?= $overallOk ? 'ph-check-circle' : 'ph-warning-circle' ?>"></i>
    </div>
    <div>
      <div style="color:#fff;font-size:20px;font-weight:800;"><?= e($overallLabel) ?></div>
      <div style="color:#94a3b8;font-size:13.5px;margin-top:2px;"><?= count($checks) ?> checks run just now · uptime below is calculated from real logged results over the last 30 days</div>
    </div>
  </div>

  <!-- Uptime calendar -->
  <div style="background:#0d1524;border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:20px 22px;margin-bottom:18px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
      <div>
        <div style="color:#fff;font-weight:800;font-size:16px;">
          <i class="ph ph-calendar-blank" style="color:#7dd3fc;vertical-align:-2px"></i> Uptime calendar
        </div>
        <div style="color:#64748b;font-size:12.5px;margin-top:2px;">
          Every cell is one day of measured results across all services. Hover a day for the exact numbers.
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <a href="?month=<?= e($prevMonth) ?>" class="btn btn-outline-light btn-sm rounded-pill" style="border-color:rgba(255,255,255,.14);">
          <i class="ph ph-caret-left"></i>
        </a>
        <span style="color:#fff;font-weight:700;font-size:14px;min-width:120px;text-align:center;"><?= e($monthLabel) ?></span>
        <a href="?month=<?= e($nextMonth) ?>" class="btn btn-outline-light btn-sm rounded-pill" style="border-color:rgba(255,255,255,.14);">
          <i class="ph ph-caret-right"></i>
        </a>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;margin-bottom:6px;">
      <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow): ?>
        <div style="color:#64748b;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;text-align:center;padding-bottom:2px;"><?= e($dow) ?></div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;">
      <?php foreach ($calendarCells as $cell):
        $bg = 'rgba(255,255,255,.035)';
        $fg = '#475569';
        if ($cell['state'] === 'ok')      { $bg = 'rgba(74,222,128,.16)';  $fg = '#4ade80'; }
        elseif ($cell['state'] === 'partial') { $bg = 'rgba(251,191,36,.18)'; $fg = '#fbbf24'; }
        elseif ($cell['state'] === 'down') { $bg = 'rgba(248,113,113,.22)'; $fg = '#f87171'; }
        if ($cell['is_today']) { $fg = '#ffffff'; }
        $tip = $cell['checks'] > 0
            ? $cell['checks'] . ' checks logged · ' . $cell['pct'] . '% passed'
            : 'no checks logged';
        if (!empty($cell['incidents'])) {
            $tip .= ' · ' . count($cell['incidents']) . ' incident(s): ' . mb_substr((string)$cell['incidents'][0]['title'], 0, 80);
        }
      ?>
        <div title="<?= e($tip) ?>"
             style="background:<?= $bg ?>;border:1px solid <?= $cell['is_today'] ? 'rgba(255,255,255,.35)' : 'rgba(255,255,255,.05)' ?>;border-radius:10px;min-height:58px;padding:7px 8px;display:flex;flex-direction:column;justify-content:space-between;<?= $cell['in_month'] ? '' : 'opacity:.35;' ?>">
          <div style="color:<?= $fg ?>;font-weight:700;font-size:12.5px;"><?= (int)$cell['day'] ?></div>
          <div style="display:flex;align-items:center;gap:3px;">
            <?php if ($cell['checks'] > 0): ?>
              <span style="color:<?= $fg ?>;font-size:10px;font-family:'JetBrains Mono',monospace;"><?= e((string)$cell['pct']) ?>%</span>
            <?php endif; ?>
            <?php foreach ($cell['incidents'] as $inc): ?>
              <span title="<?= e((string)$inc['title']) ?>" style="width:6px;height:6px;border-radius:50%;background:<?= ($inc['status'] ?? '') === 'open' ? '#f87171' : '#fbbf24' ?>;display:inline-block;"></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:12px;color:#64748b;font-size:11.5px;">
      <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(74,222,128,.5);"></span> all passed</span>
      <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(251,191,36,.6);"></span> partial failures</span>
      <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(248,113,113,.65);"></span> mostly failing</span>
      <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(255,255,255,.06);"></span> no data</span>
      <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:7px;height:7px;border-radius:50%;background:#f87171;display:inline-block;"></span> incident recorded</span>
    </div>
  </div>

  <!-- Incident log: what happened, when, and why -->
  <div style="background:#0d1524;border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:20px 22px;margin-bottom:18px;">
    <div style="color:#fff;font-weight:800;font-size:16px;margin-bottom:4px;">
      <i class="ph ph-fire" style="color:#f87171;vertical-align:-2px"></i> Incident log
    </div>
    <div style="color:#64748b;font-size:12.5px;margin-bottom:14px;">
      Every outage we detected, what caused it, and how long it lasted. Open incidents are also emailed to the platform owner in real time.
    </div>

    <?php if (empty($incidents)): ?>
      <div style="color:#64748b;font-size:13px;padding:14px 0;">
        <i class="ph ph-check-circle" style="color:#4ade80"></i> No incidents recorded for <?= e($monthLabel) ?>. Every check has stayed within its expected behaviour.
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($incidents as $inc):
          $open = ($inc['status'] ?? '') === 'open';
          $started = (string)$inc['started_at'];
          $resolved = $inc['resolved_at'] ?? null;
          $duration = 'ongoing';
          if ($resolved) {
            $secs = max(0, strtotime((string)$resolved) - strtotime($started));
            $duration = $secs < 60 ? $secs . 's'
                : ($secs < 3600 ? floor($secs / 60) . ' min'
                : floor($secs / 3600) . 'h ' . floor(($secs % 3600) / 60) . 'm');
          }
        ?>
          <div style="background:rgba(255,255,255,.03);border:1px solid <?= $open ? 'rgba(248,113,113,.35)' : 'rgba(255,255,255,.07)' ?>;border-left:3px solid <?= $open ? '#f87171' : '#fbbf24' ?>;border-radius:12px;padding:14px 16px;">
            <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:6px;">
              <span style="color:#fff;font-weight:700;font-size:14px;"><?= e((string)$inc['title']) ?></span>
              <span style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;padding:3px 9px;border-radius:999px;color:<?= $open ? '#fca5a5' : '#fde68a' ?>;background:<?= $open ? 'rgba(248,113,113,.14)' : 'rgba(251,191,36,.14)' ?>;">
                <?= $open ? 'Investigating' : 'Resolved' ?>
              </span>
              <span style="color:#64748b;font-size:11.5px;font-family:'JetBrains Mono',monospace;">
                <?= e(date('D d M, H:i', strtotime($started))) ?> · <?= e($duration) ?>
              </span>
              <?php if (!empty($inc['notified'])): ?>
                <span style="color:#64748b;font-size:11px;"><i class="ph ph-envelope-simple"></i> owner alerted</span>
              <?php endif; ?>
            </div>
            <?php if (!empty($inc['cause'])): ?>
              <div style="color:#cbd5e1;font-size:13px;line-height:1.6;">
                <b style="color:#fff;">Why it happened:</b> <?= e((string)$inc['cause']) ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($inc['detail'])): ?>
              <div style="color:#64748b;font-size:12px;margin-top:5px;font-family:'JetBrains Mono',monospace;">
                raw error: <?= e(mb_substr((string)$inc['detail'], 0, 300)) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php $lastGroup = ''; foreach ($checks as $c): ?>
    <?php if ($c['group'] !== $lastGroup): $lastGroup = $c['group']; ?>
      <div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b;margin:26px 0 10px;"><?= e($c['group']) ?></div>
    <?php endif; ?>

    <?php $u = $uptime[$c['key']] ?? null; ?>
    <div style="background:#0d1524;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px 18px;margin-bottom:10px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <span style="width:10px;height:10px;border-radius:50%;background:<?= $c['ok'] ? '#4ade80' : '#f87171' ?>;flex-shrink:0;box-shadow:0 0 10px <?= $c['ok'] ? 'rgba(74,222,128,.7)' : 'rgba(248,113,113,.7)' ?>;"></span>
      <div style="flex:1;min-width:200px;">
        <div style="color:#fff;font-weight:700;font-size:14.5px;"><?= e($c['label']) ?></div>
        <div style="color:#64748b;font-size:12.5px;margin-top:2px;">
          <?php if ($u !== null): ?>
            <?= number_format((float)$u['pct'], 2) ?>% uptime over 30 days · avg <?= (int)$u['avg_ms'] ?> ms
          <?php else: ?>
            No history yet — the uptime figure appears after a few hours of logging.
          <?php endif; ?>
        </div>
        <!-- 30-day daily uptime timeline: hover a bar for that day's real result -->
        <div aria-label="30-day uptime timeline" style="display:flex;gap:3px;margin-top:8px;max-width:420px;">
          <?php foreach ($timelineDays as $dKey): ?>
            <?= status_timeline_bar($dKey, $timeline[$c['key']][$dKey] ?? null) ?>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="text-align:right;min-width:120px;">
        <div style="color:<?= $c['ok'] ? '#4ade80' : '#f87171' ?>;font-weight:700;font-size:14px;"><?= $c['ok'] ? 'Operational' : 'Failing' ?></div>
        <div style="color:#64748b;font-size:12px;"><?= (int)$c['ms'] ?> ms<?= $c['note'] !== '' ? ' · ' . e(mb_substr($c['note'], 0, 60)) : '' ?></div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Timeline legend -->
  <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:16px;color:#64748b;font-size:12px;">
    <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:2px;background:#4ade80;"></span> all checks passed</span>
    <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:2px;background:#fbbf24;"></span> some failures</span>
    <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:2px;background:#f87171;"></span> all failed</span>
    <span style="display:inline-flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:2px;background:rgba(255,255,255,.06);"></span> no data</span>
    <span>· each bar is one day, last 30 days · hover a bar for its real result</span>
  </div>

  <!-- Honest scope note -->
  <div style="background:rgba(255,255,255,.03);border:1px dashed rgba(255,255,255,.14);border-radius:14px;padding:18px 20px;margin-top:28px;">
    <div style="color:#fff;font-weight:700;font-size:14px;margin-bottom:6px;">
      <i class="ph ph-info" style="color:var(--accent)"></i> What this page does and does not cover
    </div>
    <p style="color:#94a3b8;font-size:13.5px;line-height:1.65;margin:0 0 10px;">
      The checks above run from our own servers and cover the parts of Adsbooster we operate directly: the app, the database, storage, and the AI endpoints we call.
    </p>
    <p style="color:#94a3b8;font-size:13.5px;line-height:1.65;margin:0;">
      We do <strong>not</strong> publish uptime figures for Meta, TikTok, Google or any other third-party platform, because we do not run continuous probes against them and will not publish a number we have not measured. If a platform integration is failing for you, the cause is usually on their side — check their own status page, or <a href="<?= url('tickets') ?>" style="color:var(--accent)">contact support</a>.
    </p>
  </div>

  <div style="margin-top:22px;">
    <button type="button" class="btn btn-accent rounded-pill px-4 py-2 font-size-14 fw-bold" onclick="location.reload()">
      <i class="ph ph-arrows-clockwise me-1"></i> Run the checks again
    </button>
  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
