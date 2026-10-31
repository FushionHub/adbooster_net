<?php declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Referral & Affiliate Engine Module for Adsbooster.net
 * 
 * Manages referral code generation, tracking, and reward distribution.
 */
class ReferralEngine
{
    /**
     * Generate a unique 8-character referral code for a user
     *
     * @param int $userId The user ID
     * @return string The generated referral code
     * @throws Exception On database error or if code already exists
     */
    public static function generateCode(int $userId): string
    {
        try {
            $db = db();

            // Check if user already has a code
            $stmt = $db->prepare(
                "SELECT referral_code FROM users WHERE id = ?"
            );
            $stmt->execute([$userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && !empty($existing['referral_code'])) {
                return $existing['referral_code'];
            }

            // Generate unique 8-char alphanumeric code
            $maxAttempts = 10;
            $attempts = 0;

            do {
                $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));

                // Check uniqueness
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM users WHERE referral_code = ?"
                );
                $stmt->execute([$code]);
                $exists = $stmt->fetchColumn() > 0;

                $attempts++;
            } while ($exists && $attempts < $maxAttempts);

            if ($attempts >= $maxAttempts) {
                throw new Exception("Unable to generate unique referral code after {$maxAttempts} attempts");
            }

            // Update user with new code
            $stmt = $db->prepare(
                "UPDATE users SET referral_code = ? WHERE id = ?"
            );
            $stmt->execute([$code, $userId]);

            // Also create entry in referrals table
            $stmt = $db->prepare(
                "INSERT INTO referrals (referrer_id, code, created_at) 
                 VALUES (?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE code = VALUES(code)"
            );
            $stmt->execute([$userId, $code]);

            return $code;
        } catch (Exception $e) {
            error_log("ReferralEngine::generateCode Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a referral and credit both parties
     *
     * @param string $code The referral code
     * @param int $newUserId The new user being referred
     * @return bool True if referral processed successfully
     * @throws Exception On database error or invalid code
     */
    public static function processReferral(string $code, int $newUserId): bool
    {
        try {
            $db = db();

            // Find referrer by code
            $stmt = $db->prepare(
                "SELECT u.id, u.username 
                 FROM users u 
                 WHERE u.referral_code = ?"
            );
            $stmt->execute([$code]);
            $referrer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$referrer) {
                return false;
            }

            // Prevent self-referral
            if ($referrer['id'] === $newUserId) {
                return false;
            }

            // Check if user was already referred
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM referrals WHERE referred_user_id = ?"
            );
            $stmt->execute([$newUserId]);
            if ($stmt->fetchColumn() > 0) {
                return false;
            }

            $db->beginTransaction();

            // Record the referral
            $stmt = $db->prepare(
                "UPDATE referrals 
                 SET referred_user_id = ?, 
                     status = 'completed',
                     credited_at = NOW()
                 WHERE referrer_id = ? AND (referred_user_id IS NULL OR referred_user_id = 0)"
            );
            $stmt->execute([$newUserId, $referrer['id']]);

            // If no existing referral record, create one
            if ($stmt->rowCount() === 0) {
                $stmt = $db->prepare(
                    "INSERT INTO referrals (referrer_id, code, referred_user_id, status, created_at, credited_at) 
                     VALUES (?, ?, ?, 'completed', NOW(), NOW())"
                );
                $stmt->execute([$referrer['id'], $code, $newUserId]);
            }

            // Credit referrer (give bonus credits or discount)
            $referralBonus = 10; // $10 credit
            $stmt = $db->prepare(
                "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?"
            );
            $stmt->execute([$referralBonus, $referrer['id']]);

            // Credit new user with welcome bonus
            $welcomeBonus = 5; // $5 welcome credit
            $stmt = $db->prepare(
                "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?"
            );
            $stmt->execute([$welcomeBonus, $newUserId]);

            // Log the transaction
            $stmt = $db->prepare(
                "INSERT INTO referral_rewards (referrer_id, referred_user_id, referrer_reward, referred_reward, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$referrer['id'], $newUserId, $referralBonus, $welcomeBonus]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("ReferralEngine::processReferral Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get referral statistics for a user
     *
     * @param int $userId The user ID
     * @return array Statistics including count, pending, and earned amounts
     * @throws Exception On database error
     */
    public static function getReferralStats(int $userId): array
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT 
                    COUNT(*) as total_referrals,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN referrer_reward ELSE 0 END), 0) as total_earned,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN referrer_reward ELSE 0 END), 0) as pending_earned
                 FROM referrals 
                 WHERE referrer_id = ?"
            );
            $stmt->execute([$userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_referrals' => (int) $stats['total_referrals'],
                'completed' => (int) $stats['completed'],
                'pending' => (int) $stats['pending'],
                'total_earned' => (float) $stats['total_earned'],
                'pending_earned' => (float) $stats['pending_earned']
            ];
        } catch (Exception $e) {
            error_log("ReferralEngine::getReferralStats Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get list of users referred by a specific user
     *
     * @param int $userId The referrer's user ID
     * @return array Array of referred user information
     * @throws Exception On database error
     */
    public static function getReferredUsers(int $userId): array
    {
        try {
            $db = db();

            $stmt = $db->prepare(
                "SELECT 
                    r.id as referral_id,
                    u.id as user_id,
                    u.username,
                    u.email,
                    r.status,
                    r.referrer_reward,
                    r.created_at as referred_at,
                    r.credited_at
                 FROM referrals r
                 JOIN users u ON r.referred_user_id = u.id
                 WHERE r.referrer_id = ?
                 ORDER BY r.created_at DESC"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ReferralEngine::getReferredUsers Error: " . $e->getMessage());
            throw $e;
        }
    }
}
