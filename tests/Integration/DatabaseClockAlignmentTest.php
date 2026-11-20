<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/db.php';

/**
 * Integration tests: PHP and MySQL must agree on the clock.
 *
 * Every timestamp this application writes is produced by PHP
 * (date('Y-m-d H:i:s')), while several queries compare those columns against
 * the DATABASE clock (NOW(), DATE_ADD(NOW(), …)). If MySQL's session
 * timezone differs from PHP's, a job scheduled for "now" looks hours in the
 * future and the async worker never claims it — campaigns launch late,
 * scheduled posts publish late, and invitations never expire on time.
 *
 * Database::syncSessionTimezone() pins the MySQL session timezone to PHP's
 * current UTC offset, which makes NOW() and date() return the same value on
 * any host.
 */
#[CoversClass(Database::class)]
#[RequiresPhpExtension('pdo_mysql')]
final class DatabaseClockAlignmentTest extends TestCase
{
    public function testSessionTimezoneMatchesThePhpOffset(): void
    {
        $sessionTz = (string)db()->fetchOne('SELECT @@session.time_zone tz')['tz'];
        $offset    = date('P');

        $this->assertSame(
            $offset,
            $sessionTz,
            'MySQL session time_zone must equal PHP\'s UTC offset (' . $offset . ')'
        );
    }

    public function testMysqlNowMatchesPhpNow(): void
    {
        $phpNow  = date('Y-m-d H:i:s');
        $mysqlNow = (string)db()->fetchOne('SELECT NOW() n')['n'];

        // Both clocks read the same wall-clock second; allow one second of
        // slack for the moment the two values are sampled.
        $delta = abs(strtotime($mysqlNow) - strtotime($phpNow));
        $this->assertLessThanOrEqual(
            1,
            $delta,
            'MySQL NOW() (' . $mysqlNow . ') and PHP date() (' . $phpNow . ') must agree'
        );
    }

    public function testAJobScheduledForNowIsImmediatelyDue(): void
    {
        // This is the exact predicate the async worker uses.
        $scheduledAt = date('Y-m-d H:i:s');
        $due = (int)db()->fetchOne(
            'SELECT COUNT(*) AS cnt FROM (SELECT ? AS scheduled_at) x
             WHERE scheduled_at <= NOW()',
            [$scheduledAt]
        )['cnt'];

        $this->assertSame(1, $due, 'A timestamp written by PHP for "now" must be due for the worker');
    }

    public function testSyncIsIdempotent(): void
    {
        $before = (string)db()->fetchOne('SELECT @@session.time_zone tz')['tz'];
        Database::syncSessionTimezoneNow();
        $after = (string)db()->fetchOne('SELECT @@session.time_zone tz')['tz'];

        $this->assertSame($before, $after);
    }
}
