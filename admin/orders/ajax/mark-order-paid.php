<?php
// admin/orders/ajax/mark-order-paid.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Get order details
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Update payment status
    $stmt = $db->prepare("UPDATE orders SET payment_status = 'completed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$order_id]);
    
    // Create payment record
    $stmt = $db->prepare("
        INSERT INTO payments (user_id, order_id, payment_method, amount, status, created_at)
        VALUES (?, ?, ?, ?, 'completed', NOW())
    ");
    $stmt->execute([
        $order['user_id'] ?? null,
        $order_id,
        $order['payment_method'],
        $order['total_amount']
    ]);
    
    // Add to status history
    $stmt = $db->prepare("
        INSERT INTO order_status_history (order_id, status, changed_by, notes, created_at)
        VALUES (?, ?, ?, 'Payment marked as paid', NOW())
    ");
    $stmt->execute([$order_id, $order['status'], $_SESSION['user_id']]);
    
    // Notify customer if user exists
    if ($order['user_id']) {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, 'Payment Received', ?, 'success', NOW())
        ");
        $message = "Payment for order #{$order['order_number']} has been received and confirmed.";
        $stmt->execute([$order['user_id'], $message]);
    }
    
    // Log activity
    logUserActivity($_SESSION['user_id'], 'payment_marked_paid', 
        "Marked order #{$order['order_number']} as paid");
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order marked as paid successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}