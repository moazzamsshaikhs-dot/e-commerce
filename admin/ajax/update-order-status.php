<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['id']) || !isset($_GET['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_id = (int)$_GET['id'];
$new_status = $_GET['status'];

try {
    $db = getDB();
    
    // Get current order status
    $stmt = $db->prepare("SELECT status, user_id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Update order status
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // Record status change in history
    $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                          VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $order_id,
        $new_status,
        $_SESSION['user_id'],
        "Status changed from " . $order['status'] . " to " . $new_status
    ]);
    
    // Send notification to customer
    $notification_message = "Your order status has been updated to " . ucfirst($new_status);
    
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                          VALUES (?, 'Order Status Updated', ?, 'info')");
    $stmt->execute([$order['user_id'], $notification_message]);
    
    // If marked as delivered, update delivery date
    if ($new_status === 'delivered') {
        $stmt = $db->prepare("UPDATE orders SET delivered_date = NOW() WHERE id = ?");
        $stmt->execute([$order_id]);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Order status updated successfully',
        'new_status' => $new_status
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>