<?php
// admin/analytics.php - Complete CSS
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
    
    // Customer Acquisition Channels
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
    
    // Sales by Country/City
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

<style>
/* ============================================
   CSS VARIABLES
============================================ */
/* ============================================
   CSS VARIABLES
============================================ */
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

/* ============================================
   BASE STYLES
============================================ */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
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
    width: 100%;
}

/* ============================================
   PAGE HEADER
============================================ */
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
    font-size: 2rem;
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
    color: var(--gray-600);
    font-size: 0.875rem;
    margin-bottom: 0;
}

/* ============================================
   STATS CARDS
============================================ */
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

/* ============================================
   FILTER CARD
============================================ */
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

/* ============================================
   ANALYTICS CARDS
============================================ */
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

/* ============================================
   CHART CONTAINERS
============================================ */
.chart-container {
    height: 280px;
    position: relative;
}

.chart-sm {
    height: 220px;
}

/* ============================================
   TABLE STYLES
============================================ */
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

/* ============================================
   BADGE STYLES
============================================ */
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

/* ============================================
   PROGRESS BAR
============================================ */
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

/* ============================================
   METRIC CARDS
============================================ */
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

/* ============================================
   INSIGHT CARDS
============================================ */
.insight-card {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(58, 12, 163, 0.05));
    border: 1px solid rgba(67, 97, 238, 0.2);
    border-radius: var(--border-radius-lg);
    padding: 1.25rem;
    transition: var(--transition);
    height: 100%;
}

.insight-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.insight-card h6 {
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.insight-card p {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-bottom: 0;
    line-height: 1.5;
}

/* ============================================
   BUTTONS
============================================ */
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

/* ============================================
   RESPONSIVE DESIGN
============================================ */

/* Large screens (1200px and above) */
@media (min-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Desktop screens (992px - 1199px) */
@media (max-width: 1199px) and (min-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .chart-container {
        height: 260px;
    }
    
    .chart-sm {
        height: 200px;
    }
}

/* Tablet screens (768px - 991px) */
@media (max-width: 991px) {
    .main-content {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 1.25rem;
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
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
    
    .stat-value {
        font-size: 1.25rem;
    }
    
    .filter-card {
        padding: 1rem;
    }
    
    .filter-card .row > div {
        margin-bottom: 0.75rem;
    }
    
    .btn-filter {
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .analytics-card .card-header {
        flex-direction: column;
        text-align: center;
    }
    
    .analytics-card .card-header .dropdown {
        margin-top: 0.5rem;
    }
    
    .chart-container {
        height: 240px;
    }
    
    .chart-sm {
        height: 200px;
    }
    
    .table-custom {
        font-size: 0.75rem;
    }
    
    .table-custom th,
    .table-custom td {
        padding: 0.5rem;
    }
}

/* Mobile screens (576px - 767px) */
@media (max-width: 767px) {
    .main-content {
        padding: 0.75rem;
    }
    
    .page-header {
        padding: 0.875rem;
    }
    
    .page-header h1 {
        font-size: 1.1rem;
    }
    
    .page-header h1 i {
        font-size: 1.1rem;
    }
    
    .page-header p {
        font-size: 0.7rem;
    }
    
    .page-header .btn {
        padding: 0.4rem 0.75rem;
        font-size: 0.7rem;
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
        font-size: 1.1rem;
    }
    
    .stat-label {
        font-size: 0.6rem;
    }
    
    .stat-trend {
        font-size: 0.55rem;
        padding: 2px 6px;
    }
    
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
    
    .analytics-card {
        margin-bottom: 1rem;
    }
    
    .analytics-card .card-header {
        padding: 0.75rem 1rem;
    }
    
    .analytics-card .card-header h6 {
        font-size: 0.75rem;
    }
    
    .analytics-card .card-body {
        padding: 0.875rem;
    }
    
    .chart-container {
        height: 200px;
    }
    
    .chart-sm {
        height: 180px;
    }
    
    .metric-card {
        padding: 0.75rem;
    }
    
    .metric-value {
        font-size: 1.1rem;
    }
    
    .metric-label {
        font-size: 0.55rem;
    }
    
    .metric-card small {
        font-size: 0.5rem;
    }
    
    .insight-card {
        padding: 0.875rem;
        margin-bottom: 0.75rem;
    }
    
    .insight-card h6 {
        font-size: 0.75rem;
    }
    
    .insight-card p {
        font-size: 0.7rem;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table-custom {
        min-width: 550px;
    }
    
    .table-custom th,
    .table-custom td {
        padding: 0.5rem;
        font-size: 0.65rem;
    }
    
    .badge-category {
        padding: 0.2rem 0.4rem;
        font-size: 0.55rem;
    }
    
    .progress-custom {
        height: 4px;
    }
    
    .row {
        margin-left: -0.5rem;
        margin-right: -0.5rem;
    }
    
    .col-lg-4, .col-lg-6, .col-lg-8 {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        margin-bottom: 0.75rem;
    }
    
    .mb-4 {
        margin-bottom: 0.75rem !important;
    }
    
    .mt-4 {
        margin-top: 0.75rem !important;
    }
}

/* Small mobile screens (up to 575px) */
@media (max-width: 575px) {
    .main-content {
        padding: 0.5rem;
    }
    
    .page-header {
        padding: 0.75rem;
    }
    
    .page-header h1 {
        font-size: 1rem;
    }
    
    .page-header .btn {
        padding: 0.3rem 0.6rem;
        font-size: 0.65rem;
    }
    
    .stat-card {
        padding: 0.75rem;
    }
    
    .stat-icon {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }
    
    .stat-value {
        font-size: 1rem;
    }
    
    .filter-card {
        padding: 0.75rem;
    }
    
    .filter-card .form-control,
    .filter-card .form-select {
        padding: 0.35rem 0.5rem;
        font-size: 0.7rem;
    }
    
    .chart-container {
        height: 180px;
    }
    
    .chart-sm {
        height: 160px;
    }
    
    .metric-card {
        padding: 0.6rem;
    }
    
    .metric-value {
        font-size: 1rem;
    }
    
    .metric-label {
        font-size: 0.5rem;
    }
    
    .insight-card {
        padding: 0.75rem;
    }
    
    .insight-card h6 {
        font-size: 0.7rem;
    }
    
    .insight-card p {
        font-size: 0.65rem;
    }
    
    .table-custom {
        min-width: 480px;
    }
    
    .table-custom th,
    .table-custom td {
        padding: 0.4rem;
        font-size: 0.6rem;
    }
    
    .btn {
        padding: 0.35rem 0.7rem;
        font-size: 0.7rem;
    }
}

/* Landscape orientation for mobile */
@media (max-width: 767px) and (orientation: landscape) {
    .main-content {
        padding: 0.75rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .chart-container {
        height: 180px;
    }
    
    .chart-sm {
        height: 150px;
    }
    
    .analytics-card .card-header {
        flex-direction: row;
        justify-content: space-between;
    }
}

/* ============================================
   PRINT STYLES
============================================ */
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
    .dropdown,
    .back-to-top,
    .sidebar {
        display: none !important;
    }
    
    .stat-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    .analytics-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    .page-header {
        background: none;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .chart-container,
    .chart-sm {
        height: auto;
    }
    
    canvas {
        max-width: 100%;
        height: auto !important;
    }
}

/* ============================================
   CUSTOM SCROLLBAR
============================================ */
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

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

/* ============================================
   UTILITY CLASSES
============================================ */
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
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1>
                        <i class="fas fa-chart-line"></i>
                        Analytics Dashboard
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php echo date('F d, Y', strtotime($start_date)); ?> - <?php echo date('F d, Y', strtotime($end_date)); ?>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="analytics-dashboard.php" class="btn btn-primary">
                        <i class="fa-solid fa-magnifying-glass-chart me-2"></i> Site Analytics
                    </a>
                    <a href="../dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i> Back
                    </a>
                    <button class="btn btn-outline-primary" onclick="refreshAnalytics()">
                        <i class="fas fa-sync-alt me-2"></i> Refresh
                    </button>
                    <button class="btn btn-primary" onclick="exportAnalytics()">
                        <i class="fas fa-download me-2"></i> Export
                    </button>
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
                
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">
                        <i class="fas fa-chart-line"></i> Time Period
                    </label>
                    <select class="form-select" name="time_period">
                        <option value="daily" <?php echo $time_period == 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo $time_period == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $time_period == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo $time_period == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                    </select>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <button type="submit" class="btn-filter w-100">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Overview Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                    <i class="fas fa-dollar-sign text-primary"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo formatCurrency($revenue_data['current_revenue'] ?? 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-trend mt-1">
                        <?php echo getTrendArrow($revenue_data['current_revenue'] ?? 0, $revenue_data['previous_revenue'] ?? 0); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                    <i class="fas fa-shopping-cart text-success"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo formatNumber($orders_data['current_orders'] ?? 0); ?></div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-trend mt-1">
                        <?php echo getTrendArrow($orders_data['current_orders'] ?? 0, $orders_data['previous_orders'] ?? 0); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                    <i class="fas fa-chart-line text-warning"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo formatCurrency($aov_data['current_aov'] ?? 0); ?></div>
                    <div class="stat-label">Avg. Order Value</div>
                    <div class="stat-trend mt-1">
                        <?php echo getTrendArrow($aov_data['current_aov'] ?? 0, $aov_data['previous_aov'] ?? 0); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                    <i class="fas fa-users text-info"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo formatNumber($customers_data['current_customers'] ?? 0); ?></div>
                    <div class="stat-label">New Customers</div>
                    <div class="stat-trend mt-1">
                        <?php echo getTrendArrow($customers_data['current_customers'] ?? 0, $customers_data['previous_customers'] ?? 0); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="analytics-card" style="animation-delay: 0.3s;">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-line"></i>
                            Revenue Trend (12 Months)
                        </h6>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
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
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="analytics-card" style="animation-delay: 0.35s;">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-pie"></i>
                            Customer Segmentation
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-sm">
                            <canvas id="segmentationChart"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php foreach($customer_segments as $segment): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo $segment['segment']; ?></span>
                                <span class="fw-bold"><?php echo formatNumber($segment['customer_count']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Performance Row -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="analytics-card" style="animation-delay: 0.4s;">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-box"></i>
                            Top Performing Products
                        </h6>
                        <span class="badge bg-primary">Top 10</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Units Sold</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                    </thead>
                                <tbody>
                                    <?php foreach($top_products as $product): ?>
                                    <tr>
                                        <td class="fw-medium"><?php echo htmlspecialchars(substr($product['name'], 0, 30)) . (strlen($product['name']) > 30 ? '...' : ''); ?></td>
                                        <td><span class="badge-category"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></span></td>
                                        <td><?php echo formatNumber($product['units_sold']); ?></td>
                                        <td><?php echo formatNumber($product['order_count']); ?></td>
                                        <td class="text-success fw-bold"><?php echo formatCurrency($product['revenue']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="analytics-card" style="animation-delay: 0.45s;">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-chart-pie"></i>
                            Category Performance
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-sm">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="mt-4">
                            <?php 
                            $total_category_revenue = array_sum(array_column($category_performance, 'revenue'));
                            foreach($category_performance as $category): 
                                $percentage = $total_category_revenue > 0 ? ($category['revenue'] / $total_category_revenue) * 100 : 0;
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="flex-grow-1 me-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="small"><?php echo htmlspecialchars($category['category']); ?></span>
                                        <span class="small text-muted"><?php echo round($percentage, 1); ?>%</span>
                                    </div>
                                    <div class="progress-custom mt-1">
                                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                                <span class="fw-bold small"><?php echo formatCurrency($category['revenue']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Performance & Conversion Metrics -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="analytics-card" style="animation-delay: 0.5s;">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-calendar-day"></i>
                            Daily Performance
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 280px;">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="analytics-card" style="animation-delay: 0.55s;">
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
                                    <div class="metric-value"><?php echo number_format($cart_abandonment_rate, 1); ?>%</div>
                                    <div class="metric-label">Cart Abandonment Rate</div>
                                    <small class="text-muted">Lower is better</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card">
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
                                    <div class="metric-value"><?php echo number_format($repeat_rate, 1); ?>%</div>
                                    <div class="metric-label">Repeat Purchase Rate</div>
                                    <small class="text-muted">Higher is better</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card">
                                    <?php
                                    $total_revenue = $revenue_data['current_revenue'] ?? 0;
                                    $total_customers = $customers_data['current_customers'] ?? 0;
                                    $avg_customer_value = $total_customers > 0 ? $total_revenue / $total_customers : 0;
                                    ?>
                                    <div class="metric-value"><?php echo formatCurrency($avg_customer_value); ?></div>
                                    <div class="metric-label">Avg. Customer Value</div>
                                    <small class="text-muted">Lifetime value</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card">
                                    <?php
                                    $total_orders = $orders_data['current_orders'] ?? 0;
                                    $orders_per_customer = $total_customers > 0 ? $total_orders / $total_customers : 0;
                                    ?>
                                    <div class="metric-value"><?php echo number_format($orders_per_customer, 1); ?></div>
                                    <div class="metric-label">Orders per Customer</div>
                                    <small class="text-muted">Frequency</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Geographic & Acquisition -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="analytics-card" style="animation-delay: 0.6s;">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-globe"></i>
                            Geographic Distribution
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($geographic_data)): ?>
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
                                    <?php foreach($geographic_data as $geo): ?>
                                    <tr>
                                        <td class="fw-medium"><?php echo htmlspecialchars($geo['country']); ?></td>
                                        <td><?php echo htmlspecialchars($geo['city']); ?></td>
                                        <td><?php echo formatNumber($geo['customer_count']); ?></td>
                                        <td><?php echo formatNumber($geo['order_count']); ?></td>
                                        <td class="text-success fw-bold"><?php echo formatCurrency($geo['revenue']); ?></td>
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
                <div class="analytics-card" style="animation-delay: 0.65s;">
                    <div class="card-header">
                        <h6>
                            <i class="fas fa-user-plus"></i>
                            Customer Acquisition
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($acquisition_data)): ?>
                        <div class="chart-sm mb-4">
                            <canvas id="acquisitionChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <?php foreach($acquisition_data as $source): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo htmlspecialchars($source['source']); ?></span>
                                <span class="fw-bold"><?php echo formatNumber($source['customer_count']); ?> customers</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No acquisition data available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Insights & Recommendations -->
        <div class="analytics-card" style="animation-delay: 0.7s;">
            <div class="card-header">
                <h6>
                    <i class="fas fa-lightbulb"></i>
                    Insights & Recommendations
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="insight-card">
                            <h6 class="text-success">
                                <i class="fas fa-lightbulb"></i>
                                Top Insight
                            </h6>
                            <p>
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
                    
                    <div class="col-md-4 mb-3">
                        <div class="insight-card">
                            <h6 class="text-warning">
                                <i class="fas fa-exclamation-circle"></i>
                                Area for Improvement
                            </h6>
                            <p>
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
                    
                    <div class="col-md-4 mb-3">
                        <div class="insight-card">
                            <h6 class="text-info">
                                <i class="fas fa-chart-line"></i>
                                Growth Opportunity
                            </h6>
                            <p>
                                <?php
                                $repeat_rate = ($repeat_customers / max($total_customers, 1)) * 100;
                                if ($repeat_rate < 20) {
                                    echo "Low repeat purchase rate (" . number_format($repeat_rate, 1) . "%). 
                                          Focus on customer retention strategies and loyalty programs.";
                                } else {
                                    echo "Good customer retention. Consider premium loyalty programs to increase average order value.";
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let revenueChart, segmentationChart, categoryChart, dailyChart, acquisitionChart;

// Revenue Trend Chart
function renderRevenueChart() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    if (revenueChart) revenueChart.destroy();
    
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
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderColor: '#4361ee',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: '#4361ee',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 6
            }, {
                label: 'Orders',
                data: [<?php 
                    $orders = [];
                    foreach($revenue_trend as $data) {
                        $orders[] = $data['orders'];
                    }
                    echo implode(', ', $orders);
                ?>],
                backgroundColor: 'rgba(6, 214, 160, 0.1)',
                borderColor: '#06d6a0',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: '#06d6a0',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 6,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 10 } },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString() } },
                y1: { position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });
}

// Customer Segmentation Chart
function renderSegmentationChart() {
    const ctx = document.getElementById('segmentationChart').getContext('2d');
    if (segmentationChart) segmentationChart.destroy();
    
    segmentationChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($customer_segments as $s) echo "'" . $s['segment'] . "',"; ?>],
            datasets: [{
                data: [<?php foreach($customer_segments as $s) echo $s['customer_count'] . ','; ?>],
                backgroundColor: ['#4361ee', '#06d6a0', '#4cc9f0'],
                borderWidth: 0
            }]
        },
        options: { maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
    });
}

// Category Chart
function renderCategoryChart() {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    if (categoryChart) categoryChart.destroy();
    
    categoryChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: [<?php foreach($category_performance as $c) echo "'" . addslashes($c['category']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach($category_performance as $c) echo $c['revenue'] . ','; ?>],
                backgroundColor: ['#4361ee', '#06d6a0', '#ffb703', '#ef476f', '#4cc9f0', '#7209b7', '#f8961e', '#90be6d']
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
}

// Daily Chart
function renderDailyChart() {
    const ctx = document.getElementById('dailyChart').getContext('2d');
    if (dailyChart) dailyChart.destroy();
    
    dailyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php foreach($daily_trend as $d) echo "'" . date('M d', strtotime($d['date'])) . "',"; ?>],
            datasets: [{
                label: 'Revenue',
                data: [<?php foreach($daily_trend as $d) echo $d['revenue'] . ','; ?>],
                backgroundColor: '#4361ee',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { tooltip: { callbacks: { label: ctx => 'Revenue: $' + ctx.raw.toLocaleString() } } },
            scales: { y: { ticks: { callback: v => '$' + v.toLocaleString() } } }
        }
    });
}

// Acquisition Chart
function renderAcquisitionChart() {
    const ctx = document.getElementById('acquisitionChart').getContext('2d');
    if (acquisitionChart) acquisitionChart.destroy();
    
    <?php if (!empty($acquisition_data)): ?>
    acquisitionChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($acquisition_data as $a) echo "'" . $a['source'] . "',"; ?>],
            datasets: [{
                data: [<?php foreach($acquisition_data as $a) echo $a['customer_count'] . ','; ?>],
                backgroundColor: ['#4361ee', '#06d6a0']
            }]
        },
        options: { maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
    });
    <?php endif; ?>
}

function changeChartType(chartName, type) {
    console.log('Changing ' + chartName + ' to ' + type);
}

function refreshAnalytics() { location.reload(); }

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
        const params = new URLSearchParams(window.location.search);
        if (result.isConfirmed) {
            params.append('format', 'pdf');
            window.open(`export-analytics.php?${params.toString()}`, '_blank');
        } else if (result.isDenied) {
            params.append('format', 'excel');
            window.open(`export-analytics.php?${params.toString()}`, '_blank');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    renderRevenueChart();
    renderSegmentationChart();
    renderCategoryChart();
    renderDailyChart();
    renderAcquisitionChart();
});
</script>

<?php require_once '../includes/footer.php'; ?>