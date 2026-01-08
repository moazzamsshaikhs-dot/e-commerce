<?php


require_once './includes/config.php';
require_once './includes/auth-check.php';


// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}

// Initialize variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'monthly';
$chart_type = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'line';

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

function getStatusColor($status, $hex = false) {
    $colors = [
        'pending' => ['warning', '#f6c23e'],
        'processing' => ['info', '#36b9cc'],
        'shipped' => ['primary', '#4e73df'],
        'delivered' => ['success', '#1cc88a'],
        'cancelled' => ['danger', '#e74a3b']
    ];
    
    $color = $colors[$status] ?? ['secondary', '#858796'];
    return $hex ? $color[1] : $color[0];
}

function getRefundStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'processing' => 'info',
        'completed' => 'success',
        'failed' => 'danger'
    ];
    
    return $colors[$status] ?? 'secondary';
}

try {
    $db = getDB();
    
    // Total Sales
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_sales,
            AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE status NOT IN ('cancelled', 'failed')
        AND order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $sales_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Sales by Status
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as order_count,
            SUM(total_amount) as total_amount
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        GROUP BY status
        ORDER BY order_count DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $sales_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Products (Corrected query - removed sku and category_id)
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.category,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.unit_price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.id, p.name, p.category
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Customers
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_spent
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY u.id, u.full_name, u.email
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly Sales Data for Chart
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m') as month,
            COUNT(*) as order_count,
            SUM(total_amount) as total_sales
        FROM orders 
        WHERE status NOT IN ('cancelled', 'failed')
        AND order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily Sales Data for Selected Period
    $stmt = $db->prepare("
        SELECT 
            DATE(order_date) as date,
            COUNT(*) as order_count,
            SUM(total_amount) as total_sales
        FROM orders 
        WHERE status NOT IN ('cancelled', 'failed')
        AND order_date BETWEEN ? AND ?
        GROUP BY DATE(order_date)
        ORDER BY date
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sales by Category (Corrected query)
    $stmt = $db->prepare("
        SELECT 
            p.category,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_sales,
            COUNT(DISTINCT o.user_id) as unique_customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.category
        ORDER BY total_sales DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Payment Method Analysis
    $stmt = $db->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            SUM(total_amount) as total_amount,
            AVG(total_amount) as avg_amount
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Refund Analysis
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as refund_count,
            SUM(refund_amount) as total_refunded,
            AVG(refund_amount) as avg_refund
        FROM refunds 
        WHERE created_at BETWEEN ? AND ?
        AND status = 'completed'
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $refund_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading sales data: ' . $e->getMessage();
    $sales_summary = [];
    $sales_by_status = [];
    $top_products = [];
    $top_customers = [];
    $monthly_data = [];
    $daily_data = [];
    $category_sales = [];
    $payment_methods = [];
    $refund_summary = [];
}

$page_title = 'Sales Report';
require_once './includes/header.php';
?>

<div class="dashboard-container">
    <?php include './includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Sales Report</h1>
                <p class="text-muted mb-0">Detailed sales analytics and insights</p>
            </div>
            <div>
                <button class="btn btn-outline-primary me-2" onclick="printReport()">
                    <i class="fas fa-print me-2"></i> Print
                </button>
                <button class="btn btn-primary" onclick="exportReport()">
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
                    
                    <div class="col-md-2">
                        <label class="form-label">Filter Type</label>
                        <select class="form-select" name="filter_type">
                            <option value="daily" <?php echo $filter_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $filter_type == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="yearly" <?php echo $filter_type == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Chart Type</label>
                        <select class="form-select" name="chart_type">
                            <option value="line" <?php echo $chart_type == 'line' ? 'selected' : ''; ?>>Line Chart</option>
                            <option value="bar" <?php echo $chart_type == 'bar' ? 'selected' : ''; ?>>Bar Chart</option>
                            <option value="pie" <?php echo $chart_type == 'pie' ? 'selected' : ''; ?>>Pie Chart</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($sales_summary['total_sales'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo $sales_summary['total_orders'] ?? 0; ?> orders</span>
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
                                    Average Order Value
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($sales_summary['avg_order_value'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?></span>
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
                                    Refunds
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($refund_summary['total_refunded'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo $refund_summary['refund_count'] ?? 0; ?> refunds</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-undo fa-2x text-gray-300"></i>
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
                                    Active Customers
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    try {
                                        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM orders WHERE order_date BETWEEN ? AND ?");
                                        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                                        echo $stmt->fetchColumn();
                                    } catch(Exception $e) {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span>Unique buyers</span>
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
            <!-- Monthly Sales Chart -->
            <div class="col-xl-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Sales Trend (12 Months)</h6>
                        <div class="dropdown no-arrow">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="changeChartType('line')">Line Chart</a></li>
                                <li><a class="dropdown-item" href="#" onclick="changeChartType('bar')">Bar Chart</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="salesChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sales by Status -->
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Sales by Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4">
                            <canvas id="statusPieChart" height="200"></canvas>
                        </div>
                        <div class="mt-4 text-center small">
                            <?php foreach($sales_by_status as $status): ?>
                            <span class="mr-2">
                                <i class="fas fa-circle text-<?php echo getStatusColor($status['status']); ?>"></i>
                                <?php echo ucfirst($status['status']); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tables Row -->
        <div class="row mb-4">
            <!-- Top Products -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Top Products</h6>
                        <span class="badge bg-primary">Top 10</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
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
                                        <td><?php echo $product['total_quantity']; ?></td>
                                        <td class="font-weight-bold"><?php echo formatCurrency($product['total_revenue']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Top Customers</h6>
                        <span class="badge bg-primary">Top 10</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Email</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_customers as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo $customer['order_count']; ?></td>
                                        <td class="font-weight-bold"><?php echo formatCurrency($customer['total_spent']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Category Sales & Payment Methods -->
        <div class="row mb-4">
            <!-- Sales by Category -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Sales by Category</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Orders</th>
                                        <th>Customers</th>
                                        <th>Sales</th>
                                        <th>% of Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_category_sales = array_sum(array_column($category_sales, 'total_sales'));
                                    foreach($category_sales as $category):
                                        $percentage = $total_category_sales > 0 ? ($category['total_sales'] / $total_category_sales) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category'] ?? 'Uncategorized'); ?></td>
                                        <td><?php echo $category['order_count']; ?></td>
                                        <td><?php echo $category['unique_customers']; ?></td>
                                        <td><?php echo formatCurrency($category['total_sales']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 5px;">
                                                    <div class="progress-bar bg-success" 
                                                         style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <span><?php echo number_format($percentage, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Methods -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="m-0 font-weight-bold text-primary">Payment Methods Analysis</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Method</th>
                                        <th>Orders</th>
                                        <th>Total Amount</th>
                                        <th>Avg. Order</th>
                                        <th>Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payment_methods as $method): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo strtoupper($method['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $method['count']; ?></td>
                                        <td><?php echo formatCurrency($method['total_amount']); ?></td>
                                        <td><?php echo formatCurrency($method['avg_amount']); ?></td>
                                        <td>
                                            <?php
                                            try {
                                                $stmt = $db->prepare("
                                                    SELECT 
                                                        COUNT(*) as total,
                                                        SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed
                                                    FROM orders 
                                                    WHERE payment_method = ?
                                                    AND order_date BETWEEN ? AND ?
                                                ");
                                                $stmt->execute([$method['payment_method'], $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                                                $rate = $stmt->fetch();
                                                $success_rate = $rate['total'] > 0 ? ($rate['completed'] / $rate['total']) * 100 : 0;
                                                echo number_format($success_rate, 1) . '%';
                                            } catch(Exception $e) {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Refund Details -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h6 class="m-0 font-weight-bold text-primary">Refund Details</h6>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stmt = $db->prepare("
                        SELECT 
                            r.*,
                            u.full_name as customer_name,
                            p.transaction_id,
                            o.order_number
                        FROM refunds r
                        JOIN users u ON r.user_id = u.id
                        JOIN payments p ON r.payment_id = p.id
                        LEFT JOIN orders o ON r.order_id = o.id
                        WHERE r.created_at BETWEEN ? AND ?
                        ORDER BY r.created_at DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                    $recent_refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($recent_refunds)):
                ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Refund ID</th>
                                <th>Customer</th>
                                <th>Order</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_refunds as $refund): ?>
                            <tr>
                                <td>#<?php echo $refund['id']; ?></td>
                                <td><?php echo htmlspecialchars($refund['customer_name']); ?></td>
                                <td><?php echo $refund['order_number']; ?></td>
                                <td class="text-danger">-<?php echo formatCurrency($refund['refund_amount']); ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars($refund['reason']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getRefundStatusColor($refund['status']); ?>">
                                        <?php echo ucfirst($refund['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($refund['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No refunds in selected period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Sales Chart
let salesChart;
function renderSalesChart() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    if (salesChart) {
        salesChart.destroy();
    }
    
    const chartType = '<?php echo $chart_type; ?>';
    
    salesChart = new Chart(ctx, {
        type: chartType,
        data: {
            labels: [<?php 
                $labels = [];
                foreach($monthly_data as $data) {
                    $labels[] = "'" . date('M Y', strtotime($data['month'] . '-01')) . "'";
                }
                echo implode(', ', $labels);
            ?>],
            datasets: [{
                label: 'Sales',
                data: [<?php 
                    $sales = [];
                    foreach($monthly_data as $data) {
                        $sales[] = $data['total_sales'];
                    }
                    echo implode(', ', $sales);
                ?>],
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                fill: true
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
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Sales: $' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Status Pie Chart
let statusPieChart;
function renderStatusChart() {
    const ctx = document.getElementById('statusPieChart').getContext('2d');
    
    if (statusPieChart) {
        statusPieChart.destroy();
    }
    
    const statusData = [<?php 
        $status_counts = [];
        foreach($sales_by_status as $status) {
            $status_counts[] = $status['order_count'];
        }
        echo implode(', ', $status_counts);
    ?>];
    
    const statusLabels = [<?php 
        $status_labels = [];
        foreach($sales_by_status as $status) {
            $status_labels[] = "'" . ucfirst($status['status']) . "'";
        }
        echo implode(', ', $status_labels);
    ?>];
    
    // Prepare colors for JavaScript
    const statusColors = [<?php 
        $status_colors = [];
        foreach($sales_by_status as $status) {
            $status_colors[] = "'" . getStatusColor($status['status'], true) . "'";
        }
        echo implode(', ', $status_colors);
    ?>];
    
    statusPieChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusColors,
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label;
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Change chart type
function changeChartType(type) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('chart_type', type);
    window.location.href = '?' + urlParams.toString();
}

// Print report
function printReport() {
    const printContent = document.querySelector('.main-content').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Sales Report - <?php echo date('Y-m-d'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; }
                .table { width: 100%; border-collapse: collapse; }
                .table th, .table td { border: 1px solid #ddd; padding: 8px; }
                .table th { background-color: #f8f9fa; }
                .text-center { text-align: center; }
                .mb-4 { margin-bottom: 1.5rem; }
                .card { border: 1px solid #dee2e6; border-radius: 0.25rem; }
                .card-header { background-color: #f8f9fa; padding: 0.75rem 1.25rem; }
                .card-body { padding: 1.25rem; }
            </style>
        </head>
        <body>
            <div style="text-align: center; margin-bottom: 20px;">
                <h2>Sales Report</h2>
                <p>Period: <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></p>
                <p>Generated: <?php echo date('M j, Y H:i:s'); ?></p>
            </div>
            ${printContent}
        </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Export report
function exportReport() {
    Swal.fire({
        title: 'Export Report',
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
            window.open(`export-sales-report.php?${params.toString()}`, '_blank');
        } else if (result.isDenied) {
            const params = new URLSearchParams(window.location.search);
            params.append('format', 'excel');
            window.open(`export-sales-report.php?${params.toString()}`, '_blank');
        }
    });
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    renderSalesChart();
    renderStatusChart();
});
</script>

<?php 
require_once '../includes/footer.php';
// No closing
 }catch(Exception $e) {
                    $recent_refunds = [];
                    } ?> 