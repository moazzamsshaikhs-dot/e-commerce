<?php
// admin/ajax/cancel-order.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$reason = isset($input['reason']) ? trim($input['reason']) : '';

if (!$order_id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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

    if ($order['status'] == 'delivered' || $order['status'] == 'cancelled') {
        throw new Exception('Order cannot be cancelled');
    }

    // Update order status
    $stmt = $db->prepare("UPDATE orders SET status = 'cancelled', cancelled_date = NOW() WHERE id = ?");
    $stmt->execute([$order_id]);

    // Add to status history
    $stmt = $db->prepare("
        INSERT INTO order_status_history (order_id, status, changed_by, notes, created_at)
        VALUES (?, 'cancelled', ?, ?, NOW())
    ");
    $stmt->execute([$order_id, $_SESSION['user_id'], $reason]);

    // Process refund if payment was completed
    if ($order['payment_status'] == 'completed') {
        // Add refund logic here if needed
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?");
        $stmt->execute([$order_id]);
    }

    // Create notification for customer
    if ($order['user_id']) {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, 'Order Cancelled', ?, 'error', NOW())
        ");
        $message = "Your order #{$order['order_number']} has been cancelled. Reason: {$reason}";
        $stmt->execute([$order['user_id'], $message]);
    }

    // Log activity
    logUserActivity($_SESSION['user_id'], 'order_cancelled', 
        "Cancelled order #{$order['order_number']}. Reason: {$reason}");

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}