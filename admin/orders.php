<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User only.';
    redirect('admin/dashboard.php');
}

$page_title = 'My Orders';
require_once '../includes/header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter variables
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Build WHERE clause
    $where = ["user_id = ?"];
    $params = [$user_id];
    
    if (!empty($filter_status)) {
        $where[] = "status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($search)) {
        $where[] = "(order_number LIKE ? OR id LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_sql = 'WHERE ' . implode(' AND ', $where);
    
    // Get total orders count
    $count_sql = "SELECT COUNT(*) as total FROM orders $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = $stmt->fetch()['total'];
    $total_pages = ceil($total_orders / $limit);
    
    // Get orders with pagination
    $orders_sql = "SELECT * FROM orders $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
    $all_params = $params;
    $all_params[] = $limit;
    $all_params[] = $offset;
    
    $stmt = $db->prepare($orders_sql);
    $stmt->execute($all_params);
    $orders = $stmt->fetchAll();
    
    // Get order statuses for filter
    $stmt = $db->query("SELECT DISTINCT status FROM orders WHERE status != ''");
    $order_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get total spent
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as spent FROM orders WHERE user_id = ? AND payment_status = 'completed'");
    $stmt->execute([$user_id]);
    $total_spent = $stmt->fetch()['spent'];
    
} catch(PDOException $e) {
    $error = 'Error loading orders: ' . $e->getMessage();
    $orders = [];
    $total_orders = 0;
    $total_spent = 0;
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">My Orders</h1>
                <p class="text-muted mb-0">Total <?php echo $total_orders; ?> orders • Spent: $<?php echo number_format($total_spent, 2); ?></p>
            </div>
            <a href="<?php echo SITE_URL; ?>shop.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> New Order
            </a>
        </div>
        
        <!-- Filters Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search by Order #"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <?php foreach($order_statuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $filter_status == $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="orders.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo me-2"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Orders Cards View -->
        <div class="row">
            <?php if (empty($orders)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h4>No Orders Found</h4>
                        <p class="text-muted">You haven't placed any orders yet.</p>
                        <a href="<?php echo SITE_URL; ?>shop.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <?php foreach($orders as $order): 
                    // Determine badge color based on status
                    $status_color = 'secondary';
                    $status_icon = 'clock';
                    
                    switch($order['status']) {
                        case 'pending':
                            $status_color = 'warning';
                            $status_icon = 'clock';
                            break;
                        case 'processing':
                            $status_color = 'info';
                            $status_icon = 'sync-alt';
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
                    
                    // Format date
                    $order_date = '';
                    if (isset($order['order_date'])) {
                        $order_date = date('d M Y', strtotime($order['order_date']));
                    } elseif (isset($order['created_at'])) {
                        $order_date = date('d M Y', strtotime($order['created_at']));
                    }
                ?>
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">Order #<?php echo $order['order_number'] ?? $order['id']; ?></h5>
                                    <p class="text-muted small mb-0">
                                        <i class="far fa-calendar me-1"></i> <?php echo $order_date; ?>
                                    </p>
                                </div>
                                <span class="badge bg-<?php echo $status_color; ?>">
                                    <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Items</span>
                                    <span><?php echo $order['items_count'] ?? 'N/A'; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Total Amount</span>
                                    <span class="fw-bold">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Payment</span>
                                    <span class="badge bg-<?php echo $order['payment_status'] == 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                                
                                <?php if ($order['status'] == 'pending'): ?>
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </button>
                                <?php elseif ($order['status'] == 'delivered'): ?>
                                <button class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-redo me-1"></i> Reorder
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Order Status Guide -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Order Status Guide</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-warning me-3">Pending</span>
                            <small class="text-muted">Order placed, awaiting confirmation</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-info me-3">Processing</span>
                            <small class="text-muted">Order is being prepared</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary me-3">Shipped</span>
                            <small class="text-muted">Order is on the way</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-success me-3">Delivered</span>
                            <small class="text-muted">Order delivered successfully</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>