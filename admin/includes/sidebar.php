<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_type = $_SESSION['user_type'] ?? 'user';
$is_admin = ($user_type === 'admin');
$is_vendor = ($user_type === 'vendor');
$is_user = ($user_type === 'user');

// Different sidebar based on user type
if ($is_vendor && file_exists(__DIR__ . '/vendor-sidebar.php')) {
    include 'vendor-sidebar.php';
    return;
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- Mobile close button -->
    <button class="sidebar-close d-lg-none" id="sidebarClose">
        <i class="fas fa-times"></i>
    </button>
    
    <div class="sidebar-header p-4 border-bottom border-secondary">
        <div class="text-center">
            <div class="avatar mb-3">
                <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $_SESSION['profile_pic'] ?? 'default.png'; ?>" 
                     alt="Profile" class="rounded-circle" width="80" height="80" 
                     onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png'">
                <span class="online-status"></span>
            </div>
            <h6 class="mb-1 text-white"><?php echo $_SESSION['full_name']; ?></h6>
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
                       href="<?php echo SITE_URL; ?>admin/dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        <?php
                        // Get pending notifications count
                        try {
                            $db = getDB();
                            $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                            $stmt->execute([$_SESSION['user_id']]);
                            $notif_count = $stmt->fetchColumn();
                            if ($notif_count > 0) {
                                echo '<span class="badge bg-danger ms-2">' . $notif_count . '</span>';
                            }
                        } catch(Exception $e) {}
                        ?>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'users') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/users/users.php">
                        <i class="fas fa-users me-2"></i> Users
                        <?php
                        // Get new users count
                        try {
                            $db = getDB();
                            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
                            $new_users = $stmt->fetchColumn();
                            if ($new_users > 0) {
                                echo '<span class="badge bg-success ms-2">+' . $new_users . '</span>';
                            }
                        } catch(Exception $e) {}
                        ?>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'products') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/products/products.php">
                        <i class="fas fa-box me-2"></i> Products
                        <?php
                        // Get pending products count
                        try {
                            $db = getDB();
                            $stmt = $db->query("SELECT COUNT(*) FROM products WHERE approved_status = 'pending'");
                            $pending_products = $stmt->fetchColumn();
                            if ($pending_products > 0) {
                                echo '<span class="badge bg-warning ms-2">' . $pending_products . '</span>';
                            }
                        } catch(Exception $e) {}
                        ?>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'orders') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/orders/orders.php">
                        <i class="fas fa-shopping-cart me-2"></i> Orders
                        <?php
                        // Get pending orders count
                        try {
                            $db = getDB();
                            $stmt = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
                            $pending_orders = $stmt->fetchColumn();
                            if ($pending_orders > 0) {
                                echo '<span class="badge bg-warning ms-2">' . $pending_orders . '</span>';
                            }
                        } catch(Exception $e) {}
                        ?>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'vendors') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/vendors/vendors.php">
                        <i class="fas fa-store me-2"></i> Vendors
                        <?php
                        // Get pending vendors count
                        try {
                            $db = getDB();
                            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'vendor' AND vendor_status = 'pending'");
                            $pending_vendors = $stmt->fetchColumn();
                            if ($pending_vendors > 0) {
                                echo '<span class="badge bg-warning ms-2">' . $pending_vendors . '</span>';
                            }
                        } catch(Exception $e) {}
                        ?>
                    </a>
                </li>
                <!-- Category Approvals Link -->
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'approvals') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/categories/index.php">
                        <i class="fas fa-check-double me-2"></i> Category Approvals
                        <?php
                        // Get pending categories count
                        try {
                            $db = getDB();
                            $stmt = $db->query("SELECT COUNT(*) FROM vendor_categories WHERE approval_status = 'pending'");
                            $pending_categories = $stmt->fetchColumn();
                            if ($pending_categories > 0) {
                                echo '<span class="badge bg-warning ms-2">' . $pending_categories . '</span>';
                            }
                        } catch(Exception $e) {}
                        ?>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'payments') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/payments/index.php">
                        <i class="fas fa-credit-card me-2"></i> Payments
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'withdrawals') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/withdrawals.php">
                        <i class="fas fa-money-bill-wave me-2"></i> Withdrawals
                        <?php
                        // Get pending withdrawals count
                        try {
                            $db = getDB();
                            $stmt = $db->query("SELECT COUNT(*) FROM vendor_withdrawals WHERE status = 'pending'");
                            $pending_withdrawals = $stmt->fetchColumn();
                            if ($pending_withdrawals > 0) {
                                echo '<span class="badge bg-warning ms-2">' . $pending_withdrawals . '</span>';
                            }
                        } catch(Exception $e) {}
                        ?>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'reports') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/reports/reports.php">
                        <i class="fas fa-chart-bar me-2"></i> Reports
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'profile') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/profile.php">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'settings') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/settings/settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
            </ul>
            
            <!-- Admin Reports -->
            <div class="mt-4">
                <h6 class="text-uppercase text-muted mb-3">Quick Stats</h6>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>admin/sales_report.php">
                            <i class="fas fa-chart-line me-2"></i> Sales Report
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>admin/analytics/analytics.php">
                            <i class="fas fa-chart-pie me-2"></i> Analytics
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>admin/invoices/invoices.php">
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
                    <a class="nav-link <?php echo (strpos($current_page, 'profile') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/profile.php">
                        <i class="fas fa-user me-2"></i> My Profile
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'orders') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/orders/orders.php">
                        <i class="fas fa-shopping-cart me-2"></i> My Orders
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'wishlist') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/wishlist/wishlist.php">
                        <i class="fas fa-heart me-2"></i> Wishlist
                    </a>
                </li>
                <?php if ($_SESSION['subscription_plan'] === 'free'): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'upgrade') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/upgrade/upgrade.php">
                        <i class="fas fa-crown me-2"></i> Upgrade Plan
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'settings') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>user/settings/settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
            </ul>
            
            <!-- User Quick Actions -->
            <div class="mt-4">
                <h6 class="text-uppercase text-muted mb-3">Quick Actions</h6>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>user/activity/activity.php">
                            <i class="fas fa-history me-2"></i> Activities
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
        
        <!-- Social Media Icons -->
        <div class="mt-4 pt-3 border-top border-secondary">
            <div class="d-flex justify-content-center gap-3">
                <a href="https://facebook.com" target="_blank" class="text-white-50 hover-text-primary">
                    <i class="fab fa-facebook-f fa-lg"></i>
                </a>
                <a href="https://twitter.com" target="_blank" class="text-white-50 hover-text-info">
                    <i class="fab fa-twitter fa-lg"></i>
                </a>
                <a href="https://instagram.com" target="_blank" class="text-white-50 hover-text-danger">
                    <i class="fab fa-instagram fa-lg"></i>
                </a>
                <a href="https://linkedin.com" target="_blank" class="text-white-50 hover-text-primary">
                    <i class="fab fa-linkedin-in fa-lg"></i>
                </a>
                <a href="https://youtube.com" target="_blank" class="text-white-50 hover-text-danger">
                    <i class="fab fa-youtube fa-lg"></i>
                </a>
            </div>
            <p class="text-center text-white-50 small mt-2">
                &copy; <?php echo date('Y'); ?> ShopEasePro. All rights reserved.
            </p>
        </div>
        
        <!-- Logout Button -->
        <div class="mt-3">
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

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
.sidebar {
    background: linear-gradient(40deg, #1a1a2e, #16213e) !important;
    color: #fff !important;
    width: 250px;
    position: fixed;
    /* left: /0; */
    top: 75px; 
    height: 100vh;
    /* z-index: 1050; */
    transition: transform 0.3s ease-in-out;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
}

/* Online status indicator */
.online-status {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 12px;
    height: 12px;
    background: #06d6a0;
    border-radius: 50%;
    border: 2px solid #1a1a2e;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(6, 214, 160, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(6, 214, 160, 0); }
    100% { box-shadow: 0 0 0 0 rgba(6, 214, 160, 0); }
}

.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
    position: relative;
}

.sidebar .nav-link:hover {
    color: #fff !important;
    background: rgba(255, 255, 255, 0.1) !important;
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%) !important;
    color: #fff !important;
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
}

.sidebar .nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(135deg, #06d6a0, #4cc9f0);
    border-radius: 0 4px 4px 0;
}

.sidebar .badge {
    font-size: 10px;
    padding: 3px 6px;
    border-radius: 10px;
}

.sidebar .bg-warning {
    background: #ffb703 !important;
    color: #000 !important;
}

.sidebar .bg-danger {
    background: #ef476f !important;
}

.sidebar .bg-success {
    background: #06d6a0 !important;
    color: #000 !important;
}

.sidebar .btn-danger {
    background: linear-gradient(135deg, #ec4899 0%, #db2777 100%) !important;
    border: none !important;
    color: white !important;
    padding: 10px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.sidebar .btn-danger:hover {
    background: linear-gradient(135deg, #db2777 0%, #ec4899 100%) !important;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(219, 39, 119, 0.4);
}

/* Social Media Icons */
.text-white-50 {
    color: rgba(255, 255, 255, 0.5) !important;
    transition: all 0.3s ease;
}

.hover-text-primary:hover {
    color: #4361ee !important;
    transform: translateY(-2px);
}

.hover-text-info:hover {
    color: #4cc9f0 !important;
    transform: translateY(-2px);
}

.hover-text-danger:hover {
    color: #ef476f !important;
    transform: translateY(-2px);
}

/* Section Titles */
.text-muted {
    color: rgba(255, 255, 255, 0.5) !important;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Border */
.border-secondary {
    border-color: rgba(255, 255, 255, 0.1) !important;
}

/* Avatar */
.avatar {
    position: relative;
    display: inline-block;
}

.avatar img {
    border: 3px solid #4361ee;
    transition: transform 0.3s ease;
}

.avatar:hover img {
    transform: scale(1.05);
}

/* Sidebar Toggle Button */
.sidebar-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1040;
    background: linear-gradient(135deg, #4361ee, #3a0ca3) !important;
    border: none !important;
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.4);
    transition: all 0.3s ease;
    display: none;
}

.sidebar-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(67, 97, 238, 0.6);
}

/* Sidebar Close Button */
.sidebar-close {
    position: absolute;
    top: 10px;
    right: 10px;
    background: transparent;
    border: none;
    color: white;
    font-size: 1.5rem;
    display: none;
    z-index: 1060;
    transition: transform 0.3s ease;
}

.sidebar-close:hover {
    transform: rotate(90deg);
}

/* Overlay for mobile */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(3px);
    z-index: 1045;
    display: none;
}

.sidebar-overlay.show {
    display: block;
}

/* Mobile styles */
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        top: 0;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
    }
    
    .sidebar-toggle {
        display: flex;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding-bottom: 80px;
    }
    
    .sidebar .avatar img {
        width: 60px;
        height: 60px;
    }
}

/* Desktop styles */
@media (min-width: 992px) {
    .sidebar {
        transform: translateX(0);
    }
    
    .sidebar-toggle {
        display: none;
    }
    
    .sidebar-close {
        display: none;
    }
    
    .main-content {
        margin-left: 250px;
    }
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    
    // Create overlay if it doesn't exist
    if (!overlay) {
        const newOverlay = document.createElement('div');
        newOverlay.className = 'sidebar-overlay';
        newOverlay.id = 'sidebarOverlay';
        document.body.appendChild(newOverlay);
    }
    
    function closeSidebar() {
        sidebar.classList.remove('show');
        document.getElementById('sidebarOverlay').classList.remove('show');
        document.body.style.overflow = '';
    }
    
    function openSidebar() {
        sidebar.classList.add('show');
        document.getElementById('sidebarOverlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    // Toggle sidebar on mobile
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', openSidebar);
    }
    
    // Close sidebar with close button
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar when clicking on overlay
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('sidebar-overlay')) {
            closeSidebar();
        }
    });
    
    // Close sidebar on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
    
    // Auto-close sidebar on mobile when clicking a link
    if (window.innerWidth < 992) {
        const sidebarLinks = sidebar.querySelectorAll('.nav-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeSidebar();
            });
        });
    }
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 992) {
                closeSidebar();
            }
        }, 250);
    });
    
    // Add active class based on current URL
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