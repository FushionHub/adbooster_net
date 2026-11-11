<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/ThirdPartyGateway.php';

$pageTitle = 'Social Media & Publishing Hub';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $postId = (int)($_POST['post_id'] ?? 0);
        $accId  = (int)($_POST['account_id'] ?? 0);

        if ($act === 'force_publish' && $postId > 0) {
            require_once __DIR__ . '/../modules/social_post_engine.php';
            require_once __DIR__ . '/../modules/AsyncQueue.php';
            // ASYNCHRONOUS force-publish: enqueue the bundle_publish_post job;
            // the worker performs the Bundle.social dispatch and records the
            // confirmed result. No gateway call in this admin request.
            $post = db()->fetchOne("SELECT id, status, user_id, bundle_post_id FROM scheduled_posts WHERE id = ?", [$postId]);
            if (!$post || !in_array($post['status'], ['scheduled', 'failed'], true)) {
                set_flash('error', "Post #{$postId} is not publishable (current status: " . ($post['status'] ?? 'not found') . ').');
            } else {
                // Reset a failed row so the worker can retry publishing it.
                if ($post['status'] === 'failed') {
                    db()->update('scheduled_posts', ['status' => 'scheduled', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$postId]);
                }
                $jobId = AsyncQueue::push((int)$post['user_id'], 'bundle_publish_post', ['post_id' => $postId], null, 'post:' . $postId);
                if ($jobId > 0) {
                    set_flash('success', "Post #{$postId} dispatch queued for Bundle.social (job #{$jobId}) — the worker confirms the publish shortly.");
                } else {
                    set_flash('error', "Background queue temporarily unavailable — post #{$postId} not queued.");
                }
            }
        } elseif ($act === 'cancel_post' && $postId > 0) {
            require_once __DIR__ . '/../modules/social_post_engine.php';
            SocialPostEngine::cancelPost($postId);
            set_flash('success', "Post #{$postId} cancelled.");
        } elseif ($act === 'delete_post' && $postId > 0) {
            db()->delete('scheduled_posts', 'id=?', [$postId]);
            set_flash('success', "Scheduled post #{$postId} removed.");
        } elseif ($act === 'comment_reply' && $postId > 0) {
            require_once __DIR__ . '/../modules/AsyncQueue.php';
            // ASYNCHRONOUS comment reply: the reply is executed by
            // cron/queue_worker.php through the Bundle.social Gateway (which
            // holds the platform credentials) — no gateway call in this
            // admin request.
            $commentId = trim((string)($_POST['comment_id'] ?? ''));
            $replyText = trim((string)($_POST['reply_text'] ?? ''));
            $post = db()->fetchOne("SELECT id, user_id FROM scheduled_posts WHERE id = ?", [$postId]);
            if (!$post) {
                set_flash('error', "Post #{$postId} not found.");
            } elseif ($commentId === '' || $replyText === '') {
                set_flash('error', 'A comment id and reply text are required.');
            } else {
                $jobId = AsyncQueue::push(
                    (int)$post['user_id'],
                    'bundle_reply_comment',
                    ['comment_id' => $commentId, 'reply_text' => $replyText],
                    null,
                    'comment-reply:' . $commentId
                );
                if ($jobId > 0) {
                    set_flash('success', "Reply queued for Bundle.social (job #{$jobId}) — the worker posts it out-of-band and only a gateway confirmation records it.");
                } else {
                    set_flash('error', 'Background queue temporarily unavailable — reply not queued.');
                }
            }
        } elseif ($act === 'comment_moderate' && $postId > 0) {
            require_once __DIR__ . '/../modules/AsyncQueue.php';
            // ASYNCHRONOUS moderation (hide / unhide / delete) — same
            // queue-first discipline as every other gateway mutation.
            $commentId = trim((string)($_POST['comment_id'] ?? ''));
            $action    = strtolower(trim((string)($_POST['mod_action'] ?? '')));
            $post = db()->fetchOne("SELECT id, user_id FROM scheduled_posts WHERE id = ?", [$postId]);
            if (!$post) {
                set_flash('error', "Post #{$postId} not found.");
            } elseif ($commentId === '' || !in_array($action, ['hide', 'unhide', 'delete'], true)) {
                set_flash('error', "Invalid moderation request (allowed: hide, unhide, delete).");
            } else {
                $jobId = AsyncQueue::push(
                    (int)$post['user_id'],
                    'bundle_moderate_comment',
                    ['comment_id' => $commentId, 'action' => $action],
                    null,
                    'comment-mod:' . $commentId . ':' . $action
                );
                if ($jobId > 0) {
                    set_flash('success', "Moderation ('{$action}') queued for Bundle.social (job #{$jobId}) — the gateway applies it out-of-band.");
                } else {
                    set_flash('error', 'Background queue temporarily unavailable — moderation not queued.');
                }
            }
        } elseif ($act === 'disconnect_account' && $accId > 0) {
            db()->delete('connected_accounts', 'id=?', [$accId]);
            set_flash('success', "Connected social account #{$accId} unlinked.");
        }
    }
    redirect(url('admin/social-media'));
}

// KPI Metrics
$totalAccounts  = db()->count('connected_accounts');
$queuedPosts    = db()->count('scheduled_posts', "status='scheduled'");
$publishedPosts = db()->count('scheduled_posts', "status='published'");

$tab = $_GET['tab'] ?? 'posts';
$filterStatus = $_GET['status'] ?? '';
$filterPlatform = $_GET['platform'] ?? '';

// Posts query
$postWhere = '1=1';
$postParams = [];
if ($filterStatus && in_array($filterStatus, ['scheduled', 'published', 'failed', 'cancelled'])) {
    $postWhere .= " AND p.status = ?";
    $postParams[] = $filterStatus;
}
if ($filterPlatform) {
    $postWhere .= " AND p.platform = ?";
    $postParams[] = $filterPlatform;
}

$posts = db()->fetchAll(
    "SELECT p.*, u.full_name, u.email 
     FROM scheduled_posts p
     LEFT JOIN users u ON u.id = p.user_id
     WHERE {$postWhere}
     ORDER BY p.scheduled_time DESC LIMIT 50",
    $postParams
);

// Accounts query
$accounts = db()->fetchAll(
    "SELECT a.*, u.full_name, u.email 
     FROM connected_accounts a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC LIMIT 50"
);

// ── Comment Moderation tab data (Bundle.social core feature) ──
// Mutations (reply / hide / unhide / delete) go through the async queue;
// the comment LIST itself is a read-only gateway inspection performed
// here and never writes anything locally.
$modPosts = db()->fetchAll(
    "SELECT id, user_id, bundle_post_id, caption, platform, scheduled_time
     FROM scheduled_posts
     WHERE status = 'published' AND bundle_post_id IS NOT NULL AND bundle_post_id <> ''
     ORDER BY scheduled_time DESC LIMIT 25"
);
$modSelectedId = (int)($_GET['post_id'] ?? 0);
if ($modSelectedId <= 0 && !empty($modPosts)) {
    $modSelectedId = (int)$modPosts[0]['id'];
}
$modSelected = null;
foreach ($modPosts as $mp) {
    if ((int)$mp['id'] === $modSelectedId) {
        $modSelected = $mp;
        break;
    }
}
$comments    = [];
$commentsSimulated = false;
$commentsNote = '';
if ($tab === 'comments' && $modSelected && !empty($modSelected['bundle_post_id'])) {
    $cres = ThirdPartyGateway::getPostComments((string)$modSelected['bundle_post_id']);
    if (!empty($cres['data']['simulated'])) {
        $commentsSimulated = true;
        $commentsNote = (string)($cres['data']['message'] ?? 'Sandbox data — placeholder API key.');
    }
    if (!empty($cres['success']) && isset($cres['data']['comments']) && is_array($cres['data']['comments'])) {
        $comments = array_values($cres['data']['comments']);
    } elseif (empty($cres['success'])) {
        $commentsNote = (string)($cres['error'] ?? 'Bundle.social could not list comments for this post.');
    }
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-share-network"></i></div>
            <div>
                <div class="card-title">Connected Accounts</div>
                <div class="card-value"><?= number_format($totalAccounts) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-clock"></i></div>
            <div>
                <div class="card-title">Queued Posts</div>
                <div class="card-value"><?= number_format($queuedPosts) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon green"><i class="ph-bold ph-check-circle"></i></div>
            <div>
                <div class="card-title">Published Posts</div>
                <div class="card-value"><?= number_format($publishedPosts) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex gap-2">
        <a href="?tab=posts" class="btn-admin <?= $tab === 'posts' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-calendar-check"></i> Post Pipeline (<?= $queuedPosts + $publishedPosts ?>)
        </a>
        <a href="?tab=comments" class="btn-admin <?= $tab === 'comments' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-chats-circle"></i> Comment Moderation
        </a>
        <a href="?tab=accounts" class="btn-admin <?= $tab === 'accounts' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-users"></i> Linked Channels (<?= $totalAccounts ?>)
        </a>
    </div>
</div>

<?php if ($tab === 'posts'): ?>
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h6 class="text-white fw-bold mb-0">Scheduled & Published Post Pipeline</h6>
            <div class="d-flex gap-2">
                <a href="?tab=posts" class="btn-admin btn-admin-outline <?= !$filterStatus ? 'active' : '' ?>">All</a>
                <a href="?tab=posts&status=scheduled" class="btn-admin btn-admin-outline <?= $filterStatus === 'scheduled' ? 'active' : '' ?>">Scheduled</a>
                <a href="?tab=posts&status=published" class="btn-admin btn-admin-outline <?= $filterStatus === 'published' ? 'active' : '' ?>">Published</a>
                <a href="?tab=posts&status=failed" class="btn-admin btn-admin-outline <?= $filterStatus === 'failed' ? 'active' : '' ?>">Failed</a>
            </div>
        </div>

        <?php if (empty($posts)): ?>
            <p class="text-muted py-4 text-center">No posts found matching filter criteria.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Platform & Type</th>
                            <th>Caption & Content</th>
                            <th>Author</th>
                            <th>Schedule Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-white text-capitalize">
                                        <i class="ph-bold ph-share"></i> <?= htmlspecialchars($p['platform'] ?? 'social') ?>
                                    </div>
                                    <span class="badge bg-dark border border-secondary text-light small">
                                        <?= strtoupper($p['media_type'] ?? 'TEXT') ?>
                                    </span>
                                </td>
                                <td style="max-width: 320px;">
                                    <div class="small text-white text-truncate" title="<?= htmlspecialchars($p['caption'] ?? '') ?>">
                                        <?= htmlspecialchars(substr($p['caption'] ?? '', 0, 90)) ?>...
                                    </div>
                                    <?php if (!empty($p['media_url'])): ?>
                                        <div class="small text-info mt-1">
                                            <i class="ph-bold ph-image"></i> Attachment attached
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($p['full_name'] ?? 'System') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($p['email'] ?? '') ?></div>
                                </td>
                                <td class="text-muted small">
                                    <?= dt($p['scheduled_time'], 'M j, Y H:i') ?>
                                </td>
                                <td>
                                    <?php
                                    $stCls = [
                                        'published' => 'active',
                                        'scheduled' => 'in_progress',
                                        'failed' => 'rejected',
                                        'cancelled' => 'inactive'
                                    ];
                                    $cls = $stCls[$p['status']] ?? 'pending';
                                    ?>
                                    <span class="badge-status <?= $cls ?>"><?= ucfirst($p['status']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php if ($p['status'] === 'scheduled'): ?>
                                            <form method="POST" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="force_publish">
                                                <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn-admin btn-admin-green" title="Force Publish Now">
                                                    <i class="ph-bold ph-paper-plane-right"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this scheduled post?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn-admin btn-admin-red" title="Delete">
                                                <i class="ph-bold ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'comments'): ?>
    <?php // Comment Moderation via the Bundle.social Gateway (organic engine core feature). ?>
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h6 class="text-white fw-bold mb-0">Comment Moderation — Bundle.social Gateway</h6>
            <?php if ($commentsSimulated): ?>
                <span class="badge bg-warning-subtle text-warning border border-warning border-opacity-25" title="<?= htmlspecialchars($commentsNote) ?>">
                    ◇ Sandbox data — placeholder API key
                </span>
            <?php endif; ?>
        </div>

        <?php if (empty($modPosts)): ?>
            <p class="text-muted py-4 text-center">
                No gateway-confirmed posts yet. Replies and moderation appear once a post is published
                through Bundle.social (a real <code>bundle_post_id</code> is required for the gateway feed).
            </p>
        <?php else: ?>
            <div class="mb-3">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="hidden" name="tab" value="comments">
                    <label class="text-white small fw-semibold me-1">Moderating:</label>
                    <select name="post_id" class="form-select bg-dark text-white border-secondary" style="max-width:480px;" onchange="this.form.submit();">
                        <?php foreach ($modPosts as $mp): ?>
                            <option value="<?= (int)$mp['id'] ?>" <?= (int)$mp['id'] === $modSelectedId ? 'selected' : '' ?>>
                                #<?= (int)$mp['id'] ?> · <?= htmlspecialchars(ucfirst((string)$mp['platform'])) ?> · <?= htmlspecialchars(mb_substr((string)$mp['caption'], 0, 60)) ?>…
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn-admin btn-admin-outline">Load comments</button></noscript>
                </form>
            </div>

            <?php if ($modSelected && !empty($modSelected['bundle_post_id'])): ?>
                <div class="small text-muted mb-2">
                    <i class="ph-bold ph-link"></i> Bundle.social post id&nbsp;
                    <code class="text-info"><?= htmlspecialchars((string)$modSelected['bundle_post_id']) ?></code>
                </div>

                <?php if (empty($comments)): ?>
                    <p class="text-muted py-3 text-center"><?= htmlspecialchars($commentsNote !== '' ? $commentsNote : 'No comments returned on the gateway feed for this post.') ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Comment</th>
                                    <th>Author &amp; Time</th>
                                    <th>State</th>
                                    <th style="min-width:260px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comments as $c):
                                    $cId    = (string)($c['id'] ?? '');
                                    $hidden = !empty($c['hidden']);
                                ?>
                                    <tr>
                                        <td style="max-width:340px;">
                                            <div class="text-white small"><?= htmlspecialchars(mb_substr((string)($c['text'] ?? ''), 0, 220)) ?></div>
                                            <?php if (!empty($c['likes'])): ?>
                                                <span class="text-muted font-size-11"><i class="ph-bold ph-heart"></i> <?= (int)$c['likes'] ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($c['simulated'])): ?>
                                                <span class="text-warning font-size-10 d-block mt-1">◇ sandbox comment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-white small"><?= htmlspecialchars((string)($c['author'] ?? 'Unknown')) ?></div>
                                            <?php if (!empty($c['created_at'])): ?>
                                                <div class="text-muted small"><?= dt($c['created_at'], 'M j, H:i') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($hidden): ?>
                                                <span class="badge-status rejected">Hidden</span>
                                            <?php else: ?>
                                                <span class="badge-status active">Visible</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-flex gap-2 align-items-start" style="max-width:300px;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="comment_reply">
                                                <input type="hidden" name="post_id" value="<?= $modSelectedId ?>">
                                                <input type="hidden" name="comment_id" value="<?= htmlspecialchars($cId) ?>">
                                                <input type="text" name="reply_text" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Reply via Bundle.social…" required>
                                                <button type="submit" class="btn-admin btn-admin-green" title="Queue reply (async)">
                                                    <i class="ph-bold ph-arrow-bend-double-up-right"></i>
                                                </button>
                                            </form>
                                            <div class="d-flex gap-2 mt-2">
                                                <?php foreach ([['hide', 'Hide', 'ph-eye-slash'], ['unhide', 'Unhide', 'ph-eye'], ['delete', 'Delete', 'ph-trash']] as $modAction): ?>
                                                    <?php if ($modAction[0] === 'unhide' && !$hidden) { continue; } if ($modAction[0] === 'hide' && $hidden) { continue; } ?>
                                                    <form method="POST" style="display:inline;" <?= $modAction[0] === 'delete' ? "onsubmit=\"return confirm('Delete this comment via the Bundle.social worker?');\"" : '' ?>>
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="act" value="comment_moderate">
                                                        <input type="hidden" name="post_id" value="<?= $modSelectedId ?>">
                                                        <input type="hidden" name="comment_id" value="<?= htmlspecialchars($cId) ?>">
                                                        <input type="hidden" name="mod_action" value="<?= $modAction[0] ?>">
                                                        <button type="submit" class="btn-admin <?= $modAction[0] === 'delete' ? 'btn-admin-red' : 'btn-admin-outline' ?>" title="<?= $modAction[1] ?> via Bundle.social (queued)">
                                                            <i class="ph-bold <?= $modAction[2] ?>"></i> <?= $modAction[1] ?>
                                                        </button>
                                                    </form>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-3">
                        <i class="ph-bold ph-info"></i> Replies and moderation are executed
                        <strong class="text-white">asynchronously</strong> by the queue worker directly on the Bundle.social
                        Gateway — comments feed is read-only here; only gateway confirmations produce real effects.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'accounts'): ?>
    <div class="admin-card">
        <h6 class="text-white fw-bold mb-3">Connected User Accounts</h6>
        <?php if (empty($accounts)): ?>
            <p class="text-muted py-4 text-center">No connected social accounts found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Account Name</th>
                            <th>Account Owner</th>
                            <th>Platform UID</th>
                            <th>Connected At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a): 
                            $plat = $a['platform_name'] ?? ($a['platform'] ?? 'unknown');
                            $gw = $a['gateway_provider'] ?? '';
                            $accType = $a['account_type'] ?? '';
                            $extId = $a['external_account_id'] ?? ($a['platform_user_id'] ?? 'N/A');
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark border border-primary text-primary text-uppercase">
                                        <?= htmlspecialchars($plat) ?>
                                    </span>
                                    <?php if ($gw): ?>
                                        <span class="badge bg-secondary text-white-50 font-size-10 d-block mt-1">
                                            <?= $gw === 'bundle_social' ? 'Bundle.social' : ($gw === 'zernio' ? 'Zernio' : htmlspecialchars($gw)) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($a['display_name'] ?? ($a['account_name'] ?? 'Social Profile')) ?></div>
                                    <?php if ($accType): ?>
                                        <span class="text-muted font-size-11"><?= str_replace('_', ' ', ucfirst($accType)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($a['full_name'] ?? 'User') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($a['email'] ?? '') ?></div>
                                </td>
                                <td class="text-muted font-monospace small">
                                    <?= htmlspecialchars($extId) ?>
                                </td>
                                <td class="text-muted small">
                                    <?= dt($a['created_at'], 'M j, Y') ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Revoke and unlink this social account?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="disconnect_account">
                                        <input type="hidden" name="account_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn-admin btn-admin-red" title="Disconnect">
                                            <i class="ph-bold ph-plugs-connected"></i> Unlink
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
