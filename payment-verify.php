<?php declare(strict_types=1);

/**
 * Payment Verification Callback — Adsbooster.net
 *
 * Handles BOTH hosted-checkout return URLs and the admin "Verify" button:
 *
 *   /payment/callback/<gateway>   ← the payer comes back from the gateway
 *   /payment-verify?gateway=…     ← admin re-checks a transaction
 *
 * The verification itself is delegated to PaymentProcessor::verifyGateway(),
 * so every gateway behaves identically.
 *
 * Outcomes:
 *   • success → the user lands on their dashboard, an admin lands back on
 *               Admin → Transactions.
 *   • failure → everybody lands on the Checkout page, which shows the exact
 *               reason (and what to update) in the error banner.
 */

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/payments.php';
require_once __DIR__ . '/modules/payment_gateways.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Build the checkout URL that keeps the order context intact, so the
 * Checkout page shows the real order (not "No item selected") next to the
 * error message.
 */
function payment_checkout_url(?array $transaction, string $fallbackReference = ''): string
{
    return PaymentProcessor::checkoutUrlForTransaction($transaction, $fallbackReference);
}

$gateway = strtolower(trim($_GET['gateway'] ?? $_GET['source'] ?? ''));

// ── Resolve the reference the gateway handed back ───────────────────
$reference = '';
foreach ([
    $_GET['reference']      ?? null,   // Paystack
    $_GET['trxref']         ?? null,   // Paystack (legacy)
    $_GET['tx_ref']         ?? null,   // Flutterwave
    $_GET['transaction_id'] ?? null,   // Flutterwave
    $_GET['id']             ?? null,
] as $candidate) {
    if ($candidate !== null && trim((string)$candidate) !== '') {
        $reference = trim((string)$candidate);
        break;
    }
}

// ── Admin "Verify" button: the reference may be an internal txn id ──
$internalTxnId = (int)($_GET['txn'] ?? 0);
if ($reference === '' && $internalTxnId > 0) {
    $row = PaymentProcessor::findTransactionById($internalTxnId);
    if ($row !== null) {
        $reference = (string)($row['gateway_reference'] ?? $row['reference'] ?? '');
    }
}

if ($gateway === '' && $reference !== '') {
    // Infer the gateway from the local transaction when the caller omitted it.
    $row = PaymentProcessor::findTransaction('', $reference);
    if ($row !== null && !empty($row['gateway'])) {
        $gateway = strtolower((string)$row['gateway']);
    }
}

$isAdmin = function_exists('is_admin') && is_admin();

$verified = false;
$message  = '';
$transaction = null;

try {
    if ($gateway === '') {
        throw new \RuntimeException('No payment gateway was specified.');
    }
    if ($reference === '') {
        throw new \RuntimeException('Missing payment reference.');
    }

    $result = PaymentProcessor::verifyGateway($gateway, $reference);

    $verified   = (bool)$result['success'];
    $message    = (string)$result['message'];
    $transaction = $result['transaction'] ?? null;

    // A successful verification returns a friendlier sentence.
    if ($verified && $message === '') {
        $message = 'Payment verified and wallet credited successfully!';
    }
} catch (\Throwable $e) {
    error_log('Payment verify error: ' . $e->getMessage());
    $message = $e->getMessage();
}

// ── Land on the right page ──────────────────────────────────────────
if ($verified) {
    set_flash('success', $message);
    redirect($isAdmin ? url('admin/transactions') : url('dashboard'));
}

// Failure → the Checkout page explains exactly what went wrong.
if ($message === '') {
    $message = 'Payment verification failed.';
}

set_flash('error', $message);

// Administrators get the full diagnostic so they can act on it.
if ($isAdmin) {
    set_flash(
        'warning',
        'Verification failed for ' . strtoupper($gateway)
        . ' reference ' . $reference . '. '
        . 'The checkout page below shows the order context; database fixes for this '
        . 'class of error are listed in update-any-error.sql.'
    );
}

redirect(payment_checkout_url($transaction, $reference));
