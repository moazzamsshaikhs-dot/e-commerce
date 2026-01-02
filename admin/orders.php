<?php
require_once './includes/config.php';
require_once './includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'Orders Management';
require_once './includes/header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filter variables
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_payment = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$customer_filter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : '';

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filter_status)) {
        $where[] = "o.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_payment)) {
        $where[] = "o.payment_status = ?";
        $params[] = $filter_payment;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(o.order_date) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(o.order_date) <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($customer_filter)) {
        $where[] = "o.user_id = ?";
        $params[] = $customer_filter;
    }
    
    if (!empty($search)) {
        $where[] = "(o.order_number LIKE ? OR o.id LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total orders count
    $count_sql = "SELECT COUNT(*) as total 
                  FROM orders o
                  LEFT JOIN users u ON o.user_id = u.id
                  WHERE $where_sql";
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = $stmt->fetch()['total'];
    $total_pages = ceil($total_orders / $limit);
    
    // Get orders with details
    $orders_sql = "SELECT o.*, 
                          u.full_name,
                          u.email,
                          u.phone,
                          COUNT(oi.id) as items_count,
                          SUM(oi.quantity) as total_items,
                          sc.name as carrier_name
                   FROM orders o
                   LEFT JOIN users u ON o.user_id = u.id
                   LEFT JOIN order_items oi ON o.id = oi.order_id
                   LEFT JOIN shipping_carriers sc ON o.shipping_carrier_id = sc.id
                   WHERE $where_sql
                   GROUP BY o.id
                   ORDER BY o.order_date DESC
                   LIMIT ? OFFSET ?";
    
    $all_params = array_merge($params, [$limit, $offset]);
    
    $stmt = $db->prepare($orders_sql);
    $stmt->execute($all_params);
    $orders = $stmt->fetchAll();
    
    // Get statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                    SUM(total_amount) as total_sales,
                    AVG(total_amount) as avg_order_value
                  FROM orders
                  WHERE DATE(order_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $stmt = $db->query($stats_sql);
    $stats = $stmt->fetch();
    
    // Get order statuses
    $stmt = $db->query("SELECT DISTINCT status FROM orders WHERE status != '' ORDER BY status");
    $order_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get payment statuses
    $stmt = $db->query("SELECT DISTINCT payment_status FROM orders WHERE payment_status != '' ORDER BY payment_status");
    $payment_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get customers for filter
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE user_type = 'user' ORDER BY full_name");
    $customers = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error loading orders: ' . $e->getMessage();
    $orders = [];
    $total_orders = 0;
    $stats = [];
    $order_statuses = [];
    $payment_statuses = [];
    $customers = [];
}
?>

<div class="dashboard-container">
    <?php include './includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Orders Management</h1>
                <p class="text-muted mb-0">Total <?php echo number_format($total_orders); ?> orders</p>
            </div>
            <div>
                <a href="export-orders.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-file-export me-2"></i> Export
                </a>
                <a href="create-order.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Create Order
                </a>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Orders
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_orders'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Pending
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['pending_orders'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Processing
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['processing_orders'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-cogs fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Shipped
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['shipped_orders'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shipping-fast fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Delivered
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['delivered_orders'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Sales (30 days)
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?php echo number_format($stats['total_sales'] ?? 0, 2); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search by Order #, Name, Email, Phone"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <?php foreach($order_statuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $filter_status == $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="payment_status" class="form-select">
                            <option value="">All Payment Status</option>
                            <?php foreach($payment_statuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $filter_payment == $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" 
                               name="start_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($start_date); ?>"
                               placeholder="Start Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" 
                               name="end_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($end_date); ?>"
                               placeholder="End Date">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Advanced Filters -->
                <div class="row mt-3">
                    <div class="col-md-4">
                        <select name="customer_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Customers</option>
                            <?php foreach($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo $customer_filter == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['full_name'] . ' (' . $customer['email'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 text-end">
                        <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-redo me-2"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Orders Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                    <h5>No Orders Found</h5>
                                    <p class="text-muted">No orders match your search criteria</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($orders as $order): 
                                    // Status badge color
                                    $status_color = 'secondary';
                                    $status_icon = 'clock';
                                    
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
                                    
                                    // Format date
                                    $order_date = date('d M Y', strtotime($order['order_date']));
                                    $order_time = date('h:i A', strtotime($order['order_date']));
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="order-checkbox" value="<?php echo $order['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="fw-bold">
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                <?php echo $order['order_number']; ?>
                                            </a>
                                        </div>
                                        <small class="text-muted">ID: #<?php echo $order['id']; ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($order['full_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo $order_date; ?></div>
                                        <small class="text-muted"><?php echo $order_time; ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo $order['total_items'] ?? $order['items_count'] ?? 0; ?></div>
                                        <small class="text-muted">items</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                        <?php if (!empty($order['carrier_name'])): ?>
                                        <small class="text-muted">via <?php echo $order['carrier_name']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                        <?php if ($order['priority'] == 'high'): ?>
                                        <span class="badge bg-danger ms-1">High</span>
                                        <?php elseif ($order['priority'] == 'urgent'): ?>
                                        <span class="badge bg-danger ms-1">Urgent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $payment_color; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" 
                                                        class="btn btn-outline-secondary dropdown-toggle"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#">
                                                            <i class="fas fa-edit me-2"></i> Edit Order
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="invoice.php?id=<?php echo $order['id']; ?>" target="_blank">
                                                            <i class="fas fa-print me-2"></i> Print Invoice
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'processing')">
                                                            <i class="fas fa-cogs me-2"></i> Mark as Processing
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'shipped')">
                                                            <i class="fas fa-shipping-fast me-2"></i> Mark as Shipped
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'delivered')">
                                                            <i class="fas fa-check-circle me-2"></i> Mark as Delivered
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                                            <i class="fas fa-times me-2"></i> Cancel Order
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Bulk Actions -->
                <div class="card-footer border-0 bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <select class="form-select form-select-sm d-inline-block w-auto" id="bulkAction">
                            <option value="">Bulk Actions</option>
                            <option value="processing">Mark as Processing</option>
                            <option value="shipped">Mark as Shipped</option>
                            <option value="delivered">Mark as Delivered</option>
                            <option value="cancelled">Cancel Orders</option>
                            <option value="export">Export Selected</option>
                            <option value="delete">Delete Orders</option>
                        </select>
                        <button class="btn btn-sm btn-primary ms-2" onclick="applyBulkAction()">
                            <i class="fas fa-play me-1"></i> Apply
                        </button>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Select all checkbox
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Update order status
function updateOrderStatus(orderId, status) {
    Swal.fire({
        title: 'Update Order Status',
        text: `Are you sure you want to mark this order as ${status}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/update-order-status.php?id=${orderId}&status=${status}`)
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
function cancelOrder(orderId) {
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
            fetch(`ajax/cancel-order.php?id=${orderId}`, {
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

// Bulk actions
function applyBulkAction() {
    const selectedOrders = [];
    document.querySelectorAll('.order-checkbox:checked').forEach(checkbox => {
        selectedOrders.push(checkbox.value);
    });
    
    if (selectedOrders.length === 0) {
        Swal.fire('Warning!', 'Please select at least one order.', 'warning');
        return;
    }
    
    const action = document.getElementById('bulkAction').value;
    if (!action) {
        Swal.fire('Warning!', 'Please select an action.', 'warning');
        return;
    }
    
    if (action === 'export') {
        // Export selected orders
        const orderIds = selectedOrders.join(',');
        window.open(`export-orders.php?ids=${orderIds}`, '_blank');
        return;
    }
    
    let confirmText = '';
    let confirmIcon = 'question';
    
    switch(action) {
        case 'processing':
            confirmText = `Mark ${selectedOrders.length} order(s) as Processing?`;
            break;
        case 'shipped':
            confirmText = `Mark ${selectedOrders.length} order(s) as Shipped?`;
            break;
        case 'delivered':
            confirmText = `Mark ${selectedOrders.length} order(s) as Delivered?`;
            break;
        case 'cancelled':
            confirmText = `Cancel ${selectedOrders.length} order(s)?`;
            confirmIcon = 'warning';
            break;
        case 'delete':
            confirmText = `Delete ${selectedOrders.length} order(s) permanently? This cannot be undone!`;
            confirmIcon = 'error';
            break;
    }
    
    Swal.fire({
        title: 'Confirm Action',
        text: confirmText,
        icon: confirmIcon,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, proceed!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/bulk-order-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_ids: selectedOrders,
                    action: action
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
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Quick status update buttons
function quickStatusUpdate(orderId, status) {
    const statusMap = {
        'processing': { icon: 'cogs', color: 'info' },
        'shipped': { icon: 'shipping-fast', color: 'primary' },
        'delivered': { icon: 'check-circle', color: 'success' }
    };
    
    const statusInfo = statusMap[status];
    if (!statusInfo) return;
    
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    updateOrderStatus(orderId, status);
}
</script>

<?php require_once './includes/footer.php'; ?>