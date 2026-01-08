<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

$order_id = (int)$_POST['id'];
$reason = $_POST['reason'] ?? 'Cancelled by admin';

try {
    $db = getDB();
    
    // Get order details
    $stmt = $db->prepare("SELECT status, user_id, total_amount FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    if ($order['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Order is already cancelled']);
        exit;
    }
    
    // Update order status
    $stmt = $db->prepare("UPDATE orders SET status = 'cancelled', cancelled_date = NOW() WHERE id = ?");
    $stmt->execute([$order_id]);
    
    // Update payment status if paid
    $stmt = $db->prepare("UPDATE payments SET status = 'failed' WHERE order_id = ? AND status = 'pending'");
    $stmt->execute([$order_id]);
    
    // Record in history
    $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                          VALUES (?, 'cancelled', ?, ?)");
    $stmt->execute([
        $order_id,
        $_SESSION['user_id'],
        "Order cancelled. Reason: " . $reason
    ]);
    
    // Add internal note
    $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) 
                          VALUES (?, ?, 'internal', ?)");
    $stmt->execute([
        $order_id,
        $_SESSION['user_id'],
        "Order cancelled by admin. Reason: " . $reason
    ]);
    
    // Send notification to customer
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                          VALUES (?, 'Order Cancelled', ?, 'warning')");
    $stmt->execute([
        $order['user_id'],
        "Your order #" . $order_id . " has been cancelled. Reason: " . $reason
    ]);
    
    // Return stock if any
    $stmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    foreach ($order_items as $item) {
        $stmt = $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>