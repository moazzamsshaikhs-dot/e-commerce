<?php
// Start output buffering
ob_start();

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login to view order details';
    redirect(SITE_URL . 'user/login.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order details
$stmt = $db->prepare("
    SELECT o.*, u.full_name, u.email, u.phone 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = 'Order not found';
    redirect('my-orders.php');
}

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, p.name, p.image, p.vendor_id,
           v.username as vendor_name, v.store_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN vendor_settings v ON p.vendor_id = v.vendor_id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment proof if exists (for bank transfers)
$payment_proof = null;
if ($order['payment_method'] === 'bank') {
    $stmt = $db->prepare("
        SELECT * FROM payment_proofs 
        WHERE order_id = ? 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $payment_proof = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get order status history
$stmt = $db->prepare("
    SELECT * FROM order_status_history 
    WHERE order_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$order_id]);
$status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Order Confirmation - #' . $order['order_number'];
require_once '../../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>user/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="my-orders.php">My Orders</a></li>
            <li class="breadcrumb-item active">Order #<?php echo $order['order_number']; ?></li>
        </ol>
    </nav>

    <!-- Success Message -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Order Status Alert -->
    <?php
    $status_colors = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger'
    ];
    $status_color = $status_colors[$order['status']] ?? 'secondary';
    
    $payment_status_colors = [
        'pending' => 'warning',
        'completed' => 'success',
        'failed' => 'danger',
        'refunded' => 'info'
    ];
    $payment_color = $payment_status_colors[$order['payment_status']] ?? 'secondary';
    ?>

    <div class="alert alert-<?php echo $status_color; ?>">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-info-circle me-2"></i>
                <strong>Order Status:</strong> <?php echo ucfirst($order['status']); ?>
                | <strong>Payment Status:</strong> 
                <span class="badge bg-<?php echo $payment_color; ?>">
                    <?php echo ucfirst($order['payment_status']); ?>
                </span>
            </div>
            <div>
                <strong>Order Date:</strong> <?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?>
            </div>
        </div>
    </div>

    <!-- Bank Transfer Payment Proof Section -->
    <?php if ($order['payment_method'] === 'bank' && $order['payment_status'] === 'pending'): ?>
        <div class="alert alert-warning mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Action Required: Bank Transfer</h5>
                    <p class="mb-0">Your order is pending payment. Please complete the bank transfer and upload payment proof.</p>
                    
                    <?php if ($payment_proof): ?>
                        <div class="mt-2">
                            <strong>Payment Proof Status:</strong> 
                            <?php if ($payment_proof['status'] === 'pending'): ?>
                                <span class="badge bg-warning">Pending Verification</span>
                                <br>
                                <small>Your payment proof has been submitted and is awaiting admin verification.</small>
                            <?php elseif ($payment_proof['status'] === 'approved'): ?>
                                <span class="badge bg-success">Approved</span>
                                <br>
                                <small>Your payment has been verified on <?php echo date('d M Y', strtotime($payment_proof['verified_at'])); ?></small>
                            <?php elseif ($payment_proof['status'] === 'rejected'): ?>
                                <span class="badge bg-danger">Rejected</span>
                                <br>
                                <small>Reason: <?php echo htmlspecialchars($payment_proof['admin_notes']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <?php if (!$payment_proof || $payment_proof['status'] === 'rejected'): ?>
                        <a href="upload-payment-slip.php?order_id=<?php echo $order_id; ?>" class="btn btn-warning">
                            <i class="fas fa-upload me-2"></i>Upload Payment Proof
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- PayPal Payment Section -->
    <?php if ($order['payment_method'] === 'paypal' && $order['payment_status'] === 'pending'): ?>
        <div class="alert alert-info mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5><i class="fab fa-paypal me-2"></i>PayPal Payment</h5>
                    <p class="mb-0">You will be redirected to PayPal to complete your payment.</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="process-paypal-payment.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">
                        <i class="fab fa-paypal me-2"></i>Complete PayPal Payment
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="row">
        <!-- Order Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($item['image']) && file_exists('../../assets/images/products/' . $item['image'])): ?>
                                                    <img src="<?php echo SITE_URL . 'assets/images/products/' . $item['image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                         style="width: 50px; height: 50px; object-fit: cover;"
                                                         class="rounded me-3">
                                                <?php else: ?>
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" 
                                                         style="width: 50px; height: 50px;">
                                                        <i class="fas fa-box text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="<?php echo SITE_URL; ?>product-details.php?id=<?php echo $item['product_id']; ?>" 
                                                       class="text-decoration-none">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </a>
                                                    <br>
                                                    <small class="text-muted">
                                                        Vendor: <?php echo htmlspecialchars($item['vendor_name'] ?? $item['vendor_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Shipping & Billing Address -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-truck me-2"></i>Shipping Address</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                            <?php if (!empty($order['shipping_method'])): ?>
                                <p class="mb-0 mt-2">
                                    <strong>Method:</strong> 
                                    <?php 
                                    $method_names = [
                                        'standard' => 'Standard Shipping',
                                        'express' => 'Express Shipping',
                                        'overnight' => 'Overnight Shipping'
                                    ];
                                    echo $method_names[$order['shipping_method']] ?? ucfirst($order['shipping_method']);
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Billing Address</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($order['billing_address'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Notes -->
            <?php if (!empty($order['customer_notes'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Order Notes</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Order Status History -->
            <?php if (!empty($status_history)): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Order Status History</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($status_history as $history): ?>
                                <div class="d-flex mb-3">
                                    <div class="me-3">
                                        <span class="badge bg-<?php echo $status_colors[$history['status']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($history['status']); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="mb-0"><?php echo htmlspecialchars($history['notes']); ?></p>
                                        <small class="text-muted">
                                            <?php echo date('d M Y h:i A', strtotime($history['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <!-- Order Info -->
                    <div class="mb-4">
                        <p class="mb-1"><strong>Order Number:</strong></p>
                        <p class="text-primary">#<?php echo $order['order_number']; ?></p>
                        
                        <p class="mb-1"><strong>Order Date:</strong></p>
                        <p><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></p>
                        
                        <p class="mb-1"><strong>Payment Method:</strong></p>
                        <p>
                            <?php 
                            $method_icons = [
                                'bank' => 'university',
                                'paypal' => 'fa-paypal',
                                'stripe' => 'fa-stripe',
                                'easypaisa' => 'mobile-alt',
                                'jazzcash' => 'mobile-alt',
                                'cod' => 'money-bill-wave'
                            ];
                            $icon = $method_icons[$order['payment_method']] ?? 'credit-card';
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> me-2"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                        </p>
                        
                        <?php if (!empty($order['transaction_id'])): ?>
                            <p class="mb-1"><strong>Transaction ID:</strong></p>
                            <p><small class="text-muted"><?php echo $order['transaction_id']; ?></small></p>
                        <?php endif; ?>
                    </div>

                    <!-- Price Breakdown -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal (<?php echo count($order_items); ?> items)</span>
                            <span>$<?php echo number_format($order['total_amount'] - 5.99 - ($order['total_amount'] * 0.1), 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <span>$5.99</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (10%)</span>
                            <span>$<?php echo number_format($order['total_amount'] * 0.1, 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total</span>
                            <span class="text-primary h5 mb-0">$<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <?php if ($order['status'] === 'delivered'): ?>
                            <a href="rate-order.php?id=<?php echo $order_id; ?>" class="btn btn-success">
                                <i class="fas fa-star me-2"></i>Rate Products
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'pending'): ?>
                            <button class="btn btn-danger" onclick="cancelOrder(<?php echo $order_id; ?>)">
                                <i class="fas fa-times me-2"></i>Cancel Order
                            </button>
                        <?php endif; ?>
                        
                        <a href="invoice.php?id=<?php echo $order_id; ?>" class="btn btn-outline-primary" target="_blank">
                            <i class="fas fa-file-pdf me-2"></i>Download Invoice
                        </a>
                        
                        <a href="track-order.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-truck me-2"></i>Track Order
                        </a>
                    </div>

                    <!-- Contact Support -->
                    <div class="mt-4 text-center">
                        <p class="text-muted small mb-2">Need help with your order?</p>
                        <a href="../../support/contact.php?order=<?php echo $order_id; ?>" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-headset me-2"></i>Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sticky-top {
    z-index: 100;
}

.timeline {
    position: relative;
    padding-left: 20px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline .d-flex {
    position: relative;
}

.timeline .d-flex::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 8px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #007bff;
    z-index: 1;
}

@media (max-width: 768px) {
    .sticky-top {
        position: relative;
        top: 0;
    }
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function cancelOrder(orderId) {
    if (!confirm('Are you sure you want to cancel this order?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/cancel-order.php',
        type: 'POST',
        data: { order_id: orderId },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Failed to cancel order');
            }
        },
        error: function() {
            alert('Error cancelling order');
        }
    });
}
</script>

<?php
require_once '../../includes/footer.php';
ob_end_flush();
?>