<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login to checkout';
    redirect(SITE_URL . 'user/login.php?redirect=user/orders/checkout.php');
}

// Check if user is not admin/vendor
if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'vendor') {
    $_SESSION['error'] = 'Access denied. Customer checkout only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Checkout';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];

// Safe string function to handle null values
function safe_html($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// =============== GET USER INFORMATION ===============
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// =============== CHECK FOR SINGLE PRODUCT CHECKOUT ===============
$single_product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$single_quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;

if ($single_product_id > 0) {
    // For single product checkout, get the product
    $product_sql = "SELECT p.*, u.full_name as vendor_name 
                   FROM products p
                   LEFT JOIN users u ON p.vendor_id = u.id
                   WHERE p.id = ? AND p.approved_status = 'approved' AND p.stock > 0";
    $product_stmt = $db->prepare($product_sql);
    $product_stmt->execute([$single_product_id]);
    $single_product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($single_product) {
        // Create a single item cart
        $cart_items = [[
            'product_id' => $single_product['id'],
            'quantity' => min($single_quantity, $single_product['stock']),
            'name' => $single_product['name'],
            'price' => $single_product['price'],
            'stock' => $single_product['stock'],
            'image' => $single_product['image'],
            'vendor_id' => $single_product['vendor_id'],
            'vendor_name' => $single_product['vendor_name'] ?? 'Vendor'
        ]];
    } else {
        $_SESSION['error'] = 'Product not available';
        redirect('shop.php');
    }
} else {
    // Get cart items from database
    $cart_sql = "SELECT ci.*, p.name, p.price, p.stock, p.image, p.vendor_id,
                        u.full_name as vendor_name, u.vendor_rating
                 FROM cart_items ci
                 JOIN products p ON ci.product_id = p.id
                 LEFT JOIN users u ON p.vendor_id = u.id
                 WHERE ci.user_id = ?";
    $cart_stmt = $db->prepare($cart_sql);
    $cart_stmt->execute([$user_id]);
    $cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if cart is empty
if (empty($cart_items)) {
    $_SESSION['error'] = 'Your cart is empty';
    redirect('cart.php');
}

// =============== CALCULATE TOTALS ===============
$subtotal = 0;
$total_items = 0;
$vendors = [];

foreach ($cart_items as $item) {
    $item_total = $item['price'] * $item['quantity'];
    $subtotal += $item_total;
    $total_items += $item['quantity'];
    
    // Group items by vendor
    if (!isset($vendors[$item['vendor_id']])) {
        $vendors[$item['vendor_id']] = [
            'name' => $item['vendor_name'] ?? 'Vendor',
            'items' => [],
            'subtotal' => 0
        ];
    }
    $vendors[$item['vendor_id']]['items'][] = $item;
    $vendors[$item['vendor_id']]['subtotal'] += $item_total;
}

// Default shipping cost (can be calculated based on items/vendors)
$shipping_cost = 5.99;
$tax_rate = 10; // 10% tax
$tax_amount = ($subtotal * $tax_rate) / 100;
$total = $subtotal + $shipping_cost + $tax_amount;

// =============== GET PAYMENT METHODS FROM VENDORS ===============
$all_payment_methods = [
    'bank' => ['name' => 'Bank Transfer', 'icon' => 'fas fa-university', 'description' => 'Direct bank transfer'],
    'paypal' => ['name' => 'PayPal', 'icon' => 'fab fa-paypal', 'description' => 'Secure PayPal payments'],
    'stripe' => ['name' => 'Stripe', 'icon' => 'fab fa-stripe', 'description' => 'Secure Stripe payments'],
    'easypaisa' => ['name' => 'Easypaisa', 'icon' => 'fas fa-mobile-alt', 'description' => 'Mobile wallet'],
    'jazzcash' => ['name' => 'JazzCash', 'icon' => 'fas fa-mobile-alt', 'description' => 'Mobile wallet'],
    'cod' => ['name' => 'Cash on Delivery', 'icon' => 'fas fa-money-bill-wave', 'description' => 'Pay when you receive'],
    'visa' => ['name' => 'Visa Card', 'icon' => 'fab fa-cc-visa', 'description' => 'Visa credit/debit cards'],
    'mastercard' => ['name' => 'Mastercard', 'icon' => 'fab fa-cc-mastercard', 'description' => 'Mastercard'],
    'amex' => ['name' => 'American Express', 'icon' => 'fab fa-cc-amex', 'description' => 'American Express']
];

// Get vendor payment methods
$vendor_payment_methods = [];
foreach (array_keys($vendors) as $vendor_id) {
    $vendor_settings_sql = "SELECT payment_methods FROM vendor_settings WHERE vendor_id = ?";
    $vendor_settings_stmt = $db->prepare($vendor_settings_sql);
    $vendor_settings_stmt->execute([$vendor_id]);
    $vendor_settings = $vendor_settings_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($vendor_settings['payment_methods'])) {
        $methods = json_decode($vendor_settings['payment_methods'], true);
        if (is_array($methods)) {
            $vendor_payment_methods = array_merge($vendor_payment_methods, $methods);
        }
    }
}

// If no payment methods found, use all
if (empty($vendor_payment_methods)) {
    $vendor_payment_methods = array_keys($all_payment_methods);
} else {
    $vendor_payment_methods = array_unique($vendor_payment_methods);
}

// =============== GET COUNTRIES FOR ADDRESS ===============
$countries_sql = "SELECT * FROM countries WHERE is_active = 1 ORDER BY name";
$countries_stmt = $db->query($countries_sql);
$countries = $countries_stmt->fetchAll(PDO::FETCH_ASSOC);

// =============== HANDLE ORDER SUBMISSION ===============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? $shipping_address);
    $shipping_method = $_POST['shipping_method'] ?? 'standard';
    $payment_method = $_POST['payment_method'] ?? 'cod';
    $customer_notes = trim($_POST['customer_notes'] ?? '');
    $same_as_shipping = isset($_POST['same_as_shipping']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($shipping_address)) {
        $errors[] = 'Shipping address is required';
    }
    
    if (!$same_as_shipping && empty($billing_address)) {
        $errors[] = 'Billing address is required';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate order number
            $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Insert main order
            $order_sql = "INSERT INTO orders (
                user_id, order_number, total_amount, status, payment_method, payment_status,
                shipping_address, billing_address, shipping_method, customer_notes,
                order_date
            ) VALUES (?, ?, ?, 'pending', ?, 'pending', ?, ?, ?, ?, NOW())";
            
            $order_stmt = $db->prepare($order_sql);
            $order_stmt->execute([
                $user_id,
                $order_number,
                $total,
                $payment_method,
                $shipping_address,
                $billing_address,
                $shipping_method,
                $customer_notes
            ]);
            
            $order_id = $db->lastInsertId();
            
            // Insert order items and update stock
            foreach ($cart_items as $item) {
                $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                            VALUES (?, ?, ?, ?, ?)";
                $item_stmt = $db->prepare($item_sql);
                $item_total = $item['price'] * $item['quantity'];
                $item_stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item_total
                ]);
                
                // Update product stock
                $stock_sql = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $stock_stmt = $db->prepare($stock_sql);
                $stock_stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Add order status history
            $history_sql = "INSERT INTO order_status_history (order_id, status, changed_by, notes)
                           VALUES (?, 'pending', ?, 'Order placed successfully')";
            $history_stmt = $db->prepare($history_sql);
            $history_stmt->execute([$order_id, $user_id]);
            
            // Clear cart only if not single product checkout
            if ($single_product_id == 0) {
                $clear_sql = "DELETE FROM cart_items WHERE user_id = ?";
                $clear_stmt = $db->prepare($clear_sql);
                $clear_stmt->execute([$user_id]);
            }
            
            // Log activity
            if (function_exists('logUserActivity')) {
                logUserActivity($user_id, 'order_placed', 'Placed order #' . $order_number);
            }
            
            $db->commit();
            
            // Redirect to order confirmation
            $_SESSION['success'] = 'Order placed successfully!';
            redirect('order-confirmation.php?id=' . $order_id);
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Order placement error: " . $e->getMessage());
            $_SESSION['error'] = 'Error placing order: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}
?>

<div class="checkout-page">
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>user/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="cart.php">Cart</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>

        <!-- Display Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Checkout Form -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Checkout Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="checkoutForm">
                            <!-- Contact Information -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2">Contact Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" value="<?php echo safe_html($user['full_name'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" value="<?php echo safe_html($user['email'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" value="<?php echo safe_html($user['phone'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Address -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2">Shipping Address</h6>
                                <div class="mb-3">
                                    <label class="form-label">Address <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="shipping_address" id="shipping_address" rows="3" required><?php echo safe_html($user['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Country</label>
                                        <select class="form-select" id="shipping_country" name="shipping_country">
                                            <option value="">Select Country</option>
                                            <?php foreach ($countries as $country): ?>
                                                <option value="<?php echo $country['code']; ?>" 
                                                        data-currency="<?php echo $country['currency_code']; ?>"
                                                        data-symbol="<?php echo $country['currency_symbol']; ?>">
                                                    <?php echo safe_html($country['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" id="shipping_city" name="shipping_city">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" id="shipping_postal" name="shipping_postal">
                                    </div>
                                </div>
                            </div>

                            <!-- Billing Address -->
                            <div class="mb-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="same_as_shipping" name="same_as_shipping" checked>
                                    <label class="form-check-label" for="same_as_shipping">
                                        Billing address same as shipping
                                    </label>
                                </div>
                                <div id="billing_address_section" style="display: none;">
                                    <h6 class="border-bottom pb-2">Billing Address</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="billing_address" id="billing_address" rows="3"></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Country</label>
                                            <select class="form-select" id="billing_country" name="billing_country">
                                                <option value="">Select Country</option>
                                                <?php foreach ($countries as $country): ?>
                                                    <option value="<?php echo $country['code']; ?>">
                                                        <?php echo safe_html($country['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">City</label>
                                            <input type="text" class="form-control" id="billing_city" name="billing_city">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Postal Code</label>
                                            <input type="text" class="form-control" id="billing_postal" name="billing_postal">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Method -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2">Shipping Method</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="shipping_method" id="shipping_standard" value="standard" checked>
                                            <label class="form-check-label" for="shipping_standard">
                                                <strong>Standard Shipping</strong><br>
                                                <small class="text-muted">3-5 business days</small>
                                                <br>
                                                <span class="text-primary">$5.99</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="shipping_method" id="shipping_express" value="express">
                                            <label class="form-check-label" for="shipping_express">
                                                <strong>Express Shipping</strong><br>
                                                <small class="text-muted">1-2 business days</small>
                                                <br>
                                                <span class="text-primary">$12.99</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="shipping_method" id="shipping_overnight" value="overnight">
                                            <label class="form-check-label" for="shipping_overnight">
                                                <strong>Overnight Shipping</strong><br>
                                                <small class="text-muted">Next day delivery</small>
                                                <br>
                                                <span class="text-primary">$24.99</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2">Payment Method</h6>
                                <div class="row">
                                    <?php foreach ($vendor_payment_methods as $method): ?>
                                        <?php if (isset($all_payment_methods[$method])): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="payment-method-card">
                                                    <input class="form-check-input" type="radio" name="payment_method" 
                                                           id="payment_<?php echo $method; ?>" 
                                                           value="<?php echo $method; ?>"
                                                           <?php echo $method === 'cod' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="payment_<?php echo $method; ?>">
                                                        <i class="<?php echo $all_payment_methods[$method]['icon']; ?> me-2"></i>
                                                        <?php echo $all_payment_methods[$method]['name']; ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $all_payment_methods[$method]['description']; ?></small>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Bank Transfer Details (shown only if bank transfer selected) -->
                            <div class="mb-4" id="bank_details_section" style="display: none;">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">Bank Transfer Instructions</h6>
                                    <p class="mb-2">Please transfer the total amount to the following account:</p>
                                    <p class="mb-1"><strong>Bank:</strong> Example Bank</p>
                                    <p class="mb-1"><strong>Account Name:</strong> Your Company Name</p>
                                    <p class="mb-1"><strong>Account Number:</strong> 1234567890</p>
                                    <p class="mb-1"><strong>IBAN:</strong> PK12ABCD1234567890</p>
                                    <p class="mb-0"><strong>SWIFT Code:</strong> EXAMPLPK</p>
                                    <hr>
                                    <p class="mb-0 small">Your order will be processed after payment confirmation.</p>
                                </div>
                            </div>

                            <!-- Order Notes -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2">Order Notes (Optional)</h6>
                                <textarea class="form-control" name="customer_notes" rows="3" 
                                          placeholder="Special instructions for delivery..."></textarea>
                            </div>

                            <button type="submit" name="place_order" class="btn btn-primary btn-lg w-100" id="placeOrderBtn">
                                <i class="fas fa-check-circle me-2"></i>Place Order
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Items List -->
                        <div class="order-items mb-3" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                                    <div class="flex-shrink-0">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?php echo SITE_URL . 'assets/images/products/' . $item['image']; ?>" 
                                                 alt="<?php echo safe_html($item['name']); ?>"
                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                 class="rounded"
                                                 onerror="this.src='<?php echo SITE_URL; ?>assets/images/no-image.png';">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-box text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0"><?php echo safe_html(substr($item['name'], 0, 30)); ?></h6>
                                        <small class="text-muted">
                                            Qty: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?>
                                        </small>
                                    </div>
                                    <div class="fw-bold">
                                        $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Totals -->
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td>Subtotal (<?php echo $total_items; ?> items)</td>
                                <td class="text-end">$<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Shipping</td>
                                <td class="text-end" id="shipping_display">$<?php echo number_format($shipping_cost, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Tax (<?php echo $tax_rate; ?>%)</td>
                                <td class="text-end">$<?php echo number_format($tax_amount, 2); ?></td>
                            </tr>
                            <tr class="fw-bold fs-5">
                                <td>Total</td>
                                <td class="text-end text-primary" id="total_display">$<?php echo number_format($total, 2); ?></td>
                            </tr>
                        </table>

                        <!-- Payment Methods Preview -->
                        <div class="mt-3 pt-3 border-top">
                            <small class="text-muted d-block mb-2">Accepted Payment Methods:</small>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($vendor_payment_methods as $method): ?>
                                    <?php if (isset($all_payment_methods[$method])): ?>
                                        <span class="badge bg-light text-dark border" 
                                              title="<?php echo $all_payment_methods[$method]['name']; ?>">
                                            <i class="<?php echo $all_payment_methods[$method]['icon']; ?>"></i>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.checkout-page .payment-method-card {
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkout-page .payment-method-card:hover {
    border-color: #007bff;
    background-color: #f8f9ff;
}

.checkout-page .payment-method-card input[type="radio"] {
    margin-right: 10px;
    margin-bottom: 10px;
}

.checkout-page .payment-method-card input[type="radio"]:checked + label {
    color: #007bff;
}

.sticky-top {
    z-index: 100;
}

@media (max-width: 768px) {
    .sticky-top {
        position: relative;
        top: 0;
    }
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Toggle billing address section
    $('#same_as_shipping').change(function() {
        if ($(this).is(':checked')) {
            $('#billing_address_section').slideUp();
        } else {
            $('#billing_address_section').slideDown();
        }
    });

    // Update shipping cost and total when shipping method changes
    $('input[name="shipping_method"]').change(function() {
        let shippingCost = 0;
        const method = $(this).val();
        
        switch(method) {
            case 'standard':
                shippingCost = 5.99;
                break;
            case 'express':
                shippingCost = 12.99;
                break;
            case 'overnight':
                shippingCost = 24.99;
                break;
        }
        
        const subtotal = <?php echo $subtotal; ?>;
        const tax = <?php echo $tax_amount; ?>;
        const total = subtotal + shippingCost + tax;
        
        $('#shipping_display').text('$' + shippingCost.toFixed(2));
        $('#total_display').text('$' + total.toFixed(2));
    });

    // Show bank details when bank transfer is selected
    $('input[name="payment_method"]').change(function() {
        if ($(this).val() === 'bank') {
            $('#bank_details_section').slideDown();
        } else {
            $('#bank_details_section').slideUp();
        }
    });

    // Form validation
    $('#checkoutForm').submit(function(e) {
        const shippingAddress = $('#shipping_address').val().trim();
        
        if (!shippingAddress) {
            e.preventDefault();
            alert('Please enter shipping address');
            return false;
        }
        
        if (!$('#same_as_shipping').is(':checked')) {
            const billingAddress = $('#billing_address').val().trim();
            if (!billingAddress) {
                e.preventDefault();
                alert('Please enter billing address');
                return false;
            }
        }
        
        // Disable submit button to prevent double submission
        $('#placeOrderBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
        
        return true;
    });

    // Make payment method cards clickable
    $('.payment-method-card').click(function() {
        $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>