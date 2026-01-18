<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirectToDashboard();
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        redirect('../../vendor/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect('../../vendor/dashboard.php');
}

$page_title = 'My Orders';
require_once '../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

// Get vendor orders
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Build query
    $query = "SELECT DISTINCT o.*, u.username, u.full_name, u.email, u.phone,
                     GROUP_CONCAT(p.name SEPARATOR ', ') as product_names,
                     GROUP_CONCAT(p.id SEPARATOR ', ') as product_ids,
                     SUM(oi.subtotal) as order_total
              FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN products p ON oi.product_id = p.id
              JOIN users u ON o.user_id = u.id
              WHERE p.vendor_id = ?";
    $params = [$vendor_id];
    
    if ($search) {
        $query .= " AND (o.order_number LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status) {
        $query .= " AND o.status = ?";
        $params[] = $status;
    }
    
    if ($date_from) {
        $query .= " AND DATE(o.order_date) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $query .= " AND DATE(o.order_date) <= ?";
        $params[] = $date_to;
    }
    
    $query .= " GROUP BY o.id";
    
    // Get total count
    $count_query = "SELECT COUNT(DISTINCT o.id) as total FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    WHERE p.vendor_id = ?";
    $count_params = [$vendor_id];
    
    if ($search) {
        $count_query .= " AND (o.order_number LIKE ? OR u.username LIKE ?)";
        $count_params[] = "%$search%";
        $count_params[] = "%$search%";
    }
    if ($status) {
        $count_query .= " AND o.status = ?";
        $count_params[] = $status;
    }
    
    $stmt = $db->prepare($count_query);
    $stmt->execute($count_params);
    $total_orders = $stmt->fetch()['total'];
    $total_pages = ceil($total_orders / $limit);
    
    // Get orders with pagination
    $query .= " ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get order statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(oi.subtotal) as total_sales,
            AVG(oi.subtotal) as avg_order_value
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        AND o.status NOT IN ('cancelled')
    ");
    $stmt->execute([$vendor_id]);
    $order_stats = $stmt->fetch();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading orders: ' . $e->getMessage();
    $orders = [];
    $total_orders = 0;
    $total_pages = 1;
    $order_stats = ['total_orders' => 0, 'total_sales' => 0, 'avg_order_value' => 0];
}

// Update order status
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    try {
        $order_id = (int)$_POST['order_id'];
        $new_status = sanitize($_POST['status']);
        $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
        
        // Verify order belongs to vendor
        $stmt = $db->prepare("
            SELECT o.id FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE o.id = ? AND p.vendor_id = ?
            LIMIT 1
        ");
        $stmt->execute([$order_id, $vendor_id]);
        
        if ($stmt->rowCount() > 0) {
            // Update order status
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            
            // Add to status history
            $stmt = $db->prepare("
                INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $new_status, $vendor_id, $notes]);
            
            // Add order note
            if ($notes) {
                $stmt = $db->prepare("
                    INSERT INTO order_notes (order_id, user_id, note_type, note) 
                    VALUES (?, ?, 'internal', ?)
                ");
                $stmt->execute([$order_id, $vendor_id, "Status changed to $new_status: $notes"]);
            }
            
            $_SESSION['success'] = "Order status updated to $new_status successfully!";
            
            // Log activity
            logUserActivity($vendor_id, 'order_update', "Updated order #$order_id status to $new_status");
            
            // Send notification to customer
            sendOrderStatusUpdateNotification($order_id, $new_status);
            
            // Redirect to avoid resubmission
            redirect('orders.php');
        } else {
            $_SESSION['error'] = 'Order not found or access denied.';
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error updating order status: ' . $e->getMessage();
    }
}
?>

<div class="dashboard-container">
    <?php include '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">My Orders</h1>
                    <p class="text-muted mb-0">Manage and track customer orders</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="../../vendor/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Orders</h6>
                                <h2 class="mb-0"><?php echo $order_stats['total_orders'] ?? 0; ?></h2>
                            </div>
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-shopping-cart text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Sales</h6>
                                <h2 class="mb-0">$<?php echo number_format($order_stats['total_sales'] ?? 0, 2); ?></h2>
                            </div>
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-dollar-sign text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Avg Order Value</h6>
                                <h2 class="mb-0">$<?php echo number_format($order_stats['avg_order_value'] ?? 0, 2); ?></h2>
                            </div>
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-chart-line text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search by order # or customer..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $status == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="orders.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Orders Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No orders found</h4>
                        <p class="text-muted">Orders will appear here when customers purchase your products</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Products</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): 
                                    $product_ids = explode(',', $order['product_ids']);
                                    $product_names = explode(', ', $order['product_names']);
                                ?>
                                <tr>
                                    <td>
                                        <a href="view.php?id=<?php echo $order['id']; ?>" class="fw-bold text-decoration-none">
                                            #<?php echo $order['order_number']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo $order['full_name']; ?></div>
                                            <small class="text-muted">@<?php echo $order['username']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo count($product_ids); ?> item<?php echo count($product_ids) > 1 ? 's' : ''; ?>
                                        </small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($order['order_total'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status_color = 'secondary';
                                        if ($order['status'] == 'pending') $status_color = 'warning';
                                        if ($order['status'] == 'processing') $status_color = 'info';
                                        if ($order['status'] == 'shipped') $status_color = 'primary';
                                        if ($order['status'] == 'delivered') $status_color = 'success';
                                        if ($order['status'] == 'cancelled') $status_color = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <i class="fas fa-<?php 
                                                echo $order['status'] == 'pending' ? 'clock' : 
                                                     ($order['status'] == 'processing' ? 'cog' : 
                                                     ($order['status'] == 'shipped' ? 'truck' : 
                                                     ($order['status'] == 'delivered' ? 'check' : 'times'))); 
                                            ?> me-1"></i>
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $payment_color = $order['payment_status'] == 'completed' ? 'success' : 
                                                       ($order['payment_status'] == 'pending' ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge bg-<?php echo $payment_color; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $order['id']; ?>" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="invoice.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-info" title="View Invoice">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                        </div>
                                        
                                        <!-- Status Update Modal -->
                                        <div class="modal fade" id="statusModal<?php echo $order['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Update Order Status</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Order #<?php echo $order['order_number']; ?></label>
                                                                <div class="alert alert-info">
                                                                    <small>
                                                                        Customer: <?php echo $order['full_name']; ?><br>
                                                                        Current Status: <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst($order['status']); ?></span>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">New Status *</label>
                                                                <select name="status" class="form-select" required>
                                                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                                    <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Notes (Optional)</label>
                                                                <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes for this status update..."></textarea>
                                                            </div>
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="update_status" class="btn btn-primary">
                                                                <i class="fas fa-save me-2"></i> Update Status
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Order Status Guide -->
        <div class="row g-4 mt-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-info-circle me-2 text-primary"></i> Order Status Guide
                        </h5>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-warning me-3">Pending</span>
                                    <small class="text-muted">Order received, awaiting processing</small>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-info me-3">Processing</span>
                                    <small class="text-muted">Order is being prepared</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-primary me-3">Shipped</span>
                                    <small class="text-muted">Order has been dispatched</small>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-success me-3">Delivered</span>
                                    <small class="text-muted">Order delivered to customer</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-chart-line me-2 text-success"></i> Quick Actions
                        </h5>
                        <div class="text-center py-3">
                            <a href="../reports/sales.php" class="btn btn-outline-success me-2 mb-2">
                                <i class="fas fa-chart-bar me-1"></i> Sales Report
                            </a>
                            <a href="../earnings/earnings.php" class="btn btn-outline-warning me-2 mb-2">
                                <i class="fas fa-money-bill-wave me-1"></i> View Earnings
                            </a>
                            <a href="export.php" class="btn btn-outline-info mb-2">
                                <i class="fas fa-download me-1"></i> Export Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.table th {
    background: #f8f9fa;
    font-weight: 600;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.modal-content {
    border-radius: 10px;
    border: none;
}

.badge {
    font-weight: 500;
}
</style>

<script>
// Auto-close alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Date range validation
document.querySelector('form').addEventListener('submit', function(e) {
    const dateFrom = document.querySelector('[name="date_from"]').value;
    const dateTo = document.querySelector('[name="date_to"]').value;
    
    if (dateFrom && dateTo && dateFrom > dateTo) {
        e.preventDefault();
        alert('"From Date" cannot be after "To Date"');
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>