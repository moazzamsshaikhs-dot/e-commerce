<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . '../index.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Payment ID is required.';
    redirect('index.php');
}

$payment_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Get payment details
    $stmt = $db->prepare("SELECT p.*, 
                                 u.full_name as customer_name,
                                 u.email as customer_email,
                                 u.phone as customer_phone,
                                 u.address as customer_address,
                                 o.order_number,
                                 o.total_amount as order_total,
                                 o.order_date
                          FROM payments p
                          LEFT JOIN users u ON p.user_id = u.id
                          LEFT JOIN orders o ON p.order_id = o.id
                          WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        die('Payment not found.');
    }
    
    // Get company settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    $settings_result = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_result as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Get refunds for this payment
    $stmt = $db->prepare("SELECT SUM(refund_amount) as total_refunded 
                          FROM refunds 
                          WHERE payment_id = ? AND status = 'completed'");
    $stmt->execute([$payment_id]);
    $total_refunded = $stmt->fetch()['total_refunded'] ?? 0;
    
    $net_amount = $payment['amount'] - $total_refunded;
    
} catch(PDOException $e) {
    die('Error loading receipt: ' . $e->getMessage());
}

// Set header for print
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $payment['transaction_id'] ?? $payment['id']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .container { max-width: 100% !important; padding: 0 !important; }
            .receipt-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .receipt-table th { border-top: none !important; }
            .total-row { font-size: 1.2em; font-weight: bold; }
            .receipt-watermark { opacity: 0.1; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 120px; color: #ccc; z-index: -1; }
        }
        @media screen {
            body { background-color: #f8f9fa; }
            .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); margin-top: 20px; margin-bottom: 20px; }
        }
        .receipt-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .receipt-table th { border-top: none !important; background-color: #f8f9fa; }
        .total-row { font-size: 1.2em; font-weight: bold; }
        .company-logo { max-height: 80px; }
        .receipt-watermark { opacity: 0.05; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 120px; color: #ccc; z-index: -1; pointer-events: none; }
        .payment-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="receipt-watermark"><?php echo $settings['site_name'] ?? 'RECEIPT'; ?></div>
    
    <div class="container">
        <!-- Print Controls -->
        <div class="no-print mb-4">
            <div class="d-flex justify-content-between">
                <a href="payment-details.php?id=<?php echo $payment_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Payment
                </a>
                <div>
                    <button onclick="window.print()" class="btn btn-primary me-2">
                        <i class="fas fa-print me-2"></i> Print Receipt
                    </button>
                    <button onclick="downloadPDF()" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Download PDF
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div class="row">
                <div class="col-md-6">
                    <?php if (isset($settings['site_logo']) && !empty($settings['site_logo'])): ?>
                    <img src="<?php echo SITE_URL; ?>uploads/<?php echo $settings['site_logo']; ?>" 
                         alt="Company Logo" class="company-logo mb-3">
                    <?php endif; ?>
                    <h1 class="h2 mb-0">PAYMENT RECEIPT</h1>
                    <p class="text-muted mb-0">Receipt #<?php echo $payment['transaction_id'] ?? 'REC-' . $payment['id']; ?></p>
                    <p class="text-muted mb-0">Date: <?php echo date('F d, Y'); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <h4 class="mb-0"><?php echo $settings['site_name'] ?? 'ShopEase Pro'; ?></h4>
                    <p class="text-muted mb-0"><?php echo $settings['site_address'] ?? '123 Business Street'; ?></p>
                    <p class="text-muted mb-0"><?php echo $settings['site_city'] ?? 'New York, NY 10001'; ?></p>
                    <p class="text-muted mb-0">Phone: <?php echo $settings['site_phone'] ?? '+1 (555) 123-4567'; ?></p>
                    <p class="text-muted mb-0">Email: <?php echo $settings['site_email'] ?? 'contact@shopease.com'; ?></p>
                    <p class="text-muted mb-0">Website: <?php echo $settings['site_url'] ?? 'www.shopease.com'; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Receipt Details -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Paid By:</h5>
                <p class="mb-1"><strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong></p>
                <p class="mb-1"><?php echo htmlspecialchars($payment['customer_email']); ?></p>
                <?php if ($payment['customer_phone']): ?>
                <p class="mb-1"><?php echo htmlspecialchars($payment['customer_phone']); ?></p>
                <?php endif; ?>
                <?php if ($payment['customer_address']): ?>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($payment['customer_address'])); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-end">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Receipt #:</strong></td>
                        <td>REC-<?php echo $payment['id']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Date:</strong></td>
                        <td><?php echo date('F d, Y', strtotime($payment['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Status:</strong></td>
                        <td><span class="badge bg-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($payment['status']); ?>
                        </span></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td><span class="badge bg-light text-dark">
                            <?php echo strtoupper($payment['payment_method']); ?>
                        </span></td>
                    </tr>
                    <?php if ($payment['transaction_id']): ?>
                    <tr>
                        <td><strong>Transaction ID:</strong></td>
                        <td class="font-monospace"><?php echo $payment['transaction_id']; ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Payment Summary -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Payment Summary</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td width="60%">Original Amount:</td>
                                        <td class="text-end">$<?php echo number_format($payment['amount'], 2); ?></td>
                                    </tr>
                                    <?php if ($total_refunded > 0): ?>
                                    <tr>
                                        <td>Refunds Issued:</td>
                                        <td class="text-end text-danger">-$<?php echo number_format($total_refunded, 2); ?></td>
                                    </tr>
                                    <tr class="table-active">
                                        <td><strong>Net Amount:</strong></td>
                                        <td class="text-end"><strong>$<?php echo number_format($net_amount, 2); ?></strong></td>
                                    </tr>
                                    <?php else: ?>
                                    <tr class="table-active">
                                        <td><strong>Amount Paid:</strong></td>
                                        <td class="text-end"><strong>$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-info">
                                    <h6>Payment Information</h6>
                                    <p class="mb-1"><strong>Currency:</strong> <?php echo $payment['currency']; ?></p>
                                    <p class="mb-1"><strong>Status:</strong> <?php echo ucfirst($payment['status']); ?></p>
                                    <p class="mb-1"><strong>Method:</strong> <?php echo strtoupper($payment['payment_method']); ?></p>
                                    <p class="mb-0"><strong>Date:</strong> <?php echo date('F d, Y h:i A', strtotime($payment['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Information -->
        <?php if ($payment['order_id']): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Order Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Order Number:</strong> <?php echo $payment['order_number']; ?></p>
                                <p class="mb-2"><strong>Order Date:</strong> <?php echo date('F d, Y', strtotime($payment['order_date'])); ?></p>
                                <p class="mb-0"><strong>Order Total:</strong> $<?php echo number_format($payment['order_total'], 2); ?></p>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info py-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    This payment is for order <?php echo $payment['order_number']; ?>.
                                    For order details, please contact customer support.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="mt-5 pt-4 border-top text-center">
            <div class="row">
                <div class="col-md-4">
                    <h6>Payment Terms</h6>
                    <p class="small text-muted mb-0">
                        This is an official receipt for payment received.<br>
                        Please keep this receipt for your records.
                    </p>
                </div>
                <div class="col-md-4">
                    <h6>Contact Information</h6>
                    <p class="small text-muted mb-0">
                        Email: <?php echo $settings['site_email'] ?? 'support@shopease.com'; ?><br>
                        Phone: <?php echo $settings['site_phone'] ?? '+1 (555) 123-4567'; ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <h6>Thank You!</h6>
                    <p class="small text-muted mb-0">
                        We appreciate your payment.<br>
                        Please contact us for any questions.
                    </p>
                </div>
            </div>
            <div class="mt-3">
                <p class="small text-muted mb-0">
                    This is a computer-generated receipt. No signature required.
                </p>
                <p class="small text-muted mb-0">
                    Generated on: <?php echo date('F d, Y h:i A'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Print Controls at Bottom -->
    <div class="no-print fixed-bottom bg-white border-top py-3">
        <div class="container">
            <div class="d-flex justify-content-center">
                <button onclick="window.print()" class="btn btn-primary me-3">
                    <i class="fas fa-print me-2"></i> Print Receipt
                </button>
                <button onclick="downloadPDF()" class="btn btn-success me-3">
                    <i class="fas fa-download me-2"></i> Download PDF
                </button>
                <button onclick="sendReceiptEmail()" class="btn btn-info">
                    <i class="fas fa-paper-plane me-2"></i> Email to Customer
                </button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // Auto-print option
    window.onload = function() {
        // Uncomment below line to auto-print on page load
        window.print();
    };
    
    // Download as PDF
    function downloadPDF() {
        // In production, you would use a PDF generation library
        // For now, just print the page
        window.print();
    }
    
    // Send receipt via email
    function sendReceiptEmail() {
        Swal.fire({
            title: 'Send Receipt',
            text: 'Send this receipt to customer email?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Send Email'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../ajax/resend-receipt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        payment_id: <?php echo $payment_id; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Sent!', data.message, 'success');
                    } else {
                        Swal.fire('Error!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error!', 'An error occurred.', 'error');
                });
            }
        });
    }
    </script>
</body>
</html>