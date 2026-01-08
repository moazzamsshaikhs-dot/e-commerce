<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    redirect('create-order.php');
}

// Process the order
$order_data = [];
$errors = [];

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // =============== VALIDATE AND PROCESS CUSTOMER ===============
    $customer_id = null;
    
    if (!empty($_POST['customer_id'])) {
        $customer_id = (int)$_POST['customer_id'];
        
        // Verify customer exists
        $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND user_type = 'user'");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            throw new Exception('Selected customer not found');
        }
        
        $order_data['customer'] = $customer;
    } 
    elseif (!empty($_POST['new_customer_name']) && !empty($_POST['new_customer_email'])) {
        // Create new customer
        $new_name = trim($_POST['new_customer_name']);
        $new_email = trim($_POST['new_customer_email']);
        $new_phone = trim($_POST['new_customer_phone'] ?? '');
        
        // Validate email
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address for new customer');
        }
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$new_email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists. Please select existing customer.');
        }
        
        // Generate username
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $new_name)) . rand(100, 999);
        
        // Insert new customer
        $stmt = $db->prepare("INSERT INTO users (username, email, full_name, phone, user_type, created_at) 
                              VALUES (?, ?, ?, ?, 'user', NOW())");
        $stmt->execute([$username, $new_email, $new_name, $new_phone]);
        $customer_id = $db->lastInsertId();
        
        $order_data['customer'] = [
            'id' => $customer_id,
            'full_name' => $new_name,
            'email' => $new_email
        ];
    } 
    else {
        throw new Exception('Customer information is required');
    }
    
    // =============== VALIDATE PRODUCTS ===============
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $order_items = [];
    
    if (empty($products) || count($products) == 0) {
        throw new Exception('At least one product is required');
    }
    
    // Validate each product
    $product_subtotal = 0;
    for ($i = 0; $i < count($products); $i++) {
        if (!empty($products[$i])) {
            $product_id = (int)$products[$i];
            $quantity = (int)($quantities[$i] ?? 1);
            
            if ($quantity < 1) {
                throw new Exception('Quantity must be at least 1');
            }
            
            // Get product details and check stock
            $stmt = $db->prepare("SELECT id, name, price, stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception("Product ID {$product_id} not found");
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("Insufficient stock for {$product['name']}. Available: {$product['stock']}");
            }
            
            $unit_price = $product['price'];
            $subtotal = $unit_price * $quantity;
            $product_subtotal += $subtotal;
            
            $order_items[] = [
                'product_id' => $product_id,
                'name' => $product['name'],
                'price' => $unit_price,
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];
        }
    }
    
    // =============== CALCULATE TOTALS ===============
    $shipping_cost = (float)($_POST['shipping_cost'] ?? 0);
    $tax_rate = (float)($_POST['tax_rate'] ?? 0);
    $tax_amount = ($product_subtotal * $tax_rate) / 100;
    $total_amount = $product_subtotal + $shipping_cost + $tax_amount;
    
    // =============== CREATE ORDER ===============
    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    $stmt = $db->prepare("INSERT INTO orders (
        user_id, order_number, total_amount, status, payment_method, payment_status,
        shipping_address, billing_address, shipping_method, shipping_carrier_id,
        tracking_number, customer_notes, is_gift, gift_message, priority, order_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? $shipping_address);
    $order_status = $_POST['order_status'] ?? 'pending';
    $payment_method = $_POST['payment_method'] ?? 'cod';
    $payment_status = $_POST['payment_status'] ?? 'pending';
    
    if (empty($shipping_address)) {
        throw new Exception('Shipping address is required');
    }
    
    $stmt->execute([
        $customer_id,
        $order_number,
        $total_amount,
        $order_status,
        $payment_method,
        $payment_status,
        $shipping_address,
        $billing_address,
        $_POST['shipping_method'] ?? 'standard',
        !empty($_POST['shipping_carrier_id']) ? (int)$_POST['shipping_carrier_id'] : null,
        trim($_POST['tracking_number'] ?? ''),
        trim($_POST['customer_notes'] ?? ''),
        isset($_POST['is_gift']) ? 1 : 0,
        trim($_POST['gift_message'] ?? ''),
        $_POST['order_priority'] ?? 'normal'
    ]);
    
    $order_id = $db->lastInsertId();
    
    // =============== ADD ORDER ITEMS ===============
    foreach ($order_items as $item) {
        $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['subtotal']
        ]);
        
        // Update product stock
        $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // =============== ADD STATUS HISTORY ===============
    $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                          VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $order_id,
        $order_status,
        $_SESSION['user_id'],
        "Order created manually by admin"
    ]);
    
    // =============== ADD INTERNAL NOTES IF ANY ===============
    if (!empty($_POST['internal_notes'])) {
        $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) 
                              VALUES (?, ?, 'internal', ?)");
        $stmt->execute([
            $order_id,
            $_SESSION['user_id'],
            trim($_POST['internal_notes'])
        ]);
    }
    
    // =============== CREATE PAYMENT RECORD IF PAID ===============
    if ($payment_status === 'completed') {
        $stmt = $db->prepare("INSERT INTO payments (user_id, order_id, payment_method, amount, status) 
                              VALUES (?, ?, ?, ?, 'completed')");
        $stmt->execute([
            $customer_id,
            $order_id,
            $payment_method,
            $total_amount
        ]);
    }
    
    // =============== COMMIT TRANSACTION ===============
    $db->commit();
    
    // Prepare order data for confirmation page
    $order_data['order_id'] = $order_id;
    $order_data['order_number'] = $order_number;
    $order_data['order_items'] = $order_items;
    $order_data['totals'] = [
        'subtotal' => $product_subtotal,
        'shipping' => $shipping_cost,
        'tax_rate' => $tax_rate,
        'tax_amount' => $tax_amount,
        'total' => $total_amount
    ];
    $order_data['shipping'] = [
        'address' => $shipping_address,
        'method' => $_POST['shipping_method'] ?? 'standard',
        'tracking' => trim($_POST['tracking_number'] ?? '')
    ];
    $order_data['payment'] = [
        'method' => $payment_method,
        'status' => $payment_status
    ];
    
    // Store in session for confirmation page
    $_SESSION['new_order_data'] = $order_data;
    
    // Clear any previous errors
    unset($_SESSION['error']);
    
    // Redirect to confirmation page
    redirect('order-confirmation.php?id=' . $order_id);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->rollBack();
    }
    
    $_SESSION['error'] = 'Error creating order: ' . $e->getMessage();
    $_SESSION['form_data'] = $_POST; // Save form data for repopulation
    redirect('create-order.php');
}
?><?php require_once '../includes/header.php'; ?>