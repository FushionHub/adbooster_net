<?php declare(strict_types=1);

/**
 * AI UGC Image Creative API Endpoint
 * Adsbooster.net REST API v1
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../modules/ai_engine.php';

$userId = (int)$auth['user']['id'];
$body = getRequestBody();

// ── GET /api/v1/ugc.php: List recent creatives ──
if ($method === 'GET') {
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 15)));
    $creatives = db()->fetchAll(
        "SELECT id, prompt, result_output, credits_used, created_at 
         FROM ai_creatives 
         WHERE user_id = ? AND type = 'image' 
         ORDER BY created_at DESC LIMIT ?",
        [$userId, $limit]
    );

    $items = [];
    foreach ($creatives as $c) {
        $meta = json_decode($c['result_output'] ?? '{}', true) ?: [];
        $items[] = [
            'id'           => (int)$c['id'],
            'image_url'    => $meta['image_url'] ?? '',
            'product_name' => $meta['product_name'] ?? '',
            'ugc_style'    => $meta['ugc_style'] ?? '',
            'aspect_ratio' => $meta['aspect_ratio'] ?? '1:1',
            'headline'     => $meta['hook_headline'] ?? $c['prompt'],
            'ad_copy'      => $meta['ad_copy'] ?? '',
            'badge_text'   => $meta['badge_text'] ?? '',
            'seed'         => $meta['seed'] ?? 0,
            'created_at'   => $c['created_at'],
        ];
    }

    jsonResponse(200, [
        'success'   => true,
        'count'     => count($items),
        'creatives' => $items,
    ]);
}

// ── POST /api/v1/ugc.php: Generate new UGC image creative ──
if ($method === 'POST') {
    $productName    = trim((string)($body['product_name'] ?? ''));
    $ugcStyle       = trim((string)($body['ugc_style'] ?? 'selfie_hold'));
    $creatorPersona = trim((string)($body['creator_persona'] ?? 'gen_z'));
    $aspectRatio    = trim((string)($body['aspect_ratio'] ?? '1:1'));
    $targetAudience = trim((string)($body['target_audience'] ?? ''));
    $headline       = trim((string)($body['headline'] ?? ''));
    $badgeText      = trim((string)($body['badge_text'] ?? '⭐⭐⭐⭐⭐ Top Rated'));
    $seed           = (int)($body['seed'] ?? random_int(100000, 999999));

    if (empty($productName)) {
        jsonResponse(400, [
            'success' => false,
            'error'   => 'ValidationFailed',
            'message' => 'Field "product_name" is required.',
        ]);
    }

    $gen = AIEngine::generateUGCImage([
        'product_name'    => $productName,
        'ugc_style'       => $ugcStyle,
        'creator_persona' => $creatorPersona,
        'aspect_ratio'    => $aspectRatio,
        'target_audience' => $targetAudience,
        'headline'        => $headline,
        'badge_text'      => $badgeText,
        'seed'            => $seed,
    ], $userId);

    if (!$gen['success']) {
        jsonResponse(402, [
            'success' => false,
            'error'   => 'CreditCheckFailed',
            'message' => $gen['message'],
            'credits_remaining' => $gen['credits_remaining'],
        ]);
    }

    jsonResponse(201, [
        'success'           => true,
        'creative'          => $gen['data'],
        'credits_remaining' => $gen['credits_remaining'],
        'message'           => 'UGC Image Creative successfully generated.',
    ]);
}

jsonResponse(405, ['success' => false, 'error' => 'MethodNotAllowed']);
