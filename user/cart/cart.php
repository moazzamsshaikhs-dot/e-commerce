<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login to view your cart';
    redirect(SITE_URL . 'login.php');
}

$page_title = 'My Cart';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get cart items with product details
$stmt = $db->prepare("
    SELECT ci.*, 
           p.name, p.description, p.price, p.old_price, p.image, 
           p.stock, p.vendor_id, p.approved_status,
           u.username as vendor_name, u.full_name as vendor_full_name,
           c.name as category_name
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    LEFT JOIN users u ON p.vendor_id = u.id
    LEFT JOIN categories c ON p.category = c.slug
    WHERE ci.user_id = ?
    ORDER BY ci.added_at DESC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$total_items = 0;

foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

// Shipping and tax calculation
$shipping = ($subtotal > 50) ? 0 : 10.00;
$tax_rate = 0.10; // 10%
$tax_amount = $subtotal * $tax_rate;
$total_amount = $subtotal + $shipping + $tax_amount;

// Log activity
logUserActivity($user_id, 'cart_view', 'Viewed shopping cart');
?>

<div class="cart-page">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>user/orders/shop.php">Shop</a></li>
            <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
        </ol>
    </nav>

    <h1 class="mb-4">Shopping Cart</h1>
    
    <?php if (empty($cart_items)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                <h4>Your cart is empty</h4>
                <p class="text-muted mb-4">Add some products to your cart</p>
                <a href="<?php echo SITE_URL; ?>user/orders/shop.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Cart Items (<?php echo $total_items; ?>)</h5>
                            <button class="btn btn-sm btn-outline-danger" id="clearCartBtn">
                                <i class="fas fa-trash me-1"></i> Clear Cart
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Product</th>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th style="width: 150px;">Quantity</th>
                                        <th>Subtotal</th>
                                        <th style="width: 80px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                        <tr id="cart-row-<?php echo $item['product_id']; ?>">
                                            <!-- Product Image -->
                                            <td>
                                                <a href="product-details.php?id=<?php echo $item['product_id']; ?>">
                                                    <?php if ($item['image']): ?>
                                                        <img src="<?php echo SITE_URL . 'assets/images/products/' . $item['image']; ?>" 
                                                             class="img-fluid rounded" 
                                                             style="width: 80px; height: 80px; object-fit: cover;"
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                             style="width: 80px; height: 80px;">
                                                            <i class="fas fa-box text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                            </td>
                                            
                                            <!-- Product Name and Info -->
                                            <td>
                                                <h6 class="mb-1">
                                                    <a href="product-details.php?id=<?php echo $item['product_id']; ?>" 
                                                       class="text-decoration-none text-dark">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted d-block">Category: <?php echo $item['category_name']; ?></small>
                                                <?php if ($item['vendor_name']): ?>
                                                    <small class="text-muted">Vendor: <?php echo $item['vendor_full_name'] ?? $item['vendor_name']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Price -->
                                            <td>
                                                <div class="text-primary fw-bold">
                                                    $<?php echo number_format($item['price'], 2); ?>
                                                </div>
                                                <?php if ($item['old_price'] && $item['old_price'] > $item['price']): ?>
                                                    <small class="text-muted text-decoration-line-through">
                                                        $<?php echo number_format($item['old_price'], 2); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Quantity Controls -->
                                            <td>
                                                <div class="input-group" style="max-width: 120px;">
                                                    <button class="btn btn-outline-secondary decrease-qty" 
                                                            data-product-id="<?php echo $item['product_id']; ?>">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input type="number" 
                                                           class="form-control text-center quantity-input" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" 
                                                           max="<?php echo $item['stock']; ?>"
                                                           data-product-id="<?php echo $item['product_id']; ?>">
                                                    <button class="btn btn-outline-secondary increase-qty" 
                                                            data-product-id="<?php echo $item['product_id']; ?>"
                                                            data-max-stock="<?php echo $item['stock']; ?>">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted d-block mt-1">
                                                    <?php if ($item['stock'] < 10 && $item['stock'] > 0): ?>
                                                        <span class="text-warning">Only <?php echo $item['stock']; ?> left</span>
                                                    <?php elseif ($item['stock'] == 0): ?>
                                                        <span class="text-danger">Out of stock</span>
                                                    <?php else: ?>
                                                        <span class="text-success">In stock</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            
                                            <!-- Subtotal -->
                                            <td>
                                                <div class="fw-bold">
                                                    $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Actions -->
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger remove-item" 
                                                        data-product-id="<?php echo $item['product_id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($item['name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Continue Shopping -->
                <div class="mt-4">
                    <a href="<?php echo SITE_URL; ?>user/orders/shop.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Continue Shopping
                    </a>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Price Breakdown -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal (<?php echo $total_items; ?> items)</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping</span>
                                <span class="<?php echo $shipping == 0 ? 'text-success' : ''; ?>">
                                    <?php echo $shipping == 0 ? 'FREE' : '$' . number_format($shipping, 2); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax (<?php echo ($tax_rate * 100); ?>%)</span>
                                <span>$<?php echo number_format($tax_amount, 2); ?></span>
                            </div>
                            
                            <?php if ($subtotal < 50): ?>
                                <div class="alert alert-info py-2 small mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Add $<?php echo number_format(50 - $subtotal, 2); ?> more for FREE shipping!
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Total</strong>
                                <h4 class="text-primary mb-0">$<?php echo number_format($total_amount, 2); ?></h4>
                            </div>
                        </div>
                        
                        <!-- Checkout Button -->
                        <a href="<?php echo SITE_URL; ?>user/checkout/checkout.php" 
                           class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-lock me-2"></i> Proceed to Checkout
                        </a>
                        
                        <!-- Payment Methods -->
                        <div class="text-center mb-3">
                            <small class="text-muted d-block mb-2">We accept</small>
                            <div class="d-flex justify-content-center gap-2">
                                <i class="fab fa-cc-visa fa-2x text-primary"></i>
                                <i class="fab fa-cc-mastercard fa-2x text-danger"></i>
                                <i class="fab fa-cc-paypal fa-2x text-info"></i>
                                <i class="fab fa-cc-stripe fa-2x text-success"></i>
                            </div>
                        </div>
                        
                        <!-- Security Info -->
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1 text-success"></i>
                                100% Secure Payment
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Coupon Code -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h6 class="mb-3">Have a coupon?</h6>
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Enter coupon code">
                            <button class="btn btn-outline-primary" type="button">Apply</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Update quantity on input change
    $('.quantity-input').on('change', function() {
        const productId = $(this).data('product-id');
        const quantity = parseInt($(this).val());
        const maxStock = $(this).attr('max');
        
        if (quantity < 1) {
            $(this).val(1);
            return;
        }
        
        if (quantity > maxStock) {
            showToast('Cannot add more than available stock', 'warning');
            $(this).val(maxStock);
            return;
        }
        
        updateCartQuantity(productId, quantity);
    });
    
    // Increase quantity
    $('.increase-qty').click(function() {
        const productId = $(this).data('product-id');
        const maxStock = $(this).data('max-stock');
        const input = $(this).siblings('.quantity-input');
        const currentQty = parseInt(input.val());
        
        if (currentQty < maxStock) {
            input.val(currentQty + 1);
            updateCartQuantity(productId, currentQty + 1);
        } else {
            showToast('Cannot add more than available stock', 'warning');
        }
    });
    
    // Decrease quantity
    $('.decrease-qty').click(function() {
        const productId = $(this).data('product-id');
        const input = $(this).siblings('.quantity-input');
        const currentQty = parseInt(input.val());
        
        if (currentQty > 1) {
            input.val(currentQty - 1);
            updateCartQuantity(productId, currentQty - 1);
        } else {
            removeFromCart(productId, $(this).closest('tr'));
        }
    });
    
    // Remove item
    $('.remove-item').click(function() {
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        const row = $(this).closest('tr');
        
        if (confirm(`Remove "${productName}" from cart?`)) {
            removeFromCart(productId, row);
        }
    });
    
    // Clear cart
    $('#clearCartBtn').click(function() {
        if (confirm('Are you sure you want to clear your entire cart?')) {
            $.ajax({
                url: '<?php echo SITE_URL; ?>user/ajax/clear-cart.php',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('.cart-count').text('0');
                        location.reload();
                    } else {
                        showToast(response.message || 'Error clearing cart', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Network error: ' + error, 'error');
                }
            });
        }
    });
    
    // Cart functions
    function updateCartQuantity(productId, quantity) {
        $.ajax({
            url: '<?php echo SITE_URL; ?>user/ajax/update-cart.php',
            type: 'POST',
            data: {
                product_id: productId,
                quantity: quantity
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update header cart count
                    $('.cart-count').text(response.cart_count);
                    
                    // Reload page to update totals
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    showToast(response.message || 'Error updating cart', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Network error: ' + error, 'error');
            }
        });
    }
    
    function removeFromCart(productId, row) {
        $.ajax({
            url: '<?php echo SITE_URL; ?>user/ajax/remove-from-cart.php',
            type: 'POST',
            data: { product_id: productId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update header cart count
                    $('.cart-count').text(response.cart_count);
                    
                    // Remove row with animation
                    row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // If cart is empty, reload page
                        if ($('tbody tr').length === 0) {
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        } else {
                            // Reload to update totals
                            location.reload();
                        }
                    });
                    
                    showToast('Removed from cart', 'success');
                } else {
                    showToast(response.message || 'Error removing item', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Network error: ' + error, 'error');
            }
        });
    }
    
    function showToast(message, type = 'info') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0 position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
});
</script>

<style>
.cart-page .sticky-top {
    z-index: 1020;
}

.cart-page .table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.cart-page .table td {
    vertical-align: middle;
}

.cart-page .quantity-input {
    width: 60px;
}

.cart-page .btn-group {
    width: 120px;
}

.cart-page .alert {
    border-radius: 8px;
}

.cart-page .text-success {
    color: #198754 !important;
}

.cart-page .text-warning {
    color: #ffc107 !important;
}

.cart-page .text-danger {
    color: #dc3545 !important;
}
</style>

<?php require_once '../../includes/footer.php'; ?>