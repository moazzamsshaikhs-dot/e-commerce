<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Product not found';
    redirect('shop.php');
}

$page_title = 'Checkout & Payment';
require_once '../../includes/header.php';

$db = getDB();
$product_id = (int)$_GET['id'];

// Get product details with vendor info
$stmt = $db->prepare("
    SELECT p.*, 
           c.name as category_name,
           u.id as vendor_id,
           u.username as vendor_username,
           u.full_name as vendor_name,
           u.vendor_rating,
           vs.store_name,
           vs.store_logo,
           vs.payment_methods
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

// Get user details for billing
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Parse vendor payment methods
$vendor_payment_methods = json_decode($product['payment_methods'] ?? '[]', true);

// Get available payment methods
$payment_methods = [
    'credit_card' => [
        'name' => 'Credit/Debit Card',
        'icon' => 'fas fa-credit-card',
        'color' => 'primary',
        'description' => 'Visa, MasterCard, American Express',
        'enabled' => true
    ],
    'paypal' => [
        'name' => 'PayPal',
        'icon' => 'fab fa-paypal',
        'color' => 'primary',
        'description' => 'Pay with PayPal account',
        'enabled' => true
    ],
    'bank_transfer' => [
        'name' => 'Bank Transfer',
        'icon' => 'fas fa-university',
        'color' => 'success',
        'description' => 'Direct bank transfer',
        'enabled' => true
    ],
    'cash_on_delivery' => [
        'name' => 'Cash on Delivery',
        'icon' => 'fas fa-money-bill-wave',
        'color' => 'success',
        'description' => 'Pay when you receive',
        'enabled' => true
    ]
];

// Filter payment methods based on vendor settings
if (!empty($vendor_payment_methods) && is_array($vendor_payment_methods)) {
    foreach ($payment_methods as $key => $method) {
        if (!in_array($key, $vendor_payment_methods)) {
            $payment_methods[$key]['enabled'] = false;
        }
    }
}

// Get shipping address if exists
$shipping_address = $user['address'] ?? '';
$billing_address = $user['billing_address'] ?? $shipping_address;

// Calculate totals
$subtotal = $product['price'];
$shipping_fee = 5.00; // Fixed shipping for demo
$tax_rate = 0.10; // 10% tax
$tax_amount = $subtotal * $tax_rate;
$total = $subtotal + $shipping_fee + $tax_amount;

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    $use_same_address = isset($_POST['use_same_address']);
    
    // Get addresses
    $shipping_name = trim($_POST['shipping_name']);
    $shipping_phone = trim($_POST['shipping_phone']);
    $shipping_address = trim($_POST['shipping_address']);
    $shipping_city = trim($_POST['shipping_city']);
    $shipping_country = trim($_POST['shipping_country']);
    $shipping_postal = trim($_POST['shipping_postal']);
    
    if ($use_same_address) {
        $billing_name = $shipping_name;
        $billing_phone = $shipping_phone;
        $billing_address = $shipping_address;
        $billing_city = $shipping_city;
        $billing_country = $shipping_country;
        $billing_postal = $shipping_postal;
    } else {
        $billing_name = trim($_POST['billing_name']);
        $billing_phone = trim($_POST['billing_phone']);
        $billing_address = trim($_POST['billing_address']);
        $billing_city = trim($_POST['billing_city']);
        $billing_country = trim($_POST['billing_country']);
        $billing_postal = trim($_POST['billing_postal']);
    }
    
    // Validate
    $errors = [];
    if ($quantity < 1 || $quantity > $product['stock']) {
        $errors[] = 'Invalid quantity';
    }
    if (empty($payment_method)) {
        $errors[] = 'Please select a payment method';
    }
    if (empty($shipping_name) || empty($shipping_address) || empty($shipping_phone)) {
        $errors[] = 'Please fill all required shipping fields';
    }
    
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Generate order number
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad($_SESSION['user_id'], 4, '0', STR_PAD_LEFT) . '-' . time();
            
            // Create order
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, order_number, total_amount, status, payment_method, 
                    payment_status, shipping_address, billing_address, 
                    order_date, estimated_delivery
                ) VALUES (?, ?, ?, 'pending', ?, 'pending', ?, ?, NOW(), 
                DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            
            $shipping_full = implode(', ', [
                $shipping_name,
                $shipping_address,
                $shipping_city,
                $shipping_country,
                $shipping_postal,
                'Phone: ' . $shipping_phone
            ]);
            
            $billing_full = implode(', ', [
                $billing_name,
                $billing_address,
                $billing_city,
                $billing_country,
                $billing_postal,
                'Phone: ' . $billing_phone
            ]);
            
            $stmt->execute([
                $_SESSION['user_id'],
                $order_number,
                $total * $quantity,
                $payment_method,
                $shipping_full,
                $billing_full
            ]);
            
            $order_id = $db->lastInsertId();
            
            // Add order item
            $stmt = $db->prepare("
                INSERT INTO order_items (
                    order_id, product_id, quantity, unit_price, subtotal
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $item_subtotal = $subtotal * $quantity;
            $stmt->execute([
                $order_id,
                $product_id,
                $quantity,
                $subtotal,
                $item_subtotal
            ]);
            
            // Create vendor earnings record
            $stmt = $db->prepare("
                INSERT INTO vendor_earnings (
                    vendor_id, order_id, product_id, order_item_id,
                    product_price, commission, commission_amount, vendor_amount,
                    status
                ) VALUES (?, ?, ?, ?, ?, 10, ?, ?, 'pending')
            ");
            
            $commission_amount = ($item_subtotal * 10) / 100;
            $vendor_amount = $item_subtotal - $commission_amount;
            
            $stmt->execute([
                $product['vendor_id'],
                $order_id,
                $product_id,
                $db->lastInsertId(), // order_item_id
                $subtotal,
                $commission_amount,
                $vendor_amount
            ]);
            
            // Create payment record
            $stmt = $db->prepare("
                INSERT INTO payments (
                    user_id, order_id, payment_method, transaction_id,
                    amount, currency, status, payment_details, created_at
                ) VALUES (?, ?, ?, ?, ?, 'USD', 'pending', ?, NOW())
            ");
            
            $transaction_id = strtoupper(substr(md5(time()), 0, 12));
            $payment_details = json_encode([
                'shipping_address' => $shipping_full,
                'billing_address' => $billing_full,
                'quantity' => $quantity
            ]);
            
            $stmt->execute([
                $_SESSION['user_id'],
                $order_id,
                $payment_method,
                $transaction_id,
                $total * $quantity,
                $payment_details
            ]);
            
            // Update product stock
            $stmt = $db->prepare("
                UPDATE products 
                SET stock = stock - ?, sales_count = sales_count + ?
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $quantity, $product_id]);
            
            // Update user statistics
            $stmt = $db->prepare("
                UPDATE users 
                SET total_sales = total_sales + ?
                WHERE id = ? AND user_type = 'vendor'
            ");
            $stmt->execute([$quantity, $product['vendor_id']]);
            
            // Commit transaction
            $db->commit();
            
            // Process payment based on method
            switch ($payment_method) {
                case 'credit_card':
                    // Redirect to card payment gateway
                    $_SESSION['order_id'] = $order_id;
                    $_SESSION['payment_amount'] = $total * $quantity;
                    redirect('process-card-payment.php');
                    break;
                    
                case 'paypal':
                    // Redirect to PayPal
                    $_SESSION['order_id'] = $order_id;
                    redirect('process-paypal.php');
                    break;
                    
                case 'bank_transfer':
                    // Show bank details
                    $_SESSION['success'] = 'Order placed successfully! Please transfer payment to our bank account.';
                    $_SESSION['order_id'] = $order_id;
                    redirect('bank-transfer.php');
                    break;
                    
                case 'cash_on_delivery':
                    // Order confirmed for COD
                    $_SESSION['success'] = 'Order placed successfully! Pay when you receive the product.';
                    $_SESSION['order_id'] = $order_id;
                    redirect('order-confirmation.php');
                    break;
                    
                default:
                    $_SESSION['success'] = 'Order placed successfully!';
                    redirect('order-confirmation.php');
            }
            
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Error processing order: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Log activity
logUserActivity($_SESSION['user_id'], 'checkout_start', 'Started checkout for product: ' . $product['name']);
?>

<div class="checkout-page">
    <!-- Progress Steps -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="steps">
                <div class="step completed">
                    <div class="step-circle">1</div>
                    <div class="step-label">Cart</div>
                </div>
                <div class="step active">
                    <div class="step-circle">2</div>
                    <div class="step-label">Checkout</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Payment</div>
                </div>
                <div class="step">
                    <div class="step-circle">4</div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Order Form -->
        <div class="col-lg-8">
            <form method="POST" id="checkoutForm">
                <!-- Product Summary -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i> Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo SITE_URL . 'assets/images/products/' . $product['image']; ?>" 
                                         class="img-fluid rounded" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 80px;">
                                        <i class="fas fa-box text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                <p class="text-muted small mb-2">Category: <?php echo $product['category_name']; ?></p>
                                <div class="d-flex align-items-center">
                                    <div class="text-warning me-2">
                                        <?php
                                        $rating = $product['average_rating'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++):
                                            $starClass = $i <= floor($rating) ? 'fas fa-star' : 'far fa-star';
                                        ?>
                                            <i class="<?php echo $starClass; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-muted small">(<?php echo $product['review_count'] ?? 0; ?>)</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Quantity</label>
                                <input type="number" 
                                       name="quantity" 
                                       class="form-control" 
                                       value="1" 
                                       min="1" 
                                       max="<?php echo $product['stock']; ?>"
                                       required>
                            </div>
                            <div class="col-md-2 text-end">
                                <h5 class="text-primary mb-0">$<?php echo number_format($product['price'], 2); ?></h5>
                                <?php if ($product['old_price']): ?>
                                    <small class="text-muted text-decoration-line-through">
                                        $<?php echo number_format($product['old_price'], 2); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Vendor Info -->
                        <div class="border-top mt-3 pt-3">
                            <div class="d-flex align-items-center">
                                <?php if ($product['store_logo']): ?>
                                    <img src="<?php echo SITE_URL . 'uploads/vendors/' . $product['store_logo']; ?>" 
                                         class="rounded-circle me-2" 
                                         style="width: 30px; height: 30px; object-fit: cover;"
                                         alt="<?php echo htmlspecialchars($product['store_name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-store text-primary me-2"></i>
                                <?php endif; ?>
                                <div>
                                    <small class="text-muted">Sold by</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($product['store_name'] ?? $product['vendor_name']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Address -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-truck me-2"></i> Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       name="shipping_name" 
                                       class="form-control" 
                                       value="<?php echo $user['full_name'] ?? ''; ?>"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" 
                                       name="shipping_phone" 
                                       class="form-control" 
                                       value="<?php echo $user['phone'] ?? ''; ?>"
                                       required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea name="shipping_address" 
                                          class="form-control" 
                                          rows="2" 
                                          required><?php echo $shipping_address; ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" 
                                       name="shipping_city" 
                                       class="form-control" 
                                       value="<?php echo $user['city'] ?? ''; ?>"
                                       required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Country <span class="text-danger">*</span></label>
                                <input type="text" 
                                       name="shipping_country" 
                                       class="form-control" 
                                       value="<?php echo $user['country'] ?? ''; ?>"
                                       required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Postal Code</label>
                                <input type="text" 
                                       name="shipping_postal" 
                                       class="form-control" 
                                       value="<?php echo $user['postal_code'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Billing Address -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i> Billing Address</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="use_same_address"
                                   name="use_same_address"
                                   checked>
                            <label class="form-check-label" for="use_same_address">
                                Same as shipping address
                            </label>
                        </div>
                        
                        <div id="billing_address_fields" style="display: none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="billing_name" 
                                           class="form-control" 
                                           value="<?php echo $user['full_name'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" 
                                           name="billing_phone" 
                                           class="form-control" 
                                           value="<?php echo $user['phone'] ?? ''; ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address <span class="text-danger">*</span></label>
                                    <textarea name="billing_address" 
                                              class="form-control" 
                                              rows="2"><?php echo $billing_address; ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">City <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="billing_city" 
                                           class="form-control" 
                                           value="<?php echo $user['city'] ?? ''; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Country <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="billing_country" 
                                           class="form-control" 
                                           value="<?php echo $user['country'] ?? ''; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" 
                                           name="billing_postal" 
                                           class="form-control" 
                                           value="<?php echo $user['postal_code'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i> Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($payment_methods as $method_key => $method): ?>
                                <?php if ($method['enabled']): ?>
                                    <div class="col-md-6">
                                        <div class="form-check payment-method-card">
                                            <input class="form-check-input" 
                                                   type="radio" 
                                                   name="payment_method" 
                                                   id="method_<?php echo $method_key; ?>"
                                                   value="<?php echo $method_key; ?>"
                                                   data-method="<?php echo $method_key; ?>"
                                                   required>
                                            <label class="form-check-label w-100" for="method_<?php echo $method_key; ?>">
                                                <div class="card border h-100">
                                                    <div class="card-body d-flex align-items-center">
                                                        <div class="avatar-md bg-<?php echo $method['color']; ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                                            <i class="<?php echo $method['icon']; ?> fa-2x text-<?php echo $method['color']; ?>"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1"><?php echo $method['name']; ?></h6>
                                                            <small class="text-muted"><?php echo $method['description']; ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Card Details (Hidden by default) -->
                        <div id="card_details" style="display: none;" class="mt-4">
                            <h6 class="mb-3">Card Details</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Card Number</label>
                                    <input type="text" 
                                           class="form-control" 
                                           placeholder="1234 5678 9012 3456"
                                           maxlength="19">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="text" 
                                           class="form-control" 
                                           placeholder="MM/YY">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">CVV</label>
                                    <input type="text" 
                                           class="form-control" 
                                           placeholder="123"
                                           maxlength="3">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cardholder Name</label>
                                    <input type="text" 
                                           class="form-control" 
                                           placeholder="Name on card">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bank Transfer Details (Hidden by default) -->
                        <div id="bank_details" style="display: none;" class="mt-4">
                            <h6 class="mb-3">Bank Transfer Instructions</h6>
                            <div class="alert alert-info">
                                <p class="mb-2"><strong>Bank Name:</strong> National Bank</p>
                                <p class="mb-2"><strong>Account Name:</strong> ShopEase Pro</p>
                                <p class="mb-2"><strong>Account Number:</strong> 1234 5678 9012 3456</p>
                                <p class="mb-2"><strong>IFSC Code:</strong> NBIN0001234</p>
                                <p class="mb-0"><strong>Note:</strong> Please include order number in transaction reference</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Notes -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <label class="form-label">Order Notes (Optional)</label>
                        <textarea class="form-control" 
                                  name="order_notes" 
                                  rows="3" 
                                  placeholder="Special instructions for your order..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Right Column - Order Summary -->
        <div class="col-lg-4">
            <div class="sticky-top" style="top: 20px;">
                <!-- Order Summary -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span id="subtotalDisplay">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Shipping</span>
                                <span>$<?php echo number_format($shipping_fee, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Tax (10%)</span>
                                <span id="taxDisplay">$<?php echo number_format($tax_amount, 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Total</span>
                                <span id="totalDisplay">$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                        
                        <!-- Security Badge -->
                        <div class="text-center mb-3">
                            <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                            <p class="small text-muted mb-0">
                                <strong>Secure Checkout</strong><br>
                                256-bit SSL encryption
                            </p>
                        </div>
                        
                        <!-- Terms Agreement -->
                        <div class="form-check mb-3">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="terms_agreement" 
                                   required>
                            <label class="form-check-label small" for="terms_agreement">
                                I agree to the <a href="<?php echo SITE_URL; ?>terms.php" target="_blank">Terms & Conditions</a> 
                                and authorize the payment.
                            </label>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" 
                                form="checkoutForm" 
                                class="btn btn-primary btn-lg w-100"
                                id="placeOrderBtn">
                            <i class="fas fa-lock me-2"></i> Place Order
                        </button>
                        
                        <!-- Continue Shopping -->
                        <div class="text-center mt-3">
                            <a href="shop.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-2"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Support Info -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-headset fa-2x text-primary mb-3"></i>
                        <h6>Need Help?</h6>
                        <p class="small text-muted mb-2">Our support team is here to help</p>
                        <a href="<?php echo SITE_URL; ?>user/support/support.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-question-circle me-2"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Processing Modal -->
<div class="modal fade" id="processingModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <h4>Processing Payment</h4>
                <p class="text-muted">Please wait while we process your payment.</p>
                <div class="progress mt-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Billing address toggle
    $('#use_same_address').change(function() {
        if ($(this).prop('checked')) {
            $('#billing_address_fields').slideUp();
        } else {
            $('#billing_address_fields').slideDown();
        }
    });
    
    // Payment method selection
    $('input[name="payment_method"]').change(function() {
        const method = $(this).data('method');
        
        // Hide all details sections
        $('#card_details').slideUp();
        $('#bank_details').slideUp();
        
        // Show relevant section
        if (method === 'credit_card') {
            $('#card_details').slideDown();
        } else if (method === 'bank_transfer') {
            $('#bank_details').slideDown();
        }
        
        // Update place order button text
        updatePlaceOrderButton(method);
    });
    
    // Quantity change handler
    $('input[name="quantity"]').on('input', function() {
        const quantity = parseInt($(this).val()) || 1;
        const maxStock = parseInt($(this).attr('max'));
        
        if (quantity > maxStock) {
            $(this).val(maxStock);
            showToast('Maximum quantity is ' + maxStock, 'warning');
            updateTotals(maxStock);
        } else if (quantity < 1) {
            $(this).val(1);
            updateTotals(1);
        } else {
            updateTotals(quantity);
        }
    });
    
    // Update totals based on quantity
    function updateTotals(quantity) {
        const subtotal = <?php echo $subtotal; ?> * quantity;
        const taxAmount = subtotal * <?php echo $tax_rate; ?>;
        const shipping = <?php echo $shipping_fee; ?>;
        const total = subtotal + taxAmount + shipping;
        
        $('#subtotalDisplay').text('$' + subtotal.toFixed(2));
        $('#taxDisplay').text('$' + taxAmount.toFixed(2));
        $('#totalDisplay').text('$' + total.toFixed(2));
    }
    
    // Update place order button based on payment method
    function updatePlaceOrderButton(method) {
        const btn = $('#placeOrderBtn');
        const icons = {
            'credit_card': 'fas fa-credit-card',
            'paypal': 'fab fa-paypal',
            'bank_transfer': 'fas fa-university',
            'cash_on_delivery': 'fas fa-money-bill-wave'
        };
        const texts = {
            'credit_card': 'Pay with Card',
            'paypal': 'Pay with PayPal',
            'bank_transfer': 'Confirm Bank Transfer',
            'cash_on_delivery': 'Place Order (COD)'
        };
        
        btn.find('i').attr('class', icons[method] + ' me-2');
        btn.find('span').text(texts[method]);
    }
    
    // Form submission
    $('#checkoutForm').submit(function(e) {
        e.preventDefault();
        
        // Validate terms agreement
        if (!$('#terms_agreement').prop('checked')) {
            showToast('Please agree to the terms & conditions', 'error');
            return;
        }
        
        // Validate payment method
        const paymentMethod = $('input[name="payment_method"]:checked').val();
        if (!paymentMethod) {
            showToast('Please select a payment method', 'error');
            return;
        }
        
        // Validate quantity
        const quantity = parseInt($('input[name="quantity"]').val());
        const maxStock = <?php echo $product['stock']; ?>;
        if (quantity > maxStock) {
            showToast('Insufficient stock available', 'error');
            return;
        }
        
        // Validate card details if credit card selected
        if (paymentMethod === 'credit_card') {
            const cardNumber = $('#card_details input:eq(0)').val().trim();
            const expiryDate = $('#card_details input:eq(1)').val().trim();
            const cvv = $('#card_details input:eq(2)').val().trim();
            const cardName = $('#card_details input:eq(3)').val().trim();
            
            if (!cardNumber || !expiryDate || !cvv || !cardName) {
                showToast('Please fill all card details', 'error');
                return;
            }
            
            // Validate card number (simple check)
            if (cardNumber.replace(/\s/g, '').length !== 16) {
                showToast('Please enter a valid 16-digit card number', 'error');
                return;
            }
            
            if (cvv.length !== 3) {
                showToast('Please enter a valid 3-digit CVV', 'error');
                return;
            }
        }
        
        // Show processing modal
        $('#processingModal').modal('show');
        
        // Submit form after delay for visual effect
        setTimeout(() => {
            this.submit();
        }, 2000);
    });
    
    // Card number formatting
    $('#card_details input:eq(0)').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        value = value.replace(/(\d{4})/g, '$1 ').trim();
        $(this).val(value.substring(0, 19));
    });
    
    // Expiry date formatting
    $('#card_details input:eq(1)').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        $(this).val(value.substring(0, 5));
    });
    
    // CVV formatting
    $('#card_details input:eq(2)').on('input', function() {
        $(this).val($(this).val().replace(/\D/g, '').substring(0, 3));
    });
    
    // Phone number formatting
    $('input[type="tel"]').on('input', function() {
        $(this).val($(this).val().replace(/\D/g, ''));
    });
});

// Toast notification
function showToast(message, type = 'info') {
    const toast = $(`
        <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0 position-fixed" 
             style="top: 20px; right: 20px; z-index: 1060;" role="alert" 
             aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `);
    $('body').append(toast);
    const bsToast = new bootstrap.Toast(toast[0], { delay: 5000 });
    bsToast.show();
    toast.on('hidden.bs.toast', function () {
        $(this).remove();
    });
}
</script>
<?php
include_once '../../includes/footer.php';
?>