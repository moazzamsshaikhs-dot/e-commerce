<?php
// Start output buffering at the very beginning
ob_start();

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Start output buffering at the very beginning
// Add manual includes for payment gateway classes
require_once '../../includes/payments/PaymentGatewayInterface.php';
require_once '../../includes/payments/PaymentGateway.php';
require_once '../../includes/payments/BankTransfer.php';
require_once '../../includes/payments/CashOnDelivery.php';
require_once '../../includes/payments/PayPalGateway.php';
require_once '../../includes/payments/StripeGateway.php';
require_once '../../includes/payments/EasypaisaGateway.php';
require_once '../../includes/payments/JazzCashGateway.php';

require_once '../../includes/payments/PaymentFactory.php';


// Rest of your code...
use Ecommerce\Payments\PaymentFactory;

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login to checkout';
    redirect(SITE_URL . 'user/login.php?redirect=user/orders/multi-item-checkout.php');
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

// Safe string function
function safe_html($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// =============== GET USER INFORMATION ===============
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// =============== GET SELECTED ITEMS FROM SESSION ===============
if (!isset($_SESSION['checkout_items']) || empty($_SESSION['checkout_items'])) {
    $_SESSION['error'] = 'No items selected for checkout';
    redirect('../../cart.php');
}

$selected_items = $_SESSION['checkout_items'];
$cart_items = [];
$subtotal = 0;
$total_items = 0;
$vendors = [];

// Fetch product details for selected items
foreach ($selected_items as $item) {
    $product_sql = "SELECT p.*, u.full_name as vendor_name 
                    FROM products p
                    LEFT JOIN users u ON p.vendor_id = u.id
                    WHERE p.id = ? AND p.approved_status = 'approved'";
    $product_stmt = $db->prepare($product_sql);
    $product_stmt->execute([$item['product_id']]);
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $quantity = min($item['quantity'], $product['stock']);
        $item_total = $product['price'] * $quantity;
        
        $cart_item = [
            'product_id' => $product['id'],
            'quantity' => $quantity,
            'name' => $product['name'],
            'price' => $product['price'],
            'stock' => $product['stock'],
            'image' => $product['image'],
            'vendor_id' => $product['vendor_id'],
            'vendor_name' => $product['vendor_name'] ?? 'Vendor',
            'subtotal' => $item_total
        ];
        
        $cart_items[] = $cart_item;
        $subtotal += $item_total;
        $total_items += $quantity;
        
        // Group by vendor
        if (!isset($vendors[$product['vendor_id']])) {
            $vendors[$product['vendor_id']] = [
                'name' => $product['vendor_name'] ?? 'Vendor',
                'items' => [],
                'subtotal' => 0
            ];
        }
        $vendors[$product['vendor_id']]['items'][] = $cart_item;
        $vendors[$product['vendor_id']]['subtotal'] += $item_total;
    }
}

// Default calculations
$shipping_cost = 5.99;
$tax_rate = 10;
$tax_amount = ($subtotal * $tax_rate) / 100;
$total = $subtotal + $shipping_cost + $tax_amount;

// =============== GET ACTIVE PAYMENT GATEWAYS ===============
$activeGateways = PaymentFactory::getActiveGateways($db);

// =============== HANDLE ORDER SUBMISSION ===============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? $shipping_address);
    $shipping_method = $_POST['shipping_method'] ?? 'standard';
    $gateway_code = $_POST['gateway_code'] ?? '';
    $customer_notes = trim($_POST['customer_notes'] ?? '');
    $same_as_shipping = isset($_POST['same_as_shipping']);
    
    // Calculate shipping cost based on selected method
    switch($shipping_method) {
        case 'express':
            $shipping_cost = 12.99;
            break;
        case 'overnight':
            $shipping_cost = 24.99;
            break;
        default:
            $shipping_cost = 5.99;
    }
    
    // Recalculate total
    $tax_amount = ($subtotal * $tax_rate) / 100;
    $total = $subtotal + $shipping_cost + $tax_amount;
    
    // Validation
    $errors = [];
    
    if (empty($shipping_address)) {
        $errors[] = 'Shipping address is required';
    }
    
    if (!$same_as_shipping && empty($billing_address)) {
        $errors[] = 'Billing address is required';
    }
    
    if (empty($gateway_code) || !isset($activeGateways[$gateway_code])) {
        $errors[] = 'Please select a payment method';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate order number
            $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Insert main order
            $order_sql = "INSERT INTO orders (
                user_id, order_number, total_amount, status, payment_method, payment_status,
                shipping_address, billing_address, shipping_method, customer_notes, order_date
            ) VALUES (?, ?, ?, 'pending', ?, 'pending', ?, ?, ?, ?, NOW())";
            
            $order_stmt = $db->prepare($order_sql);
            $order_stmt->execute([
                $user_id,
                $order_number,
                $total,
                $gateway_code,
                $shipping_address,
                $billing_address,
                $shipping_method,
                $customer_notes
            ]);
            
            $order_id = $db->lastInsertId();
            
            // Create order array for payment processing
            $order = [
                'id' => $order_id,
                'order_number' => $order_number,
                'user_id' => $user_id,
                'total_amount' => $total
            ];
            
            // Insert order items
            foreach ($cart_items as $item) {
                $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                            VALUES (?, ?, ?, ?, ?)";
                $item_stmt = $db->prepare($item_sql);
                $item_stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['subtotal']
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
            
            // Process payment
            $gateway = $activeGateways[$gateway_code]['instance'];
            
            // Prepare payment data
            $paymentData = [
                'total_amount' => $total,
                'currency' => 'USD',
                'order_id' => $order_id,
                'order_number' => $order_number,
                'user_id' => $user_id,
                'user_email' => $user['email'],
                'user_name' => $user['full_name'],
                'return_url' => SITE_URL . 'user/orders/order-confirmation.php?id=' . $order_id,
                'cancel_url' => SITE_URL . 'user/orders/multi-item-checkout.php'
            ];
            
            // Merge POST data for payment gateway specific fields
            $paymentData = array_merge($paymentData, $_POST);
            
            $paymentResult = $gateway->processPayment($order, $paymentData);
            
            if ($paymentResult['success']) {
    $db->commit();
    
    // Clear selected items from session
    unset($_SESSION['checkout_items']);
    
    // Log activity
    if (function_exists('logUserActivity')) {
        logUserActivity($user_id, 'order_placed', 'Placed order #' . $order_number);
    }
    
    // Handle redirect based on payment method
    if (isset($paymentResult['redirect'])) {
        redirect($paymentResult['redirect']);
    } else {
        // For COD and Bank Transfer, go to confirmation page
        $_SESSION['success'] = 'Order placed successfully!';
        redirect('order-confirmation.php?id=' . $order_id);
    }
}
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Order placement error: " . $e->getMessage());
            $_SESSION['error'] = 'Error placing order: ' . $e->getMessage();
            redirect('multi-item-checkout.php');
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
                <li class="breadcrumb-item"><a href="../../cart.php">Cart</a></li>
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

                            <!-- Payment Methods -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2">Select Payment Method</h6>
                                <div class="row">
                                    <?php foreach ($activeGateways as $code => $gateway): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="payment-gateway-card" data-gateway="<?php echo $code; ?>">
                                                <input type="radio" name="gateway_code" value="<?php echo $code; ?>" 
                                                       id="gateway_<?php echo $code; ?>" 
                                                       class="gateway-radio"
                                                       <?php echo ($code == 'cod' || $code == 'bank') ? 'checked' : ''; ?>>
                                                <label for="gateway_<?php echo $code; ?>" class="gateway-label">
                                                    <i class="<?php echo $gateway['config']['gateway_icon'] ?? 'fas fa-credit-card'; ?> me-2"></i>
                                                    <?php echo $gateway['config']['gateway_name'] ?? ucfirst($code); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Payment Method Details -->
                            <div class="mb-4" id="payment-details-container">
                                <?php foreach ($activeGateways as $code => $gateway): ?>
                                    <div class="payment-details" id="details-<?php echo $code; ?>" style="display: none;">
                                        <?php 
                                        $paymentData = [
                                            'total_amount' => $total,
                                            'currency' => 'USD',
                                            'user_id' => $user_id,
                                            'user_email' => $user['email'] ?? '',
                                            'user_name' => $user['full_name'] ?? ''
                                        ];
                                        echo $gateway['instance']->getPaymentForm($paymentData); 
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Order Notes -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2">Order Notes (Optional)</h6>
                                <textarea class="form-control" name="customer_notes" rows="3" 
                                          placeholder="Special instructions for delivery..."></textarea>
                            </div>

                            <button type="submit" name="place_order" class="btn btn-primary btn-lg w-100" id="placeOrderBtn">
                                <i class="fas fa-check-circle me-2"></i>Place Order (Pay $<?php echo number_format($total, 2); ?>)
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
                                        <?php if (!empty($item['image']) && file_exists('../../assets/images/products/' . $item['image'])): ?>
                                            <img src="<?php echo SITE_URL . 'assets/images/products/' . $item['image']; ?>" 
                                                 alt="<?php echo safe_html($item['name']); ?>"
                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                 class="rounded">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-box text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0"><?php echo safe_html(substr($item['name'], 0, 30)) . (strlen($item['name']) > 30 ? '...' : ''); ?></h6>
                                        <small class="text-muted">
                                            Qty: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?>
                                        </small>
                                    </div>
                                    <div class="fw-bold">
                                        $<?php echo number_format($item['subtotal'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Vendor Summary -->
                        <?php if (count($vendors) > 1): ?>
                            <div class="mb-3">
                                <h6 class="small">Vendors:</h6>
                                <?php foreach ($vendors as $vendor): ?>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span><?php echo safe_html($vendor['name']); ?></span>
                                        <span class="fw-bold">$<?php echo number_format($vendor['subtotal'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.checkout-page .payment-gateway-card {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkout-page .payment-gateway-card:hover {
    border-color: #007bff;
    background-color: #f8f9ff;
}

.checkout-page .payment-gateway-card.selected {
    border-color: #007bff;
    background-color: #f0f7ff;
}

.checkout-page .gateway-radio {
    display: none;
}

.checkout-page .gateway-label {
    display: block;
    cursor: pointer;
    font-weight: 500;
    margin: 0;
}

.checkout-page .payment-details {
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-top: 10px;
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
    // Toggle billing address
    $('#same_as_shipping').change(function() {
        if ($(this).is(':checked')) {
            $('#billing_address_section').slideUp();
        } else {
            $('#billing_address_section').slideDown();
        }
    });

    // Handle payment gateway selection
    $('.payment-gateway-card').click(function() {
        const gateway = $(this).data('gateway');
        
        // Update radio
        $(this).find('.gateway-radio').prop('checked', true);
        
        // Update selected state
        $('.payment-gateway-card').removeClass('selected');
        $(this).addClass('selected');
        
        // Show payment details
        $('.payment-details').hide();
        $('#details-' + gateway).show();
    });

    // Update shipping cost
    $('input[name="shipping_method"]').change(function() {
        let shippingCost = 5.99;
        const method = $(this).val();
        
        switch(method) {
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
        $('#placeOrderBtn').html('<i class="fas fa-check-circle me-2"></i>Place Order (Pay $' + total.toFixed(2) + ')');
    });

    // Trigger initial payment details
    $('.payment-gateway-card.selected').click();
});
</script>

<?php
require_once '../../includes/footer.php';
ob_end_flush();
?>