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

// Export handling
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $export_data = [];
    
    try {
        if ($report_type === 'sales') {
            $stmt = $db->prepare("
                SELECT DATE(order_date) as date, COUNT(*) as orders, 
                       SUM(total_amount) as revenue, AVG(total_amount) as avg_order,
                       COUNT(DISTINCT user_id) as customers
                FROM orders WHERE payment_status = 'completed' 
                AND DATE(order_date) BETWEEN ? AND ?
                GROUP BY DATE(order_date) ORDER BY date DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $export_data = $stmt->fetchAll();
            $headers = ['Date', 'Orders', 'Revenue', 'Avg Order', 'Customers'];
        } elseif ($report_type === 'products') {
            $stmt = $db->prepare("
                SELECT p.name, p.price, p.stock, COALESCE(SUM(oi.quantity), 0) as sold,
                       COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue,
                       v.full_name as vendor
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed' 
                    AND DATE(o.order_date) BETWEEN ? AND ?
                LEFT JOIN users v ON p.vendor_id = v.id
                GROUP BY p.id ORDER BY revenue DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $export_data = $stmt->fetchAll();
            $headers = ['Product', 'Price', 'Stock', 'Units Sold', 'Revenue', 'Vendor'];
        } elseif ($report_type === 'customers') {
            $stmt = $db->prepare("
                SELECT u.full_name, u.email, u.created_at as registered,
                       COUNT(DISTINCT o.id) as orders,
                       COALESCE(SUM(o.total_amount), 0) as spent,
                       MAX(o.order_date) as last_order
                FROM users u
                LEFT JOIN orders o ON u.id = o.user_id AND o.payment_status = 'completed' 
                    AND DATE(o.order_date) BETWEEN ? AND ?
                WHERE u.user_type IN ('user', 'vendor')
                GROUP BY u.id ORDER BY spent DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $export_data = $stmt->fetchAll();
            $headers = ['Customer', 'Email', 'Registered', 'Orders', 'Total Spent', 'Last Order'];
        } elseif ($report_type === 'vendors') {
            $stmt = $db->prepare("
                SELECT u.full_name, u.email, u.vendor_status,
                       COUNT(DISTINCT p.id) as products,
                       COUNT(DISTINCT oi.id) as sales,
                       COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue,
                       COALESCE(SUM(w.withdrawal_amount), 0) as withdrawn
                FROM users u
                LEFT JOIN products p ON u.id = p.vendor_id
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed' 
                    AND DATE(o.order_date) BETWEEN ? AND ?
                LEFT JOIN vendor_withdrawals w ON u.id = w.vendor_id AND w.status = 'completed'
                WHERE u.user_type = 'vendor'
                GROUP BY u.id ORDER BY revenue DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $export_data = $stmt->fetchAll();
            $headers = ['Vendor', 'Email', 'Status', 'Products', 'Sales', 'Revenue', 'Withdrawn', 'Balance'];
        }
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($output, $headers);
            foreach ($export_data as $row) {
                fputcsv($output, array_values($row));
            }
            fclose($output);
            exit;
        } elseif ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.xls"');
            echo '<table border="1">';
            echo '<tr><th>' . implode('</th><th>', $headers) . '</th></tr>';
            foreach ($export_data as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . htmlspecialchars($cell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
            exit;
        }
    } catch(Exception $e) {
        // Handle export error silently
    }
}

// Fetch report data based on type
$data = [];
$summary = [];

try {
    if ($report_type === 'sales') {
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
        
        $chart_labels = [];
        $chart_revenue = [];
        $chart_orders = [];
        foreach(array_reverse($data) as $row) {
            $chart_labels[] = date('M d', strtotime($row['date']));
            $chart_revenue[] = $row['revenue'];
            $chart_orders[] = $row['order_count'];
        }
        
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
        
        $summary = [
            'total_products' => count($data),
            'total_sold' => array_sum(array_column($data, 'quantity_sold')),
            'total_revenue' => array_sum(array_column($data, 'revenue')),
            'avg_price' => count($data) > 0 ? array_sum(array_column($data, 'price')) / count($data) : 0
        ];
        
    } elseif ($report_type === 'customers') {
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
        
        $summary = [
            'total_customers' => count($data),
            'total_revenue' => array_sum(array_column($data, 'total_spent')),
            'avg_spent' => count($data) > 0 ? array_sum(array_column($data, 'total_spent')) / count($data) : 0,
            'active_customers' => count(array_filter($data, fn($c) => $c['order_count'] > 0))
        ];
        
    } elseif ($report_type === 'vendors') {
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
        
        $summary = [
            'total_vendors' => count($data),
            'active_vendors' => count(array_filter($data, fn($v) => $v['total_sales'] > 0)),
            'total_revenue' => array_sum(array_column($data, 'revenue')),
            'total_withdrawn' => array_sum(array_column($data, 'total_withdrawn')),
            'pending_balance' => array_sum(array_column($data, 'revenue')) - array_sum(array_column($data, 'total_withdrawn'))
        ];
    }
    
    // Get available months for quick filters
    $months = [];
    $stmt = $db->query("
        SELECT DISTINCT DATE_FORMAT(order_date, '%Y-%m') as month 
        FROM orders 
        WHERE order_date IS NOT NULL 
        ORDER BY month DESC 
        LIMIT 6
    ");
    $months = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $error = "Error loading report: " . $e->getMessage();
}
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.stat-card.primary { border-left-color: var(--primary); }
.stat-card.success { border-left-color: var(--success); }
.stat-card.warning { border-left-color: var(--warning); }
.stat-card.danger { border-left-color: var(--danger); }
.stat-card.info { border-left-color: var(--info); }

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
    font-size: 24px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 13px;
}

.stat-sub {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.filter-card .form-control,
.filter-card .form-select {
    border-radius: 12px;
    border: 2px solid #edf2f9;
    padding: 10px 15px;
    transition: all 0.3s ease;
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.filter-card .btn-filter {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
}

.filter-card .btn-filter:hover {
    background: #3651c4;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.filter-card .btn-reset {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
}

.filter-card .btn-reset:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* Quick Filters */
.quick-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 5px;
}

.quick-filter {
    padding: 6px 12px;
    border-radius: 20px;
    background: #f8f9fa;
    border: 1px solid #edf2f9;
    color: #6c757d;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.quick-filter:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
}

/* Report Tabs */
.report-tabs {
    background: white;
    border-radius: 50px;
    padding: 5px;
    display: inline-flex;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    border: 1px solid #edf2f9;
}

.report-tab {
    padding: 10px 28px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #6c757d;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    background: transparent;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.report-tab:hover {
    color: var(--primary);
    background: rgba(67, 97, 238, 0.05);
}

.report-tab.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

/* Chart Container */
.chart-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    border: 1px solid #edf2f9;
}

.chart-container h5 {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-container h5 i {
    color: var(--primary);
}

/* Report Table */
.report-table {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    border: 1px solid #edf2f9;
}

.table-header {
    padding: 20px 25px;
    border-bottom: 1px solid #edf2f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    background: white;
}

.table-header h5 {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header h5 i {
    color: var(--primary);
}

.record-count {
    background: #f8f9fa;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    color: #6c757d;
}

.export-btns {
    display: flex;
    gap: 8px;
}

.btn-export {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-excel {
    background: #28a745;
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
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
    padding: 15px 12px;
    border-bottom: 2px solid #edf2f9;
}

.table td {
    padding: 15px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #edf2f9;
    font-size: 14px;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Status Badges */
.badge-status {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-approved {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.badge-pending {
    background: rgba(255, 183, 3, 0.15);
    color: var(--warning);
    border: 1px solid rgba(255, 183, 3, 0.3);
}

.badge-suspended {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.badge-verified {
    background: rgba(76, 201, 240, 0.15);
    color: var(--info);
    border: 1px solid rgba(76, 201, 240, 0.3);
}

.badge-low-stock {
    background: rgba(255, 183, 3, 0.15);
    color: var(--warning);
    border: 1px solid rgba(255, 183, 3, 0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 60px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h5 {
    color: var(--dark);
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 20px;
}

/* Error Alert */
.error-alert {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    border-left: 4px solid var(--danger);
    display: flex;
    align-items: center;
    gap: 15px;
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

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }

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
        flex-wrap: wrap;
        border-radius: 15px;
    }
    
    .report-tab {
        flex: 1;
        text-align: center;
        justify-content: center;
        padding: 8px 16px;
    }
    
    .table-header {
        flex-direction: column;
        text-align: center;
    }
    
    .export-btns {
        justify-content: center;
    }
    
    .quick-filters {
        justify-content: center;
    }
    
    .table-responsive {
        padding: 0 15px 15px 15px;
    }
    
    .table th,
    .table td {
        padding: 10px 8px;
        font-size: 12px;
    }
}
</style>

<div class="reports-container">
    <!-- Page Header -->
    <div class="page-header animate-slide-in">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h2 fw-bold mb-1">
                    <i class="fas fa-chart-line me-2 text-primary"></i>
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
    <div class="report-tabs animate-slide-in delay-1">
        <a href="?type=sales&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="report-tab <?php echo $report_type === 'sales' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Sales Report
        </a>
        <a href="?type=products&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="report-tab <?php echo $report_type === 'products' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> Products Report
        </a>
        <a href="?type=customers&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="report-tab <?php echo $report_type === 'customers' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Customers Report
        </a>
        <a href="?type=vendors&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="report-tab <?php echo $report_type === 'vendors' ? 'active' : ''; ?>">
            <i class="fas fa-store"></i> Vendors Report
        </a>
    </div>

    <!-- Filter Card -->
    <div class="filter-card animate-slide-in delay-2">
        <form method="GET" class="row g-3">
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
            
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0">
                        <i class="fas fa-calendar-alt text-muted"></i>
                    </span>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0">
                        <i class="fas fa-calendar-check text-muted"></i>
                    </span>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn-filter">
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
        <div class="error-alert animate-slide-in delay-2">
            <i class="fas fa-exclamation-circle fa-2x"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <?php if (!empty($summary)): ?>
        <div class="stats-grid animate-slide-in delay-2">
            <?php if ($report_type === 'sales'): ?>
                <div class="stat-card primary">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-shopping-cart text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_orders']); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                        <i class="fas fa-dollar-sign text-success"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                        <i class="fas fa-chart-line text-warning"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['avg_order'], 2); ?></div>
                    <div class="stat-label">Avg Order Value</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                        <i class="fas fa-users text-info"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_customers']); ?></div>
                    <div class="stat-label">Unique Customers</div>
                </div>
                
            <?php elseif ($report_type === 'products'): ?>
                <div class="stat-card primary">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-box text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_products']); ?></div>
                    <div class="stat-label">Products Sold</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                        <i class="fas fa-shopping-cart text-success"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_sold']); ?></div>
                    <div class="stat-label">Units Sold</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                        <i class="fas fa-dollar-sign text-warning"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                        <i class="fas fa-chart-line text-info"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['avg_price'], 2); ?></div>
                    <div class="stat-label">Average Price</div>
                </div>
                
            <?php elseif ($report_type === 'customers'): ?>
                <div class="stat-card primary">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-users text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_customers']); ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                        <i class="fas fa-shopping-cart text-success"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['active_customers']); ?></div>
                    <div class="stat-label">Active Customers</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                        <i class="fas fa-dollar-sign text-warning"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                        <i class="fas fa-chart-line text-info"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['avg_spent'], 2); ?></div>
                    <div class="stat-label">Average Spent</div>
                </div>
                
            <?php elseif ($report_type === 'vendors'): ?>
                <div class="stat-card primary">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-store text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['total_vendors']); ?></div>
                    <div class="stat-label">Total Vendors</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                        <i class="fas fa-check-circle text-success"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary['active_vendors']); ?></div>
                    <div class="stat-label">Active Vendors</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                        <i class="fas fa-dollar-sign text-warning"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                        <i class="fas fa-money-bill-wave text-info"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($summary['pending_balance'], 2); ?></div>
                    <div class="stat-label">Pending Balance</div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Sales Chart (Only for Sales Report) -->
    <?php if ($report_type === 'sales' && !empty($chart_labels)): ?>
    <div class="chart-container animate-slide-in delay-3">
        <h5>
            <i class="fas fa-chart-line text-primary me-2"></i>
            Daily Sales Trend
        </h5>
        <canvas id="salesChart" style="height: 300px; width: 100%;"></canvas>
    </div>
    <?php endif; ?>

    <!-- Report Table -->
    <div class="report-table animate-slide-in delay-4">
        <div class="table-header">
            <h5>
                <i class="fas fa-table me-2 text-primary"></i>
                <?php 
                if ($report_type === 'sales') echo 'Sales Details';
                elseif ($report_type === 'products') echo 'Product Performance';
                elseif ($report_type === 'customers') echo 'Customer Analysis';
                elseif ($report_type === 'vendors') echo 'Vendor Performance';
                ?>
                <span class="record-count">
                    <i class="fas fa-list"></i> <?php echo count($data); ?> records
                </span>
            </h5>
            <div class="export-btns">
                <button class="btn-export btn-excel" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="btn-export btn-csv" onclick="exportReport('csv')">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button class="btn-export btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <?php if (empty($data)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h5>No Data Available</h5>
                    <p class="text-muted">Try adjusting your date range or filters to see results</p>
                    <a href="?type=<?php echo $report_type; ?>&date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-calendar-week me-2"></i> View This Month
                    </a>
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
                                <td><strong><?php echo date('M d, Y', strtotime($row['date'])); ?></strong></td>
                                <td><?php echo number_format($row['order_count']); ?></td>
                                <td class="text-success fw-bold">$<?php echo number_format($row['revenue'], 2); ?></td>
                                <td>$<?php echo number_format($row['avg_order_value'], 2); ?></td>
                                <td><?php echo number_format($row['unique_customers']); ?></td>
                                
                            <?php elseif ($report_type === 'products'): ?>
                                <td><strong><?php echo htmlspecialchars(substr($row['name'], 0, 35)) . (strlen($row['name']) > 35 ? '...' : ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['vendor_name'] ?? 'N/A'); ?></td>
                                <td>$<?php echo number_format($row['price'], 2); ?></td>
                                <td>
                                    <?php if ($row['stock'] <= 0): ?>
                                        <span class="badge-status badge-suspended"><i class="fas fa-times-circle"></i> Out</span>
                                    <?php elseif ($row['stock'] < 10): ?>
                                        <span class="badge-status badge-low-stock"><i class="fas fa-exclamation-triangle"></i> <?php echo $row['stock']; ?></span>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> <?php echo $row['stock']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($row['quantity_sold']); ?></td>
                                <td class="text-success fw-bold">$<?php echo number_format($row['revenue'], 2); ?></td>
                                <td><?php echo number_format($row['order_count']); ?></td>
                                
                            <?php elseif ($report_type === 'customers'): ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></strong>
                                    <br><small class="text-muted">@<?php echo $row['username']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['registration_date'])); ?></td>
                                <td><?php echo number_format($row['order_count']); ?></td>
                                <td class="text-success fw-bold">$<?php echo number_format($row['total_spent'], 2); ?></td>
                                <td><?php echo $row['last_order'] ? date('M d, Y', strtotime($row['last_order'])) : '<span class="text-muted">Never</span>'; ?></td>
                                
                            <?php elseif ($report_type === 'vendors'): ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></strong>
                                    <br><small class="text-muted">@<?php echo $row['username']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <?php if ($row['vendor_status'] == 'approved'): ?>
                                        <span class="badge-status badge-approved"><i class="fas fa-check-circle"></i> Approved</span>
                                    <?php elseif ($row['vendor_status'] == 'pending'): ?>
                                        <span class="badge-status badge-pending"><i class="fas fa-clock"></i> Pending</span>
                                    <?php elseif ($row['vendor_status'] == 'suspended'): ?>
                                        <span class="badge-status badge-suspended"><i class="fas fa-ban"></i> Suspended</span>
                                    <?php endif; ?>
                                    <?php if ($row['vendor_verified']): ?>
                                        <span class="badge-status badge-verified ms-1"><i class="fas fa-check-double"></i> Verified</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($row['total_products']); ?></td>
                                <td><?php echo number_format($row['total_sales']); ?></td>
                                <td class="text-success fw-bold">$<?php echo number_format($row['revenue'], 2); ?></td>
                                <td>$<?php echo number_format($row['total_withdrawn'], 2); ?></td>
                                <td class="fw-bold <?php echo ($row['revenue'] - $row['total_withdrawn']) > 0 ? 'text-success' : 'text-muted'; ?>">
                                    <i class="fas fa-wallet"></i> $<?php echo number_format($row['revenue'] - $row['total_withdrawn'], 2); ?>
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
// Sales Chart
<?php if ($report_type === 'sales' && !empty($chart_labels)): ?>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Revenue ($)',
                data: <?php echo json_encode($chart_revenue); ?>,
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.05)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: '#4361ee',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 6,
                yAxisID: 'y'
            },
            {
                label: 'Orders',
                data: <?php echo json_encode($chart_orders); ?>,
                borderColor: '#06d6a0',
                backgroundColor: 'rgba(6, 214, 160, 0.05)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: '#06d6a0',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 6,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top',
                labels: { usePointStyle: true, boxWidth: 10 }
            },
            tooltip: {
                backgroundColor: '#2b2d42',
                titleColor: '#fff',
                bodyColor: '#e9ecef',
                borderColor: '#4361ee',
                borderWidth: 1,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        let value = context.raw;
                        if (context.dataset.label === 'Revenue ($)') {
                            return label + ': $' + value.toLocaleString();
                        }
                        return label + ': ' + value.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                title: { display: true, text: 'Revenue ($)', color: '#4361ee' },
                ticks: { callback: function(value) { return '$' + value.toLocaleString(); } },
                grid: { color: '#e9ecef' }
            },
            y1: {
                position: 'right',
                title: { display: true, text: 'Orders', color: '#06d6a0' },
                grid: { drawOnChartArea: false },
                ticks: { stepSize: 1 }
            },
            x: {
                title: { display: true, text: 'Date' },
                grid: { display: false },
                ticks: { maxRotation: 45, minRotation: 45 }
            }
        }
    }
});
<?php endif; ?>

// Export Report
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