<?php
declare(strict_types=1);

/**
 * Server-Side Conversion Tracking — Zernio-Managed Routing (Dual Gateway)
 * Adsbooster.net
 *
 * DIRECT PLATFORM API PURGE: this engine no longer contacts
 * graph.facebook.com (Meta CAPI) or business-api.tiktok.com (TikTok Events)
 * and holds NO platform access tokens. Conversion events are delivered by
 * the Zernio Ads Engine, which carries every platform credential:
 *
 *   1. Request path (page views, client SDK bridge, admin test events)
 *      persists the hashed event row and enqueues 'zernio_send_conversion'
 *      — the request answers instantly with no external HTTP.
 *   2. cron/queue_worker.php calls dispatchQueuedEvent() out-of-band, which
 *      makes ONE ThirdPartyGateway::sendConversionEvent() call per
 *      destination pixel (Meta + TikTok as routing labels) — the platforms
 *      the user attached to the pixel.
 *
 * `pixel_token` is Adsbooster's own bridge token (consumed by the SDK
 * endpoint api/v1/capi-bridge.php); it is unrelated to platform tokens.
 * Grandfathered `meta_capi_access_token` / `tiktok_events_access_token`
 * columns are dead code — nothing reads or writes them anymore.
 */

require_once __DIR__ . '/AsyncQueue.php';

class ServerTrackingEngine
{
    /**
     * Create a new conversion pixel set. Platform pixel IDs are stored as
     * ROUTING LABELS for the Zernio engine — platform tokens/credentials are
     * held (and rotated) entirely inside the Zernio gateway.
     */
    public static function createPixel(int $userId, array $params): array
    {
        $token = 'px_' . bin2hex(random_bytes(16));
        $name = trim($params['name'] ?? 'Primary Growth Pixel');
        $metaPixelId = trim($params['meta_pixel_id'] ?? '');
        $metaTestCode = trim($params['meta_test_event_code'] ?? '');
        $tiktokPixelId = trim($params['tiktok_pixel_id'] ?? '');
        $tiktokTestCode = trim($params['tiktok_test_event_code'] ?? '');

        $pixelId = db()->insert('server_tracking_pixels', [
            'user_id' => $userId,
            'pixel_token' => $token,
            'name' => $name,
            'meta_pixel_id' => $metaPixelId,
            'meta_test_event_code' => $metaTestCode,
            'tiktok_pixel_id' => $tiktokPixelId,
            'tiktok_test_event_code' => $tiktokTestCode,
            'domain_verified' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'is_active' => 1
        ]);

        return [
            'id' => $pixelId,
            'token' => $token,
            'name' => $name
        ];
    }

    /**
     * Ingest and queue one conversion event (asynchronous delivery).
     *
     * Persists the hashed event row (statuses 'queued') and enqueues a
     * zernio_send_conversion job; the CLI worker delivers it to the Zernio
     * Ads Engine out-of-band. No external platform call happens here.
     */
    public static function trackEvent(string $pixelToken, string $eventName, array $userData = [], array $customData = [], string $sourceUrl = ''): array
    {
        $pixel = db()->fetch("SELECT * FROM server_tracking_pixels WHERE pixel_token = ? AND is_active = 1", [$pixelToken]);
        if (!$pixel) {
            return ['success' => false, 'error' => 'Invalid or inactive pixel token'];
        }

        // Hash sensitive user data with SHA256 (GDPR / CAPI compliance)
        $hashedUserData = [
            'em' => !empty($userData['email']) ? hash('sha256', strtolower(trim($userData['email']))) : null,
            'ph' => !empty($userData['phone']) ? hash('sha256', preg_replace('/[^0-9]/', '', $userData['phone'])) : null,
            'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0'
        ];

        $eventId = 'evt_' . bin2hex(random_bytes(12));
        $source  = $sourceUrl ?: ($_SERVER['HTTP_REFERER'] ?? BASE_URL);

        // Event log row: statuses start as 'queued' — the queue worker writes
        // the gateway-derived honest outcomes (gateway_dispatched / simulated
        // / skipped / error:…) once delivery is confirmed.
        $logId = db()->insert('server_tracking_events', [
            'pixel_id' => (int)$pixel['id'],
            'user_id' => (int)$pixel['user_id'],
            'event_name' => $eventName,
            'event_id' => $eventId,
            'event_source_url' => $source,
            'user_data_hashed' => json_encode($hashedUserData),
            'custom_data' => json_encode($customData),
            'meta_capi_status' => 'queued',
            'tiktok_events_status' => 'queued',
        ]);

        $jobId = AsyncQueue::push(
            (int)$pixel['user_id'],
            'zernio_send_conversion',
            [
                'event_row_id' => (int)$logId,
                'event_id'     => $eventId,
            ],
            null,
            'event:' . $eventId
        );

        if ($jobId <= 0) {
            error_log('ServerTrackingEngine: queue insert failed for event ' . $eventId);
        }

        return [
            'success'       => true,
            'queued'        => $jobId > 0,
            'event_id'      => $eventId,
            'event_name'    => $eventName,
            'meta_status'   => 'queued',
            'tiktok_status' => 'queued',
            'log_id'        => (int)$logId,
            'job_id'        => $jobId,
        ];
    }

    /**
     * WORKER-SIDE delivery of one queued conversion event via the Zernio Ads
     * Engine. Called exclusively by cron/queue_worker.php (or the smoke
     * harness); never from a request loop.
     *
     * One ThirdPartyGateway::sendConversionEvent() call per configured
     * destination pixel. Status columns receive gateway-derived honest
     * values: 'gateway_dispatched (HTTP ###)' on confirmed acceptance,
     * 'simulated' when the sandbox answered, 'skipped' for destinations the
     * pixel doesn't route to, and 'error: …' on delivery failure.
     *
     * @return array{ok:bool,simulated:bool,meta:string,tiktok:string}
     */
    public static function dispatchQueuedEvent(array $eventRow, callable $log): array
    {
        $rowId = (int)$eventRow['id'];
        $pixel = db()->fetch("SELECT * FROM server_tracking_pixels WHERE id = ?", [(int)$eventRow['pixel_id']]);

        $result = ['ok' => false, 'simulated' => false, 'meta' => 'skipped (no Meta pixel routed)', 'tiktok' => 'skipped (no TikTok pixel routed)'];

        if (!$pixel || empty($pixel['is_active'])) {
            $result['meta'] = $result['tiktok'] = 'error: source pixel missing or deactivated';
            self::persistStatuses($rowId, $result);
            return $result;
        }

        $userData = json_decode((string)$eventRow['user_data_hashed'], true) ?: [];
        $customData = json_decode((string)$eventRow['custom_data'], true) ?: [];
        $eventId = (string)($eventRow['event_id'] ?? '');
        $eventName = (string)($eventRow['event_name'] ?? 'PageView');
        $sourceUrl = (string)($eventRow['event_source_url'] ?? '');

        $simulated = false;

        // ── Destination 1: Meta (routing label = meta_pixel_id) ──
        if (!empty($pixel['meta_pixel_id'])) {
            $res = ThirdPartyGateway::sendConversionEvent([
                'network'          => 'meta',
                'pixel_id'         => (string)$pixel['meta_pixel_id'],
                'event'            => $eventName,
                'event_id'         => $eventId,
                'event_source_url' => $sourceUrl,
                'user_data'        => array_filter($userData, static fn ($v) => $v !== null && $v !== ''),
                'custom_data'      => $customData,
                'test_event_code'  => (string)($pixel['meta_test_event_code'] ?? ''),
            ]);
            $status = self::deriveStatus($res, $simulated);
            $result['meta'] = $status;
            $result['simulated'] = $result['simulated'] || $status === 'simulated';
            $log('[Worker] Meta destination routed via Zernio: ' . $status);
        }

        // ── Destination 2: TikTok (routing label = tiktok_pixel_id) ──
        if (!empty($pixel['tiktok_pixel_id'])) {
            $res = ThirdPartyGateway::sendConversionEvent([
                'network'          => 'tiktok',
                'pixel_id'         => (string)$pixel['tiktok_pixel_id'],
                'event'            => $eventName,
                'event_id'         => $eventId,
                'event_source_url' => $sourceUrl,
                'user_data'        => array_filter($userData, static fn ($v) => $v !== null && $v !== ''),
                'custom_data'      => $customData,
                'test_event_code'  => (string)($pixel['tiktok_test_event_code'] ?? ''),
            ]);
            $status = self::deriveStatus($res, $simulated);
            $result['tiktok'] = $status;
            $result['simulated'] = $result['simulated'] || $status === 'simulated';
            $log('[Worker] TikTok destination routed via Zernio: ' . $status);
        }

        $result['ok'] = true;
        self::persistStatuses($rowId, $result);

        if ($simulated) {
            // Honest sandbox hold: the row explicitly reads 'simulated' and
            // the job terminal-fails so --retry-failed replays it once a
            // live Zernio key is configured. Nothing is recorded as sent.
            throw new \RuntimeException('SIMULATED: Zernio conversion-event sandbox answer (placeholder API key) — delivery held, statuses honest.');
        }

        return $result;
    }

    /**
     * Map one gateway answer to the honest status string.
     */
    private static function deriveStatus(array $res, bool &$simulated): string
    {
        if (!empty($res['data']['simulated'])) {
            $simulated = true;
            return 'simulated';
        }
        $code = (int)($res['status_code'] ?? 0);
        if ($code >= 200 && $code < 300) {
            return 'gateway_dispatched (' . $code . ')';
        }
        $msg = (string)($res['data']['message'] ?? ($res['error'] ?? 'unknown gateway answer'));
        return 'error: ' . mb_substr(trim(strip_tags($msg)), 0, 140);
    }

    private static function persistStatuses(int $rowId, array $result): void
    {
        try {
            db()->update('server_tracking_events', [
                'meta_capi_status'     => $result['meta'],
                'tiktok_events_status' => $result['tiktok'],
            ], 'id = ?', [$rowId]);
        } catch (\Throwable $e) {
            error_log('ServerTrackingEngine status update failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate HTML / JS Snippet for User's Website
     */
    public static function getTrackingSnippet(string $pixelToken): string
    {
        $endpoint = BASE_URL . '/api/v1/capi-bridge.php';
        return <<<HTML
<!-- Adsbooster.net Server-Side Conversion Routing Script (via Zernio Ads Engine) -->
<script>
!function(w,d,t,u){
  w.Adsbooster=w.Adsbooster||function(){(w.Adsbooster.q=w.Adsbooster.q||[]).push(arguments)};
  w.Adsbooster('init', '{$pixelToken}');
  w.Adsbooster('track', 'PageView');
  var s=d.createElement(t);s.async=1;s.src=u;
  var f=d.getElementsByTagName(t)[0];f.parentNode.insertBefore(s,f);
}(window,document,'script','{$endpoint}?js=1');
</script>
<!-- End Adsbooster Tracking -->
HTML;
    }
}
