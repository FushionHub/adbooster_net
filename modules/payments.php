<?php declare(strict_types=1);

/**
 * Unified Payment Processor
 * Adsbooster.net
 *
 * Handles payment initiation, verification, and wallet crediting
 * across multiple payment gateways (Paystack, Flutterwave).
 */

require_once __DIR__ . '/../config/db.php';

class PaymentProcessor
{
    /** @var string Paystack API base URL */
    private const PAYSTACK_BASE = 'https://api.paystack.co';

    /** @var string Flutterwave API base URL */
    private const FLUTTERWAVE_BASE = 'https://api.flutterwave.com/v3';

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

        $validGateways = ['paystack', 'flutterwave'];
        if (!in_array($gateway, $validGateways, true)) {
            return ['success' => false, 'transaction_id' => null, 'redirect_url' => null, 'message' => 'Invalid payment gateway.'];
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
        $secretKey = get_setting('paystack_secret_key');
        if (empty($secretKey)) {
            return ['success' => false, 'data' => null, 'message' => 'Paystack secret key not configured.'];
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Paystack verification cURL error: {$error}");
            return ['success' => false, 'data' => null, 'message' => 'Network error during verification.'];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['success' => false, 'data' => null, 'message' => 'Invalid response from Paystack.'];
        }

        if (!isset($decoded['status']) || $decoded['status'] !== true) {
            $msg = $decoded['message'] ?? 'Verification failed.';
            return ['success' => false, 'data' => $decoded['data'] ?? null, 'message' => $msg];
        }

        $data = $decoded['data'] ?? [];
        $verified = ($data['status'] ?? '') === 'success';

        return [
            'success' => $verified,
            'data'    => $data,
            'message' => $verified ? 'Payment verified successfully.' : 'Payment not successful.',
        ];
    }

    /**
     * Verify a Flutterwave transaction by transaction ID.
     *
     * @param string $transactionId Flutterwave transaction ID
     * @return array ['success' => bool, 'data' => array|null, 'message' => string]
     */
    public static function verifyFlutterwave(string $transactionId): array
    {
        $secretKey = get_setting('flutterwave_secret_key');
        if (empty($secretKey)) {
            return ['success' => false, 'data' => null, 'message' => 'Flutterwave secret key not configured.'];
        }

        $url = self::FLUTTERWAVE_BASE . '/transactions/' . urlencode($transactionId) . '/verify';

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

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Flutterwave verification cURL error: {$error}");
            return ['success' => false, 'data' => null, 'message' => 'Network error during verification.'];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['success' => false, 'data' => null, 'message' => 'Invalid response from Flutterwave.'];
        }

        if (!isset($decoded['status']) || $decoded['status'] !== 'success') {
            $msg = $decoded['message'] ?? 'Verification failed.';
            return ['success' => false, 'data' => $decoded['data'] ?? null, 'message' => $msg];
        }

        $data = $decoded['data'] ?? [];
        $verified = ($data['status'] ?? '') === 'successful';

        return [
            'success' => $verified,
            'data'    => $data,
            'message' => $verified ? 'Payment verified successfully.' : 'Payment not successful.',
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
                $creditNote = '$' . number_format($usdAmount, 2) . ' in ad credits';
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
                    $creditNote = 'plan "' . ($plan['name'] ?? 'Subscription') . '" activated with $' . number_format($adCredits, 2) . ' Ad Credits & ' . $aiCredits . ' AI Credits included';
                }
            }

            // Check if this user was referred and has a pending referral reward
            try {
                $pendingRef = db()->fetch(
                    "SELECT * FROM referrals WHERE referred_id = ? AND status = 'pending' LIMIT 1",
                    [$userId]
                );
                if ($pendingRef) {
                    $rewardCredits = (float)($pendingRef['reward_credits'] ?? 10.00);
                    $referrerId = (int)$pendingRef['referrer_id'];

                    // Credit referrer with ad credits
                    db()->query(
                        "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + ? WHERE id = ?",
                        [$rewardCredits, $referrerId]
                    );
                    // Give referred user a bonus $5.00 ad credit
                    db()->query(
                        "UPDATE users SET ad_credits = COALESCE(ad_credits, 0) + 5.00 WHERE id = ?",
                        [$userId]
                    );
                    // Mark referral as rewarded
                    db()->update('referrals', [
                        'status'         => 'rewarded',
                        'reward_credits' => $rewardCredits,
                        'updated_at'     => date('Y-m-d H:i:s')
                    ], 'id = ?', [(int)$pendingRef['id']]);

                    // Send notification to referrer
                    if (class_exists('NotificationService')) {
                        NotificationService::create(
                            $referrerId,
                            'referral_reward',
                            'Referral Bonus Earned! 🎉',
                            'Your referral made their first payment! You received $' . number_format($rewardCredits, 2) . ' in ad credits.',
                            '/referrals.php'
                        );
                    }
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
                    'Your payment of $' . number_format($amount, 2) . ' was verified'
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
        $secretKey = get_setting('paystack_secret_key');
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
        $secretKey = get_setting('flutterwave_secret_key');
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

        $ch = curl_init(self::FLUTTERWAVE_BASE . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

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
