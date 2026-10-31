<?php declare(strict_types=1);

/**
 * Invoice Generator
 * Adsbooster.net
 *
 * Generates professional HTML invoices for completed transactions.
 * Invoices are saved to the uploads/invoices/ directory.
 */

require_once __DIR__ . '/../config/db.php';

class InvoiceGenerator
{
    /** @var string Brand primary accent color */
    private const BRAND_PRIMARY = '#00ff88';

    /** @var string Brand dark background */
    private const BRAND_DARK = '#1a1a2e';

    /** @var string Brand secondary dark */
    private const BRAND_DARK_ALT = '#16213e';

    /** @var string Text color */
    private const BRAND_TEXT = '#e0e0e0';

    /** @var string Muted text color */
    private const BRAND_MUTED = '#a0a0a0';

    /** @var string Company legal name */
    private const COMPANY_NAME = 'Adsbooster.net';

    /** @var string Company address line 1 */
    private const COMPANY_ADDRESS_1 = 'Digital Marketing Solutions';

    /** @var string Company address line 2 */
    private const COMPANY_ADDRESS_2 = 'Lagos, Nigeria';

    /** @var string Company contact email */
    private const COMPANY_EMAIL = 'billing@adsbooster.net';

    /** @var string Company website */
    private const COMPANY_URL = 'https://adsbooster.net';

    /**
     * Generate an HTML invoice for a completed transaction.
     *
     * @param int $transactionId The completed transaction ID
     * @return string File path to the saved invoice
     * @throws \RuntimeException If transaction not found or invoice generation fails
     */
    public static function generate(int $transactionId): string
    {
        $transaction = db()->fetchOne(
            "SELECT t.*, u.full_name, u.email
             FROM transactions t
             JOIN users u ON t.user_id = u.id
             WHERE t.id = ? AND t.status = 'successful'",
            [$transactionId]
        );

        if ($transaction === null) {
            throw new \RuntimeException("Completed transaction not found: {$transactionId}");
        }

        $invoiceNumber = $transaction['invoice_number'] ?? self::generateInvoiceNumber();
        $date = date('F j, Y', strtotime($transaction['created_at']));
        $dueDate = 'Immediate';

        $items = self::buildInvoiceItems($transaction);
        $subtotal = (float) $transaction['amount'];
        $tax = 0; // Add tax calculation logic if needed
        $total = $subtotal + $tax;

        $html = self::renderInvoice(
            invoiceNumber: $invoiceNumber,
            date: $date,
            dueDate: $dueDate,
            customerName: $transaction['full_name'],
            customerEmail: $transaction['email'],
            customerCompany: $transaction['company_name'] ?? '',
            customerAddress: $transaction['address'] ?? '',
            items: $items,
            subtotal: $subtotal,
            tax: $tax,
            total: $total,
            currency: $transaction['currency'],
            paymentMethod: ucfirst($transaction['gateway']),
            transactionRef: $transaction['reference']
        );

        $filePath = self::saveInvoice($invoiceNumber, $html);

        self::logInvoice($transactionId, $invoiceNumber, $filePath);

        return $filePath;
    }

    /**
     * Build line items from transaction data.
     *
     * @param array $transaction Transaction row from DB
     * @return array<int, array{description: string, quantity: int, unit_price: float, total: float}>
     */
    private static function buildInvoiceItems(array $transaction): array
    {
        $typeLabels = [
            'subscription' => 'Platform Subscription',
            'campaign'     => 'Campaign Service',
            'ai_credits'   => 'AI Credit Package',
            'wallet_fund'  => 'Wallet Funding',
        ];

        $description = $typeLabels[$transaction['type']] ?? ucfirst(str_replace('_', ' ', $transaction['type']));

        $metadata = json_decode($transaction['metadata'] ?? '{}', true);
        if (!empty($metadata['description'])) {
            $description = $metadata['description'];
        }

        return [
            [
                'description' => $description,
                'quantity'    => 1,
                'unit_price'  => (float) $transaction['amount'],
                'total'       => (float) $transaction['amount'],
            ],
        ];
    }

    /**
     * Render the full HTML invoice.
     *
     * @param string $invoiceNumber   Unique invoice number
     * @param string $date            Invoice date
     * @param string $dueDate         Due date
     * @param string $customerName    Customer full name
     * @param string $customerEmail   Customer email
     * @param string $customerCompany Customer company name
     * @param string $customerAddress Customer address
     * @param array  $items           Invoice line items
     * @param float  $subtotal        Subtotal amount
     * @param float  $tax             Tax amount
     * @param float  $total           Total amount
     * @param string $currency        Currency code
     * @param string $paymentMethod   Payment method/gateway
     * @param string $transactionRef  Transaction reference
     * @return string Complete HTML document
     */
    private static function renderInvoice(
        string $invoiceNumber,
        string $date,
        string $dueDate,
        string $customerName,
        string $customerEmail,
        string $customerCompany,
        string $customerAddress,
        array $items,
        float $subtotal,
        float $tax,
        float $total,
        string $currency,
        string $paymentMethod,
        string $transactionRef
    ): string {
        $accent = self::BRAND_PRIMARY;
        $dark = self::BRAND_DARK;
        $darkAlt = self::BRAND_DARK_ALT;
        $text = self::BRAND_TEXT;
        $muted = self::BRAND_MUTED;
        $company = self::COMPANY_NAME;

        $currencySymbol = self::getCurrencySymbol($currency);

        $itemsHtml = '';
        foreach ($items as $index => $item) {
            $bgColor = $index % 2 === 0 ? '#0d1117' : $darkAlt;
            $itemsHtml .= '
                <tr style="background-color:' . $bgColor . ';">
                    <td style="padding:12px 16px;color:' . $text . ';border-bottom:1px solid #333;">' . htmlspecialchars($item['description']) . '</td>
                    <td style="padding:12px 16px;color:' . $text . ';border-bottom:1px solid #333;text-align:center;">' . $item['quantity'] . '</td>
                    <td style="padding:12px 16px;color:' . $text . ';border-bottom:1px solid #333;text-align:right;">' . $currencySymbol . number_format($item['unit_price'], 2) . '</td>
                    <td style="padding:12px 16px;color:' . $accent . ';border-bottom:1px solid #333;text-align:right;font-weight:bold;">' . $currencySymbol . number_format($item['total'], 2) . '</td>
                </tr>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice ' . htmlspecialchars($invoiceNumber) . ' - ' . $company . '</title>
</head>
<body style="margin:0;padding:0;background-color:' . $dark . ';font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;">
    <div style="max-width:800px;margin:0 auto;padding:40px 20px;">
        <!-- Invoice Container -->
        <div style="background-color:' . $darkAlt . ';border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.3);">

            <!-- Header -->
            <div style="padding:40px;display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid ' . $accent . ';">
                <div>
                    <h1 style="margin:0;font-size:28px;color:' . $accent . ';letter-spacing:1px;">' . $company . '</h1>
                    <p style="margin:8px 0 0 0;color:' . $muted . ';font-size:13px;">' . self::COMPANY_ADDRESS_1 . '</p>
                    <p style="margin:4px 0 0 0;color:' . $muted . ';font-size:13px;">' . self::COMPANY_ADDRESS_2 . '</p>
                    <p style="margin:4px 0 0 0;color:' . $muted . ';font-size:13px;">' . self::COMPANY_EMAIL . '</p>
                </div>
                <div style="text-align:right;">
                    <h2 style="margin:0;font-size:32px;color:' . $text . ';font-weight:300;">INVOICE</h2>
                    <p style="margin:12px 0 4px 0;color:' . $accent . ';font-size:16px;font-weight:bold;">' . htmlspecialchars($invoiceNumber) . '</p>
                    <p style="margin:4px 0 0 0;color:' . $muted . ';font-size:13px;">Date: ' . $date . '</p>
                    <p style="margin:4px 0 0 0;color:' . $muted . ';font-size:13px;">Due: ' . $dueDate . '</p>
                </div>
            </div>

            <!-- Bill To / Payment Info -->
            <div style="padding:30px 40px;display:flex;justify-content:space-between;border-bottom:1px solid #333;">
                <div>
                    <p style="margin:0 0 8px 0;color:' . $muted . ';font-size:12px;text-transform:uppercase;letter-spacing:1px;">Bill To</p>
                    <p style="margin:0;color:' . $text . ';font-weight:bold;">' . htmlspecialchars($customerName) . '</p>
                    ' . ($customerCompany ? '<p style="margin:4px 0 0 0;color:' . $text . ';">' . htmlspecialchars($customerCompany) . '</p>' : '') . '
                    <p style="margin:4px 0 0 0;color:' . $muted . ';font-size:13px;">' . htmlspecialchars($customerEmail) . '</p>
                    ' . ($customerAddress ? '<p style="margin:4px 0 0 0;color:' . $muted . ';font-size:13px;">' . htmlspecialchars($customerAddress) . '</p>' : '') . '
                </div>
                <div style="text-align:right;">
                    <p style="margin:0 0 8px 0;color:' . $muted . ';font-size:12px;text-transform:uppercase;letter-spacing:1px;">Payment Info</p>
                    <p style="margin:0;color:' . $text . ';font-size:13px;">Method: ' . htmlspecialchars($paymentMethod) . '</p>
                    <p style="margin:4px 0 0 0;color:' . $muted . ';font-size:13px;">Ref: ' . htmlspecialchars($transactionRef) . '</p>
                </div>
            </div>

            <!-- Items Table -->
            <div style="padding:30px 40px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #333;border-radius:8px;overflow:hidden;">
                    <thead>
                        <tr style="background-color:' . $dark . ';">
                            <th style="padding:14px 16px;color:' . $accent . ';text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid ' . $accent . ';">Description</th>
                            <th style="padding:14px 16px;color:' . $accent . ';text-align:center;font-size:12px;text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid ' . $accent . ';">Qty</th>
                            <th style="padding:14px 16px;color:' . $accent . ';text-align:right;font-size:12px;text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid ' . $accent . ';">Unit Price</th>
                            <th style="padding:14px 16px;color:' . $accent . ';text-align:right;font-size:12px;text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid ' . $accent . ';">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $itemsHtml . '
                    </tbody>
                </table>

                <!-- Totals -->
                <div style="margin-top:20px;text-align:right;">
                    <table cellpadding="0" cellspacing="0" style="display:inline-block;min-width:300px;">
                        <tr>
                            <td style="padding:8px 16px;color:' . $muted . ';font-size:14px;">Subtotal:</td>
                            <td style="padding:8px 16px;color:' . $text . ';text-align:right;font-size:14px;">' . $currencySymbol . number_format($subtotal, 2) . '</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 16px;color:' . $muted . ';font-size:14px;">Tax:</td>
                            <td style="padding:8px 16px;color:' . $text . ';text-align:right;font-size:14px;">' . $currencySymbol . number_format($tax, 2) . '</td>
                        </tr>
                        <tr style="border-top:2px solid ' . $accent . ';">
                            <td style="padding:14px 16px;color:' . $text . ';font-size:18px;font-weight:bold;">Total:</td>
                            <td style="padding:14px 16px;color:' . $accent . ';text-align:right;font-size:22px;font-weight:bold;">' . $currencySymbol . number_format($total, 2) . ' ' . strtoupper($currency) . '</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div style="padding:30px 40px;background-color:' . $dark . ';border-top:1px solid #333;text-align:center;">
                <p style="margin:0 0 8px 0;color:' . $accent . ';font-size:14px;font-weight:bold;">Thank you for your business!</p>
                <p style="margin:0;color:' . $muted . ';font-size:12px;">
                    &copy; ' . date('Y') . ' ' . $company . '. All rights reserved.<br>
                    <a href="' . self::COMPANY_URL . '" style="color:' . $accent . ';text-decoration:none;">' . self::COMPANY_URL . '</a> |
                    <a href="mailto:' . self::COMPANY_EMAIL . '" style="color:' . $accent . ';text-decoration:none;">' . self::COMPANY_EMAIL . '</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Save the invoice HTML to a file.
     *
     * @param string $invoiceNumber Invoice number (used as filename)
     * @param string $html          HTML content
     * @return string Relative file path
     * @throws \RuntimeException If directory creation or write fails
     */
    private static function saveInvoice(string $invoiceNumber, string $html): string
    {
        $dir = __DIR__ . '/../uploads/invoices';

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Failed to create invoice directory: {$dir}");
            }
        }

        $filename = $invoiceNumber . '.html';
        $filePath = $dir . '/' . $filename;

        if (file_put_contents($filePath, $html) === false) {
            throw new \RuntimeException("Failed to write invoice file: {$filePath}");
        }

        return 'uploads/invoices/' . $filename;
    }

    /**
     * Log invoice generation to the database.
     *
     * @param int    $transactionId Transaction ID
     * @param string $invoiceNumber Invoice number
     * @param string $filePath      Saved file path
     * @return void
     */
    private static function logInvoice(int $transactionId, string $invoiceNumber, string $filePath): void
    {
        try {
            db()->insert('invoices', [
                'transaction_id' => $transactionId,
                'invoice_number' => $invoiceNumber,
                'file_path'      => $filePath,
                'generated_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to log invoice: " . $e->getMessage());
        }
    }

    /**
     * Generate an invoice number if not provided.
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
     * Get the currency symbol for a given currency code.
     *
     * @param string $currency Three-letter currency code
     * @return string Currency symbol
     */
    private static function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'NGN' => '₦',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'GHS' => 'GH₵',
            'KES' => 'KSh',
            'ZAR' => 'R',
        ];

        return $symbols[strtoupper($currency)] ?? strtoupper($currency) . ' ';
    }
}
