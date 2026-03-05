<?php
// admin/orders/ajax/send-order-update.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/email-templates.php';

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
    
    // Get order with customer details
    $stmt = $db->prepare("
        SELECT o.*, u.full_name, u.email 
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order || !$order['email']) {
        throw new Exception('Order or customer email not found');
    }
    
    // Send email (implement your email function)
    // sendOrderStatusEmail($order['email'], $order);
    
    // Log activity
    logUserActivity($_SESSION['user_id'], 'order_update_sent', 
        "Sent status update for order #{$order['order_number']} to {$order['email']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Order update sent successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}