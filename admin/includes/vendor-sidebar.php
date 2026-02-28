<?php
// Check if user is vendor
// if ($_SESSION['user_type'] !== 'admin') {
//     return;
// }
if ($_SESSION['user_type'] !== 'vendor') {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);

// Get vendor status
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status, vendor_category FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_data = $stmt->fetch();
    $vendor_status = $vendor_data['vendor_status'] ?? 'pending';
    $vendor_category = $vendor_data['vendor_category'] ?? 'Not set';
} catch(PDOException $e) {
    $vendor_status = 'pending';
    $vendor_category = 'Not set';
}

$is_approved = ($vendor_status === 'approved');
?>

<!-- Mobile Header (Shows only on mobile) -->
<div class="mobile-header d-lg-none">
    <div class="d-flex align-items-center justify-content-between p-3 bg-white shadow-sm">
        <div class="d-flex align-items-center">
            <button class="btn sidebar-toggle me-3" id="sidebarToggleMobile">
                <i class="fas fa-bars fa-lg text-primary"></i>
            </button>
            <div>
                <h6 class="mb-0 fw-bold"><?php echo $_SESSION['full_name']; ?></h6>
                <small class="text-muted">
                    <i class="fas fa-store me-1 text-success"></i> Vendor
                    <?php if ($vendor_status): ?>
                        <span class="badge bg-<?php 
                            echo $vendor_status === 'approved' ? 'success' : 
                                 ($vendor_status === 'pending' ? 'warning' : 'danger'); 
                        ?> ms-2">
                            <?php echo ucfirst($vendor_status); ?>
                        </span>
                    <?php endif; ?>
                </small>
            </div>
        </div>
        <div class="avatar-sm">
            <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $_SESSION['profile_pic'] ?? 'default.png'; ?>" 
                 alt="Profile" class="rounded-circle" width="40" height="40"
                 onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png'">
        </div>
    </div>
</div>

<!-- Sidebar Overlay (Mobile only) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Main Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="text-center py-4">
            <div class="avatar mb-3">
                <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $_SESSION['profile_pic'] ?? 'default.png'; ?>" 
                     alt="Profile" class="rounded-circle border border-3 border-white" width="80" height="80"
                     onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png'">
            </div>
            <h6 class="mb-1 text-black-10 fw-bold"><?php echo $_SESSION['full_name']; ?></h6>
            <small class="text-white-75">
                <i class="fas fa-store me-1"></i> Vendor
                <?php if ($vendor_status): ?>
                    <span class="badge bg-<?php 
                        echo $vendor_status === 'approved' ? 'success' : 
                             ($vendor_status === 'pending' ? 'warning' : 'danger'); 
                    ?> ms-2">
                        <?php echo ucfirst($vendor_status); ?>
                    </span>
                <?php endif; ?>
            </small>
            
            <!-- Vendor Category -->
            <div class="mt-2">
                <small class="text-white-75">
                    <i class="fas fa-tag me-1"></i> <?php echo $vendor_category; ?>
                </small>
            </div>
            
            <?php if ($is_approved): ?>
            <!-- Vendor Stats -->
            <div class="vendor-stats mt-3">
                <div class="row g-2">
                    <?php 
                    // Get quick stats
                    try {
                        $db = getDB();
                        $vendor_id = $_SESSION['user_id'];
                        
                        // Total Products
                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE vendor_id = ?");
                        $stmt->execute([$vendor_id]);
                        $total_products = $stmt->fetch()['total'];
                        
                        // Total Orders
                        $stmt = $db->prepare("
                            SELECT COUNT(DISTINCT oi.order_id) as total 
                            FROM order_items oi 
                            JOIN products p ON oi.product_id = p.id 
                            WHERE p.vendor_id = ?
                        ");
                        $stmt->execute([$vendor_id]);
                        $total_orders = $stmt->fetch()['total'];
                        
                    } catch(PDOException $e) {
                        $total_products = 0;
                        $total_orders = 0;
                    }
                    ?>
                    <div class="col-6">
                        <div class="bg-white-10 p-2 rounded text-center">
                            <small class="d-block text-white-75">Products</small>
                            <span class="fw-bold text-muted"><?php echo $total_products; ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-white-10 p-2 rounded text-center">
                            <small class="d-block text-white-75">Orders</small>
                            <span class="fw-bold text-muted"><?php echo $total_orders; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sidebar Menu -->
    <div class="sidebar-menu">
        <!-- Main Vendor Menu -->
        <ul class="nav flex-column px-3">
            <!-- Dashboard -->
            <li class="nav-item mb-2 me-2">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/dashboard.php">
                    <i class="fas fa-tachometer-alt me-3"></i> Dashboard
                </a>
            </li>
            
            <?php if ($is_approved): ?>
            <!-- Products Management -->
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo ($current_page == 'products.php' || strpos($current_page, 'products/') !== false) ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/products/products.php">
                    <i class="fas fa-boxes me-3"></i> My Products
                </a>
            </li>
            
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo ($current_page == 'add.php') ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/products/add.php">
                    <i class="fas fa-plus-circle me-3"></i> Add Product
                </a>
            </li>
            
            <!-- Orders -->
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo ($current_page == 'orders.php' || strpos($current_page, 'orders/') !== false) ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/orders/orders.php">
                    <i class="fas fa-shopping-cart me-3"></i> My Orders
                </a>
            </li>
            
            <!-- Earnings -->
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo ($current_page == 'earnings.php' || strpos($current_page, 'earnings/') !== false) ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/earnings/earnings.php">
                    <i class="fas fa-money-bill-wave me-3"></i> Earnings
                </a>
            </li>
            
            <!-- Withdrawals -->
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo ($current_page == 'withdraw.php') ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/earnings/withdraw.php">
                    <i class="fas fa-wallet me-3"></i> Withdraw
                </a>
            </li>
            
            <!-- Reviews -->
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo ($current_page == 'reviews.php') ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/reviews/reviews.php">
                    <i class="fas fa-star me-3"></i> Reviews
                </a>    
            </li>
            <?php endif; ?>
            
            <!-- Profile -->
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/profile.php">
                    <i class="fas fa-user me-3"></i> Vendor Profile
                </a>
            </li>

            <!-- Add this in vendor sidebar -->
<li class="nav-item mb-2">
    <a href="categories/my-categories.php" class="nav-link text-dark <?php echo strpos($_SERVER['PHP_SELF'], 'my-categories.php') ? 'active' : ''; ?>">
        <i class="fas fa-tags me-3"></i>
        <span>My Categories</span>
        <?php
        // Get pending count for this vendor
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT COUNT(*) FROM vendor_categories WHERE vendor_id = ? AND approval_status = 'pending'");
            $stmt->execute([$_SESSION['user_id']]);
            $pending_count = $stmt->fetchColumn();
            if ($pending_count > 0) {
                echo '<span class="badge bg-warning ms-auto">' . $pending_count . '</span>';
            }
        } catch(Exception $e) {}
        ?>
    </a>
</li>
            
            <!-- Settings -->
            <li class="nav-item mb-2">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" 
                   href="<?php echo SITE_URL; ?>admin/vendors/settings/settings.php">
                    <i class="fas fa-cog me-3"></i> Settings
                </a>
            </li>
        </ul>
        
        <!-- Vendor Tools -->
        <div class="px-3 mt-4">
            <h6 class="text-uppercase text-black-50 mb-3">Vendor Tools</h6>
            <ul class="nav flex-column">
                <?php if ($is_approved): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>admin/vendors/reports/sales.php">
                        <i class="fas fa-chart-line me-3"></i> Sales Reports
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>admin/vendors/reports/performance.php">
                        <i class="fas fa-chart-bar me-3"></i> Performance
                    </a>
                </li>
                
                <li class="nav-item mb-2">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>admin/vendors/inventory/inventory.php">
                        <i class="fas fa-warehouse me-3"></i> Inventory
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item mb-2">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>admin/vendors/help/support.php">
                        <i class="fas fa-question-circle me-3"></i> Vendor Support
                    </a>
                </li>
                
                <?php if (!$is_approved): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link text-warning" href="<?php echo SITE_URL; ?>admin/vendors/verify.php">
                        <i class="fas fa-user-check me-3"></i> Complete Verification
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Account Status Alert -->
        <?php if (!$is_approved): ?>
        <div class="alert alert-warning mx-3 mt-4 p-3">
            <small>
                <i class="fas fa-info-circle me-1"></i>
                <strong>Account Status: <?php echo ucfirst($vendor_status); ?></strong>
                <br>
                You need admin approval to start selling.
            </small>
        </div>
        <?php endif; ?>
        
        <!-- Logout Button -->
        <div class="px-3 mt-5 pt-4 border-top border-white-10">
            <a href="<?php echo SITE_URL; ?>logout.php" class="btn btn-logout w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer px-3 py-3 text-center">
        <small class="text-white-50">
            <?php echo SITE_NAME; ?> &copy; <?php echo date('Y'); ?>
        </small>
    </div>
</aside>

<!-- Vendor Sidebar CSS -->
<style>
/* Vendor specific sidebar styles */
.sidebar .badge.bg-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
}

.sidebar .badge.bg-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
}

.sidebar .badge.bg-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
}

/* Vendor specific link colors */
.sidebar .nav-link.text-warning {
    color: #f59e0b !important;
}

.sidebar .nav-link.text-warning:hover {
    color: #fbbf24 !important;
    background: rgba(245, 158, 11, 0.1) !important;
}

.vendor-stats .bg-white-10 {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.vendor-stats .bg-white-10:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}
</style>

<!-- Sidebar JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggleMobile = document.getElementById('sidebarToggleMobile');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Toggle sidebar on mobile
    if (sidebarToggleMobile) {
        sidebarToggleMobile.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.add('show');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    }
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            this.classList.remove('show');
            document.body.style.overflow = 'auto';
        });
    }
    
    // Close sidebar when clicking a link (mobile only)
    if (window.innerWidth < 992) {
        const sidebarLinks = sidebar.querySelectorAll('.nav-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('show');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
                document.body.style.overflow = 'auto';
            });
        });
    }
    
    // Close sidebar with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('show');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('show');
            }
            document.body.style.overflow = 'auto';
        }
    });
    
    // Auto-highlight active menu item
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href.replace(SITE_URL, ''))) {
            link.classList.add('active');
        }
    });
});
</script>