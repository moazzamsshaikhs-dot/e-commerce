<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

try {
    $db->beginTransaction();
    
    // Check if order can be cancelled
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order cannot be cancelled');
    }
    
    // Update order status
    $stmt = $db->prepare("
        UPDATE orders SET 
        status = 'cancelled', 
        cancelled_date = NOW(),
        updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);
    
    // Restore product stock
    $stmt = $db->prepare("
        SELECT product_id, quantity FROM order_items WHERE order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        $stmt = $db->prepare("
            UPDATE products SET stock = stock + ? WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Add status history
    $stmt = $db->prepare("
        INSERT INTO order_status_history (order_id, status, changed_by, notes)
        VALUES (?, 'cancelled', ?, 'Order cancelled by customer')
    ");
    $stmt->execute([$order_id, $user_id]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>