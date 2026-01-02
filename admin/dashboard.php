<?php
require_once './includes/config.php';
require_once './includes/auth-check.php';

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
    
    // Total Products
    $stmt = $db->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];
    
    // Total Orders
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];
    
    // Pending Orders
    $stmt = $db->query("SELECT COUNT(*) as pending FROM orders WHERE status = 'pending'");
    $pending_orders = $stmt->fetch()['pending'];
    
    // Total Revenue
    $stmt = $db->query("SELECT SUM(total_amount) as revenue FROM orders WHERE payment_status = 'completed'");
    $total_revenue = $stmt->fetch()['revenue'] ?? 0;
    
    // Today's Revenue
    $stmt = $db->query("SELECT SUM(total_amount) as today_revenue FROM orders WHERE DATE(order_date) = CURDATE() AND payment_status = 'completed'");
    $today_revenue = $stmt->fetch()['today_revenue'] ?? 0;
    
    // Recent Users (last 5)
    $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();
    
    // Recent Orders (last 5)
    $stmt = $db->prepare("
        SELECT o.*, u.username 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.order_date DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
    
    // Low Stock Products
    $stmt = $db->prepare("SELECT * FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 5");
    $stmt->execute();
    $low_stock = $stmt->fetchAll();
    
    // Subscription Stats
    $stmt = $db->query("
        SELECT subscription_plan, COUNT(*) as count 
        FROM users 
        GROUP BY subscription_plan
    ");
    $subscription_stats = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error_message = 'Error loading dashboard: ' . $e->getMessage();
    error_log($error_message);
}
?>

<!-- Dashboard Layout -->
<div class="dashboard-container">
    <!-- Include Sidebar -->
    <?php include './includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Dashboard Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Dashboard</h1>
                    <p class="text-muted mb-0">Welcome back hello, <?php echo $_SESSION['full_name']; ?>!</p>
                </div>
                <div class="text-muted">
                    <i class="fas fa-calendar me-1"></i>
                    <?php echo date('F j, Y'); ?>
                </div>
            </div>
        </div>
        
        <!-- Error Message -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <!-- Total Users -->
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Users</h6>
                                <h2 class="mb-0"><?php echo $total_users; ?></h2>
                                <small class="text-success">
                                    <i class="fas fa-user-check me-1"></i>
                                    <?php echo $active_users; ?> Active
                                </small>
                            </div>
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-users text-primary fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Products -->
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Products</h6>
                                <h2 class="mb-0"><?php echo $total_products; ?></h2>
                                <small class="text-muted">
                                    <?php echo count($low_stock); ?> Low stock
                                </small>
                            </div>
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-box text-success fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Orders -->
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Orders</h6>
                                <h2 class="mb-0"><?php echo $total_orders; ?></h2>
                                <small class="text-warning">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo $pending_orders; ?> Pending
                                </small>
                            </div>
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-shopping-cart text-warning fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Revenue -->
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Revenue</h6>
                                <h2 class="mb-0">$<?php echo number_format($total_revenue, 2); ?></h2>
                                <small class="text-muted">
                                    Today: $<?php echo number_format($today_revenue, 2); ?>
                                </small>
                            </div>
                            <div class="avatar-sm bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-dollar-sign text-danger fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Tables -->
        <div class="row g-3">
            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Orders</h5>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No orders yet</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $order['order_number']; ?></strong>
                                            </td>
                                            <td>
                                                <?php echo $order['username'] ?? 'Guest'; ?>
                                            </td>
                                            <td><?php echo date('d M', strtotime($order['order_date'])); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                $status_color = 'secondary';
                                                if ($order['status'] == 'pending') $status_color = 'warning';
                                                if ($order['status'] == 'processing') $status_color = 'info';
                                                if ($order['status'] == 'delivered') $status_color = 'success';
                                                if ($order['status'] == 'cancelled') $status_color = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
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
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Users</h5>
                        <a href="users.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users yet</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($recent_users as $user): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3">
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-muted"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo $user['full_name'] ?? $user['username']; ?></h6>
                                            <small class="text-muted d-block">
                                                @<?php echo $user['username']; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?php echo $user['user_type'] == 'admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($user['user_type']); ?>
                                            </span>
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
        
        <!-- Additional Stats -->
        <div class="row g-3 mt-3">
            <!-- Subscription Stats -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Subscription Plans</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <?php foreach($subscription_stats as $stat): ?>
                            <div class="col-4">
                                <div class="avatar-lg mx-auto mb-2 bg-<?php 
                                    echo $stat['subscription_plan'] == 'premium' ? 'warning' : 
                                         ($stat['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                                ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="fas fa-<?php 
                                        echo $stat['subscription_plan'] == 'premium' ? 'crown' : 
                                             ($stat['subscription_plan'] == 'business' ? 'building' : 'user'); 
                                    ?> text-<?php 
                                        echo $stat['subscription_plan'] == 'premium' ? 'warning' : 
                                             ($stat['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                                    ?>"></i>
                                </div>
                                <h4 class="mb-0"><?php echo $stat['count']; ?></h4>
                                <small class="text-muted"><?php echo ucfirst($stat['subscription_plan']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="products.php?action=add" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-plus me-1"></i> Add Product
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="users.php?action=add" class="btn btn-outline-success w-100 mb-2">
                                    <i class="fas fa-user-plus me-1"></i> Add User
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="orders.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-shopping-cart me-1"></i> View Orders
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="settings.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-cog me-1"></i> Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                        <p><strong>Database:</strong> MySQL</p>
                        <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total Tables:</strong> 
                            <?php 
                            try {
                                $stmt = $db->query("SHOW TABLES");
                                echo $stmt->rowCount();
                            } catch(Exception $e) {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                        <p><strong>System Status:</strong> <span class="badge bg-success">Operational</span></p>
                        <p><strong>Memory Usage:</strong> <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Dashboard CSS -->
<style>
.dashboard-container {
    display: flex;
    min-height: calc(100vh - 70px);
}

.sidebar {
    width: 250px;
    background: #1a1a2e;
    transition: all 0.3s;
    position: fixed;
    height: calc(100vh - 70px);
    overflow-y: auto;
    z-index: 1000;
}

.main-content {
    flex: 1;
    margin-left: 250px;
    padding: 20px;
    background: #f8f9fa;
    /* color: #f8f9fa; */
    min-height: calc(100vh - 70px);
}

.sidebar-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1001;
    display: none;
}

@media (max-width: 992px) {
    .sidebar {
        margin-left: -250px;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .sidebar.active {
        margin-left: 0;
    }
    
    .sidebar-toggle {
        display: block;
    }
}

.card {
    border-radius: 10px;
    border: none;
}

.avatar-sm {
    width: 40px;
    height: 40px;
}

.avatar-lg {
    width: 60px;
    height: 60px;
}

.badge {
    padding: 5px 10px;
    font-weight: 500;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.list-group-item {
    border: none;
    border-bottom: 1px solid #eee;
}

.list-group-item:last-child {
    border-bottom: none;
}


</style>

<script>
$(document).ready(function() {
    // Auto-hide alerts
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Mobile sidebar toggle
    $('.sidebar-toggle').click(function() {
        $('.sidebar').toggleClass('active');
    });
});
</script>

<?php require_once './includes/footer.php'; ?>