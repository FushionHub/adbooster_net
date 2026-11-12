<?php
declare(strict_types=1);

/**
 * Asynchronous Job Queue — Adsbooster.net (Dual Gateway Architecture)
 *
 * Zero synchronous external API calls in request/response loops: producers
 * (page controllers, REST handlers, crons) only INSERT into the queue and
 * return instantly; `cron/queue_worker.php` (and any test harness) runs
 * `processBatch()` out-of-band against the Bundle.social / Zernio gateways
 * with exponential backoff retries.
 *
 * Persistence honesty rule (mirrors the gateway no-fabrication policy):
 * when a gateway call is answered by sandbox simulation (placeholder API
 * key, payload flagged `simulated: true`), the worker must NOT persist the
 * simulated external ids as real outcomes. Jobs then fail immediately with
 * an honest note and can be re-queued later with `retryFailed()` once a
 * live key is configured.
 */

require_once __DIR__ . '/ThirdPartyGateway.php';

class AsyncQueue
{
    /** @var array<string> Supported job types */
    public const JOB_TYPES = [
        'bundle_publish_post',   // organic scheduled/realtime post dispatch
        'bundle_cancel_post',    // withdraw an already-dispatched scheduled post
        'bundle_reply_comment',  // organic comment reply via Bundle.social
        'bundle_moderate_comment', // organic comment moderation via Bundle.social
        'zernio_launch_ad',      // paid campaign launch via Zernio
        'zernio_toggle_status',  // paid campaign pause/resume via Zernio
        'zernio_send_conversion', // server-side conversion-event delivery via Zernio
    ];

    /** Seconds between attempts (2^attempts * base, capped) */
    private const BACKOFF_BASE_SECONDS = 30;
    private const BACKOFF_MAX_SECONDS = 3600;

    /** A stuck 'processing' job older than this is reclaimed automatically. */
    private const STALE_PROCESSING_SECONDS = 900;

    /**
     * Enqueue a job for non-blocking asynchronous background execution.
     *
     * @param int         $userId      Owning user (kept even for system jobs).
     * @param string      $jobType     One of JOB_TYPES.
     * @param array       $payload     JSON-serialisable handler payload.
     * @param string|null $scheduledAt 'Y-m-d H:i:s' when the job becomes due.
     * @param string|null $subjectKey  Dedupe key, e.g. 'post:12' / 'campaign:9'.
     * @param bool        $dedupeActive When true, an already pending/processing
     *                                 job with the same subject+type suppresses
     *                                 the insert (no duplicate dispatches).
     * @return int The new queue row id, or 0 when the job was deduped/failed.
     */
    public static function push(
        int $userId,
        string $jobType,
        array $payload,
        ?string $scheduledAt = null,
        ?string $subjectKey = null,
        bool $dedupeActive = true
    ): int {
        if (!in_array($jobType, self::JOB_TYPES, true)) {
            error_log("AsyncQueue: refused unknown job type '{$jobType}'.");
            return 0;
        }
        $scheduledAt = ($scheduledAt && strtotime($scheduledAt) !== false)
            ? date('Y-m-d H:i:s', strtotime($scheduledAt))
            : date('Y-m-d H:i:s');

        try {
            if ($dedupeActive && $subjectKey !== null && self::hasActive($jobType, $subjectKey)) {
                $existing = db()->fetchOne(
                    "SELECT id FROM async_job_queue
                     WHERE job_type = ? AND subject_key = ? AND status IN ('pending','processing')
                     ORDER BY id ASC LIMIT 1",
                    [$jobType, $subjectKey]
                );
                return (int)($existing['id'] ?? 0);
            }

            return db()->insert('async_job_queue', [
                'user_id'        => $userId,
                'job_type'       => $jobType,
                'subject_key'    => $subjectKey,
                'payload'        => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status'         => 'pending',
                'attempts'       => 0,
                'max_attempts'   => 5,
                'scheduled_at'   => $scheduledAt,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('AsyncQueue::push failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Whether a pending/processing job already exists for subject+type.
     */
    public static function hasActive(string $jobType, string $subjectKey): bool
    {
        try {
            return (bool)db()->fetchOne(
                "SELECT id FROM async_job_queue
                 WHERE job_type = ? AND subject_key = ? AND status IN ('pending','processing')
                 LIMIT 1",
                [$jobType, $subjectKey]
            );
        } catch (\Throwable $e) {
            error_log('AsyncQueue::hasActive failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Re-queue failed jobs (e.g. from sandbox-simulation holds) so a
     * configured live API key picks them up on the next worker pass.
     *
     * @return int Number of re-queued jobs.
     */
    public static function retryFailed(?string $jobType = null): int
    {
        try {
            // Organic companion records: re-open scheduled_posts rows that
            // failed in sandbox mode so the re-queued worker pass can retry
            // them (the row and the job share the 'post:{id}' subject key).
            try {
                db()->query(
                    "UPDATE scheduled_posts sp
                     JOIN async_job_queue q ON q.subject_key = CONCAT('post:', sp.id) AND q.job_type = 'bundle_publish_post'
                     SET sp.status = 'scheduled', sp.updated_at = NOW()
                     WHERE q.status = 'failed' AND sp.status = 'failed'"
                    . ($jobType === 'bundle_publish_post' || $jobType === null ? '' : ' AND FALSE'),
                    []
                );
            } catch (\Throwable $ignore) {}
            return db()->update(
                'async_job_queue',
                [
                    'status'       => 'pending',
                    'attempts'     => 0,
                    'scheduled_at' => date('Y-m-d H:i:s'),
                    'error_log'    => 'Re-queued for live-key retry',
                ],
                $jobType !== null ? "status = 'failed' AND job_type = ?" : "status = 'failed'",
                $jobType !== null ? [$jobType] : []
            );
        } catch (\Throwable $e) {
            error_log('AsyncQueue::retryFailed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cancel queued/processing jobs for a subject (used when a local record
     * is deleted/cancelled before the worker executes it).
     *
     * @return int Number of jobs cancelled.
     */
    public static function cancelSubject(string $jobType, string $subjectKey): int
    {
        try {
            return db()->update(
                'async_job_queue',
                ['status' => 'cancelled', 'error_log' => 'Cancelled by user action', 'processed_at' => date('Y-m-d H:i:s')],
                "job_type = ? AND subject_key = ? AND status IN ('pending','processing')",
                [$jobType, $subjectKey]
            );
        } catch (\Throwable $e) {
            error_log('AsyncQueue::cancelSubject: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Claim and process one batch of due jobs. Called repeatedly by the CLI
     * worker daemon, or directly by verification harnesses.
     *
     * Uses SELECT ... FOR UPDATE SKIP LOCKED so any number of parallel
     * workers can claim disjoint batches safely.
     *
     * @return array{claimed:int, completed:int, failed:int}
     */
    public static function processBatch(int $limit = 10, ?callable $logger = null): array
    {
        $log = static function (string $line) use ($logger): void {
            if ($logger !== null) {
                $logger($line);
            }
        };

        $stats = ['claimed' => 0, 'completed' => 0, 'failed' => 0];

        self::reclaimStaleProcessing($log);

        $pdo = DB::getConnection();
        $limit = max(1, min(50, $limit));

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT id, user_id, job_type, payload, attempts, max_attempts
                 FROM async_job_queue
                 WHERE status = 'pending' AND scheduled_at <= NOW()
                 ORDER BY id ASC LIMIT {$limit}
                 FOR UPDATE SKIP LOCKED"
            );
            $stmt->execute();
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$jobs) {
                $pdo->commit();
                return $stats;
            }

            $ids = array_map(static fn($j) => (int)$j['id'], $jobs);
            $inClause = implode(',', $ids);
            $now = date('Y-m-d H:i:s');
            $pdo->exec("UPDATE async_job_queue SET status = 'processing', attempts = attempts + 1, last_attempt_at = '{$now}' WHERE id IN ({$inClause})");
            $pdo->commit();
            $stats['claimed'] = count($jobs);
        } catch (\Throwable $e) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Throwable $ignore) {}
            error_log('AsyncQueue claim failed: ' . $e->getMessage());
            return $stats;
        }

        foreach ($jobs as $job) {
            $jobId = (int)$job['id'];
            $log("[Worker] Processing job #{$jobId} ({$job['job_type']}, attempt " . ((int)$job['attempts'] + 1) . '/' . (int)$job['max_attempts'] . ')');

            try {
                self::handle($job, $log);
                db()->update('async_job_queue', [
                    'status'       => 'completed',
                    'processed_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$jobId]);
                $log("[Worker] Job #{$jobId} COMPLETED.");
                $stats['completed']++;
            } catch (\Throwable $e) {
                $stats['failed'] += self::failJob($job, $e, $log);
            }
        }

        return $stats;
    }

    // ------------------------------------------------------------------
    //  Job handlers
    // ------------------------------------------------------------------

    private static function handle(array $job, callable $log): void
    {
        $payload = json_decode((string)$job['payload'], true) ?: [];
        $payload['job_id'] = (int)$job['id'];

        switch ((string)$job['job_type']) {
            case 'bundle_publish_post':
                self::handleBundlePublishPost($payload, $log);
                return;
            case 'bundle_cancel_post':
                self::handleBundleCancelPost($payload, $log);
                return;
            case 'bundle_reply_comment':
                self::handleBundleReplyComment($payload, $log);
                return;
            case 'bundle_moderate_comment':
                self::handleBundleModerateComment($payload, $log);
                return;
            case 'zernio_launch_ad':
                self::handleZernioLaunchAd($payload, $log);
                return;
            case 'zernio_toggle_status':
                self::handleZernioToggleStatus($payload, $log);
                return;
            case 'zernio_send_conversion':
                self::handleZernioSendConversion($payload, $log);
                return;
        }
        throw new \RuntimeException("Unknown job type '{$job['job_type']}'.");
    }

    /** Dispatch an organic post row via Bundle.social. */
    private static function handleBundlePublishPost(array $payload, callable $log): void
    {
        $postId = (int)($payload['post_id'] ?? $payload['internal_post_id'] ?? 0);
        if ($postId <= 0) {
            throw new \RuntimeException('bundle_publish_post payload missing post_id.');
        }

        require_once __DIR__ . '/social_post_engine.php';

        // Idempotency: a row already published (with a real external id)
        // makes the job a no-op success rather than a scary retry.
        $row = db()->fetchOne("SELECT status, bundle_post_id FROM scheduled_posts WHERE id = ?", [$postId]);
        if ($row && $row['status'] === 'published' && !empty($row['bundle_post_id'])) {
            $log('[Worker] Post #' . $postId . ' already published (external id recorded) — nothing to do.');
            return;
        }

        $result = SocialPostEngine::publishScheduledRow($postId);

        if (empty($result['success'])) {
            throw new \RuntimeException((string)($result['error'] ?? 'Bundle.social publish failed.'));
        }

        // Guard against simulated payloads sneaking through as real outcomes.
        $data = $result['data'] ?? [];
        if (!empty($data['simulated'])) {
            throw new \RuntimeException('SIMULATED: Bundle.social answered from the sandbox (placeholder API key) — publish held, nothing recorded as real.');
        }

        $log('[Worker] Post #' . $postId . ' dispatched on Bundle.social.');
    }

    /** Ask Bundle.social to withdraw a previously dispatched scheduled post. */
    private static function handleBundleCancelPost(array $payload, callable $log): void
    {
        $bundlePostId = (string)($payload['bundle_post_id'] ?? '');
        if ($bundlePostId === '') {
            $log('[Worker] bundle_cancel_post: no external id to withdraw; local cancel already applied.');
            return;
        }
        $res = ThirdPartyGateway::deleteOrganicPost($bundlePostId);
        if (!empty($res['data']['simulated'])) {
            // A simulated delete invents nothing — the local row is already
            // cancelled, so the job completes honestly without recording it.
            $log('[Worker] bundle_cancel_post: sandbox-simulated withdrawal (nothing recorded).');
            return;
        }
        if (empty($res['success'])) {
            throw new \RuntimeException((string)($res['error'] ?? 'Bundle.social cancel failed.'));
        }
        $log('[Worker] bundle_cancel_post: withdrawn on Bundle.social.');
    }

    /**
     * Organic comment reply via Bundle.social — performed by the gateway,
     * which holds the platform credentials. Only a confirmed (non-simulated)
     * answer completes the job.
     */
    private static function handleBundleReplyComment(array $payload, callable $log): void
    {
        $commentId = (string)($payload['comment_id'] ?? '');
        $replyText = (string)($payload['reply_text'] ?? '');
        if ($commentId === '' || trim($replyText) === '') {
            throw new \RuntimeException('bundle_reply_comment requires comment_id + reply_text.');
        }

        $res = ThirdPartyGateway::replyToComment($commentId, $replyText);

        if (!empty($res['data']['simulated'])) {
            // No-fabrication: a sandbox answer invents no reply id; the host
            // UI hasn't persisted a reply yet, so failing the job keeps the
            // moderation feed honest and --retry-failed replays it live.
            throw new \RuntimeException('SIMULATED: Bundle.social sandbox comment reply — nothing recorded as replied.');
        }
        if (empty($res['success'])) {
            throw new \RuntimeException((string)($res['error'] ?? 'Bundle.social comment reply failed.'));
        }
        $log('[Worker] Comment ' . $commentId . ' replied on Bundle.social.');
    }

    /**
     * Organic comment moderation (hide/unhide/delete) via Bundle.social.
     * The gateway executes the action; nothing is recorded locally unless
     * a real confirmation arrives.
     */
    private static function handleBundleModerateComment(array $payload, callable $log): void
    {
        $commentId = (string)($payload['comment_id'] ?? '');
        $action = strtolower((string)($payload['action'] ?? ''));
        if ($commentId === '' || !in_array($action, ['hide', 'unhide', 'delete'], true)) {
            throw new \RuntimeException('bundle_moderate_comment requires comment_id + action (hide|unhide|delete).');
        }

        $res = ThirdPartyGateway::moderateComment($commentId, $action);

        if (!empty($res['data']['simulated'])) {
            throw new \RuntimeException("SIMULATED: Bundle.social sandbox moderation ({$action}) — nothing persisted as moderated.");
        }
        if (empty($res['success'])) {
            throw new \RuntimeException((string)($res['error'] ?? 'Bundle.social moderation failed.'));
        }
        $log('[Worker] Comment ' . $commentId . ' ' . $action . ' confirmed by Bundle.social.');
    }

    /** Launch a paid campaign via Zernio from the local ad_campaigns row. */
    private static function handleZernioLaunchAd(array $payload, callable $log): void
    {
        $campaignId = (int)($payload['internal_campaign_id'] ?? 0);
        if ($campaignId <= 0) {
            throw new \RuntimeException('zernio_launch_ad payload missing internal_campaign_id.');
        }

        $campaign = db()->fetchOne("SELECT * FROM ad_campaigns WHERE id = ?", [$campaignId]);
        if (!$campaign) {
            throw new \RuntimeException("Campaign #{$campaignId} no longer exists.");
        }

        // Idempotency: already launched and mapped (real, non-simulated id)
        // → resolve as a no-op so worker retries never double-launch.
        if (!empty($campaign['zernio_campaign_id']) && !empty($campaign['status'])
            && $campaign['status'] === 'active'
            && strpos((string)$campaign['zernio_campaign_id'], 'simulated') === false) {
            $log("[Worker] Campaign #{$campaignId} already launched on Zernio (id {$campaign['zernio_campaign_id']}) — nothing to do.");
            return;
        }

        $spec = json_decode((string)($campaign['targeting_spec'] ?? '{}'), true) ?: [];
        // Empty fallback on purpose: launchPaidCampaign() self-heals by
        // resolving a REAL Zernio connection id from GET /v1/accounts.
        // (Inventing 'zern_act_<userId>' here guaranteed a 404.)
        $adAccountId = (string)($payload['ad_account_id'] ?: ($spec['zernio_ad_account_id'] ?? ''));
        $platforms = (array)($payload['platforms'] ?: ($spec['platforms'] ?? ['meta']));

        $res = ThirdPartyGateway::launchPaidCampaign([
            'ad_account_id'      => $adAccountId,
            'platforms'          => $platforms,
            'title'              => (string)($campaign['campaign_name'] ?? 'Adsbooster Campaign'),
            'objective'          => (string)($campaign['objective'] ?? 'conversions'),
            'budget_amount'      => (float)($campaign['budget_amount'] ?? 10),
            'headline'           => (string)($campaign['headline'] ?? ''),
            'primary_text'       => (string)($campaign['primary_text'] ?? ''),
            'destination_url'    => (string)($campaign['destination_url'] ?? ''),
            'creative_media_url' => (string)($campaign['creative_media_url'] ?? ''),
            'targeting'          => $spec,
        ]);

        $zid = (string)($res['data']['campaign_id'] ?? ($res['data']['id'] ?? ''));

        // Email-first: report a launch failure to the user exactly once per
        // campaign (the queue retries with backoff, so a plain throw would
        // mail the user on every attempt).
        $failReason = null;
        if (!empty($res['data']['simulated'])) {
            $failReason = 'The ad gateway answered from its sandbox (placeholder API key), so the launch was held and no campaign was created.';
        } elseif (empty($res['success']) || $zid === '') {
            $failReason = 'The ad network rejected the launch: ' . mb_substr((string)($res['error'] ?? json_encode($res['data'] ?? [])), 0, 400);
        }

        if ($failReason !== null) {
            try {
                if (empty($spec['failure_notified_at'])) {
                    require_once __DIR__ . '/notifier.php';
                    Notifier::user((int)$campaign['user_id'], 'ad_failed', [
                        'campaign_name' => (string)($campaign['campaign_name'] ?? 'Campaign'),
                        'reason'        => $failReason,
                        'link'          => (defined('BASE_URL') ? BASE_URL : '') . '/create-ad',
                        'time'          => date('c'),
                    ]);
                    $spec['failure_notified_at'] = date('c');
                    db()->update('ad_campaigns', ['targeting_spec' => json_encode($spec)], 'id = ?', [$campaignId]);
                }
            } catch (\Throwable $mailErr) {
                error_log('Ad-failed email failed: ' . $mailErr->getMessage());
            }

            if (!empty($res['data']['simulated'])) {
                // Honest hold: the campaign stays 'pending' (nothing invented);
                // retryFailed() re-queues once a live Zernio key is configured.
                throw new \RuntimeException('SIMULATED: Zernio answered from the sandbox (placeholder API key) — launch held, campaign remains pending.');
            }

            throw new \RuntimeException('Zernio launch failed: ' . json_encode($res['data'] ?? ($res['error'] ?? 'unknown')));
        }

        $spec['gateway']             = 'zernio';
        $spec['zernio_campaign_id']  = $zid;
        $spec['zernio_ad_account_id']= $adAccountId;
        $spec['zernio_status_code']  = (int)($res['status_code'] ?? 200);
        $spec['queued_job_id']       = (int)$payload['job_id'];
        $spec['launched_at']         = date('c');

        db()->update('ad_campaigns', [
            'status'               => 'active',
            'zernio_campaign_id'   => $zid,
            'zernio_ad_account_id' => $adAccountId,
            'targeting_spec'       => json_encode($spec),
            'updated_at'           => date('Y-m-d H:i:s'),
        ], 'id = ?', [$campaignId]);

        // Email-first: the campaign is genuinely live — tell the user.
        try {
            require_once __DIR__ . '/notifier.php';
            Notifier::user((int)$campaign['user_id'], 'ad_published', [
                'campaign_name' => (string)($campaign['campaign_name'] ?? 'Campaign'),
                'campaign_id'   => $zid,
                'platforms'     => implode(', ', array_map('ucfirst', array_map('strval', $platforms))),
                'budget'        => number_format((float)($campaign['budget_amount'] ?? 0), 2),
                'link'          => (defined('BASE_URL') ? BASE_URL : '') . '/create-ad',
                'time'          => date('c'),
            ]);
        } catch (\Throwable $mailErr) {
            error_log('Ad-published email failed: ' . $mailErr->getMessage());
        }

        $log("[Worker] Campaign #{$campaignId} launched on Zernio (ID: {$zid}).");
    }    /** Pause/resume a mapped campaign via Zernio; persist only on confirmation. */
    private static function handleZernioToggleStatus(array $payload, callable $log): void
    {
        $campaignId = (int)($payload['internal_campaign_id'] ?? 0);
        $targetStatus = strtolower((string)($payload['target_status'] ?? ''));
        if ($campaignId <= 0 || !in_array($targetStatus, ['active', 'paused'], true)) {
            throw new \RuntimeException('zernio_toggle_status requires internal_campaign_id + target_status (active|paused).');
        }

        $campaign = db()->fetchOne(
            "SELECT id, status, zernio_campaign_id, targeting_spec FROM ad_campaigns WHERE id = ?",
            [$campaignId]
        );
        if (!$campaign) {
            throw new \RuntimeException("Campaign #{$campaignId} no longer exists.");
        }
        if (empty($campaign['zernio_campaign_id'])) {
            // Not mapped yet (e.g. launch still queued) — nothing to confirm;
            // fall back to launching first by failing this attempt.
            throw new \RuntimeException("Campaign #{$campaignId} has no zernio_campaign_id mapping yet.");
        }

        // Idempotency: if the currently confirmed status already equals the
        // target (e.g. the toggle was applied by another job), the gateway
        // call is unnecessary — record success without touching anything.
        if (($campaign['status'] ?? '') === $targetStatus) {
            $log("[Worker] Campaign #{$campaignId} is already {$targetStatus} — toggle job resolved as no-op.");
            return;
        }

        // The Zernio status write needs the posting platform (required body
        // field on PUT /v1/ads/campaigns/{id}/status); derive it from the
        // campaign's stored launch spec.
        $toggleSpec = json_decode((string)($campaign['targeting_spec'] ?? '{}'), true) ?: [];
        $togglePlatform = (string)($payload['platform'] ?? '');
        if ($togglePlatform === '' && !empty($toggleSpec['platforms']) && is_array($toggleSpec['platforms'])) {
            $togglePlatform = (string)($toggleSpec['platforms'][0] ?? '');
        }

        $res = ThirdPartyGateway::setAdCampaignStatus((string)$campaign['zernio_campaign_id'], $targetStatus, $togglePlatform);

        if (!empty($res['data']['simulated'])) {
            throw new \RuntimeException('SIMULATED: Zernio sandbox status toggle — local status left unchanged (source of truth preserved).');
        }

        if (empty($res['success'])) {
            throw new \RuntimeException('Zernio refused the ' . $targetStatus . ' toggle; status unchanged.');
        }

        db()->update('ad_campaigns', [
            'status'     => $targetStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$campaignId]);

        $log("[Worker] Campaign #{$campaignId} {$targetStatus} confirmed by Zernio.");
    }

    /**
     * Deliver one queued conversion event through the Zernio Ads Engine
     * (one gateway call per destination pixel — Meta + TikTok are routing
     * labels; Zernio holds the platform credentials).
     */
    private static function handleZernioSendConversion(array $payload, callable $log): void
    {
        require_once __DIR__ . '/server_tracking.php';

        $rowId = (int)($payload['event_row_id'] ?? 0);
        if ($rowId <= 0) {
            throw new \RuntimeException('zernio_send_conversion payload missing event_row_id.');
        }

        // Idempotency: already-dispatched (non-queued) events resolve as
        // no-ops so worker retries never double-deliver.
        $row = db()->fetchOne("SELECT * FROM server_tracking_events WHERE id = ?", [$rowId]);
        if (!$row) {
            $log('[Worker] Conversion event row #' . $rowId . ' no longer exists — nothing to do.');
            return;
        }
        $metaStatus   = (string)$row['meta_capi_status'];
        $tiktokStatus = (string)$row['tiktok_events_status'];
        $final = static function (string $s): bool {
            return $s !== 'queued' && $s !== 'pending';
        };
        if ($final($metaStatus) && $final($tiktokStatus)) {
            $log('[Worker] Event ' . ($row['event_id'] ?? '#') . ' already delivered (' . $metaStatus . ' / ' . $tiktokStatus . ') — no-op.');
            return;
        }

        $res = ServerTrackingEngine::dispatchQueuedEvent($row, $log);
        $log('[Worker] Event ' . ($row['event_id'] ?? ('#' . $rowId)) . ' routed via Zernio. Meta: ' . $res['meta'] . ' | TikTok: ' . $res['tiktok']);
    }

    // ------------------------------------------------------------------
    //  Internals
    // ------------------------------------------------------------------

    /**
     * Retry with exponential backoff, or terminate with an honest error log
     * and a user notification.
     *
     * @return int 1 when the job was recorded failed/backed-off.
     */
    private static function failJob(array $job, \Throwable $e, callable $log): int
    {
        $jobId = (int)$job['id'];
        $attempts = (int)$job['attempts'] + 1;
        $maxAttempts = (int)$job['max_attempts'];
        $message = mb_substr($e->getMessage(), 0, 900);

        if ($attempts >= $maxAttempts || str_starts_with($message, 'SIMULATED:')) {
            // Terminal failure (retries exhausted, or a sandbox hold that
            // only a live key / retryFailed() can clear).
            db()->update('async_job_queue', [
                'status'       => 'failed',
                'attempts'     => $attempts,
                'error_log'    => $message,
                'processed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$jobId]);
            $log("[Worker] Job #{$jobId} FAILED (terminal, attempt {$attempts}/{$maxAttempts}): {$message}");
            self::notifyFailure((int)$job['user_id'], (string)$job['job_type'], $message);
            return 1;
        }

        // Exponential backoff: 30s, 60s, 120s, 240s, ... capped at 1h.
        $delay = min(self::BACKOFF_MAX_SECONDS, self::BACKOFF_BASE_SECONDS * (2 ** $attempts));
        db()->update('async_job_queue', [
            'status'       => 'pending',
            'attempts'     => $attempts,
            'error_log'    => $message,
            'scheduled_at' => date('Y-m-d H:i:s', time() + $delay),
        ], 'id = ?', [$jobId]);
        $log("[Worker] Job #{$jobId} retry scheduled in {$delay}s (attempt {$attempts}/{$maxAttempts}): {$message}");
        return 1;
    }

    private static function notifyFailure(int $userId, string $jobType, string $message): void
    {
        try {
            db()->insert('notifications', [
                'user_id'    => $userId,
                'title'      => 'Background job failed',
                'body'       => 'Your ' . str_replace('_', ' ', $jobType) . ' job could not be completed: ' . mb_substr($message, 0, 300),
                'type'       => 'system',
                'is_read'    => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('AsyncQueue notification insert failed: ' . $e->getMessage());
        }
    }

    /** Requeue jobs stuck in 'processing' from a crashed worker. */
    private static function reclaimStaleProcessing(callable $log): void
    {
        try {
            $n = db()->update(
                'async_job_queue',
                [
                    'status'     => 'pending',
                    'error_log'  => 'Reclaimed: worker died mid-execution (stale lock)',
                ],
                "status = 'processing' AND last_attempt_at < ?",
                [date('Y-m-d H:i:s', time() - self::STALE_PROCESSING_SECONDS)]
            );
            if ($n > 0) {
                $log("[Worker] Reclaimed {$n} stale 'processing' job(s).");
            }
        } catch (\Throwable $e) {
            error_log('AsyncQueue stale reclaim failed: ' . $e->getMessage());
        }
    }
}
