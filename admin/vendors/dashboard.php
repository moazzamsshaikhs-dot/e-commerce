<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}else if(!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please log in to access the vendor dashboard.';
    redirect(SITE_URL . 'login.php');
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['warning'] = 'Your vendor account is pending approval. Please wait for admin approval.';
    }
} catch(PDOException $e) {
    // Continue anyway
}

$page_title = 'Vendor Dashboard';
require_once '../includes/header.php';

// Get vendor statistics
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Total Products
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $total_products = $stmt->fetch()['total'];
    
    // Approved Products
    $stmt = $db->prepare("SELECT COUNT(*) as approved FROM products WHERE vendor_id = ? AND approved_status = 'approved'");
    $stmt->execute([$vendor_id]);
    $approved_products = $stmt->fetch()['approved'];
    
    // Pending Products
    $stmt = $db->prepare("SELECT COUNT(*) as pending FROM products WHERE vendor_id = ? AND approved_status = 'pending'");
    $stmt->execute([$vendor_id]);
    $pending_products = $stmt->fetch()['pending'];
    
    // Total Orders
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT oi.order_id) as total 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE p.vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $total_orders = $stmt->fetch()['total'];
    
    // Total Earnings
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(ve.vendor_amount), 0) as total_earnings 
        FROM vendor_earnings ve 
        WHERE ve.vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $total_earnings = $stmt->fetch()['total_earnings'];
    
    // Pending Earnings
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(ve.vendor_amount), 0) as pending_earnings 
        FROM vendor_earnings ve 
        WHERE ve.vendor_id = ? AND ve.status IN ('pending', 'processing')
    ");
    $stmt->execute([$vendor_id]);
    $pending_earnings = $stmt->fetch()['pending_earnings'];
    
    // Paid Earnings
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(ve.vendor_amount), 0) as paid_earnings 
        FROM vendor_earnings ve 
        WHERE ve.vendor_id = ? AND ve.status = 'paid'
    ");
    $stmt->execute([$vendor_id]);
    $paid_earnings = $stmt->fetch()['paid_earnings'];
    
    // Recent Orders
    $stmt = $db->prepare("
        SELECT o.*, u.username, u.full_name,
               GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE p.vendor_id = ?
        GROUP BY o.id
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stmt->execute([$vendor_id]);
    $recent_orders = $stmt->fetchAll();
    
    // Low Stock Products
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE vendor_id = ? AND stock > 0 AND stock < 10 
        ORDER BY stock ASC 
        LIMIT 5
    ");
    $stmt->execute([$vendor_id]);
    $low_stock = $stmt->fetchAll();
    
    // Recent Reviews
    $stmt = $db->prepare("
        SELECT r.*, p.name as product_name, u.username as customer_name
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        JOIN users u ON r.user_id = u.id
        WHERE p.vendor_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$vendor_id]);
    $recent_reviews = $stmt->fetchAll();
    
    // Get vendor details
    $stmt = $db->prepare("
        SELECT vendor_category, vendor_rating, vendor_since, total_sales 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor_details = $stmt->fetch();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading dashboard data: ' . $e->getMessage();
    error_log("Vendor Dashboard Error: " . $e->getMessage());
}

// Log dashboard access
logUserActivity($_SESSION['user_id'], 'dashboard_access', 'Accessed vendor dashboard');
?>

<div class="dashboard-container">
    <!-- Include Vendor Sidebar -->
    <?php    
        include_once '../includes/vendor-sidebar.php';    
    ?>
    
    <main class="main-content">
        <!-- Welcome Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Welcome, <?php echo $_SESSION['full_name']; ?>!</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-store me-1 text-success"></i>
                        Vendor Dashboard 
                        <?php if (isset($vendor_status) && $vendor_status === 'approved'): ?>
                            <span class="badge bg-success ms-2">
                                <i class="fas fa-check-circle me-1"></i> Approved
                            </span>
                        <?php elseif (isset($vendor_status) && $vendor_status === 'pending'): ?>
                            <span class="badge bg-warning ms-2">
                                <i class="fas fa-clock me-1"></i> Pending Approval
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-3">
                    <?php if (isset($vendor_status) && $vendor_status === 'approved'): ?>
                        <a href="products/add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Product
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt me-2"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Vendor Info Bar -->
            <?php if (isset($vendor_details) && $vendor_status === 'approved'): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="d-flex flex-wrap gap-4">
                        <div class="d-flex align-items-center bg-light p-3 rounded">
                            <i class="fas fa-tag me-2 text-primary fs-5"></i>
                            <div>
                                <small class="text-muted d-block">Category</small>
                                <strong><?php echo $vendor_details['vendor_category'] ?? 'Not set'; ?></strong>
                            </div>
                        </div>
                        <div class="d-flex align-items-center bg-light p-3 rounded">
                            <i class="fas fa-star me-2 text-warning fs-5"></i>
                            <div>
                                <small class="text-muted d-block">Rating</small>
                                <strong><?php echo number_format($vendor_details['vendor_rating'] ?? 0, 1); ?>/5.0</strong>
                            </div>
                        </div>
                        <div class="d-flex align-items-center bg-light p-3 rounded">
                            <i class="fas fa-calendar-alt me-2 text-success fs-5"></i>
                            <div>
                                <small class="text-muted d-block">Vendor Since</small>
                                <strong><?php echo date('M Y', strtotime($vendor_details['vendor_since'] ?? date('Y-m-d'))); ?></strong>
                            </div>
                        </div>
                        <div class="d-flex align-items-center bg-light p-3 rounded">
                            <i class="fas fa-chart-line me-2 text-info fs-5"></i>
                            <div>
                                <small class="text-muted d-block">Total Sales</small>
                                <strong><?php echo $vendor_details['total_sales'] ?? 0; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pending Approval Alert -->
        <?php if (isset($vendor_status) && $vendor_status !== 'approved'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-3 fa-2x"></i>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">Account Pending Approval</h5>
                    <p class="mb-0">
                        Your vendor account is <strong><?php echo $vendor_status; ?></strong>.
                        <?php if ($vendor_status === 'pending'): ?>
                            Please wait for admin approval to start selling. You can complete your profile in the meantime.
                        <?php elseif ($vendor_status === 'rejected'): ?>
                            Your application was rejected. Please contact admin for details.
                        <?php elseif ($vendor_status === 'suspended'): ?>
                            Your account is suspended. Contact admin for support.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <!-- Total Products -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Products</h6>
                                <h2 class="mb-0"><?php echo $total_products ?? 0; ?></h2>
                                <small class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <?php echo $approved_products ?? 0; ?> Approved
                                </small>
                            </div>
                            <div class="stats-icon primary">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <?php if (isset($vendor_status) && $vendor_status === 'approved'): ?> 
                            <a href="products/products.php" class="text-decoration-none small d-flex align-items-center">
                                <i class="fas fa-eye me-2"></i> Manage Products
                            </a>
                            <?php else: ?>
                            <a href="verify.php" class="text-decoration-none small d-flex align-items-center">
                                <i class="fas fa-user-edit me-2"></i> Complete Profile
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Orders -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Orders</h6>
                                <h2 class="mb-0"><?php echo $total_orders ?? 0; ?></h2>
                                <small class="text-muted">
                                    From your products
                                </small>
                            </div>
                            <div class="stats-icon success">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <?php if (isset($vendor_status) && $vendor_status === 'approved'): ?>
                            <a href="orders/orders.php" class="text-decoration-none small d-flex align-items-center">
                                <i class="fas fa-list-alt me-2"></i> View Orders
                            </a>
                            <?php else: ?>
                            <a href="verify.php" class="text-decoration-none small d-flex align-items-center">
                                <i class="fas fa-user-edit me-2"></i> Complete Profile
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Earnings -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Earnings</h6>
                                <h2 class="mb-0">$<?php echo number_format($total_earnings ?? 0, 2); ?></h2>
                                <small class="text-success">
                                    <i class="fas fa-check me-1"></i>
                                    $<?php echo number_format($paid_earnings ?? 0, 2); ?> Paid
                                </small>
                            </div>
                            <div class="stats-icon warning">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <?php if (isset($vendor_status) && $vendor_status === 'approved'): ?>
                            <a href="earnings/earnings.php" class="text-decoration-none small d-flex align-items-center">
                                <i class="fas fa-chart-line me-2"></i> View Earnings
                            </a>
                            <?php else: ?>
                            <a href="verify.php" class="text-decoration-none small d-flex align-items-center">
                                <i class="fas fa-user-edit me-2"></i> Complete Profile
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Products -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Pending Products</h6>
                                <h2 class="mb-0"><?php echo $pending_products ?? 0; ?></h2>
                                <small class="text-warning">
                                    <i class="fas fa-clock me-1"></i>
                                    Awaiting approval
                                </small>
                            </div>
                            <div class="stats-icon info">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <?php if (isset($vendor_status) && $vendor_status === 'approved'): ?>
                            <a href="products/pending.php" class="text-decoration-none small d-flex align-items-center">
                                <i class="fas fa-clock me-2"></i> View Pending
                            </a>
                            <?php else: ?>
                            <a href="verify.php" class="text-decoration-none small d-flex align-items-center">
                                <i class="fas fa-user-edit me-2"></i> Complete Profile
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders & Low Stock -->
        <div class="row g-4">
            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Recent Orders</h5>
                        <a href="orders/orders.php" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No orders yet</p>
                                <p class="text-muted small">Orders will appear here when customers purchase your products</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Products</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="orders/view.php?id=<?php echo $order['id']; ?>" class="text-decoration-none fw-bold">
                                                    #<?php echo $order['order_number']; ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2">
                                                        <i class="fas fa-user text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <div><?php echo $order['full_name'] ?? $order['username']; ?></div>
                                                        <small class="text-muted">@<?php echo $order['username']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php 
                                                    $products = explode(', ', $order['product_names']);
                                                    echo count($products) . ' item' . (count($products) > 1 ? 's' : '');
                                                    ?>
                                                </small>
                                            </td>
                                            <td class="fw-bold">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] == 'delivered' ? 'success' : 
                                                         ($order['status'] == 'pending' ? 'warning' : 'info'); 
                                                ?>">
                                                    <i class="fas fa-<?php 
                                                        echo $order['status'] == 'delivered' ? 'check' : 
                                                             ($order['status'] == 'pending' ? 'clock' : 'truck'); 
                                                    ?> me-1"></i>
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Low Stock & Reviews -->
            <div class="col-lg-4">
                <!-- Low Stock Products -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Low Stock Alert</h5>
                        <span class="badge bg-danger"><?php echo count($low_stock); ?> items</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($low_stock)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p class="text-muted mb-0">All products have sufficient stock</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($low_stock as $product): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo $product['name']; ?></h6>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-3" style="height: 8px;">
                                                    <div class="progress-bar bg-danger" 
                                                         style="width: <?php echo ($product['stock'] / 10) * 100; ?>%"></div>
                                                </div>
                                                <small class="text-danger fw-bold"><?php echo $product['stock']; ?> left</small>
                                            </div>
                                        </div>
                                        <a href="products/edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit me-1"></i> Restock
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="products/low-stock.php" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-exclamation-triangle me-1"></i> View All Low Stock
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Reviews -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Recent Reviews</h5>
                        <a href="reviews/reviews.php" class="btn btn-sm btn-outline-primary">
                            All Reviews
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_reviews)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-star fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No reviews yet</p>
                                <p class="text-muted small">Reviews will appear here when customers rate your products</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($recent_reviews as $review): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="fas fa-user text-muted"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="mb-1"><?php echo $review['customer_name']; ?></h6>
                                                <small class="text-muted"><?php echo date('d M', strtotime($review['created_at'])); ?></small>
                                            </div>
                                            <div class="mb-2">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <p class="text-muted small mb-0"><?php echo substr($review['review_text'], 0, 80); ?>...</p>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-box me-1"></i><?php echo $review['product_name']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row g-4 mt-4">
            <?php if (isset($vendor_status) && $vendor_status === 'approved'): ?>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <i class="fas fa-plus-circle fa-2x text-primary"></i>
                        </div>
                        <h6 class="fw-bold">Add Product</h6>
                        <p class="text-muted small mb-3">List new product for sale</p>
                        <a href="products/add.php" class="btn btn-outline-primary btn-sm w-100">Add Now</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <i class="fas fa-chart-line fa-2x text-success"></i>
                        </div>
                        <h6 class="fw-bold">View Reports</h6>
                        <p class="text-muted small mb-3">Sales and performance reports</p>
                        <a href="reports/sales.php" class="btn btn-outline-success btn-sm w-100">View Reports</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="avatar-lg bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <i class="fas fa-wallet fa-2x text-warning"></i>
                        </div>
                        <h6 class="fw-bold">Withdraw Earnings</h6>
                        <p class="text-muted small mb-3">Transfer money to your account</p>
                        <a href="earnings/withdraw.php" class="btn btn-outline-warning btn-sm w-100">Withdraw</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="avatar-lg bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <i class="fas fa-headset fa-2x text-info"></i>
                        </div>
                        <h6 class="fw-bold">Get Support</h6>
                        <p class="text-muted small mb-3">Vendor support center</p>
                        <a href="help/support.php" class="btn btn-outline-info btn-sm w-100">Contact Support</a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Pending Approval Actions -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center p-5">
                        <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4">
                            <i class="fas fa-store-alt fa-3x text-muted"></i>
                        </div>
                        <h3 class="mb-3 fw-bold">Account Pending Approval</h3>
                        <p class="text-muted mb-4">
                            Your vendor account is <span class="fw-bold"><?php echo $vendor_status; ?></span>. 
                            You need admin approval to access all vendor features.
                        </p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <a href="profile.php" class="btn btn-primary px-4">
                                <i class="fas fa-user-edit me-2"></i> Complete Profile
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/vendors/dashboard.php" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-shopping-cart me-2"></i> Continue Shopping
                            </a>
                            <a href="<?php echo SITE_URL; ?>contact.php" class="btn btn-outline-info px-4">
                                <i class="fas fa-headset me-2"></i> Contact Admin
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pending Earnings Card -->
        <?php if (isset($vendor_status) && $vendor_status === 'approved' && $pending_earnings > 0): ?>
        <div class="card border-0 shadow-sm mt-4 border-start border-5 border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1">
                            <i class="fas fa-money-check-alt me-2 text-warning"></i>
                            Pending Earnings Available
                        </h5>
                        <p class="text-muted mb-0">You have $<?php echo number_format($pending_earnings, 2); ?> ready to withdraw</p>
                    </div>
                    <a href="earnings/withdraw.php" class="btn btn-warning">
                        <i class="fas fa-wallet me-2"></i> Withdraw Now
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Dashboard CSS -->
<style>
.dashboard-container {
    display: flex;
    min-height: 100vh;
    background-color: #f8f9fa;
}

.main-content {
    flex: 1;
    padding: 20px;
    min-height: 100vh;
    /* margin-left: 250px; */
    transition: margin-left 0.3s ease;
}

@media (max-width: 991.98px) {
    .main-content {
        margin-left: 0;
        padding-top: 70px;
    }
}

/* Stats Cards */
.stats-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
}

.stats-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stats-icon.primary {
    background: rgba(67, 97, 238, 0.1);
    color: #4361ee;
}

.stats-icon.success {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.stats-icon.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.stats-icon.info {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

/* Table styles */
.table th {
    font-weight: 600;
    border-top: none;
    color: #495057;
    background: #f8f9fa;
}

.table td {
    vertical-align: middle;
    border-color: #eee;
}

/* Badge styles */
.badge {
    padding: 6px 12px;
    font-weight: 500;
    font-size: 0.75rem;
    border-radius: 20px;
}

/* Progress bar */
.progress {
    border-radius: 10px;
    background: #e9ecef;
}

.progress-bar {
    border-radius: 10px;
}

/* Avatar sizes */
.avatar-sm {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-lg {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* List group */
.list-group-item {
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}
</style>

<!-- Dashboard JavaScript -->
<script>
function refreshDashboard() {
    const refreshBtn = event.target.closest('button');
    const originalHTML = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>