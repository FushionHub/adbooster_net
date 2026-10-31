<?php declare(strict_types=1);

/**
 * REST API v1: Social Posts Endpoint
 * Adsbooster.net
 *
 * GET    /api/v1/posts.php           - List user's scheduled & published posts
 * GET    /api/v1/posts.php?id={id}   - Retrieve single post
 * POST   /api/v1/posts.php           - Schedule a new post
 * DELETE /api/v1/posts.php?id={id}   - Cancel a scheduled post
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../modules/social_post_engine.php';

$userId = (int)$auth['user']['id'];
$postId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($segments[1]) ? (int)$segments[1] : null);

switch ($method) {
    case 'GET':
        handleListPosts($userId, $postId);
        break;
    case 'POST':
        handleCreatePost($userId);
        break;
    case 'DELETE':
        if ($postId === null) {
            jsonResponse(400, ['success' => false, 'error' => 'Post ID is required (e.g. ?id=123).']);
        }
        handleDeletePost($userId, $postId);
        break;
    default:
        jsonResponse(405, ['success' => false, 'error' => 'Method not allowed.']);
}

function handleListPosts(int $userId, ?int $postId): void
{
    if ($postId !== null) {
        $post = db()->fetchOne(
            "SELECT id, platform, post_type, caption, media_url, media_type,
                    scheduled_time, status, bundle_post_id, created_at, updated_at
             FROM scheduled_posts
             WHERE id = ? AND user_id = ?",
            [$postId, $userId]
        );

        if ($post === null) {
            jsonResponse(404, ['success' => false, 'error' => 'Post not found.']);
        }

        jsonResponse(200, ['success' => true, 'post' => $post]);
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? null;

    $sql = "SELECT id, platform, post_type, caption, media_url, media_type,
                   scheduled_time, status, bundle_post_id, created_at, updated_at
            FROM scheduled_posts
            WHERE user_id = ?";
    $params = [$userId];

    if ($status !== null && in_array($status, ['scheduled', 'published', 'failed', 'cancelled'], true)) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $total = db()->count('scheduled_posts', 'user_id = ?' . ($status !== null ? " AND status = '" . $status . "'" : ''), [$userId]);

    $sql .= " ORDER BY scheduled_time DESC LIMIT {$limit} OFFSET {$offset}";
    $posts = db()->fetchAll($sql, $params);

    jsonResponse(200, [
        'success'    => true,
        'posts'      => $posts,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

function handleCreatePost(int $userId): void
{
    $body = getRequestBody();

    $required = ['platform', 'caption'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            jsonResponse(400, ['success' => false, 'error' => "Missing required field: {$field}"]);
        }
    }

    // Organic publishing only — paid channels (snapchat, google_ads) are owned
    // by the Zernio ad gateway and never appear in the organic post API.
    $validPlatforms = ['meta', 'facebook', 'instagram', 'tiktok', 'twitter', 'linkedin', 'pinterest', 'youtube'];
    $platform = strtolower(trim($body['platform']));
    if (!in_array($platform, $validPlatforms, true)) {
        $paidBlocked = in_array($platform, ['google_ads', 'snapchat', 'combo_pack'], true);
        jsonResponse(400, ['success' => false, 'error' => $paidBlocked
            ? "Platform '{$platform}' is a paid advertising channel — manage it through the Zernio Ads API (/api/v1/campaigns.php), not the organic posts API."
            : 'Invalid organic platform. Valid: ' . implode(', ', $validPlatforms)]);
    }

    $publishNow = !empty($body['publish_now']) || (isset($body['scheduled_time']) && strtotime($body['scheduled_time']) <= (time() + 60));
    $mediaType = trim($body['media_type'] ?? 'image');
    $mediaUrl = trim($body['media_url'] ?? '') ?: null;

    if ($publishNow) {
        $liveRes = SocialPostEngine::publishLive($userId, $platform, trim($body['caption']), $mediaUrl, $mediaType);
        if (!empty($liveRes['success'])) {
            // Asynchronous path (202-style): 201 Created on the local row; the
            // Bundle.social dispatch happens out-of-band via the queue worker.
            jsonResponse(201, [
                'success'     => true,
                'status'      => 'queued',
                'message'     => 'Post queued for immediate dispatch — the Bundle.social worker is broadcasting it now.',
                'post_id'     => $liveRes['post_id'] ?? null,
                'platform'    => $platform,
                'external_id' => null,
                'note'        => 'Fetch /api/v1/posts.php?id={post_id} for the confirmed bundle_post_id when the worker reports it.',
            ]);
        } else {
            jsonResponse(400, [
                'success'  => false,
                'status'   => 'failed',
                'error'    => 'PublishFailed',
                'message'  => $liveRes['error'] ?? 'Could not queue the publish job.',
                'platform' => $platform,
            ]);
        }
    }

    $scheduledTime = !empty($body['scheduled_time']) ? trim($body['scheduled_time']) : date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $scheduledTimestamp = strtotime($scheduledTime);
    if ($scheduledTimestamp === false) {
        jsonResponse(400, ['success' => false, 'error' => 'Invalid scheduled_time format (use YYYY-MM-DD HH:MM:SS or ISO 8601).']);
    }

    try {
        $postId = SocialPostEngine::createPost(
            $userId,
            $platform,
            trim($body['caption']),
            $mediaUrl,
            $mediaType,
            date('Y-m-d H:i:s', $scheduledTimestamp)
        );
    } catch (\Throwable $e) {
        jsonResponse(500, ['success' => false, 'error' => $e->getMessage()]);
    }

    $savedPost = db()->fetchOne("SELECT bundle_post_id FROM scheduled_posts WHERE id = ?", [$postId]);

    jsonResponse(201, [
        'success'        => true,
        'message'        => 'Post scheduled — queued for dispatch via the Bundle.social worker at the requested time.',
        'post_id'        => $postId,
        // Always null at scheduling time under the async architecture: the
        // worker records the REAL provider id only from the confirmed answer.
        'bundle_post_id' => $savedPost['bundle_post_id'] ?? null,
        'scheduled_time' => date('Y-m-d H:i:s', $scheduledTimestamp),
        'platform'       => $platform
    ]);
}

function handleDeletePost(int $userId, int $postId): void
{
    $post = db()->fetchOne(
        "SELECT id, status FROM scheduled_posts WHERE id = ? AND user_id = ?",
        [$postId, $userId]
    );

    if ($post === null) {
        jsonResponse(404, ['success' => false, 'error' => 'Post not found.']);
    }

    if (!in_array($post['status'], ['scheduled', 'draft'], true)) {
        jsonResponse(400, ['success' => false, 'error' => 'Cannot cancel a post that is already ' . $post['status'] . '.']);
    }

    SocialPostEngine::cancelPost($postId);

    jsonResponse(200, ['success' => true, 'message' => 'Post cancelled successfully.']);
}
