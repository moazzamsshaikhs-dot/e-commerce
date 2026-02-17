<?php
// admin/vendors/reports/sales.php
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
    
    // Build WHERE clause for queries
    $params = [$vendor_id];
    $where_clause = "WHERE p.vendor_id = ?";
    
    if (!empty($filter_start) && !empty($filter_end)) {
        $where_clause .= " AND DATE(o.order_date) BETWEEN ? AND ?";
        $params[] = $filter_start;
        $params[] = $filter_end;
    }
    
    if (!empty($filter_product)) {
        $where_clause .= " AND p.id = ?";
        $params[] = $filter_product;
    }
    
    if (!empty($filter_status)) {
        $where_clause .= " AND o.status = ?";
        $params[] = $filter_status;
    }
    
    // ============================================
    // SALES CHART DATA - Monthly Trend (Last 12 Months)
    // ============================================
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
        ORDER BY month ASC
    ");
    $stmt->execute([$vendor_id]);
    $monthly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare chart data arrays
    $chart_months = [];
    $chart_sales = [];
    $chart_orders = [];
    
    // Fill with last 12 months even if no data
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_display = date('M Y', strtotime("-$i months"));
        $chart_months[] = $month_display;
        
        // Find if we have data for this month
        $found = false;
        foreach ($monthly_sales as $data) {
            if ($data['month'] == $month) {
                $chart_sales[] = (float)$data['total_sales'];
                $chart_orders[] = (int)$data['order_count'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chart_sales[] = 0;
            $chart_orders[] = 0;
        }
    }
    
    // ============================================
    // CATEGORY DISTRIBUTION CHART DATA
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            COALESCE(p.category, 'Uncategorized') as category,
            COUNT(DISTINCT p.id) as product_count,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE p.vendor_id = ?
        AND (o.order_date IS NULL OR DATE(o.order_date) BETWEEN ? AND ?)
        GROUP BY p.category
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $category_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $category_labels = [];
    $category_values = [];
    $category_colors = [
        '#4361ee', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6',
        '#ec4899', '#14b8a6', '#f97316', '#6b7280', '#64748b'
    ];
    
    foreach ($category_data as $index => $cat) {
        if ($index < 5) { // Top 5 categories
            $category_labels[] = $cat['category'] ?: 'Uncategorized';
            $category_values[] = (float)$cat['total_revenue'];
        }
    }
    
    // If no categories, add default
    if (empty($category_labels)) {
        $category_labels = ['No Sales'];
        $category_values = [1];
    }
    
    // ============================================
    // ORDER STATUS CHART DATA
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            o.status,
            COUNT(DISTINCT o.id) as order_count,
            SUM(o.total_amount) as total_sales
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        AND DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY o.status
        ORDER BY order_count DESC
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $status_labels = [];
    $status_values = [];
    $status_colors = [
        'delivered' => '#22c55e',
        'shipped' => '#3b82f6',
        'processing' => '#f59e0b',
        'pending' => '#f97316',
        'cancelled' => '#ef4444',
        'refunded' => '#6b7280'
    ];
    
    foreach ($status_data as $status) {
        if (!empty($status['status'])) {
            $status_labels[] = ucfirst($status['status']);
            $status_values[] = (int)$status['order_count'];
        }
    }
    
    // If no status data, add default
    if (empty($status_labels)) {
        $status_labels = ['No Orders'];
        $status_values = [1];
    }
    
    // ============================================
    // TOP PRODUCTS DATA
    // ============================================
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
        AND (o.order_date IS NULL OR DATE(o.order_date) BETWEEN ? AND ?)
        GROUP BY p.id
        HAVING total_revenue > 0
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $sales_by_product = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // TOP CUSTOMERS DATA
    // ============================================
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
        AND DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // PRODUCTS FOR FILTER DROPDOWN
    // ============================================
    $stmt = $db->prepare("SELECT id, name FROM products WHERE vendor_id = ? ORDER BY name");
    $stmt->execute([$vendor_id]);
    $vendor_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // SALES STATISTICS
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(o.total_amount), 0) as total_sales,
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(oi.quantity), 0) as total_items,
            COUNT(DISTINCT o.user_id) as total_customers,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        $where_clause
    ");
    $stmt->execute($params);
    $sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // CONVERSION DATA
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(p.views), 0) as total_views,
            COALESCE(SUM(p.sales_count), 0) as total_sales
        FROM products p
        WHERE p.vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $conversion_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $conversion_rate = 0;
    if ($conversion_data['total_views'] > 0) {
        $conversion_rate = ($conversion_data['total_sales'] / $conversion_data['total_views']) * 100;
    }
    
    // ============================================
    // DETAILED SALES TABLE
    // ============================================
    $detailed_params = $params;
    $detailed_sql = "
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
        $where_clause
        GROUP BY o.id
        ORDER BY o.order_date DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($detailed_sql);
    $stmt->execute($params);
    $detailed_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading sales data: ' . $e->getMessage();
    error_log("Sales Report Error: " . $e->getMessage());
    
    // Set default empty values
    $sales_stats = [
        'total_sales' => 0,
        'total_orders' => 0,
        'total_items' => 0,
        'total_customers' => 0,
        'avg_order_value' => 0
    ];
    $sales_by_product = [];
    $monthly_sales = [];
    $top_customers = [];
    $vendor_products = [];
    $conversion_rate = 0;
    $detailed_sales = [];
    $chart_months = [];
    $chart_sales = [];
    $chart_orders = [];
    $category_labels = ['No Data'];
    $category_values = [1];
    $status_labels = ['No Data'];
    $status_values = [1];
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
                            <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                                <h6 class="text-muted mb-2">Avg Order Value</h6>
                                <h2 class="mb-0">$<?php echo number_format($sales_stats['avg_order_value'], 2); ?></h2>
                                <small class="text-info">
                                    <i class="fas fa-chart-line me-1"></i>
                                    Per transaction
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
                                    <p class="mt-2 mb-0 fw-bold">By Category</p>
                                    <small class="text-muted">Revenue distribution</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center">
                                    <canvas id="statusChart" width="200" height="200"></canvas>
                                    <p class="mt-2 mb-0 fw-bold">By Order Status</p>
                                    <small class="text-muted">Order count</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <h6 class="mb-3">Quick Insights</h6>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center">
                                        <div class="text-primary fw-bold">
                                            $<?php echo number_format($sales_stats['avg_order_value'], 2); ?>
                                        </div>
                                        <small class="text-muted">Average Order Value</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded text-center">
                                        <div class="text-success fw-bold">
                                            <?php echo $sales_stats['total_customers']; ?>
                                        </div>
                                        <small class="text-muted">Total Customers</small>
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
                <span class="badge bg-info">Last 20 Orders</span>
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
                            <?php if (empty($detailed_sales)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No sales data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($detailed_sales as $sale): ?>
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
                                                 ($sale['status'] == 'shipped' ? 'info' :
                                                 ($sale['status'] == 'processing' ? 'primary' :
                                                 ($sale['status'] == 'pending' ? 'warning' : 'danger'))); 
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
                                <?php endforeach; ?>
                            <?php endif; ?>
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

<script>
// Sales Chart Data from PHP
const chartMonths = <?php echo json_encode($chart_months); ?>;
const chartSales = <?php echo json_encode($chart_sales); ?>;
const chartOrders = <?php echo json_encode($chart_orders); ?>;

// Category Chart Data
const categoryLabels = <?php echo json_encode($category_labels); ?>;
const categoryValues = <?php echo json_encode($category_values); ?>;
const categoryColors = ['#4361ee', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6b7280'];

// Status Chart Data
const statusLabels = <?php echo json_encode($status_labels); ?>;
const statusValues = <?php echo json_encode($status_values); ?>;
const statusColors = {
    'Delivered': '#22c55e',
    'Shipped': '#3b82f6',
    'Processing': '#f59e0b',
    'Pending': '#f97316',
    'Cancelled': '#ef4444',
    'Refunded': '#6b7280'
};

let salesChart;

// Initialize Sales Chart
function initSalesChart() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartMonths,
            datasets: [
                {
                    label: 'Sales ($)',
                    data: chartSales,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders',
                    data: chartOrders,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.dataset.label.includes('Sales')) {
                                label += '$' + context.parsed.y.toLocaleString();
                            } else {
                                label += context.parsed.y;
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Sales ($)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Orders'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                }
            }
        }
    });
}

// Initialize Category Distribution Chart
function initCategoryChart() {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryValues,
                backgroundColor: categoryColors.slice(0, categoryLabels.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: { size: 10 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const value = context.raw;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${context.label}: $${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Initialize Order Status Chart
function initStatusChart() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    
    const backgroundColors = statusLabels.map(label => {
        return statusColors[label] || '#6b7280';
    });
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: backgroundColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: { size: 10 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const value = context.raw;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ${value} orders (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Update chart view (for dropdown)
function updateChart(view) {
    // This would make an AJAX call to get different time period data
    Swal.fire({
        title: 'Loading...',
        text: 'Fetching ' + view + ' data',
        icon: 'info',
        timer: 1500,
        showConfirmButton: false
    });
    // For now, just show a message
    setTimeout(() => {
        window.location.reload();
    }, 1500);
}

// Print report
function printReport() {
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    // Get current filters
    const startDate = '<?php echo $filter_start; ?>';
    const endDate = '<?php echo $filter_end; ?>';
    
    // Format dates
    const formattedStart = new Date(startDate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    const formattedEnd = new Date(endDate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    
    // Get stats
    const totalSales = <?php echo $sales_stats['total_sales']; ?>;
    const totalOrders = <?php echo $sales_stats['total_orders']; ?>;
    const totalItems = <?php echo $sales_stats['total_items']; ?>;
    const totalCustomers = <?php echo $sales_stats['total_customers']; ?>;
    const avgOrder = <?php echo $sales_stats['avg_order_value']; ?>;
    
    // Generate print content
    printWindow.document.write(`
        <html>
            <head>
                <title>Sales Report - <?php echo htmlspecialchars($vendor['full_name'] ?? 'Vendor'); ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { font-family: Arial, sans-serif; padding: 30px; }
                    .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4361ee; padding-bottom: 20px; }
                    .header h1 { color: #4361ee; }
                    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 30px 0; }
                    .stat-box { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; border-left: 4px solid #4361ee; }
                    .stat-box h3 { margin: 0; color: #4361ee; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    th { background: #4361ee; color: white; padding: 10px; text-align: left; }
                    td { padding: 8px; border-bottom: 1px solid #ddd; }
                    tr:nth-child(even) { background: #f9f9f9; }
                    .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; }
                    @media print {
                        .no-print { display: none; }
                        button { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Sales Report</h1>
                    <p><strong>Vendor:</strong> <?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?></p>
                    <p><strong>Period:</strong> ${formattedStart} to ${formattedEnd}</p>
                    <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-box">
                        <h3>$${totalSales.toLocaleString()}</h3>
                        <p class="text-muted">Total Sales</p>
                    </div>
                    <div class="stat-box">
                        <h3>${totalOrders.toLocaleString()}</h3>
                        <p class="text-muted">Total Orders</p>
                    </div>
                    <div class="stat-box">
                        <h3>${totalItems.toLocaleString()}</h3>
                        <p class="text-muted">Items Sold</p>
                    </div>
                    <div class="stat-box">
                        <h3>$${avgOrder.toLocaleString()}</h3>
                        <p class="text-muted">Avg Order Value</p>
                    </div>
                </div>
                
                <h3>Recent Orders</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach(array_slice($detailed_sales, 0, 10) as $sale): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($sale['order_date'])); ?></td>
                            <td><?php echo $sale['order_number']; ?></td>
                            <td><?php echo htmlspecialchars($sale['full_name'] ?? $sale['username']); ?></td>
                            <td><?php echo $sale['item_count']; ?></td>
                            <td>$${<?php echo $sale['total_amount']; ?>.toFixed(2)}</td>
                            <td><?php echo ucfirst($sale['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="footer">
                    <p>This is a computer-generated report. No signature required.</p>
                    <p><?php echo SITE_NAME; ?> - Vendor Dashboard</p>
                </div>
                
                <script>
                    window.onload = function() { 
                        window.print(); 
                        setTimeout(function() { window.close(); }, 1000);
                    }
                <\/script>
            </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Initialize all charts when page loads
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

// Auto-refresh every 10 minutes (optional)
setTimeout(function() {
    if (confirm('Refresh sales data?')) {
        window.location.reload();
    }
}, 600000);
</script>

<?php require_once '../../includes/footer.php'; ?>