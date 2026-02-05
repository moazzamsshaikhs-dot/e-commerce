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

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $invoice['invoice_number']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .invoice-container { max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 30px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .company-info { flex: 1; }
        .invoice-info { text-align: right; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; }
        .total-row td { font-weight: bold; }
        .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; }
        @media print {
            .no-print { display: none; }
            .invoice-container { border: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">
                <h2><?php echo htmlspecialchars($invoice['company_name']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($invoice['company_address'])); ?></p>
                <p>Email: <?php echo $invoice['company_email']; ?></p>
                <p>Phone: <?php echo $invoice['company_phone']; ?></p>
                <?php if ($invoice['tax_id']): ?>
                    <p>Tax ID: <?php echo $invoice['tax_id']; ?></p>
                <?php endif; ?>
            </div>
            <div class="invoice-info">
                <h1>INVOICE</h1>
                <p><strong>Invoice #:</strong> <?php echo $invoice['invoice_number']; ?></p>
                <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?></p>
                <p><strong>Due Date:</strong> <?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></p>
                <p><strong>Status:</strong> 
                    <span class="badge bg-<?php echo $invoice['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                        <?php echo strtoupper($invoice['payment_status']); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <div class="row" style="display: flex; justify-content: space-between; margin-bottom: 30px;">
            <div style="flex: 1;">
                <h4>Bill To:</h4>
                <p><strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
                <p>Email: <?php echo $invoice['email']; ?></p>
                <p>Phone: <?php echo $invoice['phone']; ?></p>
            </div>
            <div style="flex: 1; text-align: right;">
                <h4>Payment Details:</h4>
                <p><strong>Total Amount:</strong> <?php echo $invoice['currency']; ?> <?php echo number_format($invoice['total_amount'], 2); ?></p>
                <p><strong>Amount Paid:</strong> <?php echo $invoice['currency']; ?> <?php echo number_format($invoice['amount_paid'], 2); ?></p>
                <p><strong>Balance Due:</strong> <?php echo $invoice['currency']; ?> <?php echo number_format($invoice['balance_due'], 2); ?></p>
                <?php if ($invoice['order_id']): ?>
                    <p><strong>Order #:</strong> <?php echo $invoice['order_id']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <table>
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
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                    <td><?php echo $invoice['currency']; ?> <?php echo number_format($item['unit_price'], 2); ?></td>
                    <td><?php echo $invoice['currency']; ?> <?php echo number_format($item['discount'], 2); ?></td>
                    <td><?php echo number_format($item['tax_rate'], 2); ?>%</td>
                    <td><?php echo $invoice['currency']; ?> <?php echo number_format($item['subtotal'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;"><strong>Subtotal:</strong></td>
                    <td><?php echo $invoice['currency']; ?> <?php echo number_format($invoice['subtotal'], 2); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;"><strong>Tax (<?php echo number_format($invoice['tax_rate'], 2); ?>%):</strong></td>
                    <td><?php echo $invoice['currency']; ?> <?php echo number_format($invoice['tax_amount'], 2); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;"><strong>Total:</strong></td>
                    <td><?php echo $invoice['currency']; ?> <?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;"><strong>Amount Paid:</strong></td>
                    <td><?php echo $invoice['currency']; ?> <?php echo number_format($invoice['amount_paid'], 2); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;"><strong>Balance Due:</strong></td>
                    <td><?php echo $invoice['currency']; ?> <?php echo number_format($invoice['balance_due'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        
        <?php if ($invoice['notes']): ?>
        <div style="margin-top: 30px;">
            <h4>Notes:</h4>
            <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($invoice['terms']): ?>
        <div style="margin-top: 30px;">
            <h4>Terms & Conditions:</h4>
            <p><?php echo nl2br(htmlspecialchars($invoice['terms'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Thank you for your business!</p>
            <p>If you have any questions about this invoice, please contact:</p>
            <p><?php echo $invoice['company_email']; ?> | <?php echo $invoice['company_phone']; ?></p>
            <p><?php echo $invoice['website']; ?></p>
            <p>Invoice generated on: <?php echo date('F d, Y H:i:s'); ?></p>
        </div>
        
        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</body>
</html>