<?php declare(strict_types=1);

/**
 * Verified Directory & Marketplace API Endpoint
 * Adsbooster.net REST API v1
 */

require_once __DIR__ . '/bootstrap.php';

$userId = (int)$auth['user']['id'];
$body = getRequestBody();

// ── GET /api/v1/directory.php: Query listings ──
if ($method === 'GET') {
    $category = trim((string)($_GET['category'] ?? ''));
    $query = trim((string)($_GET['q'] ?? ''));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

    $sql = "SELECT id, title, category, address, phone, whatsapp, rating, views_count AS reviews_count, seller_verified AS is_verified, featured_image AS cover_image, website_url, created_at
            FROM marketplace_items WHERE status = 'active'";
    $params = [];

    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    if (!empty($query)) {
        $sql .= " AND (title LIKE ? OR address LIKE ? OR description LIKE ?)";
        $params[] = "%{$query}%";
        $params[] = "%{$query}%";
        $params[] = "%{$query}%";
    }

    $sql .= " ORDER BY is_verified DESC, rating DESC LIMIT {$limit}";
    $listings = db()->fetchAll($sql, $params);

    jsonResponse(200, [
        'success'  => true,
        'count'    => count($listings),
        'listings' => $listings,
    ]);
}

// ── POST /api/v1/directory.php: Submit new listing ──
if ($method === 'POST') {
    $title       = trim((string)($body['title'] ?? ''));
    $category    = trim((string)($body['category'] ?? 'Retail'));
    $address     = trim((string)($body['address'] ?? 'Worldwide / Online'));
    $phone       = trim((string)($body['phone'] ?? ''));
    $whatsapp    = trim((string)($body['whatsapp'] ?? ''));
    $description = trim((string)($body['description'] ?? ''));
    $websiteUrl  = trim((string)($body['website_url'] ?? ''));

    if (empty($title)) {
        jsonResponse(400, ['success' => false, 'error' => 'ValidationFailed', 'message' => 'Listing "title" is required.']);
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();

    try {
        $listingId = db()->insert('marketplace_listings', [
            'user_id'      => $userId,
            'title'        => $title,
            'slug'         => $slug,
            'category'     => $category,
            'address'      => $address,
            'phone'        => $phone,
            'whatsapp'     => $whatsapp,
            'description'  => $description,
            'website_url'  => $websiteUrl,
            'status'       => 'active',
            'is_verified'  => 1,
            'rating'       => 5.0,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        jsonResponse(201, [
            'success'    => true,
            'listing_id' => $listingId,
            'slug'       => $slug,
            'message'    => 'Directory listing published successfully.',
        ]);
    } catch (\Throwable $e) {
        jsonResponse(500, ['success' => false, 'error' => 'DatabaseError', 'message' => $e->getMessage()]);
    }
}

jsonResponse(405, ['success' => false, 'error' => 'MethodNotAllowed']);
