<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Order ID is required.';
    redirect(SITE_URL . 'orders.php');
}

$order_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Get order details
    $stmt = $db->prepare("SELECT o.*, 
                                 u.full_name,
                                 u.email,
                                 u.phone,
                                 u.address as user_address
                          FROM orders o
                          LEFT JOIN users u ON o.user_id = u.id
                          WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        die('Order not found.');
    }
    
    // Get order items
    $stmt = $db->prepare("SELECT oi.*, p.name, p.category 
                          FROM order_items oi
                          LEFT JOIN products p ON oi.product_id = p.id
                          WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    // Get company settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    $settings_result = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_result as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Calculate totals
    $subtotal = 0;
    $total_items = 0;
    foreach ($order_items as $item) {
        $subtotal += $item['subtotal'];
        $total_items += $item['quantity'];
    }
    
    // Calculate shipping and tax
    $shipping_cost = 5.99; // Default
    $tax_rate = isset($settings['tax_rate']) ? (float)$settings['tax_rate'] : 10;
    $tax_amount = ($subtotal * $tax_rate) / 100;
    $total_amount = $subtotal + $shipping_cost + $tax_amount;
    
} catch(PDOException $e) {
    die('Error loading invoice: ' . $e->getMessage());
}

// Set header for print
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['order_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .container { max-width: 100% !important; padding: 0 !important; }
            .invoice-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .invoice-table th { border-top: none !important; }
            .total-row { font-size: 1.2em; font-weight: bold; }
            .invoice-watermark { opacity: 0.1; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 120px; color: #ccc; z-index: -1; }
        }
        @media screen {
            body { background-color: #f8f9fa; }
            .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); margin-top: 20px; margin-bottom: 20px; }
        }
        .invoice-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .invoice-table th { border-top: none !important; background-color: #f8f9fa; }
        .total-row { font-size: 1.2em; font-weight: bold; }
        .company-logo { max-height: 80px; }
        .invoice-watermark { opacity: 0.05; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 120px; color: #ccc; z-index: -1; pointer-events: none; }
        .payment-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="invoice-watermark"><?php echo $settings['site_name'] ?? 'INVOICE'; ?></div>
    
    <div class="container">
        <!-- Print Controls -->
        <div class="no-print mb-4">
            <div class="d-flex justify-content-between">
                <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Order
                </a>
                <div>
                    <button onclick="window.print()" class="btn btn-primary me-2">
                        <i class="fas fa-print me-2"></i> Print Invoice
                    </button>
                    <button onclick="downloadPDF()" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Download PDF
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="row">
                <div class="col-md-6">
                    <?php if (isset($settings['site_logo']) && !empty($settings['site_logo'])): ?>
                    <img src="<?php echo SITE_URL; ?>uploads/<?php echo $settings['site_logo']; ?>" 
                         alt="Company Logo" class="company-logo mb-3">
                    <?php endif; ?>
                    <h1 class="h2 mb-0">INVOICE</h1>
                    <p class="text-muted mb-0">Order #<?php echo $order['order_number']; ?></p>
                    <p class="text-muted mb-0">Invoice Date: <?php echo date('F d, Y'); ?></p>
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
        
        <!-- Invoice Details -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Bill To:</h5>
                <p class="mb-1"><strong><?php echo htmlspecialchars($order['full_name']); ?></strong></p>
                <p class="mb-1"><?php echo htmlspecialchars($order['email']); ?></p>
                <p class="mb-1"><?php echo htmlspecialchars($order['phone']); ?></p>
                <?php if (!empty($order['billing_address'])): ?>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['billing_address'])); ?></p>
                <?php else: ?>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-end">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Invoice #:</strong></td>
                        <td>INV-<?php echo $order['order_number']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Order Date:</strong></td>
                        <td><?php echo date('F d, Y', strtotime($order['order_date'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Due Date:</strong></td>
                        <td><?php echo date('F d, Y', strtotime('+30 days', strtotime($order['order_date']))); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Order Status:</strong></td>
                        <td><span class="badge bg-<?php echo $order['status'] == 'delivered' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Status:</strong></td>
                        <td><span class="badge bg-<?php echo $order['payment_status'] == 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Order Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; ?>
                    <?php foreach($order_items as $item): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <div class="text-muted small">Category: <?php echo $item['category']; ?></div>
                            <div class="text-muted small">Product ID: <?php echo $item['product_id']; ?></div>
                        </td>
                        <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary Section -->
        <div class="row">
            <div class="col-md-8">
                <?php if (!empty($order['customer_notes'])): ?>
                <div class="card border">
                    <div class="card-body">
                        <h6 class="card-title">Customer Notes</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="payment-info">
                    <h6>Payment Information</h6>
                    <p class="mb-1"><strong>Payment Method:</strong> <?php echo strtoupper($order['payment_method']); ?></p>
                    <p class="mb-1"><strong>Payment Status:</strong> <?php echo ucfirst($order['payment_status']); ?></p>
                    <?php if ($order['payment_status'] == 'completed'): ?>
                    <p class="mb-0"><strong>Payment Date:</strong> <?php echo date('F d, Y', strtotime($order['order_date'])); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4">
                    <h6>Terms & Conditions</h6>
                    <p class="small text-muted">
                        1. Payment is due within 30 days of invoice date.<br>
                        2. Late payments are subject to a 1.5% monthly interest charge.<br>
                        3. All sales are final unless otherwise specified.<br>
                        4. Shipping costs are non-refundable.<br>
                        5. For any questions regarding this invoice, please contact our support team.
                    </p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Amount Due</h5>
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Subtotal:</td>
                                <td class="text-end">$<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Shipping:</td>
                                <td class="text-end">$<?php echo number_format($shipping_cost, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Tax (<?php echo $tax_rate; ?>%):</td>
                                <td class="text-end">$<?php echo number_format($tax_amount, 2); ?></td>
                            </tr>
                            <tr class="total-row">
                                <td><strong>Total:</strong></td>
                                <td class="text-end"><strong>$<?php echo number_format($total_amount, 2); ?></strong></td>
                            </tr>
                        </table>
                        
                        <?php if ($order['payment_status'] == 'pending'): ?>
                        <div class="alert alert-warning mt-3 mb-0 py-2">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Payment Pending</strong>
                        </div>
                        <?php elseif ($order['payment_status'] == 'completed'): ?>
                        <div class="alert alert-success mt-3 mb-0 py-2">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Payment Received</strong>
                            <div class="small">Thank you for your payment!</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Shipping Information -->
                <div class="card border mt-3">
                    <div class="card-body">
                        <h6 class="card-title mb-2">Shipping Information</h6>
                        <p class="mb-1 small">
                            <strong>Method:</strong> <?php echo ucfirst($order['shipping_method']); ?>
                        </p>
                        <p class="mb-0 small">
                            <strong>Address:</strong><br>
                            <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                        </p>
                        <?php if (!empty($order['tracking_number'])): ?>
                        <p class="mb-0 small mt-2">
                            <strong>Tracking:</strong> <?php echo $order['tracking_number']; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-5 pt-4 border-top text-center">
            <div class="row">
                <div class="col-md-4">
                    <h6>Contact Information</h6>
                    <p class="small text-muted mb-0">
                        Email: <?php echo $settings['site_email'] ?? 'support@shopease.com'; ?><br>
                        Phone: <?php echo $settings['site_phone'] ?? '+1 (555) 123-4567'; ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <h6>Business Hours</h6>
                    <p class="small text-muted mb-0">
                        Monday - Friday: 9:00 AM - 6:00 PM<br>
                        Saturday: 10:00 AM - 4:00 PM<br>
                        Sunday: Closed
                    </p>
                </div>
                <div class="col-md-4">
                    <h6>Thank You!</h6>
                    <p class="small text-muted mb-0">
                        We appreciate your business.<br>
                        Please contact us for any questions.
                    </p>
                </div>
            </div>
            <div class="mt-3">
                <p class="small text-muted mb-0">
                    This is a computer-generated invoice. No signature required.
                </p>
            </div>
        </div>
    </div>
    
    <!-- Print Controls at Bottom -->
    <div class="no-print fixed-bottom bg-white border-top py-3">
        <div class="container">
            <div class="d-flex justify-content-center">
                <button onclick="window.print()" class="btn btn-primary me-3">
                    <i class="fas fa-print me-2"></i> Print Invoice
                </button>
                <button onclick="downloadPDF()" class="btn btn-success me-3">
                    <i class="fas fa-download me-2"></i> Download PDF
                </button>
                <button onclick="sendInvoiceEmail()" class="btn btn-info">
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
        // window.print();
    };
    
    // Download as PDF
    function downloadPDF() {
        window.open('generate-pdf.php?order_id=<?php echo $order_id; ?>', '_blank');
    }
    
    // Send invoice via email
    function sendInvoiceEmail() {
        Swal.fire({
            title: 'Send Invoice',
            text: 'Send this invoice to customer email?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Send Email'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../ajax/send-invoice-email.php?order_id=<?php echo $order_id; ?>')
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
    
    // Print specific section
    function printSection(elementId) {
        const printContent = document.getElementById(elementId).innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = printContent;
        window.print();
        document.body.innerHTML = originalContent;
        location.reload();
    }
    </script>
</body>
</html>