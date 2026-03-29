<?php
// admin/sales-report.php
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

function formatNumber($number) {
    return number_format($number ?? 0);
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
    
    // Top Products
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
    
    // Sales by Category
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
    
    // Recent Refunds
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
    $recent_refunds = [];
}

$page_title = 'Sales Report';
require_once './includes/header.php';
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --primary-light: #4895ef;
    --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
    
    --success: #06d6a0;
    --success-dark: #0ca678;
    --success-light: #80ffdb;
    --success-gradient: linear-gradient(135deg, #06d6a0, #0ca678);
    
    --warning: #ffb703;
    --warning-dark: #f77f00;
    --warning-light: #ffe066;
    --warning-gradient: linear-gradient(135deg, #ffb703, #f77f00);
    
    --danger: #ef476f;
    --danger-dark: #d62828;
    --danger-light: #ffafcc;
    --danger-gradient: linear-gradient(135deg, #ef476f, #d62828);
    
    --info: #4cc9f0;
    --info-dark: #0096c7;
    --info-light: #a2d6f9;
    --info-gradient: linear-gradient(135deg, #4cc9f0, #0096c7);
    
    --dark: #2b2d42;
    --dark-light: #4a4e69;
    --light: #f8f9fa;
    
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
    
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --border-radius-sm: 8px;
    --border-radius-md: 12px;
    --border-radius-lg: 16px;
    --border-radius-xl: 20px;
    --border-radius-2xl: 24px;
    --border-radius-full: 9999px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: var(--gray-100);
    overflow-x: hidden;
}

.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--gray-100);
    position: relative;
}

.main-content {
    flex: 1;
    padding: 2rem;
    background: var(--gray-100);
    transition: var(--transition);
    position: relative;
    height:auto;
    width: 100%;
}

/* ============================================
   RESPONSIVE MAIN CONTENT
============================================ */
@media (max-width: 1200px) {
    .main-content {
        padding: 1.5rem;
    }
}

@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 0.75rem;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 0.5rem;
    }
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
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

.page-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-header h1 i {
    font-size: 1.5rem;
    color: var(--primary);
    -webkit-text-fill-color: initial;
}

.page-header p {
    font-size: 0.875rem;
}

/* Responsive Page Header */
@media (max-width: 768px) {
    .page-header {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 1.2rem;
    }
    
    .page-header h1 i {
        font-size: 1.2rem;
    }
    
    .page-header p {
        font-size: 0.75rem;
    }
}

@media (max-width: 576px) {
    .page-header {
        padding: 0.875rem;
    }
    
    .page-header h1 {
        font-size: 1rem;
    }
    
    .page-header .d-flex {
        flex-direction: column;
        align-items: center !important;
        text-align: center;
        gap: 0.75rem;
    }
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 1.25rem;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    border: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-trend {
    font-size: 0.65rem;
    padding: 2px 8px;
    border-radius: var(--border-radius-full);
    background: rgba(6, 214, 160, 0.1);
    color: var(--success);
    display: inline-block;
    margin-top: 0.25rem;
}

/* Responsive Stats Cards */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .stat-card {
        padding: 0.875rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .stat-value {
        font-size: 1.2rem;
    }
    
    .stat-label {
        font-size: 0.6rem;
    }
    
    .stat-trend {
        font-size: 0.55rem;
        padding: 2px 6px;
    }
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.filter-card .form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-card .form-label i {
    color: var(--primary);
}

.filter-card .form-control,
.filter-card .form-select {
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-300);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    transition: var(--transition);
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    outline: none;
}

.btn-filter {
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--border-radius-md);
    padding: 0.5rem 1rem;
    font-weight: 600;
    font-size: 0.875rem;
    transition: var(--transition);
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

/* Responsive Filter Card */
@media (max-width: 768px) {
    .filter-card {
        padding: 0.875rem;
    }
    
    .filter-card .form-label {
        font-size: 0.7rem;
    }
    
    .filter-card .form-control,
    .filter-card .form-select {
        padding: 0.4rem 0.6rem;
        font-size: 0.75rem;
    }
    
    .btn-filter {
        padding: 0.4rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .filter-card .row > div {
        margin-bottom: 0.75rem;
    }
}

/* Analytics Cards */
.analytics-card {
    background: white;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    margin-bottom: 1.5rem;
}

.analytics-card .card-header {
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.analytics-card .card-header h6 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.analytics-card .card-header h6 i {
    color: var(--primary);
    font-size: 1rem;
}

.analytics-card .card-body {
    padding: 1.25rem;
}

/* Responsive Analytics Cards */
@media (max-width: 768px) {
    .analytics-card {
        margin-bottom: 1rem;
    }
    
    .analytics-card .card-header {
        padding: 0.75rem 1rem;
        flex-direction: column;
        text-align: center;
    }
    
    .analytics-card .card-header h6 {
        font-size: 0.75rem;
    }
    
    .analytics-card .card-body {
        padding: 0.875rem;
    }
}

/* Chart Containers */
.chart-container {
    height: 280px;
    position: relative;
}

.chart-sm {
    height: 220px;
}

/* Responsive Charts */
@media (max-width: 1200px) {
    .chart-container {
        height: 260px;
    }
    
    .chart-sm {
        height: 200px;
    }
}

@media (max-width: 992px) {
    .chart-container {
        height: 240px;
    }
}

@media (max-width: 768px) {
    .chart-container {
        height: 200px;
    }
    
    .chart-sm {
        height: 180px;
    }
}

@media (max-width: 576px) {
    .chart-container {
        height: 180px;
    }
    
    .chart-sm {
        height: 160px;
    }
}

/* Table Styles */
.table-custom {
    margin-bottom: 0;
    font-size: 0.875rem;
    width: 100%;
}

.table-custom th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.75rem;
    border-bottom: 2px solid var(--gray-300);
}

.table-custom td {
    padding: 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

.table-custom tbody tr:hover {
    background: var(--gray-100);
}

/* Responsive Tables */
@media (max-width: 992px) {
    .table-custom {
        font-size: 0.75rem;
    }
    
    .table-custom th,
    .table-custom td {
        padding: 0.5rem;
    }
}

@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 0 -0.75rem;
        padding: 0 0.75rem;
    }
    
    .table-custom {
        min-width: 550px;
    }
    
    .table-custom th,
    .table-custom td {
        padding: 0.5rem;
        font-size: 0.65rem;
        white-space: nowrap;
    }
}

@media (max-width: 576px) {
    .table-custom {
        min-width: 480px;
    }
    
    .table-custom th,
    .table-custom td {
        padding: 0.4rem;
        font-size: 0.6rem;
    }
}

/* Badge Styles */
.badge-category {
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-full);
    font-size: 0.65rem;
    font-weight: 600;
    background: rgba(76, 201, 240, 0.15);
    color: var(--info);
    display: inline-block;
}

.badge-status {
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-full);
    font-size: 0.65rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.badge-status.pending { background: rgba(255, 183, 3, 0.15); color: var(--warning); }
.badge-status.processing { background: rgba(76, 201, 240, 0.15); color: var(--info); }
.badge-status.shipped { background: rgba(67, 97, 238, 0.15); color: var(--primary); }
.badge-status.delivered { background: rgba(6, 214, 160, 0.15); color: var(--success); }
.badge-status.cancelled { background: rgba(239, 71, 111, 0.15); color: var(--danger); }

/* Responsive Badges */
@media (max-width: 768px) {
    .badge-category,
    .badge-status {
        font-size: 0.55rem;
        padding: 0.2rem 0.4rem;
    }
}

@media (max-width: 576px) {
    .badge-category,
    .badge-status {
        font-size: 0.5rem;
        padding: 0.15rem 0.35rem;
    }
}

/* Progress Bar */
.progress-custom {
    height: 5px;
    border-radius: var(--border-radius-full);
    background: var(--gray-200);
    overflow: hidden;
}

.progress-custom .progress-bar {
    background: var(--primary-gradient);
    border-radius: var(--border-radius-full);
    height: 100%;
    transition: width 0.6s ease;
}

/* Buttons */
.btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    border-radius: var(--border-radius-md);
    transition: var(--transition);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--primary-gradient);
    border: none;
    color: white;
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
}

.btn-outline-primary {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary);
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

/* Responsive Buttons */
@media (max-width: 768px) {
    .btn {
        padding: 0.4rem 0.75rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 576px) {
    .btn {
        padding: 0.3rem 0.6rem;
        font-size: 0.7rem;
    }
    
    .page-header .btn {
        width: 100%;
    }
}

/* Dropdown */
.dropdown-menu {
    border: none;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    padding: 0.5rem 0;
    animation: fadeInDown 0.2s ease;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    transition: var(--transition);
}

.dropdown-item:hover {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
}

/* Responsive Dropdown */
@media (max-width: 768px) {
    .dropdown-menu {
        position: fixed;
        top: auto;
        bottom: auto;
        left: 50%;
        right: auto;
        transform: translateX(-50%);
        width: calc(100% - 2rem);
        min-width: auto;
        /* max-width: 280px; */
        z-index: 1050;
    }
    
    .dropdown-menu.show {
        animation: fadeInUp 0.2s ease;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translate(-50%, 10px);
        }
        to {
            opacity: 1;
            transform: translate(-50%, 0);
        }
    }
    
    .dropdown-item {
        padding: 0.4rem 0.8rem;
        font-size: 0.75rem;
    }
    
    .dropdown-item i {
        font-size: 0.7rem;
    }
}

/* Row and Column Adjustments */
.row {
    margin-left: -0.75rem;
    margin-right: -0.75rem;
}

[class*="col-"] {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

@media (max-width: 768px) {
    .row {
        margin-left: -0.5rem;
        margin-right: -0.5rem;
    }
    
    [class*="col-"] {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        margin-bottom: 0.75rem;
    }
    
    .mb-4 {
        margin-bottom: 0.75rem !important;
    }
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-400);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--gray-500);
}

/* Print Styles */
@media print {
    .dashboard-container {
        background: white;
    }
    
    .main-content {
        margin: 0;
        padding: 0;
    }
    
    .filter-card,
    .page-header .btn,
    .page-header .dropdown,
    .dropdown,
    .btn-filter,
    .btn-outline-primary,
    .analytics-card .dropdown,
    .sidebar {
        display: none !important;
    }
    
    .stat-card {
        break-inside: avoid;
        page-break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .analytics-card {
        break-inside: avoid;
        page-break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .page-header {
        background: none;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    canvas {
        max-width: 100%;
        height: auto !important;
    }
}

/* Animations */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: var(--border-radius-full);
}

/* Utility Classes */
.text-success { color: var(--success) !important; }
.text-warning { color: var(--warning) !important; }
.text-danger { color: var(--danger) !important; }
.text-info { color: var(--info) !important; }
.text-primary { color: var(--primary) !important; }

.fw-bold { font-weight: 700; }
.fw-medium { font-weight: 500; }
.fw-normal { font-weight: 400; }

.text-center { text-align: center; }
.text-start { text-align: left; }
.text-end { text-align: right; }

/* ============================================
   FIXED DROPDOWN - CONTAINER SE BAHAR
============================================ */

/* Page Header ke liye overflow visible */
.page-header {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: visible !important; /* IMPORTANT: Dropdown ke liye overflow visible */
}

/* Page Header ke children ko overflow visible */
.page-header .d-flex {
    overflow: visible;
}

.page-header .d-flex .dropdown {
    overflow: visible;
}

/* Dropdown Container - Relative positioning */
.dropdown {
    position: relative;
    display: inline-block;
    overflow: visible;
}

/* Dropdown Toggle Button */
.dropdown-toggle {
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--border-radius-md);
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    transition: var(--transition);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.dropdown-toggle:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.dropdown-toggle::after {
    display: inline-block;
    margin-left: 0.5rem;
    content: '';
    border-top: 0.3em solid;
    border-right: 0.3em solid transparent;
    border-bottom: 0;
    border-left: 0.3em solid transparent;
    vertical-align: middle;
}

/* Dropdown Menu - Positioned absolutely relative to dropdown container */
.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    z-index: 1050; /* High z-index to appear above everything */
    min-width: 200px;
    padding: 0.5rem 0;
    margin: 0.5rem 0 0;
    font-size: 0.875rem;
    color: var(--gray-700);
    text-align: left;
    list-style: none;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    transition: opacity 0.2s ease, transform 0.2s ease;
    opacity: 0;
    transform: translateY(-10px);
    visibility: hidden;
    display: block !important;
}

.dropdown-menu.show {
    opacity: 1;
    transform: translateY(0);
    visibility: visible;
}

/* Dropdown Menu Items */
.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 1.25rem;
    clear: both;
    font-weight: 400;
    color: var(--gray-700);
    text-align: inherit;
    text-decoration: none;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
    transition: var(--transition);
    cursor: pointer;
}

.dropdown-item i {
    width: 20px;
    font-size: 1rem;
    color: var(--gray-500);
    transition: var(--transition);
}

.dropdown-item:hover {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(58, 12, 163, 0.05));
    color: var(--primary);
}

.dropdown-item:hover i {
    color: var(--primary);
}

/* Dropdown Divider */
.dropdown-divider {
    height: 1px;
    margin: 0.5rem 0;
    overflow: hidden;
    background-color: var(--gray-200);
}

/* ============================================
   MOBILE DROPDOWN FIX - CONTAINER SE BAHAR
============================================ */
@media (max-width: 768px) {
    /* Page header ke liye overflow visible */
    .page-header {
        overflow: visible !important;
        position: relative;
        z-index: 100;
    }
    
    /* Main content ke liye overflow visible */
    .main-content {
        overflow-x: visible !important;
        overflow-y: visible !important;
    }
    
    /* Dashboard container ke liye overflow visible */
    .dashboard-container {
        overflow-x: visible !important;
    }
    
    /* Dropdown ko absolute positioning se fix karna */
    .dropdown {
        position: relative;
        overflow: visible;
    }
    
    /* Dropdown menu ko right side align karna aur container se bahar nikalna */
    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        left: auto;
        transform: translateX(0);
        min-width: 200px;
        width: auto;
        max-width: 280px;
        margin-top: 0.5rem;
        z-index: 9999;
    }
    
    /* Dropdown menu show hone par */
    .dropdown-menu.show {
        display: block;
        opacity: 1;
        transform: translateY(0);
        visibility: visible;
    }
    
    /* Dropdown items ki styling */
    .dropdown-item {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
        white-space: nowrap;
    }
    
    .dropdown-item i {
        font-size: 0.85rem;
    }
}

/* Small mobile screens (up to 576px) */
@media (max-width: 576px) {
    /* Dropdown ko thoda adjust karna */
    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        left: auto;
        min-width: 180px;
        max-width: 240px;
    }
    
    .dropdown-item {
        padding: 0.4rem 0.8rem;
        font-size: 0.75rem;
    }
    
    .dropdown-item i {
        font-size: 0.75rem;
    }
}

/* Landscape orientation for mobile */
@media (max-width: 768px) and (orientation: landscape) {
    .dropdown-menu {
        max-height: 300px;
        overflow-y: auto;
    }
}

/* Animation for dropdown */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-menu.show {
    animation: fadeInDown 0.2s ease;
}
</style>

<div class="dashboard-container">
    
    <main class="main-content">
 <!-- Page Header - Add style overflow-visible -->
<div class="page-header mb-4" style="overflow: visible !important; position: relative; z-index: 100;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>
                <i class="fas fa-chart-line"></i>
                Sales Report
            </h1>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar-alt me-2"></i>
                <?php echo date('F d, Y', strtotime($start_date)); ?> - <?php echo date('F d, Y', strtotime($end_date)); ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap" style="position: relative; z-index: 101;">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
            <button class="btn btn-outline-primary" onclick="printReport()">
                <i class="fas fa-print me-2"></i> Print
            </button>
            <div class="dropdown" style="position: relative; overflow: visible;">
                <button class="btn btn-primary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download me-2"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown" style="position: absolute; top: 100%; right: 0; left: auto; z-index: 9999;">
                    <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')"><i class="fas fa-file-pdf me-2"></i> PDF Report</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('excel')"><i class="fas fa-file-excel me-2"></i> Excel Data</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('csv')"><i class="fas fa-file-csv me-2"></i> CSV Data</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="fas fa-print me-2"></i> Print</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i> Start Date
                    </label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">
                        <i class="fas fa-calendar-check"></i> End Date
                    </label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="col-md-2 col-sm-6">
                    <label class="form-label">
                        <i class="fas fa-chart-line"></i> Filter Type
                    </label>
                    <select class="form-select" name="filter_type">
                        <option value="daily" <?php echo $filter_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo $filter_type == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo $filter_type == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
                
                <div class="col-md-2 col-sm-6">
                    <label class="form-label">
                        <i class="fas fa-chart-simple"></i> Chart Type
                    </label>
                    <select class="form-select" name="chart_type">
                        <option value="line" <?php echo $chart_type == 'line' ? 'selected' : ''; ?>>Line Chart</option>
                        <option value="bar" <?php echo $chart_type == 'bar' ? 'selected' : ''; ?>>Bar Chart</option>
                    </select>
                </div>
                
                <div class="col-md-2 col-sm-6">
                    <button type="submit" class="btn-filter w-100">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                    <i class="fas fa-dollar-sign text-primary"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo formatCurrency($sales_summary['total_sales'] ?? 0); ?></div>
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-trend mt-1">
                        <i class="fas fa-shopping-cart me-1"></i> <?php echo formatNumber($sales_summary['total_orders'] ?? 0); ?> orders
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                    <i class="fas fa-chart-line text-success"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo formatCurrency($sales_summary['avg_order_value'] ?? 0); ?></div>
                    <div class="stat-label">Avg. Order Value</div>
                    <div class="stat-trend mt-1">
                        <i class="fas fa-calendar me-1"></i> <?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d', strtotime($end_date)); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                    <i class="fas fa-undo text-warning"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo formatCurrency($refund_summary['total_refunded'] ?? 0); ?></div>
                    <div class="stat-label">Refunds</div>
                    <div class="stat-trend mt-1">
                        <i class="fas fa-receipt me-1"></i> <?php echo formatNumber($refund_summary['refund_count'] ?? 0); ?> refunds
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                    <i class="fas fa-users text-info"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">
                        <?php 
                        try {
                            $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM orders WHERE order_date BETWEEN ? AND ?");
                            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                            echo formatNumber($stmt->fetchColumn());
                        } catch(Exception $e) {
                            echo '0';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Active Customers</div>
                    <div class="stat-trend mt-1">
                        <i class="fas fa-user-check me-1"></i> Unique buyers
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-line"></i>
                            Sales Trend (12 Months)
                        </h6>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="changeChartType('line')">Line Chart</a></li>
                                <li><a class="dropdown-item" href="#" onclick="changeChartType('bar')">Bar Chart</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-pie"></i>
                            Sales by Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-sm">
                            <canvas id="statusPieChart"></canvas>
                        </div>
                        <div class="mt-4 text-center">
                            <?php foreach($sales_by_status as $status): ?>
                            <span class="me-2">
                                <i class="fas fa-circle text-<?php echo getStatusColor($status['status']); ?>"></i>
                                <?php echo ucfirst($status['status']); ?> (<?php echo formatNumber($status['order_count']); ?>)
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-box"></i>
                            Top Products
                        </h6>
                        <span class="badge-primary">Top 10</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom">
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
                                        <td class="fw-medium"><?php echo htmlspecialchars(substr($product['name'], 0, 35)) . (strlen($product['name']) > 35 ? '...' : ''); ?></td>
                                        <td><span class="badge-category"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></span></td>
                                        <td><?php echo formatNumber($product['total_quantity']); ?></td>
                                        <td class="text-success fw-bold"><?php echo formatCurrency($product['total_revenue']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-users"></i>
                            Top Customers
                        </h6>
                        <span class="badge-primary">Top 10</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom">
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
                                        <td class="fw-medium"><?php echo htmlspecialchars($customer['full_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo formatNumber($customer['order_count']); ?></td>
                                        <td class="text-success fw-bold"><?php echo formatCurrency($customer['total_spent']); ?></td>
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
            <div class="col-lg-6 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-pie"></i>
                            Sales by Category
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom">
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
                                        <td class="fw-medium"><?php echo htmlspecialchars($category['category'] ?? 'Uncategorized'); ?></td>
                                        <td><?php echo formatNumber($category['order_count']); ?></td>
                                        <td><?php echo formatNumber($category['unique_customers']); ?></td>
                                        <td>$<?php echo number_format($category['total_sales'], 2); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress-custom flex-grow-1 me-2" style="width: 80px;">
                                                    <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <span class="small"><?php echo number_format($percentage, 1); ?>%</span>
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
            
            <div class="col-lg-6 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-credit-card"></i>
                            Payment Methods Analysis
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Method</th>
                                        <th>Orders</th>
                                        <th>Total Amount</th>
                                        <th>Avg. Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payment_methods as $method): ?>
                                    <tr>
                                        <td><span class="badge-category"><?php echo strtoupper($method['payment_method']); ?></span></td>
                                        <td><?php echo formatNumber($method['count']); ?></td>
                                        <td>$<?php echo number_format($method['total_amount'], 2); ?></td>
                                        <td>$<?php echo number_format($method['avg_amount'], 2); ?></td>
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
        <div class="analytics-card">
            <div class="card-header">
                <h6>
                    <i class="fas fa-receipt"></i>
                    Recent Refunds
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_refunds)): ?>
                <div class="table-responsive">
                    <table class="table table-custom">
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
                                <td class="fw-medium">#<?php echo $refund['id']; ?></td>
                                <td><?php echo htmlspecialchars($refund['customer_name']); ?></td>
                                <td><?php echo $refund['order_number'] ?? 'N/A'; ?></td>
                                <td class="text-danger">-<?php echo formatCurrency($refund['refund_amount']); ?></td>
                                <td><?php echo htmlspecialchars($refund['reason']); ?></td>
                                <td>
                                    <span class="badge-status <?php echo getRefundStatusColor($refund['status']); ?>">
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
                <div class="empty-state text-center py-5">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No refunds in selected period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let salesChart;
let statusPieChart;

// Sales Chart Data
const monthlyLabels = [<?php 
    $labels = [];
    foreach($monthly_data as $data) {
        $labels[] = "'" . date('M Y', strtotime($data['month'] . '-01')) . "'";
    }
    echo implode(', ', $labels);
?>];

const monthlySales = [<?php 
    $sales = [];
    foreach($monthly_data as $data) {
        $sales[] = $data['total_sales'];
    }
    echo implode(', ', $sales);
?>];

// Status Chart Data
const statusLabels = [<?php 
    $labels = [];
    foreach($sales_by_status as $status) {
        $labels[] = "'" . ucfirst($status['status']) . "'";
    }
    echo implode(', ', $labels);
?>];

const statusData = [<?php 
    $data = [];
    foreach($sales_by_status as $status) {
        $data[] = $status['order_count'];
    }
    echo implode(', ', $data);
?>];

const statusColors = [<?php 
    $colors = [];
    foreach($sales_by_status as $status) {
        $colors[] = "'" . getStatusColor($status['status'], true) . "'";
    }
    echo implode(', ', $colors);
?>];

function renderSalesChart() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const chartType = '<?php echo $chart_type; ?>';
    
    if (salesChart) salesChart.destroy();
    
    salesChart = new Chart(ctx, {
        type: chartType,
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Sales',
                data: monthlySales,
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderColor: '#4361ee',
                borderWidth: 2,
                pointBackgroundColor: '#4361ee',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '$' + v.toLocaleString() }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: ctx => 'Sales: $' + ctx.parsed.y.toLocaleString()
                    }
                }
            }
        }
    });
}

function renderStatusChart() {
    const ctx = document.getElementById('statusPieChart').getContext('2d');
    
    if (statusPieChart) statusPieChart.destroy();
    
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
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((ctx.raw / total) * 100);
                            return `${ctx.label}: ${ctx.raw} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function changeChartType(type) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('chart_type', type);
    window.location.href = '?' + urlParams.toString();
}

function printReport() {
    window.print();
}

function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.append('format', format);
    window.open(`export-sales-report.php?${params.toString()}`, '_blank');
    Swal.fire({ title: 'Export Started', text: 'Your report is being generated', icon: 'success', timer: 2000, showConfirmButton: false });
}

// Handle window resize for charts
let resizeTimer;
window.addEventListener('resize', function() {
    if (resizeTimer) clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        if (salesChart) salesChart.resize();
        if (statusPieChart) statusPieChart.resize();
    }, 250);
});

// Fix dropdown on mobile to close when clicking outside
document.addEventListener('click', function(event) {
    const dropdowns = document.querySelectorAll('.dropdown-menu');
    const toggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdowns.forEach(function(dropdown) {
        if (!dropdown.contains(event.target) && !Array.from(toggles).some(toggle => toggle.contains(event.target))) {
            const bsDropdown = bootstrap.Dropdown.getInstance(dropdown.previousElementSibling);
            if (bsDropdown) bsDropdown.hide();
        }
    });
});
// Initialize dropdown properly
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl, {
            boundary: 'viewport',
            popperConfig: function(defaultConfig) {
                defaultConfig.modifiers = [
                    {
                        name: 'preventOverflow',
                        options: {
                            boundary: 'viewport',
                            altAxis: true,
                            tether: false
                        }
                    }
                ];
                return defaultConfig;
            }
        });
    });
});

// Fix dropdown positioning on mobile
function fixDropdownPosition() {
    if (window.innerWidth <= 768) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(function(dropdown) {
            dropdown.style.position = 'fixed';
            dropdown.style.top = 'auto';
            dropdown.style.bottom = 'auto';
            dropdown.style.left = '50%';
            dropdown.style.right = 'auto';
            dropdown.style.transform = 'translateX(-50%)';
            dropdown.style.maxWidth = '280px';
            dropdown.style.width = 'calc(100% - 2rem)';
        });
    } else {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(function(dropdown) {
            dropdown.style.position = 'absolute';
            dropdown.style.transform = '';
            dropdown.style.left = 'auto';
            dropdown.style.right = '0';
            dropdown.style.width = 'auto';
            dropdown.style.maxWidth = '';
        });
    }
}

// Call on load and resize
window.addEventListener('load', fixDropdownPosition);
window.addEventListener('resize', function() {
    setTimeout(fixDropdownPosition, 100);
});

</script>

<?php require_once './includes/footer.php'; ?>