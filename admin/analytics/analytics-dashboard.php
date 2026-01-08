<?php
// admin/analytics-dashboard.php
// session_start();

require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}

// Initialize variables
$default_range = isset($_GET['range']) ? $_GET['range'] : '30days';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set date range based on selection
switch ($default_range) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date = date('Y-m-d', strtotime('-1 day'));
        break;
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $end_date = date('Y-m-d');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_year':
        $start_date = date('Y-01-01', strtotime('-1 year'));
        $end_date = date('Y-12-31', strtotime('-1 year'));
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
}

try {
    $db = getDB();
    
    // ==================== REALTIME STATS ====================
    
    // Today's Stats
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    
    // Today's Revenue
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders 
                         WHERE order_date BETWEEN ? AND ? 
                         AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$today_start, $today_end]);
    $today_revenue = $stmt->fetchColumn();
    
    // Today's Orders
    $stmt = $db->prepare("SELECT COUNT(*) as orders FROM orders 
                         WHERE order_date BETWEEN ? AND ? 
                         AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$today_start, $today_end]);
    $today_orders = $stmt->fetchColumn();
    
    // Today's Visitors (approximation based on sessions)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT ip_address) as visitors FROM user_sessions 
                         WHERE login_time BETWEEN ? AND ?");
    $stmt->execute([$today_start, $today_end]);
    $today_visitors = $stmt->fetchColumn();
    
    // Today's New Customers
    $stmt = $db->prepare("SELECT COUNT(*) as customers FROM users 
                         WHERE created_at BETWEEN ? AND ? 
                         AND user_type = 'user'");
    $stmt->execute([$today_start, $today_end]);
    $today_customers = $stmt->fetchColumn();
    
    // ==================== OVERVIEW METRICS ====================
    
    // Total Revenue
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders 
                         WHERE order_date BETWEEN ? AND ? 
                         AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $total_revenue = $stmt->fetchColumn();
    
    // Total Orders
    $stmt = $db->prepare("SELECT COUNT(*) as orders FROM orders 
                         WHERE order_date BETWEEN ? AND ? 
                         AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $total_orders = $stmt->fetchColumn();
    
    // Average Order Value
    $avg_order_value = ($total_orders > 0) ? $total_revenue / $total_orders : 0;
    
    // Total Customers
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as customers FROM orders 
                         WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $total_customers = $stmt->fetchColumn();
    
    // Conversion Rate (based on sessions vs orders)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT session_token) as sessions FROM user_sessions 
                         WHERE login_time BETWEEN ? AND ?");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $total_sessions = $stmt->fetchColumn();
    
    $conversion_rate = ($total_sessions > 0) ? ($total_orders / $total_sessions) * 100 : 0;
    
    // Repeat Customer Rate
    $stmt = $db->prepare("
        SELECT COUNT(*) as repeat_customers FROM (
            SELECT user_id FROM orders 
            WHERE order_date BETWEEN ? AND ?
            AND status NOT IN ('cancelled', 'failed')
            GROUP BY user_id 
            HAVING COUNT(*) > 1
        ) as repeaters
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $repeat_customers = $stmt->fetchColumn();
    
    $repeat_rate = ($total_customers > 0) ? ($repeat_customers / $total_customers) * 100 : 0;
    
    // ==================== REVENUE CHARTS DATA ====================
    
    // Daily Revenue Trend
    $stmt = $db->prepare("
        SELECT 
            DATE(order_date) as date,
            SUM(total_amount) as revenue,
            COUNT(*) as orders,
            COUNT(DISTINCT user_id) as customers
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
        GROUP BY DATE(order_date)
        ORDER BY date
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly Revenue Trend (Last 12 months)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m') as month,
            SUM(total_amount) as revenue,
            COUNT(*) as orders,
            COUNT(DISTINCT user_id) as customers
        FROM orders 
        WHERE order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND status NOT IN ('cancelled', 'failed')
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==================== TOP PERFORMERS ====================
    
    // Top Products
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.category,
            SUM(oi.quantity) as units_sold,
            SUM(oi.quantity * oi.unit_price) as revenue,
            COUNT(DISTINCT o.id) as orders
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.id, p.name, p.category
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Categories
    $stmt = $db->prepare("
        SELECT 
            p.category,
            SUM(o.total_amount) as revenue,
            COUNT(DISTINCT o.id) as orders,
            SUM(oi.quantity) as units_sold
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.category
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $top_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Customers
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.order_date) as last_order
        FROM users u
        JOIN orders o ON u.id = o.user_id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY u.id, u.full_name, u.email
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==================== TRAFFIC SOURCES ====================
    
    // Traffic by Device Type (approximation based on user_agent)
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%' OR user_agent LIKE '%iPhone%' THEN 'Mobile'
                WHEN user_agent LIKE '%Tablet%' OR user_agent LIKE '%iPad%' THEN 'Tablet'
                ELSE 'Desktop'
            END as device_type,
            COUNT(*) as sessions,
            COUNT(DISTINCT ip_address) as visitors
        FROM user_sessions 
        WHERE login_time BETWEEN ? AND ?
        GROUP BY device_type
        ORDER BY sessions DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $device_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hourly Traffic Pattern
    $stmt = $db->prepare("
        SELECT 
            HOUR(login_time) as hour,
            COUNT(*) as sessions,
            COUNT(DISTINCT ip_address) as visitors
        FROM user_sessions 
        WHERE login_time BETWEEN ? AND ?
        GROUP BY HOUR(login_time)
        ORDER BY hour
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $hourly_traffic = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==================== SALES FUNNEL ====================
    
    // Visitors
    $total_visitors = $total_sessions;
    
    // Add to Cart (approximation)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as carts FROM cart_items 
                         WHERE added_at BETWEEN ? AND ?");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $cart_visitors = $stmt->fetchColumn();
    
    // Checkout Started (orders with pending status)
    $stmt = $db->prepare("SELECT COUNT(*) as checkouts FROM orders 
                         WHERE order_date BETWEEN ? AND ? 
                         AND status = 'pending'");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $checkout_visitors = $stmt->fetchColumn();
    
    // Purchases Completed
    $completed_orders = $total_orders;
    
    // Funnel percentages
    $cart_rate = ($total_visitors > 0) ? ($cart_visitors / $total_visitors) * 100 : 0;
    $checkout_rate = ($cart_visitors > 0) ? ($checkout_visitors / $cart_visitors) * 100 : 0;
    $conversion_rate_funnel = ($checkout_visitors > 0) ? ($completed_orders / $checkout_visitors) * 100 : 0;
    
    // ==================== GEOGRAPHIC DATA ====================
    
    // Sales by Country/City
    $stmt = $db->prepare("
        SELECT 
            COALESCE(u.country, 'Unknown') as country,
            COALESCE(u.city, 'Unknown') as city,
            COUNT(DISTINCT o.id) as orders,
            SUM(o.total_amount) as revenue,
            COUNT(DISTINCT o.user_id) as customers
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY u.country, u.city
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $geographic_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==================== ORDER STATUS DISTRIBUTION ====================
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(total_amount) as revenue
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        GROUP BY status
        ORDER BY count DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $order_status_dist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading analytics data: ' . $e->getMessage();
    $today_revenue = $today_orders = $today_visitors = $today_customers = 0;
    $total_revenue = $total_orders = $avg_order_value = $total_customers = $conversion_rate = 0;
    $daily_data = $monthly_data = $top_products = $top_categories = $top_customers = [];
    $device_data = $hourly_traffic = $geographic_sales = $order_status_dist = [];
    $cart_rate = $checkout_rate = $conversion_rate_funnel = 0;
}

$page_title = 'Analytics Dashboard';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Site Analytics Dashboard</h1>
                <p class="text-muted mb-0">Real-time analytics and insights</p>
            </div>
            <div>
                <button class="btn btn-outline-primary me-2" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt me-2"></i> Refresh
                </button>
                <button class="btn btn-primary me-2">
                    <i class="fa-solid fa-diagram-project me-2"></i><a href="<?php echo SITE_URL; ?>admin/analytics/analytics.php" class="text-decoration-none text-white">Analytics</a> 
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-download me-2"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="exportData('pdf')"><i class="fas fa-file-pdf me-2"></i> PDF Report</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportData('excel')"><i class="fas fa-file-excel me-2"></i> Excel Data</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportData('csv')"><i class="fas fa-file-csv me-2"></i> CSV Data</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Date Range Selector -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" name="range" onchange="this.form.submit()">
                            <option value="today" <?= $default_range == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="yesterday" <?= $default_range == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                            <option value="7days" <?= $default_range == '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30days" <?= $default_range == '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="90days" <?= $default_range == '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                            <option value="this_month" <?= $default_range == 'this_month' ? 'selected' : '' ?>>This Month</option>
                            <option value="last_month" <?= $default_range == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                            <option value="this_year" <?= $default_range == 'this_year' ? 'selected' : '' ?>>This Year</option>
                            <option value="last_year" <?= $default_range == 'last_year' ? 'selected' : '' ?>>Last Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 custom-date-range" style="display: <?= $default_range == 'custom' ? 'block' : 'none' ?>">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                    </div>
                    
                    <div class="col-md-3 custom-date-range" style="display: <?= $default_range == 'custom' ? 'block' : 'none' ?>">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Today's Stats -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Today's Revenue
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?= number_format($today_revenue, 2) ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><i class="fas fa-calendar me-1"></i> <?= date('M d, Y') ?></span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Today's Orders
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($today_orders) ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><i class="fas fa-shopping-cart me-1"></i> Completed</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Today's Visitors
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($today_visitors) ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><i class="fas fa-users me-1"></i> Unique Visitors</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-eye fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    New Customers
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= number_format($today_customers) ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><i class="fas fa-user-plus me-1"></i> Registered Today</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Charts Row -->
        <div class="row mb-4">
            <!-- Revenue Chart -->
            <div class="col-xl-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Revenue & Orders Trend</h6>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary active" onclick="changeChartView('daily')">Daily</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="changeChartView('monthly')">Monthly</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="revenueChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Traffic Sources -->
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Traffic by Device</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4">
                            <canvas id="deviceChart" height="200"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php foreach($device_data as $device): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-<?= $device['device_type'] == 'Mobile' ? 'mobile-alt' : ($device['device_type'] == 'Tablet' ? 'tablet-alt' : 'desktop') ?> me-2"></i><?= $device['device_type'] ?></span>
                                <span class="font-weight-bold"><?= number_format($device['visitors']) ?> visitors</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sales Funnel & Conversion -->
        <div class="row mb-4">
            <!-- Sales Funnel -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Sales Funnel</h6>
                    </div>
                    <div class="card-body">
                        <div class="funnel-container">
                            <div class="funnel-step">
                                <div class="funnel-label">Visitors</div>
                                <div class="funnel-bar" style="width: 100%">
                                    <div class="funnel-value"><?= number_format($total_visitors) ?></div>
                                </div>
                                <div class="funnel-percentage">100%</div>
                            </div>
                            
                            <div class="funnel-step">
                                <div class="funnel-label">Added to Cart</div>
                                <div class="funnel-bar" style="width: <?= $cart_rate ?>%">
                                    <div class="funnel-value"><?= number_format($cart_visitors) ?></div>
                                </div>
                                <div class="funnel-percentage"><?= number_format($cart_rate, 1) ?>%</div>
                            </div>
                            
                            <div class="funnel-step">
                                <div class="funnel-label">Checkout Started</div>
                                <div class="funnel-bar" style="width: <?= $checkout_rate ?>%">
                                    <div class="funnel-value"><?= number_format($checkout_visitors) ?></div>
                                </div>
                                <div class="funnel-percentage"><?= number_format($checkout_rate, 1) ?>%</div>
                            </div>
                            
                            <div class="funnel-step">
                                <div class="funnel-label">Purchases Completed</div>
                                <div class="funnel-bar" style="width: <?= $conversion_rate_funnel ?>%">
                                    <div class="funnel-value"><?= number_format($completed_orders) ?></div>
                                </div>
                                <div class="funnel-percentage"><?= number_format($conversion_rate_funnel, 1) ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Conversion Metrics -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Conversion Metrics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-4">
                                <div class="card border-left-success shadow-sm py-3">
                                    <div class="card-body">
                                        <div class="text-success font-weight-bold mb-2">Conversion Rate</div>
                                        <div class="display-4 mb-2"><?= number_format($conversion_rate, 2) ?>%</div>
                                        <small class="text-muted">Sessions to Orders</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6 mb-4">
                                <div class="card border-left-info shadow-sm py-3">
                                    <div class="card-body">
                                        <div class="text-info font-weight-bold mb-2">Repeat Customer Rate</div>
                                        <div class="display-4 mb-2"><?= number_format($repeat_rate, 1) ?>%</div>
                                        <small class="text-muted">Returning Customers</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="card border-left-warning shadow-sm py-3">
                                    <div class="card-body">
                                        <div class="text-warning font-weight-bold mb-2">Avg. Order Value</div>
                                        <div class="display-4 mb-2">$<?= number_format($avg_order_value, 2) ?></div>
                                        <small class="text-muted">Average per order</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="card border-left-primary shadow-sm py-3">
                                    <div class="card-body">
                                        <div class="text-primary font-weight-bold mb-2">Customer Value</div>
                                        <?php 
                                        $customer_value = ($total_customers > 0) ? $total_revenue / $total_customers : 0;
                                        ?>
                                        <div class="display-4 mb-2">$<?= number_format($customer_value, 2) ?></div>
                                        <small class="text-muted">Revenue per customer</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Performers -->
        <div class="row mb-4">
            <!-- Top Products -->
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Top Products</h6>
                        <span class="badge bg-primary">Revenue</span>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach($top_products as $index => $product): ?>
                            <div class="list-group-item d-flex align-items-center px-0">
                                <div class="flex-shrink-0">
                                    <div class="avatar-sm bg-light rounded">
                                        <span class="avatar-title bg-primary text-white rounded">
                                            <?= $index + 1 ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1"><?= htmlspecialchars($product['name']) ?></h6>
                                    <p class="text-muted mb-0">
                                        <small><?= $product['category'] ?> • <?= number_format($product['units_sold']) ?> sold</small>
                                    </p>
                                </div>
                                <div class="flex-shrink-0 text-end">
                                    <div class="fw-bold">$<?= number_format($product['revenue'], 2) ?></div>
                                    <small class="text-muted"><?= number_format($product['orders']) ?> orders</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Categories -->
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Top Categories</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4">
                            <canvas id="categoryChart" height="200"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php 
                            $total_category_revenue = array_sum(array_column($top_categories, 'revenue'));
                            foreach($top_categories as $category): 
                                $percentage = ($total_category_revenue > 0) ? ($category['revenue'] / $total_category_revenue) * 100 : 0;
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span><?= htmlspecialchars($category['category']) ?></span>
                                    <div class="progress mt-1" style="height: 4px; width: 100px;">
                                        <div class="progress-bar bg-primary" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                </div>
                                <span class="font-weight-bold">$<?= number_format($category['revenue'], 2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Top Customers</h6>
                        <span class="badge bg-primary">Loyalty</span>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach($top_customers as $index => $customer): ?>
                            <div class="list-group-item d-flex align-items-center px-0">
                                <div class="flex-shrink-0">
                                    <div class="avatar-sm bg-light rounded-circle">
                                        <span class="avatar-title bg-success text-white rounded-circle">
                                            <?= substr($customer['full_name'] ?? $customer['email'], 0, 1) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1"><?= htmlspecialchars($customer['full_name'] ?? 'Customer') ?></h6>
                                    <p class="text-muted mb-0">
                                        <small><?= $customer['email'] ?></small>
                                    </p>
                                </div>
                                <div class="flex-shrink-0 text-end">
                                    <div class="fw-bold">$<?= number_format($customer['total_spent'], 2) ?></div>
                                    <small class="text-muted"><?= number_format($customer['order_count']) ?> orders</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Geographic & Hourly Analysis -->
        <div class="row mb-4">
            <!-- Geographic Distribution -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Geographic Distribution</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($geographic_sales)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>City</th>
                                        <th>Customers</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($geographic_sales as $geo): ?>
                                    <tr>
                                        <td><i class="fas fa-globe-americas me-2"></i><?= htmlspecialchars($geo['country']) ?></td>
                                        <td><?= htmlspecialchars($geo['city']) ?></td>
                                        <td><?= number_format($geo['customers']) ?></td>
                                        <td><?= number_format($geo['orders']) ?></td>
                                        <td class="font-weight-bold">$<?= number_format($geo['revenue'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No geographic data available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Hourly Traffic Pattern -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Hourly Traffic Pattern</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="hourlyChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Status Distribution -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h6 class="m-0 font-weight-bold text-primary">Order Status Distribution</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach($order_status_dist as $status): 
                        $percentage = ($total_orders > 0) ? ($status['count'] / $total_orders) * 100 : 0;
                        $color = match($status['status']) {
                            'delivered' => 'success',
                            'processing' => 'info',
                            'shipped' => 'primary',
                            'pending' => 'warning',
                            'cancelled' => 'danger',
                            default => 'secondary'
                        };
                    ?>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="card border-left-<?= $color ?> shadow-sm py-2">
                            <div class="card-body text-center">
                                <div class="text-<?= $color ?> font-weight-bold mb-1">
                                    <?= ucfirst($status['status']) ?>
                                </div>
                                <div class="h4"><?= number_format($status['count']) ?></div>
                                <small class="text-muted"><?= number_format($percentage, 1) ?>%</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Real-time Updates -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-bolt me-2"></i>Real-time Updates
                </h6>
                <button class="btn btn-sm btn-outline-primary" onclick="startLiveUpdates()">
                    <i class="fas fa-play me-1"></i> Start Live
                </button>
            </div>
            <div class="card-body">
                <div class="row" id="realTimeUpdates">
                    <!-- Real-time data will be loaded here -->
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Charts
let revenueChart, deviceChart, categoryChart, hourlyChart;
let chartView = 'daily';

// Format currency
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2});
}

// Format number
function formatNumber(num) {
    return parseFloat(num).toLocaleString('en-US');
}

// Initialize charts
function initCharts() {
    // Revenue Chart (Daily/Monthly)
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    
    const dailyLabels = [<?php 
        $labels = [];
        foreach($daily_data as $data) {
            $labels[] = "'" . date('M d', strtotime($data['date'])) . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const dailyRevenue = [<?php 
        $revenue = [];
        foreach($daily_data as $data) {
            $revenue[] = $data['revenue'] ?? 0;
        }
        echo implode(', ', $revenue);
    ?>];
    
    const dailyOrders = [<?php 
        $orders = [];
        foreach($daily_data as $data) {
            $orders[] = $data['orders'] ?? 0;
        }
        echo implode(', ', $orders);
    ?>];
    
    const monthlyLabels = [<?php 
        $labels = [];
        foreach($monthly_data as $data) {
            $labels[] = "'" . date('M Y', strtotime($data['month'] . '-01')) . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const monthlyRevenue = [<?php 
        $revenue = [];
        foreach($monthly_data as $data) {
            $revenue[] = $data['revenue'] ?? 0;
        }
        echo implode(', ', $revenue);
    ?>];
    
    const monthlyOrders = [<?php 
        $orders = [];
        foreach($monthly_data as $data) {
            $orders[] = $data['orders'] ?? 0;
        }
        echo implode(', ', $orders);
    ?>];
    
    revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Revenue',
                data: dailyRevenue,
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }, {
                label: 'Orders',
                data: dailyOrders,
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                borderColor: 'rgba(28, 200, 138, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                if (label === 'Revenue') {
                                    label += ': ' + formatCurrency(context.parsed.y);
                                } else {
                                    label += ': ' + formatNumber(context.parsed.y);
                                }
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
    
    // Device Chart
    const deviceCtx = document.getElementById('deviceChart').getContext('2d');
    const deviceLabels = [<?php 
        $labels = [];
        foreach($device_data as $device) {
            $labels[] = "'" . $device['device_type'] . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const deviceVisitors = [<?php 
        $visitors = [];
        foreach($device_data as $device) {
            $visitors[] = $device['visitors'];
        }
        echo implode(', ', $visitors);
    ?>];
    
    deviceChart = new Chart(deviceCtx, {
        type: 'doughnut',
        data: {
            labels: deviceLabels,
            datasets: [{
                data: deviceVisitors,
                backgroundColor: [
                    '#4e73df',
                    '#1cc88a',
                    '#36b9cc',
                    '#f6c23e',
                    '#e74a3b'
                ],
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Category Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryLabels = [<?php 
        $labels = [];
        foreach($top_categories as $cat) {
            $labels[] = "'" . addslashes($cat['category']) . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const categoryRevenue = [<?php 
        $revenue = [];
        foreach($top_categories as $cat) {
            $revenue[] = $cat['revenue'];
        }
        echo implode(', ', $revenue);
    ?>];
    
    categoryChart = new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryRevenue,
                backgroundColor: [
                    '#4e73df',
                    '#1cc88a',
                    '#36b9cc',
                    '#f6c23e',
                    '#e74a3b'
                ]
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Hourly Traffic Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    
    // Create 24-hour array
    const hours = Array.from({length: 24}, (_, i) => i);
    const hourlyData = hours.map(hour => {
        const found = <?= json_encode($hourly_traffic) ?>.find(h => parseInt(h.hour) === hour);
        return found ? found.visitors : 0;
    });
    
    hourlyChart = new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: hours.map(h => h + ':00'),
            datasets: [{
                label: 'Visitors',
                data: hourlyData,
                backgroundColor: 'rgba(78, 115, 223, 0.8)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

// Change chart view between daily and monthly
function changeChartView(view) {
    chartView = view;
    const dailyBtn = document.querySelector('.btn-group .btn:nth-child(1)');
    const monthlyBtn = document.querySelector('.btn-group .btn:nth-child(2)');
    
    dailyBtn.classList.toggle('active', view === 'daily');
    monthlyBtn.classList.toggle('active', view === 'monthly');
    
    // Update chart data
    const dailyLabels = [<?php 
        $labels = [];
        foreach($daily_data as $data) {
            $labels[] = "'" . date('M d', strtotime($data['date'])) . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const dailyRevenue = [<?php 
        $revenue = [];
        foreach($daily_data as $data) {
            $revenue[] = $data['revenue'] ?? 0;
        }
        echo implode(', ', $revenue);
    ?>];
    
    const dailyOrders = [<?php 
        $orders = [];
        foreach($daily_data as $data) {
            $orders[] = $data['orders'] ?? 0;
        }
        echo implode(', ', $orders);
    ?>];
    
    const monthlyLabels = [<?php 
        $labels = [];
        foreach($monthly_data as $data) {
            $labels[] = "'" . date('M Y', strtotime($data['month'] . '-01')) . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const monthlyRevenue = [<?php 
        $revenue = [];
        foreach($monthly_data as $data) {
            $revenue[] = $data['revenue'] ?? 0;
        }
        echo implode(', ', $revenue);
    ?>];
    
    const monthlyOrders = [<?php 
        $orders = [];
        foreach($monthly_data as $data) {
            $orders[] = $data['orders'] ?? 0;
        }
        echo implode(', ', $orders);
    ?>];
    
    revenueChart.data.labels = view === 'daily' ? dailyLabels : monthlyLabels;
    revenueChart.data.datasets[0].data = view === 'daily' ? dailyRevenue : monthlyRevenue;
    revenueChart.data.datasets[1].data = view === 'daily' ? dailyOrders : monthlyOrders;
    revenueChart.update();
}

// Toggle custom date range fields
document.querySelector('select[name="range"]').addEventListener('change', function() {
    const customFields = document.querySelectorAll('.custom-date-range');
    if (this.value === 'custom') {
        customFields.forEach(field => field.style.display = 'block');
    } else {
        customFields.forEach(field => field.style.display = 'none');
    }
});

// Refresh dashboard
function refreshDashboard() {
    window.location.reload();
}

// Export data
function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.append('export', format);
    
    Swal.fire({
        title: 'Exporting Data',
        text: 'Preparing your ' + format.toUpperCase() + ' report...',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            
            setTimeout(() => {
                window.open(`export-analytics.php?${params.toString()}`, '_blank');
                Swal.close();
            }, 1000);
        }
    });
}

// Start live updates
function startLiveUpdates() {
    Swal.fire({
        title: 'Live Updates Started',
        text: 'Dashboard will update every 30 seconds',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
    
    // Simulate real-time updates (in a real app, you would use WebSockets)
    setInterval(() => {
        fetch('real-time-data.php')
            .then(response => response.json())
            .then(data => {
                updateRealTimeData(data);
            });
    }, 30000);
}

// Update real-time data
function updateRealTimeData(data) {
    const container = document.getElementById('realTimeUpdates');
    const now = new Date();
    const timeStr = now.toLocaleTimeString();
    
    container.innerHTML = `
        <div class="col-md-3 col-6">
            <div class="card border-left-primary shadow-sm py-2">
                <div class="card-body text-center">
                    <div class="text-primary font-weight-bold mb-1">Active Users</div>
                    <div class="h4">${data.active_users || 0}</div>
                    <small class="text-muted">Last updated: ${timeStr}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-left-success shadow-sm py-2">
                <div class="card-body text-center">
                    <div class="text-success font-weight-bold mb-1">Orders Today</div>
                    <div class="h4">${data.orders_today || 0}</div>
                    <small class="text-muted">Last updated: ${timeStr}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-left-info shadow-sm py-2">
                <div class="card-body text-center">
                    <div class="text-info font-weight-bold mb-1">Revenue Today</div>
                    <div class="h4">${formatCurrency(data.revenue_today || 0)}</div>
                    <small class="text-muted">Last updated: ${timeStr}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-left-warning shadow-sm py-2">
                <div class="card-body text-center">
                    <div class="text-warning font-weight-bold mb-1">New Customers</div>
                    <div class="h4">${data.new_customers || 0}</div>
                    <small class="text-muted">Last updated: ${timeStr}</small>
                </div>
            </div>
        </div>
    `;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    
    // Set up real-time updates initially
    updateRealTimeData({});
});
</script>

<style>
.funnel-container {
    padding: 20px;
}

.funnel-step {
    margin-bottom: 20px;
    position: relative;
}

.funnel-label {
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.funnel-bar {
    background: linear-gradient(90deg, #4e73df, #1cc88a);
    height: 40px;
    border-radius: 5px;
    position: relative;
    transition: width 1s ease-in-out;
}

.funnel-value {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-weight: bold;
}

.funnel-percentage {
    text-align: right;
    margin-top: 5px;
    font-weight: bold;
    color: #4e73df;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-title {
    font-size: 1.2rem;
    font-weight: bold;
}

.display-4 {
    font-size: 2.5rem;
    font-weight: 300;
    line-height: 1.2;
}
</style>

<?php require_once '../includes/footer.php'; ?>