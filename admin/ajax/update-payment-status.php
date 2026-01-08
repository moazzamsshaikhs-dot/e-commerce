<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['payment_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$payment_id = (int)$data['payment_id'];
$new_status = $data['status'];

try {
    $db = getDB();
    
    // Get current payment details
    $stmt = $db->prepare("SELECT p.*, o.id as order_id, o.payment_status as order_payment_status 
                          FROM payments p 
                          LEFT JOIN orders o ON p.order_id = o.id 
                          WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Update payment status
    $stmt = $db->prepare("UPDATE payments SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $payment_id]);
    
    // Update order payment status if linked
    if ($payment['order_id']) {
        $order_payment_status = ($new_status === 'completed') ? 'completed' : 'pending';
        $stmt = $db->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $stmt->execute([$order_payment_status, $payment['order_id']]);
        
        // Add order status history
        $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                              VALUES (?, 'processing', ?, ?)");
        $note = "Payment marked as " . $new_status . " by admin";
        $stmt->execute([$payment['order_id'], $_SESSION['user_id'], $note]);
    }
    
    // Add payment audit trail
    $stmt = $db->prepare("INSERT INTO payment_audit (payment_id, action, old_status, new_status, performed_by, notes) 
                          VALUES (?, 'status_update', ?, ?, ?, 'Status updated by admin')");
    $stmt->execute([$payment_id, $payment['status'], $new_status, $_SESSION['user_id']]);
    
    // Send notification to customer
    if ($payment['user_id']) {
        $message = "Your payment status has been updated to " . ucfirst($new_status);
        if ($payment['transaction_id']) {
            $message .= " for transaction " . $payment['transaction_id'];
        }
        
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                              VALUES (?, 'Payment Status Updated', ?, 'info')");
        $stmt->execute([$payment['user_id'], $message]);
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Payment status updated successfully',
        'new_status' => $new_status
    ]);
    
} catch(PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>