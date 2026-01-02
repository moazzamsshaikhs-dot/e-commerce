<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect('admin/dashboard.php');
}

$page_title = 'User Dashboard';
require_once '../includes/header.php';

// Get user statistics
try {
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
    
    // Recent Orders
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();
    
    // User Subscription
    $subscription = getUserSubscription($user_id);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading dashboard data: ' . $e->getMessage();
}

// Log dashboard access
logUserActivity($_SESSION['user_id'], 'dashboard_access', 'Accessed user dashboard');
?>

<!-- Dashboard Layout -->
<div class="dashboard-container">
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Welcome, <?php echo $_SESSION['full_name']; ?>!</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-crown me-1 text-<?php 
                            echo $_SESSION['subscription_plan'] == 'premium' ? 'warning' : 
                                 ($_SESSION['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                        ?>"></i>
                        <?php echo ucfirst($_SESSION['subscription_plan']); ?> Plan User
                    </p>
                </div>
                <div class="d-flex gap-3">
                    <?php if ($_SESSION['subscription_plan'] === 'free'): ?>
                        <a href="upgrade.php" class="btn btn-warning">
                            <i class="fas fa-crown me-2"></i> Upgrade Now
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> New Order
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Orders</h6>
                                <h3 class="mb-0"><?php echo $user_orders; ?></h3>
                            </div>
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-shopping-cart text-primary fa-2x"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="orders.php" class="text-decoration-none small">
                                <i class="fas fa-eye me-1"></i> View All Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Completed</h6>
                                <h3 class="mb-0"><?php echo $completed_orders; ?></h3>
                            </div>
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-check-circle text-success fa-2x"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i> Delivered
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Pending</h6>
                                <h3 class="mb-0"><?php echo $pending_orders; ?></h3>
                            </div>
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-clock text-warning fa-2x"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-warning">
                                <i class="fas fa-sync-alt me-1"></i> Processing
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Spent</h6>
                                <h3 class="mb-0">$<?php echo number_format($total_spent, 2); ?></h3>
                            </div>
                            <div class="avatar-sm bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-dollar-sign text-danger fa-2x"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="text-success">
                                <i class="fas fa-chart-line me-1"></i> View History
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Subscription Plan Card -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Your Plan</h5>
                        <?php if ($_SESSION['subscription_plan'] === 'free'): ?>
                            <a href="upgrade.php" class="btn btn-sm btn-warning">
                                <i class="fas fa-crown me-1"></i> Upgrade
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="avatar-lg mx-auto mb-3 bg-<?php 
                                echo $_SESSION['subscription_plan'] == 'premium' ? 'warning' : 
                                     ($_SESSION['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                            ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                 style="width: 100px; height: 100px;">
                                <i class="fas fa-<?php 
                                    echo $_SESSION['subscription_plan'] == 'premium' ? 'crown' : 
                                         ($_SESSION['subscription_plan'] == 'business' ? 'building' : 'user'); 
                                ?> fa-3x text-<?php 
                                    echo $_SESSION['subscription_plan'] == 'premium' ? 'warning' : 
                                         ($_SESSION['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                                ?>"></i>
                            </div>
                            <h3 class="mb-1"><?php echo ucfirst($_SESSION['subscription_plan']); ?> Plan</h3>
                            <p class="text-muted">
                               <?php if (isset($subscription['subscription_expiry']) && $subscription['subscription_expiry']): ?>
    Valid until: <?php echo date('d M Y', strtotime($subscription['subscription_expiry'])); ?>
<?php else: ?>
    Lifetime access
<?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <h5 class="mb-0"><?php echo $_SESSION['subscription_plan'] === 'free' ? '5' : '∞'; ?></h5>
                                <small class="text-muted">Products</small>
                            </div>
                            <div class="col-4">
                                <h5 class="mb-0"><?php echo $_SESSION['subscription_plan'] === 'free' ? 'Basic' : 'Premium'; ?></h5>
                                <small class="text-muted">Support</small>
                            </div>
                            <div class="col-4">
                                <h5 class="mb-0"><?php echo $_SESSION['subscription_plan'] === 'free' ? 'No' : 'Yes'; ?></h5>
                                <small class="text-muted">Analytics</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Recent Orders</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No orders yet</p>
                                <a href="#" class="btn btn-primary">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['order_number'] ?? $order['id']; ?></td>
                                            <td><?php echo date('d M', strtotime($order['order_date'])); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] == 'delivered' ? 'success' : 
                                                         ($order['status'] == 'pending' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="orders.php" class="btn btn-sm btn-outline-primary">
                                    View All Orders <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row g-4 mt-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-user-edit fa-3x text-primary mb-3"></i>
                        <h5>Complete Profile</h5>
                        <p class="text-muted small">Update your personal information</p>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">Go to Profile</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                        <h5>Security</h5>
                        <p class="text-muted small">Manage password and security</p>
                        <a href="settings.php?tab=security" class="btn btn-outline-success btn-sm">Security Settings</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-question-circle fa-3x text-info mb-3"></i>
                        <h5>Need Help?</h5>
                        <p class="text-muted small">Contact our support team</p>
                        <a href="#" class="btn btn-outline-info btn-sm">Get Help</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0" id="recentActivity">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6>Account Created</h6>
                            <p class="text-muted small mb-0">You joined ShopEase Pro</p>
                            <small class="text-muted"><?php echo date('d M Y', strtotime($_SESSION['login_time'])); ?></small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6>Last Login</h6>
                            <p class="text-muted small mb-0">You logged in successfully</p>
                            <small class="text-muted">Today at <?php echo date('h:i A'); ?></small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6>Profile Updated</h6>
                            <p class="text-muted small mb-0">You updated your profile picture</p>
                            <small class="text-muted">2 days ago</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Timeline CSS -->
<style>
    .sidbar {
    width: 250px;    
    position: fixed;
    min-height: 100vh;
}
    .main-content {
    flex: 1;
    padding: 20px;
    background: #f8f9fa;
    min-height: calc(100vh - 70px);
}
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        background-color: #f8f9fa;
    }
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 3px solid white;
}

.timeline-content {
    padding-bottom: 10px;
}
</style>

<?php require_once '../includes/footer.php'; ?>