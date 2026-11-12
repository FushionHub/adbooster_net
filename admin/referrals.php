<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/referral_service.php';

$pageTitle = 'Referral Management';

/**
 * Create or update a site setting row.
 */
function referral_save_setting(string $key, string $value, string $group = 'referrals'): void
{
    $exists = db()->fetchOne("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
    if ($exists !== null) {
        db()->update('site_settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        return;
    }
    db()->insert('site_settings', [
        'setting_key'   => $key,
        'setting_value' => $value,
        'setting_group' => $group,
    ]);
}

// ============================================================
//  Actions
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
        redirect(url('admin/referrals'));
    }

    $act = $_POST['act'] ?? '';
    $refId = (int)($_POST['referral_id'] ?? 0);

    switch ($act) {
        // ── Adjust the reward amount on a single referral ──────────
        case 'update_reward':
            if ($refId > 0) {
                $reward = round(max(0.0, (float)($_POST['reward_credits'] ?? 0)), 2);
                db()->update('referrals', [
                    'reward_credits' => $reward,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ], 'id = ?', [$refId]);
                set_flash('success', "Referral #{$refId} reward set to {$reward} credits.");
            }
            break;

        // ── Pay the referral NOW (credits the referrer for real) ───
        case 'pay_reward':
            $result = ReferralService::payReward($refId, 'Manual admin payout');
            set_flash($result['rewarded'] ? 'success' : 'error', $result['message']);
            break;

        // ── Reset a paid referral back to pending ───────────────────
        case 'reset_pending':
            $result = ReferralService::resetToPending($refId);
            set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            break;

        // ── Reject a referral (fraud / self-referral) ───────────────
        case 'reject':
            $result = ReferralService::reject($refId);
            set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            break;

        // ── Pay every pending referral in one go ────────────────────
        case 'pay_all_pending':
            $pending = db()->fetchAll("SELECT id FROM referrals WHERE status = 'pending' ORDER BY id ASC");
            $paid = 0;
            $skipped = 0;
            foreach ($pending as $row) {
                $result = ReferralService::payReward((int)$row['id'], 'Bulk admin payout');
                if (!empty($result['rewarded'])) {
                    $paid++;
                } else {
                    $skipped++;
                }
            }
            set_flash(
                'success',
                "Bulk payout finished: {$paid} referral(s) rewarded"
                . ($skipped > 0 ? ", {$skipped} skipped." : '.')
            );
            break;

        // ── Programme-wide reward settings ──────────────────────────
        case 'save_settings':
            $referrerReward = round(max(0.0, (float)($_POST['referral_reward_credits'] ?? 10)), 2);
            $referredBonus  = round(max(0.0, (float)($_POST['referral_referred_bonus'] ?? 5)), 2);
            $autoReward     = isset($_POST['referral_auto_reward']) ? '1' : '0';

            referral_save_setting('referral_reward_credits', (string)$referrerReward);
            referral_save_setting('referral_referred_bonus', (string)$referredBonus);
            referral_save_setting('referral_auto_reward', $autoReward);

            set_flash(
                'success',
                'Referral settings saved: referrer gets {$referrerReward} credits, '
                . "referred account gets {$referredBonus} credits, automatic reward "
                . ($autoReward === '1' ? 'enabled' : 'disabled') . '.'
            );
            break;
    }

    redirect(url('admin/referrals'));
}

// ============================================================
//  Data
// ============================================================
$filterStatus = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if (in_array($filterStatus, ['pending', 'rewarded', 'rejected'], true)) {
    $where .= ' AND r.status = ?';
    $params[] = $filterStatus;
}

$referrals = db()->fetchAll(
    "SELECT r.*,
            u1.full_name as referrer_name, u1.email as referrer_email,
            u2.full_name as referred_name, u2.email as referred_email
     FROM referrals r
     LEFT JOIN users u1 ON u1.id = r.referrer_id
     LEFT JOIN users u2 ON u2.id = COALESCE(r.referred_id, r.referred_user_id)
     WHERE {$where}
     ORDER BY r.created_at DESC LIMIT 200",
    $params
);

$totalReferrals = db()->count('referrals');
$pendingCount   = db()->count('referrals', 'status = ?', ['pending']);
$rewardedCount  = db()->count('referrals', 'status = ?', ['rewarded']);
$rejectedCount  = db()->count('referrals', 'status = ?', ['rejected']);
$totalRewards   = (float)(db()->fetchOne(
    "SELECT COALESCE(SUM(reward_credits), 0) AS total FROM referrals WHERE status = 'rewarded'"
)['total'] ?? 0);
$pendingValue   = (float)(db()->fetchOne(
    "SELECT COALESCE(SUM(reward_credits), 0) AS total FROM referrals WHERE status = 'pending'"
)['total'] ?? 0);

$settings = ReferralService::settings();

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="card-title">Total Referrals</div>
            <div class="card-value"><?= number_format($totalReferrals) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="card-title">Pending Rewards</div>
            <div class="card-value" style="color:#eab308;"><?= number_format($pendingCount) ?></div>
            <div style="font-size:0.72rem;color:#8b93a7;margin-top:4px;"><?= number_format($pendingValue, 2) ?> credits queued</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="card-title">Rewards Paid</div>
            <div class="card-value" style="color:#22c55e;"><?= number_format($rewardedCount) ?></div>
            <div style="font-size:0.72rem;color:#8b93a7;margin-top:4px;"><?= number_format($totalRewards, 2) ?> credits issued</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <div class="card-title">Rejected</div>
            <div class="card-value" style="color:#94a3b8;"><?= number_format($rejectedCount) ?></div>
            <div style="font-size:0.72rem;color:#8b93a7;margin-top:4px;">never paid</div>
        </div>
    </div>
</div>

<!-- Programme settings -->
<div class="admin-card mb-4">
    <div class="card-title mb-3">
        <i class="ph-bold ph-sliders-horizontal me-1"></i> Referral Programme Settings
    </div>
    <form method="POST" class="d-flex flex-wrap gap-3 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="save_settings">

        <div>
            <label class="form-label" style="font-size:0.75rem;color:#8b93a7;">Referrer reward (ad credits)</label>
            <input type="number" name="referral_reward_credits" class="form-control-admin" style="width:150px;" min="0" step="0.01"
                   value="<?= e((string)$settings['referrer_reward']) ?>">
        </div>
        <div>
            <label class="form-label" style="font-size:0.75rem;color:#8b93a7;">Referred account bonus (ad credits)</label>
            <input type="number" name="referral_referred_bonus" class="form-control-admin" style="width:150px;" min="0" step="0.01"
                   value="<?= e((string)$settings['referred_bonus']) ?>">
        </div>
        <div class="form-check ms-2 mb-2">
            <input class="form-check-input" type="checkbox" name="referral_auto_reward" id="autoReward" value="1"
                   <?= $settings['auto_reward'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="autoReward" style="font-size:0.8rem;color:#e5e9f0;">
                Auto-reward on verified payment
            </label>
        </div>
        <button class="btn-admin btn-admin-green">
            <i class="ph-bold ph-floppy-disk"></i> Save Settings
        </button>
        <div style="font-size:0.74rem;color:#6f7891;flex-basis:100%;">
            When auto-reward is on, a referrer is paid automatically the moment a referred
            user's payment is verified (checkout callback or gateway webhook). When it is off,
            every reward has to be released manually from this page.
        </div>
    </form>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
        <select name="status" class="form-select-admin" onchange="this.form.submit()">
            <option value="">All Status</option>
            <?php foreach (['pending', 'rewarded', 'rejected'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="?" class="btn-admin btn-admin-outline">Reset</a>
    </form>
    <?php if ($pendingCount > 0): ?>
        <form method="POST" class="ms-auto" onsubmit="return confirm('Pay all <?= $pendingCount ?> pending referral(s) now?')">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="pay_all_pending">
            <button class="btn-admin btn-admin-green">
                <i class="ph-bold ph-seal-check"></i> Pay All Pending (<?= $pendingCount ?>)
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>Referrer</th><th>Referred</th><th>Status</th><th>Reward (Ad Credits)</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($referrals as $r): ?>
                <?php $referredUser = $r['referred_name'] ?? null; ?>
                <tr>
                    <td class="text-white fw-bold">#<?= (int)$r['id'] ?></td>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($r['referrer_name'] ?? 'N/A') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($r['referrer_email'] ?? '') ?></div>
                    </td>
                    <td>
                        <div class="text-white fw-medium"><?= htmlspecialchars($referredUser ?? 'Not linked yet') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($r['referred_email'] ?? '') ?></div>
                    </td>
                    <td><span class="badge-status <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td class="text-white fw-bold"><?= number_format((float)$r['reward_credits'], 2) ?> credits</td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= dt($r['created_at'] ?? null) ?></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <form method="POST" class="d-flex gap-1 align-items-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="update_reward">
                                <input type="hidden" name="referral_id" value="<?= (int)$r['id'] ?>">
                                <input type="number" name="reward_credits" class="form-control-admin" style="width:80px;" value="<?= e((string)$r['reward_credits']) ?>" min="0" step="0.01">
                                <button class="btn-admin btn-admin-blue" title="Update reward amount"><i class="ph-bold ph-check"></i></button>
                            </form>

                            <?php if ($r['status'] !== 'rewarded'): ?>
                                <form method="POST" onsubmit="return confirm('Pay this referral now? The referrer gets credited immediately.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="pay_reward">
                                    <input type="hidden" name="referral_id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn-admin btn-admin-green" title="Pay reward now"><i class="ph-bold ph-seal-check"></i></button>
                                </form>
                            <?php endif; ?>

                            <?php if ($r['status'] === 'rewarded'): ?>
                                <form method="POST" onsubmit="return confirm('Reset this referral to pending? Credits already paid are not clawed back.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="reset_pending">
                                    <input type="hidden" name="referral_id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn-admin btn-admin-outline" title="Reset to pending"><i class="ph-bold ph-arrow-counter-clockwise"></i></button>
                                </form>
                            <?php else: ?>
                                <form method="POST" onsubmit="return confirm('Reject this referral? It will never be paid.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="reject">
                                    <input type="hidden" name="referral_id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn-admin btn-admin-outline" title="Reject referral"><i class="ph-bold ph-ban"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($referrals)): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">No referrals found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
