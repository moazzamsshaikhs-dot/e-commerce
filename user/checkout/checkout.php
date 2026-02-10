<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login to proceed to checkout';
    redirect(SITE_URL . 'login.php');
}

$page_title = 'Checkout';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get cart items with product details
$stmt = $db->prepare("
    SELECT ci.*, 
           p.name, p.price, p.image, p.stock, p.vendor_id,
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

// Check if cart is empty
if (empty($cart_items)) {
    $_SESSION['error'] = 'Your cart is empty';
    redirect(SITE_URL . 'user/cart/cart.php');
}

// Calculate totals
$subtotal = 0;
$total_items = 0;
$items_valid = true;
$error_messages = [];

foreach ($cart_items as $item) {
    // Check stock availability
    if ($item['stock'] < $item['quantity']) {
        $items_valid = false;
        $error_messages[] = "{$item['name']} - Only {$item['stock']} available (requested {$item['quantity']})";
    }
    
    // Check product approval
    if ($item['approved_status'] !== 'approved') {
        $items_valid = false;
        $error_messages[] = "{$item['name']} is not available for purchase";
    }
    
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

// If items are not valid, redirect to cart
if (!$items_valid) {
    foreach ($error_messages as $error) {
        $_SESSION['error'] = $error;
    }
    redirect(SITE_URL . 'user/cart/cart.php');
}

// Shipping and tax calculation
$shipping = ($subtotal > 50) ? 0 : 10.00;
$tax_rate = 0.10; // 10%
$tax_amount = $subtotal * $tax_rate;
$total_amount = $subtotal + $shipping + $tax_amount;

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user addresses (if available)
$user_address = $user['address'] ?? '';
$user_city = $user['city'] ?? '';
$user_country = $user['country'] ?? '';
$user_postal_code = $user['postal_code'] ?? '';
$user_phone = $user['phone'] ?? '';

// Get vendor payment methods
$vendor_payment_methods = [];
foreach ($cart_items as $item) {
    if ($item['vendor_id']) {
        $stmt = $db->prepare("SELECT payment_methods FROM vendor_settings WHERE vendor_id = ?");
        $stmt->execute([$item['vendor_id']]);
        $vendor_settings = $stmt->fetch();
        
        if ($vendor_settings && $vendor_settings['payment_methods']) {
            $methods = json_decode($vendor_settings['payment_methods'], true);
            if (is_array($methods)) {
                $vendor_payment_methods[$item['vendor_id']] = $methods;
            }
        }
    }
}

// Find common payment methods across all vendors
$common_payment_methods = [];
if (!empty($vendor_payment_methods)) {
    $all_methods = [];
    foreach ($vendor_payment_methods as $methods) {
        $all_methods = array_merge($all_methods, $methods);
    }
    $common_payment_methods = array_unique($all_methods);
}

// If no vendor-specific methods, use default
if (empty($common_payment_methods)) {
    $common_payment_methods = ['credit_card', 'paypal', 'bank_transfer'];
}

// Payment method icons and labels
$payment_method_icons = [
    'credit_card' => 'fas fa-credit-card',
    'debit_card' => 'fas fa-credit-card',
    'paypal' => 'fab fa-paypal',
    'bank_transfer' => 'fas fa-university',
    'stripe' => 'fab fa-stripe',
    'cod' => 'fas fa-money-bill-wave',
    'apple_pay' => 'fab fa-apple-pay',
    'google_pay' => 'fab fa-google-pay'
];

$payment_method_labels = [
    'credit_card' => 'Credit Card',
    'debit_card' => 'Debit Card',
    'paypal' => 'PayPal',
    'bank_transfer' => 'Bank Transfer',
    'stripe' => 'Stripe',
    'cod' => 'Cash on Delivery',
    'apple_pay' => 'Apple Pay',
    'google_pay' => 'Google Pay'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $billing_address = trim($_POST['billing_address']) ?: $shipping_address;
    $shipping_city = trim($_POST['shipping_city']);
    $shipping_country = trim($_POST['shipping_country']);
    $shipping_postal_code = trim($_POST['shipping_postal_code']);
    $shipping_phone = trim($_POST['shipping_phone']);
    $payment_method = trim($_POST['payment_method']);
    $card_holder = trim($_POST['card_holder'] ?? '');
    $card_number = trim($_POST['card_number'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv = trim($_POST['card_cvv'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($shipping_address)) {
        $errors[] = 'Shipping address is required';
    }
    
    if (empty($shipping_city)) {
        $errors[] = 'City is required';
    }
    
    if (empty($shipping_country)) {
        $errors[] = 'Country is required';
    }
    
    if (empty($payment_method)) {
        $errors[] = 'Payment method is required';
    }
    
    if ($payment_method === 'credit_card' || $payment_method === 'debit_card') {
        if (empty($card_holder)) {
            $errors[] = 'Card holder name is required';
        }
        if (empty($card_number) || strlen($card_number) < 12) {
            $errors[] = 'Valid card number is required';
        }
        if (empty($card_expiry)) {
            $errors[] = 'Card expiry date is required';
        }
        if (empty($card_cvv) || strlen($card_cvv) < 3) {
            $errors[] = 'Card CVV is required';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate order number
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT) . '-' . mt_rand(1000, 9999);
            
            // 1. Create main order
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, order_number, total_amount, status, payment_method, payment_status,
                    shipping_address, billing_address, shipping_method, order_date, estimated_delivery,
                    customer_notes
                ) VALUES (?, ?, ?, 'pending', ?, 'pending', ?, ?, 'standard', NOW(), 
                          DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
            ");
            $stmt->execute([
                $user_id,
                $order_number,
                $total_amount,
                $payment_method,
                $shipping_address,
                $billing_address,
                $notes
            ]);
            $order_id = $db->lastInsertId();
            
            // 2. Add order items and vendor earnings
            foreach ($cart_items as $item) {
                // Add order item
                $item_subtotal = $item['price'] * $item['quantity'];
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item_subtotal
                ]);
                $order_item_id = $db->lastInsertId();
                
                // Create vendor earnings record (if product has vendor)
                if ($item['vendor_id']) {
                    $commission_rate = 10.00; // 10% commission
                    $commission_amount = ($item['price'] * $item['quantity']) * ($commission_rate / 100);
                    $vendor_amount = ($item['price'] * $item['quantity']) - $commission_amount;
                    
                    $stmt = $db->prepare("
                        INSERT INTO vendor_earnings (
                            vendor_id, order_id, product_id, order_item_id, product_price, 
                            commission, commission_amount, vendor_amount, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $item['vendor_id'],
                        $order_id,
                        $item['product_id'],
                        $order_item_id,
                        $item['price'],
                        $commission_rate,
                        $commission_amount,
                        $vendor_amount
                    ]);
                }
                
                // Update product stock
                $stmt = $db->prepare("
                    UPDATE products 
                    SET stock = stock - ?, 
                        sales_count = sales_count + ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
            }
            
            // 3. Create payment record
            $transaction_id = 'TXN' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
            
            $payment_details = json_encode([
                'card_last4' => substr($card_number, -4),
                'card_holder' => $card_holder,
                'shipping_address' => $shipping_address,
                'billing_address' => $billing_address,
                'shipping_city' => $shipping_city,
                'shipping_country' => $shipping_country,
                'shipping_phone' => $shipping_phone
            ]);
            
            $stmt = $db->prepare("
                INSERT INTO payments (
                    user_id, order_id, payment_method, transaction_id, amount, currency, 
                    status, payment_details, created_at
                ) VALUES (?, ?, ?, ?, ?, 'USD', 'completed', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $order_id,
                $payment_method,
                $transaction_id,
                $total_amount,
                $payment_details
            ]);
            $payment_id = $db->lastInsertId();
            
            // 4. Create invoice
            $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO invoices (
                    invoice_number, user_id, order_id, subtotal, tax_rate, tax_amount, 
                    total_amount, amount_paid, balance_due, payment_status,
                    invoice_date, due_date, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, 'paid',
                          CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'sent', ?)
            ");
            $stmt->execute([
                $invoice_number,
                $user_id,
                $order_id,
                $subtotal,
                $tax_rate * 100,
                $tax_amount,
                $total_amount,
                $total_amount,
                $notes
            ]);
            $invoice_id = $db->lastInsertId();
            
            // 5. Add invoice items
            foreach ($cart_items as $item) {
                $item_subtotal = $item['price'] * $item['quantity'];
                $stmt = $db->prepare("
                    INSERT INTO invoice_items (
                        invoice_id, description, quantity, unit_price, tax_rate, subtotal, product_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $invoice_id,
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $tax_rate * 100,
                    $item_subtotal,
                    $item['product_id']
                ]);
            }
            
            // Add shipping as invoice item
            if ($shipping > 0) {
                $stmt->execute([
                    $invoice_id,
                    'Shipping Fee',
                    1,
                    $shipping,
                    0,
                    $shipping,
                    null
                ]);
            }
            
            // 6. Clear cart
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // 7. Add order status history
            $stmt = $db->prepare("
                INSERT INTO order_status_history (order_id, status, changed_by, notes)
                VALUES (?, 'processing', ?, 'Order placed and payment completed')
            ");
            $stmt->execute([$order_id, $user_id]);
            
            // 8. Add order note
            if (!empty($notes)) {
                $stmt = $db->prepare("
                    INSERT INTO order_notes (order_id, user_id, note_type, note)
                    VALUES (?, ?, 'customer', ?)
                ");
                $stmt->execute([$order_id, $user_id, $notes]);
            }
            
            // 9. Create notifications
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Order Placed', ?, 'success')
            ");
            $stmt->execute([$user_id, 'Your order #' . $order_number . ' has been placed successfully']);
            
            // Notify vendors
            foreach ($cart_items as $item) {
                if ($item['vendor_id']) {
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        VALUES (?, 'New Order', ?, 'info')
                    ");
                    $stmt->execute([
                        $item['vendor_id'],
                        'New order #' . $order_number . ' for ' . $item['quantity'] . 'x ' . $item['name']
                    ]);
                }
            }
            
            $db->commit();
            
            // Log activity
            logUserActivity($user_id, 'checkout_complete', 'Completed checkout for order: ' . $order_number);
            
            // Redirect to success page
            $_SESSION['order_success'] = true;
            $_SESSION['order_id'] = $order_id;
            $_SESSION['order_number'] = $order_number;
            $_SESSION['invoice_id'] = $invoice_id;
            $_SESSION['total_amount'] = $total_amount;
            
            redirect(SITE_URL . 'user/checkout/success.php?order_id=' . $order_id);
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Error processing order: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Log activity
logUserActivity($user_id, 'checkout_view', 'Viewed checkout page');
?>

<div class="checkout-page">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>user/cart/cart.php">Cart</a></li>
            <li class="breadcrumb-item active" aria-current="page">Checkout</li>
        </ol>
    </nav>

    <h1 class="mb-4">Checkout</h1>
    
    <div class="row">
        <!-- Checkout Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-shipping-fast me-2 text-primary"></i>
                        Shipping Information
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="checkoutForm">
                        <!-- Shipping Address -->
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Shipping Address *</label>
                                <textarea class="form-control" 
                                          name="shipping_address" 
                                          rows="3" 
                                          required><?php echo htmlspecialchars($user_address); ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">City *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="shipping_city" 
                                       value="<?php echo htmlspecialchars($user_city); ?>"
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Country *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="shipping_country" 
                                       value="<?php echo htmlspecialchars($user_country); ?>"
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="shipping_postal_code" 
                                       value="<?php echo htmlspecialchars($user_postal_code); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" 
                                       class="form-control" 
                                       name="shipping_phone" 
                                       value="<?php echo htmlspecialchars($user_phone); ?>"
                                       required>
                            </div>
                        </div>
                        
                        <!-- Billing Address (Optional) -->
                        <div class="mt-4">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="sameAsShipping">
                                <label class="form-check-label" for="sameAsShipping">
                                    Billing address same as shipping
                                </label>
                            </div>
                            
                            <div id="billingAddressSection" class="mt-3" style="display: none;">
                                <h6 class="mb-3">Billing Address</h6>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Billing Address</label>
                                        <textarea class="form-control" 
                                                  name="billing_address" 
                                                  rows="3"><?php echo htmlspecialchars($user_address); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
            
            <!-- Payment Method -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-credit-card me-2 text-primary"></i>
                        Payment Method
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Available Payment Methods -->
                    <div class="mb-4">
                        <label class="form-label">Select Payment Method *</label>
                        <div class="row g-3">
                            <?php foreach ($common_payment_methods as $method): ?>
                                <?php if (isset($payment_method_icons[$method])): ?>
                                    <div class="col-md-6">
                                        <div class="payment-method-option">
                                            <input type="radio" 
                                                   class="btn-check" 
                                                   name="payment_method" 
                                                   id="method_<?php echo $method; ?>" 
                                                   value="<?php echo $method; ?>"
                                                   <?php echo $method === 'credit_card' ? 'checked' : ''; ?>
                                                   required>
                                            <label class="btn btn-outline-primary w-100 text-start" 
                                                   for="method_<?php echo $method; ?>">
                                                <i class="<?php echo $payment_method_icons[$method]; ?> fa-lg me-2"></i>
                                                <?php echo $payment_method_labels[$method]; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Credit Card Details -->
                    <div id="creditCardSection">
                        <h6 class="mb-3">Credit Card Details</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Card Holder Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="card_holder" 
                                       placeholder="Name on card">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Card Number *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="card_number" 
                                       placeholder="1234 5678 9012 3456"
                                       maxlength="19">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Expiry *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="card_expiry" 
                                       placeholder="MM/YY"
                                       maxlength="5">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">CVV *</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="card_cvv" 
                                       placeholder="123"
                                       maxlength="4">
                            </div>
                        </div>
                        
                        <!-- Card Preview -->
                        <div class="card-preview mt-3 p-3 rounded bg-light" style="display: none;">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <i class="fab fa-cc-visa fa-2x text-primary"></i>
                                </div>
                                <div class="col">
                                    <div class="card-number-preview">**** **** **** ****</div>
                                    <div class="text-muted small">
                                        <span class="card-holder-preview">CARD HOLDER</span> | 
                                        <span class="card-expiry-preview">MM/YY</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Other Payment Sections (initially hidden) -->
                    <div id="paypalSection" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fab fa-paypal me-2"></i>
                            You will be redirected to PayPal to complete your payment.
                        </div>
                    </div>
                    
                    <div id="bankTransferSection" style="display: none;">
                        <div class="alert alert-warning">
                            <i class="fas fa-university me-2"></i>
                            Please transfer the amount to our bank account. Your order will be processed after we confirm the payment.
                        </div>
                    </div>
                    
                    <div id="codSection" style="display: none;">
                        <div class="alert alert-success">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            Pay when you receive your order. Additional cash handling fee may apply.
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   name="cod_terms" 
                                   id="codTerms">
                            <label class="form-check-label" for="codTerms">
                                I agree to pay cash on delivery
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Notes -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-sticky-note me-2 text-primary"></i>
                        Additional Notes
                    </h5>
                </div>
                <div class="card-body">
                    <label class="form-label">Order Notes (Optional)</label>
                    <textarea class="form-control" 
                              name="notes" 
                              rows="3"
                              placeholder="Special instructions, delivery preferences, etc."></textarea>
                </div>
            </div>
            
            <!-- Terms and Conditions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="terms" 
                               id="terms" 
                               required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="<?php echo SITE_URL; ?>terms.php" target="_blank">Terms and Conditions</a> 
                            and <a href="<?php echo SITE_URL; ?>privacy.php" target="_blank">Privacy Policy</a>
                        </label>
                    </div>
                </div>
            </div>
            
            </form>
        </div>
        
        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <!-- Cart Items Preview -->
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Order Items (<?php echo $total_items; ?>)</h6>
                        <div class="cart-items-preview" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <?php if ($item['image']): ?>
                                        <div class="me-3" style="width: 60px; height: 60px;">
                                            <img src="<?php echo SITE_URL . 'assets/images/products/' . $item['image']; ?>" 
                                                 class="img-fluid rounded object-fit-cover h-100"
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="me-3 bg-light rounded d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px;">
                                            <i class="fas fa-box text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 small"><?php echo htmlspecialchars(substr($item['name'], 0, 30)); ?>...</h6>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                            <small class="text-primary">$<?php echo number_format($item['price'], 2); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Price Breakdown -->
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
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
                        
                        <div class="border-top pt-2 mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Total</strong>
                                <h4 class="text-primary mb-0">
                                    $<?php echo number_format($total_amount, 2); ?>
                                </h4>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Place Order Button -->
                    <button type="submit" 
                            form="checkoutForm" 
                            name="place_order" 
                            class="btn btn-primary btn-lg w-100 mt-4">
                        <i class="fas fa-lock me-2"></i> Place Order
                    </button>
                    
                    <!-- Security Info -->
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1 text-success"></i>
                            Secure SSL Encryption
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Show/hide billing address
    $('#sameAsShipping').change(function() {
        if ($(this).is(':checked')) {
            $('#billingAddressSection').slideUp();
            $('textarea[name="billing_address"]').val($('textarea[name="shipping_address"]').val());
        } else {
            $('#billingAddressSection').slideDown();
        }
    });
    
    // Show/hide payment method sections
    $('input[name="payment_method"]').change(function() {
        const method = $(this).val();
        
        // Hide all sections
        $('#creditCardSection').hide();
        $('#paypalSection').hide();
        $('#bankTransferSection').hide();
        $('#codSection').hide();
        
        // Show selected section
        switch(method) {
            case 'credit_card':
            case 'debit_card':
                $('#creditCardSection').show();
                break;
            case 'paypal':
                $('#paypalSection').show();
                break;
            case 'bank_transfer':
                $('#bankTransferSection').show();
                break;
            case 'cod':
                $('#codSection').show();
                break;
        }
    });
    
    // Initialize payment method sections
    $('input[name="payment_method"]:checked').trigger('change');
    
    // Card number formatting
    $('input[name="card_number"]').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        value = value.replace(/(\d{4})/g, '$1 ').trim();
        $(this).val(value.substring(0, 19));
        updateCardPreview();
    });
    
    // Expiry date formatting
    $('input[name="card_expiry"]').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        $(this).val(value.substring(0, 5));
        updateCardPreview();
    });
    
    // Card holder name
    $('input[name="card_holder"]').on('input', updateCardPreview);
    
    function updateCardPreview() {
        const cardNumber = $('input[name="card_number"]').val();
        const expiry = $('input[name="card_expiry"]').val();
        const holder = $('input[name="card_holder"]').val();
        
        if (cardNumber || expiry || holder) {
            $('.card-preview').show();
            $('.card-number-preview').text(cardNumber || '**** **** **** ****');
            $('.card-expiry-preview').text(expiry || 'MM/YY');
            $('.card-holder-preview').text(holder || 'CARD HOLDER');
        } else {
            $('.card-preview').hide();
        }
    }
    
    // Form validation
    $('#checkoutForm').submit(function(e) {
        const method = $('input[name="payment_method"]:checked').val();
        
        // Credit card validation
        if (method === 'credit_card' || method === 'debit_card') {
            const cardNumber = $('input[name="card_number"]').val().replace(/\D/g, '');
            const cardExpiry = $('input[name="card_expiry"]').val();
            const cardCVV = $('input[name="card_cvv"]').val();
            const cardHolder = $('input[name="card_holder"]').val();
            
            if (cardNumber.length < 12) {
                e.preventDefault();
                showToast('Please enter a valid card number', 'error');
                return false;
            }
            
            if (!cardExpiry || !cardExpiry.match(/^\d{2}\/\d{2}$/)) {
                e.preventDefault();
                showToast('Please enter a valid expiry date (MM/YY)', 'error');
                return false;
            }
            
            if (!cardCVV || cardCVV.length < 3) {
                e.preventDefault();
                showToast('Please enter a valid CVV', 'error');
                return false;
            }
            
            if (!cardHolder.trim()) {
                e.preventDefault();
                showToast('Please enter card holder name', 'error');
                return false;
            }
        }
        
        // COD validation
        if (method === 'cod') {
            const codTerms = $('#codTerms').is(':checked');
            if (!codTerms) {
                e.preventDefault();
                showToast('Please agree to COD terms', 'error');
                return false;
            }
        }
        
        // Show processing modal
        showProcessingModal();
    });
});

function showProcessingModal() {
    const modal = $(`
        <div class="modal fade" id="processingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <h4>Processing Order</h4>
                        <p class="text-muted">Please wait while we process your order</p>
                        <div class="progress mt-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `);
    
    $('body').append(modal);
    modal.modal('show');
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
    const bsToast = new bootstrap.Toast(toast[0], { delay: 5000 });
    bsToast.show();
}
</script>
<?php
require_once '../../includes/footer.php';
?>