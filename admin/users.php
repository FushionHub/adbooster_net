<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'User Management';

// ─────────────────────────────────────────────────────────────
//  POST actions
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
        redirect(url('admin/users'));
    }

    $act = (string)($_POST['act'] ?? '');
    $uId = (int)($_POST['user_id'] ?? 0);

    if ($act === 'delete') {
        // Deleting is destructive — require the exact email as confirmation.
        $confirmEmail = trim((string)($_POST['confirm_email'] ?? ''));
        $target = $uId > 0 ? db()->fetchOne("SELECT * FROM users WHERE id=?", [$uId]) : null;
        if (!$target) {
            set_flash('error', 'User not found.');
        } elseif ((int)$target['id'] === (int)current_user_id()) {
            set_flash('error', 'You cannot delete your own admin account.');
        } elseif ($target['role'] === 'admin') {
            set_flash('error', 'Admin accounts cannot be deleted from here.');
        } elseif (!hash_equals($target['email'], $confirmEmail)) {
            set_flash('error', 'Confirmation email did not match — deletion cancelled.');
        } else {
            db()->delete('users', 'id=?', [$uId]);
            set_flash('success', "User {$target['email']} (#{ $uId }) deleted permanently.");
        }
        redirect(url('admin/users'));
    }

    if ($uId <= 0) {
        set_flash('error', 'Missing user.');
        redirect(url('admin/users'));
    }

    $user = db()->fetchOne("SELECT * FROM users WHERE id=?", [$uId]);
    if (!$user) {
        set_flash('error', 'User not found.');
        redirect(url('admin/users'));
    }

    switch ($act) {
        case 'topup_ad':
            $amount = (float)($_POST['amount'] ?? 0);
            if ($amount !== 0.0) {
                db()->update('users', ['ad_credits' => max(0, (float)$user['ad_credits'] + $amount)], 'id=?', [$uId]);
                set_flash('success', sprintf('%+.2f ad credits for %s.', $amount, $user['email']));
            }
            break;

        case 'topup_ai':
            $amount = (int)($_POST['amount'] ?? 0);
            if ($amount !== 0) {
                db()->update('users', ['ai_credits' => max(0, (int)$user['ai_credits'] + $amount)], 'id=?', [$uId]);
                set_flash('success', sprintf('%+d AI credits for %s.', $amount, $user['email']));
            }
            break;

        case 'edit_profile':
            $name  = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $company = trim((string)($_POST['company'] ?? ''));
            if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                set_flash('error', 'A valid full name and email are required.');
                break;
            }
            $clash = db()->fetchOne("SELECT id FROM users WHERE email=? AND id<>?", [$email, $uId]);
            if ($clash) {
                set_flash('error', 'That email is already used by another account.');
                break;
            }
            db()->update('users', [
                'full_name' => $name,
                'email'     => $email,
                'phone'     => $phone,
                'company'   => $company,
            ], 'id=?', [$uId]);
            set_flash('success', "Profile updated for {$email}.");
            break;

        case 'suspend':
            db()->update('users', ['status' => 'suspended'], 'id=?', [$uId]);
            set_flash('success', "User #{$uId} suspended.");
            break;

        case 'activate':
            db()->update('users', ['status' => 'active'], 'id=?', [$uId]);
            set_flash('success', "User #{$uId} activated.");
            break;

        case 'verify_seller':
            $newVal = $user['is_verified_seller'] ? 0 : 1;
            db()->update('users', ['is_verified_seller' => $newVal], 'id=?', [$uId]);
            set_flash('success', 'Seller verification ' . ($newVal ? 'enabled' : 'disabled') . " for user #{$uId}.");
            break;

        case 'upgrade_plan':
            $planId = (int)($_POST['plan_id'] ?? 0);
            $patch  = ['plan_id' => $planId > 0 ? $planId : null];
            if ($planId > 0) {
                $plan = db()->fetchOne("SELECT * FROM pricing_plans WHERE id=?", [$planId]);
                if ($plan) {
                    $cycle = (string)($plan['billing_cycle'] ?? $plan['interval'] ?? 'monthly');
                    $days  = ($cycle === 'yearly' || $cycle === 'annual') ? 365 : 30;
                    $patch['billing_cycle'] = $cycle !== '' ? $cycle : 'monthly';
                    $patch['plan_expiry']   = date('Y-m-d', strtotime("+{$days} days"));
                }
            } else {
                $patch['billing_cycle'] = null;
                $patch['plan_expiry']   = null;
            }
            db()->update('users', $patch, 'id=?', [$uId]);
            set_flash('success', "Plan updated for user #{$uId}.");
            break;

        case 'reset_password':
            $temp = bin2hex(random_bytes(6)); // 12-char one-time password
            db()->update('users', ['password' => password_hash($temp, PASSWORD_DEFAULT)], 'id=?', [$uId]);
            set_flash('success', "Password reset for {$user['email']}. Temporary password: {$temp} — share it securely, the user must change it after login.");
            break;

        case 'reset_2fa':
            // `2fa_attempts` starts with a digit so it must be backticked —
            // the generic update() interpolates keys raw.
            db()->update('users', [
                'totp_secret'         => null,
                'totp_enabled'        => 0,
                'two_factor_enabled'  => 0,
                'two_factor_secret'   => null,
                'two_factor_type'     => null,
                'two_factor_backup'   => null,
                'two_factor_setup_at' => null,
                'email_2fa_code'      => null,
                'email_2fa_expires_at'=> null,
                '`2fa_attempts`'      => 0,
            ], 'id=?', [$uId]);
            set_flash('success', "Two-factor authentication cleared for {$user['email']}. They can enrol again on next login.");
            break;

        case 'impersonate':
            if ((int)$uId === (int)current_user_id()) {
                set_flash('error', 'You are already yourself.');
                break;
            }
            if ($user['status'] !== 'active') {
                set_flash('error', 'Only active accounts can be impersonated.');
                break;
            }
            $_SESSION['impersonator'] = [
                'id'   => (int)current_user_id(),
                'name' => (string)($_SESSION['admin_name'] ?? 'Admin'),
            ];
            auth_login($user);
            set_flash('success', "Viewing the site as {$user['full_name']}. Use the yellow banner to return to the admin panel.");
            redirect(url('dashboard'));
            break;
    }

    redirect(url('admin/users'));
}

// ─────────────────────────────────────────────────────────────
//  Listing, filters, KPIs
// ─────────────────────────────────────────────────────────────
$search   = trim((string)($_GET['q'] ?? ''));
$fStatus  = trim((string)($_GET['status'] ?? ''));
$fRole    = trim((string)($_GET['role'] ?? ''));
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;

$conds  = ['1=1'];
$params = [];
if ($search !== '') {
    $conds[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (in_array($fStatus, ['active', 'suspended', 'pending'], true)) {
    $conds[]  = 'u.status = ?';
    $params[] = $fStatus;
}
if (in_array($fRole, ['user', 'team_member', 'admin'], true)) {
    $conds[]  = 'u.role = ?';
    $params[] = $fRole;
}
$where = implode(' AND ', $conds);

$total = (int)db()->fetchOne("SELECT COUNT(*) AS c FROM users u WHERE {$where}", $params)['c'];
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$users = db()->fetchAll(
    "SELECT u.*, p.name AS plan_name FROM users u
     LEFT JOIN pricing_plans p ON p.id = u.plan_id
     WHERE {$where}
     ORDER BY u.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);
$plans = db()->fetchAll("SELECT id, name FROM pricing_plans WHERE status='active' ORDER BY name");

$kpi = db()->fetchOne(
    "SELECT COUNT(*) AS total,
            SUM(status='active')   AS active,
            SUM(status='suspended') AS suspended,
            SUM(status='pending')  AS pending,
            COALESCE(SUM(ai_credits),0) AS ai_total,
            COALESCE(SUM(ad_credits),0) AS ad_total
     FROM users"
);

function user_query(array $extra): string
{
    $qs = http_build_query(array_merge($_GET, $extra, ['page' => null]));
    return '?' . preg_replace('/page=$|&page=$/', '', $qs);
}

require_once __DIR__ . '/sidebar.php';
?>

<!-- KPI row -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label' => 'Total Users',   'value' => number_format((int)$kpi['total']),    'icon' => 'ph-users-three', 'color' => 'green'],
        ['label' => 'Active',        'value' => number_format((int)$kpi['active']),   'icon' => 'ph-user-check',  'color' => 'blue'],
        ['label' => 'Suspended',     'value' => number_format((int)$kpi['suspended']),'icon' => 'ph-user-minus',  'color' => 'red'],
        ['label' => 'Pending',       'value' => number_format((int)$kpi['pending']),  'icon' => 'ph-user-clock',  'color' => 'yellow'],
        ['label' => 'AI Credits Out','value' => number_format((int)$kpi['ai_total']), 'icon' => 'ph-sparkle',     'color' => 'purple'],
        ['label' => 'Ad Credits Out','value' => number_format((float)$kpi['ad_total'], 2), 'icon' => 'ph-coins',  'color' => 'green'],
    ];
    foreach ($kpis as $k): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-card h-100 mb-0">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="card-icon <?= $k['color'] ?>" style="width:34px;height:34px;font-size:1rem;"><i class="ph-bold <?= $k['icon'] ?>"></i></div>
                <div class="card-title mb-0"><?= $k['label'] ?></div>
            </div>
            <div class="card-value" style="font-size:1.4rem;"><?= $k['value'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="GET" class="admin-card">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label-admin">Search</label>
            <input type="text" name="q" class="form-control-admin w-100" placeholder="Name, email, or phone..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label-admin">Status</label>
            <select name="status" class="form-select-admin w-100">
                <option value="">All statuses</option>
                <?php foreach (['active', 'suspended', 'pending'] as $s): ?>
                    <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label-admin">Role</label>
            <select name="role" class="form-select-admin w-100">
                <option value="">All roles</option>
                <?php foreach (['user', 'team_member', 'admin'] as $r): ?>
                    <option value="<?= $r ?>" <?= $fRole === $r ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn-admin btn-admin-green flex-grow-1"><i class="ph-bold ph-funnel"></i> Filter</button>
            <a href="<?= url('admin/users') ?>" class="btn-admin btn-admin-outline" title="Reset">✕</a>
        </div>
    </div>
    <div class="text-muted mt-2" style="font-size:0.78rem;">
        <?= number_format($total) ?> user(s) match · page <?= $page ?> of <?= $pages ?>
    </div>
</form>

<!-- Users table -->
<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th><th>Role</th><th>Ad Cr.</th><th>AI Cr.</th><th>Plan</th>
                    <th>Seller</th><th>2FA</th><th>Status</th><th>Joined</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar"><?= strtoupper(substr($u['full_name'] ?? 'A', 0, 1)) ?></div>
                            <div>
                                <div class="text-white fw-medium"><?= e($u['full_name'] ?? 'N/A') ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= e($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-capitalize"><?= e($u['role']) ?></td>
                    <td class="text-white"><?= number_format((float)$u['ad_credits'], 2) ?></td>
                    <td class="text-white"><?= number_format((int)$u['ai_credits']) ?></td>
                    <td><?= e($u['plan_name'] ?? 'Free') ?></td>
                    <td>
                        <?php if ($u['is_verified_seller']): ?>
                            <span class="badge-status active">Verified</span>
                        <?php else: ?>
                            <span class="badge-status inactive">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($u['two_factor_enabled']) || !empty($u['totp_enabled'])): ?>
                            <span class="badge-status active">On</span>
                        <?php else: ?>
                            <span class="badge-status inactive">Off</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-status <?= e($u['status']) ?>"><?= e($u['status']) ?></span></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td class="d-flex gap-1">
                        <button class="btn-admin btn-admin-outline" data-bs-toggle="modal" data-bs-target="#userModal<?= $u['id'] ?>" title="Manage">
                            <i class="ph-bold ph-gear"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('View the site as <?= e($u['full_name'] ?? $u['email']) ?>? You can exit from the yellow banner.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act" value="impersonate">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-admin btn-admin-yellow" title="Impersonate"><i class="ph-bold ph-mask-happy"></i></button>
                        </form>
                    </td>
                </tr>

                <!-- Manage modal -->
                <div class="modal fade" id="userModal<?= $u['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content" style="background:var(--admin-card);border:1px solid var(--admin-border);">
                            <div class="modal-header" style="border-bottom:1px solid var(--admin-border);">
                                <h6 class="text-white fw-bold mb-0">
                                    Manage: <?= e($u['full_name'] ?? $u['email']) ?>
                                    <span class="badge-status <?= e($u['status']) ?> ms-2"><?= e($u['status']) ?></span>
                                </h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">

                                    <!-- Profile -->
                                    <div class="col-12">
                                        <div class="form-label-admin mb-2"><i class="ph-bold ph-user me-1"></i> Profile</div>
                                        <form method="POST" class="row g-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="edit_profile">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <div class="col-md-6">
                                                <input type="text" name="full_name" class="form-control-admin w-100" placeholder="Full name" value="<?= e($u['full_name'] ?? '') ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="email" name="email" class="form-control-admin w-100" placeholder="Email" value="<?= e($u['email']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" name="phone" class="form-control-admin w-100" placeholder="Phone" value="<?= e($u['phone'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" name="company" class="form-control-admin w-100" placeholder="Company" value="<?= e($u['company'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn-admin btn-admin-blue w-100">Save</button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Credits -->
                                    <div class="col-md-6">
                                        <div class="form-label-admin mb-2"><i class="ph-bold ph-coins me-1"></i> Ad Credits (current: <?= number_format((float)$u['ad_credits'], 2) ?>)</div>
                                        <form method="POST" class="d-flex gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="topup_ad">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="number" step="0.01" name="amount" class="form-control-admin flex-grow-1" placeholder="+100 or -50" required>
                                            <button type="submit" class="btn-admin btn-admin-green">Apply</button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-label-admin mb-2"><i class="ph-bold ph-sparkle me-1"></i> AI Credits (current: <?= number_format((int)$u['ai_credits']) ?>)</div>
                                        <form method="POST" class="d-flex gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="topup_ai">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="number" step="1" name="amount" class="form-control-admin flex-grow-1" placeholder="+50 or -10" required>
                                            <button type="submit" class="btn-admin btn-admin-green">Apply</button>
                                        </form>
                                    </div>

                                    <!-- Plan -->
                                    <div class="col-12">
                                        <div class="form-label-admin mb-2"><i class="ph-bold ph-crown me-1"></i> Subscription Plan</div>
                                        <form method="POST" class="d-flex gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="upgrade_plan">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="plan_id" class="form-select-admin flex-grow-1">
                                                <option value="0">Free (No Plan)</option>
                                                <?php foreach ($plans as $p): ?>
                                                    <option value="<?= $p['id'] ?>" <?= (int)$u['plan_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-admin btn-admin-blue">Update</button>
                                        </form>
                                        <?php if (!empty($u['plan_expiry'])): ?>
                                            <small class="text-muted" style="font-size:0.7rem;">Expires <?= e($u['plan_expiry']) ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Account actions -->
                                    <div class="col-12">
                                        <div class="form-label-admin mb-2"><i class="ph-bold ph-shield me-1"></i> Account Actions</div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <?php if ($u['status'] === 'active'): ?>
                                                <form method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="act" value="suspend">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button class="btn-admin btn-admin-red" onclick="return confirm('Suspend this user?')"><i class="ph-bold ph-lock"></i> Suspend</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="act" value="activate">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button class="btn-admin btn-admin-green"><i class="ph-bold ph-check"></i> Activate</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="verify_seller">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button class="btn-admin btn-admin-yellow">
                                                    <i class="ph-bold ph-seal-check"></i>
                                                    <?= $u['is_verified_seller'] ? 'Unverify Seller' : 'Verify Seller' ?>
                                                </button>
                                            </form>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button class="btn-admin btn-admin-blue" onclick="return confirm('Generate a one-time password for this user?')"><i class="ph-bold ph-key"></i> Reset Password</button>
                                            </form>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="reset_2fa">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button class="btn-admin btn-admin-outline" onclick="return confirm('Clear two-factor authentication for this user?')"><i class="ph-bold ph-shield-check"></i> Reset 2FA</button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Danger zone -->
                                    <?php if ($u['role'] !== 'admin'): ?>
                                    <div class="col-12 mt-2">
                                        <div class="form-label-admin mb-2 text-danger"><i class="ph-bold ph-warning me-1"></i> Danger Zone</div>
                                        <form method="POST" onsubmit="return confirm('PERMANENTLY delete this user and their login? This cannot be undone.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <div class="d-flex gap-2">
                                                <input type="email" name="confirm_email" class="form-control-admin flex-grow-1" placeholder="Type the user's email to confirm" required>
                                                <button type="submit" class="btn-admin btn-admin-red"><i class="ph-bold ph-trash"></i> Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="10" class="text-muted text-center py-4">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="text-muted" style="font-size:0.78rem;">Page <?= $page ?> of <?= $pages ?></div>
        <div class="pagination-admin">
            <?php if ($page > 1): ?>
                <a class="page-btn" href="<?= e(user_query(['page' => $page - 1])) ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($pages, $page + 2);
            if ($start > 1): ?>
                <a class="page-btn" href="<?= e(user_query(['page' => 1])) ?>">1</a>
                <?php if ($start > 2): ?><span class="page-btn" style="border:none;background:none;">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <a class="page-btn<?= $i === $page ? ' active' : '' ?>" href="<?= e(user_query(['page' => $i])) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($end < $pages): ?>
                <?php if ($end < $pages - 1): ?><span class="page-btn" style="border:none;background:none;">…</span><?php endif; ?>
                <a class="page-btn" href="<?= e(user_query(['page' => $pages])) ?>"><?= $pages ?></a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="page-btn" href="<?= e(user_query(['page' => $page + 1])) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
