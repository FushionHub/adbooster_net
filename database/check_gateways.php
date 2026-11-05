<?php declare(strict_types=1);
/**
 * Payment gateway registry — prints the live state of every gateway.
 *
 * Usage: php database/check_gateways.php
 */
require_once __DIR__ . '/../config/site_setting_and_config.php';
require_once __DIR__ . '/../modules/payment_gateways.php';

echo "Payment gateway registry\n";
echo str_repeat('-', 70) . "\n";

foreach (PaymentGateways::all() as $key => $cfg) {
    printf(
        "%-12s %-14s public:%-6s secret:%-6s encryption:%-9s mode:%-9s\n",
        $key,
        $cfg['configured'] ? 'ACTIVE' : 'DISABLED',
        $cfg['public_key'] !== '' ? 'yes' : 'no',
        $cfg['secret_key'] !== '' ? 'yes' : 'no',
        $cfg['encryption_key'] !== '' ? 'yes' : 'no',
        $cfg['mode']
    );
    if (!$cfg['configured']) {
        echo "             -> " . PaymentGateways::unavailableReason($key) . " (hidden from customers)\n";
    }
}

echo str_repeat('-', 70) . "\n";
$enabled = PaymentGateways::enabled();
echo "Enabled : " . (empty($enabled) ? '(none)' : implode(', ', $enabled)) . "\n";
echo "Default : " . (PaymentGateways::defaultGateway() ?? '(none)') . "\n";
echo "Disabled: " . (empty(PaymentGateways::disabled()) ? '(none)' : implode(', ', PaymentGateways::disabled())) . "\n";
