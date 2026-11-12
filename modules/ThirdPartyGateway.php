<?php
declare(strict_types=1);

/**
 * Unified Third-Party Service Gateway — Adsbooster.net
 *
 * 1. Bundle.social API (https://api.bundle.social, /api/v1/..., x-api-key)
 *    -> Organic Social Media Management
 *    (Account OAuth connect, Post Scheduling, Multi-Platform Publishing,
 *     Analytics, Comment Moderation)
 * 2. Zernio API (https://zernio.com/api/v1, Bearer key)
 *    -> Paid Advertising Management + Personal Calendar
 *    (Ads connect GET /v1/connect/{platform}/ads, POST /v1/ads/create,
 *     campaign status PUT /v1/ads/campaigns/{id}/status,
 *     analytics GET /v1/ads/campaigns/{id}/analytics,
 *     ad accounts GET /v1/ads/accounts, budget PUT /v1/ads/campaigns/{id},
 *     server-side conversion events POST /v1/ads/conversions,
 *     calendar POST /v1/calendar/events)
 *
 * SPEC SOURCES (verified against the providers' live docs):
 *  - Bundle.social: https://api.bundle.social/swagger-json + info.bundle.social
 *  - Zernio: https://zernio.com/openapi.json (components) + docs.zernio.com
 *
 * NO DIRECT PLATFORM API REMNANTS: this class is the ONLY external-call
 * surface outside cron/queue_worker.php, and no segment ever contacts
 * platform endpoints (graph.facebook.com, business-api.tiktok.com, …)
 * directly — the gateways hold all platform credentials.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../config/apikeys.php';

class ThirdPartyGateway
{
    /** Canonical provider base URLs (real, documented endpoints). */
    private const BUNDLE_BASE = 'https://api.bundle.social';
    private const BUNDLE_PREFIX = 'api/v1/';
    private const ZERNIO_BASE = 'https://zernio.com/api/v1';

    /** Bundle.social platform type enum (POST /api/v1/social-account/connect). */
    private const BUNDLE_TYPES = [
        'tiktok' => 'TIKTOK', 'youtube' => 'YOUTUBE', 'instagram' => 'INSTAGRAM',
        'facebook' => 'FACEBOOK', 'twitter' => 'TWITTER', 'threads' => 'THREADS',
        'linkedin' => 'LINKEDIN', 'pinterest' => 'PINTEREST', 'reddit' => 'REDDIT',
        'mastodon' => 'MASTODON', 'discord' => 'DISCORD', 'slack' => 'SLACK',
        'bluesky' => 'BLUESKY', 'snapchat' => 'SNAPCHAT',
    ];

    /** Per-request Bundle team cache (server-side persisted; resolved lazily). */
    private static ?string $bundleTeamId = null;

    /**
     * Retrieve credentials for third-party gateways from MySQL or config fallbacks
     */
    public static function getGatewayConfig(string $provider): array
    {
        $provider = strtolower(trim($provider));

        // 1. Try third_party_gateways table first (API key only; the base URL
        //    is forced to the provider's canonical value so stale DB rows can
        //    never point requests at retired invented endpoints).
        $apiKey = '';
        try {
            $stmt = DB::getConnection()->prepare(
                "SELECT api_key, is_active FROM third_party_gateways WHERE provider_key = ? LIMIT 1"
            );
            $stmt->execute([$provider]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            // A seeded placeholder row (e.g. 'YOUR_ZERNIO_API_KEY') must NOT
            // shadow a real key: treat placeholders as absent so the
            // constants / site_settings fallbacks below are consulted.
            // Otherwise production silently drops into sandbox mode and
            // Zernio "redirects" to the simulated .local endpoint.
            if ($config && !empty($config['is_active'])) {
                $candidate = (string)$config['api_key'];
                if ($candidate !== '' && !self::isPlaceholderKey($candidate)) {
                    $apiKey = $candidate;
                }
            }
        } catch (\Throwable $e) {
            // Table might not exist yet or DB unreachable — fall through
        }

        // 2. Fall back to site_settings or constants
        if ($provider === 'bundle_social') {
            if ($apiKey === '') {
                $apiKey = api_key('BUNDLE_SOCIAL_API_KEY', 'bundle_social_api_key', 'YOUR_BUNDLE_SOCIAL_API_KEY');
            }
            return ['api_key' => $apiKey, 'base_url' => self::BUNDLE_BASE];
        }

        if ($provider === 'zernio') {
            if ($apiKey === '') {
                $apiKey = api_key('ZERNIO_API_KEY', 'zernio_api_key', 'YOUR_ZERNIO_API_KEY');
            }
            return ['api_key' => $apiKey, 'base_url' => self::ZERNIO_BASE];
        }

        throw new \InvalidArgumentException("Unknown gateway provider '{$provider}'.");
    }

    /**
     * Check whether an API key is a placeholder or live
     */
    public static function isPlaceholderKey(string $key): bool
    {
        $k = trim($key);
        return empty($k) || str_starts_with($k, 'YOUR_') || str_contains($k, 'EXAMPLE');
    }

    /**
     * Whether the configured key for a provider is a sandbox placeholder.
     * Mirror of isPlaceholderKey() resolved against the same config the
     * gateway uses (DB row first, config fallback). Public so the smoke
     * harness can assert per-mode expectations instead of one global mode.
     */
    public static function isSandboxMode(string $provider): bool
    {
        $config = self::getGatewayConfig($provider);
        return self::isPlaceholderKey((string)($config['api_key'] ?? ''));
    }

    /**
     * Execute standard JSON gateway request.
     *
     * $endpoint is relative to the provider's versioned root:
     *  - bundle_social: 'post/', 'social-account/connect', 'analytics/post', …
     *  - zernio:        'profiles', 'connect/facebook/ads', 'ads/tree', …
     *
     * $opts keys: headers [], query []. Returns the legacy envelope
     * ['success','status_code','data','error','raw','simulated'].
     */
    public static function makeRequest(
        string $provider,
        string $endpoint,
        string $method = 'POST',
        array $body = [],
        array $opts = []
    ): array {
        $provider = strtolower(trim($provider));
        $config = self::getGatewayConfig($provider);
        $apiKey = $config['api_key'];
        $baseUrl = rtrim($config['base_url'], '/');

        // Bundle.social is always addressed under its version prefix;
        // Zernio's base URL already carries /api/v1.
        if ($provider === 'bundle_social' && !str_starts_with(ltrim($endpoint, '/'), self::BUNDLE_PREFIX)) {
            $endpoint = self::BUNDLE_PREFIX . ltrim($endpoint, '/');
        }

        $url = $baseUrl . '/' . ltrim($endpoint, '/');
        if (!empty($opts['query'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($opts['query']);
        }

        // Sandbox / Mock simulation for offline development or placeholder keys
        if (self::isPlaceholderKey($apiKey)) {
            return self::simulateResponse($provider, $endpoint, $method, $body);
        }

        $methodUpper = strtoupper($method);

        // Bounded retry: Windows threaded SAPIs (mod_php) can hit transient
        // DNS/connect timeouts that a fresh cURL handle resolves. One retry
        // keeps interactive connect flows stable; deeper failures surface as
        // honest HTTP-0 refusals as before.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $methodUpper);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            if (function_exists('ca_curl_setopt')) {
                ca_curl_setopt($ch);
            }

            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Adsbooster-Gateway/5.0',
            ];

            // Provider-specific auth: Bundle.social uses x-api-key,
            // Zernio uses Bearer (per each provider's documented scheme).
            if ($provider === 'bundle_social') {
                $headers[] = 'x-api-key: ' . $apiKey;
            } else {
                $headers[] = 'Authorization: Bearer ' . $apiKey;
            }

            foreach (($opts['headers'] ?? []) as $h) {
                $headers[] = (string)$h;
            }

            if (!empty($body) && in_array($methodUpper, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode > 0) {
                break;
            }
            if ($attempt < 2) {
                usleep(250000);
            }
        }

        if ($response === false) {
            error_log("ThirdPartyGateway [{$provider}] cURL error: " . $curlErr);
            return [
                'success'     => false,
                'status_code' => 0,
                'error'       => $curlErr ?: 'Gateway network connection failed.',
                'data'        => null,
            ];
        }

        $decoded = json_decode((string)$response, true);

        return [
            'success'     => ($httpCode >= 200 && $httpCode < 300),
            'status_code' => $httpCode,
            'data'        => $decoded ?? $response,
            'raw'         => (string)$response,
        ];
    }

    /**
     * Realistic simulated response for testing and offline environments
     * (placeholder API keys). Every simulated payload is flagged so callers
     * (AsyncQueue, server_tracking) can refuse to record it as real.
     */
    private static function simulateResponse(string $provider, string $endpoint, string $method, array $body): array
    {
        $id = bin2hex(random_bytes(8));
        $m  = strtoupper($method);

        // Bundle.social simulation (/api/v1/... paths)
        if ($provider === 'bundle_social') {
            if (str_contains($endpoint, 'social-account/connect')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'url'       => (string)($body['redirectUrl'] ?? ''),
                        'simulated' => true,
                        'message'   => 'Bundle.social connect URL simulated (sandbox)',
                    ],
                ];
            }
            if (preg_match('~/team/?$~', $endpoint) && $m === 'GET') {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'items'     => [['id' => 'bndl_sim_team_' . $id, 'name' => 'Adsbooster (Sandbox)']],
                        'simulated' => true,
                    ],
                ];
            }
            if (str_contains($endpoint, '/post') && str_starts_with($m, 'P')) {
                return [
                    'success'     => true,
                    'status_code' => 201,
                    'data'        => [
                        'id'       => 'bndl_post_' . $id,
                        'postDate' => $body['postDate'] ?? null,
                        'status'   => (string)($body['status'] ?? 'SCHEDULED'),
                        'simulated'=> true,
                        'message'  => 'Dispatched via Bundle.social Gateway (Simulated Sandbox)',
                    ],
                ];
            }
            if (str_contains($endpoint, 'analytics')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'views'       => random_int(1200, 14000),
                        'likes'       => random_int(80, 1400),
                        'comments'    => random_int(12, 240),
                        'shares'      => random_int(12, 140),
                        'impressions' => random_int(2400, 18000),
                        'simulated'   => true,
                    ],
                ];
            }
            if (str_contains($endpoint, 'comment')) {
                $simAction = (string)($body['action'] ?? '');
                if ($simAction === '' && isset($body['status'])) {
                    // moderation PATCH: status 'HIDDEN' | 'VISIBLE'
                    $simAction = ((string)$body['status'] === 'HIDDEN') ? 'hide' : 'unhide';
                }
                if ($simAction === '' && (!empty($body['internalParentCommentId']) || !empty($body['text']))) {
                    $simAction = 'reply_registered';
                }
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'id'        => 'bndl_cmt_' . $id,
                        'action'    => $simAction ?: 'acknowledged',
                        'simulated' => true,
                        'message'   => 'Comment action registered on Bundle.social Gateway (Simulated Sandbox)',
                    ],
                ];
            }
            if (str_contains($endpoint, 'upload')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => ['id' => 'bndl_upl_' . $id, 'simulated' => true],
                ];
            }
        }

        // Zernio simulation (/v1/... paths)
        if ($provider === 'zernio') {
            if (str_contains($endpoint, 'profiles')) {
                return [
                    'success'     => true,
                    'status_code' => 201,
                    'data'        => ['id' => 'zern_sim_prof_' . $id, 'externalId' => $body['externalId'] ?? '', 'simulated' => true],
                ];
            }
            if ((str_starts_with($endpoint, 'connect/') || str_contains($endpoint, '/connect/')) && str_contains($endpoint, '/ads')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'authUrl'   => (string)($body['redirect_url'] ?? 'https://simulated.zernio.local/oauth/authorize?state=' . $id),
                        'state'     => 'simulated_state',
                        'simulated' => true,
                    ],
                ];
            }
            if (str_contains($endpoint, 'ads/create') && $m === 'POST') {
                return [
                    'success'     => true,
                    'status_code' => 201,
                    'data'        => [
                        'platformCampaignId' => 'zern_cmp_' . $id,
                        'platformAdSetId'    => 'zern_adset_' . $id,
                        'ad'                 => ['platformAdId' => 'zern_ad_' . $id],
                        'status'             => (string)($body['status'] ?? 'ACTIVE'),
                        'simulated'          => true,
                        'message'            => 'Launched via Zernio Ads Engine (Simulated Sandbox)',
                    ],
                ];
            }
            if (str_contains($endpoint, '/status') && in_array($m, ['PUT', 'PATCH'], true)) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'status'    => (string)($body['status'] ?? 'active'),
                        'updated'   => 1,
                        'skipped'   => 0,
                        'simulated' => true,
                    ],
                ];
            }
            if (str_contains($endpoint, '/analytics')) {
                $spend = round(random_int(20, 250) + (random_int(10, 99) / 100), 2);
                $impressions = random_int(4500, 28000);
                $clicks = (int)round($impressions * (random_int(18, 45) / 1000));
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'metrics' => [
                            'impressions' => $impressions,
                            'clicks'      => $clicks,
                            'spend'       => $spend,
                            'conversions' => (int)round($clicks * (random_int(30, 85) / 1000)),
                            'ctr'         => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                            'cpc'         => $clicks > 0 ? round($spend / $clicks, 2) : 0,
                            'roas'        => round(random_int(220, 480) / 100, 2),
                        ],
                        'simulated' => true,
                    ],
                ];
            }
            if (str_contains($endpoint, '/conversions') && $m === 'POST') {
                return [
                    'success'     => true,
                    'status_code' => 202,
                    'data'        => [
                        'id'       => 'zern_evt_' . $id,
                        'network'  => (string)($body['network'] ?? ''),
                        'simulated'=> true,
                        'message'  => 'Conversion event accepted by Zernio Ads Engine (Simulated Sandbox)',
                    ],
                ];
            }
            if (str_contains($endpoint, 'ads/accounts')) {
                $simAccounts = [
                    ['accountId' => 'zern_sim_act_' . $id, 'adAccountId' => 'act_zern_sim_' . $id, 'platform' => 'metaads', 'status' => 'connected', 'simulated' => true],
                ];
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'items'      => $simAccounts,
                        'ad_accounts'=> $simAccounts,
                        'simulated'  => true,
                        'message'    => 'Ad accounts listed via Zernio Ads Engine (Simulated Sandbox)',
                    ],
                ];
            }
            if (str_contains($endpoint, 'ads/campaigns') && $m === 'PUT') {
                // PUT /v1/ads/campaigns/{id} — budget update (AdBudget {amount, type}).
                $simBudget = is_array($body['budget'] ?? null) ? $body['budget'] : [];
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'budget'    => [
                            'amount' => (float)($simBudget['amount'] ?? 0),
                            'type'   => (string)($simBudget['type'] ?? 'daily'),
                        ],
                        'status'    => 'ok',
                        'simulated' => true,
                        'message'   => 'Campaign budget updated via Zernio Ads Engine (Simulated Sandbox)',
                    ],
                ];
            }
            if (str_contains($endpoint, 'calendar')) {
                return [
                    'success'     => true,
                    'status_code' => 201,
                    'data'        => [
                        'id'      => 'zern_cal_' . $id,
                        'status'  => 'confirmed',
                        'simulated'=> true,
                        'message' => 'Calendar event synced via Zernio (Simulated Sandbox)',
                    ],
                ];
            }
        }

        return [
            'success'     => true,
            'status_code' => 200,
            'data'        => ['status' => 'ok', 'id' => $id, 'simulated' => true],
        ];
    }

    // =========================================================================
    // CONNECT AUTH — Signed, single-use, provider-bound state
    // =========================================================================
    // The organic (Bundle.social) and paid-ads (Zernio) authentication journeys
    // each issue their own HMAC-signed connect state. The provider name and the
    // user id are inside the signature payload and every state is single-use,
    // so a Bundle.social state can never be replayed against the Zernio flow
    // (or vice versa), and connect callbacks cannot be forged via URL editing.

    /**
     * Session-scoped HMAC secret for gateway connect states.
     */
    private static function connectSecret(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (empty($_SESSION['gateway_state_secret'])) {
                $_SESSION['gateway_state_secret'] = bin2hex(random_bytes(32));
            }
            return (string)$_SESSION['gateway_state_secret'];
        }
        // CLI/queued contexts cannot sign interactive connect journeys.
        return '';
    }

    /**
     * Issue a signed, single-use, 15-minute connect state for one gateway journey.
     * Format: {userId}.{platform}.{expiry}.{nonce}.{hmac}
     */
    public static function issueGatewayState(string $provider, int $userId, string $platform): string
    {
        $platform = strtolower(preg_replace('~[^a-z0-9_\-]~', '', $platform) ?: 'unknown');
        $secret = self::connectSecret();
        if ($secret === '') {
            // Degraded (CLI): behave like the legacy plain state.
            return (string)$userId;
        }

        // Hygiene: drop states that expired more than a minute ago.
        foreach (($_SESSION['gateway_state_pending'] ?? []) as $n => $p) {
            if (($p['exp'] ?? 0) < time() - 60) {
                unset($_SESSION['gateway_state_pending'][$n]);
            }
        }

        $nonce   = bin2hex(random_bytes(8));
        $exp     = time() + 900;
        $payload = $provider . '|' . $userId . '|' . $platform . '|' . $exp . '|' . $nonce;
        $sig     = hash_hmac('sha256', $payload, $secret);
        $_SESSION['gateway_state_pending'][$nonce] = ['sig' => $sig, 'provider' => $provider, 'exp' => $exp];

        return $userId . '.' . $platform . '.' . $exp . '.' . $nonce . '.' . $sig;
    }

    /**
     * Validate and consume a gateway connect state.
     *
     * @return string|null The validated platform on success; null when the
     *         state is missing, forged, expired, already consumed, or belongs
     *         to a different gateway journey / user session.
     */
    public static function validateGatewayState(?string $state, string $provider, int $currentUserId): ?string
    {
        $state = trim((string)$state);
        if ($state === '' || session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $parts = explode('.', $state, 5);
        if (count($parts) !== 5) {
            return null;
        }
        [$stateUserId, $platform, $exp, $nonce, $sig] = $parts;

        if ((int)$stateUserId !== (int)$currentUserId || (int)$exp < time()) {
            return null;
        }

        $pending = $_SESSION['gateway_state_pending'][$nonce] ?? null;
        if ($pending === null) {
            return null; // unknown, purged, or already consumed (single-use)
        }

        $payload  = $provider . '|' . $stateUserId . '|' . $platform . '|' . $exp . '|' . $nonce;
        $expected = hash_hmac('sha256', $payload, self::connectSecret());

        if (!hash_equals((string)$pending['sig'], $expected) || ($pending['provider'] ?? '') !== $provider) {
            return null;
        }

        // Consume: the state cannot be replayed.
        unset($_SESSION['gateway_state_pending'][$nonce]);

        return preg_replace('~[^a-z0-9_\-]~', '', $platform) ?: null;
    }

    // =========================================================================
    // 1. BUNDLE.SOCIAL API METHODS (ORGANIC POSTING & AUTOMATION)
    //    Real API: base https://api.bundle.social, every path under /api/v1,
    //    authentication via the `x-api-key` header.
    // =========================================================================

    /**
     * Map a platform name (dashboard key) to Bundle.social's UPPERCASE type
     * enum, which scout aliases handled too.
     */
    private static function bundleType(string $platform): ?string
    {
        $p = strtolower(trim($platform));
        return self::BUNDLE_TYPES[$p] ?? null;
    }

    /**
     * Resolve (or lazily create) the Bundle.social team used for all
     * Adsbooster organic operations. The team persists server-side; resolved
     * via GET /api/v1/team/ on each cold call, so no local state is needed.
     */
    private static function bundleTeamId(array $simulatedGuard = []): ?string
    {
        if (self::$bundleTeamId !== null) {
            return self::$bundleTeamId;
        }

        $res = self::makeRequest('bundle_social', 'team/', 'GET', [], ['query' => ['limit' => 10]]);
        if (!empty($res['success'])) {
            $items = $res['data']['items'] ?? ($res['data'] ?? []);
            if (is_array($items)) {
                foreach ($items as $team) {
                    if (!empty($team['id'])) {
                        self::$bundleTeamId = (string)$team['id'];
                        return self::$bundleTeamId;
                    }
                }
            }
        }

        // No team yet: create one (org defaults to the key's own organization).
        $create = self::makeRequest('bundle_social', 'team/', 'POST', [
            'name' => 'Adsbooster',
        ]);
        if (!empty($create['success']) && !empty($create['data']['id'])) {
            self::$bundleTeamId = (string)$create['data']['id'];
            return self::$bundleTeamId;
        }

        // Total failure (bad key / network): surface honestly, no invented id.
        self::$bundleTeamId = null;
        return null;
    }

    /**
     * Generate the social-account connection URL via Bundle.social.
     *
     * Real flow: POST /api/v1/social-account/connect with JSON body
     * {type, teamId, redirectUrl} returns {url}; the user is redirected to
     * that URL and Bundle.social runs the platform OAuth, then lands the
     * browser back on `redirectUrl` as-is (or with ?error= appended).
     *
     * @param string $returnPath Optional page basename the provider lands back
     *        on ('dashboard.php' (default) | 'social-connect.php' | 'onboarding.php').
     *        The signed state rides in the redirect query; the return page's
     *        own `bundle_connected` handler validates it.
     * @return string The OAuth URL (or '' when the gateway refused).
     */
    public static function getBundleConnectUrl(int $userId, string $platform, string $returnPath = 'dashboard.php'): string
    {
        $safeReturn = preg_replace('~[^\w\-]~', '', pathinfo($returnPath, PATHINFO_FILENAME)) ?: 'dashboard';

        $type = self::bundleType($platform);
        if ($type === null) {
            error_log('ThirdPartyGateway: unsupported Bundle.social platform "' . $platform . '".');
            return '';
        }

        $teamId = self::bundleTeamId();
        if ($teamId === null) {
            error_log('ThirdPartyGateway: Bundle.social team could not be resolved (key/network).');
            return '';
        }

        $state = self::issueGatewayState('bundle_social', $userId, $platform);
        // Clean URL (no .php): with a literal `?` query the provider lands
        // directly on the canonical route — no explicit-.php 301 hop.
        $redirectUrl = BASE_URL . "/" . $safeReturn . "?action=bundle_connected&state=" . urlencode($state);

        $res = self::makeRequest('bundle_social', 'social-account/connect', 'POST', [
            'type'        => $type,
            'teamId'      => $teamId,
            'redirectUrl' => $redirectUrl,
        ]);

        if (!empty($res['success']) && !empty($res['data']['url'])) {
            return (string)$res['data']['url'];
        }

        error_log('ThirdPartyGateway [bundle_social] connect failed: HTTP ' . ($res['status_code'] ?? 0)
            . ' payload ' . mb_substr((string)($res['raw'] ?? ''), 0, 300));
        return '';
    }

    /**
     * Publish or schedule an organic post across connected channels.
     *
     * Real API: POST /api/v1/post/ with body
     * {teamId, title, postDate, status: DRAFT|SCHEDULED, socialAccountTypes: […],
     *  data: {PLATFORM: {text, uploadIds}}} (all fields required).
     * Media attachments are uploaded first via POST /api/v1/upload/from-url
     * {teamId, url} and referenced by upload id.
     *
     * $postPayload keys: account_ids[] | social_account_types[],
     * caption, media_urls[], scheduled_at (ISO8601|null), team_id (optional).
     */
    public static function createOrganicPost(array $postPayload): array
    {
        $teamId = (string)($postPayload['team_id'] ?? '') ?: (string)(self::bundleTeamId() ?? '');
        if ($teamId === '') {
            return [
                'success'     => false,
                'status_code' => 0,
                'error'       => 'Bundle.social team could not be resolved — post not dispatched.',
                'data'        => null,
            ];
        }

        $caption   = (string)($postPayload['caption'] ?? ($postPayload['text'] ?? ''));
        $mediaUrls = (array)($postPayload['media_urls'] ?? ($postPayload['media'] ?? []));
        $scheduledAt = $postPayload['scheduled_at'] ?? null;

        // --- socialAccountTypes: explicit list, else mapped from account ids,
        // else fall back to the connected platforms on the team.
        $types = [];
        foreach ((array)($postPayload['social_account_types'] ?? []) as $t) {
            $type = self::bundleType((string)$t);
            if ($type !== null && !in_array($type, $types, true)) {
                $types[] = $type;
            }
        }
        if (!$types) {
            foreach ((array)($postPayload['account_ids'] ?? []) as $id) {
                $type = self::bundleType((string)$id);
                if ($type !== null && !in_array($type, $types, true)) {
                    $types[] = $type;
                }
            }
        }
        if (!$types) {
            // Legacy gateway-shaped account ids (e.g. 'bndl_facebook_test',
            // local external_account_id values) — lift the platform name out
            // of the string itself.
            foreach ((array)($postPayload['account_ids'] ?? []) as $id) {
                $haystack = strtolower((string)$id);
                foreach (self::BUNDLE_TYPES as $name => $type) {
                    if (str_contains($haystack, str_replace('_', '', $name)) || str_contains($haystack, $name)) {
                        if (!in_array($type, $types, true)) {
                            $types[] = $type;
                        }
                        break;
                    }
                }
            }
        }
        if (!$types) {
            // Derive from the connected accounts table (organic rows are
            // gateway-shaped connected_accounts with platform_name keys).
            try {
                $rows = db()->fetchAll(
                    "SELECT DISTINCT platform_name FROM connected_accounts
                     WHERE gateway_provider = 'bundle_social' AND status = 'connected'"
                );
                foreach ($rows as $r) {
                    $type = self::bundleType((string)($r['platform_name'] ?? ''));
                    if ($type !== null) {
                        $types[] = $type;
                    }
                }
            } catch (\Throwable $e) {
                // fall through — the API answers 400 on an empty list
            }
        }
        if (!$types) {
            return [
                'success'     => false,
                'status_code' => 422,
                'error'       => 'No Bundle.social platform types could be derived from the payload or connected accounts.',
                'data'        => null,
            ];
        }

        // --- postDate / status: everything is handed over as SCHEDULED;
        // a null scheduled_at schedules the dispatch for "now" (Bundle
        // publishes it immediately at that timestamp).
        $postDate = $scheduledAt !== null
            ? date('Y-m-d\TH:i:s\Z', strtotime((string)$scheduledAt) ?: time())
            : gmdate('Y-m-d\TH:i:s\Z');

        // --- data payload: upload media per platform, then text + uploadIds.
        $data = [];
        foreach ($types as $type) {
            $uploadIds = [];
            foreach ($mediaUrls as $mu) {
                $mu = (string)$mu;
                if ($mu === '') {
                    continue;
                }
                $url = str_starts_with($mu, 'http') ? $mu : (BASE_URL . '/' . ltrim($mu, '/'));
                $up = self::makeRequest('bundle_social', 'upload/from-url', 'POST', [
                    'teamId' => $teamId,
                    'url'    => $url,
                ]);
                if (!empty($up['success']) && !empty($up['data']['id'])) {
                    $uploadIds[] = (string)$up['data']['id'];
                } elseif (!empty($up['data']['simulated'])) {
                    $uploadIds[] = 'sim_upload_' . count($uploadIds);
                }
            }
            $data[$type] = array_filter([
                'text'      => $caption !== '' ? $caption : null,
                'uploadIds' => $uploadIds ?: null,
            ]);
        }

        $body = [
            'teamId'             => $teamId,
            'title'              => mb_strlen($caption) > 90 ? mb_substr($caption, 0, 87) . '…' : ($caption !== '' ? $caption : 'Adsbooster Post'),
            'postDate'           => $postDate,
            'status'             => 'SCHEDULED',
            'socialAccountTypes' => $types,
            'data'               => $data ?: new \stdClass(),
        ];
        if (!empty($postPayload['first_comment'])) {
            $body['firstComment'] = (string)$postPayload['first_comment'];
        }

        $result = self::makeRequest('bundle_social', 'post/', 'POST', $body);

        // Legacy compatibility: carry the returned post id under 'post_id'.
        if (!empty($result['success']) && is_array($result['data'])) {
            $postData = $result['data'];
            if (isset($postData['id']) && !isset($postData['post_id'])) {
                $postData['post_id'] = (string)$postData['id'];
            }
            $result['data'] = $postData;
        }

        return $result;
    }

    /**
     * Retrieve Post Analytics via Bundle.social
     * (real endpoint: GET /api/v1/analytics/post?postId=…).
     */
    public static function getOrganicPostAnalytics(string $bundlePostId): array
    {
        $res = self::makeRequest('bundle_social', 'analytics/post', 'GET', [], [
            'query' => ['postId' => $bundlePostId],
        ]);

        // Normalize the documented metric names into the legacy envelope keys.
        if (!empty($res['success']) && is_array($res['data'])) {
            $d = $res['data'];
            foreach (['likes', 'comments', 'shares', 'views', 'impressions'] as $k) {
                if (isset($d[$k]) && !isset($d[strtolower($k)])) {
                    $d[$k] = (int)$d[$k];
                }
            }
            $res['data'] = $d;
        }
        return $res;
    }

    /**
     * Delete / Cancel Scheduled Post via Bundle.social
     * (real endpoint: DELETE /api/v1/post/{id}).
     */
    public static function deleteOrganicPost(string $bundlePostId): array
    {
        return self::makeRequest('bundle_social', 'post/' . urlencode($bundlePostId), 'DELETE');
    }

    /**
     * Sync a personal calendar event via the Zernio Ads & Productivity Engine.
     *
     * Bundle.social's current API carries no calendar surface, so all calendar
     * traffic is routed through the Zernio gateway (POST /v1/calendar/events)
     * whose Google/Microsoft connections own the OAuth tokens. The calendar
     * account identifier is forwarded as the routing label.
     *
     * $eventPayload keys: calendar_account_id, platform ('google_calendar'),
     * summary, description, start_time (ISO8601), end_time (ISO8601), timezone.
     */
    public static function createCalendarEvent(array $eventPayload): array
    {
        $start = (string)($eventPayload['start_time'] ?? gmdate('c'));
        $end   = (string)($eventPayload['end_time'] ?? gmdate('c', time() + 1800));

        $body = [
            'accountId'  => (string)($eventPayload['calendar_account_id'] ?? ''),
            'platform'   => (string)($eventPayload['platform'] ?? 'google_calendar'),
            'title'      => (string)($eventPayload['summary'] ?? 'Adsbooster Event'),
            'description'=> (string)($eventPayload['description'] ?? ''),
            'start'      => gmdate('Y-m-d\TH:i:s\Z', strtotime($start) ?: time()),
            'end'        => gmdate('Y-m-d\TH:i:s\Z', strtotime($end) ?: time() + 1800),
            'timeZone'   => (string)($eventPayload['timezone'] ?? date_default_timezone_get()),
        ];

        return self::makeRequest('zernio', 'calendar/events', 'POST', $body);
    }

    /**
     * Delete a previously synced calendar event via the Zernio gateway
     * (real endpoint: DELETE /v1/calendar/events/{eventId}).
     */
    public static function deleteCalendarEvent(string $externalEventId): array
    {
        return self::makeRequest('zernio', 'calendar/events/' . urlencode($externalEventId), 'DELETE');
    }

    /**
     * List the team's connected organic accounts (Bundle.social managed
     * connections) — read from the real team social-account surface:
     * GET /api/v1/social-account/by-type per connected platform type.
     *
     * @return array Legacy envelope; data.accounts carries a flattened
     *         per-platform account list (empty list when none connected).
     */
    /** Public wrapper: map a local platform key to its Bundle.social type (FACEBOOK, INSTAGRAM, …). */
    public static function bundlePlatformType(string $platform): ?string
    {
        return self::bundleType($platform);
    }

    /**
     * Verify a Bundle.social connection is REAL: the team must actually hold
     * an account of the given type (verified server-side via
     * GET /v1/social-account/by-type). OAuth callback query params are never
     * trusted — earlier code fabricated ids like 'bndl_facebook_…' and marked
     * accounts connected that did not exist on the gateway.
     *
     * @return array{success:bool, error?:string, account?:array{id:string,name:string,avatar:string}}
     */
    public static function verifyBundleAccount(string $type): array
    {
        $teamId = self::bundleTeamId();
        if ($teamId === null) {
            return ['success' => false, 'error' => 'the Bundle.social team could not be resolved (API key or connectivity issue)'];
        }

        $res = self::makeRequest('bundle_social', 'social-account/by-type', 'GET', [], [
            'query' => ['type' => $type, 'teamId' => $teamId],
        ]);
        if (empty($res['success'])) {
            $err = $res['error'] ?? '';
            if (is_array($err)) {
                $err = (string)($err['message'] ?? json_encode($err));
            }
            $err = trim((string)$err);
            if ($err === '') {
                $err = 'HTTP ' . (string)($res['status_code'] ?? 0);
            }
            return ['success' => false, 'error' => mb_substr($err, 0, 300)];
        }

        $data = $res['data'] ?? [];
        $candidates = is_array($data) ? ($data['items'] ?? ($data['accounts'] ?? (isset($data['id']) ? [$data] : []))) : [];
        foreach ((array)$candidates as $acc) {
            if (is_array($acc) && !empty($acc['id'])) {
                return [
                    'success' => true,
                    'account' => [
                        'id'     => (string)$acc['id'],
                        'name'   => (string)($acc['name'] ?? ($acc['displayName'] ?? ($acc['username'] ?? ($acc['accountName'] ?? '')))),
                        'avatar' => (string)($acc['avatarUrl'] ?? ($acc['avatar'] ?? '')),
                    ],
                ];
            }
        }
        return ['success' => false, 'error' => 'the gateway returned no usable account for this platform'];
    }

    public static function getBundleConnectedAccounts(): array
    {
        $types = [];
        try {
            $rows = db()->fetchAll(
                "SELECT DISTINCT platform_name FROM connected_accounts
                 WHERE gateway_provider = 'bundle_social' AND status = 'connected'"
            );
            foreach ($rows as $r) {
                $type = self::bundleType((string)($r['platform_name'] ?? ''));
                if ($type !== null) {
                    $types[] = $type;
                }
            }
        } catch (\Throwable $e) {
            $types = [];
        }

        if (!$types) {
            return [
                'success'     => true,
                'status_code' => 200,
                'data'        => [
                    'accounts'  => [],
                    'simulated' => self::isPlaceholderKey((string)self::getGatewayConfig('bundle_social')['api_key']),
                ],
            ];
        }

        $teamId = self::bundleTeamId();
        if ($teamId === null) {
            return [
                'success'     => false,
                'status_code' => 0,
                'error'       => 'Bundle.social team could not be resolved.',
                'data'        => null,
            ];
        }

        $accounts = [];
        foreach ($types as $type) {
            $res = self::makeRequest('bundle_social', 'social-account/by-type', 'GET', [], [
                'query' => ['type' => $type, 'teamId' => $teamId],
            ]);
            $items = is_array($res['data'] ?? null) ? $res['data'] : [];
            if (isset($items['id']) || (isset($res['data']['id']))) {
                $items = [$res['data']];
            }
            foreach ((array)$items as $item) {
                if (is_array($item) && !empty($item['id'])) {
                    $item['platform'] = strtolower((string)$type);
                    $accounts[] = $item;
                }
            }
        }

        $placeholderKey = self::isPlaceholderKey((string)self::getGatewayConfig('bundle_social')['api_key']);

        return [
            'success'     => true,
            'status_code' => 200,
            'data'        => [
                'accounts'  => $accounts,
                'simulated' => $placeholderKey,
            ],
        ];
    }

    // ---------------------------------------------------------------------
    //  COMMENT MODERATION (organic community management via Bundle.social)
    // ---------------------------------------------------------------------

    /**
     * List the comments on one published organic post
     * (real endpoint: GET /api/v1/comment/?postId=…).
     */
    public static function getPostComments(string $bundlePostId): array
    {
        $res = self::makeRequest('bundle_social', 'comment/', 'GET', [], [
            'query' => ['postId' => $bundlePostId],
        ]);

        if (!empty($res['success']) && is_array($res['data'])) {
            $items = $res['data']['items'] ?? ($res['data']['comments'] ?? []);
            if (!isset($res['data']['comments'])) {
                $res['data']['comments'] = is_array($items) ? $items : [];
            }
        }
        return $res;
    }

    /**
     * Reply to a comment from the connected account (async via the queue)
     * (real endpoint: POST /api/v1/comment/ — a reply is a new comment whose
     * internalParentCommentId references the parent comment).
     */
    public static function replyToComment(string $commentId, string $replyText): array
    {
        $teamId = (string)(self::bundleTeamId() ?? '');
        if ($teamId === '') {
            return [
                'success'     => false,
                'status_code' => 0,
                'error'       => 'Bundle.social team could not be resolved — reply not dispatched.',
                'data'        => null,
            ];
        }

        return self::makeRequest('bundle_social', 'comment/', 'POST', [
            'teamId'                => $teamId,
            'text'                  => trim($replyText),
            'internalParentCommentId' => $commentId,
            'status'                => 'PENDING',
        ]);
    }

    /**
     * Moderate a comment: 'hide' | 'unhide' | 'delete' (performed by the
     * Bundle.social Gateway, which holds the platform credentials).
     *
     * Real API: PATCH /api/v1/comment/{id} (status change) for hide/unhide,
     * DELETE /api/v1/comment/{id} for delete.
     */
    public static function moderateComment(string $commentId, string $action): array
    {
        $action = strtolower(trim($action));
        if (!in_array($action, ['hide', 'unhide', 'delete'], true)) {
            throw new \InvalidArgumentException("Unsupported moderation action '{$action}' (allowed: hide, unhide, delete).");
        }

        if ($action === 'delete') {
            return self::makeRequest('bundle_social', 'comment/' . urlencode($commentId), 'DELETE');
        }

        // hide / unhide — status PATCH (Bundle accepts PATCH on comment status).
        return self::makeRequest('bundle_social', 'comment/' . urlencode($commentId), 'PATCH', [
            'status' => $action === 'hide' ? 'HIDDEN' : 'VISIBLE',
        ]);
    }

    // =========================================================================
    // 2. ZERNIO API METHODS (PAID ADVERTISING MANAGEMENT)
    //    Real API: base https://zernio.com/api/v1, Bearer key.
    // =========================================================================

    /** Zernio posting-platform alias → /v1/connect/{platform} segment name. */
    public static function zernioPlatformSegment(string $network): ?string
    {
        $n = strtolower(trim($network));
        return match ($n) {
            'facebook', 'meta'                          => 'facebook',
            'instagram'                                 => 'instagram',
            'linkedin'                                  => 'linkedin',
            'tiktok'                                    => 'tiktok',
            'twitter', 'x'                              => 'twitter',
            'pinterest'                                 => 'pinterest',
            'google', 'google_ads', 'googleads', 'google-ad' => 'googleads',
            'reddit', 'reddit_ads', 'redditads'         => 'redditads',
            default                                     => $n !== '' ? $n : null,
        };
    }

    /**
     * Build the Zernio ad-connect URL for one network.
     *
     * Real flow (documented, verified):
     *  1. Ensure a profile exists for the user (POST /v1/profiles with
     *     externalId 'adsbooster_user_{id}'; 409 conflict → GET /v1/profiles?externalId=…).
     *  2. GET /v1/connect/{postingPlatform}/ads?profileId=…&redirect_url=….
     *  Response: {authUrl, state} for OAuth-needing platforms, or
     *  {alreadyConnected: true, accountId, platform} when same-token platforms
     *  (facebook, instagram, linkedin, pinterest) already carry ad scopes.
     *
     * There is NO code/state completion step on Adsbooster's side: Zernio
     * completes the OAuth on its own callback and redirects the browser to
     * `redirect_url` as-is (or with ?error= appended). Our signed state rides
     * inside the redirect_url query so dashboard.php can still validate it.
     *
     * @return string The authUrl (or an already-connected marker URL; '' on refusal).
     */
    public static function getZernioAdConnectUrl(int $userId, string $network, string $returnPath = 'dashboard.php'): string
    {
        $safeReturn = preg_replace('~[^\w\-]~', '', pathinfo($returnPath, PATHINFO_FILENAME)) ?: 'dashboard';
        $segment = self::zernioPlatformSegment($network);
        if ($segment === null) {
            error_log('ThirdPartyGateway: unsupported Zernio ads platform "' . $network . '".');
            return '';
        }

        // --- Step 1: resolve/ensure the profile (one per end user).
        // Real API (verified live): POST /v1/profiles requires `name` AND the
        // name must be UNIQUE across the Zernio workspaces (a second user
        // with the same 'Adsbooster' name 409s with profile_name_conflict —
        // so the name is user-scoped). The profile id key is `_id`
        // (POST returns {profile:{_id}}, GET returns {profiles:[{_id}]}).
        $externalId = 'adsbooster_user_' . (int)$userId;
        $profileId  = '';
        $res = self::makeRequest('zernio', 'profiles', 'POST', [
            'name'      => 'Adsbooster User ' . (int)$userId,
            'externalId'=> $externalId,
        ], ['query' => []]);
        if (!empty($res['success'])) {
            $profileObj = $res['data']['profile'] ?? ($res['data'] ?? []);
            $profileId  = (string)($profileObj['_id'] ?? ($profileObj['id'] ?? ''));
        }
        if ($profileId === '') {
            // Conflict payloads carry the existing profile id directly
            // (works for both profile_external_id_conflict and the
            // profile_name_conflict of an older same-name profile).
            $detailId = '';
            if (is_array($res['data'])) {
                $detailId = (string)($res['data']['details']['existingProfileId'] ?? '');
            }
            if ($detailId === '' && !empty($res['raw'])) {
                $decoded = json_decode((string)$res['raw'], true);
                if (is_array($decoded)) {
                    $detailId = (string)($decoded['details']['existingProfileId'] ?? '');
                }
            }
            if ($detailId !== '') {
                $profileId = $detailId;
            }
        }
        if ($profileId === '') {
            // 409 duplicate externalId (or any create refusal): fetch the
            // existing profile by externalId.
            $find = self::makeRequest('zernio', 'profiles', 'GET', [], ['query' => ['externalId' => $externalId]]);
            $profiles = $find['data']['profiles'] ?? ($find['data'][0] ?? null);
            if (is_array($profiles)) {
                $first = $profiles[0] ?? $profiles;
                $profileId = (string)($first['_id'] ?? ($first['id'] ?? ''));
            }
        }

        // Placeholder-key sandbox: the profile call simulated; profile id not
        // returned in the real shape — keep going with a synthetic label.
        if ($profileId === '' && !empty($res['data']['simulated'])) {
            $profileId = 'sim_profile';
        }

        if ($profileId === '') {
            error_log('ThirdPartyGateway [zernio] profile resolution failed: HTTP '
                . ($res['status_code'] ?? 0) . ' payload ' . mb_substr((string)($res['raw'] ?? ''), 0, 300));
            return '';
        }

        // --- Step 2: start the ads connection.
        $state = self::issueGatewayState('zernio', $userId, $network);
        // Clean URL (no .php): the provider lands directly on the canonical
        // route — no explicit-.php 301 hop.
        $redirectUrl = BASE_URL . "/" . $safeReturn . "?action=zernio_connected&state=" . urlencode($state);

        $res = self::makeRequest('zernio', 'connect/' . rawurlencode($segment) . '/ads', 'GET', [], [
            'query' => [
                'profileId'    => $profileId,
                'redirect_url' => $redirectUrl,
            ],
        ]);

        if (!empty($res['success']) && is_array($res['data'])) {
            if (!empty($res['data']['authUrl'])) {
                return (string)$res['data']['authUrl'];
            }
            if (!empty($res['data']['alreadyConnected'])) {
                // Same-token platform already carries ads scopes — nothing to
                // OAuth. Send the user straight to the (state-validated)
                // connection confirmation on the dashboard.
                return $redirectUrl . '&already=1';
            }
        }

        error_log('ThirdPartyGateway [zernio] ads connect failed: HTTP ' . ($res['status_code'] ?? 0)
            . ' payload ' . mb_substr((string)($res['raw'] ?? ''), 0, 300));
        return '';
    }

    /**
     * Launch a paid campaign via POST /v1/ads/create (legacy single-creative
     * shape, works on every supported ad platform).
     *
     * Documented required fields: accountId (Zernio connection id) and
     * adAccountId (platform ad account, e.g. act_…). For Meta the legacy
     * creative shape additionally requires budgetAmount, headline, body,
     * callToAction, exactly one of imageUrl/video, and linkUrl.
     *
     * $adPayload keys (legacy contract preserved):
     *  - ad_account_id / zernio_account_id: Zernio connection id (accountId)
     *  - platform_ad_account_id: platform ad account (adAccountId); resolved
     *    via GET /v1/ads/accounts when omitted
     *  - platforms: ['facebook'|'instagram'|…] (first entry picks the CTA
     *    platform prefix for status writes)
     *  - title/objective/budget_amount/budget_type/headline/primary_text/
     *    destination_url/creative_media_url/media_type/targeting
     */
    public static function launchPaidCampaign(array $adPayload): array
    {
        $accountId = (string)($adPayload['ad_account_id'] ?? ($adPayload['zernio_account_id'] ?? ''));

        $mediaUrl = (string)($adPayload['creative_media_url'] ?? ($adPayload['media_url'] ?? ''));
        if ($mediaUrl !== '' && !str_starts_with($mediaUrl, 'http')) {
            $mediaUrl = BASE_URL . '/' . ltrim($mediaUrl, '/');
        }
        $isVideo = str_contains(strtolower((string)($adPayload['media_type'] ?? '')), 'video')
            || (bool)preg_match('~\.(mp4|mov|webm|m4v)(\?|$)~i', $mediaUrl);

        $goal = strtolower((string)($adPayload['objective'] ?? 'conversions'));
        $cta  = (string)($adPayload['call_to_action'] ?? 'LEARN_MORE');

        $body = [
            'accountId'    => $accountId !== '' ? $accountId : 'default',
            'adAccountId'  => (string)($adPayload['platform_ad_account_id'] ?? ''),
            'name'         => (string)($adPayload['campaign_name'] ?? ($adPayload['title'] ?? 'Adsbooster Campaign')),
            'campaignName' => (string)($adPayload['title'] ?? 'Adsbooster Campaign'),
            'goal'         => $goal,
            'budgetAmount' => (float)($adPayload['budget_amount'] ?? 10.0),
            'budgetType'   => (string)($adPayload['budget_type'] ?? 'daily'),
            'headline'     => (string)($adPayload['headline'] ?? ''),
            'body'         => (string)($adPayload['primary_text'] ?? ''),
            'callToAction' => $cta,
            'linkUrl'      => (string)($adPayload['destination_url'] ?? BASE_URL),
        ];
        // accountId self-heal: locally stored connection ids may be stale or
        // placeholder rows (pre-verification). Resolve a REAL connection id
        // from GET /v1/accounts, whose items carry `_id`. Prefer a real
        // account whose platform matches the campaign's platforms.
        $accPlatform = '';
        $accName     = '';
        try {
            $realAccounts = self::zernioListAccounts();
        } catch (\Throwable $e) {
            $realAccounts = [];
        }
        if ($realAccounts) {
            $wanted = [];
            foreach ((array)($adPayload['platforms'] ?? []) as $pp) {
                $seg = self::zernioPlatformSegment(strtolower(trim((string)$pp)));
                if ($seg !== null) {
                    $wanted[] = $seg;
                }
            }
            $preferred = null;
            $fallback  = null;
            foreach ($realAccounts as $ra) {
                if (empty($ra['id'])) {
                    continue;
                }
                if ($fallback === null) {
                    $fallback = $ra;
                }
                if ($wanted && in_array((string)$ra['platform'], $wanted, true) && $preferred === null) {
                    $preferred = $ra;
                }
            }
            $chosen = $preferred ?? $fallback;
            if ($chosen !== null
                && ($accountId === '' || $accountId === 'default'
                    || !in_array($accountId, array_column($realAccounts, 'id'), true))) {
                $accountId = (string)$chosen['id'];
                $accPlatform = (string)($chosen['platform'] ?? '');
                $accName     = (string)($chosen['name'] ?? '');
                $body['accountId'] = $accountId;
            } elseif ($chosen !== null) {
                foreach ($realAccounts as $ra) {
                    if ((string)$ra['id'] === $accountId) {
                        $accPlatform = (string)($ra['platform'] ?? '');
                        $accName     = (string)($ra['name'] ?? '');
                        break;
                    }
                }
            }
        }

        // Goal validation is per platform: googleads only accepts
        // engagement | traffic | awareness | video_views — remap the
        // internal objective honestly instead of letting the launch die.
        if ($accPlatform === 'googleads') {
            $goalMap = [
                'conversions' => 'traffic', 'conversion' => 'traffic',
                'sales'       => 'traffic', 'leads'      => 'traffic',
                'clicks'      => 'traffic', 'link_clicks'=> 'traffic',
                'reach'       => 'awareness',
                'video'       => 'video_views', 'views' => 'video_views',
            ];
            $goal = $goalMap[$goal] ?? (in_array($goal, ['engagement', 'traffic', 'awareness', 'video_views'], true) ? $goal : 'traffic');
            $body['goal'] = $goal;
        }

        // Creative media: only TikTok takes the `video` field; Google Ads
        // (and every other platform) rejects it with INVALID_FIELD_VALUE and
        // takes the media URL via the documented `imageUrl` overload.
        if ($isVideo && $mediaUrl !== '') {
            if ($accPlatform === 'tiktok') {
                $body['video'] = ['url' => $mediaUrl];
            } else {
                $body['imageUrl'] = $mediaUrl;
            }
        } elseif ($mediaUrl !== '') {
            $body['imageUrl'] = $mediaUrl;
        }
        if ($accPlatform === 'googleads' && !empty($body['imageUrl'])) {
            // Google Display requires BOTH a landscape (1.91:1) and a square
            // (1:1) image — supplying only one is rejected as "Too few."
            // With a single creative source we send the same URL for both
            // slots so the launch is accepted (Google re-checks aspect ratios
            // at review time).
            $body['images'] = ['landscape' => $mediaUrl, 'square' => $mediaUrl];
        }
        if ($accPlatform === 'googleads' && empty($body['businessName'])) {
            $body['businessName'] = $accName !== '' ? $accName : get_setting('site_name', 'Adsbooster');
        }

        if (!empty($adPayload['targeting']) && is_array($adPayload['targeting'])) {
            // Zernio rejects unknown fields (INVALID_FIELD_VALUE) and the
            // internal targeting_spec is snake_case housekeeping + comma
            // strings — normalize to the clean TargetingSpec contract.
            $normalized = self::zernioNormalizeTargeting($adPayload['targeting'], $accountId);
            if ($normalized) {
                $body['targeting'] = $normalized;
            }
        }

        // adAccountId is required but the caller may not know it: resolve the
        // connection's first ad account once — GET /v1/ads/accounts requires
        // accountId (the Zernio connection id) and answers {accounts:[{id}]}.
        if ($body['adAccountId'] === '') {
            $accs = $accountId !== ''
                ? self::getZernioAdAccounts($accountId)
                : self::getZernioAdAccounts();
            $items = (array)($accs['data']['accounts'] ?? ($accs['data']['ad_accounts'] ?? ($accs['data']['items'] ?? [])));
            foreach ($items as $acc) {
                if (is_array($acc) && !empty($acc['id'])) {
                    $body['adAccountId'] = (string)$acc['id'];
                    break;
                }
            }
        }

        unset($body['id']);

        $result = self::makeRequest('zernio', 'ads/create', 'POST', $body, [
            'headers' => ['Idempotency-Key: zern_launch_' . bin2hex(random_bytes(12))],
        ]);

        // Legacy contract: surface the campaign id under data.campaign_id.
        if (!empty($result['success']) && is_array($result['data']) && !isset($result['data']['campaign_id'])) {
            $d = $result['data'];
            $campaignId = (string)
                ($d['platformCampaignId']
                ?? ($d['ad']['platformCampaignId'] ?? '')
                ?? ($d['campaignId'] ?? '')
                ?? ($d['ad']['id'] ?? '')
                ?? '');
            if ($campaignId !== '') {
                $d['campaign_id'] = $campaignId;
            }
            $result['data'] = $d;
        }

        return $result;
    }

    /**
     * Pause / Resume Ad Campaign Status via Zernio API
     * (real endpoint: PUT /v1/ads/campaigns/{campaignId}/status with body
     * {status: active|paused, platform: facebook|instagram|…|google|…}).
     *
     * @param string $zernioCampaignId Platform campaign id (platformCampaignId)
     * @param string $status           'active' | 'paused'
     * @param string $platform         Posting platform segment (default facebook)
     */
    public static function setAdCampaignStatus(string $zernioCampaignId, string $status, string $platform = 'facebook'): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'paused'], true)) {
            throw new \InvalidArgumentException("Unsupported campaign status '{$status}' (allowed: active, paused).");
        }

        $segment = self::zernioStatusPlatform($platform);
        return self::makeRequest('zernio', 'ads/campaigns/' . rawurlencode($zernioCampaignId) . '/status', 'PUT', [
            'status'   => $status,
            'platform' => $segment,
        ]);
    }

    private static function zernioStatusPlatform(string $platform): string
    {
        $n = strtolower(trim($platform));
        return match ($n) {
            'facebook', 'meta'  => 'facebook',
            'instagram'         => 'instagram',
            'tiktok'            => 'tiktok',
            'linkedin'          => 'linkedin',
            'pinterest'         => 'pinterest',
            'google', 'google_ads', 'googleads' => 'google',
            'twitter', 'x'      => 'twitter',
            'openai', 'chatgpt' => 'openai',
            'reddit'            => 'reddit',
            default             => 'facebook',
        };
    }

    /**
     * Fetch Real-Time Campaign Performance Metrics via Zernio API
     * (real endpoint: GET /v1/ads/campaigns/{campaignId}/analytics,
     * campaignId = platformCampaignId).
     */
    public static function getCampaignMetrics(string $zernioCampaignId, string $platform = ''): array
    {
        $query = [];
        if ($platform !== '') {
            $query['platform'] = self::zernioStatusPlatform($platform);
        }
        $res = self::makeRequest('zernio', 'ads/campaigns/' . rawurlencode($zernioCampaignId) . '/analytics', 'GET', [], [
            'query' => $query,
        ]);

        // Flatten the analytics summary into the legacy metrics keys callers
        // read (AdMetrics vocabulary: spend, impressions, clicks, ctr, cpc,
        // conversions, roas, purchaseValue …).
        if (!empty($res['success']) && is_array($res['data']) && !isset($res['data']['simulated'])) {
            $d = $res['data'];
            $metrics = $d['metrics'] ?? ($d['analytics']['metrics'] ?? ($d['analytics'] ?? []));
            if (is_array($metrics)) {
                foreach (['spend', 'impressions', 'reach', 'clicks', 'ctr', 'cpc', 'cpm', 'conversions', 'purchaseValue', 'roas'] as $k) {
                    if (isset($metrics[$k])) {
                        $res['data'][$k] = is_numeric($metrics[$k]) ? (float)$metrics[$k] : $metrics[$k];
                    }
                }
            }
        }
        return $res;
    }

    /**
     * Delete Ad Campaign via Zernio API
     * (real endpoint: DELETE /v1/ads/campaigns/{campaignId}).
     */
    public static function deleteAdCampaign(string $zernioCampaignId): array
    {
        return self::makeRequest('zernio', 'ads/campaigns/' . rawurlencode($zernioCampaignId), 'DELETE');
    }

    /**
     * List all connected Zernio ad accounts (real endpoint:
     * GET /v1/ads/accounts?accountId={connectionId} — verified live: the
     * connection `accountId` is REQUIRED and the response carries
     * {accounts: [{id: "act_…", name, currency, selected, …}]}).
     *
     * @param string|null $zernioAccountId A Zernio ads connection id. When
     *        omitted, every `connected_accounts` row routed through Zernio
     *        (gateway_provider = 'zernio') is queried in turn.
     * @return array Legacy envelope; data.ad_accounts carries the flattened
     *         platform ad account list.
     */
    /**
     * REAL Zernio connected accounts (GET /v1/accounts), normalized to
     * [{id (= `_id`), platform, isActive, name}]. The provider's own list is
     * the source of truth — locally stored connection ids may be stale or
     * placeholder rows that never resolve.
     */
    public static function zernioListAccounts(): array
    {
        $res = self::makeRequest('zernio', 'accounts', 'GET');
        if (empty($res['success']) || !is_array($res['data'] ?? null)) {
            return [];
        }
        $items = $res['data']['accounts'] ?? ($res['data']['items'] ?? []);
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $id = (string)($it['_id'] ?? ($it['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id'       => $id,
                'platform' => (string)($it['platform'] ?? ''),
                'isActive' => !empty($it['isActive']),
                'name'     => (string)($it['displayName'] ?? ($it['username'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * Convert the internal targeting_spec (snake_case form fields from
     * create-ad.php plus housekeeping keys written by the queue worker) into
     * the clean Zernio TargetingSpec contract:
     *   - camelCase ageMin/ageMax (ints clamped to 13–65, min <= max)
     *   - countries: ISO-3166-1 alpha-2 codes, resolved via targeting/search
     *     (dimension=geo, exact country-type name match); 2-letter codes pass
     *     through directly
     *   - interests: array of {id, name} resolved via targeting/search
     *     (dimension=interest, interestKeyword fallback); unresolvable names
     *     are DROPPED rather than sent — Zernio rejects unknown values with
     *     INVALID_FIELD_VALUE, which kills the entire launch
     *   - every internal/unrecognized key is dropped (whitelist contract)
     */
    private static function zernioNormalizeTargeting(array $raw, string $accountId): array
    {
        $out = [];
        $accId = $accountId !== '' ? $accountId : 'default';

        // ageMin / ageMax.
        $ageMin = $raw['ageMin'] ?? ($raw['age_min'] ?? null);
        $ageMax = $raw['ageMax'] ?? ($raw['age_max'] ?? null);
        if ($ageMin !== null && $ageMin !== '') {
            $ageMin = max(13, min(65, (int)$ageMin));
            $out['ageMin'] = $ageMin;
        }
        if ($ageMax !== null && $ageMax !== '') {
            $ageMax = max(13, min(65, (int)$ageMax));
            if (isset($out['ageMin']) && $ageMax < $out['ageMin']) {
                $ageMax = $out['ageMin'];
            }
            $out['ageMax'] = $ageMax;
        }

        // countries: "United States, United Kingdom" | ["US","GB"] | mixed.
        $countries = [];
        $geoNames  = [];
        $geoRaw    = $raw['countries'] ?? ($raw['geo'] ?? '');
        $geoParts  = is_array($geoRaw)
            ? $geoRaw
            : (preg_split('~[,;]\s*~', trim((string)$geoRaw)) ?: []);
        foreach ($geoParts as $g) {
            $g = trim((string)$g);
            if ($g === '') {
                continue;
            }
            if (preg_match('~^[A-Za-z]{2}$~', $g)) {
                $countries[] = strtoupper($g);
            } else {
                $geoNames[] = $g;
            }
        }
        foreach (array_unique($geoNames) as $nm) {
            try {
                $r = self::makeRequest('zernio', 'ads/targeting/search', 'GET', [], [
                    'query' => ['accountId' => $accId, 'dimension' => 'geo', 'q' => $nm, 'platform' => 'facebook'],
                ]);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ((array)($r['data']['results'] ?? []) as $hit) {
                if (is_array($hit)
                    && ($hit['type'] ?? '') === 'country'
                    && strcasecmp((string)($hit['name'] ?? ''), $nm) === 0
                    && !empty($hit['id'])) {
                    $countries[] = strtoupper((string)$hit['id']);
                    break;
                }
            }
        }
        $countries = array_values(array_unique($countries));
        if ($countries) {
            $out['countries'] = $countries;
        }

        // interests: "A, B" | ["A","B"] | [{id,name},...].
        $resolved  = [];
        $intNames  = [];
        $intRaw    = $raw['interests'] ?? [];
        $intParts  = is_array($intRaw)
            ? $intRaw
            : (preg_split('~[,;]\s*~', trim((string)$intRaw)) ?: []);
        foreach ($intParts as $it) {
            if (is_array($it)) {
                if (!empty($it['id'])) {
                    $resolved[] = ['id' => (string)$it['id'], 'name' => (string)($it['name'] ?? '')];
                }
            } else {
                $n = trim((string)$it);
                if ($n !== '') {
                    $intNames[] = $n;
                }
            }
        }
        foreach (array_unique($intNames) as $nm) {
            $hit = null;
            foreach (['interest', 'interestKeyword'] as $dim) {
                try {
                    $r = self::makeRequest('zernio', 'ads/targeting/search', 'GET', [], [
                        'query' => ['accountId' => $accId, 'dimension' => $dim, 'q' => $nm, 'platform' => 'facebook'],
                    ]);
                } catch (\Throwable $e) {
                    continue;
                }
                $results = (array)($r['data']['results'] ?? []);
                if ($results) {
                    $candidate = reset($results);
                    if (is_array($candidate) && !empty($candidate['id'])) {
                        $hit = $candidate;
                    }
                    break;
                }
            }
            if ($hit !== null) {
                $resolved[] = ['id' => (string)$hit['id'], 'name' => (string)($hit['name'] ?? $nm)];
            }
            // Unresolvable interests are dropped silently — sending them
            // invalidates the whole launch (INVALID_FIELD_VALUE).
        }
        if ($resolved) {
            $out['interests'] = $resolved;
        }

        // gender passthrough (all|male|female → 0|1|2), ints pass through.
        $gender = $raw['gender'] ?? null;
        if ($gender !== null && $gender !== '' && $gender !== 'all') {
            $map = ['male' => 1, 'female' => 2];
            $out['gender'] = is_string($gender) && isset($map[strtolower($gender)])
                ? $map[strtolower($gender)]
                : (int)$gender;
        }

        return $out;
    }

    public static function getZernioAdAccounts(?string $zernioAccountId = null): array
    {
        // Resolve candidate connection ids.
        $connectionIds = [];
        if ($zernioAccountId !== null && $zernioAccountId !== '') {
            $connectionIds[] = $zernioAccountId;
        } else {
            try {
                $rows = db()->fetchAll(
                    "SELECT DISTINCT external_account_id FROM connected_accounts
                     WHERE gateway_provider = 'zernio' AND status = 'connected'
                       AND external_account_id <> ''"
                );
                foreach ($rows as $r) {
                    $cid = (string)($r['external_account_id'] ?? '');
                    if ($cid !== '') {
                        $connectionIds[] = $cid;
                    }
                }
            } catch (\Throwable $e) {
                $connectionIds = [];
            }
        }

        // Self-heal: locally stored ids may be placeholders/stale (pre-
        // verification rows). Always union with the REAL connection ids
        // reported by GET /v1/accounts (items carry `_id`) so ad-account
        // resolution never silently finds nothing.
        try {
            foreach (self::zernioListAccounts() as $acc) {
                $rid = (string)($acc['id'] ?? '');
                if ($rid !== '' && !in_array($rid, $connectionIds, true)) {
                    $connectionIds[] = $rid;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to whatever ids we already collected.
        }

        if (!$connectionIds) {
            // No connections known yet — issue one call without accountId so
            // the provider's own 400 ("accountId is required") surfaces
            // honestly instead of a fabricated empty list.
            return self::makeRequest('zernio', 'ads/accounts', 'GET', [], []);
        }

        $allAccounts = [];
        $lastRes = null;
        foreach ($connectionIds as $cid) {
            $res = self::makeRequest('zernio', 'ads/accounts', 'GET', [], ['query' => ['accountId' => $cid]]);
            $lastRes = $res;
            if (!empty($res['success']) && is_array($res['data'])) {
                $accounts = $res['data']['accounts'] ?? ($res['data']['items'] ?? []);
                if (is_array($accounts)) {
                    foreach ($accounts as $acc) {
                        if (is_array($acc) && !empty($acc['id'])) {
                            $acc['zernioAccount'] = $cid;
                            $allAccounts[] = $acc;
                        }
                    }
                }
            }
        }

        return [
            'success'     => true,
            'status_code' => 200,
            'data'        => [
                'accounts'    => $allAccounts,
                'ad_accounts' => $allAccounts,
                'connections' => $connectionIds,
            ],
            'raw'         => is_array($lastRes['raw'] ?? null) ? json_encode($lastRes['raw']) : ($lastRes['raw'] ?? ''),
        ];
    }

    /**
     * Retrieve full campaign detail (settings, budget, delivery)
     * from the Zernio Ads Engine
     * (real endpoint: GET /v1/ads/campaigns/{campaignId}).
     */
    public static function getCampaignDetail(string $zernioCampaignId): array
    {
        return self::makeRequest('zernio', 'ads/campaigns/' . rawurlencode($zernioCampaignId), 'GET', [], []);
    }

    /**
     * Adjust a live campaign budget (manual scaling; auto-budget scaling
     * lives in the performance cron via the same endpoint).
     * (real endpoint: PUT /v1/ads/campaigns/{campaignId} with budget {amount,type}.)
     *
     * @param int|float $amount New budget amount (whole currency units)
     * @param string    $type   'daily' | 'lifetime' (Zernio AdBudget.type)
     * @param string    $platform Posting platform (disambiguator)
     */
    public static function setCampaignBudget(string $zernioCampaignId, $amount, string $type = 'daily', string $platform = ''): array
    {
        $amount = (float)$amount;
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Budget amount must be greater than zero.');
        }
        $type = in_array(strtolower($type), ['daily', 'lifetime'], true) ? strtolower($type) : 'daily';

        $body = [
            'budget' => ['amount' => $amount, 'type' => $type],
        ];
        if ($platform !== '') {
            $body['platform'] = self::zernioStatusPlatform($platform);
        }

        $result = self::makeRequest('zernio', 'ads/campaigns/' . rawurlencode($zernioCampaignId), 'PUT', $body);

        // Legacy contract: callers read data.budget as the new numeric amount;
        // the provider's structured AdBudget {amount, type} is preserved
        // alongside under data.budget_detail.
        if (!empty($result['success']) && is_array($result['data'])) {
            $d = $result['data'];
            if (isset($d['budget']) && is_array($d['budget'])) {
                $d['budget_detail'] = $d['budget'];
            }
            $d['budget'] = $amount;
            $result['data'] = $d;
        }

        return $result;
    }

    /**
     * Deliver one server-side conversion event through the Zernio Ads Engine
     * (real endpoint: POST /v1/ads/conversions, ConversionEvent schema:
     * eventName, eventTime, user identity block, fbc/fbp/gclid …).
     *
     * ZERNIO HOLDS THE PLATFORM CREDENTIALS: Adsbooster never touches
     * graph.facebook.com or business-api.tiktok.com itself. The platform
     * `pixel_id` is forwarded purely as a routing label for Zernio's
     * destination targeting; per-platform access tokens were retired.
     *
     * $eventPayload keys:
     *  - network: 'meta' | 'tiktok' (target network)
     *  - pixel_id: platform pixel id as routing label (may be empty)
     *  - event, event_id, event_source_url
     *  - user_data: SHA-256 hashed identity block (CAPI compliant)
     *  - custom_data: value/currency/order_id/... block
     *  - test_event_code: optional sandbox routing code
     */
    public static function sendConversionEvent(array $eventPayload): array
    {
        $eventName = (string)($eventPayload['event'] ?? 'PageView');
        $userBlock = is_array($eventPayload['user_data'] ?? null) ? $eventPayload['user_data'] : [];

        $conversion = [
            'eventName'      => $eventName,
            'eventTime'      => gmdate('Y-m-d\TH:i:s\Z'),
            'userData'       => $userBlock ?: new \stdClass(),
            'customData'     => is_array($eventPayload['custom_data'] ?? null)
                ? $eventPayload['custom_data']
                : new \stdClass(),
        ];
        if (!empty($eventPayload['event_id'])) {
            $conversion['eventId'] = (string)$eventPayload['event_id'];
        }
        if (!empty($eventPayload['event_source_url'])) {
            $conversion['eventSourceUrl'] = (string)$eventPayload['event_source_url'];
        }

        $body = [
            'network'     => strtolower((string)($eventPayload['network'] ?? 'meta')),
            'pixelId'     => (string)($eventPayload['pixel_id'] ?? ''),
            'conversions' => [$conversion],
        ];
        if (!empty($eventPayload['test_event_code'])) {
            $body['testEventCode'] = (string)$eventPayload['test_event_code'];
        }

        return self::makeRequest('zernio', 'ads/conversions', 'POST', $body);
    }
}
