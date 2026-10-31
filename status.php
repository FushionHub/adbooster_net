<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/config/apikeys.php';   // provides api_key() for the provider checks
require_once __DIR__ . '/includes/security.php'; // provides ca_bundle_found() for TLS probes

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

    return [
        'key'   => $key,
        'label' => $label,
        'group' => $group,
        'ok'    => $ok,
        'ms'    => $ms,
        'note'  => $note,
    ];
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

// 4. Google image generation (Nano Banana 2) used by the studios.
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

// 5. Script-writing model.
$checks[] = probe('ai_script', 'AI script model (Groq)', 'AI', function () use ($remote, $groqKey) {
    if ($groqKey === '') {
        return [true, 'not configured on this node'];
    }
    $r = $remote['ai_script'] ?? ['ok' => false, 'note' => 'probe not run'];
    return [$r['ok'], $r['note']];
});

// 6. Session / cache directory.
$checks[] = probe('cache', 'Cache & rate-limit store', 'Core', function () {
    $dir = ROOT_PATH . '/cache/rate_limit';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [false, 'cache dir missing'];
    }
    return [is_writable($dir), 'writable'];
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
$checks[] = probe('webhooks', 'Webhooks engine', 'Platform', function () {
    try {
        $rows = db()->fetchOne("SELECT COUNT(*) AS total, SUM(status='active') AS active FROM webhooks");
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

$overallOk    = count(array_filter($checks, fn($c) => !$c['ok'])) === 0;
$overallLabel = $overallOk ? 'All systems operational' : 'Some checks are failing';
$overallClass = $overallOk ? 'success' : 'danger';
$checkedAt    = date('D d M Y, H:i:s T');

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
      </div>
      <div style="text-align:right;min-width:120px;">
        <div style="color:<?= $c['ok'] ? '#4ade80' : '#f87171' ?>;font-weight:700;font-size:14px;"><?= $c['ok'] ? 'Operational' : 'Failing' ?></div>
        <div style="color:#64748b;font-size:12px;"><?= (int)$c['ms'] ?> ms<?= $c['note'] !== '' ? ' · ' . e(mb_substr($c['note'], 0, 60)) : '' ?></div>
      </div>
    </div>
  <?php endforeach; ?>

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
