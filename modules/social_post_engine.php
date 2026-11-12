<?php declare(strict_types=1);

/**
 * Social Media Posting Engine — Unified Bundle.social Gateway (Asynchronous)
 * Adsbooster.net
 *
 * All organic post scheduling, UGC video publishing, and live social
 * broadcasting are routed exclusively through the Bundle.social Third-Party
 * API — asynchronously. Producers only insert a scheduled_posts row and push
 * a job into modules/AsyncQueue.php (instant, no external HTTP in the
 * request loop); cron/queue_worker.php performs the gateway dispatch,
 * retries with exponential backoff, and persists the confirmed row state.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ThirdPartyGateway.php';

class SocialPostEngine
{
    /** @var array Supported social platforms via Bundle.social */
    public const SUPPORTED_PLATFORMS = [
        'meta',
        'facebook',
        'instagram',
        'tiktok',
        'twitter',
        'linkedin',
        'pinterest',
        'youtube',
    ];

    /** @var array Valid post statuses */
    public const VALID_STATUSES = ['scheduled', 'published', 'failed', 'cancelled'];

    /** @var array Valid media types */
    public const VALID_MEDIA_TYPES = ['image', 'ugc_image', 'ugc_video', 'reel', 'text', 'text_only'];

    /**
     * Resolve account ID for a given user and platform from connected_accounts
     */
    private static function resolveBundleAccountId(int $userId, string $platform): string
    {
        $platform = strtolower(trim($platform));
        $match = match ($platform) {
            'facebook', 'instagram' => ['facebook', 'instagram', 'meta'],
            default => [$platform],
        };

        try {
            $placeholders = implode(',', array_fill(0, count($match), '?'));
            $row = db()->fetchOne(
                "SELECT external_account_id FROM connected_accounts 
                 WHERE user_id = ? AND gateway_provider = 'bundle_social' AND platform_name IN ({$placeholders})
                 ORDER BY id DESC LIMIT 1",
                array_merge([$userId], $match)
            );

            if ($row && !empty($row['external_account_id'])) {
                return (string)$row['external_account_id'];
            }
        } catch (\Throwable $e) {
            // Database offline or unmigrated; proceed with deterministic fallback
        }

        // Return a deterministic fallback account ID if not yet mapped
        return 'bndl_' . $platform . '_' . $userId;
    }

    /**
     * Guard: organic engine only — reject paid advertising channels, which are
     * owned by the Zernio gateway (launched from create-ad.php / API v1).
     */
    private static function assertOrganicPlatform(string $platform): void
    {
        if (!in_array($platform, self::SUPPORTED_PLATFORMS, true)) {
            throw new \InvalidArgumentException(
                "Platform '{$platform}' is not an organic Bundle.social channel. Paid advertising platforms (snapchat, google_ads, combo_pack) must be launched via the Zernio Ads Gateway."
            );
        }
    }

    /**
     * Publish a post "immediately" — asynchronous path.
     *
     * Inserts the scheduled_posts row marked 'scheduled' and enqueues a
     * bundle_publish_post job due now; the CLI worker performs the actual
     * Bundle.social dispatch out-of-band, so no external API call ever runs
     * in the request/response loop. Returns success=queued state, with
     * external_id null until the worker confirms the real provider id.
     */
    public static function publishLive(
        int $userId,
        string $platform,
        string $caption,
        ?string $mediaUrl = null,
        string $mediaType = 'image'
    ): array {
        $platform = strtolower(trim($platform));
        try {
            self::assertOrganicPlatform($platform);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'platform' => $platform, 'error' => $e->getMessage(), 'queued' => false];
        }

        $postId = self::enqueuePostJob([
            'user_id'        => $userId,
            'platform'       => $platform,
            'post_type'      => 'realtime_publish',
            'caption'        => trim($caption),
            'media_url'      => $mediaUrl,
            'media_type'     => $mediaType,
            'scheduled_time' => date('Y-m-d H:i:s'),
        ]);
        if ($postId <= 0) {
            return ['success' => false, 'platform' => $platform, 'error' => 'Could not queue the publish job. Please retry.', 'queued' => false];
        }

        return [
            'success'        => true,
            'platform'       => $platform,
            'post_id'        => $postId,
            'queued'         => true,
            'external_id'    => null, // worker sets bundle_post_id on confirmation
            'bundle_post_id' => null,
            'error'          => null,
            'data'           => ['simulated' => false],
        ];
    }

    /**
     * Queue publishing across multiple connected social networks at once.
     * Each platform becomes its own scheduled_posts row + queue job, so the
     * worker can report per-channel success independently.
     */
    public static function publishMultiLive(
        int $userId,
        array $platforms,
        string $caption,
        ?string $mediaUrl = null,
        string $mediaType = 'image'
    ): array {
        $results = [];
        $overallSuccess = true;

        foreach ($platforms as $platform) {
            $res = self::publishLive($userId, (string)$platform, $caption, $mediaUrl, $mediaType);
            $results[$platform] = $res;
            if (empty($res['success'])) {
                $overallSuccess = false;
            }
        }

        return [
            'success' => $overallSuccess,
            'results' => $results,
        ];
    }

    /**
     * Create and schedule a social post — asynchronous path.
     *
     * Persists the scheduled_posts row locally, then enqueues the
     * bundle_publish_post job to run exactly at the requested time. The
     * CLI worker talks to Bundle.social; the request loop never does.
     */
    public static function createPost(
        int $userId,
        string $platform,
        string $caption,
        ?string $mediaUrl,
        string $mediaType,
        string $scheduledTime
    ): int {
        if (empty(trim($caption))) {
            throw new \InvalidArgumentException('Caption cannot be empty.');
        }
        $platform = strtolower(trim($platform));
        self::assertOrganicPlatform($platform);

        $scheduledTimestamp = strtotime($scheduledTime);
        if ($scheduledTimestamp === false) {
            throw new \InvalidArgumentException('Invalid scheduled time format.');
        }

        $postId = self::enqueuePostJob([
            'user_id'        => $userId,
            'platform'       => $platform,
            'post_type'      => 'scheduled_campaign',
            'caption'        => trim($caption),
            'media_url'      => $mediaUrl,
            'media_type'     => $mediaType,
            'scheduled_time' => date('Y-m-d H:i:s', $scheduledTimestamp),
        ], $scheduledTimestamp);

        if ($postId <= 0) {
            throw new \RuntimeException('Failed to schedule post. Please try again.');
        }

        // Email-first: the user hears back the moment a post is queued, and
        // again when the network confirms (or refuses) publication.
        try {
            require_once __DIR__ . '/notifier.php';
            Notifier::user($userId, 'post_scheduled', [
                'platform'       => ucfirst($platform),
                'scheduled_time' => date('D d M Y, H:i', $scheduledTimestamp),
                'caption'        => mb_substr(trim($caption), 0, 400),
                'link'           => (defined('BASE_URL') ? BASE_URL : '') . '/post-creator',
            ]);
        } catch (\Throwable $e) {
            error_log('Post-scheduled email failed: ' . $e->getMessage());
        }

        return $postId;
    }

    /**
     * Shared queue enqueue helper: insert scheduled_posts row WITHOUT a
     * fabricated external id, then push the Bundle.social job.
     */
    private static function enqueuePostJob(array $row, ?int $scheduledTimestamp = null): int
    {
        try {
            $postId = db()->insert('scheduled_posts', array_merge($row, [
                'bundle_post_id' => null, // set only from a confirmed gateway answer
                'status'         => 'scheduled',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]));
        } catch (\Throwable $e) {
            error_log('SocialPostEngine enqueue insert failed: ' . $e->getMessage());
            return 0;
        }

        if (!class_exists('AsyncQueue')) {
            require_once __DIR__ . '/AsyncQueue.php';
        }
        $due = date('Y-m-d H:i:s', $scheduledTimestamp ?: time());
        $jobId = AsyncQueue::push(
            (int)$row['user_id'],
            'bundle_publish_post',
            ['post_id' => $postId],
            $due,
            'post:' . $postId
        );

        if ($jobId <= 0) {
            error_log('SocialPostEngine: queue insert failed for post ' . $postId . ' — publish cron will pick it up as fallback.');
        }
        return $postId;
    }

    /**
     * Publish a scheduled post immediately.
     */
    public static function publishPost(int $postId): bool
    {
        $res = self::publishScheduledRow($postId);
        return !empty($res['success']);
    }

    /**
     * Publish one existing scheduled_posts row via Bundle.social
     */
    public static function publishScheduledRow(int $postId): array
    {
        $post = db()->fetchOne("SELECT * FROM scheduled_posts WHERE id = ?", [$postId]);
        if (!$post) {
            return ['success' => false, 'error' => 'Scheduled post not found.', 'bundle_post_id' => ''];
        }
        if (!in_array((string)$post['status'], ['scheduled', 'publishing', 'failed'], true)) {
            return ['success' => false, 'error' => "Post is already {$post['status']}.", 'bundle_post_id' => ''];
        }
        if ((string)$post['status'] === 'failed') {
            // Self-heal: a retried publish job whose row is already failed
            // resets the row so the publish can actually run again. Without
            // this, queue retries deadlock forever on "Post is already
            // failed." (observed in production).
            try {
                db()->update('scheduled_posts', [
                    'status'        => 'scheduled',
                    'publish_error' => '',
                    'updated_at'    => date('Y-m-d H:i:s'),
                ], 'id = ?', [$postId]);
            } catch (\Throwable $resetErr) {
                error_log('publishScheduledRow retry-reset error: ' . $resetErr->getMessage());
            }
            $post['status'] = 'scheduled';
        }

        $userId = (int)$post['user_id'];
        $platform = (string)$post['platform'];
        $caption = (string)$post['caption'];
        try {
            self::assertOrganicPlatform(strtolower(trim($platform)));
        } catch (\InvalidArgumentException $e) {
            // Do not route paid/unknown channels through the organic gateway.
            try {
                db()->update('scheduled_posts', [
                    'status'        => 'failed',
                    'publish_error' => mb_substr($e->getMessage(), 0, 500),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ], 'id = ?', [$postId]);
            } catch (\Throwable $uErr) {
                error_log('publishScheduledRow status update error: ' . $uErr->getMessage());
            }
            try {
                require_once __DIR__ . '/notifier.php';
                Notifier::user($userId, 'post_failed', [
                    'platform' => ucfirst($platform),
                    'caption'  => mb_substr($caption, 0, 400),
                    'reason'   => mb_substr($e->getMessage(), 0, 500),
                    'post_id'  => 'local-' . $postId,
                    'link'     => (defined('BASE_URL') ? BASE_URL : '') . '/post-creator',
                    'time'     => date('c'),
                ]);
            } catch (\Throwable $mailErr) {
                error_log('Post-failure email failed: ' . $mailErr->getMessage());
            }
            return ['success' => false, 'error' => $e->getMessage(), 'bundle_post_id' => (string)($post['bundle_post_id'] ?? '')];
        }
        $mediaUrl = !empty($post['media_url']) ? (string)$post['media_url'] : null;
        $fullMediaUrl = (!empty($mediaUrl) && !str_starts_with($mediaUrl, 'http')) ? (BASE_URL . '/' . ltrim($mediaUrl, '/')) : $mediaUrl;

        $accountId = self::resolveBundleAccountId($userId, $platform);

        $gatewayResult = ThirdPartyGateway::createOrganicPost([
            'account_ids'         => [$accountId],
            'social_account_types'=> [$platform],
            'caption'             => $caption,
            'media_urls'          => $fullMediaUrl ? [$fullMediaUrl] : [],
            'scheduled_at'        => null,
        ]);

        $simulated = !empty($gatewayResult['data']['simulated']);
        $isSuccess = !empty($gatewayResult['success']) && !$simulated;

        // No-fabrication rule: a simulated answer never overwrites the
        // row's real external id and never records the post as published.
        $bundlePostId = $simulated
            ? (string)($post['bundle_post_id'] ?? '')
            : (string)($gatewayResult['data']['post_id'] ?? ($gatewayResult['data']['id'] ?? ($post['bundle_post_id'] ?? '')));

        $status = $isSuccess ? 'published' : 'failed';

        $previousStatus = (string)$post['status'];

        try {
            db()->update('scheduled_posts', [
                'status'         => $status,
                'bundle_post_id' => $bundlePostId,
                'updated_at'     => date('Y-m-d H:i:s'),
            ], 'id = ?', [$postId]);
        } catch (\Throwable $e) {
            error_log('publishScheduledRow update error: ' . $e->getMessage());
        }

        // Email-first: notify on the state transition only, so worker
        // retries never spam the user with the same outcome.
        if ($previousStatus !== $status) {
            try {
                require_once __DIR__ . '/notifier.php';
                $notifyData = [
                    'platform' => ucfirst($platform),
                    'caption'  => mb_substr($caption, 0, 400),
                    'post_id'  => $bundlePostId !== '' ? $bundlePostId : ('local-' . $postId),
                    'link'     => (defined('BASE_URL') ? BASE_URL : '') . '/post-creator',
                    'time'     => date('c'),
                ];
                if ($isSuccess) {
                    Notifier::user($userId, 'post_published', $notifyData);
                } else {
                    Notifier::user($userId, 'post_failed', array_merge($notifyData, [
                        'reason' => mb_substr((string)($gatewayResult['data']['message'] ?? 'The social network did not accept the post.'), 0, 500),
                    ]));
                }
            } catch (\Throwable $e) {
                error_log('Post-publish email failed: ' . $e->getMessage());
            }
        }

        return [
            'success'        => $isSuccess,
            'bundle_post_id' => $bundlePostId,
            'error'          => $simulated
                ? 'SIMULATED: Bundle.social sandbox answer (placeholder API key) — publish held, nothing recorded as real.'
                : ($isSuccess ? null : ((string)($gatewayResult['data']['message'] ?? '') !== '' ? (string)$gatewayResult['data']['message'] : 'Bundle.social publish failed.')),
            'data'           => $gatewayResult['data'] ?? null,
        ];
    }

    /**
     * Get posts for a user
     */
    public static function getPosts(int $userId, ?string $status = null): array
    {
        $sql = "SELECT * FROM scheduled_posts WHERE user_id = ?";
        $params = [$userId];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY scheduled_time DESC";
        return db()->fetchAll($sql, $params);
    }

    /**
     * Cancel a scheduled post — asynchronous path.
     *
     * 1. Withdraws any queued/processing bundle_publish_post job for the row
     *    (statuses get 'cancelled' so the worker never dispatches it).
     * 2. If the post was already registered on the gateway, enqueues a
     *    bundle_cancel_post job so the worker asks Bundle.social to withdraw
     *    it out-of-band — no external API call in the request loop.
     */
    public static function cancelPost(int $postId): bool
    {
        $post = db()->fetchOne("SELECT * FROM scheduled_posts WHERE id = ? AND status = 'scheduled'", [$postId]);
        if (!$post) {
            return false;
        }

        if (!class_exists('AsyncQueue')) {
            require_once __DIR__ . '/AsyncQueue.php';
        }
        AsyncQueue::cancelSubject('bundle_publish_post', 'post:' . $postId);

        if (!empty($post['bundle_post_id'])) {
            AsyncQueue::push(
                (int)$post['user_id'],
                'bundle_cancel_post',
                ['post_id' => $postId, 'bundle_post_id' => (string)$post['bundle_post_id']],
                null,
                'post-cancel:' . $postId
            );
        }

        return db()->update('scheduled_posts', [
            'status'     => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$postId]) > 0;
    }

    /**
     * Reschedule a post
     */
    public static function reschedulePost(int $postId, string $newTime): bool
    {
        $newTimestamp = strtotime($newTime);
        if ($newTimestamp === false || $newTimestamp <= time()) {
            return false;
        }

        return db()->update('scheduled_posts', [
            'scheduled_time' => date('Y-m-d H:i:s', $newTimestamp),
            'status'         => 'scheduled',
            'updated_at'     => date('Y-m-d H:i:s'),
     