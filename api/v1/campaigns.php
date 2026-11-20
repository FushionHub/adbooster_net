<?php declare(strict_types=1);

/**
 * REST API v1: Campaigns Endpoint
 * Adsbooster.net
 *
 * GET    /api/v1/campaigns.php           - List user's campaigns
 * GET    /api/v1/campaigns.php?id={id}   - Retrieve single campaign
 * POST   /api/v1/campaigns.php           - Create new ad campaign
 * PUT    /api/v1/campaigns.php?id={id}   - Update / Pause / Resume campaign
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../modules/ThirdPartyGateway.php';

$userId = (int)$auth['user']['id'];
$campaignId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($segments[1]) ? (int)$segments[1] : null);

switch ($method) {
    case 'GET':
        handleListCampaigns($userId, $campaignId);
        break;
    case 'POST':
        handleCreateCampaign($userId);
        break;
    case 'PUT':
    case 'PATCH':
        if ($campaignId === null) {
            jsonResponse(400, ['success' => false, 'error' => 'Campaign ID is required (e.g. ?id=123).']);
        }
        handleUpdateCampaign($userId, $campaignId);
        break;
    default:
        jsonResponse(405, ['success' => false, 'error' => 'Method not allowed.']);
}

function handleListCampaigns(int $userId, ?int $campaignId): void
{
    if ($campaignId !== null) {
        $campaign = db()->fetchOne(
            "SELECT id, campaign_name, campaign_type, objective, budget_type,
                    budget_amount, status, autopilot_enabled, zernio_campaign_id, zernio_ad_account_id,
                    created_at, updated_at
             FROM ad_campaigns
             WHERE id = ? AND user_id = ?",
            [$campaignId, $userId]
        );

        if ($campaign === null) {
            jsonResponse(404, ['success' => false, 'error' => 'Campaign not found.']);
        }

        jsonResponse(200, ['success' => true, 'campaign' => $campaign]);
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? null;

    $sql = "SELECT id, campaign_name, campaign_type, objective, budget_type,
                   budget_amount, status, autopilot_enabled, zernio_campaign_id, zernio_ad_account_id,
                   created_at, updated_at
            FROM ad_campaigns
            WHERE user_id = ?";
    $params = [$userId];

    if ($status !== null && in_array($status, ['pending', 'active', 'paused', 'completed', 'rejected'], true)) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $total = db()->count('ad_campaigns', 'user_id = ?' . ($status !== null ? " AND status = '" . $status . "'" : ''), [$userId]);

    $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
    $campaigns = db()->fetchAll($sql, $params);

    jsonResponse(200, [
        'success'    => true,
        'campaigns'  => $campaigns,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

function handleCreateCampaign(int $userId): void
{
    $body = getRequestBody();

    $required = ['campaign_name', 'campaign_type'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            jsonResponse(400, ['success' => false, 'error' => "Missing required field: {$field}"]);
        }
    }

    $validTypes = ['meta', 'instagram', 'tiktok', 'google_ads', 'snapchat', 'combo_pack'];
    if (!in_array($body['campaign_type'], $validTypes, true)) {
        jsonResponse(400, ['success' => false, 'error' => 'Invalid campaign type. Valid: ' . implode(', ', $validTypes)]);
    }

    $budgetType = $body['budget_type'] ?? 'daily';
    $budgetAmount = (float)($body['budget_amount'] ?? $body['budget'] ?? 15000.00);
    if ($budgetAmount <= 0) {
        jsonResponse(400, ['success' => false, 'error' => 'Budget amount must be greater than zero.']);
    }

    require_once __DIR__ . '/../../modules/rate_sheet.php';

    // The budget is NAIRA (the platform currency) and the wallet is the ad
    // budget: a daily budget commits daily × timeline days, a lifetime budget
    // commits its full amount. The same number is debited here and refunded
    // by the worker if the gateway permanently rejects the launch.
    $campaignDays = max(1, min(365, (int)($body['campaign_days'] ?? 30)));
    $committedNgn = RateSheet::committedCampaignCost($budgetAmount, (string)$budgetType, $campaignDays);

    $wallet = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$userId]);
    $balance = (float)($wallet['ad_credits'] ?? 0);
    if ($balance < $committedNgn) {
        jsonResponse(402, ['success' => false, 'error' => 'Insufficient ad credits. This campaign commits '
            . price_ngn_fmt($committedNgn) . ' but the wallet holds ' . price_ngn_fmt($balance)
            . '. Top up the wallet before creating it.', 'required_ngn' => $committedNgn, 'balance_ngn' => $balance]);
    }

    $campaignId = 0;
    $jobId = 0;
    $zPlatforms = [];
    $adAccountId = '';
    try {
        db()->query("BEGIN");

        // Debit the committed budget first — inside the same transaction as
        // the campaign row, so a failure can never leave a free campaign.
        db()->query(
            "UPDATE users SET ad_credits = ad_credits - ? WHERE id = ?",
            [$committedNgn, $userId]
        );

        $campaignId = db()->insert('ad_campaigns', [
            'user_id'            => $userId,
            'campaign_name'      => trim($body['campaign_name']),
            'campaign_type'      => $body['campaign_type'],
            'objective'          => trim($body['objective'] ?? 'traffic'),
            'budget_type'        => $budgetType,
            'budget_amount'      => $budgetAmount,
            'targeting_spec'     => !empty($body['targeting_spec']) ? json_encode($body['targeting_spec']) : null,
            'headline'           => trim($body['headline'] ?? ''),
            'primary_text'       => trim($body['primary_text'] ?? ''),
            'destination_url'    => trim($body['destination_url'] ?? ''),
            'creative_media_url' => trim($body['creative_media_url'] ?? '') ?: null,
            'status'             => 'pending',
            'autopilot_enabled'  => (int)($body['autopilot_enabled'] ?? 1),
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        db()->insert('transactions', [
            'user_id'     => $userId,
            'gateway'     => 'wallet',
            'type'        => 'ad_campaign_fee',
            'amount'      => $committedNgn,
            'currency'    => 'NGN',
            'status'      => 'successful',
        ]);

        // ── Launch ASYNCHRONOUSLY via the Zernio queue ──
        // This endpoint never performs a synchronous external call: it enqueues
        // a zernio_launch_ad job and replies instantly; the worker performs the
        // gateway launch and only then records the real Zernio mapping.
        $platformRoutes = [
            'meta'       => ['meta'],
            'instagram'  => ['meta'],
            'tiktok'     => ['tiktok'],
            'google_ads' => ['google'],
            'snapchat'   => ['snapchat'],
            'combo_pack' => ['meta', 'tiktok', 'google', 'snapchat'],
        ];
        $zPlatforms = $platformRoutes[$body['campaign_type']] ?? ['meta'];

        $adAccount = db()->fetchOne(
            "SELECT external_account_id FROM connected_accounts
             WHERE user_id = ? AND gateway_provider = 'zernio' AND account_type = 'ad_account' AND status = 'connected'
             ORDER BY id DESC LIMIT 1",
            [$userId]
        );
        $adAccountId = !empty($adAccount['external_account_id']) ? (string)$adAccount['external_account_id'] : 'zern_act_' . $userId;

        // Persist routing telemetry now; Zernio's campaign id is recorded by
        // the worker exclusively from the confirmed gateway answer. The launch
        // fee is the exact wallet debit, so a rejected launch refunds precisely
        // what was taken.
        $spec = is_array($body['targeting_spec'] ?? null) ? $body['targeting_spec'] : [];
        $spec['gateway']              = 'zernio';
        $spec['zernio_ad_account_id'] = $adAccountId;
        $spec['platforms']            = $zPlatforms;
        $spec['created_via']          = 'api_v1';
        $spec['campaign_days']        = $campaignDays;
        $spec['daily_budget_ngn']     = RateSheet::dailyBudget($committedNgn, $campaignDays);
        $spec['launch_fee_ngn']       = $committedNgn;
        db()->update('ad_campaigns', [
            'zernio_ad_account_id' => $adAccountId,
            'targeting_spec'       => json_encode($spec),
        ], 'id = ?', [$campaignId]);

        // The queue row is inserted INSIDE the same transaction as the wallet
        // debit: when the queue is unavailable the whole thing rolls back, so
        // no money is taken for a campaign that could not be queued (and no
        // job exists that would launch it later).
        require_once __DIR__ . '/../../modules/AsyncQueue.php';
        $jobId = AsyncQueue::push($userId, 'zernio_launch_ad', [
            'internal_campaign_id' => $campaignId,
            'ad_account_id'        => $adAccountId,
            'platforms'            => $zPlatforms,
        ], null, 'campaign:' . $campaignId);

        if ($jobId <= 0) {
            throw new \RuntimeException('queue-unavailable');
        }

        db()->query("COMMIT");
    } catch (\Throwable $e) {
        try {
            db()->query("ROLLBACK");
        } catch (\Throwable $ignoreRollback) {
            // No open transaction — nothing to undo.
        }
        error_log('API campaign create failed: ' . $e->getMessage());
        if ($e->getMessage() === 'queue-unavailable') {
            jsonResponse(503, ['success' => false, 'error' => 'The background queue is unavailable, so the campaign was not created. No ad credits were taken.']);
        }
        jsonResponse(500, ['success' => false, 'error' => 'Could not create the campaign. No ad credits were taken.']);
    }

    jsonResponse(202, [
        'success'     => true,
        'message'     => 'Campaign queued. Background workers are provisioning the launch via the Zernio Ads Gateway; the campaign becomes active when Zernio confirms it.',
        'job_id'      => $jobId,
        'campaign_id' => $campaignId,
        'campaign'    => [
            'id'            => $campaignId,
            'name'          => trim($body['campaign_name']),
            'type'          => $body['campaign_type'],
            'budget_amount' => $budgetAmount,
            'committed_ngn' => $committedNgn,
            'status'        => 'pending',
            'zernio_ad_account_id' => $adAccountId,
        ]
    ]);
}

function handleUpdateCampaign(int $userId, int $campaignId): void
{
    $campaign = db()->fetchOne(
        "SELECT * FROM ad_campaigns WHERE id = ? AND user_id = ?",
        [$campaignId, $userId]
    );

    if ($campaign === null) {
        jsonResponse(404, ['success' => false, 'error' => 'Campaign not found.']);
    }

    require_once __DIR__ . '/../../modules/rate_sheet.php';

    $body = getRequestBody();

    $allowedFields = [
        'campaign_name', 'objective',
        'headline', 'primary_text', 'destination_url', 'creative_media_url',
        'autopilot_enabled',
    ];

    $updates = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $body)) {
            $updates[$field] = $body[$field];
        }
    }

    // ── Budget (₦) + wallet ───────────────────────────────────────
    // The budget is naira and the wallet is the spend ceiling. What the
    // campaign commits is daily × timeline days (a lifetime budget commits
    // its full amount) — the same number the launcher debited.
    $budgetType   = (string)($body['budget_type'] ?? ($campaign['budget_type'] ?? 'daily'));
    $budgetAmount = array_key_exists('budget_amount', $body)
        ? (float)$body['budget_amount']
        : (float)($campaign['budget_amount'] ?? 0);

    if (!in_array($budgetType, ['daily', 'lifetime'], true)) {
        jsonResponse(400, ['success' => false, 'error' => 'budget_type must be daily or lifetime.']);
    }
    if ($budgetAmount <= 0) {
        jsonResponse(400, ['success' => false, 'error' => 'Budget amount must be greater than zero.']);
    }

    $spec = json_decode((string)($campaign['targeting_spec'] ?? '{}'), true) ?: [];
    $campaignDays = max(1, min(365, (int)($body['campaign_days'] ?? ($spec['campaign_days'] ?? 30))));
    $oldCommitted = RateSheet::committedCampaignCost(
        (float)($campaign['budget_amount'] ?? 0),
        (string)($campaign['budget_type'] ?? 'daily'),
        $campaignDays
    );
    $newCommitted = RateSheet::committedCampaignCost($budgetAmount, $budgetType, $campaignDays);
    $walletDelta  = round($newCommitted - $oldCommitted, 2);
    $budgetChanged = abs($walletDelta) >= 0.01;
    $isLive = !empty($campaign['zernio_campaign_id']);

    $statusJobId  = 0;
    $budgetJobId  = 0;
    $statusQueued = null;

    // Pause / Resume MUST happen on Zernio's side; ASYNCHRONOUSLY. The
    // request enqueues a zernio_toggle_status job (202) and the local
    // status is persisted only when the worker records Zernio's
    // confirmation — the table never diverges from gateway truth.
    if (isset($body['status'])) {
        $newStatus = $body['status'];
        if (!in_array($newStatus, ['active', 'paused'], true)) {
            jsonResponse(400, ['success' => false, 'error' => 'Can only pause or resume a campaign (status: active/paused).']);
        }

        if (empty($campaign['zernio_campaign_id'])) {
            jsonResponse(409, ['success' => false, 'error' => "Campaign not yet mapped to the Zernio Ads Engine — the launch job is queued. Fetch the campaign again once the worker confirms the launch."]);
        }

        require_once __DIR__ . '/../../modules/AsyncQueue.php';
        $statusJobId = AsyncQueue::push($userId, 'zernio_toggle_status', [
            'internal_campaign_id' => $campaignId,
            'zernio_campaign_id'   => (string)$campaign['zernio_campaign_id'],
            'target_status'        => $newStatus,
        ], null, 'campaign-toggle:' . $campaignId);

        if ($statusJobId <= 0) {
            jsonResponse(503, ['success' => false, 'error' => 'The background queue is temporarily unavailable. Status left unchanged — retry shortly.']);
        }

        $statusQueued = $newStatus;
    }

    if ($budgetChanged && $isLive) {
        // A live campaign is changed on the ad network FIRST: the gateway
        // confirms the new budget, and only then do the local row and the
        // wallet follow (worker-side).
        $wallet  = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [$userId]);
        $balance = (float)($wallet['ad_credits'] ?? 0);
        if ($walletDelta > 0 && $balance < $walletDelta) {
            jsonResponse(402, ['success' => false, 'error' => 'Insufficient ad credits. This change needs '
                . price_ngn_fmt($walletDelta) . ' more and the wallet holds ' . price_ngn_fmt($balance)
                . '. The live budget was left unchanged.', 'required_ngn' => $walletDelta, 'balance_ngn' => $balance]);
        }

        require_once __DIR__ . '/../../modules/AsyncQueue.php';
        $budgetJobId = AsyncQueue::push($userId, 'zernio_set_budget', [
            'internal_campaign_id' => $campaignId,
            'zernio_campaign_id'   => (string)$campaign['zernio_campaign_id'],
            'new_budget_ngn'       => $budgetAmount,
            'new_budget_type'      => $budgetType,
            'campaign_days'        => $campaignDays,
            'change_token'         => bin2hex(random_bytes(8)),
        ], null, 'campaign-budget:' . $campaignId);

        if ($budgetJobId <= 0) {
            jsonResponse(503, ['success' => false, 'error' => 'The background queue is temporarily unavailable. The budget was left unchanged — retry shortly.']);
        }
    }

    if (empty($updates) && !$budgetChanged) {
        jsonResponse(400, ['success' => false, 'error' => 'No valid fields provided to update.']);
    }

    if (!empty($updates)) {
        $updates['updated_at'] = date('Y-m-d H:i:s');
        db()->update('ad_campaigns', $updates, 'id = ? AND user_id = ?', [$campaignId, $userId]);
    }

    if ($budgetChanged && !$isLive) {
        // Not launched yet → the change is applied locally and the wallet
        // follows immediately (the launch worker reads the row when it runs).
        $spec['launch_fee_ngn']   = $newCommitted;
        $spec['daily_budget_ngn'] = RateSheet::dailyBudget($newCommitted, $campaignDays);

        db()->query("BEGIN");
        try {
            if ($walletDelta > 0) {
                $affected = db()->query(
                    "UPDATE users SET ad_credits = ad_credits - ? WHERE id = ? AND ad_credits >= ?",
                    [$walletDelta, $userId, $walletDelta]
                )->rowCount();
                if ($affected < 1) {
                    throw new \RuntimeException('insufficient ad credits');
                }
            } elseif ($walletDelta < 0) {
                db()->query(
                    "UPDATE users SET ad_credits = ad_credits + ? WHERE id = ?",
                    [abs($walletDelta), $userId]
                );
            }
            db()->update('ad_campaigns', [
                'budget_type'    => $budgetType,
                'budget_amount'  => $budgetAmount,
                'targeting_spec' => json_encode($spec),
            ], 'id = ? AND user_id = ?', [$campaignId, $userId]);
            db()->query("COMMIT");
        } catch (\Throwable $e) {
            try {
                db()->query("ROLLBACK");
            } catch (\Throwable $ignoreRollback) {
                // No open transaction — nothing to undo.
            }
            if ($e->getMessage() === 'insufficient ad credits') {
                jsonResponse(402, ['success' => false, 'error' => 'Insufficient ad credits for the new budget. The campaign was left unchanged.']);
            }
            error_log('API campaign budget update failed: ' . $e->getMessage());
            jsonResponse(500, ['success' => false, 'error' => 'Could not update the campaign. The budget was left unchanged.']);
        }
    }

    $response = ['success' => true, 'message' => 'Campaign updated successfully.'];
    if ($statusJobId > 0) {
        $response['status_job_id'] = $statusJobId;
        $response['status_requested'] = $statusQueued;
        $response['current_confirmed_status'] = (string)$campaign['status'];
        $response['message'] = "{$statusQueued} request queued for Zernio (job #{$statusJobId}). The status is applied and locally persisted when the gateway confirms it.";
        $response['http_status'] = 202;
    }
    if ($budgetJobId > 0) {
        $response['budget_job_id'] = $budgetJobId;
        $response['budget_ngn'] = $budgetAmount;
        $response['message'] .= ' The new budget was sent to Zernio (job #' . $budgetJobId . ') — the campaign and your wallet follow when the gateway confirms it.';
        $response['http_status'] = 202;
    }
    if ($budgetChanged && !$isLive) {
        $response['budget_ngn'] = $budgetAmount;
        $response['wallet_delta_ngn'] = $walletDelta;
    }

    $httpStatus = (int)($response['http_status'] ?? 200);
    unset($response['http_status']);
    jsonResponse($httpStatus, $response);
}
