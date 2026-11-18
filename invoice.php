<?php declare(strict_types=1);

/**
 * Invoice viewer / downloader — Adsbooster.net
 *
 * Serves the generated HTML invoice (uploads/invoices/INV-*.html) for the
 * signed-in user's OWN transactions only (admins may view any). The raw
 * uploads directory stays unlinked from the app: links point here instead,
 * so an invoice can never be fetched by someone else who guesses the file.
 *
 *   /invoice?id=123          → render the invoice
 *   /invoice?id=123&dl=1      → download as a file
 */

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId = current_user_id();
$user   = current_user();
$txId   = (int)($_GET['id'] ?? 0);
$wantsDownload = isset($_GET['dl']);

if ($userId <= 0) {
    redirect(url('login'));
}
if ($txId <= 0) {
    http_response_code(400);
    exit('Invalid invoice reference.');
}

// Owner first; admins may inspect any invoice.
$tx = db()->fetchOne(
    "SELECT id, user_id, status, invoice_path, invoice_number FROM transactions WHERE id = ? AND user_id = ?",
    [$txId, $userId]
);
if ($tx === null && (($user['role'] ?? '') === 'admin')) {
    $tx = db()->fetchOne(
        "SELECT id, user_id, status, invoice_path, invoice_number FROM transactions WHERE id = ?",
        [$txId]
    );
}
if ($tx === null) {
    http_response_code(404);
    exit('Invoice not found.');
}

// On-demand generation for older successful payments that predate the
// automatic invoice pass (best-effort; never breaks the request).
if (empty($tx['invoice_path']) && in_array((string)$tx['status'], ['successful', 'completed', 'success'], true)) {
    try {
        if (!class_exists('InvoiceGenerator')) {
            require_once __DIR__ . '/modules/invoice_generator.php';
        }
        $generated = InvoiceGenerator::generate($txId);
        db()->update('transactions', ['invoice_path' => $generated], 'id = ?', [$txId]);
        $tx['invoice_path'] = $generated;
    } catch (\Throwable $e) {
        error_log('Invoice on-demand generation failed for txn ' . $txId . ': ' . $e->getMessage());
    }
}

$rel = ltrim((string)($tx['invoice_path'] ?? ''), '/');
if ($rel === '') {
    http_response_code(404);
    exit('No invoice has been generated for this transaction yet.');
}

// Path containment: the resolved file must live inside uploads/invoices/.
$uploadBase = realpath(__DIR__ . '/uploads/invoices');
$real       = realpath(__DIR__ . '/' . $rel);
if ($uploadBase === false || $real === false || !is_file($real)
    || !str_starts_with($real, $uploadBase . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Invoice file could not be found on this server.');
}

$filename = basename($real);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
if ($wantsDownload) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}
readfile($real);
exit;
