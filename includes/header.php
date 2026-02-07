<?php
// Check if config is loaded
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Get cart and wishlist counts (only if user is logged in)
$cart_count = 0;
$wishlist_count = 0;

if (isLoggedIn()) {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Get cart count
    $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_result = $stmt->fetch();
    $cart_count = $cart_result['total'] ?? 0;
    
    // Get wishlist count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_result = $stmt->fetch();
    $wishlist_count = $wishlist_result['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/dashboard.css">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>assets/images/favicon.ico">
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            padding-top: 70px;
        }
        
        .auth-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        
        .auth-links a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        /* Cart and Wishlist Count Badge Styles */
        .nav-link .cart-count,
        .nav-link .wishlist-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .nav-link {
            position: relative;
        }
        
        .nav-link .badge {
            position: absolute;
            top: -5px;
            right: -5px;
        }
        
        .cart-icon, .wishlist-icon {
            position: relative;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo SITE_URL; ?>index.php">
                <i class="fas fa-shopping-bag text-primary me-2"></i>
                ShopEase<span class="text-primary">Pro</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <?php if(!isLoggedIn()): ?>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>index.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                <div class="d-lg-none">
                    <a class="btn btn-primary me-2" href="<?php echo SITE_URL; ?>login.php">
                        <i class="fas fa-sign-in-alt me-1"></i> Login
                    </a>
                    <a class="btn btn-outline-primary" href="<?php echo SITE_URL; ?>signup.php">
                        <i class="fas fa-user-plus me-1"></i> Sign Up
                    </a>
                </div>
            
                    <?php endif;?>
                    
                    
                    <?php if (isLoggedIn()): ?>
                    
                        <!-- Cart and Wishlist for logged in users -->
                        <li class="nav-item">
                            <a class="nav-link cart-icon <?php echo ($current_page == 'cart.php') ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>user/cart/cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <?php if ($cart_count > 0): ?>
                                    <span class="cart-count badge bg-danger"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link wishlist-icon <?php echo ($current_page == 'wishlist.php') ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>user/wishlist/wishlist.php">
                                <i class="fas fa-heart"></i>
                                <?php if ($wishlist_count > 0): ?>
                                    <span class="wishlist-count badge bg-danger"><?php echo $wishlist_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>

                        <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'shop.php') ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>user/orders/shop.php">
                            <i class="fas fa-store me-1"></i> Shop
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <?php if (isLoggedIn()): ?>
                        <!-- User is logged in -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                               data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if (isAdmin()): ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>admin/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Admin Dashboard
                                    </a></li>
                                <?php elseif (isVendor()): ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>vendor/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Vendor Dashboard
                                    </a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>user/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> User Dashboard
                                    </a></li>
                                <?php endif; ?>
                                
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>profile.php">
                                    <i class="fas fa-user me-2"></i> My Profile
                                </a></li>
                                
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>user/orders/orders.php">
                                    <i class="fas fa-shopping-bag me-2"></i> My Orders
                                </a></li>
                                
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- User is not logged in -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary <?php echo ($current_page == 'signup.php') ? 'active' : ''; ?>" 
                               href="<?php echo SITE_URL; ?>signup.php">
                                <i class="fas fa-user-plus me-1"></i> Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Messages Display -->
    <div class="container mt-3">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['info'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?php 
                    echo $_SESSION['info']; 
                    unset($_SESSION['info']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php 
                    echo $_SESSION['warning']; 
                    unset($_SESSION['warning']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <!-- <main class="container-fluid py-4">
        <div class="container"> -->