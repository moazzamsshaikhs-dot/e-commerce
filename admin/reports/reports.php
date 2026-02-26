<?php
// admin/reports/reports.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Reports & Analytics';
require_once '../includes/header.php';

$db = getDB();

// Get date range from request
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['type'] ?? 'sales';

// Fetch report data based on type
$data = [];
$summary = [];

try {
    if ($report_type === 'sales') {
        // Sales Report
        $stmt = $db->prepare("
            SELECT 
                DATE(order_date) as date,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_order_value,
                COUNT(DISTINCT user_id) as unique_customers
            FROM orders 
            WHERE payment_status = 'completed'
            AND DATE(order_date) BETWEEN ? AND ?
            GROUP BY DATE(order_date)
            ORDER BY date DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Summary stats
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_order,
                COUNT(DISTINCT user_id) as total_customers
            FROM orders 
            WHERE payment_status = 'completed'
            AND DATE(order_date) BETWEEN ? AND ?
        ");
        $stmt->execute([$date_from, $date_to]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } elseif ($report_type === 'products') {
        // Products Report
        $stmt = $db->prepare("
            SELECT 
                p.id,
                p.name,
                p.price,
                p.stock,
                p.sales_count,
                COUNT(oi.id) as order_count,
                COALESCE(SUM(oi.quantity), 0) as quantity_sold,
                COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue,
                v.full_name as vendor_name
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed' AND DATE(o.order_date) BETWEEN ? AND ?
            LEFT JOIN users v ON p.vendor_id = v.id
            GROUP BY p.id
            ORDER BY revenue DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Summary stats
        $total_revenue = array_sum(array_column($data, 'revenue'));
        $total_sold = array_sum(array_column($data, 'quantity_sold'));
        $summary = [
            'total_products' => count($data),
            'total_sold' => $total_sold,
            'total_revenue' => $total_revenue,
            'avg_price' => $total_sold > 0 ? $total_revenue / $total_sold : 0
        ];
        
    } elseif ($report_type === 'customers') {
        // Customers Report
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.created_at as registration_date,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(o.total_amount), 0) as total_spent,
                MAX(o.order_date) as last_order
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id AND o.payment_status = 'completed' AND DATE(o.order_date) BETWEEN ? AND ?
            WHERE u.user_type IN ('user', 'vendor')
            GROUP BY u.id
            HAVING order_count > 0 OR registration_date BETWEEN ? AND ?
            ORDER BY total_spent DESC
        ");
        $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Summary stats
        $summary = [
            'total_customers' => count($data),
            'total_revenue' => array_sum(array_column($data, 'total_spent')),
            'avg_spent' => count($data) > 0 ? array_sum(array_column($data, 'total_spent')) / count($data) : 0,
            'active_customers' => count(array_filter($data, fn($c) => $c['order_count'] > 0))
        ];
        
    } elseif ($report_type === 'vendors') {
        // Vendors Report
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.vendor_status,
                u.vendor_verified,
                u.vendor_since,
                COUNT(DISTINCT p.id) as total_products,
                COUNT(DISTINCT oi.id) as total_sales,
                COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue,
                COALESCE(SUM(w.withdrawal_amount), 0) as total_withdrawn
            FROM users u
            LEFT JOIN products p ON u.id = p.vendor_id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed' AND DATE(o.order_date) BETWEEN ? AND ?
            LEFT JOIN vendor_withdrawals w ON u.id = w.vendor_id AND w.status = 'completed'
            WHERE u.user_type = 'vendor'
            GROUP BY u.id
            ORDER BY revenue DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Summary stats
        $summary = [
            'total_vendors' => count($data),
            'active_vendors' => count(array_filter($data, fn($v) => $v['total_sales'] > 0)),
            'total_revenue' => array_sum(array_column($data, 'revenue')),
            'total_withdrawn' => array_sum(array_column($data, 'total_withdrawn')),
            'pending_balance' => array_sum(array_column($data, 'revenue')) - array_sum(array_column($data, 'total_withdrawn'))
        ];
    }
    
} catch(PDOException $e) {
    $error = "Error loading report: " . $e->getMessage();
}

// Get available months for quick filters
$months = [];
try {
    $stmt = $db->query("
        SELECT DISTINCT DATE_FORMAT(order_date, '%Y-%m') as month 
        FROM orders 
        WHERE order_date IS NOT NULL 
        ORDER BY month DESC 
        LIMIT 6
    ");
    $months = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {}
?>

<style>
:root {
    --primary: #4361ee;
    --success: #06d6a0;
    --warning: #ffb703;
    --danger: #ef476f;
    --info: #4cc9f0;
    --dark: #2b2d42;
    --light: #f8f9fa;
}

.reports-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

/* Header */
.page-header {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success), var(--warning), var(--danger));
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.filter-card .form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

.filter-card .form-control,
.filter-card .form-select {
    border-radius: 12px;
    border: 2px solid #edf2f9;
    padding: 10px 15px;
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 14px;
}

.stat-change {
    font-size: 13px;
    margin-top: 10px;
    padding: 5px 10px;
    border-radius: 20px;
    display: inline-block;
}

/* Report Table */
.report-table {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.table-header {
    padding: 20px 25px;
    border-bottom: 1px solid #edf2f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h5 {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.table-responsive {
    padding: 0 25px 25px 25px;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px 10px;
    border-bottom: 2px solid #edf2f9;
}

.table td {
    padding: 15px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #edf2f9;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Report Tabs */
.report-tabs {
    background: white;
    border-radius: 50px;
    padding: 5px;
    display: inline-flex;
    margin-bottom: 25px;
}

.report-tab {
    padding: 10px 25px;
    border-radius: 50px;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    background: transparent;
    text-decoration: none;
}

.report-tab:hover {
    color: var(--primary);
}

.report-tab.active {
    background: var(--primary);
    color: white;
}

/* Export Buttons */
.export-btns {
    display: flex;
    gap: 10px;
}

.btn-export {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-excel {
    background: #28a745;
    color: white;
}

.btn-pdf {
    background: #dc3545;
    color: white;
}

.btn-csv {
    background: #17a2b8;
    color: white;
}

.btn-print {
    background: #6c757d;
    color: white;
}

.btn-export:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
}

/* Quick Filters */
.quick-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.quick-filter {
    padding: 8px 16px;
    border-radius: 30px;
    background: white;
    border: 1px solid #edf2f9;
    color: #6c757d;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.quick-filter:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Chart Container */
.chart-container {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    height: 400px;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-in {
    animation: slideIn 0.5s ease forwards;
}

/* Responsive */
@media (max-width: 768px) {
    .reports-container {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .report-tabs {
        width: 100%;
        justify-content: center;
    }
    
    .table-responsive {
        padding: 0 15px 15px 15px;
    }
}
</style>

<div class="reports-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1>
                    <i class="fas fa-chart-bar me-2 text-primary"></i>
                    Reports & Analytics
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?>
                </p>
            </div>
            <div class="export-btns">
                <button class="btn-export btn-excel" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="btn-export btn-pdf" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button class="btn-export btn-csv" onclick="exportReport('csv')">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button class="btn-export btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Report Type Tabs -->
    <div class="report-tabs">
        <a href="?type=sales&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="report-tab <?php echo $report_type === 'sales' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line me-2"></i> Sales Report
        </a>
        <a href="?type=products&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="report-tab <?php echo $report_type === 'products' ? 'active' : ''; ?>">
            <i class="fas fa-box me-2"></i> Products Report
        </a>
        <a href="?type=customers&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="report-tab <?php echo $report_type === 'customers' ? 'active' : ''; ?>">
            <i class="fas fa-users me-2"></i> Customers Report
        </a>
        <a href="?type=vendors&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="report-tab <?php echo $report_type === 'vendors' ? 'active' : ''; ?>">
            <i class="fas fa-store me-2"></i> Vendors Report
        </a>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
            
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Apply Filter
                </button>
            </div>
            
            <div class="col-md-4">
                <div class="quick-filters">
                    <a href="?type=<?php echo $report_type; ?>&date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" 
                       class="quick-filter">This Month</a>
                    <a href="?type=<?php echo $report_type; ?>&date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>" 
                       class="quick-filter">Last 30 Days</a>
                    <a href="?type=<?php echo $report_type; ?>&date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" 
                       class="quick-filter">This Year</a>
                    <?php foreach($months as $month): ?>
                        <a href="?type=<?php echo $report_type; ?>&date_from=<?php echo $month; ?>-01&date_to=<?php echo date('Y-m-t', strtotime($month . '-01')); ?>" 
                           class="quick-filter"><?php echo date('M Y', strtotime($month . '-01')); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <?php if (!empty($summary)): ?>
        <div class="stats-grid">
            <?php if ($report_type === 'sales'): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-shopping-cart text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_orders']); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                        <i class="fas fa-dollar-sign text-success"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                        <i class="fas fa-chart-line text-warning"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['avg_order'], 2); ?></div>
                    <div class="stat-label">Average Order Value</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                        <i class="fas fa-users text-info"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_customers']); ?></div>
                    <div class="stat-label">Unique Customers</div>
                </div>
                
            <?php elseif ($report_type === 'products'): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-box text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_products']); ?></div>
                    <div class="stat-label">Products Sold</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                        <i class="fas fa-shopping-cart text-success"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_sold']); ?></div>
                    <div class="stat-label">Units Sold</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                        <i class="fas fa-dollar-sign text-warning"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                        <i class="fas fa-chart-line text-info"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['avg_price'], 2); ?></div>
                    <div class="stat-label">Average Price</div>
                </div>
                
            <?php elseif ($report_type === 'customers'): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-users text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_customers']); ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                        <i class="fas fa-shopping-cart text-success"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['active_customers']); ?></div>
                    <div class="stat-label">Active Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                        <i class="fas fa-dollar-sign text-warning"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                        <i class="fas fa-chart-line text-info"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['avg_spent'], 2); ?></div>
                    <div class="stat-label">Average Spent</div>
                </div>
                
            <?php elseif ($report_type === 'vendors'): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-store text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_vendors']); ?></div>
                    <div class="stat-label">Total Vendors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                        <i class="fas fa-check-circle text-success"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['active_vendors']); ?></div>
                    <div class="stat-label">Active Vendors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                        <i class="fas fa-dollar-sign text-warning"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                        <i class="fas fa-money-bill-wave text-info"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['pending_balance'], 2); ?></div>
                    <div class="stat-label">Pending Balance</div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Report Table -->
    <div class="report-table">
        <div class="table-header">
            <h5>
                <?php 
                if ($report_type === 'sales') echo 'Sales Details';
                elseif ($report_type === 'products') echo 'Product Performance';
                elseif ($report_type === 'customers') echo 'Customer Analysis';
                elseif ($report_type === 'vendors') echo 'Vendor Performance';
                ?>
            </h5>
            <span class="text-muted">Showing <?php echo count($data); ?> records</span>
        </div>
        
        <div class="table-responsive">
            <?php if (empty($data)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No data available</h5>
                    <p class="text-muted">Try adjusting your date range or filters</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <?php if ($report_type === 'sales'): ?>
                                <th>Date</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                                <th>Avg Order Value</th>
                                <th>Unique Customers</th>
                            <?php elseif ($report_type === 'products'): ?>
                                <th>Product</th>
                                <th>Vendor</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Units Sold</th>
                                <th>Revenue</th>
                                <th>Orders</th>
                            <?php elseif ($report_type === 'customers'): ?>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Last Order</th>
                            <?php elseif ($report_type === 'vendors'): ?>
                                <th>Vendor</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Products</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                                <th>Withdrawn</th>
                                <th>Balance</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <?php if ($report_type === 'sales'): ?>
                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                    <td><?php echo number_format($row['order_count']); ?></td>
                                    <td><strong>$<?php echo number_format($row['revenue'], 2); ?></strong></td>
                                    <td>$<?php echo number_format($row['avg_order_value'], 2); ?></td>
                                    <td><?php echo number_format($row['unique_customers']); ?></td>
                                    
                                <?php elseif ($report_type === 'products'): ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($row['name'], 0, 30)) . (strlen($row['name']) > 30 ? '...' : ''); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['vendor_name'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo number_format($row['price'], 2); ?></td>
                                    <td>
                                        <?php if ($row['stock'] <= 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php elseif ($row['stock'] < 10): ?>
                                            <span class="badge bg-warning"><?php echo $row['stock']; ?> left</span>
                                        <?php else: ?>
                                            <?php echo $row['stock']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($row['quantity_sold']); ?></td>
                                    <td><strong>$<?php echo number_format($row['revenue'], 2); ?></strong></td>
                                    <td><?php echo number_format($row['order_count']); ?></td>
                                    
                                <?php elseif ($report_type === 'customers'): ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></strong>
                                        <br><small class="text-muted">@<?php echo $row['username']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['registration_date'])); ?></td>
                                    <td><?php echo number_format($row['order_count']); ?></td>
                                    <td><strong>$<?php echo number_format($row['total_spent'], 2); ?></strong></td>
                                    <td><?php echo $row['last_order'] ? date('M d, Y', strtotime($row['last_order'])) : 'Never'; ?></td>
                                    
                                <?php elseif ($report_type === 'vendors'): ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></strong>
                                        <br><small class="text-muted">@<?php echo $row['username']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td>
                                        <?php if ($row['vendor_status'] == 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif ($row['vendor_status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($row['vendor_status'] == 'suspended'): ?>
                                            <span class="badge bg-danger">Suspended</span>
                                        <?php endif; ?>
                                        <?php if ($row['vendor_verified']): ?>
                                            <span class="badge bg-info">Verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($row['total_products']); ?></td>
                                    <td><?php echo number_format($row['total_sales']); ?></td>
                                    <td><strong>$<?php echo number_format($row['revenue'], 2); ?></strong></td>
                                    <td>$<?php echo number_format($row['total_withdrawn'], 2); ?></td>
                                    <td>
                                        <strong class="<?php echo ($row['revenue'] - $row['total_withdrawn']) > 0 ? 'text-success' : 'text-muted'; ?>">
                                            $<?php echo number_format($row['revenue'] - $row['total_withdrawn'], 2); ?>
                                        </strong>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function exportReport(format) {
    const url = new URL(window.location.href);
    url.searchParams.set('export', format);
    window.location.href = url.toString();
}

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>