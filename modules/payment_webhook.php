<?php declare(strict_types=1);

/**
 * Payment Webhook Helpers — Adsbooster.net
 *
 * The pure decision logic behind the inbound webhook endpoint
 * (`payment-webhook.php`). It lives in a module (not in the endpoint
 * script) so it can be unit-tested without a web server or a database.
 *
 * Two decisions are made here:
 *
 *   1. webhook_payload_reports_success() — does the signed payload
 *      explicitly say the payment succeeded?
 *
 *   2. webhook_payload_matches_order() — does the payload agree with
 *      the local order it claims to pay? This is the replay guard for
 *      gateways whose signature covers the secret but not the body
 *      (Flutterwave's verif-hash).
 *
 * The endpoint itself owns the I/O: reading the body, verifying the
 * signature, calling the gateway, completing the transaction.
 */

final class PaymentWebhook
{
    /**
     * Status values that mean "the payment went through".
     *
     *   Paystack    data.status = "success"
     *   Flutterwave data.status = "successful"
     *   generic     "completed" / "paid"
     */
    private const SUCCESS_STATUSES = ['success', 'successful', 'completed', 'complete', 'paid'];

    /**
     * Does the parsed gateway payload explicitly report a successful payment?
     *
     * Anything ambiguous (missing status, "pending", "processing") returns
     * false — the fallback must only ever fire on an explicit success.
     *
     * @param array $payload Decoded webhook body
     */
    public static function payload_reports_success(array $payload): bool
    {
        $data = (array)($payload['data'] ?? []);

        $status = strtolower(trim((string)(
            $data['status']
            ?? $payload['status']
            ?? ''
        )));
        if (in_array($status, self::SUCCESS_STATUSES, true)) {
            return true;
        }

        $event = strtolower(trim((string)(
            $payload['event']
            ?? $payload['event_type']
            ?? $data['event']
            ?? ''
        )));
        if ($event === '') {
            return false;
        }
        if (preg_match('/\b(success|successful|completed|paid)\b/', $event)) {
            return true;
        }
        return str_ends_with($event, '.success') || str_ends_with($event, '.completed');
    }

    /**
     * Does the gateway payload agree with the local order it claims to pay?
     *
     * Guards the signed-payload fallback against replay: Flutterwave's
     * verif-hash authenticates the secret, not the body, so a captured
     * body could otherwise be re-sent to settle a different order.
     *
     * Rules:
     *   • the amount must match the local transaction amount (when the
     *     payload carries one) — 1 cent of tolerance absorbs rounding
     *   • the currency must match (when the payload carries one)
     *
     * A payload with no amount at all is accepted: the reference itself
     * is unique per order, so the match is still 1:1.
     *
     * @param array $payload    Decoded webhook body
     * @param array $transaction Local transaction row
     */
    public static function payload_matches_order(array $payload, array $transaction): bool
    {
        $data = (array)($payload['data'] ?? []);

        $amount = $data['amount'] ?? $data['charged_amount'] ?? $payload['amount'] ?? null;
        if ($amount !== null && trim((string)$amount) !== '') {
            $payloadAmount = (float)$amount;
            $localAmount   = (float)($transaction['amount'] ?? 0);

            if ($localAmount <= 0 || abs($payloadAmount - $localAmount) > 0.01) {
                return false;
            }
        }

        $currency = strtoupper(trim((string)(
            $data['currency'] ?? $payload['currency'] ?? ''
        )));
        if ($currency !== '') {
            $localCurrency = strtoupper(trim((string)($transaction['currency'] ?? '')));
            // NGN charges are converted to USD before the wallet is
            // credited, so only an exact currency match is accepted.
            if ($localCurrency !== '' && $currency !== $localCurrency) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pull the gateway-side transaction id out of a payload.
     *
     * Flutterwave names it flw_ref, Paystack uses "reference" and the
     * generic gateways use "id"/"transaction_id". It is the strongest
     * handle for the live API check — but it is NOT the local reference
     * (that stays the tx_ref so the local row still resolves).
     *
     * @return string Empty string when the payload carries no usable id
     */
    public static function gateway_transaction_id(array $payload, string $gateway = ''): string
    {
        $data = (array)($payload['data'] ?? []);

        $id = '';
        foreach ([
            $data['flw_ref']        ?? null,
            $data['flw_tx_ref']     ?? null,
            $data['id']             ?? null,
            $data['transaction_id'] ?? null,
        ] as $candidate) {
            if ($candidate !== null && trim((string)$candidate) !== '') {
                $id = trim((string)$candidate);
                break;
            }
        }

        // A numeric Flutterwave id is what /transactions/{id}/verify
        // expects; a non-numeric one would only produce a confusing 400.
        if ($gateway === 'flutterwave' && !ctype_digit($id)) {
            return '';
        }

        return $id;
    }

    /**
     * Pull the local reference (tx_ref) out of a payload.
     *
     * @return string Empty string when the payload carries no reference
     */
    public static function local_reference(array $payload): string
    {
        $data = (array)($payload['data'] ?? []);

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
                return trim((string)$candidate);
            }
        }

        return '';
    }
}
