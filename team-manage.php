<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId  = current_user_id();
$user    = current_user();
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'invite') {
            $email = trim(strtolower($_POST['invite_email'] ?? ''));
            $role  = $_POST['invite_role'] ?? 'viewer';
            $validRoles = ['admin', 'editor', 'viewer'];

            if ($email === '') {
                $errors[] = 'Email address is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif (!in_array($role, $validRoles, true)) {
                $errors[] = 'Invalid role selected.';
            } elseif ($email === strtolower($user['email'])) {
                $errors[] = 'You are already the workspace owner.';
            } else {
                $existingUser = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
                $existingMember = db()->fetchOne(
                    "SELECT id FROM team_members WHERE parent_user_id = ? AND email = ?",
                    [$userId, $email]
                );

                if ($existingMember) {
                    $errors[] = 'This email is already part of your team or has a pending invitation.';
                } else {
                    $token = bin2hex(random_bytes(32));
                    db()->insert('team_members', [
                        'parent_user_id' => $userId,
                        'email'          => $email,
                        'role'           => $role,
                        'status'         => $existingUser ? 'active' : 'pending',
                        'user_id'        => $existingUser['id'] ?? null,
                        'invite_token'   => $token,
                        'invited_at'     => date('Y-m-d H:i:s'),
                        'created_at'     => date('Y-m-d H:i:s'),
                    ]);

                    $siteName = get_setting('site_name', 'Adsbooster.net');
                    $inviterName = $user['full_name'] ?? 'Team Admin';
                    $inviteLink = BASE_URL . "/register.php?invite=" . $token;

                    $subject = "You've been invited to join the {$inviterName} team on {$siteName}";
                    $body = "Hi,\n\n{$inviterName} has invited you to collaborate on {$siteName} as a " . ucfirst($role) . ".\n\nAccept your invite here: {$inviteLink}\n\n– The {$siteName} Team";
                    @mail($email, $subject, $body);

                    $success = "Invitation sent successfully to {$email}! You can also share the direct invite link below.";
                }
            }
        } elseif ($action === 'remove') {
            $memberId = (int)($_POST['member_id'] ?? 0);
            if ($memberId > 0) {
                $member = db()->fetchOne(
                    "SELECT id FROM team_members WHERE id = ? AND parent_user_id = ?",
                    [$memberId, $userId]
                );
                if ($member) {
                    db()->delete('team_members', 'id = ?', [$memberId]);
                    $success = 'Team member access removed.';
                }
            }
        }
    }
}

$members = [];
try {
    $members = db()->fetchAll(
        "SELECT tm.*, u.full_name as user_name FROM team_members tm LEFT JOIN users u ON u.id = tm.user_id WHERE tm.parent_user_id = ? ORDER BY tm.created_at DESC",
        [$userId]
    );
} catch (\Throwable $e) {
    $members = [];
}

$pageTitle = 'Team & Workspace Collaborators — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Team Management</h1>
            <p class="page-subtitle">Invite team collaborators, assign role permissions, and control workspace access.</p>
        </div>
        <div>
            <a href="<?= url('dashboard') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="padding:14px 20px;border-radius:12px;margin-bottom:24px;">
            <i class="ph ph-check-circle me-1"></i> <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="padding:14px 20px;border-radius:12px;margin-bottom:24px;">
            <?php foreach ($errors as $err): ?>
                <div><i class="ph ph-warning-circle me-1"></i> <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Workspace Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-dark" style="padding:20px;display:flex;align-items:center;gap:16px;">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(200,255,0,0.12);border:1px solid rgba(200,255,0,0.3);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                    <i class="ph ph-users-three" style="font-size:24px;"></i>
                </div>
                <div>
                    <span style="font-size:12px;color:var(--fg3);text-transform:uppercase;">Workspace Members</span>
                    <h3 style="color:#fff;font-weight:700;margin:0;"><?= count($members) + 1 ?> Active</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-dark" style="padding:20px;display:flex;align-items:center;gap:16px;">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);display:flex;align-items:center;justify-content:center;color:var(--green);">
                    <i class="ph ph-shield-check" style="font-size:24px;"></i>
                </div>
                <div>
                    <span style="font-size:12px;color:var(--fg3);text-transform:uppercase;">Access Protocol</span>
                    <h3 style="color:#fff;font-weight:700;margin:0;">Role-Based (RBAC)</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-dark" style="padding:20px;display:flex;align-items:center;gap:16px;">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);display:flex;align-items:center;justify-content:center;color:#3b82f6;">
                    <i class="ph ph-ticket" style="font-size:24px;"></i>
                </div>
                <div>
                    <span style="font-size:12px;color:var(--fg3);text-transform:uppercase;">Team Plan Seats</span>
                    <h3 style="color:#fff;font-weight:700;margin:0;">Unlimited Seats</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Invite Team Member Form -->
    <div class="card-dark" style="padding:28px;margin-bottom:32px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
            <i class="ph ph-user-plus" style="font-size:22px;color:var(--accent);"></i>
            <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">Invite a New Team Member</h4>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="invite">

            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label" style="font-size:13px;font-weight:600;color:var(--fg);">Email Address</label>
                    <input type="email" name="invite_email" class="form-control" placeholder="colleague@company.com" required style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px 16px;">
                </div>
                <div class="col-lg-4">
                    <label class="form-label" style="font-size:13px;font-weight:600;color:var(--fg);">Role &amp; Permissions</label>
                    <select name="invite_role" class="form-select" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px 16px;">
                        <option value="editor">Editor — Can build ads, schedule posts &amp; creatives</option>
                        <option value="viewer">Viewer — Read-only analytics, reports &amp; calendar</option>
                        <option value="admin">Admin — Full workspace management &amp; billing</option>
                    </select>
                </div>
                <div class="col-lg-2 d-flex align-items-end">
                    <button type="submit" class="btn-accent w-100" style="padding:12px;font-weight:600;">
                        <i class="ph ph-paper-plane-tilt me-1"></i> Send Invite
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Team Members Table -->
    <div class="card-dark" style="padding:28px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <i class="ph ph-users" style="font-size:22px;color:var(--accent);"></i>
                <h4 style="color:#fff;font-size:17px;font-weight:700;margin:0;">Active Collaborators &amp; Invitations</h4>
            </div>
            <span class="badge-status badge-active"><?= count($members) + 1 ?> Total Members</span>
        </div>

        <div class="table-responsive">
            <table class="table table-dark align-middle mb-0" style="--bs-table-bg: transparent; background: transparent; color:var(--fg);">
                <thead>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.08);font-size:12px;color:var(--fg3);text-transform:uppercase;">
                        <th>Collaborator</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Invited Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Workspace Owner Row -->
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                        <td>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg, var(--accent), #10b981);color:#080c14;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;">
                                    <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;color:#fff;font-size:14px;display:flex;align-items:center;gap:6px;">
                                        <?= htmlspecialchars($user['full_name'] ?? 'Account Owner') ?>
                                        <span class="badge-accent" style="font-size:10px;padding:2px 6px;">You (Owner)</span>
                                    </div>
                                    <span style="font-size:12px;color:var(--fg3);"><?= htmlspecialchars($user['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge-platform" style="color:var(--accent);border-color:rgba(200,255,0,0.3);">Primary Owner</span>
                        </td>
                        <td>
                            <span class="badge-status badge-active" style="display:inline-flex;align-items:center;gap:4px;">
                                <span style="width:6px;height:6px;border-radius:50%;background:var(--green);display:inline-block;"></span>
                                Active
                            </span>
                        </td>
                        <td style="font-size:13px;color:var(--fg2);"><?= date('M d, Y', strtotime($user['created_at'] ?? 'now')) ?></td>
                        <td class="text-end" style="color:var(--fg3);font-size:12px;">Default Admin</td>
                    </tr>

                    <!-- Invited Members -->
                    <?php foreach ($members as $m): 
                        $initial = strtoupper(substr($m['user_name'] ?? $m['email'], 0, 1));
                        $inviteLink = BASE_URL . "/register.php?invite=" . ($m['invite_token'] ?? '');
                    ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                            <td>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;">
                                        <?= $initial ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;color:#fff;font-size:14px;">
                                            <?= htmlspecialchars($m['user_name'] ?? $m['email']) ?>
                                        </div>
                                        <span style="font-size:12px;color:var(--fg3);"><?= htmlspecialchars($m['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-platform" style="text-transform:capitalize;">
                                    <?= htmlspecialchars($m['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($m['status'] === 'active'): ?>
                                    <span class="badge-status badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge-status badge-inactive">Pending Acceptance</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:13px;color:var(--fg2);">
                                <?= date('M d, Y', strtotime($m['invited_at'] ?? $m['created_at'])) ?>
                            </td>
                            <td class="text-end">
                                <div style="display:inline-flex;gap:8px;">
                                    <?php if ($m['status'] === 'pending' && !empty($m['invite_token'])): ?>
                                        <button type="button" class="btn-outline-dark btn-sm" onclick="copyInviteLink('<?= e($inviteLink) ?>')" title="Copy Invite Link">
                                            <i class="ph ph-link"></i> Copy Link
                                        </button>
                                    <?php endif; ?>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Remove collaborator <?= e($m['email']) ?>?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                                        <button type="submit" class="btn-outline-dark btn-sm" style="color:#f43f5e;border-color:rgba(244,63,94,0.3);" title="Remove Access">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyInviteLink(link) {
    navigator.clipboard.writeText(link).then(() => {
        alert('Invitation link copied to clipboard!\nShare this with your collaborator:\n' + link);
    }).catch(() => {
        prompt('Copy invitation link:', link);
    });
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
