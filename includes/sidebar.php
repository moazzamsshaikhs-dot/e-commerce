<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    return;
}
try{
    $db = getDB();
    $user_id = $_SESSION['user_id'];
   // User Orders
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_orders = $stmt->fetch()['total'];
    
    // Completed Orders
    $stmt = $db->prepare("SELECT COUNT(*) as completed FROM orders WHERE user_id = ? AND status = 'delivered'");
    $stmt->execute([$user_id]);
    $completed_orders = $stmt->fetch()['completed'];
    
    // Pending Orders
    $stmt = $db->prepare("SELECT COUNT(*) as pending FROM orders WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_orders = $stmt->fetch()['pending'];
    
    // Total Spent
    $stmt = $db->prepare("SELECT SUM(total_amount) as spent FROM orders WHERE user_id = ? AND payment_status = 'completed'");
    $stmt->execute([$user_id]);
    $total_spent = $stmt->fetch()['spent'] ?? 0;
    
    // Recent Orders - FIXED: Use correct column name
    // Try different column names based on your database structure
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    // OR if you have a date column:
    // $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();
    
    // User Subscription
    $subscription = getUserSubscription($user_id);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading dashboard data: ' . $e->getMessage();
}


$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = ($_SESSION['user_type'] === 'admin');
?>

<!-- Sidebar -->
<aside class="sidebar bg-dark text-white" id="sidebar">
    <div class="sidebar-header p-4 border-bottom border-secondary">
        <div class="text-center">
            <div class="avatar mb-3">
                <img src="<?php echo SITE_URL; ?>assets/images/avatars/<?php echo $_SESSION['profile_pic'] ?? 'default.png'; ?>" 
                     alt="Profile" class="rounded-circle" width="80" height="80">
            </div>
            <h6 class="mb-1"><?php echo $_SESSION['full_name']; ?></h6>
            <small class="text-white-50">
                <?php 
                echo ucfirst($_SESSION['user_type']); 
                echo ' • ';
                echo ucfirst($_SESSION['subscription_plan'] ?? 'free') . ' Plan';
                ?>
            </small>
        </div>
    </div>
    
    <div class="sidebar-menu p-3">
        <?php if ($is_admin): ?>
            <!-- Admin Sidebar Menu -->
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                       href="/e-commerce/admin/dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" 
                       href="/e-commerce/admin/users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/products.php">
                        <i class="fas fa-box me-2"></i> Products
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>" 
                       href="/e-commerce/admin/orders.php">
                        <i class="fas fa-shopping-cart me-2"></i> Orders
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/payments.php">
                        <i class="fas fa-credit-card me-2"></i> Payments
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/profile.php">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
            </ul>
            
            <!-- Admin Reports -->
            <div class="mt-4">
                <h6 class="text-uppercase text-muted mb-3">Reports</h6>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>admin/sales_report.php">
                            <i class="fas fa-chart-line me-2"></i> Sales Report
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>admin/analytics.php">
                            <i class="fas fa-chart-pie me-2"></i> Analytics
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>admin/invoices.php">
                            <i class="fas fa-file-invoice me-2"></i> Invoices
                        </a>
                    </li>
                </ul>
            </div>
            
        <?php else: ?>
            <!-- User Sidebar Menu -->
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/profile.php">
                        <i class="fas fa-user me-2"></i> My Profile
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/orders.php">
                        <i class="fas fa-shopping-cart me-2"></i> My Orders
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'wishlist.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/wishlist.php">
                        <i class="fas fa-heart me-2"></i> Wishlist
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'upgrade.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/upgrade.php">
                        <i class="fas fa-crown me-2"></i> Upgrade Plan
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
            </ul>
            
            <!-- User Quick Actions -->
            <div class="mt-4">
                <h6 class="text-uppercase text-muted mb-3">Quick Actions</h6>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#recentActivity">
                            <i class="fas fa-history me-2"></i> Recent Activity
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>user/support.php">
                            <i class="fas fa-question-circle me-2"></i> Help Center
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Logout Button -->
        <div class="mt-5 pt-3 border-top border-secondary">
            <a href="<?php echo SITE_URL; ?>logout.php" class="btn btn-danger btn-sm w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>
</aside>

<!-- Mobile Sidebar Toggle Button -->
<button class="btn btn-primary sidebar-toggle d-lg-none" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>
<style>
    .sidebar {
    background: linear-gradient(40deg, #3a0ca3, #4361ee 100%) !important;
    color: #ffffff !important;
    width: 250px;
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg,  #4361ee 0%,  #3a0ca3 100%) !important;
    color: #ffffff !important;
}
.sidebar .nav-link {
    color: #ffffff !important;
}

.sidebar .btn-danger {
    background: linear-gradient(135deg, #ec4899 0%, #db2777 100%) !important;
    border: none !important;
}
.sidebar .btn-danger:hover {
    background: linear-gradient(135deg, #db2777 0%, #ec4899 100%) !important;
    border: none !important;
    color: #ffffff !important;
}
.sidebar-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1050;
}
</style>