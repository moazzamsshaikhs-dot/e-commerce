<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$order_id = intval($data['order_id'] ?? 0);
$reason = trim($data['reason'] ?? '');

if (!$order_id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Order ID and reason are required']);
    exit();
}

try {
    // Start transaction
    $db->beginTransaction();
    
    // Check if order belongs to user and is cancellable
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Order cannot be cancelled or not found');
    }
    
    // Update order status
    $stmt = $db->prepare("UPDATE orders SET status = 'cancelled', cancelled_date = NOW() WHERE id = ?");
    $stmt->execute([$order_id]);
    
    // Add order status history
    $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'cancelled', ?, ?)");
    $stmt->execute([$order_id, $user_id, "Cancelled by customer. Reason: $reason"]);
    
    // Add order note
    $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) VALUES (?, ?, 'customer', ?)");
    $stmt->execute([$order_id, $user_id, "Order cancelled by customer. Reason: $reason"]);
    
    // Restore product stock
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        if ($item['product_id']) {
            $stmt = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    logUserActivity($user_id, 'order_cancelled', 'Cancelled order #' . $order['order_number']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Order cancelled successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Cancel Order Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}