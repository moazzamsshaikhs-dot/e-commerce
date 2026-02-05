<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if (!isset($_GET['invoice_id']) || empty($_GET['invoice_id'])) {
    die('Invoice not found');
}

$invoice_id = (int)$_GET['invoice_id'];
$db = getDB();

// Get invoice details
$stmt = $db->prepare("
    SELECT i.*, u.full_name, u.email, u.address, u.phone,
           vs.company_name, vs.address as company_address, vs.email as company_email,
           vs.phone as company_phone, vs.website, vs.logo, vs.tax_id, vs.currency
    FROM invoices i
    JOIN users u ON i.user_id = u.id
    LEFT JOIN invoice_settings vs ON 1=1
    WHERE i.id = ?
");

$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found');
}

// Get invoice items
$stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll();

// Include TCPDF library
require_once '../../vendor/tcpdf/tcpdf.php';

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($invoice['company_name']);
$pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
$pdf->SetSubject('Invoice');
$pdf->SetKeywords('Invoice, Payment, Receipt');

// Set default header data
$pdf->SetHeaderData('', 0, $invoice['company_name'], 'Invoice #' . $invoice['invoice_number']);

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set margins
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// HTML content
$html = '
<style>
    h1 { color: #2c3e50; }
    .invoice-header { border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
    .invoice-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
    .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    .table th { background-color: #3498db; color: white; padding: 10px; text-align: left; }
    .table td { padding: 10px; border-bottom: 1px solid #ddd; }
    .total-row { font-weight: bold; background-color: #f8f9fa; }
    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
</style>

<div class="invoice-header">
    <h1>INVOICE</h1>
</div>

<div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
    <div style="width: 48%;">
        <h3>From:</h3>
        <p><strong>' . htmlspecialchars($invoice['company_name']) . '</strong></p>
        <p>' . nl2br(htmlspecialchars($invoice['company_address'])) . '</p>
        <p>Email: ' . $invoice['company_email'] . '</p>
        <p>Phone: ' . $invoice['company_phone'] . '</p>
        ' . ($invoice['tax_id'] ? '<p>Tax ID: ' . $invoice['tax_id'] . '</p>' : '') . '
    </div>
    
    <div style="width: 48%;" class="invoice-info">
        <h3>Invoice Details</h3>
        <p><strong>Invoice #:</strong> ' . $invoice['invoice_number'] . '</p>
        <p><strong>Date:</strong> ' . date('F d, Y', strtotime($invoice['invoice_date'])) . '</p>
        <p><strong>Due Date:</strong> ' . date('F d, Y', strtotime($invoice['due_date'])) . '</p>
        <p><strong>Status:</strong> ' . strtoupper($invoice['payment_status']) . '</p>
        <p><strong>Currency:</strong> ' . $invoice['currency'] . '</p>
    </div>
</div>

<div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
    <div style="width: 48%;">
        <h3>Bill To:</h3>
        <p><strong>' . htmlspecialchars($invoice['full_name']) . '</strong></p>
        <p>' . nl2br(htmlspecialchars($invoice['address'])) . '</p>
        <p>Email: ' . $invoice['email'] . '</p>
        <p>Phone: ' . $invoice['phone'] . '</p>
    </div>
    
    <div style="width: 48%;">
        <h3>Payment Summary</h3>
        <p><strong>Total Amount:</strong> ' . $invoice['currency'] . ' ' . number_format($invoice['total_amount'], 2) . '</p>
        <p><strong>Amount Paid:</strong> ' . $invoice['currency'] . ' ' . number_format($invoice['amount_paid'], 2) . '</p>
        <p><strong>Balance Due:</strong> ' . $invoice['currency'] . ' ' . number_format($invoice['balance_due'], 2) . '</p>
        ' . ($invoice['order_id'] ? '<p><strong>Order #:</strong> ' . $invoice['order_id'] . '</p>' : '') . '
    </div>
</div>

<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Description</th>
            <th>Quantity</th>
            <th>Unit Price</th>
            <th>Discount</th>
            <th>Tax</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>';

foreach ($items as $index => $item) {
    $html .= '
        <tr>
            <td>' . ($index + 1) . '</td>
            <td>' . htmlspecialchars($item['description']) . '</td>
            <td>' . number_format($item['quantity'], 2) . '</td>
            <td>' . $invoice['currency'] . ' ' . number_format($item['unit_price'], 2) . '</td>
            <td>' . $invoice['currency'] . ' ' . number_format($item['discount'], 2) . '</td>
            <td>' . number_format($item['tax_rate'], 2) . '%</td>
            <td>' . $invoice['currency'] . ' ' . number_format($item['subtotal'], 2) . '</td>
        </tr>';
}

$html .= '
        <tr class="total-row">
            <td colspan="6" style="text-align: right;">Subtotal:</td>
            <td>' . $invoice['currency'] . ' ' . number_format($invoice['subtotal'], 2) . '</td>
        </tr>
        <tr class="total-row">
            <td colspan="6" style="text-align: right;">Tax (' . number_format($invoice['tax_rate'], 2) . '%):</td>
            <td>' . $invoice['currency'] . ' ' . number_format($invoice['tax_amount'], 2) . '</td>
        </tr>
        <tr class="total-row">
            <td colspan="6" style="text-align: right;">Total:</td>
            <td>' . $invoice['currency'] . ' ' . number_format($invoice['total_amount'], 2) . '</td>
        </tr>
        <tr class="total-row">
            <td colspan="6" style="text-align: right;">Amount Paid:</td>
            <td>' . $invoice['currency'] . ' ' . number_format($invoice['amount_paid'], 2) . '</td>
        </tr>
        <tr class="total-row">
            <td colspan="6" style="text-align: right;">Balance Due:</td>
            <td>' . $invoice['currency'] . ' ' . number_format($invoice['balance_due'], 2) . '</td>
        </tr>
    </tbody>
</table>';

if ($invoice['notes']) {
    $html .= '
    <div style="margin-top: 30px;">
        <h3>Notes:</h3>
        <p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>
    </div>';
}

if ($invoice['terms']) {
    $html .= '
    <div style="margin-top: 20px;">
        <h3>Terms & Conditions:</h3>
        <p>' . nl2br(htmlspecialchars($invoice['terms'])) . '</p>
    </div>';
}

$html .= '
<div class="footer">
    <p>Thank you for your business!</p>
    <p>If you have any questions about this invoice, please contact:</p>
    <p>' . $invoice['company_email'] . ' | ' . $invoice['company_phone'] . '</p>
    <p>' . $invoice['website'] . '</p>
    <p>Invoice generated on: ' . date('F d, Y H:i:s') . '</p>
</div>';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('Invoice_' . $invoice['invoice_number'] . '.pdf', 'D');