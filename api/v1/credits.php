<?php declare(strict_types=1);

/**
 * REST API v1: Credits & Balances
 * Adsbooster.net
 *
 * GET /api/v1/credits.php - Retrieve ad credits, AI credits, and wallet history
 */

require_once __DIR__ . '/bootstrap.php';

$userId = (int)$auth['user']['id'];

if ($method !== 'GET') {
    jsonResponse(405, ['success' => false, 'error' => 'Method not allowed. Use GET.']);
}

$user = db()->fetchOne(
    "SELECT id, full_name, email, ad_credits, ai_credits, plan_id, plan_expiry, billing_cycle,
            auto_refill_enabled, auto_refill_threshold, auto_refill_amount
     FROM users WHERE id = ?",
    [$userId]
);

$recentTransactions = db()->fetchAll(
    "SELECT id, type, amount, currency, gateway, status, invoice_number, created_at
     FROM transactions
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 10",
    [$userId]
);

jsonResponse(200, [
    'success' => true,
    'user' => [
        'id'        => (int)$user['id'],
        'name'      => $user['full_name'],
        'email'     => $user['email'],
        'plan_id'   => $user['plan_id'] ? (int)$user['plan_id'] : null,
        'plan_expiry' => $user['plan_expiry'],
    ],
    'balances' => [
        'ad_credits_usd' => (float)$user['ad_credits'],
        'ai_credits'     => (int)$user['ai_credits'],
    ],
    'auto_refill' => [
        'enabled'   => (bool)($user['auto_refill_enabled'] ?? false),
        'threshold' => (float)($user['auto_refill_threshold'] ?? 10.00),
        'amount'    => (float)($user['auto_refill_amount'] ?? 50.00),
    ],
    'recent_transactions' => $recentTransactions,
]);
