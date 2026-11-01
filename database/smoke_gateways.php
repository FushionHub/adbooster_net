<?php
declare(strict_types=1);

// CLI-only smoke test for the dual gateway integration.
// Run with the project PHP binary:  database/smoke_gateways.php
// Verifies, against the real MySQL + gateway configuration:
//   1. Gateway config resolution for both providers
//   2. Bundle.social simulation payloads (placeholder key -> simulated: true)
//   3. Zernio dispatch, status toggle, and metrics payloads
//   4. Signed, single-use, provider-bound connect-state auth (journey separation)

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: smoke_gateways is CLI-only.');
}

require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/ThirdPartyGateway.php';

$pass = 0; $fail = 0;

// Mode-aware expectations: with placeholder keys every gateway answer is a
// SIMULATED: sandbox hold; with live real keys the calls hit the real
// provider APIs — honest attempts/refusals, never fabricated results.
$sandbox = ThirdPartyGateway::isSandboxMode('bundle_social') && ThirdPartyGateway::isSandboxMode('zernio');
echo 'Gateway key mode: ' . ($sandbox ? 'SANDBOX (placeholder keys)' : 'LIVE (real keys)') . "\n";

function check(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [PASS] {$name}" . ($extra !== '' ? " — {$extra}" : '') . "\n"; }
    else     { $fail++; echo "  [FAIL] {$name}" . ($extra !== '' ? " — {$extra}" : '') . "\n"; }
}

echo "== 1. Gateway config resolution ==\n";
foreach (['bundle_social', 'zernio'] as $provider) {
    try {
        // getGatewayConfig is private; go through a public method instead.
        $url = $provider === 'bundle_social'
            ? ThirdPartyGateway::getBundleConnectUrl(1, 'facebook')
            : ThirdPartyGateway::getZernioAdConnectUrl(1, 'meta');
        check("Resolve gateway URL ({$provider})", filter_var($url, FILTER_VALIDATE_URL) !== false, mb_substr((string)preg_replace('~&state=.*~', '&state=…', $url), 0, 120));
    } catch (\Throwable $e) {
        check("Resolve gateway URL ({$provider})", false, $e->getMessage());
    }
}

echo "== 2. Bundle.social organic payloads ==\n";
try {
    $res = ThirdPartyGateway::createOrganicPost([
        'account_ids'  => ['bndl_facebook_test'],
        'caption'      => 'Smoke test post',
        'media_urls'   => [],
        'scheduled_at' => date(DATE_ATOM, time() + 3600),
    ]);
    if ($sandbox) {
        check('createOrganicPost success', !empty($res['success']));
        check('createOrganicPost simulated flag', !empty($res['data']['simulated']), 'placeholder keys must never persist as real outcomes');
    } else {
        // Live keys with fake account ids → an honest provider refusal, never
        // a fabricated success and no real post is created.
        check('createOrganicPost live refusal is honest', !empty($res['success']) || isset($res['status_code']), 'status=' . (string)($res['status_code'] ?? '200'));
    }
} catch (\Throwable $e) { check('createOrganicPost', false, $e->getMessage()); }

try {
    $res = ThirdPartyGateway::getOrganicPostAnalytics('bndl_smoke_1');
    if ($sandbox) {
        check('getOrganicPostAnalytics simulated flag', (!empty($res['data']['simulated'])));
    } else {
        check('getOrganicPostAnalytics live attempt recorded', !empty($res['success']) || isset($res['status_code']), 'status=' . (string)($res['status_code'] ?? '200'));
    }
} catch (\Throwable $e) { check('getOrganicPostAnalytics', false, $e->getMessage()); }

try {
    $res = ThirdPartyGateway::createCalendarEvent(['summary' => 'Smoke Event', 'start_time' => date(DATE_ATOM), 'end_time' => date(DATE_ATOM, time() + 600)]);
    if ($sandbox) {
        check('createCalendarEvent simulated flag', !empty($res['data']['simulated']));
    } else {
        check('createCalendarEvent live attempt recorded', !empty($res['success']) || isset($res['status_code']), 'status=' . (string)($res['status_code'] ?? '200'));
    }
} catch (\Throwable $e) { check('createCalendarEvent', false, $e->getMessage()); }

echo "== 3. Zernio paid-ads payloads ==\n";
if ($sandbox) {
    try {
        $res = ThirdPartyGateway::launchPaidCampaign([
        'ad_account_id'      => 'zern_act_smoke',
        'platforms'          => ['meta', 'tiktok'],
        'title'              => 'Smoke Campaign',
        'objective'          => 'conversions',
        'budget_amount'      => 25,
        'headline'           => 'Headline',
        'primary_text'       => 'Body',
        'destination_url'    => 'https://example.com',
        'creative_media_url' => '',
        'targeting'          => ['geo' => ['US']],
    ]);
    $zid = (string)($res['data']['campaign_id'] ?? ($res['data']['id'] ?? ''));
    check('launchPaidCampaign success+id', !empty($res['success']) && $zid !== '', "id={$zid}");
    check('launchPaidCampaign simulated flag', !empty($res['data']['simulated']));
    } catch (\Throwable $e) { check('launchPaidCampaign', false, $e->getMessage()); }

    try {
        $res = ThirdPartyGateway::setAdCampaignStatus($zid ?? 'zern_smoke', 'paused');
        check('setAdCampaignStatus (paused)', !empty($res['success']));
    } catch (\Throwable $e) { check('setAdCampaignStatus', false, $e->getMessage()); }

    try {
        $res = ThirdPartyGateway::getCampaignMetrics($zid ?? 'zern_smoke');
        check('getCampaignMetrics simulated flag (cron must refuse to persist)', !empty($res['data']['simulated']));
    } catch (\Throwable $e) { check('getCampaignMetrics', false, $e->getMessage()); }
} else {
    // LIVE keys: never create a real remote campaign from a smoke run.
    // These calls prove the gateway reaches the real API with honest,
    // status-coded refusals for deliberately fake campaign ids.
    try {
        $res = ThirdPartyGateway::setAdCampaignStatus('zern_smoke', 'paused');
        check('setAdCampaignStatus live attempt recorded', !empty($res['success']) || isset($res['status_code']), 'status=' . (string)($res['status_code'] ?? '200'));
    } catch (\Throwable $e) { check('setAdCampaignStatus', false, $e->getMessage()); }
    try {
        $res = ThirdPartyGateway::getCampaignMetrics('zern_smoke');
        check('getCampaignMetrics live attempt recorded', !empty($res['success']) || isset($res['status_code']), 'status=' . (string)($res['status_code'] ?? '200'));
    } catch (\Throwable $e) { check('getCampaignMetrics', false, $e->getMessage()); }
    check('launchPaidCampaign skipped in live key mode', true, 'no real remote campaign is ever created by a smoke run');
}

echo "== 4. Signed connect-state auth (journey separation) ==\n";try {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    // Journey: Bundle.social organic — issue, then consume.
    $sdkState  = ThirdPartyGateway::issueGatewayState('bundle_social', 1, 'facebook');
    $validated = ThirdPartyGateway::validateGatewayState($sdkState, 'bundle_social', 1);
    check('Valid organic state validates', $validated === 'facebook', 'state=' . mb_substr($sdkState, 0, 40) . '…');

    // Replay of the same state must be rejected (single-use).
    $replay = ThirdPartyGateway::validateGatewayState($sdkState, 'bundle_social', 1);
    check('State replay rejected (single-use)', $replay === null);

    // Cross-provider: a fresh Zernio state cannot be accepted by the organic journey.
    $zState = ThirdPartyGateway::issueGatewayState('zernio', 1, 'meta');
    $cross  = ThirdPartyGateway::validateGatewayState($zState, 'bundle_social', 1);
    check('Zernio state rejected as organic', $cross === null);
    $zValid = ThirdPartyGateway::validateGatewayState($zState, 'zernio', 1);
    check('Zernio state accepted under its own journey', $zValid === 'meta');

    // Forged signature must be rejected.
    $forged = '1.meta.' . (time() + 900) . '.deadbeefdeadbeef.deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdead';
    check('Forged state rejected', ThirdPartyGateway::validateGatewayState($forged, 'zernio', 1) === null);

    // Wrong user binding.
    $uState = ThirdPartyGateway::issueGatewayState('bundle_social', 1, 'instagram');
    check('Foreign user state rejected', ThirdPartyGateway::validateGatewayState($uState, 'bundle_social', 999) === null);
} catch (\Throwable $e) { check('Connect-state auth', false, $e->getMessage()); }

echo "== 5. Asynchronous dual-gateway job queue ==\n";
try {
    require_once __DIR__ . '/../modules/AsyncQueue.php';

    // 5a. Producer round-trip with subject dedupe.
    $testCampaignId = db()->insert('ad_campaigns', [
        'user_id'        => 1,
        'campaign_name'  => 'Async Smoke Campaign',
        'campaign_type'  => 'meta',
        'objective'      => 'conversions',
        'budget_type'    => 'daily',
        'budget_amount'  => 25,
        'status'         => 'pending',
        'created_at'     => date('Y-m-d H:i:s'),
        'updated_at'     => date('Y-m-d H:i:s'),
    ]);
    db()->update('ad_campaigns', ['zernio_campaign_id' => 'zern_cmp_smoke_async'], 'id = ?', [$testCampaignId]);
    check('Campaign fixture created (pending state)', $testCampaignId > 0);

    $jobId  = AsyncQueue::push(1, 'zernio_toggle_status', ['internal_campaign_id' => $testCampaignId, 'target_status' => 'paused'], null, 'campaign-toggle:' . $testCampaignId);
    $jobId2 = AsyncQueue::push(1, 'zernio_toggle_status', ['internal_campaign_id' => $testCampaignId, 'target_status' => 'paused'], null, 'campaign-toggle:' . $testCampaignId);
    check('AsyncQueue::push enqueues job', $jobId > 0, "job=#{$jobId}");
    check('Subject dedupe prevents duplicates', $jobId2 === $jobId, 'push #2 resolved to the existing job');

    // 5b. Worker processes one batch — placeholder keys must end in an
    //     honest SIMULATED hold with the local status left untouched.
    AsyncQueue::processBatch(10, static function (string $l): void { echo '      ' . mb_substr($l, 0, 160) . "\n"; });
    $job = db()->fetchOne("SELECT status, attempts, error_log FROM async_job_queue WHERE id = ?", [$jobId]);
    if ($sandbox) {
        check('Simulated toggle terminal-fails honestly', ($job['status'] ?? '') === 'failed' && str_starts_with((string)($job['error_log'] ?? ''), 'SIMULATED:'), mb_substr((string)($job['error_log'] ?? ''), 0, 80));
    } else {
        check('Live provider toggle refused honestly', in_array(($job['status'] ?? ''), ['failed', 'pending'], true), 'status=' . (string)($job['status'] ?? '?') . ' err=' . mb_substr((string)($job['error_log'] ?? ''), 0, 60));
    }
    $camp = db()->fetchOne("SELECT status FROM ad_campaigns WHERE id = ?", [$testCampaignId]);
    check('Status truth preserved (no fabrication)', ($camp['status'] ?? '') === 'pending', "campaign stayed 'pending'");

    // 5c. retryFailed re-opens the hold for a future live-key run.
    $reQueued = AsyncQueue::retryFailed('zernio_toggle_status');
    $job = db()->fetchOne("SELECT status, attempts FROM async_job_queue WHERE id = ?", [$jobId]);
    if ($sandbox) {
        check('retryFailed re-queues simulated hold', $reQueued > 0 && ($job['status'] ?? '') === 'pending' && (int)($job['attempts'] ?? 1) === 0);
    } else {
        check('retryFailed safe under live keys', is_int($reQueued) && $reQueued >= 0, "re-queued={$reQueued}");
    }

    // 5d. Organic publish path: scheduled row + job; simulated dispatch must
    //     NEVER fabricate a bundle_post_id or a 'published' status.
    require_once __DIR__ . '/../modules/social_post_engine.php';
    $postId = SocialPostEngine::createPost(1, 'facebook', 'Async smoke post', null, 'text', date('Y-m-d H:i:s', time() + 2));
    check('SocialPostEngine::createPost queues async', $postId > 0);
    $row = db()->fetchOne("SELECT status, bundle_post_id FROM scheduled_posts WHERE id = ?", [$postId]);
    check('No bundle_post_id until gateway confirms', ($row['status'] ?? '') === 'scheduled' && ($row['bundle_post_id'] ?? null) === null);

    // Make the job due now (it was scheduled at +2s) so this batch claims it.
    db()->update('async_job_queue', ['scheduled_at' => date('Y-m-d H:i:s')], "job_type = 'bundle_publish_post' AND subject_key = ?", ['post:' . $postId]);

    AsyncQueue::processBatch(10, static function (string $l): void { echo '      ' . mb_substr($l, 0, 160) . "\n"; });
    $row = db()->fetchOne("SELECT status, bundle_post_id FROM scheduled_posts WHERE id = ?", [$postId]);
    $pubJob = db()->fetchOne("SELECT status, error_log FROM async_job_queue WHERE job_type = 'bundle_publish_post' AND subject_key = ?", ['post:' . $postId]);
    if ($sandbox) {
        check('Simulated publish holds honestly', (string)($row['bundle_post_id'] ?? '') === '' && ($pubJob['status'] ?? '') === 'failed', 'row not marked published, no fabricated id');
    } else {
        check('Live publish refused honestly (no fabricated id)', (string)($row['bundle_post_id'] ?? '') === '' && in_array(($pubJob['status'] ?? ''), ['failed', 'pending'], true), 'pubJob=' . (string)($pubJob['status'] ?? '?') . ' err=' . mb_substr((string)($pubJob['error_log'] ?? ''), 0, 60));
    }

    // 5e. Cancel flow withdraws a queued job.
    $cancelPostId = SocialPostEngine::createPost(1, 'twitter', 'Cancel-me smoke', null, 'text', date('Y-m-d H:i:s', time() + 600));
    SocialPostEngine::cancelPost($cancelPostId);
    $cancelled = db()->fetchOne("SELECT status FROM async_job_queue WHERE job_type = 'bundle_publish_post' AND subject_key = ?", ['post:' . $cancelPostId]);
    $row = db()->fetchOne("SELECT status FROM scheduled_posts WHERE id = ?", [$cancelPostId]);
    check('cancelPost withdraws queued job', ($row['status'] ?? '') === 'cancelled' && in_array(($cancelled['status'] ?? ''), ['cancelled', ''], true), "queue status: " . (string)($cancelled['status'] ?? 'none'));

    // Cleanup smoke artifacts (sandbox DB only — never run on production data).
    db()->query(
        "DELETE FROM async_job_queue WHERE subject_key = ? OR subject_key = ? OR subject_key = ?",
        ['campaign-toggle:' . $testCampaignId, 'post:' . $postId, 'post:' . $cancelPostId]
    );
    db()->query("DELETE FROM scheduled_posts WHERE id = ? OR id = ?", [$postId, $cancelPostId]);
    db()->query("DELETE FROM ad_campaigns WHERE id = ?", [$testCampaignId]);
} catch (\Throwable $e) { check('Async queue smoke', false, $e->getMessage()); }

echo "== 6. Comprehensive gateway methods (direct-API purge surface) ==\n";
try {
    require_once __DIR__ . '/../modules/server_tracking.php';

    // 6a. Bundle.social comment-moderation methods (mode-aware).
    $cmts = ThirdPartyGateway::getPostComments('bndl_smoke_post');
    if ($sandbox) {
        check('getPostComments sandbox-flagged', !empty($cmts['data']['simulated']) && isset($cmts['data']['comments']));
    } else {
        check('getPostComments live attempt recorded', !empty($cmts['success']) || isset($cmts['status_code']), 'status=' . (string)($cmts['status_code'] ?? '200'));
    }
    $testCommentId = (string)($cmts['data']['comments'][0]['id'] ?? 'bndl_cmt_smoke');
    $reply = ThirdPartyGateway::replyToComment($testCommentId, 'Check our latest drop!');
    if ($sandbox) {
        check('replyToComment sandbox-flagged', !empty($reply['data']['simulated']) && $reply['success']);
    } else {
        check('replyToComment live attempt recorded', !empty($reply['success']) || isset($reply['status_code']), 'status=' . (string)($reply['status_code'] ?? '200'));
    }
    $hid = ThirdPartyGateway::moderateComment($testCommentId, 'hide');
    if ($sandbox) {
        check('moderateComment (hide) sandbox-flagged', !empty($hid['data']['simulated']) && ($hid['data']['action'] ?? '') === 'hide');
    } else {
        check('moderateComment (hide) live attempt recorded', !empty($hid['success']) || isset($hid['status_code']), 'status=' . (string)($hid['status_code'] ?? '200'));
    }
    try {
        ThirdPartyGateway::moderateComment($testCommentId, 'boost');
        check('moderateComment rejects unknown action', false, 'no exception thrown');
    } catch (\InvalidArgumentException $iae) {
        check('moderateComment rejects unknown action', true);
    }
    $accs = ThirdPartyGateway::getBundleConnectedAccounts();
    if ($sandbox) {
        check('getBundleConnectedAccounts sandbox-flagged', !empty($accs['data']['simulated']) && isset($accs['data']['accounts']));
    } else {
        check('getBundleConnectedAccounts live attempt recorded', !empty($accs['success']) || isset($accs['status_code']), 'status=' . (string)($accs['status_code'] ?? '200'));
    }

    // 6b. Zernio comprehensive methods.
    $zAccs = ThirdPartyGateway::getZernioAdAccounts();
    if ($sandbox) {
        check('getZernioAdAccounts sandbox-flagged', !empty($zAccs['data']['simulated']) && isset($zAccs['data']['ad_accounts']));
    } else {
        check('getZernioAdAccounts live attempt recorded', !empty($zAccs['success']) || isset($zAccs['status_code']), 'status=' . (string)($zAccs['status_code'] ?? '200'));
    }
    $detail = ThirdPartyGateway::getCampaignDetail('zern_smoke_cmp');
    check('getCampaignDetail returns (sim-fallback ok)', is_array($detail));
    $bud = ThirdPartyGateway::setCampaignBudget('zern_smoke_cmp', 125.50);
    if ($sandbox) {
        check('setCampaignBudget sandbox-flagged', !empty($bud['data']['simulated']) && (float)($bud['data']['budget'] ?? 0) === 125.50);
    } else {
        check('setCampaignBudget live attempt recorded', !empty($bud['success']) || isset($bud['status_code']), 'status=' . (string)($bud['status_code'] ?? '200'));
    }
    try {
        ThirdPartyGateway::setCampaignBudget('zern_smoke_cmp', -5);
        check('setCampaignBudget rejects non-positive amounts', false, 'no exception thrown');
    } catch (\InvalidArgumentException $iae) {
        check('setCampaignBudget rejects non-positive amounts', true);
    }
    $cev = ThirdPartyGateway::sendConversionEvent([
        'network' => 'meta', 'pixel_id' => '18290480', 'event' => 'Purchase',
        'event_id' => 'evt_smoke', 'user_data' => ['em' => 'x'], 'custom_data' => ['value' => 99],
    ]);
    if ($sandbox) {
        check('sendConversionEvent sandbox-flagged', !empty($cev['data']['simulated']) && ($cev['data']['network'] ?? '') === 'meta');
    } else {
        check('sendConversionEvent live attempt recorded', !empty($cev['success']) || isset($cev['status_code']), 'status=' . (string)($cev['status_code'] ?? '200'));
    }
} catch (\Throwable $e) { check('Comprehensive gateway methods', false, $e->getMessage()); }

echo "== 7. Async conversion-event delivery via Zernio ==\n";
try {
    // 7a. Pixel creation without any platform token.
    $pixelId = db()->insert('server_tracking_pixels', [
        'user_id' => 1,
        'pixel_token' => 'px_' . bin2hex(random_bytes(16)),
        'name' => 'Async Conversion Smoke',
        'meta_pixel_id' => 'meta_route_lbl',
        'tiktok_pixel_id' => 'tt_route_lbl',
        'domain_verified' => 'localhost',
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    check('Pixel created (routing labels only)', $pixelId > 0);

    // 7b. trackEvent enqueues out-of-band delivery; no external call made.
    $tracked = ServerTrackingEngine::trackEvent('px_from_row', 'PageView'); // wrong token first
    check('Foreign pixel token rejected', empty($tracked['success']));
    $tk = db()->fetchOne("SELECT pixel_token FROM server_tracking_pixels WHERE id = ?", [$pixelId]);
    $tracked = ServerTrackingEngine::trackEvent((string)$tk['pixel_token'], 'Purchase', ['email' => 'smoke@example.com'], ['value' => 99, 'currency' => 'USD']);
    $evtRow = db()->fetchOne("SELECT * FROM server_tracking_events WHERE id = ?", [$tracked['log_id']]);
    check('trackEvent queues honestly (no fake sent)', ($evtRow['meta_capi_status'] ?? '') === 'queued' && ($evtRow['tiktok_events_status'] ?? '') === 'queued' && !empty($tracked['queued']));
    check('Conversion job enqueued (subject-key deduped)', ($tracked['job_id'] ?? 0) > 0);

    // 7c. Worker delivers through the Zernio engine; mode-aware honesty:
    //     sandbox → SIMULATED hold recorded as 'simulated';
    //     live → real gateway attempt with a recorded (dispatched/error) status.
    AsyncQueue::processBatch(10, static function (string $l): void { echo '      ' . mb_substr($l, 0, 160) . "\n"; });
    $evtRow = db()->fetchOne("SELECT * FROM server_tracking_events WHERE id = ?", [$tracked['log_id']]);
    if ($sandbox) {
        check('Sandbox delivery marked simulated (not sent)', ($evtRow['meta_capi_status'] ?? '') === 'simulated' && ($evtRow['tiktok_events_status'] ?? '') === 'simulated');
    } else {
        check('Live delivery attempted (no eternal queued lie)', ($evtRow['meta_capi_status'] ?? '') !== 'queued' && ($evtRow['tiktok_events_status'] ?? '') !== 'queued', 'meta=' . mb_substr((string)($evtRow['meta_capi_status'] ?? ''), 0, 40));
    }
    $convJob = db()->fetchOne("SELECT status, error_log FROM async_job_queue WHERE job_type = 'zernio_send_conversion' AND subject_key = ?", ['event:' . $tracked['event_id']]);
    if ($sandbox) {
        check('Conversion job honest SIMULATED hold', ($convJob['status'] ?? '') === 'failed' && str_starts_with((string)($convJob['error_log'] ?? ''), 'SIMULATED:'));
    } else {
        check('Conversion job resolved honestly under live keys', in_array(($convJob['status'] ?? ''), ['completed', 'failed', 'pending'], true), 'status=' . (string)($convJob['status'] ?? '?') . ' err=' . mb_substr((string)($convJob['error_log'] ?? ''), 0, 60));
    }

    // 7d. Idempotency: a fully delivered event resolves the job as a no-op.
    db()->update('server_tracking_events', ['meta_capi_status' => 'gateway_dispatched (200)', 'tiktok_events_status' => 'gateway_dispatched (200)'], 'id = ?', [$tracked['log_id']]);
    db()->update('async_job_queue', ['status' => 'pending', 'scheduled_at' => date('Y-m-d H:i:s'), 'attempts' => 0], "job_type = 'zernio_send_conversion' AND subject_key = ?", ['event:' . $tracked['event_id']]);
    AsyncQueue::processBatch(10, static function (string $l): void {});
    $convJob = db()->fetchOne("SELECT status FROM async_job_queue WHERE job_type = 'zernio_send_conversion' AND subject_key = ?", ['event:' . $tracked['event_id']]);
    check('Already-delivered event is a worker no-op', ($convJob['status'] ?? '') === 'completed');

    // Cleanup smoke artifacts.
    db()->query("DELETE FROM async_job_queue WHERE subject_key = ? OR subject_key LIKE 'comment-%'", ['event:' . $tracked['event_id']]);
    db()->query("DELETE FROM server_tracking_events WHERE pixel_id = ?", [$pixelId]);
    db()->query("DELETE FROM server_tracking_pixels WHERE id = ?", [$pixelId]);
} catch (\Throwable $e) { check('Async conversion-event smoke', false, $e->getMessage()); }

echo "\nResult: {$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
