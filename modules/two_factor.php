<?php declare(strict_types=1);

/**
 * TOTP Two-Factor Authentication
 * Adsbooster.net
 *
 * Implements RFC 6238 Time-based One-Time Password (TOTP) algorithm
 * using vanilla PHP with no external dependencies.
 */

require_once __DIR__ . '/../config/db.php';

class TwoFactorAuth
{
    /** @var int TOTP time step in seconds (30 seconds) */
    private const TIME_STEP = 30;

    /** @var int TOTP code length */
    private const CODE_LENGTH = 6;

    /** @var int Number of backup codes to generate */
    private const BACKUP_CODE_COUNT = 10;

    /** @var string Base32 character set */
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** @var int Verification window (±1 step = 30 seconds each way) */
    private const VERIFICATION_WINDOW = 1;

    /**
     * Generate a random 16-character Base32 secret for TOTP.
     *
     * @return string 16-character Base32 encoded secret
     */
    public static function generateSecret(): string
    {
        $secret = '';
        $chars = self::BASE32_CHARS;
        $maxIndex = strlen($chars) - 1;

        for ($i = 0; $i < 16; $i++) {
            $randomByte = random_int(0, 255);
            $secret .= $chars[$randomByte % 32];
        }

        return $secret;
    }

    /**
     * Get the standard otpauth URI for Google Authenticator / Authy.
     *
     * @param string $secret The Base32 encoded TOTP secret
     * @param string $email  User's email address
     * @return string otpauth:// URI
     */
    public static function getOtpAuthUri(string $secret, string $email): string
    {
        $issuer = 'Adsbooster.net';
        $label = rawurlencode("{$issuer}:{$email}");
        $secretParam = $secret;

        return "otpauth://totp/{$label}?secret={$secretParam}&issuer=" . rawurlencode($issuer) . '&algorithm=SHA1&digits=' . self::CODE_LENGTH . '&period=' . self::TIME_STEP;
    }

    /**
     * Generate a Google Authenticator compatible QR code URL.
     *
     * @param string $secret The Base32 encoded TOTP secret
     * @param string $email  User's email address (used as account name)
     * @return string Live QR code image URL via reliable api.qrserver.com
     */
    public static function getQRCodeUrl(string $secret, string $email): string
    {
        $otpauthUri = self::getOtpAuthUri($secret, $email);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=256x256&margin=10&data=' . rawurlencode($otpauthUri);
    }

    /**
     * Verify a TOTP code against the stored secret.
     *
     * Implements RFC 6238 with a time window to account for clock drift.
     *
     * @param string $secret The Base32 encoded TOTP secret
     * @param string $code   The 6-digit code to verify
     * @return bool True if the code is valid
     */
    public static function verifyCode(string $secret, string $code): bool
    {
        if (!preg_match('/^\d{' . self::CODE_LENGTH . '}$/', $code)) {
            return false;
        }

        $currentTime = time();

        // Check current time step and surrounding window for clock drift tolerance
        for ($offset = -self::VERIFICATION_WINDOW; $offset <= self::VERIFICATION_WINDOW; $offset++) {
            $timeStep = (int) floor($currentTime / self::TIME_STEP) + $offset;
            $expectedCode = self::generateCodeFromTimeStep($secret, $timeStep);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a set of backup codes for account recovery.
     *
     * @return array<int, array{code: string, used: bool}> Array of backup codes
     */
    public static function generateBackupCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(5)));
            $part1 = substr($hex, 0, 5);
            $part2 = substr($hex, 5, 5);
            $codes[] = [
                'code' => "{$part1}-{$part2}",
                'used' => false,
            ];
        }

        return $codes;
    }

    /**
     * Save 2FA settings for a user.
     *
     * @param int    $userId       User ID
     * @param string $secret       Base32 TOTP secret
     * @param array  $backupCodes  Array of backup code strings
     * @param bool   $enabled      Whether 2FA is enabled
     * @return bool True on success
     */
    public static function saveSettings(
        int $userId,
        string $secret,
        array $backupCodes,
        bool $enabled = true
    ): bool {
        try {
            db()->update('users', [
                'two_factor_secret'    => $secret,
                'two_factor_backup'    => json_encode($backupCodes),
                'two_factor_enabled'   => $enabled ? 1 : 0,
                'two_factor_setup_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [$userId]);

            return true;

        } catch (\Throwable $e) {
            error_log("Failed to save 2FA settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a user has 2FA enabled.
     *
     * @param int $userId User ID
     * @return bool
     */
    public static function isEnabled(int $userId): bool
    {
        $user = db()->fetchOne(
            "SELECT two_factor_enabled FROM users WHERE id = ?",
            [$userId]
        );

        return $user !== null && (bool) ($user['two_factor_enabled'] ?? false);
    }

    /**
     * Verify a backup code for account recovery.
     *
     * @param int    $userId     User ID
     * @param string $backupCode The backup code to verify
     * @return bool True if the backup code is valid and marks it as used
     */
    public static function verifyBackupCode(int $userId, string $backupCode): bool
    {
        $user = db()->fetchOne(
            "SELECT two_factor_backup FROM users WHERE id = ?",
            [$userId]
        );

        if ($user === null || empty($user['two_factor_backup'])) {
            return false;
        }

        $codes = json_decode($user['two_factor_backup'], true);
        if (!is_array($codes)) {
            return false;
        }

        $found = false;
        $updatedCodes = [];

        foreach ($codes as $code) {
            // Records may be: {hash,used} (current), {code,used} (older),
            // or a bare string (legacy). Normalise before comparing.
            $isUsed   = is_array($code) ? !empty($code['used']) : false;
            $stored   = is_array($code)
                ? (string)($code['hash'] ?? ($code['code'] ?? ''))
                : (string)$code;
            $legacyPlain = is_array($code)
                ? (string)($code['code'] ?? '')
                : (string)$code;

            if ($isUsed || $stored === '') {
                $updatedCodes[] = is_array($code) ? $code : ['code' => $code, 'used' => false];
                continue;
            }

            $match = password_verify($backupCode, $stored)
                || ($legacyPlain !== '' && hash_equals(str_replace('-', '', $legacyPlain), str_replace('-', '', $backupCode)));

            if ($match) {
                $found = true;
                // Upgrade the record to a hash on first use.
                $updatedCodes[] = ['hash' => password_hash($backupCode, PASSWORD_DEFAULT), 'used' => true];
            } else {
                $updatedCodes[] = ['hash' => $stored, 'used' => false];
            }
        }

        if ($found) {
            try {
                db()->update('users', [
                    'two_factor_backup' => json_encode($updatedCodes),
                ], 'id = ?', [$userId]);
            } catch (\Throwable $e) {
                error_log("Failed to update backup code: " . $e->getMessage());
                return false;
            }
        }

        return $found;
    }

    /**
     * Generate a TOTP code from a time step value.
     *
     * @param string $secret   Base32 encoded secret
     * @param int    $timeStep Time step counter
     * @return string The generated numeric code
     */
    private static function generateCodeFromTimeStep(string $secret, int $timeStep): string
    {
        // Decode Base32 secret to binary
        $binarySecret = self::base32Decode($secret);

        // Pack time step as 8-byte big-endian
        $timeBytes = pack('N*', 0, $timeStep);

        // Generate HMAC-SHA1
        $hmac = hash_hmac('sha1', $timeBytes, $binarySecret, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $binary = ((ord($hmac[$offset]) & 0x7F) << 24) |
                  ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
                  ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
                  (ord($hmac[$offset + 3]) & 0xFF);

        // Generate code with proper length
        $otp = $binary % (int) pow(10, self::CODE_LENGTH);

        return str_pad((string) $otp, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a Base32 encoded string to binary.
     *
     * @param string $input Base32 encoded string
     * @return string Binary decoded output
     */
    private static function base32Decode(string $input): string
    {
        $chars = self::BASE32_CHARS;
        $input = strtoupper(rtrim($input, '='));

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos($chars, $input[$i]);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
