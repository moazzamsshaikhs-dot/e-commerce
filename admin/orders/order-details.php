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
                                 u.username,
                                 u.email,
                                 u.phone,
                                 u.address as user_address,
                                 u.profile_pic,
                                 sc.name as carrier_name,
                                 sc.tracking_url,
                                 sc.id as carrier_id
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
                                 p.stock as current_stock,
                                 p.sku
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
    $stmt = $db->prepare("SELECT osh.*, u.full_name as changed_by_name, u.profile_pic as changed_by_avatar
                          FROM order_status_history osh
                          LEFT JOIN users u ON osh.changed_by = u.id
                          WHERE osh.order_id = ?
                          ORDER BY osh.created_at DESC");
    $stmt->execute([$order_id]);
    $status_history = $stmt->fetchAll();
    
    // Get order notes
    $stmt = $db->prepare("SELECT onotes.*, u.full_name as author_name, u.profile_pic as author_avatar
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
    
    // Calculate estimated delivery if shipped
    $estimated_delivery = null;
    if ($order['shipping_method'] == 'express') {
        $estimated_delivery = date('d M Y', strtotime($order['order_date'] . ' +2 days'));
    } elseif ($order['shipping_method'] == 'overnight') {
        $estimated_delivery = date('d M Y', strtotime($order['order_date'] . ' +1 day'));
    } else {
        $estimated_delivery = date('d M Y', strtotime($order['order_date'] . ' +5 days'));
    }
    
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
if ($order['payment_status'] == 'refunded') $payment_color = 'info';
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3651c4;
    --primary-light: rgba(67, 97, 238, 0.1);
    --success: #06d6a0;
    --success-dark: #05b585;
    --success-light: rgba(6, 214, 160, 0.1);
    --warning: #ffb703;
    --warning-dark: #e6a500;
    --warning-light: rgba(255, 183, 3, 0.1);
    --danger: #ef476f;
    --danger-dark: #d64161;
    --danger-light: rgba(239, 71, 111, 0.1);
    --info: #4cc9f0;
    --info-dark: #3aa9d9;
    --info-light: rgba(76, 201, 240, 0.1);
    --dark: #2b2d42;
    --dark-light: rgba(43, 45, 66, 0.1);
    --light: #f8f9fa;
    --border: #e9ecef;
    --shadow: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.1);
    --shadow-glow: 0 0 20px var(--primary-light);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

/* Animations */
@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInLeft {
    from {
        transform: translateX(-30px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Main Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--light);
}

.main-content {
    flex: 1;
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    overflow-y: auto;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.6s ease-out;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%);
    border-radius: 50%;
    z-index: 0;
}

.page-header > div {
    position: relative;
    z-index: 1;
}

.header-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.header-title i {
    color: var(--primary);
    margin-right: 10px;
}

.header-subtitle {
    color: var(--dark);
    opacity: 0.7;
}

.order-number-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* Status Cards */
.status-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border-left: 4px solid transparent;
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.5s ease-out;
    animation-fill-mode: both;
}

.status-card.status-<?php echo $status_color; ?> { 
    border-left-color: var(--<?php echo $status_color; ?>); 
}

.status-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

.status-badge-large {
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 16px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.status-badge-large i {
    font-size: 18px;
}

/* Info Cards */
.info-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    height: 100%;
    border: 1px solid var(--border);
    animation: slideInUp 0.5s ease-out;
    animation-fill-mode: both;
}

.info-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-3px);
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.info-card-header i {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.info-card-header h5 {
    margin: 0;
    font-weight: 600;
    color: var(--dark);
}

/* Timeline */
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--primary-light), var(--primary));
}

.timeline-item {
    position: relative;
    padding-left: 50px;
    margin-bottom: 25px;
    animation: slideInLeft 0.4s ease-out;
    animation-fill-mode: both;
}

.timeline-item:nth-child(1) { animation-delay: 0.1s; }
.timeline-item:nth-child(2) { animation-delay: 0.15s; }
.timeline-item:nth-child(3) { animation-delay: 0.2s; }
.timeline-item:nth-child(4) { animation-delay: 0.25s; }
.timeline-item:nth-child(5) { animation-delay: 0.3s; }

.timeline-marker {
    position: absolute;
    left: 10px;
    top: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: white;
    border: 3px solid var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}

.timeline-marker i {
    font-size: 12px;
    color: var(--primary);
}

.timeline-content {
    background: var(--light);
    border-radius: var(--radius);
    padding: 15px;
    transition: var(--transition);
}

.timeline-content:hover {
    background: white;
    box-shadow: var(--shadow);
}

.timeline-time {
    font-size: 11px;
    color: var(--dark);
    opacity: 0.6;
    margin-bottom: 5px;
}

.timeline-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
    text-transform: capitalize;
}

.timeline-desc {
    font-size: 12px;
    color: var(--dark);
    opacity: 0.7;
}

/* Notes List */
.notes-list {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 10px;
}

.note-item {
    background: var(--light);
    border-radius: var(--radius);
    padding: 15px;
    margin-bottom: 15px;
    transition: var(--transition);
    border-left: 3px solid transparent;
}

.note-item.internal {
    border-left-color: var(--info);
}

.note-item.customer {
    border-left-color: var(--success);
}

.note-item:hover {
    background: white;
    box-shadow: var(--shadow);
    transform: translateX(5px);
}

.note-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.note-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.note-author {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.note-meta {
    font-size: 11px;
    color: var(--dark);
    opacity: 0.6;
    margin-left: auto;
}

.note-type-badge {
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.note-type-badge.internal {
    background: var(--info-light);
    color: var(--info-dark);
}

.note-type-badge.customer {
    background: var(--success-light);
    color: var(--success-dark);
}

.note-text {
    font-size: 13px;
    color: var(--dark);
    line-height: 1.6;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.btn-quick {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
    padding: 12px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-quick:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
}

.btn-quick i {
    font-size: 16px;
}

/* Items Table */
.items-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.items-table th {
    padding: 12px 15px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--dark);
    opacity: 0.7;
    background: var(--light);
    border-radius: var(--radius);
}

.items-table td {
    padding: 15px;
    background: var(--light);
    border-radius: var(--radius);
    transition: var(--transition);
    vertical-align: middle;
}

.items-table tr:hover td {
    background: white;
    box-shadow: var(--shadow);
    transform: scale(1.01);
}

.product-image {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.stock-badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.stock-badge.in-stock {
    background: var(--success-light);
    color: var(--success-dark);
}

.stock-badge.low-stock {
    background: var(--warning-light);
    color: var(--warning-dark);
}

.stock-badge.out-stock {
    background: var(--danger-light);
    color: var(--danger-dark);
}

/* Summary Card */
.summary-card {
    background: linear-gradient(135deg, var(--primary-light) 0%, white 100%);
    border-radius: var(--radius);
    padding: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed var(--border);
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-label {
    color: var(--dark);
    opacity: 0.7;
}

.summary-value {
    font-weight: 600;
    color: var(--dark);
}

.summary-total {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary);
}

/* Modal Styles */
.modal-content {
    border-radius: var(--radius-lg);
    border: none;
    overflow: hidden;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: none;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid var(--border);
    background: var(--light);
}

/* Responsive */
@media (max-width: 992px) {
    .main-content {
        padding: 20px;
    }
    
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .quick-actions {
        grid-template-columns: 1fr;
    }
    
    .timeline-item {
        padding-left: 40px;
    }
    
    .items-table td {
        padding: 10px;
    }
}
</style>

<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="header-title">
                        <i class="fas fa-shopping-cart"></i>
                        Order Details
                    </h1>
                    <div class="d-flex align-items-center gap-3 mt-2">
                        <span class="order-number-badge">
                            <i class="fas fa-hashtag"></i>
                            <?php echo $order['order_number']; ?>
                        </span>
                        <span class="status-badge-large bg-<?php echo $status_color; ?> text-white">
                            <i class="fas fa-<?php echo $status_icon; ?>"></i>
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Orders
                    </a>
                    <a href="invoice.php?id=<?php echo $order_id; ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-print me-2"></i> Print Invoice
                    </a>
                </div>
            </div>
        </div>

        <!-- Order Status & Actions -->
        <div class="row g-4 mb-4">
            <!-- Status Card -->
            <div class="col-lg-8">
                <div class="status-card status-<?php echo $status_color; ?>">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div>
                                    <small class="text-muted d-block">Order Date</small>
                                    <strong><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></strong>
                                </div>
                                <div class="vr"></div>
                                <div>
                                    <small class="text-muted d-block">Est. Delivery</small>
                                    <strong><?php echo $estimated_delivery; ?></strong>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block">Payment Status</small>
                                <span class="badge bg-<?php echo $payment_color; ?> p-2">
                                    <i class="fas fa-<?php echo $order['payment_status'] == 'completed' ? 'check-circle' : 'clock'; ?> me-1"></i>
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            
                            <!-- Status Actions -->
                            <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                            <div class="btn-group">
                                <button class="btn btn-primary dropdown-toggle" 
                                        type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-edit me-2"></i>Change Status
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if ($order['status'] != 'processing'): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="updateOrderStatus('processing')">
                                            <i class="fas fa-cogs me-2 text-info"></i>Mark as Processing
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($order['status'] != 'shipped'): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="updateOrderStatus('shipped')">
                                            <i class="fas fa-shipping-fast me-2 text-primary"></i>Mark as Shipped
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($order['status'] != 'delivered'): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="updateOrderStatus('delivered')">
                                            <i class="fas fa-check-circle me-2 text-success"></i>Mark as Delivered
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" onclick="cancelOrder()">
                                            <i class="fas fa-times me-2"></i>Cancel Order
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <!-- Tracking Info -->
                            <?php if ($order['status'] == 'shipped' || $order['status'] == 'delivered'): ?>
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-3">
                                    <i class="fas fa-truck text-primary me-2"></i>
                                    Tracking Information
                                </h6>
                                
                                <?php if (!empty($order['tracking_number'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Tracking Number</small>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="bg-white p-2 rounded flex-grow-1">
                                            <?php echo htmlspecialchars($order['tracking_number']); ?>
                                        </code>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editTrackingNumber()">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <?php if (!empty($order['carrier_name'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Carrier</small>
                                    <strong><?php echo $order['carrier_name']; ?></strong>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($order['tracking_url'])): ?>
                                <a href="<?php echo $order['tracking_url'] . $order['tracking_number']; ?>" 
                                   target="_blank" class="btn btn-sm btn-success w-100 mt-2">
                                    <i class="fas fa-external-link-alt me-2"></i> Track Package
                                </a>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No tracking number added yet
                                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="addTrackingNumber()">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-bolt"></i>
                        <h5>Quick Actions</h5>
                    </div>
                    
                    <div class="quick-actions">
                        <button class="btn-quick" onclick="sendOrderUpdate()">
                            <i class="fas fa-envelope"></i>
                            <span>Email Customer</span>
                        </button>
                        <button class="btn-quick" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="fas fa-sticky-note"></i>
                            <span>Add Note</span>
                        </button>
                        <button class="btn-quick" onclick="duplicateOrder()">
                            <i class="fas fa-copy"></i>
                            <span>Duplicate</span>
                        </button>
                        <button class="btn-quick" onclick="sendInvoice()">
                            <i class="fas fa-file-invoice"></i>
                            <span>Send Invoice</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="info-card mb-4">
            <div class="info-card-header">
                <i class="fas fa-box"></i>
                <h5>Order Items (<?php echo $total_items; ?>)</h5>
                <span class="ms-auto badge bg-light text-dark">
                    Subtotal: $<?php echo number_format($subtotal, 2); ?>
                </span>
            </div>
            
            <div class="table-responsive">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="40%">Product</th>
                            <th width="10%">SKU</th>
                            <th width="12%">Unit Price</th>
                            <th width="10%">Quantity</th>
                            <th width="13%">Subtotal</th>
                            <th width="10%">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($order_items)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                                <h5>No Items Found</h5>
                                <p class="text-muted">This order has no items</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach($order_items as $item): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($item['image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $item['image']; ?>" 
                                             class="product-image" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                        <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                            <small class="text-muted"><?php echo $item['category']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?php echo $item['sku'] ?? 'N/A'; ?></code></td>
                                <td><strong>$<?php echo number_format($item['unit_price'], 2); ?></strong></td>
                                <td>
                                    <span class="badge bg-primary p-2"><?php echo $item['quantity']; ?></span>
                                </td>
                                <td><strong class="text-success">$<?php echo number_format($item['subtotal'], 2); ?></strong></td>
                                <td>
                                    <?php if ($item['current_stock'] <= 0): ?>
                                    <span class="stock-badge out-stock">
                                        <i class="fas fa-times-circle me-1"></i> Out of Stock
                                    </span>
                                    <?php elseif ($item['current_stock'] < 10): ?>
                                    <span class="stock-badge low-stock">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Low (<?php echo $item['current_stock']; ?>)
                                    </span>
                                    <?php else: ?>
                                    <span class="stock-badge in-stock">
                                        <i class="fas fa-check-circle me-1"></i> In Stock
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Order Summary -->
            <div class="summary-card mt-4">
                <div class="row">
                    <div class="col-md-6 offset-md-6">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal:</span>
                            <span class="summary-value">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Shipping:</span>
                            <span class="summary-value">$5.99</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Tax:</span>
                            <span class="summary-value">$<?php echo number_format($order['total_amount'] - $subtotal - 5.99, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Total:</span>
                            <span class="summary-total">$<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer & Shipping Info -->
        <div class="row g-4 mb-4">
            <!-- Customer Information -->
            <div class="col-md-4">
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-user"></i>
                        <h5>Customer Information</h5>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php 
                        $avatar = !empty($order['profile_pic']) ? SITE_URL . 'assets/images/profiles/' . $order['profile_pic'] : SITE_URL . 'assets/images/avatars/default.png';
                        ?>
                        <img src="<?php echo $avatar; ?>" class="rounded-circle" width="50" height="50" 
                             onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png';">
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($order['full_name']); ?></h6>
                            <small class="text-muted">@<?php echo $order['username']; ?></small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Email</small>
                        <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>" class="text-decoration-none">
                            <i class="fas fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($order['email']); ?>
                        </a>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Phone</small>
                        <a href="tel:<?php echo htmlspecialchars($order['phone']); ?>" class="text-decoration-none">
                            <i class="fas fa-phone me-2 text-primary"></i><?php echo htmlspecialchars($order['phone']); ?>
                        </a>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Customer Address</small>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['user_address'] ?? 'No address on file')); ?></p>
                    </div>
                    
                    <a href="customer-details.php?id=<?php echo $order['user_id']; ?>" class="btn btn-outline-primary w-100">
                        <i class="fas fa-user-circle me-2"></i> View Full Profile
                    </a>
                </div>
            </div>
            
            <!-- Shipping Information -->
            <div class="col-md-4">
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-truck"></i>
                        <h5>Shipping Information</h5>
                    </div>
                    
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
                                <span class="text-muted fst-italic">Same as shipping address</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Shipping Method</small>
                            <strong class="text-capitalize"><?php echo $order['shipping_method']; ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Carrier</small>
                            <strong><?php echo $order['carrier_name'] ?? 'Not assigned'; ?></strong>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['customer_notes'])): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-comment me-2"></i>
                        <small><?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="col-md-4">
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-credit-card"></i>
                        <h5>Payment Information</h5>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Payment Method</small>
                            <strong class="text-uppercase"><?php echo $order['payment_method']; ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Payment Status</small>
                            <span class="badge bg-<?php echo $payment_color; ?> p-2">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($payments)): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Payment History</small>
                        <div class="list-group list-group-flush">
                            <?php foreach($payments as $payment): ?>
                            <div class="list-group-item px-0 py-2 border-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small><?php echo date('M d, h:i A', strtotime($payment['created_at'])); ?></small>
                                        <?php if ($payment['transaction_id']): ?>
                                        <br><small class="text-muted">ID: <?php echo $payment['transaction_id']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                        $<?php echo number_format($payment['amount'], 2); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['payment_status'] != 'completed'): ?>
                    <button class="btn btn-success w-100" onclick="markAsPaid()">
                        <i class="fas fa-check-circle me-2"></i> Mark as Paid
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status History & Notes -->
        <div class="row g-4">
            <!-- Status History -->
            <div class="col-md-6">
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-history"></i>
                        <h5>Status History</h5>
                        <span class="ms-auto badge bg-light"><?php echo count($status_history); ?> updates</span>
                    </div>
                    
                    <?php if (empty($status_history)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-4x text-muted mb-3"></i>
                        <h6>No Status History</h6>
                        <p class="text-muted">This order has no status updates yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach($status_history as $history): ?>
                        <div class="timeline-item">
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
                            <div class="timeline-content">
                                <div class="timeline-time">
                                    <?php echo date('d M Y, h:i A', strtotime($history['created_at'])); ?>
                                </div>
                                <div class="timeline-title"><?php echo ucfirst($history['status']); ?></div>
                                <div class="timeline-desc">
                                    <?php if ($history['changed_by_name']): ?>
                                    By: <?php echo $history['changed_by_name']; ?>
                                    <?php else: ?>
                                    System
                                    <?php endif; ?>
                                </div>
                                <?php if ($history['notes']): ?>
                                <div class="mt-2 p-2 bg-white rounded">
                                    <small><?php echo htmlspecialchars($history['notes']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Notes -->
            <div class="col-md-6">
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-sticky-note"></i>
                        <h5>Order Notes</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="fas fa-plus me-1"></i> Add Note
                        </button>
                    </div>
                    
                    <?php if (empty($order_notes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-sticky-note fa-4x text-muted mb-3"></i>
                        <h6>No Notes</h6>
                        <p class="text-muted">No notes have been added to this order.</p>
                    </div>
                    <?php else: ?>
                    <div class="notes-list">
                        <?php foreach($order_notes as $note): ?>
                        <div class="note-item <?php echo $note['note_type']; ?>">
                            <div class="note-header">
                                <?php 
                                $author_avatar = !empty($note['author_avatar']) ? SITE_URL . 'assets/images/profiles/' . $note['author_avatar'] : SITE_URL . 'assets/images/avatars/default.png';
                                ?>
                                <img src="<?php echo $author_avatar; ?>" class="note-avatar">
                                <span class="note-author"><?php echo htmlspecialchars($note['author_name'] ?? 'System'); ?></span>
                                <span class="note-meta"><?php echo date('d M H:i', strtotime($note['created_at'])); ?></span>
                                <span class="note-type-badge <?php echo $note['note_type']; ?>">
                                    <?php echo ucfirst($note['note_type']); ?>
                                </span>
                            </div>
                            <div class="note-text">
                                <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sticky-note me-2"></i>
                    Add Order Note
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addNoteForm">
                <div class="modal-body">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Note Type</label>
                        <select class="form-select" name="note_type" required>
                            <option value="internal">Internal Note (Staff Only)</option>
                            <option value="customer">Customer Note (Visible to Customer)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Note <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="note" rows="5" required 
                                  placeholder="Enter your note here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveOrderNote()">
                        <i class="fas fa-save me-2"></i> Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-truck me-2"></i>
                    Update Tracking Information
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="trackingForm">
                <div class="modal-body">
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
                                <?php echo ($order['carrier_id'] == $carrier['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($carrier['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveTrackingInfo()">
                        <i class="fas fa-save me-2"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Update order status
function updateOrderStatus(newStatus) {
    Swal.fire({
        title: 'Update Order Status',
        text: `Are you sure you want to mark this order as ${newStatus}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'var(--success)',
        cancelButtonColor: 'var(--danger)',
        confirmButtonText: 'Yes, update it!',
        background: 'white',
        backdrop: `rgba(67,97,238,0.1)`
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/update-order-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: <?php echo $order_id; ?>,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
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
        text: 'Please provide a reason for cancellation:',
        icon: 'warning',
        input: 'textarea',
        inputPlaceholder: 'Enter cancellation reason...',
        showCancelButton: true,
        confirmButtonColor: 'var(--danger)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: 'Yes, cancel it!',
        background: 'white',
        showLoaderOnConfirm: true,
        preConfirm: (reason) => {
            if (!reason) {
                Swal.showValidationMessage('Please enter a reason');
                return false;
            }
            
            return fetch('ajax/cancel-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: <?php echo $order_id; ?>,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Cancelled!',
                text: result.value.message,
                timer: 2000
            }).then(() => location.reload());
        }
    });
}

// Save order note
function saveOrderNote() {
    const form = document.getElementById('addNoteForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Saving Note...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('ajax/save-order-note.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 1500
            }).then(() => location.reload());
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// Save tracking info
function saveTrackingInfo() {
    const form = document.getElementById('trackingForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('ajax/save-tracking-info.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 1500
            }).then(() => location.reload());
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
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

// Mark as paid
function markAsPaid() {
    Swal.fire({
        title: 'Mark as Paid',
        text: 'Are you sure you want to mark this order as paid?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'var(--success)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: 'Yes, mark as paid'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/mark-order-paid.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_id: <?php echo $order_id; ?> })
            })
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
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Send order update email
function sendOrderUpdate() {
    Swal.fire({
        title: 'Send Status Update',
        text: 'Send email notification to customer about order status?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'var(--primary)',
        cancelButtonColor: 'var(--danger)',
        confirmButtonText: 'Send Email'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/send-order-update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_id: <?php echo $order_id; ?> })
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
        confirmButtonColor: 'var(--primary)',
        cancelButtonColor: 'var(--danger)',
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
        confirmButtonColor: 'var(--primary)',
        cancelButtonColor: 'var(--danger)',
        confirmButtonText: 'Send Invoice'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/send-invoice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_id: <?php echo $order_id; ?> })
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
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>