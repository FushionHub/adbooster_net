-- Temporary screenshot seeding (local dev only).
-- Creates a known-password user + admin, links a referral between two real
-- accounts so the referral screens show content, and prints the original
-- password hashes so they can be restored afterwards.

SET @pw_user  = '$2y$12$38WNgI4xuKjBtuaFcVL1le4Nl8VlBxkoWz9gCvnOOF7DPrMczye2K';
SET @pw_admin = '$2y$12$r4Jc/NhQd468OorVPAOOXOQRhvYSHCCWRdribK38ADL0uLlLCQZRe';

-- 1. Save the original hashes of the accounts we touch.
SELECT 'ORIGINAL_HASH' AS tag, email, password FROM adsbooster.users
 WHERE email IN ('smoke@adsbooster.net');

-- 2. Reset the throwaway test account's password to a known value.
UPDATE adsbooster.users
   SET password = @pw_user
 WHERE email = 'smoke@adsbooster.net';

-- 3. Create a temporary admin account for the screenshots.
DELETE FROM adsbooster.users WHERE email = 'docs-shot-admin@adsbooster.net';
INSERT INTO adsbooster.users
    (full_name, email, password, role, user_role, is_admin, status, ad_credits,
     ai_credits, referral_code, created_at, account_type)
VALUES
    ('Docs Shot Admin', 'docs-shot-admin@adsbooster.net', @pw_admin,
     'admin', 'admin', 1, 'active', 2500, 500,
     'DOCSSHOT01', NOW(), 'individual');

-- 4. Give the accounts referral codes + a referral link so the referral
--    screens have something real to show.
UPDATE adsbooster.users
   SET referral_code = 'SMOKE2026'
 WHERE email = 'smoke@adsbooster.net';

UPDATE adsbooster.users
   SET referral_code = 'JOTA2026'
 WHERE email = 'jotahecomng@gmail.com';

UPDATE adsbooster.users
   SET referral_code = 'E2ECHECK26'
 WHERE email = 'e2e-check@adsbooster.net';

-- 5. One rewarded referral (already paid) + one pending referral.
DELETE FROM adsbooster.referral_rewards
 WHERE referred_user_id IN (SELECT id FROM adsbooster.users WHERE email IN ('jotahecomng@gmail.com','e2e-check@adsbooster.net'));
DELETE FROM adsbooster.referrals
 WHERE referred_id IN (SELECT id FROM adsbooster.users WHERE email IN ('jotahecomng@gmail.com','e2e-check@adsbooster.net'));

INSERT INTO adsbooster.referrals
    (referrer_id, referred_id, referred_user_id, code, reward_credits, status,
     credited_at, created_at, updated_at)
SELECT a.id, b.id, b.id, 'SMOKE2026', 10.00, 'rewarded', NOW(), DATE_SUB(NOW(), INTERVAL 9 DAY), NOW()
  FROM adsbooster.users a, adsbooster.users b
 WHERE a.email = 'smoke@adsbooster.net' AND b.email = 'jotahecomng@gmail.com';

INSERT INTO adsbooster.referrals
    (referrer_id, referred_id, referred_user_id, code, reward_credits, status,
     credited_at, created_at, updated_at)
SELECT a.id, b.id, b.id, 'SMOKE2026', 10.00, 'pending', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), NOW()
  FROM adsbooster.users a, adsbooster.users b
 WHERE a.email = 'smoke@adsbooster.net' AND b.email = 'e2e-check@adsbooster.net';

INSERT INTO adsbooster.referral_rewards
    (referrer_id, referred_user_id, referrer_reward, referred_reward, created_at)
SELECT id, referred_id, 10.00, 5.00, NOW()
  FROM adsbooster.referrals
 WHERE status = 'rewarded';

UPDATE adsbooster.users u
  JOIN adsbooster.referrals r ON r.referred_id = u.id
   SET u.referred_by = r.referrer_id
 WHERE r.status = 'rewarded';

SELECT 'SEEDED' AS tag, status, COUNT(*) AS n FROM adsbooster.referrals GROUP BY status;
