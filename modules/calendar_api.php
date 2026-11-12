<?php declare(strict_types=1);

/**
 * AJAX Endpoint: Content Calendar API
 * Adsbooster.net
 *
 * One JSON endpoint for every interactive action on the content calendar so
 * the page never reloads:
 *
 *   schedule        — create a scheduled post (SocialPostEngine::createPost)
 *   publish_now     — publish an existing scheduled post immediately
 *                     (SocialPostEngine::publishScheduledRow), or create +
 *                     publish a brand-new post when post_id is absent
 *   cancel_post     — withdraw a scheduled post
 *   set_reminders   — choose how many reminders the user wants (0–5)
 *   add_note        — pin a sticky note to a calendar day
 *   update_note     — edit a day note
 *   delete_note     — remove a day note
 *
 * Every action is CSRF-verified (X-CSRF-Token header) and scoped to the
 * logged-in user's rows.
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in.']);
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Your session expired — refresh the page and try again.']);
    exit;
}

require_once __DIR__ . '/social_post_engine.php';

$userId = current_user_id();
$action = (string)($_POST['action'] ?? '');

/** Read a JSON body field or fall back to form data. */
$in = static function (string $key): string {
    static $json = null;
    if ($json === null) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        $json = is_array($decoded) ? $decoded : [];
    }
    $raw = $json[$key] ?? $_POST[$key] ?? '';
    return is_string($raw) ? trim($raw) : (string)$raw;
};

$fail = static function (string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
};

try {
    switch ($action) {
        case 'schedule': {
            $caption  = $in('caption');
            $platform = strtolower($in('platform') ?: 'meta');
            $mediaType = $in('media_type') ?: 'text';
            $scheduledAt = $in('scheduled_at');
            $reminderCount = max(0, min(5, (int)($in('reminder_count') ?: 3)));

            if ($caption === '') {
                $fail('Write a caption first.');
            }
            if ($scheduledAt === '') {
                $fail('Pick a date and time.');
            }

            $mediaUrl = null;
            if (!empty($_FILES['media']['tmp_name']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'], true)) {
                    $fail('Unsupported media type.');
                }
                $dir = ROOT_PATH . '/uploads/posts/' . date('Y/m');
                if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    $fail('Could not store the upload — try again.');
                }
                $name = bin2hex(random_bytes(12)) . '.' . $ext;
                move_uploaded_file($_FILES['media']['tmp_name'], $dir . '/' . $name);
                $mediaUrl = 'uploads/posts/' . date('Y/m') . '/' . $name;
            }

            $postId = SocialPostEngine::createPost($userId, $platform, $caption, $mediaUrl, $mediaType, $scheduledAt);
            db()->update('scheduled_posts', ['reminder_count' => $reminderCount], 'id = ? AND user_id = ?', [$postId, $userId]);

            echo json_encode([
                'success' => true,
                'post_id' => $postId,
                'message' => 'Scheduled for ' . date('D, M j — g:i A', (int)strtotime($scheduledAt)),
            ]);
            exit;
        }

        case 'publish_now': {
            $postId = (int)$in('post_id');

            if ($postId > 0) {
                // Publish an existing scheduled row immediately.
                $own = db()->fetchOne(
                    "SELECT id FROM scheduled_posts WHERE id = ? AND user_id = ?",
                    [$postId, $userId]
                );
                if (!$own) {
                    $fail('That post does not belong to you.', 404);
                }
                $res = SocialPostEngine::publishScheduledRow($postId);
                if (!empty($res['success'])) {
                    echo json_encode(['success' => true, 'message' => 'Publishing now — the dispatch worker is sending it to ' . ucfirst($in('platform') ?: 'your channel') . '.']);
                } else {
                    echo json_encode(['success' => false, 'error' => (string)($res['error'] ?? 'Publish failed.')]);
                }
                exit;
            }

            // No post yet — create + queue in one step (Post Now from the modal).
            $caption  = $in('caption');
            $platform = strtolower($in('platform') ?: 'meta');
            $mediaType = $in('media_type') ?: 'text';
            if ($caption === '') {
                $fail('Write a caption first.');
            }

            $mediaUrl = null;
            if (!empty($_FILES['media']['tmp_name']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'], true)) {
                    $dir = ROOT_PATH . '/uploads/posts/' . date('Y/m');
                    if (is_dir($dir) || @mkdir($dir, 0755, true) || is_dir($dir)) {
                        $name = bin2hex(random_bytes(12)) . '.' . $ext;
                        move_uploaded_file($_FILES['media']['tmp_name'], $dir . '/' . $name);
                        $mediaUrl = 'uploads/posts/' . date('Y/m') . '/' . $name;
                    }
                }
            }

            $res = SocialPostEngine::publishLive($userId, $platform, $caption, $mediaUrl, $mediaType);
            if (!empty($res['success'])) {
                echo json_encode(['success' => true, 'post_id' => (int)$res['post_id'], 'message' => 'Queued for immediate publish to ' . ucfirst($platform) . '.']);
            } else {
                echo json_encode(['success' => false, 'error' => (string)($res['error'] ?? 'Could not queue the publish.')]);
            }
            exit;
        }

        case 'cancel_post': {
            $postId = (int)$in('post_id');
            $own = $postId > 0 ? db()->fetchOne("SELECT id FROM scheduled_posts WHERE id = ? AND user_id = ?", [$postId, $userId]) : null;
            if (!$own) {
                $fail('That post does not belong to you.', 404);
            }
            $ok = SocialPostEngine::cancelPost($postId);
            echo json_encode($ok
                ? ['success' => true, 'message' => 'Post cancelled.']
                : ['success' => false, 'error' => 'Only scheduled posts can be cancelled.']);
            exit;
        }

        case 'set_reminders': {
            $postId = (int)$in('post_id');
            $count  = max(0, min(5, (int)$in('reminder_count')));
            $own = $postId > 0 ? db()->fetchOne("SELECT id FROM scheduled_posts WHERE id = ? AND user_id = ?", [$postId, $userId]) : null;
            if (!$own) {
                $fail('That post does not belong to you.', 404);
            }
            // rowCount can be 0 when the value doesn't change — that is still
            // a success (MySQL's default CLIENT_FOUND_ROWS behaviour).
            db()->update('scheduled_posts', ['reminder_count' => $count], 'id = ?', [$postId]);
            echo json_encode([
                'success' => true,
                'message' => $count === 0 ? 'Reminders turned off.' : "You will get {$count} reminder" . ($count === 1 ? '' : 's') . ' before this post goes out.',
            ]);
            exit;
        }

        case 'add_note':
        case 'update_note': {
            $noteDate = $in('note_date');
            $text     = mb_substr($in('note'), 0, 2000);
            $color    = in_array($in('color'), ['lime', 'blue', 'pink', 'amber'], true) ? $in('color') : 'lime';

            if ($noteDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate)) {
                $fail('Pick a valid calendar day.');
            }

            if ($action === 'update_note') {
                $noteId = (int)$in('note_id');
                $own = $noteId > 0 ? db()->fetchOne("SELECT id FROM calendar_notes WHERE id = ? AND user_id = ?", [$noteId, $userId]) : null;
                if (!$own) {
                    $fail('That note does not belong to you.', 404);
                }
                if ($text === '') {
                    db()->delete('calendar_notes', 'id = ? AND user_id = ?', [$noteId, $userId]);
                    echo json_encode(['success' => true, 'deleted' => true, 'message' => 'Note removed.']);
                } else {
                    db()->update('calendar_notes', ['note' => $text, 'color' => $color], 'id = ?', [$noteId]);
                    echo json_encode(['success' => true, 'note_id' => $noteId, 'message' => 'Note updated.']);
                }
                exit;
            }

            if ($text === '') {
                $fail('Write something in the note first.');
            }
            $noteId = db()->insert('calendar_notes', [
                'user_id'   => $userId,
                'note_date' => $noteDate,
                'note'      => $text,
                'color'     => $color,
            ]);
            echo json_encode(['success' => true, 'note_id' => $noteId, 'message' => 'Note added to ' . date('M j', (int)strtotime($noteDate)) . '.']);
            exit;
        }

        case 'delete_note': {
            $noteId = (int)$in('note_id');
            $ok = $noteId > 0 && db()->delete('calendar_notes', 'id = ? AND user_id = ?', [$noteId, $userId]) > 0;
            echo json_encode($ok
                ? ['success' => true, 'message' => 'Note removed.']
                : ['success' => false, 'error' => 'Note not found.']);
            exit;
        }
    }

    $fail('Unknown action.', 400);
} catch (\Throwable $e) {
    error_log('calendar_api: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong on our side. Please try again.']);
}
