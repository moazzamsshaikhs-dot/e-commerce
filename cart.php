<?php
session_start();

require_once 'includes/config.php';

// Get cart items for logged in user
$cart_items = [];
$cart_total = 0;

if (isLoggedIn()) {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Get cart items with product details
    $stmt = $db->prepare("
        SELECT 
            ci.*,
            p.name as product_name,
            p.price as product_price,
            p.image as product_image,
            p.stock as stock_quantity,
            v.username as vendor_name,
            v.id as vendor_id
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN users v ON p.vendor_id = v.id
        WHERE ci.user_id = ?
        ORDER BY ci.added_at DESC
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total
    foreach ($cart_items as $item) {
        $cart_total += $item['product_price'] * $item['quantity'];
    }
} else {
    // For guest users, use session cart
    if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
        $db = getDB();
        $product_ids = array_column($_SESSION['guest_cart'], 'product_id');
        
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $db->prepare("
                SELECT 
                    p.id,
                    p.name as product_name,
                    p.price as product_price,
                    p.image as product_image,
                    p.stock as stock_quantity,
                    p.vendor_id,
                    v.username as vendor_name,
                    ? as quantity,
                    ? as user_id
                FROM products p
                JOIN users v ON p.vendor_id = v.id
                WHERE p.id IN ($placeholders)
            ");
            
            // Prepare parameters array
            $params = [0, 0]; // Placeholders for quantity and user_id
            foreach ($product_ids as $id) {
                $params[] = $id;
            }
            $stmt->execute($params);
            
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Map quantities from session
            foreach ($products as $product) {
                foreach ($_SESSION['guest_cart'] as $guest_item) {
                    if ($guest_item['product_id'] == $product['id']) {
                        $product['quantity'] = $guest_item['quantity'];
                        $cart_items[] = $product;
                        $cart_total += $product['product_price'] * $product['quantity'];
                        break;
                    }
                }
            }
        }
    }
}

$page_title = 'Shopping Cart';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            padding-top: 70px;
        }
        
        .cart-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            background-color: #fafafa;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }
        
        .remove-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .remove-btn:hover {
            color: #c82333;
            transform: scale(1.1);
        }
        
        .summary-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 90px;
        }
        
        .checkout-btn {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            border: none;
            padding: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.4);
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-cart i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .out-of-stock {
            color: #dc3545;
            font-size: 12px;
        }
        
        .vendor-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 11px;
        }
        
        .select-all {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        
        .product-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .cart-actions {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 15px 0;
            border-top: 1px solid #eee;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shopping-bag text-primary me-2"></i>
                ShopEase<span class="text-primary">Pro</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="category.php">
                            <i class="fas fa-th-large me-1"></i> Categories
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                               data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if (isAdmin()): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Admin Dashboard
                                    </a></li>
                                <?php elseif (isVendor()): ?>
                                    <li><a class="dropdown-item" href="vendor/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> Vendor Dashboard
                                    </a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="user/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i> My Dashboard
                                    </a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="cart.php">
                                    <i class="fas fa-shopping-cart me-2"></i> My Cart
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary" href="signup.php">
                                <i class="fas fa-user-plus me-1"></i> Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container py-5">
        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="cart-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">
                            <i class="fas fa-shopping-cart me-2 text-primary"></i>Shopping Cart
                            <?php if (!empty($cart_items)): ?>
                                <span class="badge bg-primary" id="item-count"><?php echo count($cart_items); ?> items</span>
                            <?php endif; ?>
                        </h4>
                        <?php if (!empty($cart_items)): ?>
                            <button class="btn btn-outline-danger btn-sm" onclick="clearCart()">
                                <i class="fas fa-trash me-1"></i> Clear Cart
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($cart_items)): ?>
                        <div class="empty-cart">
                            <i class="fas fa-shopping-basket"></i>
                            <h4>Your cart is empty</h4>
                            <p class="text-muted">Looks like you haven't added any items to your cart yet.</p>
                            <a href="category.php" class="btn btn-primary mt-3">
                                <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Select All -->
                        <div class="select-all mb-3 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll" checked>
                                <label class="form-check-label fw-bold" for="selectAll">
                                    Select All Items
                                </label>
                            </div>
                        </div>
                        
                        <div id="cart-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item row align-items-center" data-id="<?php echo $item['id']; ?>">
                                    <div class="col-md-1">
                                        <input type="checkbox" class="product-checkbox item-checkbox" 
                                               data-id="<?php echo $item['id']; ?>" 
                                               data-price="<?php echo $item['product_price']; ?>"
                                               data-quantity="<?php echo $item['quantity']; ?>"
                                               checked>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <?php if ($item['product_image']): ?>
                                            <img src="assets/images/products/<?php echo $item['product_image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 class="product-image">
                                        <?php else: ?>
                                            <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <h6 class="mb-1">
                                            <a href="product-details.php?id=<?php echo $item['id']; ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                            </a>
                                        </h6>
                                        <span class="vendor-badge">
                                            <i class="fas fa-store me-1"></i>
                                            <?php echo htmlspecialchars($item['vendor_name']); ?>
                                        </span>
                                        
                                        <?php if ($item['stock_quantity'] < $item['quantity']): ?>
                                            <div class="out-of-stock mt-1">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Only <?php echo $item['stock_quantity']; ?> available
                                            </div>
                                        <?php elseif ($item['stock_quantity'] == 0): ?>
                                            <div class="out-of-stock mt-1">
                                                <i class="fas fa-times-circle me-1"></i>
                                                Out of stock
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <span class="text-muted">Price:</span>
                                        <strong>$<?php echo number_format($item['product_price'], 2); ?></strong>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <input type="number" 
                                               class="quantity-input item-quantity" 
                                               value="<?php echo $item['quantity']; ?>"
                                               data-id="<?php echo $item['id']; ?>"
                                               min="1" 
                                               max="<?php echo $item['stock_quantity']; ?>"
                                               <?php echo ($item['stock_quantity'] == 0) ? 'disabled' : ''; ?>>
                                    </div>
                                    
                                    <div class="col-md-2 text-end">
                                        <strong class="text-primary item-total">
                                            $<?php echo number_format($item['product_price'] * $item['quantity'], 2); ?>
                                        </strong>
                                        <button class="remove-btn d-block mt-2" 
                                                onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Cart Actions -->
                        <div class="cart-actions">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <span class="text-muted">Selected Items:</span>
                                    <strong id="selected-count"><?php echo count($cart_items); ?></strong>
                                </div>
                                <div class="col-md-6 text-end">
                                    <span class="h5 me-3">Total: $<span id="selected-total"><?php echo number_format($cart_total, 2); ?></span></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Continue Shopping -->
                <?php if (!empty($cart_items)): ?>
                    <div class="mt-3">
                        <a href="category.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <?php if (!empty($cart_items)): ?>
                    <div class="summary-card p-4">
                        <h5 class="mb-4">
                            <i class="fas fa-receipt me-2 text-primary"></i>Order Summary
                        </h5>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal (<span id="summary-count"><?php echo count($cart_items); ?></span> items)</span>
                            <span>$<span id="summary-subtotal"><?php echo number_format($cart_total, 2); ?></span></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <span class="text-success">Free</span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Tax</span>
                            <span>$0.00</span>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <strong>Total</strong>
                            <strong class="text-primary h4 mb-0">$<span id="summary-total"><?php echo number_format($cart_total, 2); ?></span></strong>
                        </div>
                        
                        <?php if (isLoggedIn()): ?>
                            <button class="btn btn-primary checkout-btn w-100 text-white" onclick="proceedToCheckout()" id="checkoutBtn">
                                <i class="fas fa-lock me-2"></i>Proceed to Checkout
                            </button>
                            
                            <!-- Hidden form for checkout -->
                            <form id="checkoutForm" method="POST" action="user/orders/multi-item-checkout.php" style="display: none;">
                                <input type="hidden" name="selected_items" id="selected_items_input">
                            </form>
                        <?php else: ?>
                            <a href="login.php?redirect=cart.php" class="btn btn-primary checkout-btn w-100 text-white">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Checkout
                            </a>
                        <?php endif; ?>
                        
                        <!-- Security Badges -->
                        <div class="text-center mt-4">
                            <div class="d-flex justify-content-center gap-3 text-muted small">
                                <span><i class="fas fa-shield-alt me-1"></i> Secure</span>
                                <span><i class="fas fa-lock me-1"></i> SSL</span>
                                <span><i class="fas fa-credit-card me-1"></i> Cards</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            updateSelectedTotals();
            
            // Select All functionality
            $('#selectAll').change(function() {
                $('.item-checkbox').prop('checked', $(this).prop('checked'));
                updateSelectedTotals();
            });
            
            // Individual checkbox change
            $('.item-checkbox').change(function() {
                updateSelectedTotals();
                
                // Update select all checkbox
                let allChecked = $('.item-checkbox:checked').length === $('.item-checkbox').length;
                $('#selectAll').prop('checked', allChecked);
            });
            
            // Quantity change
            $('.item-quantity').on('input', function() {
                let productId = $(this).data('id');
                let quantity = $(this).val();
                
                // Update checkbox data-quantity attribute
                $(`.item-checkbox[data-id="${productId}"]`).attr('data-quantity', quantity);
                
                // Update item total
                let price = parseFloat($(`.item-checkbox[data-id="${productId}"]`).data('price'));
                let itemTotal = price * quantity;
                $(this).closest('.cart-item').find('.item-total').text('$' + itemTotal.toFixed(2));
                
                updateSelectedTotals();
            });
        });
        
        function updateSelectedTotals() {
            let selectedTotal = 0;
            let selectedCount = 0;
            
            $('.item-checkbox:checked').each(function() {
                let price = parseFloat($(this).data('price'));
                let quantity = parseInt($(this).data('quantity'));
                selectedTotal += price * quantity;
                selectedCount++;
            });
            
            $('#selected-count').text(selectedCount);
            $('#selected-total').text(selectedTotal.toFixed(2));
            $('#summary-count').text(selectedCount);
            $('#summary-subtotal').text(selectedTotal.toFixed(2));
            $('#summary-total').text(selectedTotal.toFixed(2));
        }
        
        function proceedToCheckout() {
            let selectedItems = [];
            
            $('.item-checkbox:checked').each(function() {
                selectedItems.push({
                    product_id: $(this).data('id'),
                    quantity: parseInt($(this).data('quantity'))
                });
            });
            
            if (selectedItems.length === 0) {
                alert('Please select at least one item to checkout');
                return;
            }
            
            // Store in session via AJAX
            $.ajax({
                url: 'user/ajax/set-checkout-items.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ items: selectedItems }),
                success: function(response) {
                    if (response.success) {
                        window.location.href = 'user/orders/multi-item-checkout.php';
                    } else {
                        alert('Error: ' + (response.message || 'Failed to proceed to checkout'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('Error proceeding to checkout. Please try again.');
                }
            });
        }
        
        function updateQuantity(productId, quantity) {
            $.ajax({
                url: 'user/ajax/update-cart.php',
                type: 'POST',
                data: {
                    product_id: productId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Failed to update quantity');
                        location.reload();
                    }
                },
                error: function() {
                    alert('Error updating quantity');
                    location.reload();
                }
            });
        }
        
        function removeFromCart(productId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            $.ajax({
                url: 'user/ajax/remove-from-cart.php',
                type: 'POST',
                data: {
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Failed to remove item');
                    }
                },
                error: function() {
                    alert('Error removing item');
                }
            });
        }
        
        function clearCart() {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                return;
            }
            
            $.ajax({
                url: 'user/ajax/clear-cart.php',
                type: 'POST',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to clear cart');
                    }
                },
                error: function() {
                    alert('Error clearing cart');
                }
            });
        }
    </script>
</body>
</html>