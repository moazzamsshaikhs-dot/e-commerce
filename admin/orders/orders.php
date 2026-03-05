<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Orders Management';
require_once '../includes/header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filter variables
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_payment = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$customer_filter = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : '';

try {
    $db = getDB();

    // Build WHERE clause
    $where = ["1=1"];
    $params = [];

    if (!empty($filter_status)) {
        $where[] = "o.status = ?";
        $params[] = $filter_status;
    }

    if (!empty($filter_payment)) {
        $where[] = "o.payment_status = ?";
        $params[] = $filter_payment;
    }

    if (!empty($start_date)) {
        $where[] = "DATE(o.order_date) >= ?";
        $params[] = $start_date;
    }

    if (!empty($end_date)) {
        $where[] = "DATE(o.order_date) <= ?";
        $params[] = $end_date;
    }

    if (!empty($customer_filter)) {
        $where[] = "o.user_id = ?";
        $params[] = $customer_filter;
    }

    if (!empty($search)) {
        $where[] = "(o.order_number LIKE ? OR o.id LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $where_sql = implode(' AND ', $where);

    // Get total orders count
    $count_sql = "SELECT COUNT(DISTINCT o.id) as total 
                  FROM orders o
                  LEFT JOIN users u ON o.user_id = u.id
                  WHERE $where_sql";

    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_orders = $result['total'] ?? 0;
    $total_pages = ceil($total_orders / $limit);

    // Get orders with details
    $orders_sql = "SELECT o.*, 
                          u.full_name,
                          u.email,
                          u.phone,
                          u.profile_pic,
                          COUNT(oi.id) as items_count,
                          SUM(oi.quantity) as total_items,
                          sc.name as carrier_name
                   FROM orders o
                   LEFT JOIN users u ON o.user_id = u.id
                   LEFT JOIN order_items oi ON o.id = oi.order_id
                   LEFT JOIN shipping_carriers sc ON o.shipping_carrier_id = sc.id
                   WHERE $where_sql
                   GROUP BY o.id
                   ORDER BY o.order_date DESC
                   LIMIT ? OFFSET ?";

    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($orders_sql);
    $stmt->execute($all_params);
    $orders = $stmt->fetchAll();

    // Get statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(total_amount) as total_sales,
                    AVG(total_amount) as avg_order_value
                  FROM orders
                  WHERE DATE(order_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

    $stmt = $db->query($stats_sql);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get order statuses
    $stmt = $db->query("SELECT DISTINCT status FROM orders WHERE status != '' ORDER BY status");
    $order_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get payment statuses
    $stmt = $db->query("SELECT DISTINCT payment_status FROM orders WHERE payment_status != '' ORDER BY payment_status");
    $payment_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get customers for filter
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE user_type = 'user' ORDER BY full_name");
    $customers = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Orders error: " . $e->getMessage());
    $error = 'Error loading orders: ' . $e->getMessage();
    $orders = [];
    $total_orders = 0;
    $total_pages = 1;
    $stats = [
        'total_orders' => 0,
        'pending_orders' => 0,
        'processing_orders' => 0,
        'shipped_orders' => 0,
        'delivered_orders' => 0,
        'cancelled_orders' => 0,
        'total_sales' => 0,
        'avg_order_value' => 0
    ];
    $order_statuses = [];
    $payment_statuses = [];
    $customers = [];
}
?>

<style>
/* Your Root Colors */
:root {
    --primary: #4361ee;
    --primary-dark: #3651c4;
    --primary-light: rgba(67, 97, 238, 0.1);
    --success: #06d6a0;
    --success-dark: #05b585;
    --success-light: rgba(6, 214, 160, 0.1);
    --warning: #ffb703;
    --warning-dark: #e6a500;
    --warning-light: rgba(255, 183, 3, 0.1);
    --danger: #ef476f;
    --danger-dark: #d64161;
    --danger-light: rgba(239, 71, 111, 0.1);
    --info: #4cc9f0;
    --info-dark: #3aa9d9;
    --info-light: rgba(76, 201, 240, 0.1);
    --dark: #2b2d42;
    --dark-light: rgba(43, 45, 66, 0.1);
    --light: #f8f9fa;
    --border: #e9ecef;
    --shadow: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.1);
    --shadow-glow: 0 0 20px rgba(67, 97, 238, 0.3);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

/* Animations */
@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInLeft {
    from {
        transform: translateX(-30px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes pulse-glow {
    0% { box-shadow: 0 0 0 0 var(--primary); }
    70% { box-shadow: 0 0 0 10px rgba(67, 97, 238, 0); }
    100% { box-shadow: 0 0 0 0 rgba(67, 97, 238, 0); }
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

/* Main Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--light);
}

.main-content {
    flex: 1;
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    overflow-y: auto;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.6s ease-out;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%);
    border-radius: 50%;
    z-index: 0;
}

.page-header > div {
    position: relative;
    z-index: 1;
}

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border-left: 4px solid transparent;
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.5s ease-out;
    animation-fill-mode: both;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.15s; }
.stat-card:nth-child(3) { animation-delay: 0.2s; }
.stat-card:nth-child(4) { animation-delay: 0.25s; }
.stat-card:nth-child(5) { animation-delay: 0.3s; }
.stat-card:nth-child(6) { animation-delay: 0.35s; }

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: translateX(-100%);
    animation: shimmer 2s infinite;
    pointer-events: none;
}

.stat-card.total { border-left-color: var(--primary); }
.stat-card.pending { border-left-color: var(--warning); }
.stat-card.processing { border-left-color: var(--info); }
.stat-card.shipped { border-left-color: var(--primary); }
.stat-card.delivered { border-left-color: var(--success); }
.stat-card.cancelled { border-left-color: var(--danger); }

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 10px;
}

.stat-card.total .stat-icon { background: var(--primary-light); color: var(--primary); }
.stat-card.pending .stat-icon { background: var(--warning-light); color: var(--warning); }
.stat-card.processing .stat-icon { background: var(--info-light); color: var(--info); }
.stat-card.shipped .stat-icon { background: var(--primary-light); color: var(--primary); }
.stat-card.delivered .stat-icon { background: var(--success-light); color: var(--success); }
.stat-card.cancelled .stat-icon { background: var(--danger-light); color: var(--danger); }

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-label {
    color: var(--dark);
    opacity: 0.7;
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-trend {
    font-size: 11px;
    margin-top: 5px;
    color: var(--dark);
    opacity: 0.6;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: var(--shadow);
    animation: slideInUp 0.5s ease-out 0.4s both;
}

.filter-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-title i {
    color: var(--primary);
}

.form-control, .form-select {
    border-radius: var(--radius);
    border: 2px solid var(--border);
    padding: 10px 15px;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
    outline: none;
}

.btn-filter {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-filter:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
}

.btn-clear {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
    padding: 10px 20px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-clear:hover {
    background: var(--border);
    transform: translateY(-2px);
}

/* Orders Card */
.orders-card {
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: slideInUp 0.6s ease-out 0.5s both;
}

.card-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    background: var(--light);
}

.card-header h5 {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Table Styles */
.table-responsive {
    padding: 0 25px 25px 25px;
    overflow-x: auto;
}

.orders-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.orders-table th {
    padding: 12px 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--dark);
    opacity: 0.7;
    border-bottom: 2px solid var(--border);
}

.orders-table td {
    padding: 15px;
    background: var(--light);
    border-radius: var(--radius);
    transition: var(--transition);
    font-size: 0.875rem;
    vertical-align: middle;
}

.orders-table tr {
    transition: var(--transition);
}

.orders-table tr:hover td {
    background: white;
    box-shadow: var(--shadow);
    transform: scale(1.01);
}

/* Order Info */
.order-info {
    display: flex;
    flex-direction: column;
}

.order-number {
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
    transition: var(--transition);
}

.order-number:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.order-id {
    font-size: 0.7rem;
    color: var(--dark);
    opacity: 0.6;
}

/* Customer Info */
.customer-info {
    display: flex;
    flex-direction: column;
}

.customer-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 2px;
}

.customer-email {
    font-size: 0.7rem;
    color: var(--dark);
    opacity: 0.6;
}

/* Date Info */
.date-info {
    display: flex;
    flex-direction: column;
}

.date-day {
    font-weight: 500;
    color: var(--dark);
}

.date-time {
    font-size: 0.7rem;
    color: var(--dark);
    opacity: 0.6;
}

/* Amount Info */
.amount-info {
    display: flex;
    flex-direction: column;
}

.amount-total {
    font-weight: 700;
    color: var(--dark);
}

.amount-meta {
    font-size: 0.7rem;
    color: var(--dark);
    opacity: 0.6;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-pending {
    background: var(--warning-light);
    color: var(--warning-dark);
    border: 1px solid var(--warning);
    animation: pulse-glow 2s infinite;
}

.status-processing {
    background: var(--info-light);
    color: var(--info-dark);
    border: 1px solid var(--info);
}

.status-shipped {
    background: var(--primary-light);
    color: var(--primary-dark);
    border: 1px solid var(--primary);
}

.status-delivered {
    background: var(--success-light);
    color: var(--success-dark);
    border: 1px solid var(--success);
}

.status-cancelled {
    background: var(--danger-light);
    color: var(--danger-dark);
    border: 1px solid var(--danger);
}

.priority-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 600;
    margin-left: 4px;
}

.priority-high {
    background: var(--danger-light);
    color: var(--danger-dark);
    border: 1px solid var(--danger);
}

.priority-urgent {
    background: var(--danger);
    color: white;
    animation: pulse-glow 1s infinite;
}

/* Action Buttons */
.action-group {
    display: flex;
    gap: 5px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    color: white;
    text-decoration: none;
}

.btn-view {
    background: var(--info);
}

.btn-view:hover {
    background: var(--info-dark);
    transform: translateY(-2px);
}

.btn-edit {
    background: var(--primary);
}

.btn-edit:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-dropdown {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
}

.btn-dropdown:hover {
    background: var(--border);
    transform: translateY(-2px);
}

.dropdown-menu {
    border: none;
    box-shadow: var(--shadow-hover);
    border-radius: var(--radius);
    padding: 5px;
}

.dropdown-item {
    padding: 8px 15px;
    border-radius: var(--radius-sm);
    transition: var(--transition);
    font-size: 0.875rem;
}

.dropdown-item:hover {
    background: var(--primary-light);
    color: var(--primary);
}

.dropdown-item.text-danger:hover {
    background: var(--danger-light);
    color: var(--danger);
}

/* Bulk Actions */
.bulk-actions {
    background: white;
    border-top: 1px solid var(--border);
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.bulk-select {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.bulk-select select {
    width: auto;
    display: inline-block;
}

.btn-bulk {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
}

.btn-bulk:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

/* Pagination */
.pagination {
    gap: 5px;
}

.page-item .page-link {
    border: none;
    border-radius: var(--radius-sm);
    color: var(--dark);
    transition: var(--transition);
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.page-item.active .page-link {
    background: var(--primary);
    color: white;
}

.page-item .page-link:hover {
    background: var(--primary-light);
    color: var(--primary);
    transform: translateY(-2px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: var(--dark);
    opacity: 0.2;
    margin-bottom: 20px;
}

.empty-state h5 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--dark);
    opacity: 0.7;
    max-width: 300px;
    margin: 0 auto;
}

/* Checkbox */
.order-checkbox, #selectAll {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

/* Responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 20px;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-card .row {
        flex-direction: column;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .bulk-select {
        flex-direction: column;
        align-items: stretch;
    }
    
    .bulk-select select {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard-container">
    

    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-shopping-cart me-2" style="color: var(--primary);"></i>
                        Orders Management
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>
                        Total <?php echo number_format($total_orders); ?> orders • 
                        Last 30 days: $<?php echo number_format($stats['total_sales'] ?? 0, 2); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="export-orders.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-export me-2"></i> Export
                    </a>
                    <a href="../dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i> Dashboard
                    </a>
                    <a href="create-order.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i> Create Order
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Orders</div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up text-success"></i> Lifetime
                </div>
            </div>

            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $stats['pending_orders'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
                <div class="stat-trend">
                    <i class="fas fa-hourglass-half"></i> Awaiting processing
                </div>
            </div>

            <div class="stat-card processing">
                <div class="stat-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="stat-value"><?php echo $stats['processing_orders'] ?? 0; ?></div>
                <div class="stat-label">Processing</div>
                <div class="stat-trend">
                    <i class="fas fa-sync-alt fa-spin"></i> In progress
                </div>
            </div>

            <div class="stat-card shipped">
                <div class="stat-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="stat-value"><?php echo $stats['shipped_orders'] ?? 0; ?></div>
                <div class="stat-label">Shipped</div>
                <div class="stat-trend">
                    <i class="fas fa-truck"></i> On the way
                </div>
            </div>

            <div class="stat-card delivered">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['delivered_orders'] ?? 0; ?></div>
                <div class="stat-label">Delivered</div>
                <div class="stat-trend">
                    <i class="fas fa-check-double"></i> Completed
                </div>
            </div>

            <div class="stat-card cancelled">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['cancelled_orders'] ?? 0; ?></div>
                <div class="stat-label">Cancelled</div>
                <div class="stat-trend">
                    <i class="fas fa-exclamation-triangle"></i> Failed
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="filter-card">
            <div class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Orders
            </div>
            
            <form method="GET" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by Order #, Name, Email, Phone"
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <?php foreach ($order_statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $filter_status == $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="payment_status" class="form-select">
                            <option value="">All Payment</option>
                            <?php foreach ($payment_statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $filter_payment == $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="start_date" class="form-control"
                            value="<?php echo htmlspecialchars($start_date); ?>" placeholder="Start Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="end_date" class="form-control"
                            value="<?php echo htmlspecialchars($end_date); ?>" placeholder="End Date">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn-filter w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Advanced Filters -->
                <div class="row mt-3">
                    <div class="col-md-4">
                        <select name="customer_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Customers</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo $customer_filter == $customer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['full_name'] . ' (' . $customer['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 text-end">
                        <a href="orders.php" class="btn-clear">
                            <i class="fas fa-redo me-2"></i> Clear All Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table Card -->
        <div class="orders-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list-ul me-2" style="color: var(--primary);"></i>
                    Orders List
                    <span class="badge bg-primary ms-2"><?php echo $total_orders; ?></span>
                </h5>
                <span class="text-muted small">Showing <?php echo count($orders); ?> of <?php echo $total_orders; ?> orders</span>
            </div>

            <div class="table-responsive">
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h5>No Orders Found</h5>
                        <p>No orders match your search criteria. Try adjusting your filters.</p>
                    </div>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order):
                                // Status badge class
                                $status_class = 'status-' . $order['status'];
                                $status_icon = match($order['status']) {
                                    'pending' => 'clock',
                                    'processing' => 'cogs',
                                    'shipped' => 'shipping-fast',
                                    'delivered' => 'check-circle',
                                    'cancelled' => 'times-circle',
                                    default => 'clock'
                                };
                                
                                // Payment status class
                                $payment_class = match($order['payment_status']) {
                                    'completed' => 'success',
                                    'pending' => 'warning',
                                    'failed' => 'danger',
                                    'refunded' => 'info',
                                    default => 'secondary'
                                };

                                // Format date
                                $order_date = date('d M Y', strtotime($order['order_date']));
                                $order_time = date('h:i A', strtotime($order['order_date']));
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="order-checkbox" value="<?php echo $order['id']; ?>">
                                </td>
                                <td>
                                    <div class="order-info">
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="order-number">
                                            <?php echo $order['order_number'] ?? '#' . $order['id']; ?>
                                        </a>
                                        <span class="order-id">ID: #<?php echo $order['id']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="customer-info">
                                        <span class="customer-name"><?php echo htmlspecialchars($order['full_name'] ?? 'Guest'); ?></span>
                                        <span class="customer-email"><?php echo htmlspecialchars($order['email'] ?? ''); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-info">
                                        <span class="date-day"><?php echo $order_date; ?></span>
                                        <span class="date-time"><?php echo $order_time; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <span class="fw-bold"><?php echo $order['total_items'] ?? $order['items_count'] ?? 0; ?></span>
                                        <span class="text-muted d-block small">items</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="amount-info">
                                        <span class="amount-total">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                        <?php if (!empty($order['carrier_name'])): ?>
                                            <span class="amount-meta">via <?php echo $order['carrier_name']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas fa-<?php echo $status_icon; ?>"></i>
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                    <?php if ($order['priority'] == 'high'): ?>
                                        <span class="priority-badge priority-high">High</span>
                                    <?php elseif ($order['priority'] == 'urgent'): ?>
                                        <span class="priority-badge priority-urgent">Urgent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $payment_class; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                           class="btn-icon btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <div class="dropdown">
                                            <button class="btn-icon btn-dropdown" type="button" 
                                                    data-bs-toggle="dropdown" title="More Actions">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="edit-order.php?id=<?php echo $order['id']; ?>">
                                                        <i class="fas fa-edit me-2"></i> Edit Order
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="invoice.php?id=<?php echo $order['id']; ?>" target="_blank">
                                                        <i class="fas fa-file-invoice me-2"></i> Print Invoice
                                                    </a>
                                                </li>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0)"
                                                           onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'processing')">
                                                            <i class="fas fa-cogs me-2"></i> Mark Processing
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0)"
                                                           onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'shipped')">
                                                            <i class="fas fa-shipping-fast me-2"></i> Mark Shipped
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0)"
                                                           onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'delivered')">
                                                            <i class="fas fa-check-circle me-2"></i> Mark Delivered
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="javascript:void(0)"
                                                       onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-times-circle me-2"></i> Cancel Order
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Bulk Actions & Pagination -->
            <div class="bulk-actions">
                <div class="bulk-select">
                    <select class="form-select form-select-sm" id="bulkAction">
                        <option value="">Bulk Actions</option>
                        <option value="processing">Mark as Processing</option>
                        <option value="shipped">Mark as Shipped</option>
                        <option value="delivered">Mark as Delivered</option>
                        <option value="cancelled">Cancel Orders</option>
                        <option value="export">Export Selected</option>
                        <option value="delete">Delete Orders</option>
                    </select>
                    <button class="btn-bulk" onclick="applyBulkAction()">
                        <i class="fas fa-check-double me-2"></i> Apply
                    </button>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Hidden form for bulk actions -->
<form id="bulkForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="bulkActionInput">
    <input type="hidden" name="order_ids" id="bulkOrderIds">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Select all checkbox
document.getElementById('selectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.order-checkbox').forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Update order status
function updateOrderStatus(orderId, status) {
    Swal.fire({
        title: 'Update Order Status',
        text: `Are you sure you want to mark this order as ${status}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'var(--success)',
        cancelButtonColor: 'var(--danger)',
        confirmButtonText: 'Yes, update it!',
        background: 'white',
        backdrop: `
            rgba(67,97,238,0.2)
            left top
            no-repeat
        `
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Processing...',
                html: 'Please wait while we update the order',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('ajax/update-order-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        showConfirmButton: true,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while updating the order.'
                });
            });
        }
    });
}

// Cancel order
function cancelOrder(orderId) {
    Swal.fire({
        title: 'Cancel Order',
        text: 'Please provide a reason for cancellation:',
        icon: 'warning',
        input: 'textarea',
        inputPlaceholder: 'Enter cancellation reason...',
        inputAttributes: {
            'aria-label': 'Enter cancellation reason'
        },
        showCancelButton: true,
        confirmButtonColor: 'var(--danger)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: 'Yes, cancel it!',
        background: 'white',
        showLoaderOnConfirm: true,
        preConfirm: (reason) => {
            if (!reason) {
                Swal.showValidationMessage('Please enter a reason');
                return false;
            }
            
            return fetch('ajax/cancel-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Cancelled!',
                text: result.value.message,
                timer: 2000
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Apply bulk action
function applyBulkAction() {
    const selectedOrders = [];
    document.querySelectorAll('.order-checkbox:checked').forEach(checkbox => {
        selectedOrders.push(checkbox.value);
    });

    if (selectedOrders.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Warning!',
            text: 'Please select at least one order.'
        });
        return;
    }

    const action = document.getElementById('bulkAction').value;
    if (!action) {
        Swal.fire({
            icon: 'warning',
            title: 'Warning!',
            text: 'Please select an action.'
        });
        return;
    }

    if (action === 'export') {
        // Export selected orders
        const orderIds = selectedOrders.join(',');
        window.open(`ajax/export-orders.php?ids=${orderIds}`, '_blank');
        return;
    }

    let confirmText = '';
    let confirmIcon = 'question';

    switch (action) {
        case 'processing':
            confirmText = `Mark ${selectedOrders.length} order(s) as Processing?`;
            break;
        case 'shipped':
            confirmText = `Mark ${selectedOrders.length} order(s) as Shipped?`;
            break;
        case 'delivered':
            confirmText = `Mark ${selectedOrders.length} order(s) as Delivered?`;
            break;
        case 'cancelled':
            confirmText = `Cancel ${selectedOrders.length} order(s)?`;
            confirmIcon = 'warning';
            break;
        case 'delete':
            confirmText = `Delete ${selectedOrders.length} order(s) permanently? This cannot be undone!`;
            confirmIcon = 'error';
            break;
    }

    Swal.fire({
        title: 'Confirm Bulk Action',
        text: confirmText,
        icon: confirmIcon,
        showCancelButton: true,
        confirmButtonColor: action === 'delete' ? 'var(--danger)' : 'var(--success)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: 'Yes, proceed!',
        background: 'white'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Processing...',
                text: 'Please wait while we process your request',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            document.getElementById('bulkActionInput').value = action;
            document.getElementById('bulkOrderIds').value = selectedOrders.join(',');
            
            fetch('ajax/bulk-order-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_ids: selectedOrders,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred.'
                });
            });
        }
    });
}

// Auto-refresh stats every 30 seconds (optional)
setInterval(function() {
    fetch('ajax/get-order-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update stats cards
                document.querySelectorAll('.stat-value').forEach((el, index) => {
                    // Update logic here
                });
            }
        })
        .catch(error => console.error('Error refreshing stats:', error));
}, 30000);
</script>

<?php require_once '../includes/footer.php'; ?>