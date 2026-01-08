<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Order ID is required.';
    redirect('orders.php');
}

$order_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Get the newly created order details
    $stmt = $db->prepare("SELECT o.*, 
                                 u.full_name,
                                 u.email,
                                 u.phone,
                                 sc.name as carrier_name
                          FROM orders o
                          LEFT JOIN users u ON o.user_id = u.id
                          LEFT JOIN shipping_carriers sc ON o.shipping_carrier_id = sc.id
                          WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found.';
        redirect(SITE_URL . 'orders.php');
    }
    
    // Get order items
    $stmt = $db->prepare("SELECT oi.*, p.name, p.category 
                          FROM order_items oi
                          LEFT JOIN products p ON oi.product_id = p.id
                          WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    // Calculate totals
    $subtotal = 0;
    $total_items = 0;
    foreach ($order_items as $item) {
        $subtotal += $item['subtotal'];
        $total_items += $item['quantity'];
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading order confirmation: ' . $e->getMessage();
    redirect(SITE_URL . 'orders.php');
}

$page_title = 'Order Created Successfully';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content">
        <!-- Success Confirmation -->
        <div class="card border-0 shadow-sm border-success border-2 mb-4">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center" 
                         style="width: 80px; height: 80px;">
                        <i class="fas fa-check fa-2x"></i>
                    </div>
                </div>
                <h2 class="text-success mb-3">Order Created Successfully!</h2>
                <p class="lead mb-4">Order <strong>#<?php echo $order['order_number']; ?></strong> has been created successfully.</p>
                
                <div class="row justify-content-center mb-4">
                    <div class="col-md-8">
                        <div class="card border">
                            <div class="card-body text-start">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Order Number:</strong><br>
                                            <span class="text-primary"><?php echo $order['order_number']; ?></span>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Customer:</strong><br>
                                            <?php echo htmlspecialchars($order['full_name']); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Email:</strong><br>
                                            <?php echo htmlspecialchars($order['email']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Order Date:</strong><br>
                                            <?php echo date('F d, Y h:i A', strtotime($order['order_date'])); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Total Amount:</strong><br>
                                            <span class="fs-4 text-success">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Status:</strong><br>
                                            <span class="badge bg-<?php 
                                                echo match($order['status']) {
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'shipped' => 'primary',
                                                    'delivered' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-eye me-2"></i> View Order Details
                    </a>
                    
                    <a href="invoice.php?id=<?php echo $order_id; ?>" target="_blank" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-print me-2"></i> Print Invoice
                    </a>
                    
                    <button onclick="sendOrderConfirmation(<?php echo $order_id; ?>)" class="btn btn-success btn-lg">
                        <i class="fas fa-envelope me-2"></i> Email Customer
                    </button>
                    
                    <a href="create-order.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-plus me-2"></i> Create Another Order
                    </a>
                </div>
                
                <!-- Quick Actions -->
                <div class="mt-4">
                    <p class="text-muted">What would you like to do next?</p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <a href="orders.php" class="btn btn-sm btn-outline-dark">
                            <i class="fas fa-list me-1"></i> View All Orders
                        </a>
                        <a href="edit-order.php?id=<?php echo $order_id; ?>" class="btn btn-sm btn-outline-dark">
                            <i class="fas fa-edit me-1"></i> Edit This Order
                        </a>
                        <button onclick="duplicateThisOrder(<?php echo $order_id; ?>)" class="btn btn-sm btn-outline-dark">
                            <i class="fas fa-copy me-1"></i> Duplicate Order
                        </button>
                        <a href="customer-details.php?id=<?php echo $order['user_id']; ?>" class="btn btn-sm btn-outline-dark">
                            <i class="fas fa-user me-1"></i> View Customer
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="row">
            <div class="col-md-8">
                <!-- Order Items -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Order Items (<?php echo $total_items; ?> items)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                            <div class="text-muted small">Category: <?php echo $item['category']; ?></div>
                                        </td>
                                        <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?php echo $item['quantity']; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <strong>$<?php echo number_format($item['subtotal'], 2); ?></strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end"><strong>$<?php echo number_format($subtotal, 2); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end">Shipping:</td>
                                        <td class="text-end">$5.99</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end">Tax:</td>
                                        <td class="text-end">$<?php echo number_format($order['total_amount'] - $subtotal - 5.99, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end"><h5 class="mb-0">Total:</h5></td>
                                        <td class="text-end">
                                            <h5 class="mb-0 text-success">$<?php echo number_format($order['total_amount'], 2); ?></h5>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Information -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Shipping Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <strong>Shipping Address:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Shipping Method:</strong><br>
                                    <?php echo ucfirst($order['shipping_method']); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($order['billing_address'])): ?>
                                <p class="mb-2">
                                    <strong>Billing Address:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($order['billing_address'])); ?>
                                </p>
                                <?php endif; ?>
                                <?php if (!empty($order['carrier_name'])): ?>
                                <p class="mb-2">
                                    <strong>Carrier:</strong><br>
                                    <?php echo $order['carrier_name']; ?>
                                </p>
                                <?php endif; ?>
                                <?php if (!empty($order['tracking_number'])): ?>
                                <p class="mb-0">
                                    <strong>Tracking Number:</strong><br>
                                    <?php echo $order['tracking_number']; ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Customer Information -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px;">
                                <i class="fas fa-user fa-2x text-muted"></i>
                            </div>
                            <h5 class="mt-2 mb-1"><?php echo htmlspecialchars($order['full_name']); ?></h5>
                            <p class="text-muted mb-2">Customer ID: #<?php echo $order['user_id']; ?></p>
                        </div>
                        
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0">
                                <i class="fas fa-envelope me-2 text-muted"></i>
                                <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>">
                                    <?php echo htmlspecialchars($order['email']); ?>
                                </a>
                            </div>
                            <?php if (!empty($order['phone'])): ?>
                            <div class="list-group-item px-0">
                                <i class="fas fa-phone me-2 text-muted"></i>
                                <a href="tel:<?php echo htmlspecialchars($order['phone']); ?>">
                                    <?php echo htmlspecialchars($order['phone']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3">
                            <a href="customer-details.php?id=<?php echo $order['user_id']; ?>" 
                               class="btn btn-outline-primary w-100">
                                <i class="fas fa-user-circle me-2"></i> View Full Profile
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Next Steps</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 border-0 mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                         style="width: 40px; height: 40px;">
                                        <i class="fas fa-1 text-muted"></i>
                                    </div>
                                    <div>
                                        <strong>Prepare Order</strong>
                                        <div class="text-muted small">Gather items for shipping</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="list-group-item px-0 border-0 mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                         style="width: 40px; height: 40px;">
                                        <i class="fas fa-2 text-muted"></i>
                                    </div>
                                    <div>
                                        <strong>Update Status</strong>
                                        <div class="text-muted small">Mark as Processing when ready</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="list-group-item px-0 border-0">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" 
                                         style="width: 40px; height: 40px;">
                                        <i class="fas fa-3 text-muted"></i>
                                    </div>
                                    <div>
                                        <strong>Ship Order</strong>
                                        <div class="text-muted small">Add tracking number when shipped</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-info py-2">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Order confirmation email has been sent to the customer.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Send order confirmation email
function sendOrderConfirmation(orderId) {
    const button = event?.target || document.querySelector('button[onclick*="sendOrderConfirmation"]');
    const originalText = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
    button.disabled = true;
    
    fetch(`../ajax/send-order-confirmation.php?id=${orderId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: data.message,
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred.', 'error');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Duplicate this order
function duplicateThisOrder(orderId) {
    Swal.fire({
        title: 'Duplicate Order',
        text: 'Create a copy of this order?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, duplicate it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `create-order.php?duplicate=${orderId}`;
        }
    });
}

// Auto-redirect after 30 seconds
let redirectTimer = 30;
const redirectInterval = setInterval(() => {
    if (redirectTimer > 0) {
        document.getElementById('redirectTimer').textContent = redirectTimer;
        redirectTimer--;
    } else {
        clearInterval(redirectInterval);
        window.location.href = 'orders.php';
    }
}, 1000);

// Print invoice
function printInvoice(orderId) {
    window.open(`invoice.php?id=${orderId}`, '_blank');
}

// Close and go to orders
function closeAndGoToOrders() {
    window.location.href = 'orders.php';
}

// Show auto-redirect notification
Swal.fire({
    title: 'Order Created!',
    text: `Order #${<?php echo $order_id; ?>} has been created successfully.`,
    icon: 'success',
    showCancelButton: true,
    confirmButtonText: 'View Order Details',
    cancelButtonText: 'Go to Orders List',
    showDenyButton: true,
    denyButtonText: 'Create Another Order'
}).then((result) => {
    if (result.isConfirmed) {
        window.location.href = `order-details.php?id=<?php echo $order_id; ?>`;
    } else if (result.isDenied) {
        window.location.href = 'create-order.php';
    } else {
        window.location.href = 'orders.php';
    }
});
</script>

<!-- Auto-redirect notification (hidden) -->
<div id="autoRedirect" class="fixed-bottom bg-warning text-dark py-2 d-none">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-clock me-2"></i>
                Redirecting to orders page in <span id="redirectTimer">30</span> seconds...
            </div>
            <div>
                <button class="btn btn-sm btn-dark me-2" onclick="closeAndGoToOrders()">
                    Go Now
                </button>
                <button class="btn btn-sm btn-outline-dark" onclick="clearAutoRedirect()">
                    Stay Here
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Show auto-redirect notification after 5 seconds
setTimeout(() => {
    document.getElementById('autoRedirect').classList.remove('d-none');
}, 5000);

// Clear auto-redirect
function clearAutoRedirect() {
    clearInterval(redirectInterval);
    document.getElementById('autoRedirect').classList.add('d-none');
}
</script>

<?php require_once '../includes/footer.php'; ?>