
DELETE FROM adsbooster.users WHERE email = 'docs-shot-admin@adsbooster.net';
UPDATE adsbooster.users SET password = '$2y$10$S4.kmWp6AnyMgi6d.unA4ewBqlNvVXAKPvM01Jx7/H92YYc1XWSke' WHERE email = 'smoke@adsbooster.net';
SELECT email, LEFT(password, 7) AS hash_start, status, role FROM adsbooster.users WHERE email IN ('smoke@adsbooster.net','docs-shot-admin@adsbooster.net');
SELECT COUNT(*) AS remaining_shot_admins FROM adsbooster.users WHERE email LIKE 'docs-shot-%';
SELECT COUNT(*) AS referrals, SUM(status='rewarded') AS rewarded FROM adsbooster.referrals;
