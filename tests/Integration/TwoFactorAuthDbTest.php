<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/two_factor.php';

/**
 * Integration tests: TwoFactorAuth DB-backed methods.
 *
 * Security-critical: target coverage 100%.
 *
 * Recreate the test DB with: php -d extension=php_pdo_mysql.dll tests/make-test-db.php
 */
#[CoversClass(TwoFactorAuth::class)]
#[RequiresPhpExtension('pdo_mysql')]
final class TwoFactorAuthDbTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        $this->userId = db()->insert('users', [
            'email'  => 'totp_' . bin2hex(random_bytes(6)) . '@test.local',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        db()->delete('users', 'id = ?', [$this->userId]);
    }

    // ---------------------------------------------------------------
    // saveSettings() / isEnabled()
    // ---------------------------------------------------------------

    public function testSaveSettingsPersistsSecretAndEnablesTwoFactor(): void
    {
        $secret = TwoFactorAuth::generateSecret();
        $codes = TwoFactorAuth::generateBackupCodes();

        $this->assertTrue(TwoFactorAuth::saveSettings($this->userId, $secret, $codes, true));
        $this->assertTrue(TwoFactorAuth::isEnabled($this->userId));

        $user = db()->fetchOne("SELECT two_factor_secret FROM users WHERE id = ?", [$this->userId]);
        $this->assertSame($secret, $user['two_factor_secret']);
    }

    public function testSaveSettingsCanDisableTwoFactor(): void
    {
        $secret = TwoFactorAuth::generateSecret();
        TwoFactorAuth::saveSettings($this->userId, $secret, TwoFactorAuth::generateBackupCodes(), true);
        TwoFactorAuth::saveSettings($this->userId, $secret, TwoFactorAuth::generateBackupCodes(), false);

        $this->assertFalse(TwoFactorAuth::isEnabled($this->userId));
    }

    public function testIsEnabledFailsClosedForUnknownUser(): void
    {
        $this->assertFalse(TwoFactorAuth::isEnabled(999999999));
    }

    // ---------------------------------------------------------------
    // verifyBackupCode() — recovery path (both plaintext and hashed records)
    // ---------------------------------------------------------------

    public function testVerifyBackupCodeAcceptsHashedRecordAndMarksUsed(): void
    {
        $plain = 'AB12C-DE34F';
        db()->update('users', ['two_factor_backup' => json_encode([
            ['hash' => password_hash($plain, PASSWORD_DEFAULT), 'used' => false],
        ])], 'id = ?', [$this->userId]);

        $this->assertTrue(TwoFactorAuth::verifyBackupCode($this->userId, $plain));

        // Record must have been upgraded/marked used — second use fails
        $this->assertFalse(TwoFactorAuth::verifyBackupCode($this->userId, $plain));
    }

    public function testVerifyBackupCodeAcceptsLegacyPlaintextRecord(): void
    {
        db()->update('users', ['two_factor_backup' => json_encode([
            ['code' => 'ZZ99X-YY88W', 'used' => false],
        ])], 'id = ?', [$this->userId]);

        $this->assertTrue(TwoFactorAuth::verifyBackupCode($this->userId, 'ZZ99X-YY88W'));
    }

    public function testVerifyBackupCodeToleratesDashes(): void
    {
        db()->update('users', ['two_factor_backup' => json_encode(['ABCDEFGH12'])], 'id = ?', [$this->userId]);

        // Legacy bare string matched with dashes stripped
        $this->assertTrue(TwoFactorAuth::verifyBackupCode($this->userId, 'ABCD-EFGH-12'));
    }

    public function testVerifyBackupCodeRejectsWrongCode(): void
    {
        db()->update('users', ['two_factor_backup' => json_encode([
            ['code' => 'AAAAA-BBBBB', 'used' => false],
        ])], 'id = ?', [$this->userId]);

        $this->assertFalse(TwoFactorAuth::verifyBackupCode($this->userId, 'CCCCC-DDDDD'));
    }

    public function testVerifyBackupCodeRejectsAlreadyUsedCode(): void
    {
        db()->update('users', ['two_factor_backup' => json_encode([
            ['code' => 'AAAAA-BBBBB', 'used' => true],
        ])], 'id = ?', [$this->userId]);

        $this->assertFalse(TwoFactorAuth::verifyBackupCode($this->userId, 'AAAAA-BBBBB'));
    }

    public function testVerifyBackupCodeRejectsUnknownUser(): void
    {
        $this->assertFalse(TwoFactorAuth::verifyBackupCode(999999999, 'AAAAA-BBBBB'));
    }

    public function testVerifyBackupCodeRejectsMalformedJson(): void
    {
        db()->update('users', ['two_factor_backup' => 'not-json{{'], 'id = ?', [$this->userId]);

        $this->assertFalse(TwoFactorAuth::verifyBackupCode($this->userId, 'AAAAA-BBBBB'));
    }

    public function testVerifyBackupCodeRejectsEmptyBackupField(): void
    {
        db()->update('users', ['two_factor_backup' => ''], 'id = ?', [$this->userId]);

        $this->assertFalse(TwoFactorAuth::verifyBackupCode($this->userId, 'AAAAA-BBBBB'));
    }
}
