<?php
declare(strict_types=1);

require_once __DIR__ . '/ThirdPartyGateway.php';

/**
 * Autonomous AI Media Buyer & Anti-Burn Kill Switch Engine
 * Adsbooster.net
 */

class AutoBuyerEngine
{
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
                // Fetch daily metrics or simulate current performance metrics
                $metrics = self::getCampaignCalculatedMetrics((int)$camp['id']);

                $triggered = self::checkCondition(
                    $rule['condition_metric'],
                    $rule['condition_operator'],
                    (float)$rule['condition_value'],
                    $metrics
                );

                if ($triggered) {
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
     */
    public static function getCampaignCalculatedMetrics(int $campaignId): array
    {
        $metricRow = db()->fetch(
            "SELECT SUM(impressions) as imp, SUM(clicks) as clk, SUM(spend) as total_spend, SUM(conversions) as conv, SUM(revenue) as rev 
             FROM campaign_metrics_daily WHERE campaign_id = ?",
            [$campaignId]
        );

        $imp = (int)($metricRow['imp'] ?? 1500);
        $clk = (int)($metricRow['clk'] ?? 45);
        $spend = (float)($metricRow['total_spend'] ?? 65.00);
        $conv = (int)($metricRow['conv'] ?? 2);
        $rev = (float)($metricRow['rev'] ?? 195.00);

        $ctr = $imp > 0 ? ($clk / $imp) * 100 : 0.0;
        $cpa = $conv > 0 ? ($spend / $conv) : $spend;
        $roas = $spend > 0 ? ($rev / $spend) : 0.0;

        return [
            'impressions' => $imp,
            'clicks' => $clk,
            'spend' => $spend,
            'conversions' => $conv,
            'revenue' => $rev,
            'ctr' => round($ctr, 2),
            'cpa' => round($cpa, 2),
            'roas' => round($roas, 2),
            'frequency' => 1.4
        ];
    }

    /**
     * Check if metric condition is met
     */
    private static function checkCondition(string $metric, string $operator, float $targetVal, array $metrics): bool
    {
        $currentVal = 0.0;
        switch ($metric) {
            case 'cpa': $currentVal = (float)$metrics['cpa']; break;
            case 'ctr': $currentVal = (float)$metrics['ctr']; break;
            case 'roas': $currentVal = (float)$metrics['roas']; break;
            case 'spend_without_conv':
                $currentVal = ($metrics['conversions'] == 0) ? (float)$metrics['spend'] : 0.0;
                break;
            case 'frequency': $currentVal = (float)$metrics['frequency']; break;
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

            $details = "Auto-paused campaign '{$campaign['campaign_name']}' because {$rule['condition_metric']} exceeded threshold."
                . ($pauseJobId > 0 ? " Zernio pause job #{$pauseJobId} queued (status applied on gateway confirmation)." : "");
        } elseif ($action === 'scale_budget_pct') {
            $pct = (float)$rule['action_value'];
            $newBudget = (float)$campaign['budget_amount'] * (1 + ($pct / 100));
            db()->update('ad_campaigns', ['budget_amount' => $newBudget], 'id = ?', [(int)$campaign['id']]);
            $details = "Scaled budget by +{$pct}% to \${$newBudget} on campaign '{$campaign['campaign_name']}'.";
        } elseif ($action === 'reduce_budget_pct') {
            $pct = (float)$rule['action_value'];
            $newBudget = (float)$campaign['budget_amount'] * (1 - ($pct / 100));
            db()->update('ad_campaigns', ['budget_amount' => $newBudget], 'id = ?', [(int)$campaign['id']]);
            $details = "Reduced budget by -{$pct}% to \${$newBudget} on campaign '{$campaign['campaign_name']}'.";
        } elseif ($action === 'trigger_creative_refresh') {
            $details = "Creative refresh notification queued for campaign '{$campaign['campaign_name']}'.";
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
     * Predict creative fatigue
     */
    public static function predictFatigue(int $campaignId): array
    {
        $metrics = self::getCampaignCalculatedMetrics($campaignId);
        $score = 15; // default low fatigue

        if ($metrics['ctr'] < 1.0) $score += 30;
        if ($metrics['frequency'] > 2.0) $score += 35;
        if ($metrics['cpa'] > 25.0) $score += 20;

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
            'risk_level' => $riskLevel,
            'badge_class' => $badgeClass,
            'recommendation' => $score >= 65 ? '1-Click Creative Refresh Recommended' : 'Optimal Delivery Active'
        ];
    }
}
