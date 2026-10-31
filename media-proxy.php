<?php
/**
 * Same-origin media proxy.
 *
 * The image editor draws generated creatives onto a <canvas>. A cross-origin
 * image without `Access-Control-Allow-Origin` taints the canvas, which makes
 * canvas.toDataURL() throw — so Download would silently fail. Fetching through
 * this endpoint re-serves the bytes same-origin (and with ACAO: *), keeping the
 * canvas clean.
 *
 * Security posture:
 *   - HTTPS only.
 *   - Host allowlist — no arbitrary SSRF.
 *   - Size and time capped.
 *   - Auth required.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/security.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Not signed in.']);
    exit;
}

$raw = (string)($_GET['url'] ?? '');
if ($raw === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Missing url parameter.']);
    exit;
}

$parsed = parse_url($raw);
$host   = strtolower((string)($parsed['host'] ?? ''));
$scheme = strtolower((string)($parsed['scheme'] ?? ''));

$allowedHosts = [
    'images.unsplash.com',
    'upload.wikimedia.org',
    'cdn.jsdelivr.net',
];

$isSelf = ($host !== '' && $host === strtolower((string)parse_url(BASE_URL, PHP_URL_HOST)));

$hostAllowed = in_array($host, $allowedHosts, true) || $isSelf;

if ($scheme !== 'https' && !($isSelf && $scheme === 'http')) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Only https image URLs can be proxied.']);
    exit;
}

if (!$hostAllowed) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'That host is not on the proxy allowlist.']);
    exit;
}

$ch = curl_init($raw);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 12,
    CURLOPT_TIMEOUT        => 75,
    CURLOPT_MAXREDIRS      => 0,
    CURLOPT_RANGE          => '0-8388607',
    CURLOPT_HTTPHEADER     => ['Accept: image/*'],
]);
// TLS options applied after the array — spreading ca_curl_opts() renumbers
// its CURLOPT_* integer keys and makes cURL throw. See includes/security.php.
ca_curl_setopt($ch);
$bytes = curl_exec($ch);
$err   = curl_error($ch);
$code  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($bytes === false || $err !== '') {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Upstream fetch failed.']);
    error_log('[media-proxy] fetch failed host=' . $host . ' err=' . $err);
    exit;
}

// Upstream refused (image CDNs often use 402 for rate limits) — pass the
// status through as JSON so the UI can show a real reason instead of a
// broken image.
if ($code >= 400) {
    http_response_code($code === 402 ? 429 : 502);
    header('Content-Type: application/json');
    echo json_encode([
        'ok'    => false,
        'error' => $code === 402
            ? 'The image service is rate-limited right now. Wait a few seconds and try again.'
            : 'Upstream returned HTTP ' . $code . '.',
    ]);
    exit;
}

if ($ctype !== '' && stripos($ctype, 'image/') !== 0) {
    http_response_code(415);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'That URL did not return an image.']);
    exit;
}

header('Content-Type: ' . ($ctype !== '' ? $ctype : 'image/jpeg'));
header('Access-Control-Allow-Origin: *');
header('Content-Length: ' . strlen((string)$bytes));
header('Cache-Control: public, max-age=86400, immutable');
echo $bytes;
