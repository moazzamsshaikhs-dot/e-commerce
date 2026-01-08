<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$payment_id = (int)$_POST['payment_id'];
$refund_amount = (float)$_POST['refund_amount'];
$reason = $_POST['reason'] === 'custom' ? $_POST['custom_reason'] : $_POST['reason'];
$refund_type = $_POST['refund_type'];
$refund_method = $_POST['refund_method'];
$notes = $_POST['notes'] ?? '';
$notify_customer = isset($_POST['notify_customer']);
$update_order_status = isset($_POST['update_order_status']);

try {
    $db = getDB();
    
    // Get payment details
    $stmt = $db->prepare("SELECT p.*, u.full_name, u.email, o.id as order_id, o.order_number 
                          FROM payments p 
                          LEFT JOIN users u ON p.user_id = u.id 
                          LEFT JOIN orders o ON p.order_id = o.id 
                          WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
    
    // Check if payment is refundable
    if ($payment['status'] !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Only completed payments can be refunded']);
        exit;
    }
    
    // Calculate refundable amount
    $stmt = $db->prepare("SELECT SUM(refund_amount) as total_refunded 
                          FROM refunds 
                          WHERE payment_id = ? AND status = 'completed'");
    $stmt->execute([$payment_id]);
    $total_refunded = $stmt->fetch()['total_refunded'] ?? 0;
    
    $refundable_amount = $payment['amount'] - $total_refunded;
    
    if ($refund_amount > $refundable_amount) {
        echo json_encode(['success' => false, 'message' => 'Refund amount exceeds refundable amount']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Create refund record
    $stmt = $db->prepare("INSERT INTO refunds (payment_id, user_id, order_id, refund_amount, 
                                               reason, notes, status, processed_by) 
                          VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)");
    $stmt->execute([
        $payment_id,
        $payment['user_id'],
        $payment['order_id'],
        $refund_amount,
        $reason,
        $notes,
        $_SESSION['user_id']
    ]);
    
    $refund_id = $db->lastInsertId();
    
    // Update payment status if fully refunded
    if (($total_refunded + $refund_amount) >= $payment['amount']) {
        $stmt = $db->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?");
        $stmt->execute([$payment_id]);
    }
    
    // Update order status if requested and linked
    if ($update_order_status && $payment['order_id']) {
        $stmt = $db->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?");
        $stmt->execute([$payment['order_id']]);
        
        // Add order status history
        $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                              VALUES (?, 'refunded', ?, ?)");
        $history_note = "Refund processed for amount: $" . $refund_amount . ". Reason: " . $reason;
        $stmt->execute([$payment['order_id'], $_SESSION['user_id'], $history_note]);
    }
    
    // Add payment audit trail
    $stmt = $db->prepare("INSERT INTO payment_audit (payment_id, action, details, performed_by, notes) 
                          VALUES (?, 'refund', ?, ?, ?)");
    $details = json_encode([
        'refund_id' => $refund_id,
        'amount' => $refund_amount,
        'reason' => $reason,
        'type' => $refund_type,
        'method' => $refund_method
    ]);
    $stmt->execute([$payment_id, $details, $_SESSION['user_id'], 'Refund processed']);
    
    // Send notification to customer
    if ($notify_customer && $payment['user_id']) {
        $message = "A refund of $" . number_format($refund_amount, 2) . " has been processed ";
        $message .= "for your payment. Reason: " . $reason . ". ";
        $message .= "Refund method: " . $refund_method . ". ";
        $message .= "It may take 5-10 business days to appear in your account.";
        
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                              VALUES (?, 'Refund Processed', ?, 'success')");
        $stmt->execute([$payment['user_id'], $message]);
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Refund processed successfully',
        'refund_id' => $refund_id,
        'refund_amount' => $refund_amount
    ]);
    
} catch(PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
