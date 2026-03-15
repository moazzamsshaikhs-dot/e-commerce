<?php
// admin/dashboard.php
require_once './includes/config.php';
require_once './includes/auth-check.php';

if($_SESSION['user_type'] != 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}
    
$page_title = 'Admin Dashboard';
require_once './includes/header.php';

// Get dashboard statistics
try {
    $db = getDB();
    
    // Total Users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    
    // Active Users
    $stmt = $db->query("SELECT COUNT(*) as active FROM users WHERE account_status = 'active'");
    $active_users = $stmt->fetch()['active'];
    
    // New Users Today
    $stmt = $db->query("SELECT COUNT(*) as new_today FROM users WHERE DATE(created_at) = CURDATE()");
    $new_users_today = $stmt->fetch()['new_today'];
    
    // Vendor Stats
    $stmt = $db->query("SELECT COUNT(*) as vendors FROM users WHERE user_type = 'vendor'");
    $total_vendors = $stmt->fetch()['vendors'];
    
    $stmt = $db->query("SELECT COUNT(*) as pending_vendors FROM users WHERE user_type = 'vendor' AND vendor_status = 'pending'");
    $pending_vendors = $stmt->fetch()['pending_vendors'];
    
    // Total Products
    $stmt = $db->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];
    
    // Products by status
    $stmt = $db->query("SELECT COUNT(*) as pending FROM products WHERE approved_status = 'pending'");
    $pending_products = $stmt->fetch()['pending'];
    
    // Total Orders
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];
    
    // Orders by status
    $stmt = $db->query("SELECT COUNT(*) as pending FROM orders WHERE status = 'pending'");
    $pending_orders = $stmt->fetch()['pending'];
    
    $stmt = $db->query("SELECT COUNT(*) as processing FROM orders WHERE status = 'processing'");
    $processing_orders = $stmt->fetch()['processing'];
    
    $stmt = $db->query("SELECT COUNT(*) as delivered FROM orders WHERE status = 'delivered'");
    $delivered_orders = $stmt->fetch()['delivered'];
    
    // Total Revenue
    $stmt = $db->query("SELECT SUM(total_amount) as revenue FROM orders WHERE payment_status = 'completed'");
    $total_revenue = $stmt->fetch()['revenue'] ?? 0;
    
    // Today's Revenue
    $stmt = $db->query("SELECT SUM(total_amount) as today_revenue FROM orders WHERE DATE(order_date) = CURDATE() AND payment_status = 'completed'");
    $today_revenue = $stmt->fetch()['today_revenue'] ?? 0;
    
    // Monthly Revenue (current month)
    $stmt = $db->query("SELECT SUM(total_amount) as monthly_revenue FROM orders WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE()) AND payment_status = 'completed'");
    $monthly_revenue = $stmt->fetch()['monthly_revenue'] ?? 0;
    
    // Withdrawal Stats
    $stmt = $db->query("SELECT COUNT(*) as pending_withdrawals FROM vendor_withdrawals WHERE status = 'pending'");
    $pending_withdrawals = $stmt->fetch()['pending_withdrawals'];
    
    $stmt = $db->query("SELECT SUM(withdrawal_amount) as pending_amount FROM vendor_withdrawals WHERE status = 'pending'");
    $pending_withdrawal_amount = $stmt->fetch()['pending_amount'] ?? 0;
    
    // Recent Users (last 7)
    $stmt = $db->prepare("
        SELECT id, username, full_name, email, user_type, created_at, account_status 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 7
    ");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();
    
    // Recent Orders (last 7)
    $stmt = $db->prepare("
        SELECT o.*, u.username, u.full_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.order_date DESC 
        LIMIT 7
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
    
    // Top Selling Products
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, p.sales_count, 
               COUNT(oi.id) as order_count,
               SUM(oi.quantity) as total_sold
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_products = $stmt->fetchAll();
    
    // Low Stock Products
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE stock < 10 AND stock > 0 
        ORDER BY stock ASC 
        LIMIT 5
    ");
    $stmt->execute();
    $low_stock = $stmt->fetchAll();
    
    // Out of Stock Products
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE stock = 0 
        ORDER BY updated_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $out_of_stock = $stmt->fetchAll();
    
    // Recent Reviews
    $stmt = $db->prepare("
        SELECT r.*, u.username, p.name as product_name 
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN products p ON r.product_id = p.id
        WHERE r.is_approved = 0
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $pending_reviews = $stmt->fetchAll();
    
    // Monthly Sales Data for Chart
    $stmt = $db->query("
        SELECT 
            MONTH(order_date) as month,
            COUNT(*) as order_count,
            SUM(total_amount) as revenue
        FROM orders 
        WHERE payment_status = 'completed'
        AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY MONTH(order_date)
        ORDER BY month DESC
    ");
    $monthly_data = $stmt->fetchAll();
    
    // Subscription Stats
    $stmt = $db->query("
        SELECT subscription_plan, COUNT(*) as count 
        FROM users 
        WHERE subscription_plan IS NOT NULL
        GROUP BY subscription_plan
    ");
    $subscription_stats = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error_message = 'Error loading dashboard: ' . $e->getMessage();
    error_log($error_message);
}

// Prepare chart data
$months = [];
$revenues = [];
foreach ($monthly_data as $data) {
    $months[] = date('M', mktime(0, 0, 0, $data['month'], 1));
    $revenues[] = $data['revenue'];
}
$months = array_reverse($months);
$revenues = array_reverse($revenues);
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

/* Dashboard Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: #f4f7fc;
}

.main-content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
}

/* Header */
.dashboard-header {
    background: white;
    border-radius: 20px;
    margin-bottom: 30px;
    border: none;
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success), var(--warning), var(--danger));
}

.welcome-text h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.welcome-text p {
    color: #6c757d;
    margin-bottom: 0;
}

.date-badge {
    background: var(--light);
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    color: var(--dark);
}

/* Stats Cards */
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent 50%, rgba(255,255,255,0.1) 100%);
    pointer-events: none;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
    color: var(--dark);
}

.stat-label {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 10px;
}

.stat-trend {
    font-size: 13px;
    padding: 5px 10px;
    border-radius: 20px;
    display: inline-block;
}

/* Card Styles */
.dashboard-card {
    background: white;
    border-radius: 20px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-bottom: 30px;
    overflow: hidden;
}

.card-header {
    background: white;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 20px 25px;
}

.card-header h5 {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0;
}

.card-header .badge {
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 20px;
}

.card-body {
    padding: 25px;
}

/* Table Styles */
.table {
    margin-bottom: 0;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
    padding: 15px 10px;
    border-bottom: 1px solid #edf2f9;
}

.table tr:last-child td {
    border-bottom: none;
}

/* Avatar Styles */
.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--light);
    color: var(--primary);
    font-weight: 600;
}

.avatar-lg {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

/* Product Image */
.product-img-sm {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    object-fit: cover;
}

/* Badge Styles */
.badge {
    padding: 6px 12px;
    font-weight: 500;
    border-radius: 20px;
    font-size: 12px;
}

.badge-success { background: rgba(6, 214, 160, 0.1); color: #06d6a0; }
.badge-warning { background: rgba(255, 183, 3, 0.1); color: #ffb703; }
.badge-danger { background: rgba(239, 71, 111, 0.1); color: #ef476f; }
.badge-info { background: rgba(76, 201, 240, 0.1); color: #4cc9f0; }
.badge-primary { background: rgba(67, 97, 238, 0.1); color: #4361ee; }
.badge-purple { background: rgba(114, 9, 183, 0.1); color: #7209b7; }

/* Progress Bar */
.progress {
    height: 8px;
    border-radius: 4px;
    background: #edf2f9;
    margin: 15px 0;
}

.progress-bar {
    background: linear-gradient(90deg, var(--primary), var(--info));
    border-radius: 4px;
}

/* Quick Action Buttons */
.quick-action-btn {
    display: block;
    padding: 20px;
    text-align: center;
    background: var(--light);
    border-radius: 15px;
    color: var(--dark);
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.quick-action-btn:hover {
    background: white;
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(67, 97, 238, 0.1);
}

.quick-action-btn i {
    font-size: 28px;
    color: var(--primary);
    margin-bottom: 10px;
}

.quick-action-btn span {
    display: block;
    font-weight: 500;
    font-size: 14px;
}

/* Status Indicators */
.status-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-active { background: #06d6a0; box-shadow: 0 0 0 3px rgba(6, 214, 160, 0.2); }
.status-pending { background: #ffb703; box-shadow: 0 0 0 3px rgba(255, 183, 3, 0.2); }
.status-suspended { background: #ef476f; box-shadow: 0 0 0 3px rgba(239, 71, 111, 0.2); }

/* Chart Container */
.chart-container {
    position: relative;
    height: 300px;
    margin: 20px 0;
}

/* List Group Custom */
.list-group-item {
    border: none;
    border-bottom: 1px solid #edf2f9;
    padding: 15px 0;
}

.list-group-item:last-child {
    border-bottom: none;
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

/* Responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 20px;
    }
    
    .dashboard-header {
        padding: 20px !important;
    }
    
    .welcome-text h1 {
        font-size: 24px;
    }
    
    .date-badge {
        margin-top: 15px;
    }
}

/* Gradient Backgrounds */
.bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #fad961 0%, #f76b1c 100%); }
.bg-gradient-danger { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%); }

/* Text Colors */
.text-gradient-primary { background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

/* Tooltip */
.custom-tooltip {
    position: relative;
    display: inline-block;
}

.custom-tooltip:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}

.tooltip-text {
    visibility: hidden;
    opacity: 0;
    position: absolute;
    background: var(--dark);
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    transition: all 0.3s ease;
    z-index: 1000;
}
</style>

<!-- Dashboard Layout -->
<div class="dashboard-container">
    <!-- Include Sidebar -->
    <?php include './includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Dashboard Header -->
        <div class="dashboard-header p-4 animate-slide-in">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-text">
                        <h1>
                            <i class="fas fa-chart-pie me-2 text-gradient-primary"></i>
                            Dashboard Overview
                        </h1>
                        <p>
                            <i class="fas fa-user-circle me-2 text-primary"></i>
                            Welcome back, <strong><?php echo $_SESSION['full_name']; ?></strong>! Here's what's happening with your store today.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="date-badge text-end">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                        <?php echo date('l, F j, Y'); ?>
                        <br>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Last updated: <?php echo date('h:i A'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-15 mb-4 animate-slide-in delay-1" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                    <div>
                        <strong>Error!</strong> <?php echo $error_message; ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards Row 1 -->
        <div class="row g-4 mb-4">
            <!-- Total Users -->
            <div class="col-xl-3 col-md-6 animate-slide-in delay-1">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1); color: var(--primary);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="stat-trend" style="background: rgba(6, 214, 160, 0.1); color: #06d6a0;">
                            <i class="fas fa-user-check me-1"></i>
                            <?php echo $active_users; ?> Active
                        </span>
                        <span class="stat-trend" style="background: rgba(255, 183, 3, 0.1); color: #ffb703;">
                            <i class="fas fa-user-plus me-1"></i>
                            +<?php echo $new_users_today; ?> Today
                        </span>
                    </div>
                    <div class="progress mt-3">
                        <div class="progress-bar" style="width: <?php echo ($active_users / max($total_users, 1)) * 100; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <!-- Vendors -->
            <div class="col-xl-3 col-md-6 animate-slide-in delay-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1); color: var(--info);">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_vendors); ?></div>
                    <div class="stat-label">Vendors</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="stat-trend" style="background: rgba(255, 183, 3, 0.1); color: #ffb703;">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $pending_vendors; ?> Pending
                        </span>
                        <a href="vendors/vendors.php" class="text-decoration-none small">View All →</a>
                    </div>
                </div>
            </div>
            
            <!-- Products -->
            <div class="col-xl-3 col-md-6 animate-slide-in delay-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1); color: var(--success);">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_products); ?></div>
                    <div class="stat-label">Products</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="stat-trend" style="background: rgba(255, 183, 3, 0.1); color: #ffb703;">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $pending_products; ?> Pending
                        </span>
                        <span class="stat-trend" style="background: rgba(239, 71, 111, 0.1); color: #ef476f;">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php echo count($low_stock); ?> Low
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Orders -->
            <div class="col-xl-3 col-md-6 animate-slide-in delay-1">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1); color: var(--warning);">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                    <div class="stat-label">Total Orders</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="stat-trend" style="background: rgba(255, 183, 3, 0.1); color: #ffb703;">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $pending_orders; ?> Pending
                        </span>
                        <span class="stat-trend" style="background: rgba(6, 214, 160, 0.1); color: #06d6a0;">
                            <i class="fas fa-check-circle me-1"></i>
                            <?php echo $delivered_orders; ?> Delivered
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards Row 2 -->
        <div class="row g-4 mb-4">
            <!-- Revenue -->
            <div class="col-xl-4 col-md-6 animate-slide-in delay-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1); color: var(--danger);">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <small class="text-muted">Today</small>
                            <h6 class="mb-0 text-success">$<?php echo number_format($today_revenue, 2); ?></h6>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">This Month</small>
                            <h6 class="mb-0 text-info">$<?php echo number_format($monthly_revenue, 2); ?></h6>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Withdrawals -->
            <div class="col-xl-4 col-md-6 animate-slide-in delay-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(114, 9, 183, 0.1); color: #7209b7;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($pending_withdrawal_amount, 2); ?></div>
                    <div class="stat-label">Pending Withdrawals</div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <span class="stat-trend" style="background: rgba(255, 183, 3, 0.1); color: #ffb703;">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $pending_withdrawals; ?> Requests
                        </span>
                        <a href="withdrawals.php" class="btn btn-sm btn-outline-primary">Process →</a>
                    </div>
                </div>
            </div>
            
            <!-- Average Order Value -->
            <div class="col-xl-4 col-md-6 animate-slide-in delay-1">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1); color: var(--primary);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value">
                        $<?php echo number_format($total_orders > 0 ? $total_revenue / $total_orders : 0, 2); ?>
                    </div>
                    <div class="stat-label">Average Order Value</div>
                    <div class="d-flex align-items-center mt-3">
                        <i class="fas fa-arrow-up text-success me-2"></i>
                        <span class="text-success">+12.5%</span>
                        <span class="text-muted ms-2">vs last month</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add this in admin dashboard stats -->
<?php
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM vendor_categories WHERE approval_status = 'pending'");
    $pending_categories = $stmt->fetchColumn();
} catch(Exception $e) {
    $pending_categories = 0;
}
?>

<!-- Add this card in your admin dashboard -->
<div class="col-xl-3 col-md-6">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1">Category Approvals</h6>
                    <h3 class="mb-0"><?php echo $pending_categories; ?></h3>
                    <small class="text-warning">Pending review</small>
                </div>
                <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle">
                    <i class="fas fa-check-double text-warning fa-lg"></i>
                </div>
            </div>
            <div class="mt-3">
                <a href="categories/approvals.php" class="btn btn-sm btn-outline-warning w-100">
                    Review Categories
                </a>
            </div>
        </div>
    </div>
</div>
        <!-- Charts and Tables Row -->
        <div class="row g-4 mb-4">
            <!-- Revenue Chart -->
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="fas fa-chart-bar me-2 text-primary"></i>
                            Revenue Overview
                        </h5>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary active">Monthly</button>
                            <button class="btn btn-sm btn-outline-primary">Weekly</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top Products -->
            <div class="col-lg-4">
                <div class="dashboard-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="fas fa-crown me-2 text-warning"></i>
                            Top Products
                        </h5>
                        <a href="products/products.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_products)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No products sold yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($top_products as $product): ?>
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?php echo !empty($product['image']) ? '../assets/images/products/' . $product['image'] : '../assets/images/no-image.png'; ?>" 
                                     class="product-img-sm me-3" alt="<?php echo $product['name']; ?>">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars(substr($product['name'], 0, 25)) . '...'; ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-shopping-cart me-1"></i>
                                        <?php echo $product['total_sold'] ?? 0; ?> sold
                                    </small>
                                </div>
                                <div class="text-end">
                                    <strong>$<?php echo number_format($product['price'], 2); ?></strong>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row g-4 mb-4">
            <!-- Recent Orders -->
            <div class="col-lg-7">
                <div class="dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="fas fa-shopping-bag me-2 text-success"></i>
                            Recent Orders
                        </h5>
                        <a href="orders/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No orders yet</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo $order['id']; ?></strong>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2">
                                                        <?php echo strtoupper(substr($order['full_name'] ?? $order['username'] ?? 'G', 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <?php echo $order['full_name'] ?? $order['username'] ?? 'Guest'; ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $order['email'] ?? ''; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo date('d M, H:i', strtotime($order['order_date'])); ?></td>
                                            <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'shipped' => 'primary',
                                                    'delivered' => 'success',
                                                    'cancelled' => 'danger'
                                                ];
                                                $color = $status_colors[$order['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="orders/view-order.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
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
            
            <!-- Recent Users -->
            <div class="col-lg-5">
                <div class="dashboard-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="fas fa-users me-2 text-info"></i>
                            Recent Users
                        </h5>
                        <a href="users/users.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users yet</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($recent_users as $user): ?>
                                <div class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3">
                                            <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo $user['full_name'] ?? $user['username']; ?></h6>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-envelope me-1"></i><?php echo $user['email']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $user['user_type'] == 'admin' ? 'danger' : ($user['user_type'] == 'vendor' ? 'warning' : 'primary'); ?>">
                                                <?php echo ucfirst($user['user_type']); ?>
                                            </span>
                                            <br>
                                            <span class="status-indicator status-<?php echo $user['account_status']; ?>"></span>
                                            <small><?php echo ucfirst($user['account_status']); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="row g-4">
            <!-- Low Stock Alert -->
            <div class="col-lg-4">
                <div class="dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                            Low Stock Alert
                        </h5>
                        <span class="badge bg-warning"><?php echo count($low_stock); ?> Items</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($low_stock)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="text-muted">All products are well stocked</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($low_stock as $product): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars(substr($product['name'], 0, 30)); ?></h6>
                                    <small class="text-muted">SKU: PRD-<?php echo $product['id']; ?></small>
                                </div>
                                <div class="text-center mx-3">
                                    <span class="badge bg-danger"><?php echo $product['stock']; ?> left</span>
                                </div>
                                <a href="products/edit-product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                            <?php endforeach; ?>
                            <a href="products/products.php?filter=low-stock" class="btn btn-link w-100 mt-2">
                                View All Low Stock Items
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pending Reviews -->
            <div class="col-lg-4">
                <div class="dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>
                            <i class="fas fa-star me-2 text-warning"></i>
                            Pending Reviews
                        </h5>
                        <span class="badge bg-warning"><?php echo count($pending_reviews); ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_reviews)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No pending reviews</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($pending_reviews as $review): ?>
                            <div class="d-flex align-items-start mb-3">
                                <div class="avatar-sm me-3">
                                    <?php echo strtoupper(substr($review['username'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($review['product_name']); ?></h6>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-user me-1"></i><?php echo $review['username']; ?>
                                    </small>
                                    <small class="text-warning">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </small>
                                </div>
                                <a href="reviews/review.php?id=<?php echo $review['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="dashboard-card h-100">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-bolt me-2 text-warning"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="./products/products.php?action=add" class="quick-action-btn">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Add Product</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="./users/users.php?action=add" class="quick-action-btn">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Add User</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="./vendors/vendors.php?filter=pending" class="quick-action-btn">
                                    <i class="fas fa-store"></i>
                                    <span>Pending Vendors</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="./withdrawals.php" class="quick-action-btn">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>Withdrawals</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="./reports/reports.php" class="quick-action-btn">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>Reports</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="./settings/settings.php" class="quick-action-btn">
                                    <i class="fas fa-cog"></i>
                                    <span>Settings</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="dashboard-card mt-4">
            <div class="card-header">
                <h5>
                    <i class="fas fa-server me-2 text-info"></i>
                    System Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6 mb-3">
                        <small class="text-muted d-block">PHP Version</small>
                        <strong><?php echo phpversion(); ?></strong>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <small class="text-muted d-block">Database</small>
                        <strong>MySQL</strong>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <small class="text-muted d-block">Server Time</small>
                        <strong><?php echo date('Y-m-d H:i:s'); ?></strong>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <small class="text-muted d-block">System Status</small>
                        <strong><span class="badge bg-success">Operational</span></strong>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted d-block">Total Tables</small>
                        <strong>
                            <?php 
                            try {
                                $stmt = $db->query("SHOW TABLES");
                                echo $stmt->rowCount();
                            } catch(Exception $e) {
                                echo 'N/A';
                            }
                            ?>
                        </strong>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted d-block">Memory Usage</small>
                        <strong><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</strong>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted d-block">Max Execution Time</small>
                        <strong><?php echo ini_get('max_execution_time'); ?>s</strong>
                    </div>
                    <div class="col-md-3 col-6">
                        <small class="text-muted d-block">Upload Max Size</small>
                        <strong><?php echo ini_get('upload_max_filesize'); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Auto-hide alerts
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Initialize Revenue Chart
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months ?: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']); ?>,
            datasets: [{
                label: 'Revenue ($)',
                data: <?php echo json_encode($revenues ?: [0, 0, 0, 0, 0, 0]); ?>,
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#4361ee',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#2b2d42',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#4361ee',
                    borderWidth: 2,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Mobile sidebar toggle
    $('.sidebar-toggle').click(function() {
        $('.sidebar').toggleClass('active');
        $('.main-content').toggleClass('expanded');
    });
    
    // Tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Animated counters
    $('.stat-value').each(function() {
        const $this = $(this);
        const countTo = parseInt($this.text().replace(/[^0-9]/g, ''));
        
        if (!isNaN(countTo)) {
            $({ count: 0 }).animate({ count: countTo }, {
                duration: 1000,
                easing: 'swing',
                step: function() {
                    const formatted = $this.text().includes('$') 
                        ? '$' + Math.floor(this.count).toLocaleString()
                        : Math.floor(this.count).toLocaleString();
                    $this.text(formatted);
                }
            });
        }
    });
});
</script>

<?php require_once './includes/footer.php'; ?>