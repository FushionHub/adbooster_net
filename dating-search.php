<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId  = current_user_id();
$currentUser = current_user();
$errors  = [];
$success = '';
$matchedUser = null;

// Fetch user's own dating profile
$myProfile = db()->fetchOne("SELECT * FROM dating_profiles WHERE user_id = ?", [$userId]);

// Handle POST actions: Save Profile, Pair, Block, Report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Save or Update Dating Profile ("Post Date Connection")
        if ($action === 'save_profile') {
            $age          = !empty($_POST['age']) ? (int)$_POST['age'] : null;
            $gender       = trim($_POST['gender'] ?? 'other');
            $lookingFor   = trim($_POST['looking_for'] ?? 'anyone');
            $city         = trim($_POST['city'] ?? '');
            $bio          = trim($_POST['bio'] ?? '');
            $occupation   = trim($_POST['occupation'] ?? '');
            $relationshipGoals = trim($_POST['relationship_goals'] ?? 'dating');
            $interests    = trim($_POST['interests'] ?? '');

            $photoPath = $myProfile['photo'] ?? null;
            if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                if (in_array($ext, $allowed, true)) {
                    $uploadDir = ROOT_PATH . '/uploads/dating/' . date('Y/m');
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $fname = bin2hex(random_bytes(10)) . '.' . $ext;
                    move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . '/' . $fname);
                    $photoPath = 'uploads/dating/' . date('Y/m') . '/' . $fname;
                }
            }

            if ($myProfile) {
                db()->update('dating_profiles', [
                    'age'                => $age,
                    'gender'             => $gender,
                    'looking_for'        => $lookingFor,
                    'city'               => $city,
                    'bio'                => $bio,
                    'occupation'         => $occupation,
                    'relationship_goals' => $relationshipGoals,
                    'interests'          => $interests,
                    'photo'              => $photoPath,
                    'photo_url'          => $photoPath,
                    'updated_at'         => date('Y-m-d H:i:s'),
                ], 'user_id = ?', [$userId]);
            } else {
                db()->insert('dating_profiles', [
                    'user_id'            => $userId,
                    'age'                => $age,
                    'gender'             => $gender,
                    'looking_for'        => $lookingFor,
                    'city'               => $city,
                    'bio'                => $bio,
                    'occupation'         => $occupation,
                    'relationship_goals' => $relationshipGoals,
                    'interests'          => $interests,
                    'photo'              => $photoPath,
                    'photo_url'          => $photoPath,
                    'created_at'         => date('Y-m-d H:i:s'),
                ]);
            }

            $myProfile = db()->fetchOne("SELECT * FROM dating_profiles WHERE user_id = ?", [$userId]);
            $success = 'Your connection profile has been published! You are now discoverable on the network.';
        }

        // 2. Pair / Match Action
        elseif ($action === 'pair') {
            $targetId = (int)($_POST['target_id'] ?? 0);
            if ($targetId > 0 && $targetId !== $userId) {
                // Check if target user has already paired with me
                $reversePair = db()->fetchOne("SELECT * FROM dating_pairs WHERE sender_id = ? AND receiver_id = ?", [$targetId, $userId]);
                $existingPair = db()->fetchOne("SELECT * FROM dating_pairs WHERE sender_id = ? AND receiver_id = ?", [$userId, $targetId]);

                if ($reversePair) {
                    // Mutual match!
                    db()->update('dating_pairs', [
                        'status'     => 'matched',
                        'matched_at' => date('Y-m-d H:i:s'),
                    ], 'id = ?', [(int)$reversePair['id']]);

                    if (!$existingPair) {
                        db()->insert('dating_pairs', [
                            'sender_id'   => $userId,
                            'receiver_id' => $targetId,
                            'status'      => 'matched',
                            'matched_at'  => date('Y-m-d H:i:s'),
                        ]);
                    } else {
                        db()->update('dating_pairs', [
                            'status'     => 'matched',
                            'matched_at' => date('Y-m-d H:i:s'),
                        ], 'id = ?', [(int)$existingPair['id']]);
                    }

                    // Create/send instant icebreaker into p2p chat
                    $existingConv = db()->fetchOne(
                        "SELECT id FROM p2p_conversations WHERE (user_a = ? AND user_b = ?) OR (user_a = ? AND user_b = ?)",
                        [$userId, $targetId, $targetId, $userId]
                    );
                    $convId = $existingConv ? (int)$existingConv['id'] : db()->insert('p2p_conversations', [
                        'user_a'     => min($userId, $targetId),
                        'user_b'     => max($userId, $targetId),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);

                    db()->insert('p2p_messages', [
                        'conversation_id' => $convId,
                        'sender_id'       => $userId,
                        'receiver_id'     => $targetId,
                        'message'         => "🎉 We Paired! It's a match. Excited to connect with you!",
                        'is_read'         => 0,
                        'created_at'      => date('Y-m-d H:i:s'),
                    ]);

                    $matchedUser = db()->fetchOne("SELECT id, full_name FROM users WHERE id = ?", [$targetId]);
                    $success = "🎉 Boom! You and {$matchedUser['full_name']} paired and matched mutually! You can start chatting right away.";
                } else {
                    if (!$existingPair) {
                        db()->insert('dating_pairs', [
                            'sender_id'          => $userId,
                            'receiver_id'        => $targetId,
                            'status'             => 'pending',
                            'icebreaker_message' => trim($_POST['icebreaker'] ?? ''),
                            'created_at'         => date('Y-m-d H:i:s'),
                        ]);
                    }
                    $success = 'Pair connection sent! When they pair back with you, a mutual match is confirmed.';
                }
            }
        }

        // 3. Block
        elseif ($action === 'block') {
            $blockId = (int)($_POST['block_user_id'] ?? 0);
            if ($blockId > 0 && $blockId !== $userId) {
                $exists = db()->fetchOne("SELECT id FROM dating_blocks WHERE blocker_id = ? AND blocked_id = ?", [$userId, $blockId]);
                if (!$exists) {
                    db()->insert('dating_blocks', [
                        'blocker_id' => $userId,
                        'blocked_id' => $blockId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $success = 'User blocked.';
            }
        }

        // 4. Report
        elseif ($action === 'report') {
            $reportId = (int)($_POST['report_user_id'] ?? 0);
            if ($reportId > 0 && $reportId !== $userId) {
                db()->insert('dating_reports', [
                    'reporter_id' => $userId,
                    'reported_id' => $reportId,
                    'reason'      => trim($_POST['reason'] ?? ''),
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                $success = 'Report submitted. Our moderation team will review this profile.';
            }
        }
    }
}

// Fetch who I have paired with
$myPairs = db()->fetchAll("SELECT receiver_id, status FROM dating_pairs WHERE sender_id = ?", [$userId]);
$myPairedUserIds = array_column($myPairs, 'receiver_id');
$myMatches = array_column(array_filter($myPairs, fn($p) => $p['status'] === 'matched'), 'receiver_id');

$gender     = trim($_GET['gender'] ?? '');
$lookingFor = trim($_GET['looking_for'] ?? '');
$city       = trim($_GET['city'] ?? '');

$blockedIds = db()->fetchAll("SELECT blocked_id FROM dating_blocks WHERE blocker_id = ?", [$userId]);
$blockedList = array_column($blockedIds, 'blocked_id');
$blockedList[] = $userId;

$conditions = ["dp.user_id NOT IN (" . implode(',', array_fill(0, count($blockedList), '?')) . ")"];
$paramValues = $blockedList;

if ($gender !== '') {
    $conditions[] = "dp.gender = ?";
    $paramValues[] = $gender;
}
if ($lookingFor !== '') {
    $conditions[] = "dp.looking_for = ?";
    $paramValues[] = $lookingFor;
}
if ($city !== '') {
    $conditions[] = "dp.city LIKE ?";
    $paramValues[] = '%' . $city . '%';
}

$whereClause = implode(' AND ', $conditions);
$profiles = db()->fetchAll(
    "SELECT dp.*, u.full_name, u.email
     FROM dating_profiles dp
     JOIN users u ON dp.user_id = u.id
     WHERE {$whereClause}
     ORDER BY dp.created_at DESC",
    $paramValues
);

$cities = db()->fetchAll("SELECT DISTINCT city FROM dating_profiles WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");

$pageTitle = 'Dating & Connection Network — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Dating &amp; Connection Network</h1>
            <p class="page-subtitle">Discover real people, post your connection profile, and pair with members across the network.</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <button type="button" class="btn-accent" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="ph ph-user-circle-plus me-1"></i> <?= $myProfile ? 'Edit My Connection Profile' : 'Post Date Connection' ?>
            </button>
            <a href="<?= url('p2p-chat') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-chats-circle"></i> P2P Messages
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="padding:14px 20px;border-radius:12px;margin-bottom:24px;">
            <i class="ph ph-heart text-danger me-1"></i> <?= e($success) ?>
            <?php if ($matchedUser): ?>
                <a href="<?= url('p2p-chat') ?>?user=<?= (int)$matchedUser['id'] ?>" class="btn-accent btn-sm ms-3">
                    Open Chat Now <i class="ph ph-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="padding:14px 20px;border-radius:12px;margin-bottom:24px;">
            <?php foreach ($errors as $err): ?>
                <div><i class="ph ph-warning-circle me-1"></i> <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Search / Filter Bar -->
    <div class="card-dark" style="padding:22px;margin-bottom:32px;">
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px;color:var(--fg3);text-transform:uppercase;">Gender</label>
                    <select name="gender" class="form-select" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                        <option value="">All Genders</option>
                        <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $gender === 'other' ? 'selected' : '' ?>>Non-Binary / Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px;color:var(--fg3);text-transform:uppercase;">Looking For</label>
                    <select name="looking_for" class="form-select" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                        <option value="">Anyone</option>
                        <option value="female" <?= $lookingFor === 'female' ? 'selected' : '' ?>>Women</option>
                        <option value="male" <?= $lookingFor === 'male' ? 'selected' : '' ?>>Men</option>
                        <option value="dating" <?= $lookingFor === 'dating' ? 'selected' : '' ?>>Dating &amp; Romance</option>
                        <option value="friendship" <?= $lookingFor === 'friendship' ? 'selected' : '' ?>>Friendship</option>
                        <option value="networking" <?= $lookingFor === 'networking' ? 'selected' : '' ?>>Business Networking</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px;color:var(--fg3);text-transform:uppercase;">Location</label>
                    <input type="text" name="city" value="<?= e($city) ?>" placeholder="Filter by city or state..." class="form-control" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-accent w-100" style="padding:10px;font-weight:600;">
                        <i class="ph ph-magnifying-glass me-1"></i> Filter Connections
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Profiles Grid -->
    <?php if (empty($profiles)): ?>
        <div class="card-dark" style="text-align:center;padding:72px 20px;">
            <div style="width:64px;height:64px;background:rgba(236,72,153,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#ec4899;">
                <i class="ph ph-heart" style="font-size:28px;"></i>
            </div>
            <h4 style="color:#fff;font-weight:700;margin-bottom:6px;">No connection profiles found</h4>
            <p style="color:var(--fg2);font-size:14px;max-width:380px;margin:0 auto 20px;">
                Be among the first to publish a profile and connect with verified members!
            </p>
            <button type="button" class="btn-accent" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="ph ph-user-circle-plus me-1"></i> Post My Profile Now
            </button>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($profiles as $profile): 
                $hasPaired = in_array((int)$profile['user_id'], $myPairedUserIds, true);
                $isMatched = in_array((int)$profile['user_id'], $myMatches, true);
                $photo = !empty($profile['photo']) ? (BASE_URL . '/' . $profile['photo']) : null;
            ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card-dark" style="padding:0;overflow:hidden;border:1px solid rgba(255,255,255,0.08);height:100%;display:flex;flex-direction:column;position:relative;">
                        <!-- Profile Photo -->
                        <div style="height:250px;background:#080c14;overflow:hidden;position:relative;">
                            <?php if ($photo): ?>
                                <img src="<?= e($photo) ?>" alt="<?= e($profile['full_name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:48px;font-weight:800;background:linear-gradient(135deg, rgba(200,255,0,0.1), rgba(14,22,38,0.9));">
                                    <?= strtoupper(substr($profile['full_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Badges Overlay -->
                            <div style="position:absolute;top:12px;left:12px;display:flex;gap:6px;flex-wrap:wrap;">
                                <?php if ($isMatched): ?>
                                    <span class="badge-status badge-active" style="background:#ec4899;color:#fff;">
                                        ❤️ Mutual Match
                                    </span>
                                <?php elseif ($hasPaired): ?>
                                    <span class="badge-status badge-active">
                                        ✓ Pair Requested
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($profile['city'])): ?>
                                <div style="position:absolute;bottom:10px;left:12px;background:rgba(8,12,20,0.85);backdrop-filter:blur(4px);padding:4px 10px;border-radius:100px;font-size:11.5px;color:#fff;display:flex;align-items:center;gap:4px;">
                                    <i class="ph ph-map-pin text-warning"></i> <?= htmlspecialchars($profile['city']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Body -->
                        <div style="padding:20px;flex:1;display:flex;flex-direction:column;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <h5 style="color:#fff;font-weight:700;font-size:17px;margin:0;">
                                    <?= htmlspecialchars($profile['full_name']) ?>
                                    <?php if (!empty($profile['age'])): ?>
                                        <span style="color:var(--fg2);font-weight:500;font-size:15px;">, <?= (int)$profile['age'] ?></span>
                                    <?php endif; ?>
                                </h5>
                            </div>

                            <?php if (!empty($profile['occupation'])): ?>
                                <div style="font-size:12px;color:var(--accent);margin-bottom:8px;">
                                    <i class="ph ph-briefcase me-1"></i> <?= htmlspecialchars($profile['occupation']) ?>
                                </div>
                            <?php endif; ?>

                            <p style="font-size:13px;color:var(--fg2);line-height:1.5;margin-bottom:16px;flex:1;">
                                <?= htmlspecialchars(mb_substr($profile['bio'] ?? 'Looking to connect and chat on Adsbooster.', 0, 90)) ?><?= mb_strlen($profile['bio'] ?? '') > 90 ? '...' : '' ?>
                            </p>

                            <!-- Pair & Connect Action Buttons -->
                            <div style="display:flex;gap:8px;margin-top:auto;">
                                <?php if ($isMatched): ?>
                                    <a href="<?= url('p2p-chat') ?>?user=<?= (int)$profile['user_id'] ?>" class="btn-accent w-100" style="padding:9px;text-align:center;font-weight:600;font-size:13px;">
                                        <i class="ph ph-chats-circle me-1"></i> Chat Now
                                    </a>
                                <?php else: ?>
                                    <form method="POST" style="flex:1;margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="pair">
                                        <input type="hidden" name="target_id" value="<?= (int)$profile['user_id'] ?>">
                                        <button type="submit" class="btn-accent w-100" style="padding:9px;font-weight:600;font-size:13px;background:<?= $hasPaired ? 'rgba(200,255,0,0.15)' : 'var(--accent)' ?>;color:<?= $hasPaired ? 'var(--accent)' : '#080c14' ?>;">
                                            <i class="ph ph-heart me-1"></i> <?= $hasPaired ? 'Paired' : 'Pair' ?>
                                        </button>
                                    </form>
                                    <a href="<?= url('p2p-chat') ?>?user=<?= (int)$profile['user_id'] ?>" class="btn-outline-dark btn-sm" style="padding:9px 12px;" title="Send direct message">
                                        <i class="ph ph-chat-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Post / Edit Connection Profile -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:18px;">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_profile">

                <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <i class="ph ph-heart" style="font-size:22px;color:var(--accent);"></i>
                        <h5 class="modal-title" style="color:#fff;font-weight:700;">
                            <?= $myProfile ? 'Edit My Connection Profile' : 'Post My Connection Profile' ?>
                        </h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="padding:24px;">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;color:var(--fg);">Your Age</label>
                            <input type="number" name="age" min="18" max="99" value="<?= e($myProfile['age'] ?? '25') ?>" class="form-control" required style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;color:var(--fg);">City / Location</label>
                            <input type="text" name="city" value="<?= e($myProfile['city'] ?? '') ?>" placeholder="e.g. New York, London, Lagos" class="form-control" required style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;color:var(--fg);">Your Gender</label>
                            <select name="gender" class="form-select" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                                <option value="male" <?= ($myProfile['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($myProfile['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= ($myProfile['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other / Non-Binary</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;color:var(--fg);">Looking For</label>
                            <select name="looking_for" class="form-select" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                                <option value="female" <?= ($myProfile['looking_for'] ?? '') === 'female' ? 'selected' : '' ?>>Women</option>
                                <option value="male" <?= ($myProfile['looking_for'] ?? '') === 'male' ? 'selected' : '' ?>>Men</option>
                                <option value="anyone" <?= ($myProfile['looking_for'] ?? '') === 'anyone' ? 'selected' : '' ?>>Open to All</option>
                                <option value="friendship" <?= ($myProfile['looking_for'] ?? '') === 'friendship' ? 'selected' : '' ?>>Friendship</option>
                                <option value="networking" <?= ($myProfile['looking_for'] ?? '') === 'networking' ? 'selected' : '' ?>>Professional Networking</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;color:var(--fg);">Occupation / Profession</label>
                            <input type="text" name="occupation" value="<?= e($myProfile['occupation'] ?? '') ?>" placeholder="e.g. Software Engineer, Designer, Entrepreneur" class="form-control" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:13px;color:var(--fg);">Relationship Intent</label>
                            <select name="relationship_goals" class="form-select" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                                <option value="dating" <?= ($myProfile['relationship_goals'] ?? '') === 'dating' ? 'selected' : '' ?>>Dating &amp; Romance</option>
                                <option value="serious" <?= ($myProfile['relationship_goals'] ?? '') === 'serious' ? 'selected' : '' ?>>Long-Term Relationship</option>
                                <option value="casual" <?= ($myProfile['relationship_goals'] ?? '') === 'casual' ? 'selected' : '' ?>>Casual &amp; Friends</option>
                                <option value="networking" <?= ($myProfile['relationship_goals'] ?? '') === 'networking' ? 'selected' : '' ?>>Business Connections</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;color:var(--fg);">Bio / About You</label>
                        <textarea name="bio" rows="3" class="form-control" placeholder="Share your passions, hobbies, and what you're excited to find..." style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;"><?= e($myProfile['bio'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;color:var(--fg);">Interests / Hobbies</label>
                        <input type="text" name="interests" value="<?= e($myProfile['interests'] ?? '') ?>" placeholder="e.g. Travel, Photography, Tech, Fitness, Music" class="form-control" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                    </div>

                    <div class="mb-2">
                        <label class="form-label" style="font-size:13px;color:var(--fg);">Profile Photo</label>
                        <input type="file" name="photo" accept="image/*" class="form-control" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);color:#fff;">
                    </div>
                </div>

                <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,0.08);padding:16px 24px;">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent" style="padding:10px 24px;font-weight:700;">
                        <i class="ph ph-heart me-1"></i> Save &amp; Publish Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
