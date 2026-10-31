<?php
declare(strict_types=1);

/**
 * Server-Side CAPI & TikTok Events Bridge Ingestion Endpoint (async producer)
 * Adsbooster.net REST API
 *
 * Ingests client SDK events and enqueues them for out-of-band delivery
 * through the Zernio Ads Engine — this HTTP path performs NO external
 * platform calls and stores NO platform tokens (only Adsbooster's own
 * pixel_token bridge credential).
 */

require_once __DIR__ . '/../../config/site_setting_and_config.php';
require_once __DIR__ . '/../../modules/server_tracking.php';

// Serve client-side JS library if ?js=1
if (isset($_GET['js'])) {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo <<<JS
(function(){
  window.AdsboosterTrack = function(eventName, customData, userData) {
    var token = window._adsbooster_token || '';
    if (!token) return;
    fetch('{$baseUrl}/api/v1/capi-bridge.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        pixel_token: token,
        event_name: eventName || 'PageView',
        user_data: userData || {},
        custom_data: customData || {},
        source_url: window.location.href
      })
    }).catch(function(){});
  };
  if (window.Adsbooster && window.Adsbooster.q) {
    window.Adsbooster.q.forEach(function(call){
      if (call[0] === 'init') window._adsbooster_token = call[1];
      if (call[0] === 'track') window.AdsboosterTrack(call[1], call[2], call[3]);
    });
  }
})();
JS;
    exit;
}

// Ingest Incoming Event POST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: $_POST;

$pixelToken = $body['pixel_token'] ?? '';
$eventName = $body['event_name'] ?? 'PageView';
$userData = $body['user_data'] ?? [];
$customData = $body['custom_data'] ?? [];
$sourceUrl = $body['source_url'] ?? '';

if (empty($pixelToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing pixel_token parameter']);
    exit;
}

$res = ServerTrackingEngine::trackEvent($pixelToken, $eventName, $userData, $customData, $sourceUrl);
echo json_encode($res);
