<?php
// admin/analytics/analytics-dashboard.php
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
    
    // Traffic by Device Type
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
    
    // Add to Cart
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as carts FROM cart_items 
                         WHERE added_at BETWEEN ? AND ?");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $cart_visitors = $stmt->fetchColumn();
    
    // Checkout Started
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

$page_title = 'Site Analytics Dashboard';
require_once '../includes/header.php';
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
    width: 100%;
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

/* Chart Containers */
.chart-container {
    height: 280px;
    position: relative;
}

.chart-sm {
    height: 220px;
}

/* Table Styles */
.table-custom {
    margin-bottom: 0;
    font-size: 0.875rem;
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

.badge-primary {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-full);
    font-size: 0.65rem;
    font-weight: 600;
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

/* Metric Cards */
.metric-card {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    text-align: center;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
    height: 100%;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.metric-label {
    font-size: 0.65rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.metric-card small {
    font-size: 0.6rem;
    color: var(--gray-400);
}

/* Funnel Styles */
.funnel-container {
    padding: 1rem;
}

.funnel-step {
    margin-bottom: 1.25rem;
    position: relative;
}

.funnel-label {
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: var(--gray-700);
    font-size: 0.85rem;
}

.funnel-bar {
    background: var(--primary-gradient);
    height: 45px;
    border-radius: var(--border-radius-md);
    position: relative;
    transition: width 1s ease-in-out;
    overflow: hidden;
}

.funnel-value {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.funnel-percentage {
    text-align: right;
    margin-top: 0.25rem;
    font-weight: 600;
    color: var(--primary);
    font-size: 0.75rem;
}

/* Avatar */
.avatar-sm {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(67, 97, 238, 0.1);
    border-radius: var(--border-radius-full);
}

.avatar-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary);
}

/* Buttons */
.btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    border-radius: var(--border-radius-md);
    transition: var(--transition);
    cursor: pointer;
}

.btn-primary {
    background: var(--primary);
    border: none;
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
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

/* Responsive Design */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .main-content {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1rem;
    }
    
    .page-header .d-flex {
        flex-direction: column;
        align-items: center !important;
        text-align: center;
    }
    
    .page-header .d-flex .d-flex {
        justify-content: center;
        margin-top: 0.75rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .filter-card .row > div {
        margin-bottom: 0.75rem;
    }
    
    .chart-container {
        height: 240px;
    }
    
    .chart-sm {
        height: 200px;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 0.75rem;
    }
    
    .page-header {
        padding: 0.875rem;
    }
    
    .page-header h1 {
        font-size: 1.1rem;
    }
    
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
    
    .filter-card {
        padding: 0.875rem;
    }
    
    .chart-container {
        height: 200px;
    }
    
    .chart-sm {
        height: 180px;
    }
    
    .metric-value {
        font-size: 1.2rem;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table-custom {
        min-width: 550px;
    }
    
    .funnel-bar {
        height: 38px;
    }
    
    .funnel-value {
        font-size: 0.8rem;
        right: 8px;
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

.bg-success { background: var(--success) !important; }
.bg-warning { background: var(--warning) !important; }
.bg-danger { background: var(--danger) !important; }
.bg-info { background: var(--info) !important; }
.bg-primary { background: var(--primary) !important; }

.fw-bold { font-weight: 700; }
.fw-medium { font-weight: 500; }
.fw-normal { font-weight: 400; }

.text-center { text-align: center; }
.text-start { text-align: left; }
.text-end { text-align: right; }

.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-3 { margin-top: 0.75rem; }
.mt-4 { margin-top: 1rem; }
.mb-1 { margin-bottom: 0.25rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }

.p-1 { padding: 0.25rem; }
.p-2 { padding: 0.5rem; }
.p-3 { padding: 0.75rem; }
.p-4 { padding: 1rem; }

.d-flex { display: flex; }
.flex-wrap { flex-wrap: wrap; }
.justify-content-between { justify-content: space-between; }
.align-items-center { align-items: center; }
.gap-1 { gap: 0.25rem; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }

.w-100 { width: 100%; }
.h-100 { height: 100%; }
</style>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header overflow-visible">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1>
                        <i class="fas fa-chart-line"></i>
                        Site Analytics Dashboard
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php echo date('F d, Y', strtotime($start_date)); ?> - <?php echo date('F d, Y', strtotime($end_date)); ?>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="../dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i> Back
                    </a>
                    <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt me-2"></i> Refresh
                    </button>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
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
        </div>

        <!-- Date Range Selector -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i> Date Range
                    </label>
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
                
                <div class="col-md-3 col-sm-6 custom-date-range" style="display: <?= $default_range == 'custom' ? 'block' : 'none' ?>">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i> Start Date
                    </label>
                    <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                </div>
                
                <div class="col-md-3 col-sm-6 custom-date-range" style="display: <?= $default_range == 'custom' ? 'block' : 'none' ?>">
                    <label class="form-label">
                        <i class="fas fa-calendar-check"></i> End Date
                    </label>
                    <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <button type="submit" class="btn-filter w-100">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Today's Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                    <i class="fas fa-dollar-sign text-primary"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">$<?= number_format($today_revenue, 2) ?></div>
                    <div class="stat-label">Today's Revenue</div>
                    <div class="stat-trend mt-1">
                        <i class="fas fa-calendar me-1"></i> <?= date('M d, Y') ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                    <i class="fas fa-shopping-cart text-success"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($today_orders) ?></div>
                    <div class="stat-label">Today's Orders</div>
                    <div class="stat-trend mt-1">
                        <i class="fas fa-check-circle me-1"></i> Completed
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                    <i class="fas fa-eye text-info"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($today_visitors) ?></div>
                    <div class="stat-label">Today's Visitors</div>
                    <div class="stat-trend mt-1">
                        <i class="fas fa-users me-1"></i> Unique Visitors
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                    <i class="fas fa-user-plus text-warning"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($today_customers) ?></div>
                    <div class="stat-label">New Customers</div>
                    <div class="stat-trend mt-1">
                        <i class="fas fa-calendar me-1"></i> Registered Today
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-line"></i>
                            Revenue & Orders Trend
                        </h6>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" onclick="changeChartView('daily')">Daily</button>
                            <button type="button" class="btn btn-outline-primary" onclick="changeChartView('monthly')">Monthly</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-pie"></i>
                            Traffic by Device
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-sm">
                            <canvas id="deviceChart"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php foreach($device_data as $device): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-<?= $device['device_type'] == 'Mobile' ? 'mobile-alt' : ($device['device_type'] == 'Tablet' ? 'tablet-alt' : 'desktop') ?> me-2"></i><?= $device['device_type'] ?></span>
                                <span class="fw-bold"><?= number_format($device['visitors']) ?> visitors</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Funnel & Conversion Metrics -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-funnel-dollar"></i>
                            Sales Funnel
                        </h6>
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
            
            <div class="col-lg-6 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-simple"></i>
                            Conversion Metrics
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="metric-card">
                                    <div class="metric-value"><?= number_format($conversion_rate, 2) ?>%</div>
                                    <div class="metric-label">Conversion Rate</div>
                                    <small class="text-muted">Sessions to Orders</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card">
                                    <div class="metric-value"><?= number_format($repeat_rate, 1) ?>%</div>
                                    <div class="metric-label">Repeat Customer Rate</div>
                                    <small class="text-muted">Returning Customers</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card">
                                    <div class="metric-value">$<?= number_format($avg_order_value, 2) ?></div>
                                    <div class="metric-label">Avg. Order Value</div>
                                    <small class="text-muted">Average per order</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card">
                                    <?php $customer_value = ($total_customers > 0) ? $total_revenue / $total_customers : 0; ?>
                                    <div class="metric-value">$<?= number_format($customer_value, 2) ?></div>
                                    <div class="metric-label">Customer Value</div>
                                    <small class="text-muted">Revenue per customer</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers Row -->
        <div class="row mb-4">
            <div class="col-lg-4 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-box"></i>
                            Top Products
                        </h6>
                        <span class="badge-primary">Revenue</span>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach($top_products as $index => $product): ?>
                            <div class="d-flex align-items-center py-2 border-bottom">
                                <div class="flex-shrink-0">
                                    <div class="avatar-sm">
                                        <span class="avatar-title"><?= $index + 1 ?></span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 fw-medium"><?= htmlspecialchars(substr($product['name'], 0, 35)) . (strlen($product['name']) > 35 ? '...' : '') ?></h6>
                                    <p class="text-muted mb-0 small"><?= $product['category'] ?> • <?= number_format($product['units_sold']) ?> sold</p>
                                </div>
                                <div class="flex-shrink-0 text-end">
                                    <div class="fw-bold text-success">$<?= number_format($product['revenue'], 2) ?></div>
                                    <small class="text-muted"><?= number_format($product['orders']) ?> orders</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-pie"></i>
                            Top Categories
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-sm">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php 
                            $total_category_revenue = array_sum(array_column($top_categories, 'revenue'));
                            foreach($top_categories as $category): 
                                $percentage = ($total_category_revenue > 0) ? ($category['revenue'] / $total_category_revenue) * 100 : 0;
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="flex-grow-1 me-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="small"><?= htmlspecialchars($category['category']) ?></span>
                                        <span class="small text-muted"><?= round($percentage, 1) ?>%</span>
                                    </div>
                                    <div class="progress-custom mt-1">
                                        <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                </div>
                                <span class="fw-bold small text-success">$<?= number_format($category['revenue'], 2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-users"></i>
                            Top Customers
                        </h6>
                        <span class="badge-primary">Loyalty</span>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach($top_customers as $index => $customer): ?>
                            <div class="d-flex align-items-center py-2 border-bottom">
                                <div class="flex-shrink-0">
                                    <div class="avatar-sm">
                                        <span class="avatar-title"><?= substr($customer['full_name'] ?? $customer['email'], 0, 1) ?></span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 fw-medium"><?= htmlspecialchars($customer['full_name'] ?? 'Customer') ?></h6>
                                    <p class="text-muted mb-0 small"><?= $customer['email'] ?></p>
                                </div>
                                <div class="flex-shrink-0 text-end">
                                    <div class="fw-bold text-success">$<?= number_format($customer['total_spent'], 2) ?></div>
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
            <div class="col-lg-6 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-globe"></i>
                            Geographic Distribution
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($geographic_sales)): ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>City</th>
                                        <th>Customers</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </thead>
                                <tbody>
                                    <?php foreach($geographic_sales as $geo): ?>
                                    <tr>
                                        <td><i class="fas fa-globe-americas me-2 text-primary"></i><?= htmlspecialchars($geo['country']) ?> </td>
                                        <td><?= htmlspecialchars($geo['city']) ?> </td>
                                        <td><?= number_format($geo['customers']) ?> </td>
                                        <td><?= number_format($geo['orders']) ?> </td>
                                        <td class="fw-bold text-success">$<?= number_format($geo['revenue'], 2) ?> </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No geographic data available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-clock"></i>
                            Hourly Traffic Pattern
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 280px;">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Status Distribution -->
        <div class="analytics-card">
            <div class="card-header">
                <h6>
                    <i class="fas fa-chart-pie"></i>
                    Order Status Distribution
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
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
                    <div class="col-md-3 col-6">
                        <div class="metric-card">
                            <div class="metric-value text-<?= $color ?>"><?= number_format($status['count']) ?></div>
                            <div class="metric-label"><?= ucfirst($status['status']) ?></div>
                            <small class="text-muted"><?= number_format($percentage, 1) ?>%</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Real-time Updates -->
        <div class="analytics-card mt-4">
            <div class="card-header">
                <h6>
                    <i class="fas fa-bolt"></i>
                    Real-time Updates
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let revenueChart, deviceChart, categoryChart, hourlyChart;
let chartView = 'daily';

// Chart data from PHP - Simple and clean
const dailyLabels = <?php echo json_encode(array_column($daily_data, 'date')); ?>;
const dailyRevenue = <?php echo json_encode(array_column($daily_data, 'revenue')); ?>;
const dailyOrders = <?php echo json_encode(array_column($daily_data, 'orders')); ?>;

const monthlyLabels = <?php echo json_encode(array_column($monthly_data, 'month')); ?>;
const monthlyRevenue = <?php echo json_encode(array_column($monthly_data, 'revenue')); ?>;
const monthlyOrders = <?php echo json_encode(array_column($monthly_data, 'orders')); ?>;

const deviceLabels = <?php echo json_encode(array_column($device_data, 'device_type')); ?>;
const deviceVisitors = <?php echo json_encode(array_column($device_data, 'visitors')); ?>;

const categoryLabels = <?php echo json_encode(array_column($top_categories, 'category')); ?>;
const categoryRevenue = <?php echo json_encode(array_column($top_categories, 'revenue')); ?>;

// Create hourly data array
const hourlyData = [];
<?php
$hourly_array = array_fill(0, 24, 0);
foreach($hourly_traffic as $h) {
    $hourly_array[(int)$h['hour']] = (int)$h['visitors'];
}
foreach($hourly_array as $value) {
    echo "hourlyData.push($value);\n";
}
?>
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2});
}

function formatNumber(num) {
    return parseFloat(num).toLocaleString('en-US');
}

function initCharts() {
    // Format dates for display
    const formattedDailyLabels = dailyLabels.map(d => {
        const date = new Date(d);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    const formattedMonthlyLabels = monthlyLabels.map(m => {
        const date = new Date(m + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: formattedDailyLabels,
            datasets: [{
                label: 'Revenue',
                data: dailyRevenue,
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderColor: '#4361ee',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }, {
                label: 'Orders',
                data: dailyOrders,
                backgroundColor: 'rgba(6, 214, 160, 0.1)',
                borderColor: '#06d6a0',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString() } },
                y1: { position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });
    
    // Device Chart
    const deviceCtx = document.getElementById('deviceChart').getContext('2d');
    deviceChart = new Chart(deviceCtx, {
        type: 'doughnut',
        data: {
            labels: deviceLabels,
            datasets: [{ data: deviceVisitors, backgroundColor: ['#4361ee', '#06d6a0', '#4cc9f0'] }]
        },
        options: { maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
    });
    
    // Category Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    categoryChart = new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: categoryLabels,
            datasets: [{ data: categoryRevenue, backgroundColor: ['#4361ee', '#06d6a0', '#ffb703', '#ef476f', '#4cc9f0'] }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
    
    // Hourly Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    hourlyChart = new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => i + ':00'),
            datasets: [{ label: 'Visitors', data: hourlyData, backgroundColor: '#4361ee', borderRadius: 8 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { callback: v => formatNumber(v) } } }
        }
    });
}
function changeChartView(view) {
    chartView = view;
    const dailyBtn = document.querySelector('.btn-group .btn:nth-child(1)');
    const monthlyBtn = document.querySelector('.btn-group .btn:nth-child(2)');
    
    dailyBtn.classList.toggle('active', view === 'daily');
    monthlyBtn.classList.toggle('active', view === 'monthly');
    
    revenueChart.data.labels = view === 'daily' ? dailyLabels : monthlyLabels;
    revenueChart.data.datasets[0].data = view === 'daily' ? dailyRevenue : monthlyRevenue;
    revenueChart.data.datasets[1].data = view === 'daily' ? dailyOrders : monthlyOrders;
    revenueChart.update();
}

// Toggle custom date range
document.querySelector('select[name="range"]').addEventListener('change', function() {
    const customFields = document.querySelectorAll('.custom-date-range');
    customFields.forEach(field => field.style.display = this.value === 'custom' ? 'block' : 'none');
});

function refreshDashboard() { window.location.reload(); }

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.append('export', format);
    window.open(`export-analytics.php?${params.toString()}`, '_blank');
    Swal.fire({ title: 'Export Started', text: 'Your report is being generated', icon: 'success', timer: 2000, showConfirmButton: false });
}

function startLiveUpdates() {
    Swal.fire({ title: 'Live Updates Started', text: 'Dashboard will update every 30 seconds', icon: 'success', timer: 2000, showConfirmButton: false });
    setInterval(() => updateRealTimeData({}), 30000);
}

function updateRealTimeData(data) {
    const container = document.getElementById('realTimeUpdates');
    const now = new Date().toLocaleTimeString();
    container.innerHTML = `
        <div class="col-md-3 col-6">
            <div class="metric-card"><div class="metric-value text-primary">${data.active_users || 0}</div><div class="metric-label">Active Users</div><small>Last: ${now}</small></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="metric-card"><div class="metric-value text-success">${data.orders_today || 0}</div><div class="metric-label">Orders Today</div><small>Last: ${now}</small></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="metric-card"><div class="metric-value text-info">${formatCurrency(data.revenue_today || 0)}</div><div class="metric-label">Revenue Today</div><small>Last: ${now}</small></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="metric-card"><div class="metric-value text-warning">${data.new_customers || 0}</div><div class="metric-label">New Customers</div><small>Last: ${now}</small></div>
        </div>
    `;
}

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    updateRealTimeData({});
});
</script>

<?php require_once '../includes/footer.php'; ?>