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
$filter_period = $_GET['period'] ?? 'monthly'; // daily, weekly, monthly, quarterly, yearly

// Get vendor details
try {
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor info
    $stmt = $db->prepare("SELECT full_name, username, vendor_since FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Performance Overview Stats
    $stmt = $db->prepare("
        SELECT 
            -- Sales Performance
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(oi.quantity), 0) as total_items_sold,
            
            -- Customer Metrics
            COUNT(DISTINCT o.user_id) as total_customers,
            COUNT(DISTINCT CASE WHEN o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN o.user_id END) as active_customers,
            
            -- Product Performance
            COUNT(DISTINCT p.id) as total_products,
            COUNT(DISTINCT CASE WHEN p.stock > 0 THEN p.id END) as in_stock_products,
            COUNT(DISTINCT CASE WHEN p.stock = 0 THEN p.id END) as out_of_stock_products,
            
            -- Review Metrics
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(r.id) as total_reviews
            
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.vendor_id = ?
        AND (o.order_date IS NULL OR DATE(o.order_date) BETWEEN ? AND ?)
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $performance_stats = $stmt->fetch();
    
    // Calculate additional metrics
    $avg_order_value = $performance_stats['total_orders'] > 0 ? 
        $performance_stats['total_revenue'] / $performance_stats['total_orders'] : 0;
    
    $customer_retention_rate = $performance_stats['total_customers'] > 0 ? 
        ($performance_stats['active_customers'] / $performance_stats['total_customers']) * 100 : 0;
    
    $sell_through_rate = ($performance_stats['total_items_sold'] > 0 && $performance_stats['total_products'] > 0) ? 
        ($performance_stats['total_items_sold'] / ($performance_stats['total_items_sold'] + 100)) * 100 : 0;
    
    // Monthly Performance Trend
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(o.order_date, '%Y-%m') as month,
            COUNT(DISTINCT o.id) as orders,
            COALESCE(SUM(o.total_amount), 0) as revenue,
            COUNT(DISTINCT o.user_id) as customers,
            COALESCE(AVG(r.rating), 0) as avg_rating
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.vendor_id = ?
        AND o.order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $stmt->execute([$vendor_id]);
    $monthly_trend = $stmt->fetchAll();
    
    // Top Performing Products
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.image,
            p.category,
            p.price,
            p.stock,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(r.id) as review_count,
            p.views as product_views,
            CASE 
                WHEN SUM(oi.quantity) > 0 THEN (SUM(oi.quantity) / p.views) * 100
                ELSE 0 
            END as conversion_rate
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.vendor_id = ?
        AND (oi.created_at IS NULL OR DATE(oi.created_at) BETWEEN ? AND ?)
        GROUP BY p.id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $top_products = $stmt->fetchAll();
    
    // Customer Retention Analysis
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN order_count = 1 THEN 'New Customers'
                WHEN order_count BETWEEN 2 AND 5 THEN 'Repeat Customers'
                ELSE 'Loyal Customers'
            END as customer_segment,
            COUNT(*) as customer_count,
            SUM(total_spent) as segment_revenue,
            AVG(total_spent) as avg_spent
        FROM (
            SELECT 
                u.id,
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
        ) as customer_data
        GROUP BY customer_segment
        ORDER BY segment_revenue DESC
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $customer_segments = $stmt->fetchAll();
    
    // Category Performance
    $stmt = $db->prepare("
        SELECT 
            COALESCE(p.category, 'Uncategorized') as category,
            COUNT(DISTINCT p.id) as product_count,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue,
            COALESCE(AVG(r.rating), 0) as avg_rating
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.vendor_id = ?
        AND (oi.created_at IS NULL OR DATE(oi.created_at) BETWEEN ? AND ?)
        GROUP BY p.category
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $category_performance = $stmt->fetchAll();
    
    // Inventory Performance
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN stock = 0 THEN 'Out of Stock'
                WHEN stock < 10 THEN 'Low Stock'
                WHEN stock >= 10 THEN 'In Stock'
            END as stock_status,
            COUNT(*) as product_count,
            SUM(views) as total_views,
            SUM(sales_count) as total_sales,
            AVG(price) as avg_price
        FROM products
        WHERE vendor_id = ?
        GROUP BY stock_status
        ORDER BY product_count DESC
    ");
    $stmt->execute([$vendor_id]);
    $inventory_analysis = $stmt->fetchAll();
    
    // Review Performance
    $stmt = $db->prepare("
        SELECT 
            rating,
            COUNT(*) as review_count,
            AVG(LENGTH(review_text)) as avg_review_length,
            SUM(CASE WHEN vendor_responded = 1 THEN 1 ELSE 0 END) as responded_reviews
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        WHERE p.vendor_id = ?
        AND DATE(r.created_at) BETWEEN ? AND ?
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->execute([$vendor_id, $filter_start, $filter_end]);
    $review_analysis = $stmt->fetchAll();
    
    // Calculate Review Distribution
    $total_reviews = array_sum(array_column($review_analysis, 'review_count'));
    $review_distribution = [];
    foreach($review_analysis as $review) {
        $review_distribution[$review['rating']] = $review['review_count'];
    }
    
    // Vendor Growth Metrics
    $stmt = $db->prepare("
        SELECT 
            MONTH(p.created_at) as month_num,
            MONTHNAME(p.created_at) as month_name,
            COUNT(p.id) as products_added,
            COUNT(DISTINCT o.id) as orders_received,
            COALESCE(SUM(o.total_amount), 0) as monthly_revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE p.vendor_id = ?
        AND p.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY MONTH(p.created_at), MONTHNAME(p.created_at)
        ORDER BY MONTH(p.created_at)
    ");
    $stmt->execute([$vendor_id]);
    $growth_metrics = $stmt->fetchAll();
    
    // Benchmark Calculations (Placeholder - you can add actual benchmarks)
    $industry_benchmarks = [
        'conversion_rate' => 2.5,
        'avg_order_value' => 85.00,
        'customer_retention' => 35.0,
        'avg_rating' => 4.2
    ];
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading performance data: ' . $e->getMessage();
    error_log("Performance Report Error: " . $e->getMessage());
    
    // Set default empty values
    $performance_stats = [
        'total_revenue' => 0,
        'total_orders' => 0,
        'total_items_sold' => 0,
        'total_customers' => 0,
        'active_customers' => 0,
        'total_products' => 0,
        'in_stock_products' => 0,
        'out_of_stock_products' => 0,
        'avg_rating' => 0,
        'total_reviews' => 0
    ];
    $monthly_trend = [];
    $top_products = [];
    $customer_segments = [];
    $category_performance = [];
    $inventory_analysis = [];
    $review_analysis = [];
    $growth_metrics = [];
    $review_distribution = [];
    $industry_benchmarks = [
        'conversion_rate' => 2.5,
        'avg_order_value' => 85.00,
        'customer_retention' => 35.0,
        'avg_rating' => 4.2
    ];
    
    $avg_order_value = 0;
    $customer_retention_rate = 0;
    $sell_through_rate = 0;
}

// Log activity
logUserActivity($_SESSION['user_id'], 'performance_report_view', 'Viewed performance reports');

$page_title = 'Performance Reports - Vendor Dashboard';
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
                    <h1 class="h3 mb-1 fw-bold text-primary">Performance Reports</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-chart-line me-1 text-success"></i>
                        Track and analyze your business performance
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="printReport()">
                        <i class="fas fa-print me-2"></i> Print Report
                    </button>
                    <a href="sales.php" class="btn btn-outline-secondary">
                        <i class="fas fa-exchange-alt me-2"></i> Compare with Sales
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-sliders-h me-2 text-primary"></i>
                    Analysis Period
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
                        <label class="form-label">Analysis Period</label>
                        <select name="period" class="form-select">
                            <option value="daily" <?php echo $filter_period == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $filter_period == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $filter_period == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarterly" <?php echo $filter_period == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="yearly" <?php echo $filter_period == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-chart-bar me-2"></i> Analyze
                            </button>
                            <a href="performance.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Key Performance Indicators -->
        <div class="row g-4 mb-4">
            <!-- Revenue Growth -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Revenue</h6>
                                <h2 class="mb-0">$<?php echo number_format($performance_stats['total_revenue'], 2); ?></h2>
                                <small class="<?php echo $performance_stats['total_revenue'] > 0 ? 'text-success' : 'text-muted'; ?>">
                                    <i class="fas fa-<?php echo $performance_stats['total_revenue'] > 0 ? 'trend-up' : 'minus'; ?> me-1"></i>
                                    Total Revenue
                                </small>
                            </div>
                            <div class="stats-icon primary">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small">
                                <span>Benchmark:</span>
                                <span class="fw-bold">$<?php echo number_format($industry_benchmarks['avg_order_value'] * $performance_stats['total_orders'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Retention -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Customer Retention</h6>
                                <h2 class="mb-0"><?php echo number_format($customer_retention_rate, 1); ?>%</h2>
                                <small class="<?php echo $customer_retention_rate >= $industry_benchmarks['customer_retention'] ? 'text-success' : 'text-warning'; ?>">
                                    <i class="fas fa-<?php echo $customer_retention_rate >= $industry_benchmarks['customer_retention'] ? 'users' : 'user-minus'; ?> me-1"></i>
                                    <?php echo $performance_stats['active_customers']; ?> active customers
                                </small>
                            </div>
                            <div class="stats-icon success">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small">
                                <span>Industry Avg:</span>
                                <span class="fw-bold"><?php echo $industry_benchmarks['customer_retention']; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Average Rating -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Average Rating</h6>
                                <h2 class="mb-0"><?php echo number_format($performance_stats['avg_rating'], 1); ?>/5</h2>
                                <small class="<?php echo $performance_stats['avg_rating'] >= $industry_benchmarks['avg_rating'] ? 'text-success' : 'text-warning'; ?>">
                                    <i class="fas fa-star me-1"></i>
                                    from <?php echo $performance_stats['total_reviews']; ?> reviews
                                </small>
                            </div>
                            <div class="stats-icon warning">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small">
                                <span>Industry Avg:</span>
                                <span class="fw-bold"><?php echo $industry_benchmarks['avg_rating']; ?>/5</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Conversion Rate -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Conversion Rate</h6>
                                <h2 class="mb-0"><?php echo number_format($sell_through_rate, 2); ?>%</h2>
                                <small class="<?php echo $sell_through_rate >= $industry_benchmarks['conversion_rate'] ? 'text-success' : 'text-warning'; ?>">
                                    <i class="fas fa-chart-line me-1"></i>
                                    <?php echo $performance_stats['total_items_sold']; ?> items sold
                                </small>
                            </div>
                            <div class="stats-icon info">
                                <i class="fas fa-percentage"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small">
                                <span>Industry Avg:</span>
                                <span class="fw-bold"><?php echo $industry_benchmarks['conversion_rate']; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance Trends & Analysis -->
        <div class="row g-4">
            <!-- Performance Trend Chart -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">Performance Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Customer Segments -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Customer Segments</h5>
                        <span class="badge bg-primary"><?php echo $performance_stats['total_customers']; ?> total</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($customer_segments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No customer data available</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($customer_segments as $segment): 
                                    $percentage = $performance_stats['total_customers'] > 0 ? 
                                        ($segment['customer_count'] / $performance_stats['total_customers']) * 100 : 0;
                                ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo $segment['customer_segment']; ?></h6>
                                            <small class="text-muted">
                                                <?php echo number_format($percentage, 1); ?>% of customers
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?php echo $segment['customer_count']; ?></div>
                                            <small class="text-success">$<?php echo number_format($segment['avg_spent'], 2); ?> avg</small>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar" 
                                             style="width: <?php echo $percentage; ?>%;
                                                    background-color: <?php 
                                                        echo $segment['customer_segment'] == 'New Customers' ? '#4361ee' : 
                                                               ($segment['customer_segment'] == 'Repeat Customers' ? '#22c55e' : '#f59e0b');
                                                    ?>;">
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Product Performance -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Top Performing Products</h5>
                        <a href="../products/products.php" class="btn btn-sm btn-outline-primary">
                            Manage Products
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_products)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No product performance data</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Sales</th>
                                            <th>Revenue</th>
                                            <th>Rating</th>
                                            <th>CR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($top_products as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <?php if (!empty($product['image'])): ?>
                                                            <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $product['image']; ?>" 
                                                                 class="rounded" width="32" height="32">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                                 style="width: 32px; height: 32px;">
                                                                <i class="fas fa-box text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars(substr($product['name'], 0, 20)); ?><?php echo strlen($product['name']) > 20 ? '...' : ''; ?></div>
                                                        <small class="text-muted">Stock: <?php echo $product['stock']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="fw-bold"><?php echo $product['total_sold'] ?? 0; ?></td>
                                            <td class="text-success fw-bold">$<?php echo number_format($product['total_revenue'] ?? 0, 2); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="text-warning me-1">
                                                        <i class="fas fa-star"></i>
                                                    </div>
                                                    <span><?php echo number_format($product['avg_rating'] ?? 0, 1); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo ($product['conversion_rate'] ?? 0) >= 3 ? 'success' : 
                                                         (($product['conversion_rate'] ?? 0) >= 1 ? 'warning' : 'secondary'); 
                                                ?>">
                                                    <?php echo number_format($product['conversion_rate'] ?? 0, 1); ?>%
                                                </span>
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
            
            <!-- Category Performance -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">Category Performance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($category_performance)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No category data available</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Products</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                            <th>Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($category_performance as $category): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($category['category']); ?></span>
                                            </td>
                                            <td class="fw-bold"><?php echo $category['product_count']; ?></td>
                                            <td><?php echo $category['order_count']; ?></td>
                                            <td class="text-success fw-bold">$<?php echo number_format($category['total_revenue'], 2); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="text-warning me-1">
                                                        <i class="fas fa-star"></i>
                                                    </div>
                                                    <span><?php echo number_format($category['avg_rating'], 1); ?></span>
                                                </div>
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
            
            <!-- Review Analysis -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Review Analysis</h5>
                        <a href="../reviews/reviews.php" class="btn btn-sm btn-outline-primary">
                            View All Reviews
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($review_analysis)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No reviews yet</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="reviewDistributionChart" height="200"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="list-group list-group-flush">
                                        <?php for($i = 5; $i >= 1; $i--): 
                                            $count = $review_distribution[$i] ?? 0;
                                            $percentage = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
                                        ?>
                                        <div class="list-group-item border-0 px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <?php for($j = 1; $j <= 5; $j++): ?>
                                                        <i class="fas fa-star <?php echo $j <= $i ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="text-end">
                                                    <span class="fw-bold"><?php echo $count; ?></span>
                                                    <small class="text-muted">(<?php echo number_format($percentage, 1); ?>%)</small>
                                                </div>
                                            </div>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="fw-bold text-primary"><?php echo $total_reviews; ?></div>
                                        <small class="text-muted">Total Reviews</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-success"><?php echo number_format($performance_stats['avg_rating'], 1); ?>/5</div>
                                        <small class="text-muted">Average Rating</small>
                                    </div>
                                    <div class="col-4">
                                        <?php 
                                            $response_rate = 0;
                                            if (!empty($review_analysis)) {
                                                $responded = array_sum(array_column($review_analysis, 'responded_reviews'));
                                                $response_rate = $total_reviews > 0 ? ($responded / $total_reviews) * 100 : 0;
                                            }
                                        ?>
                                        <div class="fw-bold text-info"><?php echo number_format($response_rate, 1); ?>%</div>
                                        <small class="text-muted">Response Rate</small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Analysis -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">Inventory Analysis</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inventory_analysis)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-warehouse fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No inventory data available</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="inventoryChart" height="200"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="list-group list-group-flush">
                                        <?php foreach($inventory_analysis as $inventory): 
                                            $bg_color = $inventory['stock_status'] == 'In Stock' ? 'success' : 
                                                       ($inventory['stock_status'] == 'Low Stock' ? 'warning' : 'danger');
                                        ?>
                                        <div class="list-group-item border-0 px-0 py-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge bg-<?php echo $bg_color; ?> me-2">
                                                        <?php echo $inventory['product_count']; ?>
                                                    </span>
                                                    <span><?php echo $inventory['stock_status']; ?></span>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">
                                                        <?php echo $inventory['total_sales']; ?> sales
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="progress mt-2" style="height: 6px;">
                                                <div class="progress-bar bg-<?php echo $bg_color; ?>" 
                                                     style="width: <?php 
                                                        $percentage = $performance_stats['total_products'] > 0 ? 
                                                            ($inventory['product_count'] / $performance_stats['total_products']) * 100 : 0;
                                                        echo $percentage;
                                                     ?>%">
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 alert alert-info">
                                <div class="d-flex">
                                    <i class="fas fa-lightbulb me-2 mt-1"></i>
                                    <div>
                                        <small>
                                            <strong>Inventory Tip:</strong> 
                                            <?php 
                                                $out_of_stock_percentage = $performance_stats['total_products'] > 0 ? 
                                                    ($performance_stats['out_of_stock_products'] / $performance_stats['total_products']) * 100 : 0;
                                                if ($out_of_stock_percentage > 20) {
                                                    echo 'High percentage of out-of-stock items. Consider restocking popular products.';
                                                } elseif ($performance_stats['in_stock_products'] < 5) {
                                                    echo 'Low inventory levels. Add more products to increase sales.';
                                                } else {
                                                    echo 'Inventory levels are good. Focus on marketing in-stock products.';
                                                }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Performance Insights -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-chart-pie me-2 text-primary"></i>
                            Performance Insights & Recommendations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <div class="d-flex">
                                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <i class="fas fa-bullseye text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-1">Conversion Strategy</h6>
                                                <p class="small text-muted mb-0">
                                                    <?php if ($sell_through_rate < $industry_benchmarks['conversion_rate']): ?>
                                                        Your conversion rate is below industry average. Consider improving product images and descriptions.
                                                    <?php else: ?>
                                                        Great conversion rate! Consider upselling and cross-selling opportunities.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <div class="d-flex">
                                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <i class="fas fa-users text-success"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-1">Customer Retention</h6>
                                                <p class="small text-muted mb-0">
                                                    <?php if ($customer_retention_rate < $industry_benchmarks['customer_retention']): ?>
                                                        Customer retention needs improvement. Consider loyalty programs and follow-up emails.
                                                    <?php else: ?>
                                                        Excellent customer retention! Focus on turning customers into brand advocates.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <div class="d-flex">
                                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <i class="fas fa-star text-warning"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-1">Review Management</h6>
                                                <p class="small text-muted mb-0">
                                                    <?php if ($performance_stats['avg_rating'] < 4.0): ?>
                                                        Ratings below 4.0. Consider improving product quality and customer service.
                                                    <?php else: ?>
                                                        Great ratings! Respond to reviews to maintain customer relationships.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="fw-bold mb-3">Quick Actions</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="../products/low-stock.php" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Restock Low Inventory
                                </a>
                                <a href="../reviews/reviews.php" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-comment-dots me-1"></i> Respond to Reviews
                                </a>
                                <a href="../products/add.php" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-plus me-1"></i> Add New Products
                                </a>
                                <a href="sales.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-chart-line me-1"></i> View Sales Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
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

.progress {
    border-radius: 10px;
}
</style>

<script>
// Performance Trend Chart
let performanceChart;
function initPerformanceChart() {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    const months = <?php echo json_encode(array_column($monthly_trend, 'month')); ?>;
    const revenue = <?php echo json_encode(array_column($monthly_trend, 'revenue')); ?>;
    const orders = <?php echo json_encode(array_column($monthly_trend, 'orders')); ?>;
    const customers = <?php echo json_encode(array_column($monthly_trend, 'customers')); ?>;
    
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months.map(m => {
                const [year, month] = m.split('-');
                return new Date(year, month-1).toLocaleDateString('en-US', { month: 'short' });
            }).reverse(),
            datasets: [
                {
                    label: 'Revenue ($)',
                    data: revenue.reverse(),
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders',
                    data: orders.reverse(),
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                },
                {
                    label: 'Customers',
                    data: customers.reverse(),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
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
            stacked: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Performance Metrics Trend'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue ($)'
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
                        text: 'Orders & Customers'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

// Review Distribution Chart
function initReviewDistributionChart() {
    const ctx = document.getElementById('reviewDistributionChart').getContext('2d');
    
    const labels = ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'];
    const data = [
        <?php echo $review_distribution[5] ?? 0; ?>,
        <?php echo $review_distribution[4] ?? 0; ?>,
        <?php echo $review_distribution[3] ?? 0; ?>,
        <?php echo $review_distribution[2] ?? 0; ?>,
        <?php echo $review_distribution[1] ?? 0; ?>
    ];
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#22c55e', // 5 stars - green
                    '#a3e635', // 4 stars - light green
                    '#f59e0b', // 3 stars - amber
                    '#f97316', // 2 stars - orange
                    '#ef4444'  // 1 star - red
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} reviews (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Inventory Chart
function initInventoryChart() {
    const ctx = document.getElementById('inventoryChart').getContext('2d');
    
    const labels = <?php echo json_encode(array_column($inventory_analysis, 'stock_status')); ?>;
    const data = <?php echo json_encode(array_column($inventory_analysis, 'product_count')); ?>;
    const colors = ['#22c55e', '#f59e0b', '#ef4444']; // Green, Yellow, Red
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} products (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Print report
function printReport() {
    const printContent = document.querySelector('.main-content').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Performance Report - <?php echo htmlspecialchars($vendor['full_name'] ?? 'Vendor'); ?></title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .no-print { display: none !important; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
                    .stat-box { border: 1px solid #ddd; padding: 15px; text-align: center; }
                    .chart-container { margin: 20px 0; text-align: center; }
                    .insight-box { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Performance Report</h1>
                    <p>Vendor: <?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?></p>
                    <p>Period: <?php echo date('M d, Y', strtotime($filter_start)); ?> to <?php echo date('M d, Y', strtotime($filter_end)); ?></p>
                    <p>Generated: <?php echo date('M d, Y h:i A'); ?></p>
                </div>
                
                <div class="stats">
                    <div class="stat-box">
                        <h3>$<?php echo number_format($performance_stats['total_revenue'], 2); ?></h3>
                        <p>Revenue</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo number_format($customer_retention_rate, 1); ?>%</h3>
                        <p>Customer Retention</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo number_format($performance_stats['avg_rating'], 1); ?>/5</h3>
                        <p>Average Rating</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo number_format($sell_through_rate, 2); ?>%</h3>
                        <p>Conversion Rate</p>
                    </div>
                </div>
                
                <h2>Performance Summary</h2>
                <div class="insight-box">
                    <p><strong>Total Products:</strong> <?php echo $performance_stats['total_products']; ?></p>
                    <p><strong>Total Customers:</strong> <?php echo $performance_stats['total_customers']; ?></p>
                    <p><strong>Active Customers:</strong> <?php echo $performance_stats['active_customers']; ?></p>
                    <p><strong>Total Reviews:</strong> <?php echo $performance_stats['total_reviews']; ?></p>
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

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    initPerformanceChart();
    <?php if (!empty($review_analysis)): ?>initReviewDistributionChart();<?php endif; ?>
    <?php if (!empty($inventory_analysis)): ?>initInventoryChart();<?php endif; ?>
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Auto-refresh data every 10 minutes
setTimeout(function() {
    if (confirm('Refresh performance data?')) {
        window.location.reload();
    }
}, 600000);
</script>

<?php require_once '../../includes/footer.php'; ?>