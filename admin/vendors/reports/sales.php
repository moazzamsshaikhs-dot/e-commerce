<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
} else if (!isset($_SESSION['user_id'])) {
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
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        redirect(SITE_URL . 'admin/vendors/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect(SITE_URL . 'admin/vendors/dashboard.php');
}

// Set default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Get filter parameters
$filter_start = $_GET['start_date'] ?? $start_date;
$filter_end = $_GET['end_date'] ?? $end_date;
$filter_product = $_GET['product_id'] ?? '';
$filter_status = $_GET['order_status'] ?? '';

// Validate dates
if (!empty($filter_start) && !empty($filter_end)) {
    if (strtotime($filter_start) > strtotime($filter_end)) {
        $_SESSION['error'] = 'Start date cannot be after end date.';
        $filter_start = $start_date;
        $filter_end = $end_date;
    }
}

// Get vendor details
try {
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor info
    $stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Get sales statistics
    $params = [$vendor_id];
    $where_clause = "WHERE p.vendor_id = ?";
    
    // Add date filters
    if (!empty($filter_start) && !empty($filter_end)) {
        $where_clause .= " AND DATE(o.order_date) BETWEEN ? AND ?";
        $params[] = $filter_start;
        $params[] = $filter_end;
    }
    
    // Add product filter
    if (!empty($filter_product)) {
        $where_clause .= " AND p.id = ?";
        $params[] = $filter_product;
    }
    
    // Add status filter
    if (!empty($filter_status)) {
        $where_clause .= " AND o.status = ?";
        $params[] = $filter_status;
    }
    
    // Total Sales
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(o.total_amount), 0) as total_sales,
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(oi.quantity), 0) as total_items,
            COUNT(DISTINCT o.user_id) as total_customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        $where_clause
    ");
    $stmt->execute($params);
    $sales_stats = $stmt->fetch();
    
    // Sales by product
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.image,
            p.category,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.subtotal) as total_revenue,
            AVG(p.price) as avg_price
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE p.vendor_id = ?
        " . (!empty($filter_start) && !empty($filter_end) ? "AND DATE(o.order_date) BETWEEN ? AND ?" : "") . "
        GROUP BY p.id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    
    $product_params = [$vendor_id];
    if (!empty($filter_start) && !empty($filter_end)) {
        $product_params[] = $filter_start;
        $product_params[] = $filter_end;
    }
    $stmt->execute($product_params);
    $sales_by_product = $stmt->fetchAll();
    
    // Sales by month (for chart)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(o.order_date, '%Y-%m') as month,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_sales,
            COUNT(DISTINCT o.user_id) as customer_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        AND o.order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute([$vendor_id]);
    $monthly_sales = $stmt->fetchAll();
    
    // Top customers
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.profile_pic,
            COUNT(DISTINCT o.id) as order_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.order_date) as last_order
        FROM users u
        JOIN orders o ON u.id = o.user_id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        " . (!empty($filter_start) && !empty($filter_end) ? "AND DATE(o.order_date) BETWEEN ? AND ?" : "") . "
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    
    $customer_params = [$vendor_id];
    if (!empty($filter_start) && !empty($filter_end)) {
        $customer_params[] = $filter_start;
        $customer_params[] = $filter_end;
    }
    $stmt->execute($customer_params);
    $top_customers = $stmt->fetchAll();
    
    // Get all vendor products for filter dropdown
    $stmt = $db->prepare("SELECT id, name FROM products WHERE vendor_id = ? ORDER BY name");
    $stmt->execute([$vendor_id]);
    $vendor_products = $stmt->fetchAll();
    
    // Calculate conversion rate (if there's analytics data)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(p.views), 0) as total_views,
            COALESCE(SUM(p.sales_count), 0) as total_sales
        FROM products p
        WHERE p.vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $conversion_data = $stmt->fetch();
    
    $conversion_rate = 0;
    if ($conversion_data['total_views'] > 0) {
        $conversion_rate = ($conversion_data['total_sales'] / $conversion_data['total_views']) * 100;
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading sales data: ' . $e->getMessage();
    error_log("Sales Report Error: " . $e->getMessage());
    $sales_stats = [
        'total_sales' => 0,
        'total_orders' => 0,
        'total_items' => 0,
        'total_customers' => 0
    ];
    $sales_by_product = [];
    $monthly_sales = [];
    $top_customers = [];
    $vendor_products = [];
    $conversion_rate = 0;
}

// Log activity
logUserActivity($_SESSION['user_id'], 'sales_report_view', 'Viewed sales reports');

$page_title = 'Sales Reports - Vendor Dashboard';
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Include Vendor Sidebar -->
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Sales Reports</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-chart-line me-1 text-success"></i>
                        Analyze your sales performance
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="printReport()">
                        <i class="fas fa-print me-2"></i> Print Report
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fas fa-download me-2"></i> Export
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Filters Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-filter me-2 text-primary"></i>
                    Filter Reports
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($filter_start); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($filter_end); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select">
                            <option value="">All Products</option>
                            <?php foreach($vendor_products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" 
                                    <?php echo $filter_product == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Order Status</label>
                        <select name="order_status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $filter_status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $filter_status == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $filter_status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i> Apply Filters
                            </button>
                            <a href="sales.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Stats -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Sales</h6>
                                <h2 class="mb-0">$<?php echo number_format($sales_stats['total_sales'], 2); ?></h2>
                                <small class="text-muted">
                                    <?php echo !empty($filter_start) ? 'From ' . date('M d, Y', strtotime($filter_start)) : ''; ?>
                                    <?php echo !empty($filter_end) ? ' to ' . date('M d, Y', strtotime($filter_end)) : ''; ?>
                                </small>
                            </div>
                            <div class="stats-icon primary">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Orders</h6>
                                <h2 class="mb-0"><?php echo number_format($sales_stats['total_orders']); ?></h2>
                                <small class="text-success">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo number_format($sales_stats['total_customers']); ?> customers
                                </small>
                            </div>
                            <div class="stats-icon success">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Items Sold</h6>
                                <h2 class="mb-0"><?php echo number_format($sales_stats['total_items']); ?></h2>
                                <small class="text-muted">
                                    Average order: 
                                    <?php echo $sales_stats['total_orders'] > 0 ? 
                                        number_format($sales_stats['total_items'] / $sales_stats['total_orders'], 1) : '0.0'; ?> items
                                </small>
                            </div>
                            <div class="stats-icon warning">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Conversion Rate</h6>
                                <h2 class="mb-0"><?php echo number_format($conversion_rate, 2); ?>%</h2>
                                <small class="text-info">
                                    <i class="fas fa-eye me-1"></i>
                                    <?php echo number_format($conversion_data['total_views'] ?? 0); ?> product views
                                </small>
                            </div>
                            <div class="stats-icon info">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Tables -->
        <div class="row g-4">
            <!-- Sales Chart -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Sales Trend (Last 12 Months)</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-chart-bar me-1"></i> View
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="updateChart('monthly')">Monthly</a></li>
                                <li><a class="dropdown-item" href="#" onclick="updateChart('quarterly')">Quarterly</a></li>
                                <li><a class="dropdown-item" href="#" onclick="updateChart('yearly')">Yearly</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top Products -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Top Selling Products</h5>
                        <a href="../products/products.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sales_by_product)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No sales data available</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($sales_by_product as $index => $product): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="position-relative">
                                            <span class="badge bg-primary position-absolute top-0 start-0 translate-middle">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                            <div class="avatar-sm me-3">
                                                <?php if (!empty($product['image'])): ?>
                                                    <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $product['image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                         class="rounded" width="40" height="40">
                                                <?php else: ?>
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-box text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold">$<?php echo number_format($product['total_revenue'] ?? 0, 2); ?></div>
                                            <small class="text-muted"><?php echo $product['total_quantity'] ?? 0; ?> sold</small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Top Customers</h5>
                        <span class="badge bg-primary"><?php echo count($top_customers); ?> customers</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_customers)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No customer data available</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Orders</th>
                                            <th>Total Spent</th>
                                            <th>Last Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($top_customers as $customer): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <?php if (!empty($customer['profile_pic']) && $customer['profile_pic'] != 'default.png'): ?>
                                                            <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $customer['profile_pic']; ?>" 
                                                                 class="rounded-circle" width="32" height="32">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                                                 style="width: 32px; height: 32px;">
                                                                <i class="fas fa-user text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($customer['full_name'] ?? $customer['username']); ?></div>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($customer['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="fw-bold"><?php echo $customer['order_count']; ?></td>
                                            <td class="text-success fw-bold">$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo !empty($customer['last_order']) ? date('M d, Y', strtotime($customer['last_order'])) : 'Never'; ?>
                                                </small>
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
            
            <!-- Sales Distribution -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">Sales Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="text-center">
                                    <canvas id="categoryChart" width="200" height="200"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center">
                                    <canvas id="statusChart" width="200" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <h6 class="mb-3">Quick Insights</h6>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center">
                                        <div class="text-primary fw-bold">
                                            $<?php echo number_format($sales_stats['total_orders'] > 0 ? 
                                                $sales_stats['total_sales'] / $sales_stats['total_orders'] : 0, 2); ?>
                                        </div>
                                        <small class="text-muted">Average Order Value</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center">
                                        <div class="text-success fw-bold">
                                            $<?php echo number_format($sales_stats['total_customers'] > 0 ? 
                                                $sales_stats['total_sales'] / $sales_stats['total_customers'] : 0, 2); ?>
                                        </div>
                                        <small class="text-muted">Customer Lifetime Value</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Sales Table -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Detailed Sales Report</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="toggleFilters()">
                    <i class="fas fa-sliders-h me-1"></i> More Filters
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="salesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Products</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Get detailed sales
                            try {
                                $detailed_params = [$vendor_id];
                                $detailed_where = "WHERE p.vendor_id = ?";
                                
                                if (!empty($filter_start) && !empty($filter_end)) {
                                    $detailed_where .= " AND DATE(o.order_date) BETWEEN ? AND ?";
                                    $detailed_params[] = $filter_start;
                                    $detailed_params[] = $filter_end;
                                }
                                
                                $stmt = $db->prepare("
                                    SELECT 
                                        o.id,
                                        o.order_number,
                                        o.order_date,
                                        o.total_amount,
                                        o.status,
                                        o.payment_method,
                                        o.payment_status,
                                        u.username,
                                        u.full_name,
                                        COUNT(DISTINCT oi.product_id) as product_count,
                                        SUM(oi.quantity) as item_count
                                    FROM orders o
                                    JOIN order_items oi ON o.id = oi.order_id
                                    JOIN products p ON oi.product_id = p.id
                                    JOIN users u ON o.user_id = u.id
                                    $detailed_where
                                    GROUP BY o.id
                                    ORDER BY o.order_date DESC
                                    LIMIT 20
                                ");
                                $stmt->execute($detailed_params);
                                $detailed_sales = $stmt->fetchAll();
                                
                                foreach($detailed_sales as $sale):
                            ?>
                            <tr>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($sale['order_date'])); ?><br>
                                        <?php echo date('h:i A', strtotime($sale['order_date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <a href="../orders/view.php?id=<?php echo $sale['id']; ?>" 
                                       class="text-decoration-none fw-bold">
                                        #<?php echo $sale['order_number']; ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2">
                                            <i class="fas fa-user text-muted"></i>
                                        </div>
                                        <div>
                                            <div><?php echo htmlspecialchars($sale['full_name'] ?? $sale['username']); ?></div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($sale['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $sale['product_count']; ?> products</span>
                                </td>
                                <td class="fw-bold"><?php echo $sale['item_count']; ?></td>
                                <td class="fw-bold">$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $sale['status'] == 'delivered' ? 'success' : 
                                             ($sale['status'] == 'pending' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($sale['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $sale['payment_status'] == 'completed' ? 'success' : 
                                             ($sale['payment_status'] == 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($sale['payment_method'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php 
                                endforeach;
                            } catch(PDOException $e) {
                                echo '<tr><td colspan="8" class="text-center text-muted py-4">Error loading detailed sales</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm" method="POST" action="export-sales.php">
                    <input type="hidden" name="start_date" value="<?php echo $filter_start; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $filter_end; ?>">
                    <input type="hidden" name="product_id" value="<?php echo $filter_product; ?>">
                    <input type="hidden" name="order_status" value="<?php echo $filter_status; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <select name="format" class="form-select" required>
                            <option value="csv">CSV (Excel)</option>
                            <option value="pdf">PDF Document</option>
                            <option value="excel">Excel File</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select" required>
                            <option value="summary">Summary Report</option>
                            <option value="detailed">Detailed Report</option>
                            <option value="products">Product-wise Report</option>
                            <option value="customers">Customer Report</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Include Data</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_charts" id="includeCharts">
                            <label class="form-check-label" for="includeCharts">
                                Include charts and graphs
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_details" id="includeDetails" checked>
                            <label class="form-check-label" for="includeDetails">
                                Include detailed data
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="exportForm" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Charts JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Custom styles for reports */
.stats-card {
    transition: transform 0.3s ease;
}
.stats-card:hover {
    transform: translateY(-5px);
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
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

.avatar-sm {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#salesTable tbody tr {
    cursor: pointer;
    transition: background-color 0.2s;
}

#salesTable tbody tr:hover {
    background-color: rgba(67, 97, 238, 0.05);
}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Sales Chart
let salesChart;
function initSalesChart() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    const months = <?php echo json_encode(array_column($monthly_sales, 'month')); ?>;
    const sales = <?php echo json_encode(array_column($monthly_sales, 'total_sales')); ?>;
    const orders = <?php echo json_encode(array_column($monthly_sales, 'order_count')); ?>;
    
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months.reverse().map(m => {
                const [year, month] = m.split('-');
                return new Date(year, month-1).toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
            }),
            datasets: [{
                label: 'Sales ($)',
                data: sales.reverse(),
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Orders',
                data: orders.reverse(),
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

//Category Distribution Chart
function initCategoryChart() {
   const ctx = document.getElementById('categoryChart').getContext('2d');
    
   new Chart(ctx, {
       type: 'doughnut',
       data: {
           labels: ['Electronics', 'Clothing', 'Home', 'Books', 'Other'],
           datasets: [{
               data: [35, 25, 20, 10, 10],
               backgroundColor: [
                   '#4361ee',
                   '#22c55e',
                   '#f59e0b',
                   '#8b5cf6',
                   '#6b7280'
               ]
           }]
       },
       options: {
           responsive: true,
           plugins: {
               legend: {
                   position: 'bottom'
               }
           }
        }
   });
}

// Order Status Chart
function initStatusChart() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Delivered', 'Processing', 'Pending', 'Cancelled'],
            datasets: [{
                data: [60, 25, 10, 5],
                backgroundColor: [
                    '#22c55e',
                    '#3b82f6',
                    '#f59e0b',
                    '#ef4444'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Update chart view
function updateChart(view) {
    // This would make an AJAX call to get different time period data
    alert('Would fetch ' + view + ' data via AJAX');
    // For now, just show an alert
}

// Print report
function printReport() {
    const printContent = document.querySelector('.main-content').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Sales Report - <?php echo htmlspecialchars($vendor['full_name'] ?? 'Vendor'); ?></title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .no-print { display: none !important; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
                    .stat-box { border: 1px solid #ddd; padding: 15px; text-align: center; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Sales Report</h1>
                    <p>Vendor: <?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?></p>
                    <p>Period: <?php echo date('M d, Y', strtotime($filter_start)); ?> to <?php echo date('M d, Y', strtotime($filter_end)); ?></p>
                    <p>Generated: <?php echo date('M d, Y h:i A'); ?></p>
                </div>
                
                <div class="stats">
                    <div class="stat-box">
                        <h3>$<?php echo number_format($sales_stats['total_sales'], 2); ?></h3>
                        <p>Total Sales</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $sales_stats['total_orders']; ?></h3>
                        <p>Total Orders</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $sales_stats['total_items']; ?></h3>
                        <p>Items Sold</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $sales_stats['total_customers']; ?></h3>
                        <p>Customers</p>
                    </div>
                </div>
                
                ${printContent}
                
                <script>
                    window.print();
                    window.onafterprint = function() {
                        window.location.reload();
                    };
                <\/script>
            </body>
        </html>
    `;
}

// Toggle advanced filters
function toggleFilters() {
    const filters = document.querySelectorAll('.advanced-filter');
    filters.forEach(filter => {
        filter.style.display = filter.style.display === 'none' ? 'block' : 'none';
    });
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    initSalesChart();
    initCategoryChart();
    initStatusChart();
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add click event to sales table rows
    const salesRows = document.querySelectorAll('#salesTable tbody tr');
    salesRows.forEach(row => {
        row.addEventListener('click', function() {
            const orderLink = this.querySelector('a[href*="orders/view"]');
            if (orderLink) {
                window.location.href = orderLink.href;
            }
        });
    });
});

// Auto-refresh every 5 minutes (optional)
setTimeout(function() {
    if (confirm('Refresh sales data?')) {
        window.location.reload();
    }
}, 300000);
</script>

<?php require_once '../../includes/footer.php'; ?>