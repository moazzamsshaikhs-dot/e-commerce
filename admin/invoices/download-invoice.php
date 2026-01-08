<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check permissions
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Invoice ID required.');
}

$invoice_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Get invoice details
    $stmt = $db->prepare("
        SELECT i.*, u.full_name, u.email, u.phone, u.address as customer_address 
        FROM invoices i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        die('Invoice not found.');
    }
    
    // Check permission
    if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_id'] != $invoice['user_id']) {
        die('Access denied.');
    }
    
    // Get invoice items
    $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();
    
    // Get settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'site_email', 'site_phone', 'site_address', 'tax_rate')");
    $settings_result = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_result as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Create PDF using TCPDF
    require_once '../includes/tcpdf/tcpdf.php';
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Invoice System');
    $pdf->SetAuthor($settings['site_name'] ?? 'Your Company');
    $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
    $pdf->SetSubject('Invoice');
    $pdf->SetKeywords('Invoice, Billing, Payment');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // HTML content
    $html = '
    <style>
        body { font-family: helvetica; font-size: 10pt; }
        .header { border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .company { font-size: 16pt; font-weight: bold; color: #333; }
        .title { font-size: 20pt; font-weight: bold; color: #000; margin-bottom: 5px; }
        .invoice-no { font-size: 12pt; color: #666; }
        .section { margin: 10px 0 5px 0; font-weight: bold; color: #333; }
        table.items { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table.items th { background-color: #f5f5f5; border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold; }
        table.items td { border: 1px solid #ddd; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total { font-weight: bold; background-color: #f8f9fa; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 8pt; color: #666; }
    </style>
    
    <div class="header">
        <table width="100%">
            <tr>
                <td width="60%">
                    <div class="company">' . htmlspecialchars($settings['site_name'] ?? 'Your Company') . '</div>
                    <div>' . htmlspecialchars($settings['site_address'] ?? '') . '</div>
                    <div>Phone: ' . htmlspecialchars($settings['site_phone'] ?? '') . '</div>
                    <div>Email: ' . htmlspecialchars($settings['site_email'] ?? '') . '</div>
                </td>
                <td width="40%" style="text-align: right;">
                    <div class="title">INVOICE</div>
                    <div class="invoice-no">Invoice #: ' . $invoice['invoice_number'] . '</div>
                    <div>Date: ' . date('F d, Y', strtotime($invoice['invoice_date'])) . '</div>
                    <div>Due Date: ' . date('F d, Y', strtotime($invoice['due_date'])) . '</div>
                </td>
            </tr>
        </table>
    </div>
    
    <div style="margin-bottom: 20px;">
        <table width="100%">
            <tr>
                <td width="50%">
                    <div class="section">Bill To:</div>
                    <div><strong>' . htmlspecialchars($invoice['full_name']) . '</strong></div>
                    <div>' . htmlspecialchars($invoice['email']) . '</div>
                    <div>' . htmlspecialchars($invoice['phone']) . '</div>
                    <div>' . nl2br(htmlspecialchars($invoice['customer_address'])) . '</div>
                </td>
                <td width="50%" style="text-align: right;">
                    <div class="section">Invoice Details:</div>
                    <div>Status: ' . ucfirst($invoice['status']) . '</div>
                    <div>Payment: ' . ucfirst($invoice['payment_status']) . '</div>
                    <div>Balance Due: $' . number_format($invoice['balance_due'], 2) . '</div>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="section">Invoice Items</div>
    <table class="items">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="55%">Description</th>
                <th width="10%">Qty</th>
                <th width="15%" class="text-right">Unit Price</th>
                <th width="15%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>';
    
    $counter = 1;
    foreach ($items as $item) {
        $html .= '
            <tr>
                <td>' . $counter++ . '</td>
                <td>' . htmlspecialchars($item['description']) . '</td>
                <td class="text-center">' . number_format($item['quantity'], 2) . '</td>
                <td class="text-right">$' . number_format($item['unit_price'], 2) . '</td>
                <td class="text-right">$' . number_format($item['subtotal'], 2) . '</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="4" class="text-right"><strong>Subtotal:</strong></td>
                <td class="text-right"><strong>$' . number_format($invoice['subtotal'], 2) . '</strong></td>
            </tr>
            <tr>
                <td colspan="4" class="text-right">Tax (' . $invoice['tax_rate'] . '%):</td>
                <td class="text-right">$' . number_format($invoice['tax_amount'], 2) . '</td>
            </tr>
            <tr class="total">
                <td colspan="4" class="text-right"><strong>Total:</strong></td>
                <td class="text-right"><strong>$' . number_format($invoice['total_amount'], 2) . '</strong></td>
            </tr>
            <tr>
                <td colspan="4" class="text-right">Amount Paid:</td>
                <td class="text-right">$' . number_format($invoice['amount_paid'], 2) . '</td>
            </tr>
            <tr class="total">
                <td colspan="4" class="text-right"><strong>Balance Due:</strong></td>
                <td class="text-right"><strong>$' . number_format($invoice['balance_due'], 2) . '</strong></td>
            </tr>
        </tfoot>
    </table>';
    
    if (!empty($invoice['notes'])) {
        $html .= '
        <div class="section">Notes</div>
        <div>' . nl2br(htmlspecialchars($invoice['notes'])) . '</div>';
    }
    
    $html .= '
    <div class="footer">
        <table width="100%">
            <tr>
                <td width="50%">
                    <div>Thank you for your business!</div>
                    <div>This is a computer-generated invoice.</div>
                </td>
                <td width="50%" style="text-align: right;">
                    <div>Generated on: ' . date('F d, Y h:i A') . '</div>
                    <div>Page 1 of 1</div>
                </td>
            </tr>
        </table>
    </div>';
    
    // Output HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output('Invoice_' . $invoice['invoice_number'] . '.pdf', 'D');
    
} catch(Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}