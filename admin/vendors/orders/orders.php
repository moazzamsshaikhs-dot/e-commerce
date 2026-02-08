<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

// Check if vendor is approved
$vendor_id = $_SESSION['user_id'];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved.';
        redirect(SITE_URL . 'admin/vendor/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    redirect(SITE_URL . 'admin/vendor/dashboard.php');
    exit();
}

$page_title = 'My Orders';
require_once '../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Get vendor orders
try {
    $db = getDB();
    
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
    
    if (!empty($search)) {
        $query .= " AND (o.order_number LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status)) {
        $query .= " AND o.status = ?";
        $params[] = $status;
    }
    
    if (!empty($date_from)) {
        $query .= " AND DATE(o.order_date) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
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
    
    if (!empty($search)) {
        $count_query .= " AND (o.order_number LIKE ? OR u.username LIKE ?)";
        $count_params[] = "%$search%";
        $count_params[] = "%$search%";
    }
    if (!empty($status)) {
        $count_query .= " AND o.status = ?";
        $count_params[] = $status;
    }
    
    $stmt = $db->prepare($count_query);
    $stmt->execute($count_params);
    $total_result = $stmt->fetch();
    $total_orders = $total_result['total'] ?? 0;
    $total_pages = ceil($total_orders / $limit);
    
    // Get orders with pagination
    $query .= " ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $paramType = PDO::PARAM_STR;
        
        // Check if it's the last two parameters (LIMIT and OFFSET)
        if ($key === count($params) - 2 || $key === count($params) - 1) {
            $paramType = PDO::PARAM_INT;
            $value = (int)$value;
        }
        
        $stmt->bindValue($key + 1, $value, $paramType);
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll();
    
    // Get order statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(oi.subtotal), 0) as total_sales,
            COALESCE(AVG(oi.subtotal), 0) as avg_order_value
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
?>
<?php include_once '../../includes/header.php'; ?>
<div class="dashboard-container">
    <?php 
    // Check if vendor sidebar exists
    include_once '../../includes/vendor-sidebar.php';
    ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">My Orders</h1>
                    <p class="text-muted mb-0">Manage and track customer orders</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="<?php echo SITE_URL; ?>admin/vendor/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Orders</h6>
                                <h2 class="mb-0"><?php echo number_format($order_stats['total_orders'] ?? 0); ?></h2>
                            </div>
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-shopping-cart text-primary fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Sales</h6>
                                <h2 class="mb-0">$<?php echo number_format($order_stats['total_sales'] ?? 0, 2); ?></h2>
                            </div>
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-dollar-sign text-success fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Avg Order Value</h6>
                                <h2 class="mb-0">$<?php echo number_format($order_stats['avg_order_value'] ?? 0, 2); ?></h2>
                            </div>
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-chart-line text-warning fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3" id="filterForm">
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
                        <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="orders.php" class="btn btn-outline-secondary w-100" title="Reset">
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
                                    $product_ids = !empty($order['product_ids']) ? explode(',', $order['product_ids']) : [];
                                    $product_names = !empty($order['product_names']) ? explode(', ', $order['product_names']) : [];
                                ?>
                                <tr>
                                    <td>
                                        <a href="view.php?id=<?php echo $order['id']; ?>" class="fw-bold text-decoration-none text-primary">
                                            #<?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($order['full_name'] ?? 'Guest'); ?></div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($order['username'] ?? 'guest'); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo count($product_ids); ?> item<?php echo count($product_ids) > 1 ? 's' : ''; ?>
                                        </small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($order['order_total'] ?? 0, 2); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        if ($order['status'] == 'pending') $status_class = 'status-badge-pending';
                                        if ($order['status'] == 'processing') $status_class = 'status-badge-processing';
                                        if ($order['status'] == 'shipped') $status_class = 'status-badge-shipped';
                                        if ($order['status'] == 'delivered') $status_class = 'status-badge-delivered';
                                        if ($order['status'] == 'cancelled') $status_class = 'status-badge-cancelled';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
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
                                        $payment_class = '';
                                        if ($order['payment_status'] == 'pending') $payment_class = 'payment-badge-pending';
                                        if ($order['payment_status'] == 'completed') $payment_class = 'payment-badge-completed';
                                        if ($order['payment_status'] == 'failed') $payment_class = 'payment-badge-failed';
                                        ?>
                                        <span class="badge <?php echo $payment_class; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="update_status.php?order_id=<?php echo $order['id']; ?>" class="btn btn-outline-warning" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if (file_exists('invoice.php')): ?>
                                            <a href="invoice.php?order_id=<?php echo $order['id']; ?>" class="btn btn-outline-info" title="View Invoice" target="_blank">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                            <?php endif; ?>
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
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                        <div class="text-center text-muted mt-2">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?> | Total Orders: <?php echo $total_orders; ?>
                        </div>
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
                                    <span class="badge status-badge-pending me-3">Pending</span>
                                    <small class="text-muted">Order received, awaiting processing</small>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge status-badge-processing me-3">Processing</span>
                                    <small class="text-muted">Order is being prepared</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge status-badge-shipped me-3">Shipped</span>
                                    <small class="text-muted">Order has been dispatched</small>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge status-badge-delivered me-3">Delivered</span>
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
                            <?php if (file_exists('../reports/sales.php')): ?>
                            <a href="../reports/sales.php" class="btn btn-outline-success me-2 mb-2">
                                <i class="fas fa-chart-bar me-1"></i> Sales Report
                            </a>
                            <?php endif; ?>
                            
                            <?php if (file_exists('../earnings/earnings.php')): ?>
                            <a href="../earnings/earnings.php" class="btn btn-outline-warning me-2 mb-2">
                                <i class="fas fa-money-bill-wave me-1"></i> View Earnings
                            </a>
                            <?php endif; ?>
                            
                            <?php if (file_exists('export.php')): ?>
                            <a href="export.php" class="btn btn-outline-info mb-2">
                                <i class="fas fa-download me-1"></i> Export Orders
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Safe JavaScript with null checks
document.addEventListener('DOMContentLoaded', function() {
    console.log('Orders page loaded - initializing scripts');
    
    // Auto-close alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(function(alert) {
                try {
                    if (alert && bootstrap && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    } else {
                        // Fallback if Bootstrap not available
                        alert.style.display = 'none';
                    }
                } catch (e) {
                    console.log('Could not close alert:', e);
                }
            });
        }, 5000);
    }
    
    // Date range validation
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            const dateFrom = this.querySelector('[name="date_from"]');
            const dateTo = this.querySelector('[name="date_to"]');
            
            if (dateFrom && dateTo && dateFrom.value && dateTo.value) {
                const fromDate = new Date(dateFrom.value);
                const toDate = new Date(dateTo.value);
                
                if (fromDate > toDate) {
                    e.preventDefault();
                    alert('Error: "From Date" cannot be after "To Date"');
                    dateFrom.focus();
                    return false;
                }
            }
            return true;
        });
    }
    
    // Initialize tooltips for action buttons
    const actionButtons = document.querySelectorAll('.btn-group-sm .btn[title]');
    if (actionButtons.length > 0 && bootstrap && bootstrap.Tooltip) {
        actionButtons.forEach(function(button) {
            try {
                new bootstrap.Tooltip(button);
            } catch (e) {
                console.log('Could not initialize tooltip:', e);
            }
        });
    }
    
    // Add confirmation for status update links
    const statusUpdateLinks = document.querySelectorAll('a[href*="update_status.php"]');
    statusUpdateLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to update the order status?')) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    console.log('All scripts initialized successfully');
});

// Global error handler to catch any unhandled errors
window.addEventListener('error', function(e) {
    console.error('Error caught:', e.message, 'at', e.filename, ':', e.lineno);
    return false;
});
</script>
<style>
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        background: #f8f9fa;
    }
    
    .main-content {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
    }
    
    .dashboard-header {
        color: white;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        border-radius: 10px;
        transition: transform 0.3s;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    }
    
    .table th {
        font-weight: 600;
        background: #f8f9fa;
    }
    
    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 500;
    }
    
    .pagination .page-item.active .page-link {
        background-color: #4361ee;
        border-color: #4361ee;
    }
    
    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
    }
    
    @media (max-width: 768px) {
        .dashboard-container {
            flex-direction: column;
        }
        
        .main-content {
            padding: 15px;
        }
        
        .table-responsive {
            font-size: 14px;
        }
    }
    
    .status-badge-pending { background-color: #ffc107; color: #fff; }
    .status-badge-processing { background-color: #0dcaf0; color: #fff; }
    .status-badge-shipped { background-color: #0d6efd; color: #fff; }
    .status-badge-delivered { background-color: #198754; color: #fff; }
    .status-badge-cancelled { background-color: #dc3545; color: #fff; }
    
    .payment-badge-pending { background-color: #ffc107; color: #fff; }
    .payment-badge-completed { background-color: #198754; color: #fff; }
    .payment-badge-failed { background-color: #dc3545; color: #fff; }
    </style>
<?php 
// Check if footer exists
require_once '../../includes/footer.php';
?>
</body>
</html>