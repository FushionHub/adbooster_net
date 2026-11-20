<?php
declare(strict_types=1);

require_once __DIR__ . '/ThirdPartyGateway.php';
// Money helpers (naira formatting) — side-effect-free, safe in every entry
// point (web, cron daemon, tests).
require_once __DIR__ . '/money.php';
// Rate sheet — the committed-budget maths (daily × timeline days).
require_once __DIR__ . '/rate_sheet.php';

/**
 * Autonomous AI Media Buyer & Anti-Burn Kill Switch Engine
 * Adsbooster.net
 */

class AutoBuyerEngine
{
    /** One action per rule per window: without it a persistently-true
     *  condition (e.g. roas >= 3) compounds the budget every daemon cycle. */
    private const ACTION_COOLDOWN_SECONDS = 3600;

    /** Hard budget clamps for scale/reduce actions. Budgets are stored in
     *  ₦ (the platform currency); the clamps mirror the previous USD guard
     *  rails (≈$1 / ≈$1000) so runaway compounding can never push a budget
     *  into absurd territory. */
    private const MAX_BUDGET_NGN = 1550000.00;
    private const MIN_BUDGET_NGN = 1500.00;
    /**
     * Get default anti-burn kill switch rules template
     */
    public static function getDefaultRulesTemplate(): array
    {
        return [
            [
                'rule_name' => '🚨 Anti-Burn: Pause if CPA > $30 after spend',
                'condition_metric' => 'cpa',
                'condition_operator' => '>',
                'condition_value' => 30.00,
                'action_type' => 'pause_campaign',
                'action_value' => 0
            ],
            [
                'rule_name' => '⚡ Auto-Scale: Boost Winner Budget +20% if ROAS > 3.0',
                'condition_metric' => 'roas',
                'condition_operator' => '>=',
                'condition_value' => 3.00,
                'action_type' => 'scale_budget_pct',
                'action_value' => 20.00
            ],
            [
                'rule_name' => '📉 Stop Fatigue: Reduce Budget -25% if CTR < 0.8%',
                'condition_metric' => 'ctr',
                'condition_operator' => '<',
                'condition_value' => 0.80,
                'action_type' => 'reduce_budget_pct',
                'action_value' => 25.00
            ],
            [
                'rule_name' => '🔄 Creative Refresh: Trigger AI Variant if Spend > $50 with 0 Conv',
                'condition_metric' => 'spend_without_conv',
                'condition_operator' => '>=',
                'condition_value' => 50.00,
                'action_type' => 'trigger_creative_refresh',
                'action_value' => 0
            ]
        ];
    }

    /**
     * Create or update rule
     */
    public static function saveRule(int $userId, array $data): int
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $campaignId = !empty($data['campaign_id']) ? (int)$data['campaign_id'] : null;

        $record = [
            'user_id' => $userId,
            'campaign_id' => $campaignId,
            'rule_name' => trim($data['rule_name'] ?? 'Custom Rule'),
            'condition_metric' => $data['condition_metric'] ?? 'cpa',
            'condition_operator' => $data['condition_operator'] ?? '>',
            'condition_value' => (float)($data['condition_value'] ?? 0),
            'action_type' => $data['action_type'] ?? 'pause_campaign',
            'action_value' => (float)($data['action_value'] ?? 0),
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
        ];

        if ($id > 0) {
            db()->update('ai_media_buyer_rules', $record, 'id = ? AND user_id = ?', [$id, $userId]);
            return $id;
        } else {
            return db()->insert('ai_media_buyer_rules', $record);
        }
    }

    /**
     * Delete rule
     */
    public static function deleteRule(int $userId, int $ruleId): bool
    {
        return db()->delete('ai_media_buyer_rules', 'id = ? AND user_id = ?', [$ruleId, $userId]) > 0;
    }

    /**
     * Evaluate rules for a user or all active campaigns (called by cron daemon)
     */
    public static function evaluateAllRules(?int $targetUserId = null): array
    {
        $sql = "SELECT r.*, u.email as user_email, u.full_name as user_name 
                FROM ai_media_buyer_rules r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.is_active = 1";
        $params = [];
        if ($targetUserId !== null) {
            $sql .= " AND r.user_id = ?";
            $params[] = $targetUserId;
        }

        $rules = db()->fetchAll($sql, $params);
        $executedLogs = [];

        foreach ($rules as $rule) {
            // Fetch campaigns for this user
            $campSql = "SELECT * FROM ad_campaigns WHERE user_id = ? AND status IN ('active', 'pending')";
            $campParams = [(int)$rule['user_id']];
            if (!empty($rule['campaign_id'])) {
                $campSql .= " AND id = ?";
                $campParams[] = (int)$rule['campaign_id'];
            }

            $campaigns = db()->fetchAll($campSql, $campParams);

            foreach ($campaigns as $camp) {
                // Fetch daily metrics (real observed data only)
                $metrics = self::getCampaignCalculatedMetrics((int)$camp['id']);

                // Skip campaigns with no observed performance data: acting on
                // all-zero (or previously, fabricated) numbers would make
                // budget/pause decisions from nothing.
                if (empty($metrics['has_data'])) {
                    continue;
                }

                $triggered = self::checkCondition(
                    $rule['condition_metric'],
                    $rule['condition_operator'],
                    (float)$rule['condition_value'],
                    $metrics
                );

                if ($triggered) {
                    // Cooldown window: skip if this rule already fired recently.
                    $lastTriggered = (string)($rule['last_triggered_at'] ?? '');
                    if ($lastTriggered !== '' && strtotime($lastTriggered) !== false
                        && (time() - (int)strtotime($lastTriggered)) < self::ACTION_COOLDOWN_SECONDS) {
                        continue;
                    }

                    $actionResult = self::executeAction($camp, $rule, $metrics);
                    $executedLogs[] = $actionResult;

                    // Update last triggered timestamp
                    db()->update('ai_media_buyer_rules', [
                        'last_triggered_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [(int)$rule['id']]);
                }
            }
        }

        return $executedLogs;
    }

    /**
     * Calculate campaign metrics (CPA, CTR, ROAS, spend, etc.)
     *
     * HONESTY CONTRACT: a metric the gateway never reported is `null`
     * (unavailable), NOT 0. A null revenue means "no purchase-value data",
     * which is not the same as "the campaign made no revenue" — reading it
     * as zero produced a fake ROAS of 0 and let rules fire on nothing.
     * Callers must treat null as "no data" and skip the comparison.
     */
    public static function getCampaignCalculatedMetrics(int $campaignId): array
    {
        // Column-aware: `revenue` and `reach` were added after the table was
        // first shipped. A deployment whose SchemaGuard cannot ALTER the table
        // (read-only DB user, locked schema) used to die with
        // "Unknown column 'revenue' in 'field list'" — the exact crash that
        // blanked the AI Media Buyer page. Probe once per process and only
        // select the columns that really exist.
        $hasRevenue = self::metricsColumnExists('revenue');
        $hasReach   = self::metricsColumnExists('reach');

        $select = "SUM(impressions) as imp, SUM(clicks) as clk, SUM(spend) as total_spend, SUM(conversions) as conv";
        if ($hasRevenue) {
            $select .= ", SUM(revenue) as rev";
        }
        if ($hasReach) {
            $select .= ", SUM(reach) as reach_sum";
        }

        $metricRow = db()->fetch(
            "SELECT {$select} FROM campaign_metrics_daily WHERE campaign_id = ?",
            [$campaignId]
        );

        // HONEST METRICS — no fabricated fallbacks. The old defaults
        // (1500 impressions, 45 clicks, $65 spend, 2 conversions, $195
        // revenue) invented performance for campaigns with no data and let
        // the daemon pause real campaigns on a fabricated CPA of $32.50.
        // With no observed data every metric is a truthful zero, and
        // evaluateAllRules() skips those campaigns entirely.
        $imp = (int)($metricRow['imp'] ?? 0);
        $clk = (int)($metricRow['clk'] ?? 0);
        $spend = (float)($metricRow['total_spend'] ?? 0.0);
        $conv = (int)($metricRow['conv'] ?? 0);
        $rev = ($hasRevenue && isset($metricRow['rev']) && $metricRow['rev'] !== null)
            ? (float)$metricRow['rev']
            : null;

        $reach = ($hasReach && isset($metricRow['reach_sum']) && $metricRow['reach_sum'] !== null)
            ? (int)$metricRow['reach_sum']
            : null;

        $hasData = ($imp > 0 || $clk > 0 || $spend > 0.0 || $conv > 0);

        $ctr = $imp > 0 ? ($clk / $imp) * 100 : null;
        $cpa = $conv > 0 ? ($spend / $conv) : ($spend > 0 ? $spend : null);
        $roas = ($spend > 0 && $rev !== null) ? ($rev / $spend) : null;
        // Frequency = impressions / reach. Both must be observed.
        $frequency = ($imp > 0 && $reach !== null && $reach > 0) ? ($imp / $reach) : null;

        return [
            'has_data'    => $hasData,
            'impressions' => $imp,
            'clicks' => $clk,
            'spend' => $spend,
            'conversions' => $conv,
            'revenue' => $rev,
            'reach'   => $reach,
            'ctr' => $ctr === null ? null : round($ctr, 2),
            'cpa' => $cpa === null ? null : round($cpa, 2),
            'roas' => $roas === null ? null : round($roas, 2),
            // Frequency needs reach + impressions; reach is only reported by
            // some ad networks, so it stays null (unavailable) otherwise.
            'frequency' => $frequency === null ? null : round($frequency, 2)
        ];
    }

    /**
     * Does `campaign_metrics_daily` really have this column?
     *
     * Probed once per process and cached. A failed probe (missing
     * information_schema access) is treated as "column absent" so the
     * caller degrades to the older, narrower SELECT instead of crashing.
     */
    private static function metricsColumnExists(string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        try {
            $stmt = db()->getConnection()->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'campaign_metrics_daily'
                   AND column_name = ?"
            );
            $stmt->execute([$column]);
            $cache[$column] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $cache[$column] = false;
        }

        return $cache[$column];
    }

    /**
     * Check if metric condition is met.
     *
     * A metric that is unavailable (null) NEVER satisfies a condition — a
     * rule must not fire because the gateway did not report the number.
     */
    private static function checkCondition(string $metric, string $operator, float $targetVal, array $metrics): bool
    {
        $currentVal = null;
        switch ($metric) {
            case 'cpa': $currentVal = $metrics['cpa'] ?? null; break;
            case 'ctr': $currentVal = $metrics['ctr'] ?? null; break;
            case 'roas': $currentVal = $metrics['roas'] ?? null; break;
            case 'spend_without_conv':
                // Observed spend with zero conversions IS a real condition.
                $currentVal = ((int)($metrics['conversions'] ?? 0) === 0 && (float)($metrics['spend'] ?? 0) > 0)
                    ? (float)$metrics['spend']
                    : 0.0;
                break;
            case 'frequency': $currentVal = $metrics['frequency'] ?? null; break;
        }

        // Unavailable metric → the condition cannot be evaluated → no trigger.
        if ($currentVal === null) {
            return false;
        }

        switch ($operator) {
            case '>': return $currentVal > $targetVal;
            case '>=': return $currentVal >= $targetVal;
            case '<': return $currentVal < $targetVal;
            case '<=': return $currentVal <= $targetVal;
            default: return false;
        }
    }

    /**
     * Execute action on campaign
     */
    private static function executeAction(array $campaign, array $rule, array $metrics): array
    {
        $action = $rule['action_type'];
        $details = "";

        if ($action === 'pause_campaign') {
            // Asynchronous pause via the Zernio queue: enqueue the toggle and
            // let cron/queue_worker.php apply it out-of-band. The local status
            // turns 'paused' only when the gateway confirms, preserving
            // Zernio as the single source of truth. Dedupe by subject key
            // also prevents re-triggering the same pause every daemon cycle.
            if (!class_exists('AsyncQueue')) {
                require_once __DIR__ . '/AsyncQueue.php';
            }
            $pendingPause = !empty($campaign['zernio_campaign_id'])
                ? AsyncQueue::hasActive('zernio_toggle_status', 'campaign-toggle:' . (int)$campaign['id'])
                : false;
            if ($pendingPause) {
                $details = "Campaign '{$campaign['campaign_name']}' pause is already queued for Zernio (worker pending) — skipped duplicate.";
                db()->insert('ai_media_buyer_logs', [
                    'user_id' => (int)$campaign['user_id'],
                    'campaign_id' => (int)$campaign['id'],
                    'rule_id' => (int)$rule['id'],
                    'action_taken' => 'skipped_pause_queued',
                    'details' => $details,
                    'metrics_snapshot' => json_encode($metrics)
                ]);
                return ['log_id' => 0, 'campaign_id' => $campaign['id'], 'action' => 'skipped_pause_queued', 'details' => $details];
            }

            $pauseJobId = !empty($campaign['zernio_campaign_id'])
                ? AsyncQueue::push(
                    (int)$campaign['user_id'],
                    'zernio_toggle_status',
                    [
                        'internal_campaign_id' => (int)$campaign['id'],
                        'zernio_campaign_id'   => (string)$campaign['zernio_campaign_id'],
                        'target_status'        => 'paused',
                    ],
                    null,
                    'campaign-toggle:' . (int)$campaign['id']
                )
                : 0;

            $details = "Pause rule matched '{$campaign['campaign_name']}' because {$rule['condition_metric']} exceeded threshold. "
                . ($pauseJobId > 0
                    ? "Zernio pause job #{$pauseJobId} queued (local status flips on gateway confirmation)."
                    : "No live Zernio campaign id — nothing was queued or changed.");
        } elseif ($action === 'scale_budget_pct' || $action === 'reduce_budget_pct') {
            $pct  = max(0.0, min(100.0, abs((float)$rule['action_value'])));
            $sign = $action === 'scale_budget_pct' ? 1.0 : -1.0;
            $newBudget = round((float)$campaign['budget_amount'] * (1 + $sign * ($pct / 100)), 2);
            // Hard clamps: the daemon runs every 15 minutes; a persistently
            // true rule must never compound a budget past sane limits (₦).
            $newBudget = min(self::MAX_BUDGET_NGN, max(self::MIN_BUDGET_NGN, $newBudget));

            if (abs($newBudget - (float)$campaign['budget_amount']) < 0.01) {
                $details = "Budget rule matched '{$campaign['campaign_name']}' but the computed budget (" . price_ngn_fmt($newBudget)
                    . ") is clamped at the " . price_ngn_fmt(self::MIN_BUDGET_NGN) . '/'
                    . price_ngn_fmt(self::MAX_BUDGET_NGN) . " limits — nothing was changed.";
            } elseif (empty($campaign['zernio_campaign_id'])) {
                $details = "Budget rule matched '{$campaign['campaign_name']}' but skipped: the campaign has no live Zernio id (still pending launch) — nothing was changed.";
            } else {
                // The wallet is the spend ceiling: ads run "as much and as
                // long as his money can do", so a scale-up is only allowed
                // when the ad credits already cover the increase. A
                // scale-down always returns the difference to the wallet.
                $spec = json_decode((string)($campaign['targeting_spec'] ?? '{}'), true) ?: [];
                $campaignDays = max(1, min(365, (int)($spec['campaign_days'] ?? 30)));
                $budgetType   = (string)($campaign['budget_type'] ?? 'daily');
                $oldCommitted = RateSheet::committedCampaignCost((float)($campaign['budget_amount'] ?? 0), $budgetType, $campaignDays);
                $newCommitted = RateSheet::committedCampaignCost($newBudget, $budgetType, $campaignDays);
                $walletDelta  = round($newCommitted - $oldCommitted, 2);
                $wallet       = db()->fetchOne("SELECT ad_credits FROM users WHERE id = ?", [(int)$campaign['user_id']]);
                $balance      = (float)($wallet['ad_credits'] ?? 0);

                if ($walletDelta > $balance) {
                    $details = "Budget rule matched '{$campaign['campaign_name']}' but skipped: the new budget needs "
                        . price_ngn_fmt($walletDelta) . ' more and the wallet holds ' . price_ngn_fmt($balance)
                        . ' — nothing was changed (ads run as much and as long as the money allows).';
                } else {
                    // Budget changes route through the Zernio queue like every
                    // other paid-platform mutation: the local row updates only
                    // on gateway confirmation (Zernio is the spend source of
                    // truth). The old code edited the local row only, so the
                    // live campaign kept its old budget forever.
                    if (!class_exists('AsyncQueue')) {
                        require_once __DIR__ . '/AsyncQueue.php';
                    }
                    $budgetJobId = AsyncQueue::push(
                        (int)$campaign['user_id'],
                        'zernio_set_budget',
                        [
                            'internal_campaign_id' => (int)$campaign['id'],
                            'zernio_campaign_id'   => (string)$campaign['zernio_campaign_id'],
                            'new_budget_ngn'       => $newBudget,
                            'new_budget_type'      => $budgetType,
                            'campaign_days'        => $campaignDays,
                            'change_token'         => bin2hex(random_bytes(8)),
                        ],
                        null,
                        'campaign-budget:' . (int)$campaign['id']
                    );
                    $details = ($action === 'scale_budget_pct' ? 'Scale +' : 'Reduce -') . $pct
                        . '% → ' . price_ngn_fmt($newBudget) . " on '{$campaign['campaign_name']}': "
                        . ($budgetJobId > 0
                            ? "Zernio budget job #{$budgetJobId} queued (local budget and wallet update on gateway confirmation)."
                            : "budget job could NOT be queued — nothing was changed.");
                }
            }
        } elseif ($action === 'trigger_creative_refresh') {
            // A REAL in-app notification — the old code logged "notification
            // queued" while queuing nothing.
            try {
                require_once __DIR__ . '/notification_service.php';
                NotificationService::create(
                    (int)$campaign['user_id'],
                    'campaign',
                    'Creative refresh recommended',
                    "Rule '{$rule['rule_name']}' flagged campaign '{$campaign['campaign_name']}' — refresh the creative to counter fatigue.",
                    '/edit-campaign.php?id=' . (int)$campaign['id']
                );
                $details = "Creative-refresh recommendation delivered as an in-app notification for campaign '{$campaign['campaign_name']}'.";
            } catch (\Throwable $notifyErr) {
                error_log('auto_buyer creative-refresh notification failed: ' . $notifyErr->getMessage());
                $details = "Creative refresh was recommended for '{$campaign['campaign_name']}' but the in-app notification could not be delivered.";
            }
        } else {
            $details = "Notification alert dispatched: {$rule['rule_name']}";
        }

        // Insert into execution logs
        $logId = db()->insert('ai_media_buyer_logs', [
            'user_id' => (int)$campaign['user_id'],
            'campaign_id' => (int)$campaign['id'],
            'rule_id' => (int)$rule['id'],
            'action_taken' => $action,
            'details' => $details,
            'metrics_snapshot' => json_encode($metrics)
        ]);

        return [
            'log_id' => $logId,
            'campaign_id' => $campaign['id'],
            'action' => $action,
            'details' => $details
        ];
    }

    /**
     * Predict creative fatigue.
     *
     * Without observed performance data there is NOTHING to predict — the
     * old code scored every empty campaign as 15/100 "Low fatigue", which
     * told the user their ads were healthy when no data existed at all.
     */
    public static function predictFatigue(int $campaignId): array
    {
        $metrics = self::getCampaignCalculatedMetrics($campaignId);

        if (empty($metrics['has_data'])) {
            return [
                'fatigue_score' => null,
                'risk_level'    => 'No data',
                'badge_class'   => 'bg-secondary',
                'recommendation' => 'No performance data yet — the ad gateway has not reported impressions, clicks or spend for this campaign. Fatigue cannot be measured until it does.',
            ];
        }

        $score = 15; // default low fatigue

        if ($metrics['ctr'] !== null && $metrics['ctr'] < 1.0) $score += 30;
        if ($metrics['frequency'] !== null && $metrics['frequency'] > 2.0) $score += 35;
        if ($metrics['cpa'] !== null && $metrics['cpa'] > 25.0) $score += 20;

        $riskLevel = 'Low';
        $badgeClass = 'bg-success';
        if ($score >= 65) {
            $riskLevel = 'High (Fatigue Imminent)';
            $badgeClass = 'bg-danger';
        } elseif ($score >= 40) {
            $riskLevel = 'Moderate';
            $badgeClass = 'bg-warning text-dark';
        }

        return [
            'fatigue_score' => min(100, $score),
            'risk_level'    => $riskLevel,
            'badge_class'   => $badgeClass,
            'recommendation' => $score >= 65 ? '1-Click Creative Refresh Recommended' : 'Optimal Delivery Active'
        ];
    }
}
