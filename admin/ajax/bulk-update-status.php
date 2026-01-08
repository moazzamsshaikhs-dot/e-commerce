<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$payment_ids = $_POST['payment_ids'] ?? [];
$new_status = $_POST['new_status'] ?? '';
$notes = $_POST['notes'] ?? '';
$notify_customers = isset($_POST['notify_customers']);

if (empty($payment_ids) || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    $updated_count = 0;
    $failed_count = 0;
    
    foreach ($payment_ids as $payment_id) {
        $payment_id = (int)$payment_id;
        
        // Get current payment details
        $stmt = $db->prepare("SELECT p.*, o.id as order_id 
                              FROM payments p 
                              LEFT JOIN orders o ON p.order_id = o.id 
                              WHERE p.id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            $failed_count++;
            continue;
        }
        
        // Update payment status
        $stmt = $db->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $payment_id]);
        
        // Update order payment status if linked
        if ($payment['order_id']) {
            $order_payment_status = ($new_status === 'completed') ? 'completed' : 'pending';
            $stmt = $db->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
            $stmt->execute([$order_payment_status, $payment['order_id']]);
        }
        
        // Add payment audit trail
        $stmt = $db->prepare("INSERT INTO payment_audit (payment_id, action, old_status, new_status, performed_by, notes) 
                              VALUES (?, 'status_update', ?, ?, ?, ?)");
        $audit_notes = "Bulk update" . ($notes ? ": " . $notes : "");
        $stmt->execute([$payment_id, $payment['status'], $new_status, $_SESSION['user_id'], $audit_notes]);
        
        // Send notification to customer
        if ($notify_customers && $payment['user_id']) {
            $message = "Your payment status has been updated to " . ucfirst($new_status);
            if ($payment['transaction_id']) {
                $message .= " for transaction " . $payment['transaction_id'];
            }
            
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                                  VALUES (?, 'Payment Status Updated', ?, 'info')");
            $stmt->execute([$payment['user_id'], $message]);
        }
        
        $updated_count++;
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Updated {$updated_count} payment(s) to {$new_status} status",
        'updated_count' => $updated_count,
        'failed_count' => $failed_count
    ]);
    
} catch(PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}