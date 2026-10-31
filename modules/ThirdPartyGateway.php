<?php
declare(strict_types=1);

/**
 * Unified Third-Party Service Gateway — Adsbooster.net
 *
 * 1. Bundle.social API -> Organic Social Media Management
 *    (Post Scheduling, Multi-Account Connections, UGC Video Publishing,
 *     Automated Postings, Comment Moderation, Analytics, Google Calendar Sync)
 * 2. Zernio API -> Paid Advertising Management
 *    (Creating/Launching campaigns, performance tracking, ROAS monitoring,
 *     budget viewing & scaling, pausing/resuming, ad account listing,
 *     server-side conversion-event delivery for Meta CAPI & TikTok Events)
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
    /**
     * Retrieve credentials for third-party gateways from MySQL or config fallbacks
     */
    public static function getGatewayConfig(string $provider): array
    {
        $provider = strtolower(trim($provider));

        // 1. Try third_party_gateways table first
        try {
            $stmt = DB::getConnection()->prepare(
                "SELECT api_key, base_url, is_active FROM third_party_gateways WHERE provider_key = ? LIMIT 1"
            );
            $stmt->execute([$provider]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($config && !empty($config['is_active'])) {
                return [
                    'api_key'  => (string)$config['api_key'],
                    'base_url' => (string)$config['base_url'],
                ];
            }
        } catch (\Throwable $e) {
            // Table might not exist yet or DB unreachable — fall through to settings/constants
        }

        // 2. Fall back to site_settings or constants
        if ($provider === 'bundle_social') {
            $key = api_key('BUNDLE_SOCIAL_API_KEY', 'bundle_social_api_key', 'YOUR_BUNDLE_SOCIAL_API_KEY');
            $url = function_exists('get_setting') ? get_setting('bundle_social_base_url', defined('BUNDLE_SOCIAL_BASE_URL') ? BUNDLE_SOCIAL_BASE_URL : 'https://api.bundle.social/v1') : 'https://api.bundle.social/v1';
            return [
                'api_key'  => $key,
                'base_url' => $url,
            ];
        }

        if ($provider === 'zernio') {
            $key = api_key('ZERNIO_API_KEY', 'zernio_api_key', 'YOUR_ZERNIO_API_KEY');
            $url = function_exists('get_setting') ? get_setting('zernio_base_url', defined('ZERNIO_BASE_URL') ? ZERNIO_BASE_URL : 'https://api.zernio.com/v1') : 'https://api.zernio.com/v1';
            return [
                'api_key'  => $key,
                'base_url' => $url,
            ];
        }

        throw new \InvalidArgumentException("Unknown gateway provider '{$provider}'.");
    }

    /**
     * Check whether an API key is a placeholder or live
     */
    private static function isPlaceholderKey(string $key): bool
    {
        $k = trim($key);
        return empty($k) || str_starts_with($k, 'YOUR_') || str_contains($k, 'EXAMPLE');
    }

    /**
     * Execute standard JSON cURL request
     */
    public static function makeRequest(
        string $provider,
        string $endpoint,
        string $method = 'POST',
        array $body = []
    ): array {
        $config = self::getGatewayConfig($provider);
        $apiKey = $config['api_key'];
        $baseUrl = rtrim($config['base_url'], '/');
        $url = $baseUrl . '/' . ltrim($endpoint, '/');

        // Sandbox / Mock simulation for offline development or placeholder keys
        if (self::isPlaceholderKey($apiKey)) {
            return self::simulateResponse($provider, $endpoint, $method, $body);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if (function_exists('ca_curl_setopt')) {
            ca_curl_setopt($ch);
        }

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Adsbooster-Gateway/4.0',
        ];

        if (!empty($body) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

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
     */
    private static function simulateResponse(string $provider, string $endpoint, string $method, array $body): array
    {
        $id = bin2hex(random_bytes(8));

        // Bundle.social simulation
        if ($provider === 'bundle_social') {
            if (str_contains($endpoint, '/posts') && strtoupper($method) === 'POST') {
                return [
                    'success'     => true,
                    'status_code' => 201,
                    'data'        => [
                        'post_id'      => 'bndl_post_' . $id,
                        'status'       => !empty($body['scheduled_at']) ? 'scheduled' : 'published',
                        'scheduled_at' => $body['scheduled_at'] ?? null,
                        'account_ids'  => $body['account_ids'] ?? [],
                        'simulated'    => true,
                        'message'      => 'Dispatched via Bundle.social Gateway (Simulated Sandbox)',
                    ]
                ];
            }
            if (str_contains($endpoint, '/calendar/events')) {
                return [
                    'success'     => true,
                    'status_code' => 201,
                    'data'        => [
                        'event_id'  => 'bndl_cal_' . $id,
                        'platform'  => (string)($body['platform'] ?? 'google_calendar'),
                        'status'    => 'confirmed',
                        'message'   => 'Calendar event synced via Bundle.social Gateway (Simulated Sandbox)',
                        'simulated' => true,
                    ]
                ];
            }
            if (str_contains($endpoint, '/analytics')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'reach'       => random_int(1200, 8500),
                        'impressions' => random_int(2400, 14000),
                        'clicks'      => random_int(80, 520),
                        'engagement'  => random_int(95, 680),
                        'shares'      => random_int(12, 140),
                        'simulated'   => true,
                    ]
                ];
            }
            if (str_contains($endpoint, '/accounts')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'accounts'   => [
                            ['id' => 'bndl_sim_acc_' . $id, 'platform' => 'facebook', 'status' => 'connected', 'simulated' => true],
                        ],
                        'simulated'  => true,
                        'message'    => 'Connected accounts listed via Bundle.social Gateway (Simulated Sandbox)',
                    ]
                ];
            }
            // Comment moderation (organic community management)
            if (str_contains($endpoint, '/comments') && str_contains($endpoint, '/reply') && strtoupper($method) === 'POST') {
                return [
                    'success'     => true,
                    'status_code' => 201,
                    'data'        => [
                        'reply_id'   => 'bndl_reply_' . $id,
                        'comment_id' => '', // filled by caller from context
                        'status'     => 'reply_pending',
                        'simulated'  => true,
                        'message'    => 'Comment reply accepted by Bundle.social Gateway (Simulated Sandbox)',
                    ]
                ];
            }
            if (str_contains($endpoint, '/comments')) {
                // GET: list comments on a post; POST: moderate one comment.
                if (str_contains($endpoint, '/moderate') || strtoupper($method) === 'POST') {
                    return [
                        'success'     => true,
                        'status_code' => 200,
                        'data'        => [
                            'comment_id' => '',
                            'action'     => (string)($body['action'] ?? 'hidden'),
                            'status'     => 'moderation_registered',
                            'simulated'  => true,
                            'message'    => 'Comment moderation registered on Bundle.social Gateway (Simulated Sandbox)',
                        ]
                    ];
                }
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'comments'  => [
                            [
                                'id'          => 'bndl_cmt_' . $id,
                                'author'      => 'Sandbox Visitor',
                                'text'        => 'Sample comment retrieved from the Bundle.social sandbox — not live data.',
                                'likes'       => 0,
                                'created_at'  => date('c'),
                                'hidden'      => false,
                                'simulated'   => true,
                            ],
                        ],
                        'simulated' => true,
                        'message'   => 'Comments listed via Bundle.social Gateway (Simulated Sandbox)',
                    ]
                ];
            }
        }

        // Zernio simulation
        if ($provider === 'zernio') {
            if (str_contains($endpoint, '/campaigns') && strtoupper($method) === 'POST') {
                return [
                    'success'     => true,
                    'status_code' => 201,
                    'data'        => [
                        'campaign_id'    => 'zern_cmp_' . $id,
                        'ad_account_id'  => $body['ad_account_id'] ?? 'zern_act_default',
                        'platforms'      => $body['platforms'] ?? ['meta'],
                        'status'         => 'active',
                        'budget'         => $body['campaign']['budget'] ?? 10.0,
                        'simulated'      => true,
                        'message'        => 'Launched via Zernio Ad Engine (Simulated Sandbox)',
                    ]
                ];
            }
            if (str_contains($endpoint, '/status')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'campaign_id' => basename(dirname($endpoint)),
                        'status'      => $body['status'] ?? 'active',
                        'updated_at'  => date('Y-m-d H:i:s'),
                        'simulated'   => true,
                    ]
                ];
            }
            if (str_contains($endpoint, '/events') && strtoupper($method) === 'POST') {
                // Server-side conversion-event delivery (CAPI/TikTok Events
                // routing — Zernio holds the platform credentials).
                return [
                    'success'     => true,
                    'status_code' => 202,
                    'data'        => [
                        'gateway_event_id' => 'zern_evt_' . $id,
                        'network'          => (string)($body['network'] ?? 'meta'),
                        'pixel_id'         => (string)($body['pixel_id'] ?? ''),
                        'event_name'       => (string)($body['event'] ?? ''),
                        'status'           => 'accepted',
                        'simulated'        => true,
                        'message'          => 'Conversion event accepted by Zernio Ads Engine (Simulated Sandbox)',
                    ]
                ];
            }
            if (str_contains($endpoint, '/ad_accounts')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'ad_accounts' => [
                            ['id' => 'zern_sim_act_' . $id, 'network' => 'meta', 'status' => 'connected', 'simulated' => true],
                        ],
                        'simulated'   => true,
                        'message'     => 'Ad accounts listed via Zernio Ads Engine (Simulated Sandbox)',
                    ]
                ];
            }
            if (str_contains($endpoint, '/budget')) {
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'campaign_id' => basename(dirname($endpoint)),
                        'budget'      => (float)($body['budget_amount'] ?? 0),
                        'status'      => 'budget_update_registered',
                        'simulated'   => true,
                    ]
                ];
            }
            if (str_contains($endpoint, '/metrics')) {
                $spend = round(random_int(20, 250) + (random_int(10, 99) / 100), 2);
                $impressions = random_int(4500, 28000);
                $clicks = (int)round($impressions * (random_int(18, 45) / 1000));
                $conversions = (int)round($clicks * (random_int(30, 85) / 1000));
                return [
                    'success'     => true,
                    'status_code' => 200,
                    'data'        => [
                        'impressions' => $impressions,
                        'clicks'      => $clicks,
                        'spend'       => $spend,
                        'conversions' => $conversions,
                        'ctr'         => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                        'cpc'         => $clicks > 0 ? round($spend / $clicks, 2) : 0,
                        'roas'        => round(random_int(220, 480) / 100, 2),
                        'simulated'   => true,
                    ]
                ];
            }
        }

        return [
            'success'     => true,
            'status_code' => 200,
            'data'        => ['status' => 'ok', 'id' => $id, 'mock' => true, 'simulated' => true]
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
            // Degraded (CLI): behave like the legacy spec URL (plain user id).
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
    // =========================================================================

    /**
     * Generate OAuth Account Connection URL via Bundle.social
     */
    public static function getBundleConnectUrl(int $userId, string $platform): string
    {
        $redirectUrl = BASE_URL . "/dashboard.php?action=bundle_connected";
        $config = self::getGatewayConfig('bundle_social');
        $state  = self::issueGatewayState('bundle_social', $userId, $platform);
        return rtrim($config['base_url'], '/') . "/connect/" . urlencode($platform) . "?redirect_uri=" . urlencode($redirectUrl) . "&state=" . urlencode($state);
    }

    /**
     * Publish or Schedule Organic Posts across 15+ Social Channels via Bundle.social
     */
    public static function createOrganicPost(array $postPayload): array
    {
        // $postPayload keys: account_ids (array), caption (string), media_urls (array), scheduled_at (string ISO8601|null)
        $accountIds = (array)($postPayload['account_ids'] ?? []);
        $caption    = (string)($postPayload['caption'] ?? ($postPayload['text'] ?? ''));
        $mediaUrls  = (array)($postPayload['media_urls'] ?? ($postPayload['media'] ?? []));
        $scheduledAt= $postPayload['scheduled_at'] ?? null;

        $body = [
            'account_ids' => $accountIds,
            'text'        => $caption,
            'media'       => array_values(array_filter(array_map(function ($path) {
                if (empty($path)) return '';
                return str_starts_with($path, 'http') ? $path : (BASE_URL . '/' . ltrim($path, '/'));
            }, $mediaUrls))),
            'scheduled_at' => $scheduledAt,
        ];

        return self::makeRequest('bundle_social', '/posts', 'POST', $body);
    }

    /**
     * Retrieve Post Analytics via Bundle.social
     */
    public static function getOrganicPostAnalytics(string $bundlePostId): array
    {
        return self::makeRequest('bundle_social', "/posts/" . urlencode($bundlePostId) . "/analytics", 'GET');
    }

    /**
     * Delete / Cancel Scheduled Post via Bundle.social
     */
    public static function deleteOrganicPost(string $bundlePostId): array
    {
        return self::makeRequest('bundle_social', "/posts/" . urlencode($bundlePostId), 'DELETE');
    }

    /**
     * Sync a personal calendar event (Google Calendar) via Bundle.social.
     * Calendar Sync is part of the organic engine per the gateway architecture.
     *
     * $eventPayload keys: calendar_account_id, platform ('google_calendar'),
     * summary, description, start_time (ISO8601), end_time (ISO8601), timezone.
     */
    public static function createCalendarEvent(array $eventPayload): array
    {
        $body = [
            'account_id'  => (string)($eventPayload['calendar_account_id'] ?? ''),
            'platform'    => (string)($eventPayload['platform'] ?? 'google_calendar'),
            'summary'     => (string)($eventPayload['summary'] ?? 'Adsbooster Event'),
            'description' => (string)($eventPayload['description'] ?? ''),
            'start_time'  => $eventPayload['start_time'] ?? null,
            'end_time'    => $eventPayload['end_time'] ?? null,
            'timezone'    => $eventPayload['timezone'] ?? date_default_timezone_get(),
        ];

        return self::makeRequest('bundle_social', '/calendar/events', 'POST', $body);
    }

    /**
     * Delete a previously synced calendar event via Bundle.social.
     */
    public static function deleteCalendarEvent(string $externalEventId): array
    {
        return self::makeRequest('bundle_social', '/calendar/events/' . urlencode($externalEventId), 'DELETE');
    }

    /**
     * List all connected organic accounts (Bundle.social managed connections).
     * Positions Adsbooster as the hub; the gateway carries the platform links.
     */
    public static function getBundleConnectedAccounts(): array
    {
        return self::makeRequest('bundle_social', '/accounts', 'GET');
    }

    // ---------------------------------------------------------------------
    //  COMMENT MODERATION (organic community management via Bundle.social)
    // ---------------------------------------------------------------------

    /**
     * List the comments on one published organic post.
     *
     * @return array Gateway response; 'data.comments' is the moderation feed
     *         (empty or sandbox-flagged until a live key is configured).
     */
    public static function getPostComments(string $bundlePostId): array
    {
        return self::makeRequest('bundle_social', '/posts/' . urlencode($bundlePostId) . '/comments', 'GET');
    }

    /**
     * Reply to a comment from the connected account (async via the queue).
     *
     * @return array Gateway response; only a non-simulated confirmation may
     *         be recorded as a real reply.
     */
    public static function replyToComment(string $commentId, string $replyText): array
    {
        return self::makeRequest('bundle_social', '/comments/' . urlencode($commentId) . '/reply', 'POST', [
            'text' => trim($replyText),
        ]);
    }

    /**
     * Moderate a comment: 'hide' | 'unhide' | 'delete' (performed by the
     * Bundle.social Gateway, which holds the platform credentials).
     */
    public static function moderateComment(string $commentId, string $action): array
    {
        $action = strtolower(trim($action));
        if (!in_array($action, ['hide', 'unhide', 'delete'], true)) {
            throw new \InvalidArgumentException("Unsupported moderation action '{$action}' (allowed: hide, unhide, delete).");
        }
        return self::makeRequest('bundle_social', '/comments/' . urlencode($commentId) . '/moderate', 'POST', [
            'action' => $action,
        ]);
    }

    // =========================================================================
    // 2. ZERNIO API METHODS (PAID ADVERTISING MANAGEMENT)
    // =========================================================================

    /**
     * Generate Ad Account Connect URL via Zernio
     */
    public static function getZernioAdConnectUrl(int $userId, string $network): string
    {
        $redirectUrl = BASE_URL . "/dashboard.php?action=zernio_connected";
        $config = self::getGatewayConfig('zernio');
        $state  = self::issueGatewayState('zernio', $userId, $network);
        return rtrim($config['base_url'], '/') . "/oauth/connect?network=" . urlencode($network) . "&state=" . urlencode($state) . "&callback=" . urlencode($redirectUrl);
    }

    /**
     * Launch Multi-Platform Ad Campaign or Combo Pack via Zernio API
     */
    public static function launchPaidCampaign(array $adPayload): array
    {
        /*
          $adPayload keys:
          - ad_account_id: Zernio Ad Account ID
          - platforms: ['meta', 'tiktok', 'google', 'snapchat']
          - title, objective, budget_amount, headline, primary_text, destination_url, creative_media_url, targeting
        */
        $mediaUrl = (string)($adPayload['creative_media_url'] ?? ($adPayload['media_url'] ?? ''));
        if ($mediaUrl !== '' && !str_starts_with($mediaUrl, 'http')) {
            $mediaUrl = BASE_URL . '/' . ltrim($mediaUrl, '/');
        }

        $body = [
            'ad_account_id' => (string)($adPayload['ad_account_id'] ?? 'default'),
            'platforms'     => (array)($adPayload['platforms'] ?? ['meta']),
            'campaign'      => [
                'name'      => (string)($adPayload['title'] ?? ($adPayload['campaign_name'] ?? 'Adsbooster Campaign')),
                'objective' => (string)($adPayload['objective'] ?? 'conversions'),
                'budget'    => (float)($adPayload['budget_amount'] ?? 10.0),
                'creative'  => [
                    'headline'        => (string)($adPayload['headline'] ?? ''),
                    'body'            => (string)($adPayload['primary_text'] ?? ''),
                    'destination_url' => (string)($adPayload['destination_url'] ?? BASE_URL),
                    'media_url'       => $mediaUrl,
                ],
                'targeting' => $adPayload['targeting'] ?? [],
            ],
        ];

        return self::makeRequest('zernio', '/campaigns', 'POST', $body);
    }

    /**
     * Pause / Resume Ad Campaign Status via Zernio API
     */
    public static function setAdCampaignStatus(string $zernioCampaignId, string $status): array
    {
        // $status: 'active' or 'paused'
        return self::makeRequest('zernio', "/campaigns/" . urlencode($zernioCampaignId) . "/status", 'PATCH', [
            'status' => strtolower($status),
        ]);
    }

    /**
     * Fetch Real-Time Campaign Performance Metrics via Zernio API
     */
    public static function getCampaignMetrics(string $zernioCampaignId): array
    {
        return self::makeRequest('zernio', "/campaigns/" . urlencode($zernioCampaignId) . "/metrics", 'GET');
    }

    /**
     * Delete Ad Campaign via Zernio API
     */
    public static function deleteAdCampaign(string $zernioCampaignId): array
    {
        return self::makeRequest('zernio', "/campaigns/" . urlencode($zernioCampaignId), 'DELETE');
    }

    /**
     * List all connected Zernio ad accounts across every supported network
     * (Meta, TikTok Ads, Google/PMax, Snapchat, Combo Packs).
     */
    public static function getZernioAdAccounts(): array
    {
        return self::makeRequest('zernio', '/ad_accounts', 'GET');
    }

    /**
     * Retrieve full campaign detail (status, placements, budget spend)
     * from the Zernio Ads Engine.
     */
    public static function getCampaignDetail(string $zernioCampaignId): array
    {
        return self::makeRequest('zernio', '/campaigns/' . urlencode($zernioCampaignId), 'GET');
    }

    /**
     * Adjust a live campaign budget (manual scaling; auto-budget scaling
     * lives in the performance cron via the same endpoint).
     *
     * @param int|float $amount New budget amount (gateway interprets per
     *                          campaign budget_type: daily | lifetime).
     */
    public static function setCampaignBudget(string $zernioCampaignId, $amount): array
    {
        $amount = (float)$amount;
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Budget amount must be greater than zero.');
        }
        return self::makeRequest('zernio', '/campaigns/' . urlencode($zernioCampaignId) . '/budget', 'PATCH', [
            'budget_amount' => $amount,
        ]);
    }

    /**
     * Deliver one server-side conversion event (Meta CAPI / TikTok Events)
     * through the Zernio Ads Engine.
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
        $body = [
            'network'           => strtolower((string)($eventPayload['network'] ?? 'meta')),
            'pixel_id'          => (string)($eventPayload['pixel_id'] ?? ''),
            'event'             => (string)($eventPayload['event'] ?? 'PageView'),
            'event_id'          => (string)($eventPayload['event_id'] ?? ''),
            'event_source_url'  => (string)($eventPayload['event_source_url'] ?? ''),
            'action_source'     => 'website',
            'user_data'         => $eventPayload['user_data'] ?? [],
            'custom_data'       => $eventPayload['custom_data'] ?? [],
        ];
        if (!empty($eventPayload['test_event_code'])) {
            $body['test_event_code'] = (string)$eventPayload['test_event_code'];
        }

        return self::makeRequest('zernio', '/events', 'POST', $body);
    }
}

