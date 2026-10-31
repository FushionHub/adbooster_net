<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/payments.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$gateway = strtolower(trim($_GET['gateway'] ?? $_GET['source'] ?? ''));
$verified = false;
$message  = '';

try {
    if ($gateway === 'paystack') {
        $reference = trim($_GET['reference'] ?? $_GET['trxref'] ?? '');
        if ($reference === '') {
            throw new \RuntimeException('Missing Paystack reference.');
        }

        $result = PaymentProcessor::verifyPaystack($reference);

        if ($result['success'] && isset($result['data']['metadata']['transaction_id'])) {
            $transactionId = (int)$result['data']['metadata']['transaction_id'];
            $completed = PaymentProcessor::completeTransaction($transactionId);
            if ($completed) {
                $verified = true;
                $message = 'Payment verified and wallet credited successfully!';
            } else {
                $message = 'Payment verified but crediting failed. Contact support.';
            }
        } elseif ($result['success']) {
            $txnRef = $result['data']['reference'] ?? '';
            $transaction = db()->fetchOne("SELECT id FROM transactions WHERE gateway_reference = ? AND status = 'pending'", [$txnRef]);
            if ($transaction) {
                $completed = PaymentProcessor::completeTransaction((int)$transaction['id']);
                if ($completed) {
                    $verified = true;
                    $message = 'Payment verified and wallet credited successfully!';
                } else {
                    $message = 'Payment verified but crediting failed. Contact support.';
                }
            } else {
                $message = 'Transaction not found. Contact support.';
            }
        } else {
            $message = $result['message'] ?? 'Payment verification failed.';
        }

    } elseif ($gateway === 'flutterwave') {
        $transactionIdParam = trim($_GET['transaction_id'] ?? $_GET['tx_ref'] ?? '');
        if ($transactionIdParam === '') {
            throw new \RuntimeException('Missing Flutterwave transaction ID.');
        }

        $result = PaymentProcessor::verifyFlutterwave($transactionIdParam);

        if ($result['success'] && isset($result['data']['meta']['transaction_id'])) {
            $internalId = (int)$result['data']['meta']['transaction_id'];
            $completed = PaymentProcessor::completeTransaction($internalId);
            if ($completed) {
                $verified = true;
                $message = 'Payment verified and wallet credited successfully!';
            } else {
                $message = 'Payment verified but crediting failed. Contact support.';
            }
        } else {
            $message = $result['message'] ?? 'Payment verification failed.';
        }

    } else {
        $message = 'Unknown payment gateway.';
    }
} catch (\Throwable $e) {
    error_log("Payment verify error: " . $e->getMessage());
    $message = 'An error occurred during verification.';
}

if ($verified) {
    set_flash('success', $message);
    redirect(url('dashboard'));
} else {
    set_flash('error', $message);
    redirect(url('checkout'));
}
