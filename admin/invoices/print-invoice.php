<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

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
    
    // Get payments
    $stmt = $db->prepare("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll();
    
    // Get settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'site_email', 'site_phone', 'site_address')");
    $settings_result = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_result as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
} catch(PDOException $e) {
    die('Error: ' . $e->getMessage());
}

// Set header for print
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $invoice['invoice_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; padding: 0; margin: 0; background: white !important; }
            .container { max-width: 100% !important; padding: 10px !important; margin: 0 !important; }
            .invoice-header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
            .table th { border-top: none !important; background-color: #f8f9fa !important; }
            .total-row { font-size: 1em; font-weight: bold; }
            .page-break { page-break-after: always; }
            .signature-area { margin-top: 50px; }
        }
        @media screen {
            body { background-color: #f8f9fa; padding: 20px; }
            .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); max-width: 210mm; margin: 0 auto; }
        }
        body { font-family: Arial, sans-serif; }
        .invoice-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .table th { border-top: none !important; background-color: #f8f9fa; }
        .total-row { font-size: 1.1em; font-weight: bold; }
        .company-name { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .invoice-title { font-size: 28px; font-weight: bold; color: #000; margin-bottom: 5px; }
        .invoice-number { font-size: 14px; color: #666; }
        .watermark { 
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(0,0,0,0.1);
            z-index: -1;
            pointer-events: none;
        }
        .signature-line { 
            border-top: 1px solid #000; 
            margin-top: 40px; 
            padding-top: 10px;
            width: 200px;
        }
        .footer-note {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="watermark"><?php echo $invoice['payment_status'] == 'paid' ? 'PAID' : 'INVOICE'; ?></div>
    
    <div class="container">
        <!-- Print Controls -->
        <div class="no-print mb-4 text-center">
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="fas fa-print me-2"></i> Print Invoice
            </button>
            <a href="view-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Back to View
            </a>
            <a href="download-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-success">
                <i class="fas fa-download me-2"></i> Download PDF
            </a>
        </div>
        
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="row">
                <div class="col-md-6">
                    <div class="company-name"><?php echo $settings['site_name'] ?? 'Your Company'; ?></div>
                    <p class="mb-1"><?php echo $settings['site_address'] ?? ''; ?></p>
                    <p class="mb-1">Phone: <?php echo $settings['site_phone'] ?? ''; ?></p>
                    <p class="mb-1">Email: <?php echo $settings['site_email'] ?? ''; ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-number">Invoice #: <?php echo $invoice['invoice_number']; ?></div>
                    <div>Date: <?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?></div>
                    <div>Due Date: <?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></div>
                    <div class="mt-2">
                        <span class="badge bg-<?php 
                            echo $invoice['payment_status'] == 'paid' ? 'success' : 
                            ($invoice['payment_status'] == 'partial' ? 'warning' : 'danger'); 
                        ?>">
                            <?php echo strtoupper($invoice['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bill To / Ship To -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Bill To:</h5>
                <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong></p>
                <p class="mb-1"><?php echo htmlspecialchars($invoice['email']); ?></p>
                <p class="mb-1"><?php echo htmlspecialchars($invoice['phone']); ?></p>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
            </div>
            <div class="col-md-6 text-end">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Invoice #:</strong></td>
                        <td><?php echo $invoice['invoice_number']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Invoice Date:</strong></td>
                        <td><?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Due Date:</strong></td>
                        <td><?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></td>
                    </tr>
                    <?php if ($invoice['order_id']): ?>
                    <tr>
                        <td><strong>Order #:</strong></td>
                        <td><?php echo $invoice['order_id']; ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; ?>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-center"><?php echo number_format($item['quantity'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Add empty rows for better printing -->
                    <?php for($i = count($items); $i < 8; $i++): ?>
                    <tr style="height: 35px;">
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end"><strong>$<?php echo number_format($invoice['subtotal'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end">Tax (<?php echo $invoice['tax_rate']; ?>%):</td>
                        <td class="text-end">$<?php echo number_format($invoice['tax_amount'], 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                        <td class="text-end"><strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end">Amount Paid:</td>
                        <td class="text-end">$<?php echo number_format($invoice['amount_paid'], 2); ?></td>
                    </tr>
                    <tr class="table-active total-row">
                        <td colspan="4" class="text-end"><strong>Balance Due:</strong></td>
                        <td class="text-end"><strong>$<?php echo number_format($invoice['balance_due'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="mb-4">
            <h5>Payment History</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Transaction ID</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                            <td><?php echo $payment['transaction_id'] ?: 'N/A'; ?></td>
                            <td class="text-end">$<?php echo number_format($payment['amount'], 2); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notes -->
        <?php if (!empty($invoice['notes'])): ?>
        <div class="mb-4">
            <h5>Notes</h5>
            <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Terms & Conditions -->
        <div class="mb-4">
            <h5>Terms & Conditions</h5>
            <p><?php echo nl2br($invoice['terms'] ?? 'Payment due within 30 days. Late payments subject to 1.5% monthly interest.'); ?></p>
        </div>
        
        <!-- Signature Area -->
        <div class="row signature-area">
            <div class="col-md-6">
                <div class="signature-line"></div>
                <p class="mt-1">Customer Signature</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="signature-line ms-auto"></div>
                <p class="mt-1">Authorized Signature</p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer-note text-center">
            <p class="mb-1">Thank you for your business!</p>
            <p class="mb-0">This is a computer-generated invoice. No signature required for electronic invoices.</p>
        </div>
    </div>
    
    <!-- Print Controls at Bottom -->
    <div class="no-print fixed-bottom bg-white border-top py-3">
        <div class="container text-center">
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="fas fa-print me-2"></i> Print Invoice
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary">
                <i class="fas fa-times me-2"></i> Close Window
            </button>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-print option
    window.onload = function() {
        // Uncomment below line to auto-print on page load
        // window.print();
    };
    
    // Close window after printing
    window.onafterprint = function() {
        // Optionally close window after printing
        // window.close();
    };
    </script>
</body>
</html>