<?php declare(strict_types=1);

/**
 * Unified Payment Processor
 * Adsbooster.net
 *
 * Handles payment initiation, verification, and wallet crediting
 * across multiple payment gateways (Paystack, Flutterwave).
 *
 * Gateways are resolved through PaymentGateways: a gateway without
 * complete credentials is auto-deactivated — it can never be selected
 * by a customer and is refused here server-side.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/payment_gateways.php';
require_once __DIR__ . '/referral_service.php';

class PaymentProcessor
{
    /** @var string Paystack API base URL */
    private const PAYSTACK_BASE = 'https://api.paystack.co';

    /** @var string Flutterwave API base URL */
    private const FLUTTERWAVE_BASE = 'https://api.flutterwave.com/v3';

    /**
     * Gateways this deployment can offer.
     *
     * @return string[]
     */
    public static function availableGateways(): array
    {
        return PaymentGateways::enabled();
    }

    /**
     * Initiate a payment transaction.
     *
     * Creates a pending transaction record and returns the gateway
     * redirect URL for the user to complete payment.
     *
     * @param int    $userId   The user initiating the payment
     * @param string $type     Payment type: subscription|campaign|ai_credits|wallet_fund
     * @param float  $amount   Payment amount
     * @param string $currency Three-letter currency code (e.g., NGN, USD)
     * @param string $gateway  Payment gateway: paystack|flutterwave
     * @return array ['success' => bool, 'transaction_id' => int|null, 'redirect_url' => string|null, 'message' => string]
     */
    public static function initiatePayment(
        int $userId,
        string $type,
        float $amount,
        string $currency,
        string $gateway,
        array $metadata = []
    ): array {
        if ($amount <= 0) {
            return ['success' => false, 'transaction_id' => null, 'redirect_url' => null, 'message' => 'Invalid payment amount.'];
        }

        $validGateways = PaymentGateways::enabled();
        if (!in_array($gateway, $validGateways, true)) {
            if (!in_array($gateway, array_keys(PaymentGateways::REGISTRY), true)) {
                return ['success' => false, 'transaction_id' => null, 'redirect_url' => null, 'message' => 'Invalid payment gateway.'];
            }
            return [
                'success'        => false,
                'transaction_id' => null,
                'redirect_url'   => null,
                'message'        => PaymentGateways::config($gateway)['label'] . ' is not available. '
                    . PaymentGateways::unavailableReason($gateway),
            ];
        }

        $user = db()->fetchOne("SELECT id, email, full_name FROM users WHERE id = ?", [$userId]);
        if ($user === null) {
            return ['success' => false, 'transaction_id' => null, 'redirect_url' => null, 'message' => 'User not found.'];
        }

        $invoiceNumber = self::generateInvoiceNumber();
        $reference = strtoupper(bin2hex(random_bytes(12)));

        $transactionId = db()->insert('transactions', [
            'user_id'           => $userId,
            'type'              => $type,
            'amount'            => $amount,
            'currency'          => $currency,
            'gateway'           => $gateway,
            'reference'         => $reference,
            'gateway_reference' => $reference,
            'invoice_number'    => $invoiceNumber,
            'status'            => 'pending',
            'metadata'          => json_encode(array_merge(['email' => $user['email']], $metadata)),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        if ($gateway === 'paystack') {
            $result = self::initPaystack($user['email'], $amount, $currency, $reference);
        } else {
            $result = self::initFlutterwave($user['email'], $amount, $currency, $reference, $transactionId);
        }

        if (!$result['success']) {
            db()->update('transactions', ['status' => 'failed'], 'id = ?', [$transactionId]);
            return ['success' => false, 'transaction_id' => $transactionId, 'redirect_url' => null, 'message' => $result['message']];
        }

        return [
            'success'       => true,
            'transaction_id' => $transactionId,
            'redirect_url'  => $result['redirect_url'],
            'message'       => 'Payment initiated successfully.',
        ];
    }

    /**
     * Verify a Paystack transaction by reference.
     *
     * @param string $reference Paystack transaction reference
     * @return array ['success' => bool, 'data' => array|null, 'message' => string]
     */
    public static function verifyPaystack(string $reference): array
    {
        $secretKey = PaymentGateways::secretKey('paystack');
        if (empty($secretKey)) {
            return ['success' => false, 'data' => null, 'message' => 'Paystack secret key not configured.', 'transient' => false];
        }

        $url = self::PAYSTACK_BASE . '/transaction/verify/' . urlencode($reference);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secretKey,
                'Cache-Control: no-cache',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        // Attach the project CA bundle — without it every gateway call
        // fails TLS verification on SAPIs where curl.cainfo is empty.
        if (function_exists('ca_curl_setopt')) {
            ca_curl_setopt($ch);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Paystack verification cURL error: {$error}");
            return ['success' => false, 'data' => null, 'message' => 'Network error during verification.', 'transient' => true];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['success' => false, 'data' => null, 'message' => 'Invalid response from Paystack.', 'transient' => true];
        }

        if (!isset($decoded['status']) || $decoded['status'] !== true) {
            $msg = $decoded['message'] ?? 'Verification failed.';
            return [
                'success'   => false,
                'data'      => $decoded['data'] ?? null,
                'message'   => $msg,
                // A 5xx is Paystack having a bad day, not a verdict on the
                // payment — only an answered request can deny a payout.
                'transient' => $httpCode >= 500,
            ];
        }

        $data = $decoded['data'] ?? [];
        $verified = ($data['status'] ?? '') === 'success';

        return [
            'success'   => $verified,
            'data'      => $data,
            'message'   => $verified ? 'Payment verified successfully.' : 'Payment not successful.',
            'transient' => false, // Paystack answered: this is a verdict.
        ];
    }

    /**
     * Verify a Flutterwave transaction by transaction ID or reference.
     *
     * Numeric refs hit /transactions/{id}/verify; anything else (tx_ref
     * strings like ABTX-...) is verified by reference via
     * /transactions/verify_by_reference?tx_ref= — the documented endpoint
     * for refs that never received a Flutterwave transaction id.
     *
     * @param string $transactionId Flutterwave transaction ID or tx_ref
     * @return array ['success' => bool, 'data' => array|null, 'message' => string]
     */
    public static function verifyFlutterwave(string $transactionId): array
    {
        $secretKey = PaymentGateways::secretKey('flutterwave');
        if (empty($secretKey)) {
            return ['success' => false, 'data' => null, 'message' => 'Flutterwave secret key not configured.', 'transient' => false];
        }

        if (ctype_digit($transactionId)) {
            $url = self::FLUTTERWAVE_BASE . '/transactions/' . urlencode($transactionId) . '/verify';
        } else {
            $url = self::FLUTTERWAVE_BASE . '/transactions/verify_by_reference?tx_ref=' . urlencode($transactionId);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        // Attach the project CA bundle — without it every gateway call
        // fails TLS verification on SAPIs where curl.cainfo is empty.
        if (function_exists('ca_curl_setopt')) {
            ca_curl_setopt($ch);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Flutterwave verification cURL error: {$error}");
            return ['success' => false, 'data' => null, 'message' => 'Network error during verification.', 'transient' => true];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['success' => false, 'data' => null, 'message' => 'Invalid response from Flutterwave.', 'transient' => true];
        }

        if (!isset($decoded['status']) || $decoded['status'] !== 'success') {
            $msg = $decoded['message'] ?? 'Verification failed.';
            return [
                'success'   => false,
                'data'      => $decoded['data'] ?? null,
                'message'   => $msg,
                // A 5xx is Flutterwave having a bad day, not a verdict on
                // the payment — only an answered request can deny a payout.
                'transient' => $httpCode >= 500,
            ];
        }

        $data = $decoded['data'] ?? [];
        $verified = ($data['status'] ?? '') === 'successful';

        return [
            'success'   => $verified,
            'data'      => $data,
            'message'   => $verified ? 'Payment verified successfully.' : 'Payment not successful.',
            'transient' => false, // Flutterwave answered: this is a verdict.
        ];
    }

    /**
     * Verify a transaction with ANY configured gateway.
     *
     * This is the single entry point used by the hosted-checkout callback,
     * the admin "Verify" button and the inbound webhook endpoint, so all
     * three behave identically no matter which gateway is involved.
     *
     * @param string $gateway   Gateway key (paystack|flutterwave|…)
     * @param string $reference Gateway reference, tx_ref or transaction id
     * @param string $gatewayId Gateway-side transaction id (Flutterwave
     *                           flw_ref / Paystack reference). When given
     *                           it is used for the live API check, while
     *                           $reference still resolves the local row.
     * @return array{success:bool,status:string,message:string,data:array|null,
     *               transaction:array|null,transient:bool}
     */
    public static function verifyGateway(string $gateway, string $reference, string $gatewayId = ''): array
    {
        $gateway   = strtolower(trim($gateway));
        $reference = trim($reference);

        $result = [
            'success'     => false,
            'status'      => 'failed',
            'message'     => '',
            'data'        => null,
            'transaction' => null,
            // True when the gateway could not be reached (network error,
            // unparseable answer, 5xx) — i.e. no verdict was given. A
            // caller may then fall back to a signed payload; it must NOT
            // fall back when the gateway answered and denied the payment.
            'transient'   => false,
        ];

        if ($gateway === '') {
            $result['message'] = 'No payment gateway was specified.';
            return $result;
        }
        if (!isset(PaymentGateways::REGISTRY[$gateway])) {
            $result['message'] = 'Unknown payment gateway "' . $gateway . '".';
            return $result;
        }
        if ($reference === '') {
            $result['message'] = 'Missing payment reference.';
            return $result;
        }

        // The local transaction row (if any) — the webhook/callback flow
        // needs it even when the gateway refuses the verification.
        $transaction = self::findTransaction($gateway, $reference);
        $result['transaction'] = $transaction;

        // Gateways without credentials cannot be verified — say so clearly
        // instead of surfacing a generic failure.
        if (!PaymentGateways::isEnabled($gateway)) {
            $result['status']  = 'unconfigured';
            $result['message'] = PaymentGateways::config($gateway)['label']
                . ' is not available. ' . PaymentGateways::unavailableReason($gateway);
            return $result;
        }

        if ($gateway === 'paystack') {
            $verified = self::verifyPaystack($gatewayId !== '' ? $gatewayId : $reference);
        } elseif ($gateway === 'flutterwave') {
            $verified = self::verifyFlutterwave($gatewayId !== '' ? $gatewayId : $reference);
        } else {
            $verified = self::verifyGenericGateway($gateway, $gatewayId !== '' ? $gatewayId : $reference);
        }

        $result['data']      = $verified['data'];
        $result['message']   = (string)($verified['message'] ?? '');
        $result['transient'] = (bool)($verified['transient'] ?? false);

        if (!$verified['success']) {
            $result['status'] = 'failed';
            return $result;
        }

        // ── Resolve the internal transaction and complete it ───────
        $internalId = self::resolveInternalTransactionId($gateway, $verified['data'], $reference, $transaction);
        if ($internalId <= 0) {
            $result['status']  = 'missing';
            $result['message'] = 'Payment verified at ' . PaymentGateways::config($gateway)['label']
                . ' but no matching local transaction was found. '
                . 'Run update-any-error.sql and re-verify if this transaction should exist locally.';
            return $result;
        }

        $completed = self::completeTransaction($internalId);
        if ($completed) {
            $result['success'] = true;
            $result['status']  = 'successful';
            $result['message'] = 'Payment verified and wallet credited successfully!';
        } else {
            $result['status']  = 'failed';
            $result['message'] = 'Payment verified but crediting failed. Contact support.';
        }

        $result['transaction'] = self::findTransactionById($internalId) ?? $result['transaction'];

        return $result;
    }

    /**
     * Build the Checkout URL that keeps the order context intact.
     *
     * A failed verification lands on the Checkout page — the order summary
     * and the gateway's error message must appear together, otherwise the
     * admin only sees "No item selected" and cannot tell which order broke.
     *
     * @param array|null $transaction       The local transaction row
     * @param string     $fallbackReference Reference used when no row exists
     */
    public static function checkoutUrlForTransaction(?array $transaction, string $fallbackReference = ''): string
    {
        $params = [];

        if ($transaction !== null) {
            $meta = json_decode((string)($transaction['metadata'] ?? '{}'), true) ?: [];

            if (!empty($meta['plan_id'])) {
                $params['plan']  = (int)$meta['plan_id'];
                $params['cycle'] = (string)($meta['cycle'] ?? 'monthly');
            } elseif (isset($meta['amount_usd']) && (float)$meta['amount_usd'] > 0) {
                $params['credits'] = (float)$meta['amount_usd'];
            }

            if (isset($transaction['id'])) {
                $params['txn'] = (int)$transaction['id'];
            }
        }

        if (empty($params) && $fallbackReference !== '') {
            $params['reference'] = $fallbackReference;
        }

        return url('checkout', $params);
    }

    /**
     * Find the local transaction row for a gateway reference.
     *
     * @return array|null
     */
    public static function findTransaction(string $gateway, string $reference): ?array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }
        return db()->fetchOne(
            "SELECT * FROM transactions
              WHERE gateway_reference = ? OR reference = ?
              ORDER BY id DESC LIMIT 1",
            [$reference, $reference]
        );
    }

    /**
     * Find a transaction by its internal id.
     *
     * @return array|null
     */
    public static function findTransactionById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return db()->fetchOne("SELECT * FROM transactions WHERE id = ?", [$id]);
    }

    /**
     * Work out which internal transaction the gateway answer refers to.
     */
    private static function resolveInternalTransactionId(
        string $gateway,
        ?array $data,
        string $reference,
        ?array $transaction
    ): int {
        // 1. The transaction we already matched locally.
        if ($transaction !== null && (int)($transaction['id'] ?? 0) > 0) {
            return (int)$transaction['id'];
        }

        // 2. Metadata supplied by the gateway at checkout time.
        $meta = [];
        if ($gateway === 'flutterwave') {
            $meta = (array)($data['meta'] ?? []);
        } elseif ($gateway === 'paystack') {
            $meta = (array)($data['metadata'] ?? []);
        }
        if (isset($meta['transaction_id']) && (int)$meta['transaction_id'] > 0) {
            $candidate = (int)$meta['transaction_id'];
            if (self::findTransactionById($candidate) !== null) {
                return $candidate;
            }
        }

        // 3. The gateway reference echoed back by the provider.
        $echoed = '';
        if ($gateway === 'flutterwave') {
            $echoed = (string)($data['tx_ref'] ?? '');
        } elseif ($gateway === 'paystack') {
            $echoed = (string)($data['reference'] ?? '');
        } else {
            $echoed = (string)($data['reference'] ?? $data['tx_ref'] ?? '');
        }
        if ($echoed !== '') {
            $match = self::findTransaction($gateway, $echoed);
            if ($match !== null) {
                return (int)$match['id'];
            }
        }

        // 4. The reference the caller handed us.
        $match = self::findTransaction($gateway, $reference);
        if ($match !== null) {
            return (int)$match['id'];
        }

        return 0;
    }

    /**
     * Verify a transaction with a gateway that has no bespoke verifier.
     *
     * The generic contract is intentionally simple: the gateway exposes
     * `GET {base}/verify/{reference}` returning `{status: "success"|true,
     * data: {...}}`. Any gateway added to the registry inherits this path.
     */
    private static function verifyGenericGateway(string $gateway, string $reference): array
    {
        $secretKey = PaymentGateways::secretKey($gateway);
        if ($secretKey === '') {
            return ['success' => false, 'data' => null, 'message' => ucfirst($gateway) . ' secret key not configured.'];
        }

        $meta    = PaymentGateways::REGISTRY[$gateway] ?? [];
        $baseUrl = (string)($meta['base_url'] ?? ('https://api.' . $gateway . '.com/v3'));
        $url     = rtrim($baseUrl, '/') . '/verify/' . urlencode($reference);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secretKey,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        // Attach the project CA bundle — without it every gateway call
        // fails TLS verification on SAPIs where curl.cainfo is empty.
        if (function_exists('ca_curl_setopt')) {
            ca_curl_setopt($ch);
        }

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log(ucfirst($gateway) . " verification cURL error: {$error}");
            return ['success' => false, 'data' => null, 'message' => 'Network error during verification.', 'transient' => true];
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'data' => null, 'message' => 'Invalid response from ' . ucfirst($gateway) . '.', 'transient' => true];
        }

        $ok = ($decoded['status'] ?? '') === 'success' || ($decoded['status'] ?? '') === true;
        $data = (array)($decoded['data'] ?? []);

        return [
            'success'   => $ok,
            'data'      => $data,
            'message'   => $ok
                ? 'Payment verified successfully.'
                : (string)($decoded['message'] ?? 'Payment verification failed.'),
            'transient' => false, // The gateway answered: this is a verdict.
        ];
    }

    /**
     * Mark a transaction as complete and credit the user's balance.
     *
     * Credits the same users.ad_credits / users.ai_credits balances the
     * dashboard and billing pages read. Plan subscriptions assign the
     * plan from the transaction metadata.
     *
     * @param int $transactionId The transaction ID to complete
     * @return bool True on success
     */
    public static function completeTransaction(int $transactionId): bool
    {
        try {
            $transaction = db()->fetchOne(
                "SELECT id, user_id, amount, currency, type, status, metadata FROM transactions WHERE id = ?",
                [$transactionId]
            );

            if ($transaction === null) {
                error_log("Transaction not found: {$transactionId}");
                return false;
            }

            if ($transaction['status'] === 'successful') {
                return true; // Already completed
            }

            if ($transaction['status'] !== 'pending') {
                error_log("Transaction {$transactionId} is in unexpected status: {$transaction['status']}");
                return false;
            }

            $userId = (int) $transaction['user_id'];
            $amount = (float) $transaction['amount'];
            $meta = json_decode((string)($transaction['metadata'] ?? '{}'), true) ?: [];

            // Wallets are USD-denominated. NGN charges are converted to their
            // USD equivalent (pinned at initiation when available) before any
            // credit is granted.
            $usdAmount = $amount;
            if (strtoupper((string)($transaction['currency'] ?? 'USD')) === 'NGN') {
                $usdAmount = isset($meta['amount_usd'])
                    ? (float) $meta['amount_usd']
                    : round($amount / max(1.0, function_exists('exchange_rate_ngn_per_usd') ? exchange_rate_ngn_per_usd() : 1550.0), 2);
            }
            $aiCreditsPerUsd = 100; // 100 AI credits per $1 — matches the billing UI

            db()->query("BEGIN");

            // Mark transaction as paid
            db()->update('transactions', [
                'status'     => 'successful',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$transactionId]);

            // Credit the matching balance / assign plan
            $creditNote = '';
            if ($transaction['type'] === 'ad_credit_topup') {
                db()->query(
                    "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?",
                    [$usdAmount, $userId]
                );
                $creditNote = price_fmt($usdAmount) . ' in ad credits';
            } elseif ($transaction['type'] === 'ai_credit_topup') {
                $granted = (int) round($usdAmount * $aiCreditsPerUsd);
                db()->query(
                    "UPDATE users SET ai_credits = COALESCE(ai_credits, 0) + ? WHERE id = ?",
                    [$granted, $userId]
                );
                $creditNote = $granted . ' AI credits';
            } elseif ($transaction['type'] === 'plan_subscription' && !empty($meta['plan_id'])) {
                $plan = db()->fetchOne(
                    "SELECT id, name, billing_cycle, ad_credits_included, ai_credits_included FROM pricing_plans WHERE id = ? AND status = 'active'",
                    [(int) $meta['plan_id']]
                );
                if ($plan !== null) {
                    // Explicit cycle from checkout metadata wins over the plan row
                    // (supports monthly / yearly-25% variants of the same plan).
                    $cycle  = (string)($meta['cycle'] ?? $plan['billing_cycle'] ?? 'monthly');
                    $months = ($cycle === 'yearly') ? 12 : 1;
                    $adCredits = (float)($plan['ad_credits_included'] ?? 0);
                    $aiCredits = (int)($plan['ai_credits_included'] ?? 0);

                    db()->query(
                        "UPDATE users SET plan_id = ?, billing_cycle = ?, 
                         plan_expiry = DATE_ADD(GREATEST(COALESCE(plan_expiry, CURDATE()), CURDATE()), INTERVAL {$months} MONTH),
                         ad_credits = COALESCE(ad_credits, 0) + ?,
                         ai_credits = COALESCE(ai_credits, 0) + ?
                         WHERE id = ?",
                        [(int) $plan['id'], $cycle, $adCredits, $aiCredits, $userId]
                    );
                    $creditNote = 'plan "' . ($plan['name'] ?? 'Subscription') . '" activated with ' . price_fmt($adCredits) . ' Ad Credits & ' . $aiCredits . ' AI Credits included';
                }
            }

            // ── Automatic referral reward ───────────────────────────
            // The referrer is paid the moment the referred user's payment
            // is verified. Admin can still pay the same referral manually
            // from Admin → Referrals if the automatic pass was missed.
            try {
                $reward = ReferralService::rewardForPayment($userId, $transaction);
                if ($reward['rewarded']) {
                    $creditNote = trim(
                        $creditNote . ($creditNote !== '' ? ' · ' : '')
                        . 'referral reward ' . number_format((float)$reward['amount'], 2) . ' credits issued'
                    );
                }
            } catch (\Throwable $refErr) {
                error_log("Referral crediting error: " . $refErr->getMessage());
            }

            db()->query("COMMIT");

            // Notify + email (email-first architecture)
            try {
                if (!class_exists('NotificationService')) {
                    require_once __DIR__ . '/notification_service.php';
                }
                NotificationService::create(
                    $userId,
                    'payment',
                    'Payment Successful',
                    'Your payment of ' . txn_money($amount, (string)($transaction['currency'] ?? null)) . ' was verified'
                        . ($creditNote !== '' ? ' and ' . $creditNote . ' credited to your account.' : '.')
                );
            } catch (\Throwable $e) {
                error_log("Payment notification failed: " . $e->getMessage());
            }

            // Generate invoice (best-effort, never breaks crediting)
            try {
                if (!class_exists('InvoiceGenerator')) {
                    require_once __DIR__ . '/invoice_generator.php';
                }
                $invoicePath = InvoiceGenerator::generate($transactionId);
                db()->update('transactions', ['invoice_path' => $invoicePath], 'id = ?', [$transactionId]);
            } catch (\Throwable $e) {
                error_log("Invoice generation failed for txn {$transactionId}: " . $e->getMessage());
            }

            return true;

        } catch (\Throwable $e) {
            try { db()->query("ROLLBACK"); } catch (\Throwable $ignored) {}
            error_log("Transaction completion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize Paystack payment.
     *
     * @param string $email    Payer email
     * @param float  $amount   Amount in smallest currency unit (kobo/cents)
     * @param string $currency Currency code
     * @param string $reference Unique payment reference
     * @return array ['success' => bool, 'redirect_url' => string|null, 'message' => string]
     */
    private static function initPaystack(
        string $email,
        float $amount,
        string $currency,
        string $reference
    ): array {
        $secretKey = PaymentGateways::secretKey('paystack');
        if (empty($secretKey)) {
            return ['success' => false, 'redirect_url' => null, 'message' => 'Paystack secret key not configured.'];
        }

        // Paystack expects amount in kobo (smallest unit)
        $amountInKobo = (int) ($amount * 100);

        $payload = json_encode([
            'email'     => $email,
            'amount'    => $amountInKobo,
            'currency'  => strtoupper($currency),
            'reference' => $reference,
            'callback_url' => get_setting('paystack_callback_url') ?: (self::getAppUrl() . '/payment/callback/paystack'),
        ]);

        $ch = curl_init(self::PAYSTACK_BASE . '/transaction/initialize');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json',
                'Cache-Control: no-cache',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        // Attach the project CA bundle — without it every gateway call
        // fails TLS verification on SAPIs where curl.cainfo is empty.
        if (function_exists('ca_curl_setopt')) {
            ca_curl_setopt($ch);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Paystack init cURL error: {$error}");
            return ['success' => false, 'redirect_url' => null, 'message' => 'Network error connecting to Paystack.'];
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['status']) || $decoded['status'] !== true) {
            $msg = $decoded['message'] ?? 'Failed to initialize payment.';
            return ['success' => false, 'redirect_url' => null, 'message' => $msg];
        }

        $redirectUrl = $decoded['data']['authorization_url'] ?? null;
        return ['success' => true, 'redirect_url' => $redirectUrl, 'message' => 'Paystack payment initialized.'];
    }

    /**
     * Initialize Flutterwave payment.
     *
     * @param string $email        Payer email
     * @param float  $amount       Payment amount
     * @param string $currency     Currency code
     * @param string $reference    Unique reference
     * @param int    $transactionId Internal transaction ID
     * @return array ['success' => bool, 'redirect_url' => string|null, 'message' => string]
     */
    private static function initFlutterwave(
        string $email,
        float $amount,
        string $currency,
        string $reference,
        int $transactionId
    ): array {
        $cfg       = PaymentGateways::config('flutterwave');
        $secretKey = (string)$cfg['secret_key'];
        if (empty($secretKey)) {
            return ['success' => false, 'redirect_url' => null, 'message' => 'Flutterwave secret key not configured.'];
        }

        $payload = json_encode([
            'tx_ref'       => $reference,
            'amount'       => $amount,
            'currency'     => strtoupper($currency),
            'redirect_url' => get_setting('flutterwave_callback_url') ?: (self::getAppUrl() . '/payment/callback/flutterwave'),
            'customer'     => [
                'email' => $email,
            ],
            'meta' => [
                'transaction_id' => $transactionId,
            ],
        ]);

        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // Flutterwave supports an encrypted request body. It is only used
        // with a genuine 32-character encryption key; otherwise the
        // standard Bearer flow is used.
        if ($cfg['mode'] === 'encrypted' && function_exists('openssl_encrypt')) {
            $encryptionKey = (string)$cfg['encryption_key'];
            $iv = random_bytes(16);
            $ciphertext = openssl_encrypt(
                (string)$payload,
                'AES-256-CBC',
                $encryptionKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            if ($ciphertext !== false) {
                $payload  = base64_encode($iv . $ciphertext);
                $headers[] = 'X-Encryption-Key: ' . $encryptionKey;
            }
        }

        $ch = curl_init(self::FLUTTERWAVE_BASE . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        // Attach the project CA bundle — without it every gateway call
        // fails TLS verification on SAPIs where curl.cainfo is empty.
        if (function_exists('ca_curl_setopt')) {
            ca_curl_setopt($ch);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Flutterwave init cURL error: {$error}");
            return ['success' => false, 'redirect_url' => null, 'message' => 'Network error connecting to Flutterwave.'];
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['status']) || $decoded['status'] !== 'success') {
            $msg = $decoded['message'] ?? 'Failed to initialize payment.';
            return ['success' => false, 'redirect_url' => null, 'message' => $msg];
        }

        $redirectUrl = $decoded['data']['link'] ?? null;
        return ['success' => true, 'redirect_url' => $redirectUrl, 'message' => 'Flutterwave payment initialized.'];
    }

    /**
     * Generate a unique invoice number.
     *
     * Format: INV-YYYYMMDD-XXXXXX
     *
     * @return string
     */
    private static function generateInvoiceNumber(): string
    {
        $datePart = date('Ymd');
        $randomPart = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return "INV-{$datePart}-{$randomPart}";
    }

    /**
     * Get the application base URL.
     *
     * @return string
     */
    private static function getAppUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'adsbooster.net';
        return $scheme . '://' . $host;
    }
}
