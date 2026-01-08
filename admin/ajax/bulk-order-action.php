<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_ids']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_ids = $input['order_ids'];
$action = $input['action'];

try {
    $db = getDB();
    
    if (empty($order_ids)) {
        echo json_encode(['success' => false, 'message' => 'No orders selected']);
        exit;
    }
    
    // Prepare placeholders for SQL
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    
    switch ($action) {
        case 'processing':
        case 'shipped':
        case 'delivered':
            // Update status
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$action], $order_ids));
            
            // Add to history
            foreach ($order_ids as $order_id) {
                $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                                      VALUES (?, ?, ?, 'Bulk update')");
                $stmt->execute([$order_id, $action, $_SESSION['user_id']]);
            }
            
            $message = count($order_ids) . ' order(s) marked as ' . $action;
            break;
            
        case 'cancelled':
            // Cancel orders
            $stmt = $db->prepare("UPDATE orders SET status = 'cancelled', cancelled_date = NOW() 
                                  WHERE id IN ($placeholders)");
            $stmt->execute($order_ids);
            
            foreach ($order_ids as $order_id) {
                $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                                      VALUES (?, 'cancelled', ?, 'Bulk cancellation')");
                $stmt->execute([$order_id, $_SESSION['user_id']]);
            }
            
            $message = count($order_ids) . ' order(s) cancelled';
            break;
            
        case 'delete':
            // Delete orders (soft delete or permanent based on your logic)
            // Note: Be careful with deletions
            $stmt = $db->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
            $stmt->execute($order_ids);
            
            $message = count($order_ids) . ' order(s) deleted';
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>