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
<aside class="sidebar bg-dark text-white " id="sidebar">
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
            </div>
            <h6 class="mb-1"><?php echo $_SESSION['full_name']; ?></h6>
            <small class="text-black-50">
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
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'users') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/users/users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'products') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/products/products.php">
                        <i class="fas fa-box me-2"></i> Products
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'orders') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/orders/orders.php">
                        <i class="fas fa-shopping-cart me-2"></i> Orders
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'vendors') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/vendors/dashboard.php">
                        <i class="fas fa-store me-2"></i> Vendors
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link <?php echo (strpos($current_page, 'payments') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL; ?>admin/payments/index.php">
                        <i class="fas fa-credit-card me-2"></i> Payments
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
                <h6 class="text-uppercase text-muted mb-3">Reports</h6>
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
    background: linear-gradient(40deg, #fff, #fff 100%) !important;
    color: #333 !important;
    width: 240px;
    position: fixed;
    left: 0;
    top: 77px;
    height: 100vh;
    z-index: 1050;
    transition: transform 0.3s ease-in-out;
    overflow-y: auto;
}

.sidebar .nav-link {
    color:#333 !important;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
}

.sidebar .nav-link:hover {
    color: #333 !important;
    background: rgba(255, 255, 255, 0.1) !important;
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    /* background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%) !important; */
    color: #333 !important;
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
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

.sidebar-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1040;
    background: linear-gradient(135deg, #3a0ca3, #4361ee) !important;
    border: none !important;
    color: white;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
    transition: all 0.3s ease;
}

.sidebar-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
}

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
}

/* Mobile styles */
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
    }
    
    .main-content {
        margin-left: 0 !important;
    }
    
    /* Add overlay when sidebar is open */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040;
        display: none;
    }
    
    .sidebar-overlay.show {
        display: block;
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
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    
    // Create overlay for mobile
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    
    // Toggle sidebar on mobile
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
    });
    
    // Close sidebar on mobile
    sidebarClose.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
    
    // Close sidebar when clicking on overlay
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
    
    // Auto-close sidebar on mobile when clicking a link
    if (window.innerWidth < 992) {
        const sidebarLinks = sidebar.querySelectorAll('.nav-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        });
    }
    
    // Close sidebar on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    });
    
    // Add active class to parent menu items
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href)) {
            link.classList.add('active');
            
            // Also activate parent menu if exists
            const parentMenu = link.closest('.collapse');
            if (parentMenu) {
                const parentToggle = document.querySelector('[href="#' + parentMenu.id + '"]');
                if (parentToggle) {
                    parentToggle.classList.remove('collapsed');
                    parentToggle.setAttribute('aria-expanded', 'true');
                }
            }
        }
    });
});
</script>