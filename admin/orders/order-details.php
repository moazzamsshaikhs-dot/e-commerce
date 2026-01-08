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
                                 u.address as user_address,
                                 sc.name as carrier_name,
                                 sc.tracking_url
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
    $stmt = $db->prepare("SELECT oi.*, 
                                 p.name,
                                 p.image,
                                 p.category,
                                 p.stock as current_stock
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
    
    // Get order status history
    $stmt = $db->prepare("SELECT osh.*, u.full_name as changed_by_name
                          FROM order_status_history osh
                          LEFT JOIN users u ON osh.changed_by = u.id
                          WHERE osh.order_id = ?
                          ORDER BY osh.created_at DESC");
    $stmt->execute([$order_id]);
    $status_history = $stmt->fetchAll();
    
    // Get order notes
    $stmt = $db->prepare("SELECT onotes.*, u.full_name as author_name
                          FROM order_notes onotes
                          LEFT JOIN users u ON onotes.user_id = u.id
                          WHERE onotes.order_id = ?
                          ORDER BY onotes.created_at DESC");
    $stmt->execute([$order_id]);
    $order_notes = $stmt->fetchAll();
    
    // Get payment details
    $stmt = $db->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC");
    $stmt->execute([$order_id]);
    $payments = $stmt->fetchAll();
    
    // Get shipping carriers for dropdown
    $stmt = $db->query("SELECT * FROM shipping_carriers WHERE is_active = 1 ORDER BY name");
    $shipping_carriers = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading order details: ' . $e->getMessage();
    redirect('orders.php');
}

$page_title = 'Order Details #' . $order['order_number'];
require_once '../includes/header.php';

// Status badge color
$status_color = 'secondary';
$status_icon = 'question-circle';
switch($order['status']) {
    case 'pending': 
        $status_color = 'warning'; 
        $status_icon = 'clock'; 
        break;
    case 'processing': 
        $status_color = 'info'; 
        $status_icon = 'cogs'; 
        break;
    case 'shipped': 
        $status_color = 'primary'; 
        $status_icon = 'shipping-fast'; 
        break;
    case 'delivered': 
        $status_color = 'success'; 
        $status_icon = 'check-circle'; 
        break;
    case 'cancelled': 
        $status_color = 'danger'; 
        $status_icon = 'times-circle'; 
        break;
}

// Payment status badge
$payment_color = 'warning';
if ($order['payment_status'] == 'completed') $payment_color = 'success';
if ($order['payment_status'] == 'failed') $payment_color = 'danger';
?>

<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Order Details</h1>
                <p class="text-muted mb-0">Order #<?php echo $order['order_number']; ?></p>
            </div>
            <div>
                <a href="orders.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i> Back to Orders
                </a>
                <a href="invoice.php?id=<?php echo $order_id; ?>" class="btn btn-primary" target="_blank">
                    <i class="fas fa-print me-2"></i> Print Invoice
                </a>
            </div>
        </div>
        
        <!-- Order Status & Actions -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title mb-3">Order Status</h5>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-<?php echo $status_color; ?> fs-6 p-2 me-3">
                                        <i class="fas fa-<?php echo $status_icon; ?> me-2"></i>
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                    
                                    <!-- Status Actions -->
                                    <div class="btn-group">
                                        <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown" 
                                                aria-expanded="false">
                                            Change Status
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if ($order['status'] != 'processing'): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" 
                                                   onclick="updateOrderStatus('processing')">
                                                    <i class="fas fa-cogs me-2 text-info"></i>Mark as Processing
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($order['status'] != 'shipped'): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" 
                                                   onclick="updateOrderStatus('shipped')">
                                                    <i class="fas fa-shipping-fast me-2 text-primary"></i>Mark as Shipped
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($order['status'] != 'delivered'): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" 
                                                   onclick="updateOrderStatus('delivered')">
                                                    <i class="fas fa-check-circle me-2 text-success"></i>Mark as Delivered
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" 
                                                   onclick="cancelOrder()">
                                                    <i class="fas fa-times me-2"></i>Cancel Order
                                                </a>
                                            </li>
                                        </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Tracking Info -->
                                <?php if ($order['status'] == 'shipped' || $order['status'] == 'delivered'): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Tracking Information</label>
                                    <?php if (!empty($order['tracking_number'])): ?>
                                    <div class="d-flex align-items-center">
                                        <input type="text" class="form-control me-2" 
                                               id="trackingNumber" 
                                               value="<?php echo htmlspecialchars($order['tracking_number']); ?>"
                                               readonly>
                                        <?php if (!empty($order['tracking_url']) && !empty($order['tracking_number'])): ?>
                                        <a href="<?php echo $order['tracking_url'] . $order['tracking_number']; ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt me-1"></i> Track
                                        </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" 
                                                onclick="editTrackingNumber()">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        Carrier: <?php echo $order['carrier_name'] ?? 'Not specified'; ?>
                                    </small>
                                    <?php else: ?>
                                    <div class="alert alert-warning py-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No tracking number provided
                                        <button class="btn btn-sm btn-outline-primary ms-2" 
                                                onclick="addTrackingNumber()">
                                            <i class="fas fa-plus me-1"></i> Add
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="card-title mb-3">Order Summary</h5>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Order Date</small>
                                        <strong><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Payment Status</small>
                                        <span class="badge bg-<?php echo $payment_color; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Total Items</small>
                                        <strong><?php echo $total_items; ?> items</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Order Value</small>
                                        <strong class="text-primary fs-5">$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                    </div>
                                </div>
                                
                                <?php if ($order['delivered_date']): ?>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <small class="text-muted d-block">Delivered On</small>
                                        <strong><?php echo date('d M Y', strtotime($order['delivered_date'])); ?></strong>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($order['is_gift']): ?>
                                <div class="alert alert-info mt-3 py-2">
                                    <i class="fas fa-gift me-2"></i>
                                    <strong>Gift Order</strong>
                                    <?php if ($order['gift_message']): ?>
                                    <div class="mt-1 small">"<?php echo htmlspecialchars($order['gift_message']); ?>"</div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="sendOrderUpdate()">
                                <i class="fas fa-envelope me-2"></i> Send Status Update
                            </button>
                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                <i class="fas fa-sticky-note me-2"></i> Add Internal Note
                            </button>
                            <button class="btn btn-outline-info" onclick="duplicateOrder()">
                                <i class="fas fa-copy me-2"></i> Duplicate Order
                            </button>
                            <button class="btn btn-outline-warning" onclick="sendInvoice()">
                                <i class="fas fa-paper-plane me-2"></i> Send Invoice
                            </button>
                            <button class="btn btn-outline-danger" onclick="refundOrder()">
                                <i class="fas fa-undo me-2"></i> Process Refund
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Order Items (<?php echo $total_items; ?>)</h5>
                <span class="badge bg-light text-dark">Subtotal: $<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="40%">Product</th>
                                <th width="15%">Unit Price</th>
                                <th width="15%">Quantity</th>
                                <th width="15%">Subtotal</th>
                                <th width="10%">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($order_items)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No items found in this order</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php $counter = 1; ?>
                                <?php foreach($order_items as $item): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($item['image'])): ?>
                                            <img src="<?php echo SITE_URL; ?>uploads/products/<?php echo $item['image']; ?>" 
                                                 class="rounded me-3" width="50" height="50" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <small class="text-muted">
                                                    SKU: <?php echo $item['product_id']; ?> | 
                                                    Category: <?php echo $item['category']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $item['quantity']; ?></span>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($item['subtotal'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($item['current_stock'] <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                        <?php elseif ($item['current_stock'] < 10): ?>
                                        <span class="badge bg-warning">Low (<?php echo $item['current_stock']; ?>)</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">In Stock (<?php echo $item['current_stock']; ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                <td colspan="2"><strong>$<?php echo number_format($subtotal, 2); ?></strong></td>
                            </tr>
                            <?php if (!empty($order['shipping_method'])): ?>
                            <tr>
                                <td colspan="4" class="text-end">Shipping (<?php echo ucfirst($order['shipping_method']); ?>):</td>
                                <td colspan="2">$<?php echo number_format(5.99, 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
                                <td colspan="2">
                                    <strong class="text-primary fs-5">$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Customer & Shipping Information -->
        <div class="row mb-4">
            <!-- Customer Information -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2 text-primary"></i>Customer Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Name</small>
                            <strong><?php echo htmlspecialchars($order['full_name']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Email</small>
                            <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>">
                                <?php echo htmlspecialchars($order['email']); ?>
                            </a>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Phone</small>
                            <a href="tel:<?php echo htmlspecialchars($order['phone']); ?>">
                                <?php echo htmlspecialchars($order['phone']); ?>
                            </a>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Customer Address</small>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['user_address'])); ?></p>
                        </div>
                        <div class="mt-4">
                            <a href="customer-details.php?id=<?php echo $order['user_id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> View Customer Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Shipping Information -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-truck me-2 text-primary"></i>Shipping Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Shipping Address</small>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Billing Address</small>
                            <p class="mb-0">
                                <?php if (!empty($order['billing_address'])): ?>
                                <?php echo nl2br(htmlspecialchars($order['billing_address'])); ?>
                                <?php else: ?>
                                <span class="text-muted">Same as shipping address</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Shipping Method</small>
                            <strong><?php echo ucfirst($order['shipping_method']); ?></strong>
                        </div>
                        
                        <?php if (!empty($order['carrier_name'])): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Shipping Carrier</small>
                            <strong><?php echo $order['carrier_name']; ?></strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($order['customer_notes'])): ?>
                        <div class="alert alert-info py-2">
                            <small class="text-muted d-block">Customer Notes:</small>
                            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2 text-primary"></i>Payment Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Payment Method</small>
                            <strong><?php echo strtoupper($order['payment_method']); ?></strong>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Payment Status</small>
                            <span class="badge bg-<?php echo $payment_color; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($payments)): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Payment History</small>
                            <div class="list-group list-group-flush">
                                <?php foreach($payments as $payment): ?>
                                <div class="list-group-item px-0 py-2 border-0">
                                    <div class="d-flex justify-content-between">
                                        <small><?php echo date('M d, h:i A', strtotime($payment['created_at'])); ?></small>
                                        <span class="badge bg-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                            $<?php echo number_format($payment['amount'], 2); ?>
                                        </span>
                                    </div>
                                    <?php if ($payment['transaction_id']): ?>
                                    <small class="text-muted d-block">TXN: <?php echo $payment['transaction_id']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['payment_status'] != 'completed'): ?>
                        <div class="mt-4">
                            <button class="btn btn-sm btn-success w-100" onclick="markAsPaid()">
                                <i class="fas fa-check-circle me-1"></i> Mark as Paid
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status History & Notes -->
        <div class="row">
            <!-- Status History -->
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Status History</h5>
                        <small class="text-muted"><?php echo count($status_history); ?> updates</small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($status_history)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No status history available</p>
                        </div>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach($status_history as $history): ?>
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <div class="timeline-marker">
                                        <?php 
                                        $history_icon = 'clock';
                                        switch($history['status']) {
                                            case 'processing': $history_icon = 'cogs'; break;
                                            case 'shipped': $history_icon = 'shipping-fast'; break;
                                            case 'delivered': $history_icon = 'check-circle'; break;
                                            case 'cancelled': $history_icon = 'times-circle'; break;
                                        }
                                        ?>
                                        <i class="fas fa-<?php echo $history_icon; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between">
                                            <strong class="text-capitalize"><?php echo $history['status']; ?></strong>
                                            <small class="text-muted"><?php echo date('M d, h:i A', strtotime($history['created_at'])); ?></small>
                                        </div>
                                        <small class="text-muted d-block">
                                            <?php if ($history['changed_by_name']): ?>
                                            By: <?php echo $history['changed_by_name']; ?>
                                            <?php else: ?>
                                            System
                                            <?php endif; ?>
                                        </small>
                                        <?php if ($history['notes']): ?>
                                        <p class="mb-0 small mt-1"><?php echo htmlspecialchars($history['notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Notes -->
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Order Notes</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="fas fa-plus me-1"></i> Add Note
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($order_notes)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-sticky-note fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No notes yet</p>
                        </div>
                        <?php else: ?>
                        <div class="notes-list">
                            <?php foreach($order_notes as $note): ?>
                            <div class="note-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong>
                                        <?php if ($note['author_name']): ?>
                                        <?php echo htmlspecialchars($note['author_name']); ?>
                                        <?php else: ?>
                                        System
                                        <?php endif; ?>
                                    </strong>
                                    <div>
                                        <span class="badge bg-<?php echo $note['note_type'] == 'internal' ? 'info' : 'success'; ?>">
                                            <?php echo ucfirst($note['note_type']); ?>
                                        </span>
                                        <small class="text-muted ms-2"><?php echo date('M d, h:i A', strtotime($note['created_at'])); ?></small>
                                    </div>
                                </div>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNoteModalLabel">Add Order Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addNoteForm">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <div class="mb-3">
                        <label class="form-label">Note Type</label>
                        <select class="form-select" name="note_type" required>
                            <option value="internal">Internal Note</option>
                            <option value="customer">Customer Note</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="note" rows="4" required 
                                  placeholder="Enter your note here..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveOrderNote()">Save Note</button>
            </div>
        </div>
    </div>
</div>

<!-- Tracking Number Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-labelledby="trackingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trackingModalLabel">Update Tracking Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="trackingForm">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <div class="mb-3">
                        <label class="form-label">Tracking Number</label>
                        <input type="text" class="form-control" name="tracking_number" 
                               value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>"
                               placeholder="Enter tracking number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shipping Carrier</label>
                        <select class="form-select" name="shipping_carrier_id">
                            <option value="">Select Carrier</option>
                            <?php foreach($shipping_carriers as $carrier): ?>
                            <option value="<?php echo $carrier['id']; ?>" 
                                <?php echo ($order['shipping_carrier_id'] == $carrier['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($carrier['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTrackingInfo()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Update order status
function updateOrderStatus(newStatus) {
    Swal.fire({
        title: 'Update Order Status',
        text: `Are you sure you want to mark this order as ${newStatus}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`../ajax/update-order-status.php?id=<?php echo $order_id; ?>&status=${newStatus}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
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

// Cancel order
function cancelOrder() {
    Swal.fire({
        title: 'Cancel Order',
        text: 'Are you sure you want to cancel this order? This action cannot be undone.',
        icon: 'warning',
        input: 'text',
        inputLabel: 'Reason for cancellation',
        inputPlaceholder: 'Enter reason...',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, cancel it!'
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const reason = result.value;
            fetch(`../ajax/cancel-order.php?id=<?php echo $order_id; ?>`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Cancelled!', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
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

// Save order note
function saveOrderNote() {
    const form = document.getElementById('addNoteForm');
    const formData = new FormData(form);
    
    fetch('../ajax/save-order-note.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Success!', data.message, 'success');
            $('#addNoteModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// Edit tracking number
function editTrackingNumber() {
    $('#trackingModal').modal('show');
}

// Add tracking number
function addTrackingNumber() {
    $('#trackingModal').modal('show');
}

// Save tracking information
function saveTrackingInfo() {
    const form = document.getElementById('trackingForm');
    const formData = new FormData(form);
    
    fetch('../ajax/save-tracking-info.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Success!', data.message, 'success');
            $('#trackingModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// Mark as paid
function markAsPaid() {
    Swal.fire({
        title: 'Mark as Paid',
        text: 'Are you sure you want to mark this order as paid?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, mark as paid'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`../ajax/mark-order-paid.php?id=<?php echo $order_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
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

// Send order update
function sendOrderUpdate() {
    Swal.fire({
        title: 'Send Status Update',
        text: 'Send email notification to customer about order status?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Send Email'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`../ajax/send-order-update.php?id=<?php echo $order_id; ?>`)
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

// Duplicate order
function duplicateOrder() {
    Swal.fire({
        title: 'Duplicate Order',
        text: 'Create a copy of this order?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Duplicate'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `create-order.php?duplicate_id=<?php echo $order_id; ?>`;
        }
    });
}

// Send invoice
function sendInvoice() {
    Swal.fire({
        title: 'Send Invoice',
        text: 'Send invoice to customer email?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Send Invoice'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`../ajax/send-invoice.php?id=<?php echo $order_id; ?>`)
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

// Refund order
function refundOrder() {
    window.location.href = '../ajax/refund-order.php?order_id=<?php echo $order_id; ?>';
}
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #e9ecef;
}
.timeline-item {
    position: relative;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: #fff;
    border: 2px solid #0d6efd;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0d6efd;
}
.note-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
</style>

<?php require_once '../includes/footer.php'; ?>