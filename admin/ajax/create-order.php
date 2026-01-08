<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get customer ID (either existing or new)
    $customer_id = null;
    
    if (!empty($_POST['customer_id'])) {
        $customer_id = (int)$_POST['customer_id'];
    } elseif (!empty($_POST['new_customer_name']) && !empty($_POST['new_customer_email'])) {
        // Create new customer
        $stmt = $db->prepare("INSERT INTO users (username, email, full_name, user_type, created_at) 
                              VALUES (?, ?, ?, 'user', NOW())");
        $email = $_POST['new_customer_email'];
        $username = strtolower(str_replace(' ', '', $_POST['new_customer_name'])) . rand(100, 999);
        $stmt->execute([$username, $email, $_POST['new_customer_name']]);
        $customer_id = $db->lastInsertId();
    } else {
        throw new Exception('Customer information is required');
    }
    
    // Generate order number
    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    // Calculate totals
    $subtotal = (float)$_POST['subtotal'];
    $shipping_cost = (float)$_POST['shipping_cost'];
    $tax_rate = (float)$_POST['tax_rate'];
    $tax_amount = ($subtotal * $tax_rate) / 100;
    $total_amount = $subtotal + $shipping_cost + $tax_amount;
    
    // Create order
    $stmt = $db->prepare("INSERT INTO orders (
        user_id, order_number, total_amount, status, payment_method, payment_status,
        shipping_address, billing_address, shipping_method, shipping_carrier_id,
        tracking_number, customer_notes, is_gift, gift_message, priority, order_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([
        $customer_id,
        $order_number,
        $total_amount,
        $_POST['order_status'],
        $_POST['payment_method'],
        $_POST['payment_status'],
        $_POST['shipping_address'],
        $_POST['billing_address'] ?: $_POST['shipping_address'],
        $_POST['shipping_method'],
        $_POST['shipping_carrier_id'] ?: null,
        $_POST['tracking_number'] ?: null,
        $_POST['customer_notes'] ?: null,
        isset($_POST['is_gift']) ? 1 : 0,
        $_POST['gift_message'] ?: null,
        $_POST['order_priority'] ?: 'normal'
    ]);
    
    $order_id = $db->lastInsertId();
    
    // Add order items
    $products = $_POST['products'] ?? [];
    $prices = $_POST['prices'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
    for ($i = 0; $i < count($products); $i++) {
        if (!empty($products[$i])) {
            $product_id = (int)$products[$i];
            $quantity = (int)$quantities[$i];
            $unit_price = (float)str_replace('$', '', $prices[$i]);
            $subtotal = $unit_price * $quantity;
            
            $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $product_id, $quantity, $unit_price, $subtotal]);
            
            // Update product stock
            $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$quantity, $product_id]);
        }
    }
    
    // Add initial status history
    $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                          VALUES (?, ?, ?, 'Order created manually by admin')");
    $stmt->execute([$order_id, $_POST['order_status'], $_SESSION['user_id']]);
    
    // Add internal note if provided
    if (!empty($_POST['internal_notes'])) {
        $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) 
                              VALUES (?, ?, 'internal', ?)");
        $stmt->execute([$order_id, $_SESSION['user_id'], $_POST['internal_notes']]);
    }
    
    // If payment is completed, create payment record
    if ($_POST['payment_status'] === 'completed') {
        $stmt = $db->prepare("INSERT INTO payments (user_id, order_id, payment_method, amount, status) 
                              VALUES (?, ?, ?, ?, 'completed')");
        $stmt->execute([$customer_id, $order_id, $_POST['payment_method'], $total_amount]);
    }
    
    // Commit transaction
    $db->commit();
    
    // Send notification to customer
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                          VALUES (?, 'New Order Created', ?, 'success')");
    $stmt->execute([
        $customer_id,
        "A new order #{$order_number} has been created for you."
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $order_id,
        'order_number' => $order_number
    ]);
    
} catch(Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>