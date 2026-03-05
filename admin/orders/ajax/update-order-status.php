<?php
// admin/ajax/update-order-status.php
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
$status = isset($input['status']) ? $input['status'] : '';

if (!$order_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
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

    // Update order status
    $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $order_id]);

    // Add to status history
    $stmt = $db->prepare("
        INSERT INTO order_status_history (order_id, status, changed_by, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$order_id, $status, $_SESSION['user_id']]);

    // Create notification for customer
    if ($order['user_id']) {
        $status_messages = [
            'processing' => 'Your order #' . $order['order_number'] . ' is now being processed.',
            'shipped' => 'Your order #' . $order['order_number'] . ' has been shipped!',
            'delivered' => 'Your order #' . $order['order_number'] . ' has been delivered. Thank you!',
            'cancelled' => 'Your order #' . $order['order_number'] . ' has been cancelled.'
        ];

        if (isset($status_messages[$status])) {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, 'Order Status Updated', ?, 'info', NOW())
            ");
            $stmt->execute([$order['user_id'], $status_messages[$status]]);
        }
    }

    // Log activity
    logUserActivity($_SESSION['user_id'], 'order_status_updated', 
        "Updated order #{$order['order_number']} status to {$status}");

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully',
        'status' => $status
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}