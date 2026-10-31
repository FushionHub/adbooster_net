<?php declare(strict_types=1);

/**
 * Community Reviews & Ratings API Endpoint
 * Adsbooster.net REST API v1
 */

require_once __DIR__ . '/bootstrap.php';

$userId = (int)$auth['user']['id'];
$body = getRequestBody();

// ── GET /api/v1/reviews.php: List verified platform reviews ──
if ($method === 'GET') {
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $reviews = db()->fetchAll(
        "SELECT r.id, r.rating, r.review_title as title, r.review_text, r.plan_type, r.user_role, r.created_at, 
                u.full_name as author_name, u.avatar as author_avatar
         FROM site_reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.status = 'approved'
         ORDER BY r.is_featured DESC, r.created_at DESC LIMIT {$limit}"
    );

    jsonResponse(200, [
        'success' => true,
        'count'   => count($reviews),
        'reviews' => $reviews,
    ]);
}

// ── POST /api/v1/reviews.php: Submit a review ──
if ($method === 'POST') {
    $rating = max(1, min(5, (int)($body['rating'] ?? 5)));
    $title = trim((string)($body['title'] ?? ''));
    $reviewText = trim((string)($body['review_text'] ?? ''));
    $roleTitle = trim((string)($body['user_role'] ?? 'Verified Advertiser'));

    if (empty($title)) {
        jsonResponse(400, ['success' => false, 'error' => 'ValidationFailed', 'message' => 'Review headline/title is required.']);
    }
    if (empty($reviewText) || strlen($reviewText) < 20) {
        jsonResponse(400, ['success' => false, 'error' => 'ValidationFailed', 'message' => 'Review text must be at least 20 characters long.']);
    }

    // Determine eligibility: Pro user or >= 5 days
    $userRow = db()->fetchOne("SELECT created_at, role FROM users WHERE id = ?", [$userId]);
    $createdAt = strtotime($userRow['created_at'] ?? 'now');
    $daysSinceSignup = (int)floor((time() - $createdAt) / 86400);
    $isPro = in_array($userRow['role'] ?? '', ['admin', 'pro', 'agency'], true);

    if (!$isPro && $daysSinceSignup < 5) {
        jsonResponse(403, [
            'success' => false,
            'error'   => 'EligibilityRequirement',
            'message' => 'Free accounts can submit platform reviews after 5 days of usage. Upgrade to Pro for immediate access.',
        ]);
    }

    $existing = db()->fetchOne("SELECT id FROM site_reviews WHERE user_id = ?", [$userId]);
    $planType = $isPro ? 'pro' : 'free';

    try {
        if ($existing) {
            db()->update('site_reviews', [
                'rating'       => $rating,
                'review_title' => $title,
                'review_text'  => $reviewText,
                'user_role'    => $roleTitle,
                'status'       => 'approved',
            ], 'id = ?', [$existing['id']]);
            $reviewId = (int)$existing['id'];
        } else {
            $reviewId = db()->insert('site_reviews', [
                'user_id'      => $userId,
                'rating'       => $rating,
                'review_title' => $title,
                'review_text'  => $reviewText,
                'plan_type'    => $planType,
                'user_role'    => $roleTitle,
                'status'       => 'approved',
                'is_featured'  => 1,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }


        jsonResponse(201, [
            'success'   => true,
            'review_id' => $reviewId,
            'message'   => 'Thank you! Your review has been published to the community.',
        ]);
    } catch (\Throwable $e) {
        jsonResponse(500, ['success' => false, 'error' => 'DatabaseError', 'message' => $e->getMessage()]);
    }
}

jsonResponse(405, ['success' => false, 'error' => 'MethodNotAllowed']);
