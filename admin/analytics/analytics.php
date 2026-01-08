<?php
// admin/analytics.php
// session_start();

require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}

// Initialize variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$time_period = isset($_GET['time_period']) ? $_GET['time_period'] : 'monthly';

// Default to current month if dates are invalid
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Ensure end date is not before start date
if (strtotime($end_date) < strtotime($start_date)) {
    $end_date = $start_date;
}

// Helper functions
function formatCurrency($amount) {
    return '$' . number_format($amount ?? 0, 2);
}

function formatNumber($number) {
    return number_format($number ?? 0);
}

function getTrendArrow($current, $previous) {
    if ($previous == 0) return '<i class="fas fa-minus text-secondary"></i>';
    
    $change = (($current - $previous) / $previous) * 100;
    
    if ($change > 0) {
        return '<i class="fas fa-arrow-up text-success"></i> ' . number_format(abs($change), 1) . '%';
    } elseif ($change < 0) {
        return '<i class="fas fa-arrow-down text-danger"></i> ' . number_format(abs($change), 1) . '%';
    } else {
        return '<i class="fas fa-minus text-secondary"></i> 0%';
    }
}

try {
    $db = getDB();
    
    // Get previous period for comparison
    $prev_start_date = date('Y-m-d', strtotime($start_date . ' -1 month'));
    $prev_end_date = date('Y-m-d', strtotime($end_date . ' -1 month'));
    
    // ==================== OVERVIEW METRICS ====================
    
    // Total Revenue
    $stmt = $db->prepare("
        SELECT 
            SUM(total_amount) as current_revenue,
            (SELECT SUM(total_amount) FROM orders 
             WHERE status NOT IN ('cancelled', 'failed')
             AND order_date BETWEEN ? AND ?) as previous_revenue
        FROM orders 
        WHERE status NOT IN ('cancelled', 'failed')
        AND order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$prev_start_date . ' 00:00:00', $prev_end_date . ' 23:59:59', 
                    $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $revenue_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total Orders
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as current_orders,
            (SELECT COUNT(*) FROM orders 
             WHERE status NOT IN ('cancelled', 'failed')
             AND order_date BETWEEN ? AND ?) as previous_orders
        FROM orders 
        WHERE status NOT IN ('cancelled', 'failed')
        AND order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$prev_start_date . ' 00:00:00', $prev_end_date . ' 23:59:59',
                    $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $orders_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Average Order Value
    $stmt = $db->prepare("
        SELECT 
            AVG(total_amount) as current_aov,
            (SELECT AVG(total_amount) FROM orders 
             WHERE status NOT IN ('cancelled', 'failed')
             AND order_date BETWEEN ? AND ?) as previous_aov
        FROM orders 
        WHERE status NOT IN ('cancelled', 'failed')
        AND order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$prev_start_date . ' 00:00:00', $prev_end_date . ' 23:59:59',
                    $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $aov_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // New Customers
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT user_id) as current_customers,
            (SELECT COUNT(DISTINCT user_id) FROM orders 
             WHERE order_date BETWEEN ? AND ?) as previous_customers
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$prev_start_date . ' 00:00:00', $prev_end_date . ' 23:59:59',
                    $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $customers_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ==================== TREND DATA ====================
    
    // Revenue Trend (Last 12 months)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m') as month,
            SUM(total_amount) as revenue,
            COUNT(*) as orders
        FROM orders 
        WHERE status NOT IN ('cancelled', 'failed')
        AND order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $revenue_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily Trend for Selected Period
    $stmt = $db->prepare("
        SELECT 
            DATE(order_date) as date,
            SUM(total_amount) as revenue,
            COUNT(*) as orders,
            COUNT(DISTINCT user_id) as customers
        FROM orders 
        WHERE status NOT IN ('cancelled', 'failed')
        AND order_date BETWEEN ? AND ?
        GROUP BY DATE(order_date)
        ORDER BY date
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $daily_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==================== PRODUCT ANALYTICS ====================
    
    // Top Selling Products
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.category,
            SUM(oi.quantity) as units_sold,
            SUM(oi.quantity * oi.unit_price) as revenue,
            COUNT(DISTINCT o.id) as order_count
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.id, p.name, p.category
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Product Categories Performance
    $stmt = $db->prepare("
        SELECT 
            p.category,
            COUNT(DISTINCT o.id) as order_count,
            SUM(o.total_amount) as revenue,
            SUM(oi.quantity) as units_sold,
            COUNT(DISTINCT o.user_id) as customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.category
        ORDER BY revenue DESC
        LIMIT 8
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $category_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==================== CUSTOMER ANALYTICS ====================
    
    // Customer Segmentation
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN order_count = 1 THEN 'First-time'
                WHEN order_count BETWEEN 2 AND 5 THEN 'Repeat'
                ELSE 'Loyal'
            END as segment,
            COUNT(*) as customer_count,
            SUM(total_spent) as total_revenue,
            AVG(total_spent) as avg_spent
        FROM (
            SELECT 
                u.id,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_spent
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE o.order_date BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled', 'failed')
            GROUP BY u.id
        ) as customer_stats
        GROUP BY segment
        ORDER BY FIELD(segment, 'First-time', 'Repeat', 'Loyal')
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $customer_segments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Customer Acquisition Channels (if you have this data)
    $stmt = $db->prepare("
        SELECT 
            source,
            COUNT(*) as customer_count,
            SUM(total_orders) as total_orders,
            SUM(total_revenue) as total_revenue
        FROM (
            SELECT 
                u.id,
                CASE 
                    WHEN u.created_at = u.updated_at THEN 'Organic'
                    ELSE 'Referred'
                END as source,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE u.created_at BETWEEN ? AND ?
            GROUP BY u.id
        ) as acquisition_data
        GROUP BY source
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $acquisition_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==================== CONVERSION ANALYTICS ====================
    
    // Cart Abandonment Rate
    $total_carts = 0;
    $completed_orders = 0;
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as cart_count FROM cart_items");
        $total_carts = $stmt->fetchColumn();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as order_count 
            FROM orders 
            WHERE order_date BETWEEN ? AND ?
            AND status NOT IN ('cancelled', 'failed')
        ");
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $completed_orders = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Handle error silently
    }
    
    $cart_abandonment_rate = $total_carts > 0 ? 
        (($total_carts - $completed_orders) / $total_carts) * 100 : 0;
    
    // ==================== GEOGRAPHIC ANALYTICS ====================
    
    // Sales by Country/City (if data available)
    $stmt = $db->prepare("
        SELECT 
            u.country,
            u.city,
            COUNT(DISTINCT o.id) as order_count,
            SUM(o.total_amount) as revenue,
            COUNT(DISTINCT o.user_id) as customer_count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        AND u.country IS NOT NULL
        GROUP BY u.country, u.city
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $geographic_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading analytics data: ' . $e->getMessage();
    $revenue_data = $orders_data = $aov_data = $customers_data = [];
    $revenue_trend = $daily_trend = $top_products = $category_performance = [];
    $customer_segments = $acquisition_data = $geographic_data = [];
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
                <h1 class="h3 mb-0">Analytics Dashboard</h1>
                <p class="text-muted mb-0">Comprehensive business analytics and insights</p>
            </div>
            <div>
                <button class="btn btn-outline-primary me-2" onclick="refreshAnalytics()">
                    <i class="fas fa-sync-alt me-2"></i> Refresh
                </button>
                <button class="btn btn-primary text-white me-2">
                    <i class="fa-solid fa-magnifying-glass-chart me-2"></i><a href="analytics-dashboard.php" class="text-white text-decoration-none">Site Analytics</a> 
                </button>
                <button class="btn btn-primary" href="#" onclick="exportAnalytics()">
                    <i class="fas fa-download me-2"></i> Export
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" 
                               value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Time Period</label>
                        <select class="form-select" name="time_period">
                            <option value="daily" <?php echo $time_period == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $time_period == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $time_period == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarterly" <?php echo $time_period == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Overview Metrics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Revenue
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($revenue_data['current_revenue'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo getTrendArrow($revenue_data['current_revenue'] ?? 0, $revenue_data['previous_revenue'] ?? 0); ?></span>
                                    <span class="ms-2">vs previous period</span>
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
                                    Total Orders
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatNumber($orders_data['current_orders'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo getTrendArrow($orders_data['current_orders'] ?? 0, $orders_data['previous_orders'] ?? 0); ?></span>
                                    <span class="ms-2">vs previous period</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                                    Avg. Order Value
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($aov_data['current_aov'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo getTrendArrow($aov_data['current_aov'] ?? 0, $aov_data['previous_aov'] ?? 0); ?></span>
                                    <span class="ms-2">vs previous period</span>
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
                <div class="card border-left-warning shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    New Customers
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatNumber($customers_data['current_customers'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo getTrendArrow($customers_data['current_customers'] ?? 0, $customers_data['previous_customers'] ?? 0); ?></span>
                                    <span class="ms-2">vs previous period</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Revenue Trend Chart -->
            <div class="col-xl-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Revenue Trend (12 Months)</h6>
                        <div class="dropdown no-arrow">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="changeChartType('revenue', 'line')">Line Chart</a></li>
                                <li><a class="dropdown-item" href="#" onclick="changeChartType('revenue', 'bar')">Bar Chart</a></li>
                                <li><a class="dropdown-item" href="#" onclick="changeChartType('revenue', 'area')">Area Chart</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="revenueChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Segmentation -->
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Customer Segmentation</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4">
                            <canvas id="segmentationChart" height="200"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php foreach($customer_segments as $segment): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo $segment['segment']; ?> Customers</span>
                                <span class="font-weight-bold"><?php echo formatNumber($segment['customer_count']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Performance -->
        <div class="row mb-4">
            <!-- Top Products -->
            <div class="col-xl-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Top Performing Products</h6>
                        <span class="badge bg-primary">Top 10</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Units Sold</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></span>
                                        </td>
                                        <td><?php echo formatNumber($product['units_sold']); ?></td>
                                        <td><?php echo formatNumber($product['order_count']); ?></td>
                                        <td class="font-weight-bold"><?php echo formatCurrency($product['revenue']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Category Performance -->
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Category Performance</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4">
                            <canvas id="categoryChart" height="200"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php 
                            $total_category_revenue = array_sum(array_column($category_performance, 'revenue'));
                            foreach($category_performance as $category): 
                                $percentage = $total_category_revenue > 0 ? ($category['revenue'] / $total_category_revenue) * 100 : 0;
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span><?php echo htmlspecialchars($category['category']); ?></span>
                                    <div class="progress mt-1" style="height: 3px; width: 100px;">
                                        <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                                <span class="font-weight-bold"><?php echo formatCurrency($category['revenue']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Analytics -->
        <div class="row mb-4">
            <!-- Daily Performance -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Daily Performance</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="dailyChart" height="200"></canvas>
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
                            <div class="col-6 mb-3">
                                <div class="card border-left-success shadow-sm py-2">
                                    <div class="card-body">
                                        <div class="text-success font-weight-bold mb-1">Cart Abandonment Rate</div>
                                        <div class="h4"><?php echo number_format($cart_abandonment_rate, 1); ?>%</div>
                                        <small class="text-muted">Lower is better</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="card border-left-info shadow-sm py-2">
                                    <div class="card-body">
                                        <div class="text-info font-weight-bold mb-1">Repeat Purchase Rate</div>
                                        <?php
                                        $repeat_customers = 0;
                                        $total_customers = 0;
                                        foreach($customer_segments as $segment) {
                                            if($segment['segment'] !== 'First-time') {
                                                $repeat_customers += $segment['customer_count'];
                                            }
                                            $total_customers += $segment['customer_count'];
                                        }
                                        $repeat_rate = $total_customers > 0 ? ($repeat_customers / $total_customers) * 100 : 0;
                                        ?>
                                        <div class="h4"><?php echo number_format($repeat_rate, 1); ?>%</div>
                                        <small class="text-muted">Higher is better</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card border-left-warning shadow-sm py-2">
                                    <div class="card-body">
                                        <div class="text-warning font-weight-bold mb-1">Avg. Customer Value</div>
                                        <?php
                                        $total_revenue = $revenue_data['current_revenue'] ?? 0;
                                        $total_customers = $customers_data['current_customers'] ?? 0;
                                        $avg_customer_value = $total_customers > 0 ? $total_revenue / $total_customers : 0;
                                        ?>
                                        <div class="h4"><?php echo formatCurrency($avg_customer_value); ?></div>
                                        <small class="text-muted">Lifetime value</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card border-left-primary shadow-sm py-2">
                                    <div class="card-body">
                                        <div class="text-primary font-weight-bold mb-1">Orders per Customer</div>
                                        <?php
                                        $total_orders = $orders_data['current_orders'] ?? 0;
                                        $orders_per_customer = $total_customers > 0 ? $total_orders / $total_customers : 0;
                                        ?>
                                        <div class="h4"><?php echo number_format($orders_per_customer, 1); ?></div>
                                        <small class="text-muted">Frequency</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Geographic & Acquisition Data -->
        <div class="row mb-4">
            <!-- Geographic Distribution -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Geographic Distribution</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($geographic_data)): ?>
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
                                    <?php foreach($geographic_data as $geo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($geo['country']); ?></td>
                                        <td><?php echo htmlspecialchars($geo['city']); ?></td>
                                        <td><?php echo formatNumber($geo['customer_count']); ?></td>
                                        <td><?php echo formatNumber($geo['order_count']); ?></td>
                                        <td class="font-weight-bold"><?php echo formatCurrency($geo['revenue']); ?></td>
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
            
            <!-- Acquisition Channels -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Customer Acquisition</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($acquisition_data)): ?>
                        <div class="chart-pie pt-4">
                            <canvas id="acquisitionChart" height="150"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php foreach($acquisition_data as $source): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo htmlspecialchars($source['source']); ?></span>
                                <span class="font-weight-bold"><?php echo formatNumber($source['customer_count']); ?> customers</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No acquisition data available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Insights & Recommendations -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h6 class="m-0 font-weight-bold text-primary">Insights & Recommendations</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-success h-100">
                            <div class="card-body">
                                <h6 class="card-title text-success">
                                    <i class="fas fa-lightbulb me-2"></i>Top Insight
                                </h6>
                                <p class="card-text small">
                                    <?php
                                    $top_category = $category_performance[0] ?? null;
                                    if ($top_category) {
                                        echo "Category <strong>" . htmlspecialchars($top_category['category']) . "</strong> is generating " . 
                                             formatCurrency($top_category['revenue']) . " revenue with " . 
                                             formatNumber($top_category['customers']) . " customers.";
                                    } else {
                                        echo "Analyze your top performing categories to focus marketing efforts.";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-warning h-100">
                            <div class="card-body">
                                <h6 class="card-title text-warning">
                                    <i class="fas fa-exclamation-circle me-2"></i>Area for Improvement
                                </h6>
                                <p class="card-text small">
                                    <?php
                                    if ($cart_abandonment_rate > 30) {
                                        echo "High cart abandonment rate (" . number_format($cart_abandonment_rate, 1) . "%). 
                                              Consider implementing abandoned cart recovery emails.";
                                    } else {
                                        echo "Monitor cart abandonment rate to improve conversion optimization.";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-info h-100">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-chart-line me-2"></i>Growth Opportunity
                                </h6>
                                <p class="card-text small">
                                    <?php
                                    $repeat_rate = ($repeat_customers / max($total_customers, 1)) * 100;
                                    if ($repeat_rate < 20) {
                                        echo "Low repeat purchase rate. Focus on customer retention strategies.";
                                    } else {
                                        echo "Good customer retention. Consider loyalty programs to increase repeat purchases.";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Charts
let revenueChart, segmentationChart, categoryChart, dailyChart, acquisitionChart;

// Revenue Trend Chart
function renderRevenueChart() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    if (revenueChart) {
        revenueChart.destroy();
    }
    
    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php 
                $labels = [];
                foreach($revenue_trend as $data) {
                    $labels[] = "'" . date('M Y', strtotime($data['month'] . '-01')) . "'";
                }
                echo implode(', ', $labels);
            ?>],
            datasets: [{
                label: 'Revenue',
                data: [<?php 
                    $revenue = [];
                    foreach($revenue_trend as $data) {
                        $revenue[] = $data['revenue'];
                    }
                    echo implode(', ', $revenue);
                ?>],
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 2,
                fill: true
            }, {
                label: 'Orders',
                data: [<?php 
                    $orders = [];
                    foreach($revenue_trend as $data) {
                        $orders[] = $data['orders'];
                    }
                    echo implode(', ', $orders);
                ?>],
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                borderColor: 'rgba(28, 200, 138, 1)',
                borderWidth: 2,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
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
                    intersect: false
                }
            }
        }
    });
}

// Customer Segmentation Chart
function renderSegmentationChart() {
    const ctx = document.getElementById('segmentationChart').getContext('2d');
    
    if (segmentationChart) {
        segmentationChart.destroy();
    }
    
    const segmentData = [<?php 
        $segments = [];
        foreach($customer_segments as $segment) {
            $segments[] = $segment['customer_count'];
        }
        echo implode(', ', $segments);
    ?>];
    
    const segmentLabels = [<?php 
        $labels = [];
        foreach($customer_segments as $segment) {
            $labels[] = "'" . $segment['segment'] . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    const segmentColors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'];
    
    segmentationChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: segmentLabels,
            datasets: [{
                data: segmentData,
                backgroundColor: segmentColors,
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Category Performance Chart
function renderCategoryChart() {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    
    if (categoryChart) {
        categoryChart.destroy();
    }
    
    const categoryData = [<?php 
        $categories = [];
        foreach($category_performance as $cat) {
            $categories[] = $cat['revenue'];
        }
        echo implode(', ', $categories);
    ?>];
    
    const categoryLabels = [<?php 
        $labels = [];
        foreach($category_performance as $cat) {
            $labels[] = "'" . htmlspecialchars($cat['category']) . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    categoryChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryData,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', 
                    '#e74a3b', '#858796', '#6f42c1', '#20c9a6'
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
}

// Daily Performance Chart
function renderDailyChart() {
    const ctx = document.getElementById('dailyChart').getContext('2d');
    
    if (dailyChart) {
        dailyChart.destroy();
    }
    
    dailyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php 
                $labels = [];
                foreach($daily_trend as $data) {
                    $labels[] = "'" . date('M d', strtotime($data['date'])) . "'";
                }
                echo implode(', ', $labels);
            ?>],
            datasets: [{
                label: 'Revenue',
                data: [<?php 
                    $revenue = [];
                    foreach($daily_trend as $data) {
                        $revenue[] = $data['revenue'];
                    }
                    echo implode(', ', $revenue);
                ?>],
                backgroundColor: 'rgba(78, 115, 223, 0.8)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
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

// Acquisition Chart
function renderAcquisitionChart() {
    const ctx = document.getElementById('acquisitionChart').getContext('2d');
    
    if (acquisitionChart) {
        acquisitionChart.destroy();
    }
    
    <?php if (!empty($acquisition_data)): ?>
    const acquisitionData = [<?php 
        $data = [];
        foreach($acquisition_data as $source) {
            $data[] = $source['customer_count'];
        }
        echo implode(', ', $data);
    ?>];
    
    const acquisitionLabels = [<?php 
        $labels = [];
        foreach($acquisition_data as $source) {
            $labels[] = "'" . htmlspecialchars($source['source']) . "'";
        }
        echo implode(', ', $labels);
    ?>];
    
    acquisitionChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: acquisitionLabels,
            datasets: [{
                data: acquisitionData,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc']
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    <?php endif; ?>
}

// Change chart type
function changeChartType(chartName, type) {
    // Implement chart type change logic here
    console.log('Changing ' + chartName + ' to ' + type);
}

// Refresh analytics
function refreshAnalytics() {
    location.reload();
}

// Export analytics
function exportAnalytics() {
    Swal.fire({
        title: 'Export Analytics',
        text: 'Choose export format',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Export as PDF',
        denyButtonText: 'Export as Excel',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const params = new URLSearchParams(window.location.search);
            params.append('format', 'pdf');
            window.open(`export-analytics.php?${params.toString()}`, '_blank');
        } else if (result.isDenied) {
            const params = new URLSearchParams(window.location.search);
            params.append('format', 'excel');
            window.open(`export-analytics.php?${params.toString()}`, '_blank');
        }
    });
}

// Initialize all charts
document.addEventListener('DOMContentLoaded', function() {
    renderRevenueChart();
    renderSegmentationChart();
    renderCategoryChart();
    renderDailyChart();
    renderAcquisitionChart();
});
</script>

<?php 
require_once '../includes/footer.php';
?>