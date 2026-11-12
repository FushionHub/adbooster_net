# Referral Programme

Adsbooster rewards users who bring in new accounts. A referral is **recorded** when the new
account registers and **paid** when that account makes its first successful payment.

The payout is **automatic** — the referrer does not have to chase an admin — but every part
of it stays under **admin control**.

---

## 1. How a referral works

```
User A shares their referral link
        │
        ▼
User B registers with the link
        │
        ▼
ReferralService::record()  →  status: pending   (nothing paid yet)
        │
        ▼
User B makes their first successful payment
        │
        ▼
PaymentProcessor::completeTransaction()
        │
        ▼
ReferralService::rewardForPayment()  →  status: rewarded
        │
        ├── User A (referrer) receives the referral reward
        └── User B (referred)  receives the welcome bonus
```

The referral link is stored on the user row as `referred_by`, and the referral code lives
in `users.referral_code`.

---

## 2. Reward settings

All three settings live in `site_settings` and are editable from **Admin → Referrals →
Programme settings** (no code change needed).

| Setting key | Default | Meaning |
|---|---|---|
| `referral_reward_credits` | `10` | Ad credits paid to the **referrer** |
| `referral_referred_bonus` | `5` | Ad credits paid to the **new account** as a welcome bonus |
| `referral_auto_reward` | `1` | `1` = automatic payout on payment · `0` = manual payout only |

The reward amount is **snapshotted onto the referral row** when the referral is created, so
changing the default later never rewrites rewards that were already agreed. An admin can
still adjust an individual referral's amount from the admin table.

---

## 3. Automatic rewards

`ReferralService::rewardForPayment($userId)` is called from
`PaymentProcessor::completeTransaction()` the moment a transaction is verified. It fires
for **every** verified payment path:

- the hosted checkout callback (`payment-verify.php`)
- the admin **Verify** button (Admin → Transactions)
- the inbound **webhook** (`/payment/webhook/<gateway>`)

Because it hangs off `completeTransaction()`, a payment confirmed by the gateway webhook
rewards the referrer even when the payer never returns to the browser. See
[payments-webhooks.md](payments-webhooks.md).

### Idempotency

A referral can be paid **exactly once**, no matter how many times verification runs, how
many webhooks the provider redelivers, or how often an admin clicks *Pay*.

The guard is a conditional `UPDATE`:

```sql
UPDATE referrals
   SET status = 'rewarded', credited_at = NOW(), …
 WHERE id = ? AND status = 'pending'
```

Only the caller whose statement actually changed a row (`rowCount() === 1`) proceeds to
credit the wallets. Everyone else gets *"Referral was already rewarded."* and no credits
move.

### What the payout does

1. Flips the referral `pending → rewarded` (the idempotency guard).
2. Adds `referral_reward_credits` to the referrer's `users.ad_credits`.
3. Adds `referral_referred_bonus` to the referred user's `users.ad_credits`.
4. Writes a row to the `referral_rewards` ledger (unique on `referred_user_id`, so the
   ledger can never double up).
5. Sends an in-app notification + branded email to both parties.

### When it does *not* fire

| Situation | Result |
|---|---|
| No referral for that account | `No pending referral for this account.` |
| Referral already paid | `Referral was already rewarded.` |
| Referral was rejected by an admin | `Referral is rejected and will not be paid automatically.` |
| `referral_auto_reward` is off | `Automatic referral rewards are disabled.` — the referral stays `pending` and can be paid manually |
| Referrer id invalid or self-referral | `Referral row has no valid referrer.` |

In every one of those cases the referral **stays payable by an admin**.

---

## 4. Admin management

**Admin → Referrals** (`admin/referrals.php`) gives full control over the programme.

### Programme settings

Edit `referral_reward_credits`, `referral_referred_bonus` and `referral_auto_reward` and
save — the next referral uses the new values immediately.

### Per-referral actions

| Action | What it does | Service method |
|---|---|---|
| **Pay** | Pays the referrer now, using the same code path as the automatic reward | `ReferralService::payReward()` |
| **Reset to pending** | Returns a rewarded referral to `pending` so it can be paid again. The already-paid credits are **not** clawed back automatically — reverse them from Admin → Users if needed | `ReferralService::resetToPending()` |
| **Reject** | Marks the referral `rejected`. A rejected referral can never be paid — neither automatically nor manually | `ReferralService::reject()` |

### Bulk actions

- **Pay all pending** — pays every pending referral in one go.
- **Status filter** — narrow the table to `pending`, `rewarded` or `rejected`.

### Stats

The dashboard shows totals for pending, rewarded and rejected referrals, the amount still
payable, the amount already earned, and the top referrers. The same numbers are available
programmatically:

```php
ReferralService::stats($referrerId);   // per-user stats
```

---

## 5. API reference

```php
require_once 'modules/referral_service.php';

// Record the link at registration (idempotent — returns the existing row id
// when the account was already referred).
$referralId = ReferralService::record($referrerId, $referredId, $code);

// Automatic payout — called by PaymentProcessor::completeTransaction().
$result = ReferralService::rewardForPayment($userId);
// $result = ['credited' => bool, 'rewarded' => bool, 'referral_id' => int,
//            'referrer_id' => int, 'amount' => float,
//            'referred_bonus' => float, 'message' => string]

// Manual payout — the admin path.
$result = ReferralService::payReward($referralId, 'Manual admin payout');

// Admin corrections.
ReferralService::resetToPending($referralId);
ReferralService::reject($referralId);

// Programme settings + stats.
$settings = ReferralService::settings();
$stats    = ReferralService::stats($referrerId);
```

`modules/referral_engine.php` is kept as a thin facade over `ReferralService` so older
call sites keep working.

---

## 6. Database schema

### `referrals`

| Column | Notes |
|---|---|
| `id` | Primary key |
| `referrer_id` | The user who referred |
| `referred_id` | **Canonical** — the account that was referred |
| `referred_user_id` | Legacy mirror of `referred_id`, kept in sync by the migration |
| `code` | The referral code used at sign-up (optional) |
| `reward_credits` | The reward amount agreed for this referral |
| `status` | `pending` · `rewarded` · `rejected` |
| `credited_at` | Set when the payout happened |
| `created_at` / `updated_at` | Timestamps |

### `referral_rewards` (the ledger)

| Column | Notes |
|---|---|
| `referrer_id` | Who was paid |
| `referred_user_id` | Whose referral it was — **unique key**, so the ledger can hold only one row per referral |
| `referrer_reward` | Amount paid to the referrer |
| `referred_reward` | Welcome bonus paid to the new account |
| `created_at` | Timestamp |

### Migrations

`update-any-error.sql` (project root) repairs the referral schema:

- backfills `referrals.referred_id` from the legacy `referred_user_id` (and back),
- removes duplicate referral rows,
- adds the missing indexes,
- seeds the three referral settings into `site_settings`,
- creates `payout_requests`.

```bash
mysql -u root adsbooster < update-any-error.sql
# or
php database/apply_update_any_error.php
```

The file is idempotent — running it twice is harmless.

---

## 7. Testing

```bash
php -d extension=php_pdo_mysql.dll vendor/bin/phpunit --filter ReferralServiceTest
```

`tests/Integration/ReferralServiceTest.php` covers the whole lifecycle: recording, the
default and configured reward amounts, the automatic payout, the manual payout, the
idempotency guard (paying twice), reset, reject, the full payment → reward chain, and the
stats.

---

## 8. Related files

| File | Purpose |
|---|---|
| `modules/referral_service.php` | The single source of truth for referral logic |
| `modules/referral_engine.php` | Thin facade kept for backwards compatibility |
| `admin/referrals.php` | Admin management UI (settings, pay, reset, reject, bulk) |
| `register.php` | Calls `ReferralService::record()` at sign-up |
| `modules/payments.php` | Calls `ReferralService::rewardForPayment()` on a completed payment |
| `referrals.php` | The user-facing referral dashboard |
| `update-any-error.sql` | Idempotent schema repair file |
| `tests/Integration/ReferralServiceTest.php` | Integration tests for the programme |
