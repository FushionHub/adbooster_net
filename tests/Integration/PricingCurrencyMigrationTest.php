<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/site_setting_and_config.php';
require_once __DIR__ . '/../../config/schema_guard.php';

/**
 * Integration tests: the USD → NGN pricing migration.
 *
 * WHY
 * ---
 * The platform currency is NGN and every price renders through
 * price_ngn_fmt(). Before the migration, pricing_plans.price held a
 * USD-canonical number (9 / 24 / 59 / 149) while price_ngn was NULL — so
 * the pricing page offered "Starter — ₦9.00" and checkout charged ₦9
 * instead of ₦13,950: a 1,550× undercharge on every subscription.
 *
 * The migration is keyed on price_ngn IS NULL (a legacy row) and must be
 * idempotent: running it twice must never double-convert.
 */
#[CoversClass(SchemaGuard::class)]
#[RequiresPhpExtension('pdo_mysql')]
final class PricingCurrencyMigrationTest extends TestCase
{
    private int $planId;

    protected function setUp(): void
    {
        $this->planId = db()->insert('pricing_plans', [
            'name'               => 'Legacy Fixture ' . bin2hex(random_bytes(4)),
            'description'        => 'Legacy USD-canonical pricing row',
            'price'              => 9.00,
            'billing_cycle'      => 'monthly',
            'ad_credits_included' => 5,
            'ai_credits_included' => 900,
            'status'             => 'active',
        ]);
        // The legacy rows had no price_ngn at all.
        db()->query("UPDATE pricing_plans SET price_ngn = NULL WHERE id = ?", [$this->planId]);
    }

    protected function tearDown(): void
    {
        db()->delete('pricing_plans', 'id = ?', [$this->planId]);
    }

    private function row(): array
    {
        return db()->fetchOne('SELECT * FROM pricing_plans WHERE id = ?', [$this->planId]);
    }

    public function testLegacyUsdPriceIsTranslatedToNaira(): void
    {
        SchemaGuard::migratePricingPlansNow();

        $row = $this->row();
        $rate = exchange_rate_ngn_per_usd();

        $this->assertSame(round(9.00 * $rate, 2), (float)$row['price_ngn']);
        $this->assertSame((float)$row['price_ngn'], (float)$row['price'], 'price mirrors price_ngn');
        $this->assertGreaterThan(1000, (float)$row['price'], 'A $9 plan must never stay below ₦1,000');
    }

    public function testLegacyAdCreditBundleIsTranslatedToNaira(): void
    {
        SchemaGuard::migratePricingPlansNow();

        $row = $this->row();
        $rate = exchange_rate_ngn_per_usd();

        $this->assertSame((int)round(5 * $rate), (int)$row['ad_credits_included']);
    }

    public function testMigrationIsIdempotent(): void
    {
        SchemaGuard::migratePricingPlansNow();
        $first = $this->row();

        SchemaGuard::migratePricingPlansNow();
        $second = $this->row();

        $this->assertSame((float)$first['price_ngn'], (float)$second['price_ngn']);
        $this->assertSame((int)$first['ad_credits_included'], (int)$second['ad_credits_included']);
    }

    public function testAlreadyMigratedRowsAreUntouched(): void
    {
        db()->query(
            "UPDATE pricing_plans SET price = 5000.00, price_ngn = 5000.00, ad_credits_included = 2500 WHERE id = ?",
            [$this->planId]
        );

        SchemaGuard::migratePricingPlansNow();

        $row = $this->row();
        $this->assertSame(5000.0, (float)$row['price_ngn']);
        $this->assertSame(2500, (int)$row['ad_credits_included']);
    }

    public function testLeadPackProductExistsExactlyOnce(): void
    {
        SchemaGuard::migratePricingPlansNow();

        $packs = db()->fetchAll(
            "SELECT * FROM pricing_plans WHERE product_type = 'lead_pack'"
        );

        $this->assertCount(1, $packs, 'The lead product must exist exactly once');
        $this->assertSame('1,000 Leads', $packs[0]['name']);
        $this->assertSame(3500.0, (float)$packs[0]['price']);
        $this->assertSame(3500.0, (float)$packs[0]['price_ngn']);
        $this->assertSame('active', $packs[0]['status']);
    }
}
