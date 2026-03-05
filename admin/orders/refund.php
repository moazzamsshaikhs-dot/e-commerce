<?php
// admin/orders/refund.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$refund_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $db = getDB();
    
    // If refund_id is provided, show specific refund details
    if ($refund_id) {
        $stmt = $db->prepare("
            SELECT r.*, 
                   o.order_number, 
                   o.total_amount as order_total,
                   o.payment_status,
                   o.payment_method,
                   u.full_name as customer_name,
                   u.email as customer_email,
                   u.phone as customer_phone,
                   p.full_name as processed_by_name
            FROM refunds r
            JOIN orders o ON r.order_id = o.id
            JOIN users u ON r.user_id = u.id
            LEFT JOIN users p ON r.processed_by = p.id
            WHERE r.id = ?
        ");
        $stmt->execute([$refund_id]);
        $refund = $stmt->fetch();
        
        if (!$refund) {
            $_SESSION['error'] = 'Refund not found.';
            redirect('refunds.php');
        }
        
        $order_id = $refund['order_id'];
    }
    
    // Get order details
    $stmt = $db->prepare("
        SELECT o.*, 
               u.full_name,
               u.email,
               u.phone,
               u.address
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found.';
        redirect('orders.php');
    }
    
    // Get order items
    $stmt = $db->prepare("
        SELECT oi.*, p.name, p.price as current_price
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    // Get previous refunds for this order
    $stmt = $db->prepare("
        SELECT r.*, u.full_name as processed_by_name
        FROM refunds r
        LEFT JOIN users u ON r.processed_by = u.id
        WHERE r.order_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$order_id]);
    $previous_refunds = $stmt->fetchAll();
    
    // Calculate total refunded amount
    $total_refunded = 0;
    foreach ($previous_refunds as $ref) {
        if ($ref['status'] == 'completed') {
            $total_refunded += $ref['refund_amount'];
        }
    }
    
    $remaining_refundable = $order['total_amount'] - $total_refunded;
    
    // Get payment methods for refund
    $stmt = $db->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC");
    $stmt->execute([$order_id]);
    $payments = $stmt->fetchAll();
    
    // Get admin accounts for refund destination
    $stmt = $db->query("
        SELECT * FROM admin_accounts 
        WHERE is_active = 1 
        ORDER BY is_default DESC, account_type
    ");
    $admin_accounts = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading data: ' . $e->getMessage();
    redirect('orders.php');
}

$page_title = $refund_id ? 'Refund Details' : 'Process Refund';
require_once '../../includes/header.php';

// Status badge colors
$status_colors = [
    'pending' => 'warning',
    'processing' => 'info',
    'completed' => 'success',
    'failed' => 'danger',
    'cancelled' => 'secondary'
];
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
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

/* Main Layout */
.refund-container {
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    min-height: 100vh;
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

/* Info Cards */
.info-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 25px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    height: 100%;
    border: 1px solid var(--border);
}

.info-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-3px);
}

.card-header-custom {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.card-header-custom i {
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

.card-header-custom h5 {
    margin: 0;
    font-weight: 600;
    color: var(--dark);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.stat-card.total { border-left-color: var(--primary); }
.stat-card.refunded { border-left-color: var(--warning); }
.stat-card.remaining { border-left-color: var(--success); }

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 10px;
}

.stat-card.total .stat-icon { background: var(--primary-light); color: var(--primary); }
.stat-card.refunded .stat-icon { background: var(--warning-light); color: var(--warning); }
.stat-card.remaining .stat-icon { background: var(--success-light); color: var(--success); }

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-label {
    color: var(--dark);
    opacity: 0.7;
    font-size: 13px;
    font-weight: 500;
}

/* Form Styles */
.refund-form {
    background: white;
    border-radius: var(--radius-lg);
    padding: 25px;
    box-shadow: var(--shadow);
}

.form-label {
    font-weight: 500;
    color: var(--dark);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.form-label i {
    color: var(--primary);
}

.form-control, .form-select {
    border-radius: var(--radius);
    border: 2px solid var(--border);
    padding: 10px 15px;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-light);
    outline: none;
}

.form-control[readonly] {
    background: var(--light);
    border-color: var(--border);
    color: var(--dark);
}

/* Items Table */
.items-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
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
}

.item-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--primary);
}

/* Refund Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--primary-light), var(--primary));
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-left: 20px;
}

.timeline-marker {
    position: absolute;
    left: -24px;
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
}

.timeline-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    margin-top: 5px;
}

/* Buttons */
.btn-refund {
    background: var(--warning);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: var(--radius);
    font-weight: 600;
    transition: var(--transition-bounce);
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-refund:hover {
    background: var(--warning-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
}

.btn-process {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
}

.btn-process:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

/* Refund Cards */
.refund-card {
    background: white;
    border-radius: var(--radius);
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.refund-card:hover {
    box-shadow: var(--shadow);
    transform: translateX(5px);
}

.refund-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.refund-id {
    font-weight: 600;
    color: var(--primary);
}

.refund-amount {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
}

.refund-details {
    font-size: 13px;
    color: var(--dark);
    opacity: 0.8;
}

.refund-reason {
    background: var(--light);
    padding: 10px;
    border-radius: var(--radius);
    margin-top: 10px;
    font-size: 13px;
}

/* Responsive */
@media (max-width: 768px) {
    .refund-container {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .items-table td {
        padding: 10px;
    }
}
</style>

<div class="refund-container">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-undo-alt me-2" style="color: var(--primary);"></i>
                        <?php echo $refund_id ? 'Refund Details' : 'Process Refund'; ?>
                    </h1>
                    <p class="text-muted mb-0">
                        Order #<?php echo htmlspecialchars($order['order_number']); ?> | 
                        Customer: <?php echo htmlspecialchars($order['full_name']); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Order
                    </a>
                    <?php if (!$refund_id): ?>
                    <a href="refunds.php" class="btn btn-outline-secondary">
                        <i class="fas fa-history me-2"></i> All Refunds
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($refund_id): ?>
            <!-- Refund Details View -->
            <div class="row">
                <div class="col-md-8">
                    <!-- Refund Information -->
                    <div class="info-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-info-circle"></i>
                            <h5>Refund Information</h5>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="text-muted">Refund ID:</td>
                                        <td><strong>#<?php echo $refund['id']; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Order Number:</td>
                                        <td><strong><?php echo $refund['order_number']; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Request Date:</td>
                                        <td><?php echo date('d M Y H:i', strtotime($refund['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Processed Date:</td>
                                        <td><?php echo $refund['processed_at'] ? date('d M Y H:i', strtotime($refund['processed_at'])) : 'Not processed'; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="text-muted">Amount:</td>
                                        <td><strong class="text-danger">$<?php echo number_format($refund['refund_amount'], 2); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Status:</td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_colors[$refund['status']]; ?> p-2">
                                                <?php echo ucfirst($refund['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Reason:</td>
                                        <td><?php echo ucfirst($refund['reason']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Processed By:</td>
                                        <td><?php echo $refund['processed_by_name'] ?? 'Pending'; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (!empty($refund['notes'])): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <strong>Additional Notes:</strong>
                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($refund['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($refund['status'] == 'pending'): ?>
                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-success" onclick="processRefund(<?php echo $refund['id']; ?>)">
                                <i class="fas fa-check-circle me-2"></i> Process Refund
                            </button>
                            <button class="btn btn-danger" onclick="cancelRefund(<?php echo $refund['id']; ?>)">
                                <i class="fas fa-times-circle me-2"></i> Cancel Refund
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Refund Timeline -->
                    <div class="info-card">
                        <div class="card-header-custom">
                            <i class="fas fa-history"></i>
                            <h5>Refund Timeline</h5>
                        </div>
                        
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-time"><?php echo date('d M Y H:i', strtotime($refund['created_at'])); ?></div>
                                    <div class="timeline-title">Refund Request Created</div>
                                    <div>Amount: $<?php echo number_format($refund['refund_amount'], 2); ?></div>
                                    <span class="timeline-status badge bg-warning">Pending</span>
                                </div>
                            </div>
                            
                            <?php if ($refund['processed_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <i class="fas fa-<?php echo $refund['status'] == 'completed' ? 'check' : 'times'; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-time"><?php echo date('d M Y H:i', strtotime($refund['processed_at'])); ?></div>
                                    <div class="timeline-title">Refund <?php echo ucfirst($refund['status']); ?></div>
                                    <div>Processed by: <?php echo $refund['processed_by_name'] ?? 'System'; ?></div>
                                    <span class="timeline-status badge bg-<?php echo $status_colors[$refund['status']]; ?>">
                                        <?php echo ucfirst($refund['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Customer Information -->
                    <div class="info-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-user"></i>
                            <h5>Customer</h5>
                        </div>
                        
                        <div class="mb-3">
                            <strong><?php echo htmlspecialchars($refund['customer_name']); ?></strong>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-envelope me-2 text-primary"></i>
                            <?php echo htmlspecialchars($refund['customer_email']); ?>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-phone me-2 text-primary"></i>
                            <?php echo htmlspecialchars($refund['customer_phone'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="info-card">
                        <div class="card-header-custom">
                            <i class="fas fa-shopping-cart"></i>
                            <h5>Order Summary</h5>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Order Total:</span>
                                <strong>$<?php echo number_format($refund['order_total'], 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Refund Amount:</span>
                                <strong class="text-danger">-$<?php echo number_format($refund['refund_amount'], 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Payment Method:</span>
                                <span class="badge bg-info"><?php echo strtoupper($refund['payment_method']); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Remaining:</span>
                                <span class="fw-bold text-success">
                                    $<?php echo number_format($refund['order_total'] - $refund['refund_amount'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Process Refund View -->
            <div class="row">
                <div class="col-lg-8">
                    <!-- Refund Form -->
                    <div class="info-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-undo-alt"></i>
                            <h5>Process Refund</h5>
                        </div>
                        
                        <form id="refundForm" method="POST" action="ajax/process-refund.php">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fas fa-dollar-sign"></i> Refund Amount *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="refund_amount" 
                                               id="refundAmount" step="0.01" min="0.01" 
                                               max="<?php echo $remaining_refundable; ?>" 
                                               value="<?php echo min($remaining_refundable, $order['total_amount']); ?>" required>
                                    </div>
                                    <small class="text-muted">
                                        Max refundable: $<?php echo number_format($remaining_refundable, 2); ?>
                                    </small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fas fa-tag"></i> Refund Reason *
                                    </label>
                                    <select class="form-select" name="reason" required>
                                        <option value="">Select Reason</option>
                                        <option value="customer_request">Customer Request</option>
                                        <option value="item_defective">Item Defective</option>
                                        <option value="item_damaged">Item Damaged in Shipping</option>
                                        <option value="wrong_item">Wrong Item Sent</option>
                                        <option value="item_not_received">Item Not Received</option>
                                        <option value="duplicate_order">Duplicate Order</option>
                                        <option value="fraudulent">Fraudulent Order</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fas fa-credit-card"></i> Refund Method *
                                    </label>
                                    <select class="form-select" name="refund_method" id="refundMethod" required>
                                        <option value="original">Original Payment Method</option>
                                        <option value="store_credit">Store Credit</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="paypal">PayPal</option>
                                        <option value="cash">Cash</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6" id="adminAccountField" style="display: none;">
                                    <label class="form-label">
                                        <i class="fas fa-university"></i> Admin Account
                                    </label>
                                    <select class="form-select" name="admin_account_id">
                                        <option value="">Select Account</option>
                                        <?php foreach($admin_accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>">
                                            <?php echo $account['account_name']; ?> 
                                            ($<?php echo number_format($account['current_balance'] ?? 0, 2); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="fas fa-sticky-note"></i> Additional Notes
                                    </label>
                                    <textarea class="form-control" name="notes" rows="3" 
                                              placeholder="Enter any additional notes about this refund..."></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Warning:</strong> Refunds are processed immediately and cannot be undone.
                                        Please verify all details before proceeding.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn-refund">
                                    <i class="fas fa-undo-alt me-2"></i> Process Refund
                                </button>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="previewRefund()">
                                    <i class="fas fa-eye me-2"></i> Preview
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="info-card">
                        <div class="card-header-custom">
                            <i class="fas fa-box"></i>
                            <h5>Order Items</h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th width="5%"></th>
                                        <th width="50%">Product</th>
                                        <th width="15%">Price</th>
                                        <th width="15%">Quantity</th>
                                        <th width="15%">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="item-checkbox" 
                                                   data-price="<?php echo $item['unit_price']; ?>"
                                                   data-quantity="<?php echo $item['quantity']; ?>"
                                                   onclick="calculateRefund()">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        </td>
                                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Refund Summary -->
                    <div class="info-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-calculator"></i>
                            <h5>Refund Summary</h5>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-card total">
                                <div class="stat-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="stat-value">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                <div class="stat-label">Order Total</div>
                            </div>
                            
                            <div class="stat-card refunded">
                                <div class="stat-icon">
                                    <i class="fas fa-undo"></i>
                                </div>
                                <div class="stat-value">$<?php echo number_format($total_refunded, 2); ?></div>
                                <div class="stat-label">Already Refunded</div>
                            </div>
                            
                            <div class="stat-card remaining">
                                <div class="stat-icon">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div class="stat-value">$<?php echo number_format($remaining_refundable, 2); ?></div>
                                <div class="stat-label">Available for Refund</div>
                            </div>
                        </div>
                        
                        <div class="refund-summary mt-3 p-3 bg-light rounded">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Selected Items:</span>
                                <strong id="selectedAmount">$0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Custom Amount:</span>
                                <strong id="customAmount">$0.00</strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Total Refund:</span>
                                <span class="fw-bold text-danger" id="totalRefund">$0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Previous Refunds -->
                    <?php if (!empty($previous_refunds)): ?>
                    <div class="info-card">
                        <div class="card-header-custom">
                            <i class="fas fa-history"></i>
                            <h5>Previous Refunds</h5>
                        </div>
                        
                        <?php foreach($previous_refunds as $prev): ?>
                        <div class="refund-card">
                            <div class="refund-header">
                                <span class="refund-id">#<?php echo $prev['id']; ?></span>
                                <span class="refund-amount text-danger">-$<?php echo number_format($prev['refund_amount'], 2); ?></span>
                            </div>
                            <div class="refund-details">
                                <div><?php echo ucfirst($prev['reason']); ?></div>
                                <small class="text-muted">
                                    <?php echo date('d M Y', strtotime($prev['created_at'])); ?>
                                </small>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-<?php echo $status_colors[$prev['status']]; ?>">
                                    <?php echo ucfirst($prev['status']); ?>
                                </span>
                                <?php if ($prev['status'] == 'completed'): ?>
                                <small class="text-muted ms-2">
                                    by <?php echo $prev['processed_by_name']; ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <?php if ($prev['status'] == 'pending'): ?>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-success" onclick="processRefund(<?php echo $prev['id']; ?>)">
                                    Process
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="cancelRefund(<?php echo $prev['id']; ?>)">
                                    Cancel
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Calculate refund amount based on selected items
function calculateRefund() {
    let selectedTotal = 0;
    document.querySelectorAll('.item-checkbox:checked').forEach(checkbox => {
        const price = parseFloat(checkbox.dataset.price);
        const quantity = parseInt(checkbox.dataset.quantity);
        selectedTotal += price * quantity;
    });
    
    document.getElementById('selectedAmount').textContent = '$' + selectedTotal.toFixed(2);
    
    const refundInput = document.getElementById('refundAmount');
    if (refundInput) {
        refundInput.value = selectedTotal.toFixed(2);
        updateCustomAmount();
    }
}

// Update custom amount
function updateCustomAmount() {
    const refundAmount = document.getElementById('refundAmount')?.value || 0;
    document.getElementById('customAmount').textContent = '$' + parseFloat(refundAmount).toFixed(2);
    
    const selectedTotal = parseFloat(document.getElementById('selectedAmount').textContent.replace('$', '')) || 0;
    const customTotal = parseFloat(refundAmount) || 0;
    const total = Math.max(selectedTotal, customTotal);
    
    document.getElementById('totalRefund').textContent = '$' + total.toFixed(2);
}

// Show/hide admin account field based on refund method
document.getElementById('refundMethod')?.addEventListener('change', function() {
    const adminField = document.getElementById('adminAccountField');
    adminField.style.display = (this.value === 'bank_transfer' || this.value === 'paypal') ? 'block' : 'none';
});

// Preview refund
function previewRefund() {
    const amount = document.getElementById('refundAmount').value;
    const reason = document.querySelector('select[name="reason"]').value;
    const method = document.getElementById('refundMethod').value;
    
    if (!amount || !reason || !method) {
        Swal.fire('Error!', 'Please fill all required fields.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Preview Refund',
        html: `
            <div class="text-start">
                <p><strong>Amount:</strong> $${parseFloat(amount).toFixed(2)}</p>
                <p><strong>Reason:</strong> ${reason.replace('_', ' ')}</p>
                <p><strong>Method:</strong> ${method.replace('_', ' ')}</p>
                <p><strong>Order Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                <p><strong>After Refund:</strong> $${(<?php echo $order['total_amount']; ?> - amount).toFixed(2)}</p>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: 'var(--warning)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: 'Process Refund'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('refundForm').submit();
        }
    });
}

// Process refund
function processRefund(refundId) {
    Swal.fire({
        title: 'Process Refund',
        text: 'Are you sure you want to process this refund?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'var(--success)',
        cancelButtonColor: 'var(--danger)',
        confirmButtonText: 'Yes, process it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/process-refund.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    refund_id: refundId,
                    action: 'process'
                })
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

// Cancel refund
function cancelRefund(refundId) {
    Swal.fire({
        title: 'Cancel Refund',
        text: 'Are you sure you want to cancel this refund request?',
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'Reason for cancellation',
        inputPlaceholder: 'Enter reason...',
        showCancelButton: true,
        confirmButtonColor: 'var(--danger)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: 'Yes, cancel it!'
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            fetch('ajax/process-refund.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    refund_id: refundId,
                    action: 'cancel',
                    reason: result.value
                })
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
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Form submission
document.getElementById('refundForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const amount = parseFloat(document.getElementById('refundAmount').value);
    const maxAmount = parseFloat(document.getElementById('refundAmount').max);
    
    if (amount > maxAmount) {
        Swal.fire('Error!', `Maximum refund amount is $${maxAmount.toFixed(2)}`, 'error');
        return;
    }
    
    Swal.fire({
        title: 'Confirm Refund',
        text: `Are you sure you want to refund $${amount.toFixed(2)}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'var(--warning)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: 'Yes, process refund!'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('refundAmount')?.addEventListener('input', updateCustomAmount);
});
</script>

<?php require_once '../includes/footer.php'; ?>