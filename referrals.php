<?php declare(strict_types=1);

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/notification_service.php';

$userId = current_user_id();
$user = current_user();
$errors  = [];
$success = '';

// Auto-generate unique referral code if missing
$referralCode = $user['referral_code'] ?? '';
if ($referralCode === '') {
    $referralCode = strtoupper(substr(md5((string)$userId . 'adsb'), 0, 8));
    db()->update('users', ['referral_code' => $referralCode], 'id = ?', [$userId]);
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$referralLink = BASE_URL . '/register.php?ref=' . $referralCode;

// Handle payout request & convert to credits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'];

        // Calculate available earned commission
        $earnedRow = db()->fetchOne(
            "SELECT COALESCE(SUM(reward_credits), 0) as total FROM referrals WHERE referrer_id = ? AND status = 'rewarded'",
            [$userId]
        );
        $totalEarned = (float)($earnedRow['total'] ?? 0);

        $paidOutRow = db()->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) as total FROM payout_requests WHERE user_id = ? AND status IN ('pending','approved','paid')",
            [$userId]
        );
        $totalRequested = (float)($paidOutRow['total'] ?? 0);
        $availableCommission = max(0.0, $totalEarned - $totalRequested);

        if ($action === 'request_payout') {
            $amount = (float)($_POST['amount'] ?? 0);
            $payoutMethod = trim($_POST['payout_method'] ?? 'bank');
            $details = [
                'method'         => $payoutMethod,
                'bank_name'      => trim($_POST['bank_name'] ?? ''),
                'account_number' => trim($_POST['account_number'] ?? ''),
                'account_name'   => trim($_POST['account_name'] ?? ''),
                'paypal_email'   => trim($_POST['paypal_email'] ?? ''),
                'crypto_address' => trim($_POST['crypto_address'] ?? ''),
            ];

            if ($amount < 20.00) {
                $errors[] = 'Minimum withdrawal request is $20.00.';
            } elseif ($amount > $availableCommission) {
                $errors[] = 'Insufficient commission balance. Available: $' . number_format($availableCommission, 2);
            } else {
                db()->insert('payout_requests', [
                    'user_id'      => $userId,
                    'amount'       => $amount,
                    'method'       => $payoutMethod,
                    'details_json' => json_encode($details),
                    'status'       => 'pending',
                ]);
                NotificationService::create($userId, 'payout_requested', 'Payout Request Submitted', 'Your withdrawal request for $' . number_format($amount, 2) . ' via ' . ucfirst($payoutMethod) . ' has been received and is being processed.', '/referrals.php');
                $success = 'Payout request for $' . number_format($amount, 2) . ' submitted successfully! Payouts are processed within 24-48 hours.';
            }

        } elseif ($action === 'convert_to_credits') {
            $amount = (float)($_POST['convert_amount'] ?? 0);
            $creditType = trim($_POST['credit_type'] ?? 'ad_credits');

            if ($amount <= 0) {
                $errors[] = 'Please specify a valid conversion amount.';
            } elseif ($amount > $availableCommission) {
                $errors[] = 'Insufficient commission balance. Available: $' . number_format($availableCommission, 2);
            } else {
                // Record payout conversion
                db()->insert('payout_requests', [
                    'user_id'      => $userId,
                    'amount'       => $amount,
                    'method'       => 'credit_conversion',
                    'details_json' => json_encode(['converted_to' => $creditType, 'timestamp' => date('Y-m-d H:i:s')]),
                    'status'       => 'paid',
                ]);

                if ($creditType === 'ad_credits') {
                    // 10% bonus on ad credits conversion
                    $adBonus = round($amount * 1.10, 2);
                    db()->query("UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?", [$adBonus, $userId]);
                    $msg = 'Successfully converted $' . number_format($amount, 2) . ' into $' . number_format($adBonus, 2) . ' in Ad Credits (including 10% bonus)!';
                } else {
                    // Convert into AI credits: 1 USD = 20 AI Credits
                    $aiCount = (int)($amount * 20);
                    db()->query("UPDATE users SET ai_credits = COALESCE(ai_credits, 0) + ? WHERE id = ?", [$aiCount, $userId]);
                    $msg = 'Successfully converted $' . number_format($amount, 2) . ' into ' . number_format($aiCount) . ' AI Credits!';
                }
                $success = $msg;
            }
        }
    }
}

$totalReferrals = db()->count('referrals', 'referrer_id = ?', [$userId]);
$pendingRewards = db()->fetchOne(
    "SELECT COALESCE(SUM(reward_credits), 0) as total FROM referrals WHERE referrer_id = ? AND status = 'pending'",
    [$userId]
);
$earnedRewards = db()->fetchOne(
    "SELECT COALESCE(SUM(reward_credits), 0) as total FROM referrals WHERE referrer_id = ? AND status = 'rewarded'",
    [$userId]
);

$paidOutRow = db()->fetchOne(
    "SELECT COALESCE(SUM(amount), 0) as total FROM payout_requests WHERE user_id = ? AND status IN ('pending','approved','paid')",
    [$userId]
);
$totalRequested = (float)($paidOutRow['total'] ?? 0);
$availableBalance = max(0.0, (float)$earnedRewards['total'] - $totalRequested);

$referredUsers = db()->fetchAll(
    "SELECT r.*, u.full_name, u.email, u.created_at as joined_at
     FROM referrals r
     LEFT JOIN users u ON u.id = r.referred_id
     WHERE r.referrer_id = ?
     ORDER BY r.created_at DESC",
    [$userId]
);

$payoutHistory = db()->fetchAll(
    "SELECT * FROM payout_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$userId]
);

$pageTitle = 'Referral Partner Program — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Referral Partner Program</h1>
            <p class="page-subtitle">Earn $10.00 for every business or creator you refer who subscribes or tops up. Withdraw to bank or convert to ad credits with a bonus.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <button class="btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#convertCreditsModal" style="display:inline-flex;align-items:center;gap:6px;">
                <i class="ph ph-lightning"></i> Convert to Credits
            </button>
            <button class="btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#requestPayoutModal" style="display:inline-flex;align-items:center;gap:6px;">
                <i class="ph ph-bank"></i> Request Cash Payout
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;padding:14px 18px;margin-bottom:20px;">
            <i class="ph ph-check-circle" style="vertical-align:-2px;margin-right:6px"></i> <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="background:rgba(244,63,94,0.15);border:1px solid rgba(244,63,94,0.3);color:#fca5a5;border-radius:12px;padding:14px 18px;margin-bottom:20px;">
            <?php foreach ($errors as $err): ?>
                <p style="margin:0"><i class="ph ph-warning-circle" style="vertical-align:-2px;margin-right:6px"></i> <?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;">Total Referrals</div>
                <div style="font-size:26px;font-weight:800;color:var(--accent);margin-top:4px;"><?= $totalReferrals ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:2px;">Referred accounts registered</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;">Available Commission</div>
                <div style="font-size:26px;font-weight:800;color:var(--green);margin-top:4px;">$<?= number_format($availableBalance, 2) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:2px;">Ready to withdraw or convert</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;">Pending Rewards</div>
                <div style="font-size:26px;font-weight:800;color:var(--orange);margin-top:4px;">$<?= number_format((float)$pendingRewards['total'], 2) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:2px;">Awaiting first checkout</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-dark" style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:600;color:var(--fg3);text-transform:uppercase;">Total Lifetime Earned</div>
                <div style="font-size:26px;font-weight:800;color:var(--blue);margin-top:4px;">$<?= number_format((float)$earnedRewards['total'], 2) ?></div>
                <div style="font-size:12px;color:var(--fg2);margin-top:2px;">All-time affiliate earnings</div>
            </div>
        </div>
    </div>

    <!-- Referral Link & Social Sharing -->
    <div class="card-dark mb-4" style="padding:24px;">
        <h4 style="color:#fff;font-size:16px;font-weight:700;margin-bottom:10px;">
            <i class="ph ph-link" style="color:var(--accent);margin-right:6px;"></i> Your Unique Affiliate Link
        </h4>
        <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
            <input type="text" id="referralLinkInput" class="form-control" style="background:#090e18;border:1px solid var(--accent);color:var(--accent);font-weight:700;font-size:14px;padding:12px;" value="<?= e($referralLink) ?>" readonly>
            <button type="button" class="btn-accent" onclick="copyReferralLink()" style="padding:12px 24px;white-space:nowrap;">
                <i class="ph ph-copy"></i> Copy Link
            </button>
        </div>

        <div style="font-size:13px;font-weight:600;color:var(--fg);margin-bottom:10px;">Share Instantly Across Social Networks</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="https://wa.me/?text=<?= urlencode('Scale your business with Adsbooster multi-network advertising! Use my invite link to get $5.00 in ad credits: ' . $referralLink) ?>" target="_blank" rel="noopener" class="btn-outline-dark btn-sm" style="color:#25D366;border-color:rgba(37,211,102,0.3);">
                <i class="ph ph-whatsapp-logo"></i> WhatsApp
            </a>
            <a href="https://twitter.com/intent/tweet?url=<?= urlencode($referralLink) ?>&text=<?= urlencode('Launch Meta, TikTok, Google and Snapchat ads from one dashboard with Adsbooster! Sign up with my link for bonus ad credits:') ?>" target="_blank" rel="noopener" class="btn-outline-dark btn-sm">
                <i class="ph ph-x-logo"></i> X (Twitter)
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($referralLink) ?>" target="_blank" rel="noopener" class="btn-outline-dark btn-sm" style="color:#1877F2;border-color:rgba(24,119,242,0.3);">
                <i class="ph ph-facebook-logo"></i> Facebook
            </a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($referralLink) ?>" target="_blank" rel="noopener" class="btn-outline-dark btn-sm" style="color:#0A66C2;border-color:rgba(10,102,194,0.3);">
                <i class="ph ph-linkedin-logo"></i> LinkedIn
            </a>
            <a href="https://t.me/share/url?url=<?= urlencode($referralLink) ?>&text=<?= urlencode('Get bonus ad credits on Adsbooster.net') ?>" target="_blank" rel="noopener" class="btn-outline-dark btn-sm" style="color:#229ED9;border-color:rgba(34,158,217,0.3);">
                <i class="ph ph-telegram-logo"></i> Telegram
            </a>
            <a href="mailto:?subject=<?= urlencode('Join Adsbooster with bonus credits') ?>&body=<?= urlencode('Check out Adsbooster.net: ' . $referralLink) ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-envelope"></i> Email
            </a>
        </div>
    </div>

    <!-- Referred Users Table -->
    <div class="card-dark mb-4" style="padding:24px;">
        <h4 style="color:#fff;font-size:17px;font-weight:700;margin-bottom:16px;">
            <i class="ph ph-users" style="color:var(--accent);margin-right:6px;"></i> Referred Partners &amp; Status
        </h4>

        <?php if (empty($referredUsers)): ?>
            <div style="padding:36px;text-align:center;border:1px dashed var(--border);border-radius:12px;">
                <i class="ph ph-user-plus" style="font-size:36px;color:var(--fg3);margin-bottom:8px;display:block;"></i>
                <div style="color:#fff;font-weight:600;margin-bottom:4px;">No referrals yet</div>
                <p style="color:var(--fg2);font-size:13px;margin:0 0 16px;">Share your personal link to earn $10.00 for every account that makes a payment.</p>
                <button type="button" class="btn-accent btn-sm" onclick="copyReferralLink()">Copy Your Referral Link</button>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-dark" style="width:100%;margin:0;">
                    <thead>
                        <tr>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">User</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Status</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Commission Reward</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Registered Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referredUsers as $ref): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:30px;height:30px;border-radius:50%;background:rgba(200,255,0,0.15);display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700;font-size:12px;">
                                            <?= strtoupper(substr($ref['full_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600;color:#fff;font-size:13px;"><?= htmlspecialchars($ref['full_name'] ?? 'User') ?></div>
                                            <div style="font-size:11px;color:var(--fg3);"><?= htmlspecialchars(substr($ref['email'] ?? '', 0, 3) . '***@' . explode('@', $ref['email'] ?? '')[1] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($ref['status'] === 'rewarded'): ?>
                                        <span class="badge-status badge-active"><i class="ph ph-check-circle"></i> Paid / Credited</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-pending"><i class="ph ph-clock"></i> Pending First Checkout</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:700;color:<?= $ref['status'] === 'rewarded' ? 'var(--green)' : 'var(--orange)' ?>;font-size:13px;">
                                    $<?= number_format((float)($ref['reward_credits'] ?? 10.00), 2) ?>
                                </td>
                                <td style="color:var(--fg2);font-size:13px;">
                                    <?= date('M d, Y', strtotime($ref['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payout & Conversion History -->
    <?php if (!empty($payoutHistory)): ?>
        <div class="card-dark" style="padding:24px;">
            <h4 style="color:#fff;font-size:17px;font-weight:700;margin-bottom:16px;">
                <i class="ph ph-receipt" style="color:var(--accent);margin-right:6px;"></i> Payout &amp; Conversion History
            </h4>
            <div class="table-wrap">
                <table class="table-dark" style="width:100%;margin:0;">
                    <thead>
                        <tr>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Date</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Type / Method</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Amount</th>
                            <th style="font-size:11px;font-weight:700;color:var(--fg3);text-transform:uppercase;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payoutHistory as $ph): ?>
                            <tr>
                                <td style="color:var(--fg2);font-size:13px;"><?= date('M d, Y H:i', strtotime($ph['created_at'])) ?></td>
                                <td>
                                    <span class="badge-status badge-info" style="text-transform:capitalize;">
                                        <?= str_replace('_', ' ', $ph['method']) ?>
                                    </span>
                                </td>
                                <td style="color:#fff;font-weight:700;font-size:14px;">$<?= number_format((float)$ph['amount'], 2) ?></td>
                                <td>
                                    <?php if ($ph['status'] === 'paid'): ?>
                                        <span class="badge-status badge-active">Completed</span>
                                    <?php elseif ($ph['status'] === 'approved'): ?>
                                        <span class="badge-status badge-active">Approved</span>
                                    <?php elseif ($ph['status'] === 'rejected'): ?>
                                        <span class="badge-status badge-inactive">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-pending">Processing</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Convert to Credits -->
<div class="modal fade" id="convertCreditsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:18px;color:#f8fafc;overflow:hidden;">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="convert_to_credits">

                <div class="modal-header" style="background:#090e18;border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:rgba(200,255,0,0.15);display:flex;align-items:center;justify-content:center;color:var(--accent);">
                            <i class="ph ph-lightning" style="font-size:22px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" style="font-size:17px;font-weight:700;color:#fff;margin:0;">Convert Earnings to Credits</h5>
                            <p style="font-size:12px;color:var(--fg3);margin:2px 0 0;">Instantly fund campaigns with a 10% credit bonus</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="padding:24px;">
                    <div style="background:#141f33;border:1px solid var(--border);border-radius:10px;padding:12px 16px;margin-bottom:16px;">
                        <span style="font-size:12px;color:var(--fg3);">Available to Convert:</span>
                        <div style="font-size:20px;font-weight:800;color:var(--green);">$<?= number_format($availableBalance, 2) ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#fff;">Amount to Convert ($)</label>
                        <input type="number" name="convert_amount" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;" min="1" max="<?= max(1, (int)$availableBalance) ?>" step="0.01" value="<?= min(50, (int)$availableBalance) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#fff;">Destination Balance</label>
                        <select name="credit_type" class="form-select" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;">
                            <option value="ad_credits">Ad Credits (+10% Bonus added to balance!)</option>
                            <option value="ai_credits">AI Credits ($1.00 = 20 AI Generations)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer" style="background:#090e18;border-top:1px solid rgba(255,255,255,0.08);padding:18px 24px;display:flex;justify-content:space-between;">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent">
                        <i class="ph ph-check"></i> Convert Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Request Cash Payout -->
<div class="modal fade" id="requestPayoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0d1524;border:1px solid rgba(255,255,255,0.12);border-radius:18px;color:#f8fafc;overflow:hidden;">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="request_payout">

                <div class="modal-header" style="background:#090e18;border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:rgba(56,189,248,0.15);display:flex;align-items:center;justify-content:center;color:var(--blue);">
                            <i class="ph ph-bank" style="font-size:22px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" style="font-size:17px;font-weight:700;color:#fff;margin:0;">Request Commission Payout</h5>
                            <p style="font-size:12px;color:var(--fg3);margin:2px 0 0;">Direct bank transfer, PayPal, or Crypto</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="padding:24px;">
                    <div style="background:#141f33;border:1px solid var(--border);border-radius:10px;padding:12px 16px;margin-bottom:16px;">
                        <span style="font-size:12px;color:var(--fg3);">Available for Withdrawal:</span>
                        <div style="font-size:20px;font-weight:800;color:var(--green);">$<?= number_format($availableBalance, 2) ?></div>
                        <span style="font-size:11px;color:var(--fg3);">Minimum threshold: $20.00</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#fff;">Withdrawal Amount ($)</label>
                        <input type="number" name="amount" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;" min="20" max="<?= max(20, (int)$availableBalance) ?>" step="0.01" value="<?= max(20, (int)$availableBalance) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#fff;">Payout Destination</label>
                        <select name="payout_method" id="payoutMethodSelect" class="form-select" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;" onchange="togglePayoutFields(this.value)">
                            <option value="bank">Bank Account Transfer</option>
                            <option value="paypal">PayPal</option>
                            <option value="crypto">USDT / Crypto (TRC20 / ERC20)</option>
                        </select>
                    </div>

                    <!-- Bank Fields -->
                    <div id="bankFields">
                        <div class="mb-2">
                            <label class="form-label" style="font-size:12px;color:var(--fg2);">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;" placeholder="e.g., Chase Bank, First Bank, Barclays">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" style="font-size:12px;color:var(--fg2);">Account Number / IBAN</label>
                            <input type="text" name="account_number" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;" placeholder="e.g., 0123456789">
                        </div>
                        <div class="mb-2">
                            <label class="form-label" style="font-size:12px;color:var(--fg2);">Account Holder Name</label>
                            <input type="text" name="account_name" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;" placeholder="e.g., John Doe">
                        </div>
                    </div>

                    <!-- PayPal Fields -->
                    <div id="paypalFields" style="display:none;">
                        <div class="mb-2">
                            <label class="form-label" style="font-size:12px;color:var(--fg2);">PayPal Email Address</label>
                            <input type="email" name="paypal_email" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;" placeholder="your-paypal@email.com">
                        </div>
                    </div>

                    <!-- Crypto Fields -->
                    <div id="cryptoFields" style="display:none;">
                        <div class="mb-2">
                            <label class="form-label" style="font-size:12px;color:var(--fg2);">USDT Wallet Address (TRC20)</label>
                            <input type="text" name="crypto_address" class="form-control" style="background:#141f33;border:1px solid rgba(255,255,255,0.12);color:#fff;" placeholder="T...">
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background:#090e18;border-top:1px solid rgba(255,255,255,0.08);padding:18px 24px;display:flex;justify-content:space-between;">
                    <button type="button" class="btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-accent">
                        <i class="ph ph-paper-plane-tilt"></i> Submit Payout Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyReferralLink() {
    const input = document.getElementById('referralLinkInput');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Affiliate link copied to clipboard!');
    });
}

function togglePayoutFields(method) {
    document.getElementById('bankFields').style.display = method === 'bank' ? 'block' : 'none';
    document.getElementById('paypalFields').style.display = method === 'paypal' ? 'block' : 'none';
    document.getElementById('cryptoFields').style.display = method === 'crypto' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
