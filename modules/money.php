<?php declare(strict_types=1);

/**
 * Money helpers — Adsbooster.net
 *
 * Side-effect-free helpers shared by the web app, the REST API and the CLI
 * cron workers. This file must stay dependency-light (no sessions, no
 * headers, no redirects): the REST API bootstrap loads it WITHOUT the full
 * application bootstrap, so anything defined here has to work in every
 * entry point.
 *
 * Unit model:
 *   • The platform currency is NAIRA. `users.ad_credits` is naira-
 *     denominated: one wallet credit is one naira of ad budget.
 *   • `price_fmt()` (application bootstrap) is USD-canonical: it multiplies
 *     its input by the naira rate. It must NEVER format an amount that is
 *     already stored in naira — that is what `price_ngn_fmt()` is for.
 *   • A few legacy subsystems still keep their amounts in the old
 *     USD-canonical credit units (referral rewards, bounty payouts, listing
 *     fees, signup bonuses, legacy USD transactions). `credit_units_to_ngn()`
 *     converts those exactly ONCE, at the wallet boundary.
 */

/**
 * Format an amount that is ALREADY stored in NAIRA.
 *
 * @param float $ngn Amount in naira (already NGN).
 */
function price_ngn_fmt(float $ngn): string
{
    return '₦' . number_format($ngn, 0);
}

/**
 * NGN units per 1 USD (admin-tunable via Settings → ngn_usd_rate).
 *
 * The application bootstrap exposes exchange_rate_ngn_per_usd(), which reads
 * the same setting through get_setting(). This reader is the fallback for
 * entry points that do not load the full bootstrap (REST API, CLI workers):
 * it reads the setting row directly and never fails — a broken settings
 * table must not take the API down.
 */
function money_ngn_per_usd_rate(): float
{
    static $rate = null;
    if ($rate !== null) {
        return $rate;
    }

    $rate = 1550.0;
    try {
        $row = db()->fetchOne(
            "SELECT setting_value FROM site_settings WHERE setting_key = ?",
            ['ngn_usd_rate']
        );
        $value = (float)($row['setting_value'] ?? 0);
        if ($value > 0) {
            $rate = $value;
        }
    } catch (\Throwable $e) {
        // Keep the default rate — a missing settings table must not break
        // the money path.
    }

    return $rate;
}

/**
 * Convert a USD-canonical credit amount into naira wallet units.
 *
 * @param float $usdCredits Amount expressed in the old USD-canonical units.
 */
function credit_units_to_ngn(float $usdCredits): float
{
    $rate = function_exists('exchange_rate_ngn_per_usd')
        ? exchange_rate_ngn_per_usd()
        : money_ngn_per_usd_rate();

    return round($usdCredits * max(1.0, $rate), 2);
}
