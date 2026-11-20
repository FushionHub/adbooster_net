<?php declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/site_setting_and_config.php';

/**
 * Unit tests: the naira-denominated ad wallet.
 *
 * users.ad_credits is NAIRA-denominated: one wallet credit is one naira of
 * ad budget. A few legacy subsystems still keep their amounts in the old
 * USD-canonical credit units (referral rewards, bounty payouts, listing
 * fees, signup bonuses, legacy USD transactions), and those are converted
 * exactly once — at the wallet boundary — by credit_units_to_ngn().
 *
 * price_fmt() is USD-canonical (it multiplies by the naira rate), so it must
 * never format an amount that is already in naira. price_ngn_fmt() is the
 * formatter for naira amounts.
 */
#[CoversNothing]
final class WalletCurrencyTest extends TestCase
{
    public function testCreditUnitsConvertToTheirNairaWalletValue(): void
    {
        $rate = exchange_rate_ngn_per_usd();

        $this->assertSame(round(10.00 * $rate, 2), credit_units_to_ngn(10.00));
        $this->assertSame(round(5.00 * $rate, 2), credit_units_to_ngn(5.00));
        $this->assertSame(round(180.00 * $rate, 2), credit_units_to_ngn(180.00));
    }

    public function testCreditUnitsAreAPureConversionWithNoClamping(): void
    {
        // The helper converts — it never clamps. Every call site guards the
        // amount itself, so a negative value can never be silently hidden.
        $this->assertSame(0.0, credit_units_to_ngn(0));
        $this->assertSame(-1 * credit_units_to_ngn(5.00), credit_units_to_ngn(-5.00));
    }

    public function testNairaFormatterDoesNotApplyTheExchangeRate(): void
    {
        $this->assertSame('₦15,500', price_ngn_fmt(15500));
        $this->assertSame('₦0', price_ngn_fmt(0));

        // price_fmt() treats its input as USD-canonical — running a naira
        // amount through it inflates the value by the exchange rate.
        $this->assertNotSame(price_fmt(15500), price_ngn_fmt(15500));
    }

    public function testTransactionMoneyKeepsTheStoredCurrency(): void
    {
        $this->assertSame('₦15,500', txn_money(15500, 'NGN'));
        $this->assertSame('₦15,500', txn_money(15500, 'ngn'));
        $this->assertSame('$15,500.00', txn_money(15500, 'USD'));
    }
}
