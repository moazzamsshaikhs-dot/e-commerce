<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login to continue';
    redirect(SITE_URL . 'login.php');
}

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Product not found';
    redirect('shop.php');
}

$page_title = 'Buy Now - Payment';
require_once '../../includes/header.php';

$db = getDB();
$product_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get product details with vendor info
$stmt = $db->prepare("
    SELECT p.*, 
           c.name as category_name,
           u.username as vendor_username,
           u.full_name as vendor_name,
           u.id as vendor_id,
           vs.store_name,
           vs.payment_methods,
           vs.store_currency,
           vs.min_order_amount,
           vs.free_shipping_threshold
    FROM products p
    LEFT JOIN categories c ON p.category = c.slug
    LEFT JOIN users u ON p.vendor_id = u.id
    LEFT JOIN vendor_settings vs ON p.vendor_id = vs.vendor_id
    WHERE p.id = ? AND p.approved_status = 'approved'
");

$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['error'] = 'Product not found or not available';
    redirect('shop.php');
}

// Get quantity from query string or default to 1
$quantity = isset($_GET['quantity']) ? max(1, (int)$_GET['quantity']) : 1;

// Check stock
if ($product['stock'] < $quantity) {
    $_SESSION['error'] = 'Requested quantity exceeds available stock';
    redirect('product-details.php?id=' . $product_id);
}

// Calculate totals
$subtotal = $product['price'] * $quantity;
$shipping = 10.00; // Default shipping, can be dynamic
$tax_rate = 0.10; // 10% tax
$tax_amount = $subtotal * $tax_rate;
$total_amount = $subtotal + $shipping + $tax_amount;

// Check minimum order amount
if ($product['min_order_amount'] > 0 && $subtotal < $product['min_order_amount']) {
    $_SESSION['error'] = 'Minimum order amount is $' . number_format($product['min_order_amount'], 2);
    redirect('product-details.php?id=' . $product_id);
}

// Check for free shipping
if ($product['free_shipping_threshold'] > 0 && $subtotal >= $product['free_shipping_threshold']) {
    $shipping = 0;
    $total_amount = $subtotal + $tax_amount;
}

// Parse vendor payment methods
$vendor_payment_methods = [];
if (!empty($product['payment_methods'])) {
    $vendor_payment_methods = json_decode($product['payment_methods'], true);
}

// Get user's saved payment methods
$user_payment_methods = [];
$stmt = $db->prepare("SELECT * FROM user_payment_methods WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_payment_methods = $stmt->fetchAll();

// Get user address
$user_address = '';
$stmt = $db->prepare("SELECT address, city, country, postal_code FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if ($user) {
    $user_address = $user['address'] . ', ' . $user['city'] . ', ' . $user['country'] . ' ' . $user['postal_code'];
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_method = trim($_POST['payment_method']);
    $card_number = trim($_POST['card_number'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv = trim($_POST['card_cvv'] ?? '');
    $card_holder = trim($_POST['card_holder'] ?? '');
    $use_saved_card = isset($_POST['use_saved_card']) ? true : false;
    $saved_card_id = (int)($_POST['saved_card_id'] ?? 0);
    $billing_address = trim($_POST['billing_address'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($payment_method)) {
        $errors[] = 'Please select a payment method';
    }
    
    if ($payment_method === 'card' && !$use_saved_card) {
        if (empty($card_number)) {
            $errors[] = 'Card number is required';
        }
        if (empty($card_expiry)) {
            $errors[] = 'Card expiry date is required';
        }
        if (empty($card_cvv)) {
            $errors[] = 'Card CVV is required';
        }
        if (empty($card_holder)) {
            $errors[] = 'Card holder name is required';
        }
    }
    
    if (empty($shipping_address)) {
        $errors[] = 'Shipping address is required';
    }
    
    // Check if vendor accepts this payment method
    if (!empty($vendor_payment_methods) && !in_array($payment_method, $vendor_payment_methods)) {
        $errors[] = 'This vendor does not accept ' . $payment_method . ' payments';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate order number
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT) . '-' . mt_rand(1000, 9999);
            
            // 1. Create order
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, order_number, total_amount, status, payment_method, payment_status,
                    shipping_address, billing_address, shipping_method, order_date, estimated_delivery
                ) VALUES (?, ?, ?, 'pending', ?, 'pending', ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            $stmt->execute([
                $user_id,
                $order_number,
                $total_amount,
                $payment_method,
                $shipping_address,
                $billing_address ?: $shipping_address,
                'standard'
            ]);
            $order_id = $db->lastInsertId();
            
            // 2. Add order items
            $stmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $order_item_subtotal = $product['price'] * $quantity;
            $stmt->execute([$order_id, $product_id, $quantity, $product['price'], $order_item_subtotal]);
            $order_item_id = $db->lastInsertId();
            
            // 3. Create payment record
            $transaction_id = strtoupper(bin2hex(random_bytes(6)));
            $stmt = $db->prepare("
                INSERT INTO payments (
                    user_id, order_id, payment_method, transaction_id, amount, currency, status, payment_details, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            
            $payment_details = json_encode([
                'card_last4' => substr($card_number, -4),
                'card_holder' => $card_holder,
                'billing_address' => $billing_address,
                'shipping_address' => $shipping_address,
                'quantity' => $quantity,
                'vendor_id' => $product['vendor_id']
            ]);
            
            $stmt->execute([
                $user_id,
                $order_id,
                $payment_method,
                $transaction_id,
                $total_amount,
                $product['store_currency'] ?? 'USD',
                $payment_details
            ]);
            $payment_id = $db->lastInsertId();
            
            // 4. Create vendor earnings record
            $commission_rate = 10.00; // Default 10% commission
            $commission_amount = ($product['price'] * $quantity) * ($commission_rate / 100);
            $vendor_amount = ($product['price'] * $quantity) - $commission_amount;
            
            $stmt = $db->prepare("
                INSERT INTO vendor_earnings (
                    vendor_id, order_id, product_id, order_item_id, product_price, 
                    commission, commission_amount, vendor_amount, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $product['vendor_id'],
                $order_id,
                $product_id,
                $order_item_id,
                $product['price'],
                $commission_rate,
                $commission_amount,
                $vendor_amount
            ]);
            
            // 5. Update product stock and sales count
            $stmt = $db->prepare("
                UPDATE products 
                SET stock = stock - ?, 
                    sales_count = sales_count + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $quantity, $product_id]);
            
            // 6. Update user's total sales if vendor
            if ($product['vendor_id'] == $user_id) {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET total_sales = total_sales + ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $user_id]);
            }
            
            // 7. Create invoice
            $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO invoices (
                    invoice_number, user_id, order_id, subtotal, tax_rate, tax_amount, 
                    total_amount, amount_paid, balance_due, payment_status, 
                    invoice_date, due_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', CURDATE(), 
                          DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'sent', NOW())
            ");
            $stmt->execute([
                $invoice_number,
                $user_id,
                $order_id,
                $subtotal,
                $tax_rate * 100, // Convert to percentage
                $tax_amount,
                $total_amount,
                $total_amount,
                0.00
            ]);
            $invoice_id = $db->lastInsertId();
            
            // 8. Add invoice items
            $stmt = $db->prepare("
                INSERT INTO invoice_items (
                    invoice_id, description, quantity, unit_price, discount, tax_rate, subtotal, product_id
                ) VALUES (?, ?, ?, ?, 0, ?, ?, ?)
            ");
            $stmt->execute([
                $invoice_id,
                $product['name'],
                $quantity,
                $product['price'],
                $tax_rate * 100,
                $order_item_subtotal,
                $product_id
            ]);
            
            // 9. Add shipping as invoice item
            if ($shipping > 0) {
                $stmt->execute([
                    $invoice_id,
                    'Shipping Fee',
                    1,
                    $shipping,
                    0,
                    0,
                    $shipping,
                    null
                ]);
            }
            
            // 10. Create invoice payment record
            $stmt = $db->prepare("
                INSERT INTO invoice_payments (
                    invoice_id, user_id, amount, payment_method, transaction_id, 
                    payment_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, CURDATE(), 'completed', NOW())
            ");
            $stmt->execute([
                $invoice_id,
                $user_id,
                $total_amount,
                $payment_method,
                $transaction_id
            ]);
            
            // 11. Add order status history
            $stmt = $db->prepare("
                INSERT INTO order_status_history (order_id, status, changed_by, notes, created_at)
                VALUES (?, 'processing', ?, 'Order placed and payment completed', NOW())
            ");
            $stmt->execute([$order_id, $user_id]);
            
            // 12. Add order note
            $stmt = $db->prepare("
                INSERT INTO order_notes (order_id, user_id, note_type, note, created_at)
                VALUES (?, ?, 'customer', ?, NOW())
            ");
            $stmt->execute([$order_id, $user_id, $notes ?: 'Order placed via Buy Now']);
            
            // 13. Create notification
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, 'Order Placed', 'Your order #? has been placed successfully', 'success', NOW())
            ");
            $stmt->execute([$user_id, $order_number]);
            
            // Also notify vendor
            $stmt->execute([
                $product['vendor_id'],
                'New Order Received',
                'You have received a new order #' . $order_number . ' for ' . $quantity . 'x ' . $product['name'],
                'info'
            ]);
            
            $db->commit();
            
            // Redirect to success page
            $_SESSION['success'] = 'Order placed successfully!';
            $_SESSION['order_id'] = $order_id;
            $_SESSION['invoice_id'] = $invoice_id;
            
            redirect('payment-success.php?order_id=' . $order_id . '&invoice_id=' . $invoice_id);
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Error processing payment: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}
?>

<div class="payment-page">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>user/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="product-details.php?id=<?php echo $product_id; ?>"><?php echo substr($product['name'], 0, 20); ?>...</a></li>
            <li class="breadcrumb-item active" aria-current="page">Buy Now - Payment</li>
        </ol>
    </nav>

    <div class="row">
        <!-- Order Summary -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <!-- Product Info -->
                    <div class="d-flex mb-3">
                        <?php if ($product['image']): ?>
                            <div class="me-3" style="width: 80px; height: 80px;">
                                <img src="<?php echo SITE_URL . 'assets/images/products/' . $product['image']; ?>" 
                                     class="img-fluid rounded object-fit-cover h-100"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                            <div class="text-muted small">
                                Quantity: <?php echo $quantity; ?> × $<?php echo number_format($product['price'], 2); ?>
                            </div>
                            <div class="text-primary fw-bold">
                                $<?php echo number_format($product['price'] * $quantity, 2); ?>
                            </div>
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
                        </div>
                        <span>$<?php echo number_format($tax_amount, 2); ?></span>
                        <?php if ($product['min_order_amount'] > 0): ?>
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Minimum order: $<?php echo number_format($product['min_order_amount'], 2); ?>
                                <?php if ($subtotal >= $product['min_order_amount']): ?>
                                    <span class="text-success ms-2"><i class="fas fa-check"></i> Met</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['free_shipping_threshold'] > 0): ?>
                            <div class="alert alert-warning py-2 small mb-3">
                                <i class="fas fa-shipping-fast me-2"></i>
                                Free shipping on orders over $<?php echo number_format($product['free_shipping_threshold'], 2); ?>
                                <?php if ($subtotal >= $product['free_shipping_threshold']): ?>
                                    <span class="text-success ms-2"><i class="fas fa-check"></i> Qualified</span>
                                <?php else: ?>
                                    <?php $needed = $product['free_shipping_threshold'] - $subtotal; ?>
                                    <span class="text-danger ms-2">
                                        Add $<?php echo number_format($needed, 2); ?> more
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="border-top pt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Total</strong>
                                <h4 class="text-primary mb-0">
                                    $<?php echo number_format($total_amount, 2); ?>
                                </h4>
                            </div>
                            <div class="text-muted small">
                                <?php echo $product['store_currency'] ?? 'USD'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vendor Info -->
                    <div class="border-top mt-3 pt-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-store text-muted me-2"></i>
                            <small class="text-muted">Sold by</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <?php if ($product['vendor_id']): ?>
                                <strong><?php echo htmlspecialchars($product['store_name'] ?? $product['vendor_name']); ?></strong>
                                <span class="badge bg-light text-dark ms-2 small">
                                    <!-- Vendor ID: <?php echo $product['vendor_id']; ?> -->
                                </span>
                            <?php else: ?>
                                <span class="text-muted">System</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Payment Methods Accepted -->
                    <?php if (!empty($vendor_payment_methods)): ?>
                        <div class="border-top mt-3 pt-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-credit-card text-muted me-2"></i>
                                <small class="text-muted">Payment Methods Accepted</small>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($vendor_payment_methods as $method): ?>
                                    <?php
                                    $method_icons = [
                                        'credit_card' => 'fas fa-credit-card',
                                        'debit_card' => 'fas fa-credit-card',
                                        'paypal' => 'fab fa-paypal',
                                        'stripe' => 'fab fa-stripe',
                                        'bank_transfer' => 'fas fa-university',
                                        'cod' => 'fas fa-money-bill-wave',
                                        'apple_pay' => 'fab fa-apple-pay',
                                        'google_pay' => 'fab fa-google-pay'
                                    ];
                                    $method_labels = [
                                        'credit_card' => 'Credit Card',
                                        'debit_card' => 'Debit Card',
                                        'paypal' => 'PayPal',
                                        'stripe' => 'Stripe',
                                        'bank_transfer' => 'Bank Transfer',
                                        'cod' => 'Cash on Delivery',
                                        'apple_pay' => 'Apple Pay',
                                        'google_pay' => 'Google Pay'
                                    ];
                                    ?>
                                    <span class="badge bg-light text-dark">
                                        <i class="<?php echo $method_icons[$method] ?? 'fas fa-money-bill'; ?> me-1"></i>
                                        <?php echo $method_labels[$method] ?? ucfirst($method); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Payment Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Payment Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <!-- Shipping Address -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-shipping-fast me-2 text-primary"></i>
                                Shipping Information
                            </h6>
                            <div class="mb-3">
                                <label class="form-label">Shipping Address *</label>
                                <textarea class="form-control" 
                                          name="shipping_address" 
                                          rows="3" 
                                          required><?php echo htmlspecialchars($user_address); ?></textarea>
                                <div class="form-text">
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                                            data-bs-toggle="modal" data-bs-target="#addressModal">
                                        <i class="fas fa-address-book me-1"></i> Use Saved Address
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="sameAsBilling">
                                        <label class="form-check-label" for="sameAsBilling">
                                            Billing address same as shipping
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="billingAddressSection" class="mt-3" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Billing Address</label>
                                    <textarea class="form-control" 
                                              name="billing_address" 
                                              rows="3"><?php echo htmlspecialchars($user_address); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-credit-card me-2 text-primary"></i>
                                Payment Method
                            </h6>
                            
                            <!-- Vendor Payment Methods -->
                            <div class="mb-4">
                                <label class="form-label">Select Payment Method *</label>
                                <div class="row g-3">
                                    <?php
                                    $available_methods = [
                                        'credit_card' => ['icon' => 'fas fa-credit-card', 'label' => 'Credit Card'],
                                        'paypal' => ['icon' => 'fab fa-paypal', 'label' => 'PayPal'],
                                        'bank_transfer' => ['icon' => 'fas fa-university', 'label' => 'Bank Transfer'],
                                        'stripe' => ['icon' => 'fab fa-stripe', 'label' => 'Stripe'],
                                        'cod' => ['icon' => 'fas fa-money-bill-wave', 'label' => 'Cash on Delivery']
                                    ];
                                    
                                    foreach ($available_methods as $method => $info):
                                        // Check if vendor accepts this method
                                        if (!empty($vendor_payment_methods) && !in_array($method, $vendor_payment_methods)) {
                                            continue;
                                        }
                                    ?>
                                    <div class="col-md-4">
                                        <div class="payment-method-option">
                                            <input type="radio" 
                                                   class="btn-check" 
                                                   name="payment_method" 
                                                   id="method_<?php echo $method; ?>" 
                                                   value="<?php echo $method; ?>"
                                                   <?php echo $method === 'credit_card' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-primary w-100 text-start" 
                                                   for="method_<?php echo $method; ?>">
                                                <i class="<?php echo $info['icon']; ?> fa-lg me-2"></i>
                                                <?php echo $info['label']; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($vendor_payment_methods)): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            No specific payment methods set by vendor. All methods available.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Saved Cards -->
                            <?php if (!empty($user_payment_methods)): ?>
                                <div class="mb-4">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="useSavedCard" 
                                               name="use_saved_card">
                                        <label class="form-check-label fw-bold" for="useSavedCard">
                                            Use Saved Payment Method
                                        </label>
                                    </div>
                                    
                                    <div id="savedCardsSection" style="display: none;">
                                        <label class="form-label">Select Saved Card</label>
                                        <select class="form-select" name="saved_card_id" id="savedCardSelect">
                                            <option value="">Select a saved card</option>
                                            <?php foreach ($user_payment_methods as $card): ?>
                                                <option value="<?php echo $card['id']; ?>">
                                                    <?php 
                                                    if (!empty($card['stripe_payment_method_id'])) {
                                                        echo 'Stripe Card ****' . substr($card['stripe_payment_method_id'], -4);
                                                    } elseif (!empty($card['paypal_email'])) {
                                                        echo 'PayPal: ' . $card['paypal_email'];
                                                    } elseif (!empty($card['bank_account_details'])) {
                                                        $bank_details = json_decode($card['bank_account_details'], true);
                                                        echo 'Bank Account: ****' . substr($bank_details['account_number'] ?? '', -4);
                                                    }
                                                    ?>
                                                    <?php if ($card['is_default']): ?>
                                                        <span class="text-success">(Default)</span>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
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
                            
                            <!-- PayPal Section -->
                            <div id="paypalSection" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="fab fa-paypal me-2"></i>
                                    You will be redirected to PayPal to complete your payment after submitting this form.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">PayPal Email</label>
                                    <input type="email" 
                                           class="form-control" 
                                           name="paypal_email" 
                                           placeholder="your@email.com">
                                </div>
                            </div>
                            
                            <!-- Bank Transfer Section -->
                            <div id="bankTransferSection" style="display: none;">
                                <div class="alert alert-warning">
                                    <i class="fas fa-university me-2"></i>
                                    Please transfer the amount to our bank account. Your order will be processed after we confirm the payment.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Bank Transfer Reference</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="bank_reference" 
                                           placeholder="Enter reference number">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Upload Payment Proof</label>
                                    <input type="file" 
                                           class="form-control" 
                                           name="payment_proof"
                                           accept=".jpg,.jpeg,.png,.pdf">
                                    <div class="form-text">Upload screenshot or receipt of bank transfer</div>
                                </div>
                            </div>
                            
                            <!-- Stripe Section -->
                            <div id="stripeSection" style="display: none;">
                                <div class="alert alert-primary">
                                    <i class="fab fa-stripe me-2"></i>
                                    Secure payment powered by Stripe. Your card details are encrypted and secure.
                                </div>
                                <!-- Stripe Elements will be loaded here via JavaScript -->
                                <div id="stripe-card-element" class="form-control py-3"></div>
                                <div id="stripe-card-errors" class="text-danger mt-2"></div>
                            </div>
                            
                            <!-- COD Section -->
                            <div id="codSection" style="display: none;">
                                <div class="alert alert-success">
                                    <i class="fas fa-money-bill-wave me-2"></i>
                                    Pay when you receive your order. Additional cash handling fee may apply.
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="cod_terms" 
                                           id="codTerms" 
                                           required>
                                    <label class="form-check-label" for="codTerms">
                                        I agree to pay cash on delivery and understand there may be additional fees
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Notes -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-sticky-note me-2 text-primary"></i>
                                Additional Notes
                            </h6>
                            <div class="mb-3">
                                <label class="form-label">Order Notes (Optional)</label>
                                <textarea class="form-control" 
                                          name="notes" 
                                          rows="3"
                                          placeholder="Special instructions, delivery preferences, etc."></textarea>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="mb-4">
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
                            <div class="form-check mt-2">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       name="newsletter" 
                                       id="newsletter">
                                <label class="form-check-label" for="newsletter">
                                    Subscribe to our newsletter for updates and offers
                                </label>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="product-details.php?id=<?php echo $product_id; ?>" 
                               class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Product
                            </a>
                            <button type="submit" 
                                    name="process_payment" 
                                    class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-lock me-2"></i> Complete Purchase
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Security Information -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body text-center bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <i class="fas fa-shield-alt fa-2x text-success me-2"></i>
                            <span class="fw-bold">SSL Secure Payment</span>
                        </div>
                        <div class="col-md-4">
                            <i class="fas fa-lock fa-2x text-primary me-2"></i>
                            <span class="fw-bold">256-bit Encryption</span>
                        </div>
                        <div class="col-md-4">
                            <i class="fas fa-user-shield fa-2x text-warning me-2"></i>
                            <span class="fw-bold">Privacy Protected</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Address Modal -->
<div class="modal fade" id="addressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <!-- This would typically come from user's saved addresses -->
                    <a href="#" class="list-group-item list-group-item-action address-item" 
                       data-address="123 Main St, New York, NY 10001">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>Home</strong>
                                <div class="text-muted small">123 Main St, New York, NY 10001</div>
                            </div>
                            <i class="fas fa-check text-success"></i>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action address-item"
                       data-address="456 Work Ave, Suite 100, New York, NY 10002">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>Office</strong>
                                <div class="text-muted small">456 Work Ave, Suite 100, New York, NY 10002</div>
                            </div>
                            <i class="fas fa-check text-success" style="opacity: 0;"></i>
                        </div>
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Use Selected</button>
            </div>
        </div>
    </div>
</div>

<!-- Success Page: `user/orders/payment-success.php` -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <div class="success-icon d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 mb-3" 
                         style="width: 100px; height: 100px;">
                        <i class="fas fa-check fa-3x text-success"></i>
                    </div>
                    <h2 class="text-success">Payment Successful!</h2>
                    <p class="text-muted">Your order has been placed successfully</p>
                </div>
                
                <div class="row justify-content-center mb-4">
                    <div class="col-md-8">
                        <div class="card border-success">
                            <div class="card-body">
                                <h5 class="card-title">Order Details</h5>
                                <div class="row text-start">
                                    <div class="col-6">
                                        <small class="text-muted">Order Number</small>
                                        <div class="fw-bold">ORD-20260204-1234</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Total Amount</small>
                                        <div class="fw-bold text-success">$<?php echo number_format($total_amount, 2); ?></div>
                                    </div>
                                    <div class="col-6 mt-3">
                                        <small class="text-muted">Payment Method</small>
                                        <div class="fw-bold">Credit Card</div>
                                    </div>
                                    <div class="col-6 mt-3">
                                        <small class="text-muted">Invoice Number</small>
                                        <div class="fw-bold">INV-20260204-5678</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-center gap-3">
                    <button class="btn btn-outline-primary" onclick="printInvoice()">
                        <i class="fas fa-print me-2"></i> Print Invoice
                    </button>
                    <button class="btn btn-primary" onclick="downloadPDF()">
                        <i class="fas fa-download me-2"></i> Download PDF
                    </button>
                    <a href="dashboard.php" class="btn btn-success">
                        <i class="fas fa-tachometer-alt me-2"></i> Go to Dashboard
                    </a>
                </div>
                
                <div class="mt-4">
                    <p class="text-muted small">
                        A confirmation email has been sent to your registered email address.<br>
                        You can track your order from your dashboard.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Show/hide billing address
    $('#sameAsBilling').change(function() {
        if ($(this).is(':checked')) {
            $('#billingAddressSection').slideUp();
            $('textarea[name="billing_address"]').val($('textarea[name="shipping_address"]').val());
        } else {
            $('#billingAddressSection').slideDown();
        }
    });
    
    // Show/hide saved cards
    $('#useSavedCard').change(function() {
        if ($(this).is(':checked')) {
            $('#savedCardsSection').slideDown();
            $('#creditCardSection').slideUp();
        } else {
            $('#savedCardsSection').slideUp();
            $('#creditCardSection').slideDown();
        }
    });
    
    // Show/hide payment method sections
    $('input[name="payment_method"]').change(function() {
        const method = $(this).val();
        
        // Hide all sections
        $('#creditCardSection').hide();
        $('#paypalSection').hide();
        $('#bankTransferSection').hide();
        $('#stripeSection').hide();
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
            case 'stripe':
                $('#stripeSection').show();
                // Initialize Stripe Elements here
                break;
            case 'cod':
                $('#codSection').show();
                break;
        }
    });
    
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
    
    // Address selection
    $('.address-item').click(function(e) {
        e.preventDefault();
        $('.address-item .fa-check').css('opacity', '0');
        $(this).find('.fa-check').css('opacity', '1');
        
        const address = $(this).data('address');
        $('textarea[name="shipping_address"]').val(address);
        
        if ($('#sameAsBilling').is(':checked')) {
            $('textarea[name="billing_address"]').val(address);
        }
    });
    
    // Form validation
    $('#paymentForm').submit(function(e) {
        let isValid = true;
        const method = $('input[name="payment_method"]:checked').val();
        
        // Clear previous errors
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        // Shipping address validation
        if (!$('textarea[name="shipping_address"]').val().trim()) {
            showError('shipping_address', 'Shipping address is required');
            isValid = false;
        }
        
        // Payment method specific validation
        if (method === 'credit_card' || method === 'debit_card') {
            if (!$('#useSavedCard').is(':checked')) {
                if (!$('input[name="card_holder"]').val().trim()) {
                    showError('card_holder', 'Card holder name is required');
                    isValid = false;
                }
                if (!$('input[name="card_number"]').val().trim()) {
                    showError('card_number', 'Card number is required');
                    isValid = false;
                }
                if (!$('input[name="card_expiry"]').val().trim()) {
                    showError('card_expiry', 'Expiry date is required');
                    isValid = false;
                }
                if (!$('input[name="card_cvv"]').val().trim()) {
                    showError('card_cvv', 'CVV is required');
                    isValid = false;
                }
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            showToast('Please fix the errors in the form', 'error');
        } else {
            // Show processing modal
            showProcessingModal();
        }
    });
    
    function showError(fieldName, message) {
        const field = $(`[name="${fieldName}"]`);
        field.addClass('is-invalid');
        field.after(`<div class="invalid-feedback">${message}</div>`);
    }
});

// Show processing modal
function showProcessingModal() {
    const modal = $(`
        <div class="modal fade" id="processingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <h4>Processing Payment</h4>
                        <p class="text-muted">Please wait while we process your payment</p>
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

// Print invoice function
function printInvoice() {
    window.open('invoice-print.php?invoice_id=<?php echo isset($_SESSION["invoice_id"]) ? $_SESSION["invoice_id"] : ""; ?>', '_blank');
}

// Download PDF function
function downloadPDF() {
    window.location.href = 'invoice-pdf.php?invoice_id=<?php echo isset($_SESSION["invoice_id"]) ? $_SESSION["invoice_id"] : ""; ?>';
}

// Toast notification
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

// Initialize payment method sections
$(document).ready(function() {
    // Trigger change to show correct section
    $('input[name="payment_method"]:checked').trigger('change');
    
    // If payment was successful, show success modal
    <?php if (isset($_SESSION['success']) && isset($_SESSION['order_id'])): ?>
        $('#successModal').modal('show');
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
});
</script>

<!-- CSS Styles -->
<style>
.payment-page .payment-method-option .btn {
    height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.payment-page .payment-method-option .btn i {
    margin-bottom: 8px;
}

.payment-page .sticky-top {
    z-index: 1020;
}

.payment-page .object-fit-cover {
    object-fit: cover;
}

.payment-page .card-preview {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.payment-page .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.payment-page .invalid-feedback {
    display: block;
}

.payment-page .modal-backdrop {
    z-index: 1055;
}

.payment-page .modal {
    z-index: 1060;
}

/* Credit card icons */
.payment-page .card-type-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 24px;
}

/* Progress bar animation */
@keyframes progressBarAnimation {
    0% { background-position: 0 0; }
    100% { background-position: 40px 0; }
}

.payment-page .progress-bar-animated {
    background-image: linear-gradient(
        45deg,
        rgba(255, 255, 255, 0.15) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255, 255, 255, 0.15) 50%,
        rgba(255, 255, 255, 0.15) 75%,
        transparent 75%,
        transparent
    );
    background-size: 40px 40px;
    animation: progressBarAnimation 1s linear infinite;
}
</style>

<?php require_once '../../includes/footer.php'; ?>