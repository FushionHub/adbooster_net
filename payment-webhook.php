<?php declare(strict_types=1);

/**
 * Payment Webhook Receiver — Adsbooster.net
 *
 * ONE inbound URL per payment gateway. The gateway calls this endpoint the
 * moment a payment settles, so the user's credits are granted without the
 * payer ever returning to the browser.
 *
 *   POST https://adsbooster.net/payment/webhook/flutterwave
 *   POST https://adsbooster.net/payment/webhook/paystack
 *
 * The endpoint is gateway-agnostic: it reads the gateway key from the URL,
 * verifies the payload with that gateway's signature scheme, resolves the
 * matching local transaction and completes it.
 *
 * See `docs/payments-webhooks.md` for the per-gateway setup notes.
 */

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/payments.php';
require_once __DIR__ . '/modules/payment_gateways.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Emit a JSON response and stop.
 */
function webhook_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// The gateway key travels in the clean URL (/payment/webhook/<gateway>).
$gateway = strtolower(trim(
    $_GET['gateway']
    ?? $_GET['source']
    ?? ($_SERVER['HTTP_X_PAYMENT_GATEWAY'] ?? '')
));

$rawBody = (string)(file_get_contents('php://input') ?: '');
$headers = PaymentGateways::requestHeaders();

// ── Unknown gateway ────────────────────────────────────────────────
if ($gateway === '' || !isset(PaymentGateways::REGISTRY[$gateway])) {
    error_log('Payment webhook received for unknown gateway: ' . $gateway);
    webhook_json(404, [
        'status'  => false,
        'message' => 'Unknown payment gateway. Check the webhook URL configured in your gateway dashboard.',
        'gateways' => array_keys(PaymentGateways::REGISTRY),
    ]);
}

// ── Signature verification ─────────────────────────────────────────
if (!PaymentGateways::verifyWebhookSignature($gateway, $rawBody, $headers)) {
    error_log("Payment webhook signature verification failed for {$gateway}");
    webhook_json(401, [
        'status'  => false,
        'message' => 'Invalid webhook signature.',
        'gateway' => $gateway,
    ]);
}

// ── Parse the payload ──────────────────────────────────────────────
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    // Some gateways (and simple test harnesses) post form-encoded bodies.
    $parsed = [];
    parse_str($rawBody, $parsed);
    $payload = is_array($parsed) ? $parsed : [];
}

// ── Extract the gateway reference ───────────────────────────────────
$data = (array)($payload['data'] ?? []);
$reference = '';
foreach ([
    $data['tx_ref']            ?? null,
    $data['reference']         ?? null,
    $data['transaction_id']    ?? null,
    $data['id']                ?? null,
    $payload['tx_ref']         ?? null,
    $payload['reference']      ?? null,
    $payload['transaction_id'] ?? null,
    $payload['id']             ?? null,
] as $candidate) {
    if ($candidate !== null && trim((string)$candidate) !== '') {
        $reference = trim((string)$candidate);
        break;
    }
}

if ($reference === '') {
    webhook_json(422, [
        'status'  => false,
        'message' => 'No payment reference found in the webhook payload.',
        'gateway' => $gateway,
    ]);
}

// ── Log the delivery (idempotency ledger) ──────────────────────────
$eventId = 0;
try {
    $eventId = db()->insert('payment_webhook_events', [
        'gateway'          => $gateway,
        'event_type'       => (string)($data['event'] ?? $payload['event'] ?? $payload['event_type'] ?? 'unknown'),
        'gateway_reference'=> $reference,
        'payload'          => $rawBody !== '' ? $rawBody : json_encode($payload),
        'status'           => 'received',
        'received_at'      => date('Y-m-d H:i:s'),
    ]);
} catch (\Throwable $logErr) {
    // The logging table is optional: a missing table must not stop the
    // payout. The SQL that creates it lives in update-any-error.sql.
    error_log('Payment webhook log failed (run update-any-error.sql): ' . $logErr->getMessage());
}

// ── Verify + complete the transaction ───────────────────────────────
try {
    $result = PaymentProcessor::verifyGateway($gateway, $reference);
} catch (\Throwable $e) {
    error_log('Payment webhook processing error: ' . $e->getMessage());
    webhook_json(500, [
        'status'  => false,
        'message' => 'Webhook processing failed. Our team has been notified.',
        'gateway' => $gateway,
    ]);
}

// ── Persist the outcome ────────────────────────────────────────────
try {
    $transaction = $result['transaction'] ?? null;
    if ($transaction !== null && isset($transaction['id'])) {
        db()->update('transactions', [
            'status'            => $result['success'] ? 'successful' : ($transaction['status'] ?? 'pending'),
            'gateway_reference' => $transaction['gateway_reference'] ?? $reference,
            'updated_at'        => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$transaction['id']]);
    }
    if ($eventId > 0) {
        db()->update('payment_webhook_events', [
            'status'          => $result['success'] ? 'processed' : 'ignored',
            'transaction_id'  => isset($transaction['id']) ? (int)$transaction['id'] : null,
            'message'         => (string)$result['message'],
            'processed_at'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [$eventId]);
    }
} catch (\Throwable $persistErr) {
    error_log('Payment webhook persist failed (run update-any-error.sql): ' . $persistErr->getMessage());
}

webhook_json($result['success'] ? 200 : 200, [
    'status'       => $result['success'],
    'result'       => $result['status'],
    'message'      => $result['message'],
    'gateway'      => $gateway,
    'reference'    => $reference,
    'transaction'  => isset($transaction['id']) ? (int)$transaction['id'] : null,
]);
