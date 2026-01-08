<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$customer_id = (int)$_POST['customer_id'];
$order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
$payment_method = $_POST['payment_method'];
$transaction_id = $_POST['transaction_id'] ?? null;
$amount = (float)$_POST['amount'];
$currency = $_POST['currency'];
$status = $_POST['status'];
$payment_date = $_POST['payment_date'];
$payment_details = $_POST['payment_details'] ?? '';
$send_receipt = isset($_POST['send_receipt']);
$add_internal_note = isset($_POST['add_internal_note']);

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // Generate transaction ID if not provided
    if (empty($transaction_id)) {
        $transaction_id = 'MANUAL-' . date('YmdHis') . '-' . strtoupper(uniqid());
    }
    
    // Create payment record
    $stmt = $db->prepare("INSERT INTO payments (user_id, order_id, payment_method, transaction_id, 
                                               amount, currency, status, payment_details, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $customer_id,
        $order_id,
        $payment_method,
        $transaction_id,
        $amount,
        $currency,
        $status,
        $payment_details,
        $payment_date
    ]);
    
    $payment_id = $db->lastInsertId();
    
    // Update order payment status if linked
    if ($order_id && $status === 'completed') {
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'completed' WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // Add order status history
        $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) 
                              VALUES (?, 'processing', ?, ?)");
        $note = "Manual payment received via " . $payment_method . " for amount $" . $amount;
        $stmt->execute([$order_id, $_SESSION['user_id'], $note]);
    }
    
    // Add internal note if requested
    if ($add_internal_note) {
        $note = "Manual payment recorded by admin. ";
        $note .= "Method: " . $payment_method . ", ";
        $note .= "Amount: " . $currency . " " . $amount . ", ";
        $note .= "Status: " . $status;
        
        if ($order_id) {
            $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) 
                                  VALUES (?, ?, 'internal', ?)");
            $stmt->execute([$order_id, $_SESSION['user_id'], $note]);
        }
    }
    
    // Send receipt to customer
    if ($send_receipt && $customer_id) {
        // Add notification
        $message = "A manual payment of " . $currency . " " . number_format($amount, 2) . " has been recorded. ";
        $message .= "Transaction ID: " . $transaction_id . ". Status: " . $status;
        
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                              VALUES (?, 'Manual Payment Recorded', ?, 'success')");
        $stmt->execute([$customer_id, $message]);
    }
    
    // Add payment audit trail
    $stmt = $db->prepare("INSERT INTO payment_audit (payment_id, action, performed_by, notes) 
                          VALUES (?, 'manual_payment', ?, 'Manual payment recorded by admin')");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Manual payment recorded successfully',
        'payment_id' => $payment_id,
        'transaction_id' => $transaction_id
    ]);
    
} catch(PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}