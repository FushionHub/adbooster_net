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
 * Flow:
 *   1. unknown gateway            → 404
 *   2. bad / missing signature    → 401
 *   3. no reference in payload    → 422
 *   4. log the delivery           → payment_webhook_events
 *   5. verify with the gateway    → complete the local transaction
 *   6. transient failure + signed payload → settle from the payload
 *
 * See `docs/payments-webhooks.md` for the per-gateway setup notes.
 */

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/payments.php';
require_once __DIR__ . '/modules/payment_gateways.php';
require_once __DIR__ . '/modules/payment_webhook.php';

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
$data = (array)($payload['data'] ?? []);

// ── Extract the gateway reference ───────────────────────────────────
$reference = PaymentWebhook::local_reference($payload);

if ($reference === '') {
    webhook_json(422, [
        'status'  => false,
        'message' => 'No payment reference found in the webhook payload.',
        'gateway' => $gateway,
    ]);
}

// The gateway-side transaction id (Flutterwave flw_ref, …) — used for
// the live API check when present, because it is the strongest handle.
$gatewayId = PaymentWebhook::gateway_transaction_id($payload, $gateway);

// ── Log the delivery (idempotency ledger) ──────────────────────────
$eventId = 0;
try {
    $eventId = db()->insert('payment_webhook_events', [
        'gateway'          => $gateway,
        'event_type'       => (string)($payload['event'] ?? $payload['event_type'] ?? $data['event'] ?? 'unknown'),
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
    $result = PaymentProcessor::verifyGateway($gateway, $reference, $gatewayId);
} catch (\Throwable $e) {
    error_log('Payment webhook processing error: ' . $e->getMessage());
    webhook_json(500, [
        'status'  => false,
        'message' => 'Webhook processing failed. Our team has been notified.',
        'gateway' => $gateway,
    ]);
}

// ── Trusted-payload fallback ────────────────────────────────────────
// The signature check above already proved this event came from the
// gateway — that is what the secret is for. When the gateway could not
// be reached to confirm the payment (network error, 5xx, unparseable
// answer) the money has still moved, so a payload that explicitly
// reports success settles the local pending transaction instead of
// silently dropping the event.
//
// Four conditions must all hold:
//   1. the failure was TRANSIENT — when the gateway answered and denied
//      the payment, that answer wins; crediting on a denial would pay
//      out for a transaction that never settled
//   2. the signature covers the BODY — Flutterwave's verif-hash is a
//      static sha256(secret) and does not authenticate the payload, so a
//      captured delivery could otherwise be replayed with a forged body
//   3. the payload reports success explicitly
//   4. the payload agrees with the local order (amount + currency),
//      which blocks a captured body being replayed onto another order
//
// Never fails a transaction here: a "failed" event leaves the row pending
// for the admin to decide (they can re-verify or mark it failed).
$fallback = false;
$localTxn = $result['transaction'] ?? null;
if (
    !$result['success']
    && !empty($result['transient'])
    && PaymentGateways::webhookSignatureCoversBody($gateway, $headers)
    && is_array($localTxn)
    && isset($localTxn['id'])
    && ($localTxn['status'] ?? '') === 'pending'
    && PaymentWebhook::payload_reports_success($payload)
    && PaymentWebhook::payload_matches_order($payload, $localTxn)
) {
    if (PaymentProcessor::completeTransaction((int)$localTxn['id'])) {
        $fallback = true;
        $result['success']     = true;
        $result['status']      = 'successful';
        $result['message']     = 'Payment settled from the signed gateway payload. Live confirmation said: '
            . ((string)$result['message'] ?: 'none');
        $result['transaction'] = PaymentProcessor::findTransactionById((int)$localTxn['id']) ?? $localTxn;
    }
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
            'message'         => ($fallback ? '[signed-payload fallback] ' : '') . (string)$result['message'],
            'processed_at'    => date('Y-m-d H:i:s'),
        ], 'id = ?', [$eventId]);
    }
} catch (\Throwable $persistErr) {
    error_log('Payment webhook persist failed (run update-any-error.sql): ' . $persistErr->getMessage());
}

// ── Answer the gateway ─────────────────────────────────────────────
// 200 = "understood, stop retrying" (whether or not it paid out).
// 502 = the gateway could not be reached to confirm, so the provider
//       should redeliver later instead of silently dropping the event.
$message   = (string)$result['message'];
$transient = !$result['success'] && !empty($result['transient']);

webhook_json($transient ? 502 : 200, [
    'status'       => $result['success'],
    'result'       => $result['status'],
    'message'      => $message,
    'gateway'      => $gateway,
    'reference'    => $reference,
    'transaction'  => isset($transaction['id']) ? (int)$transaction['id'] : null,
    'settled_by'   => $fallback ? 'signed_payload' : ($result['success'] ? 'gateway_api' : null),
    'retry'        => $transient,
]);
