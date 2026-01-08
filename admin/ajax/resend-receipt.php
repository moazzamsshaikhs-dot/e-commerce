<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['payment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit;
}

$payment_id = (int)$data['payment_id'];

try {
    $db = getDB();
    
    // Get payment details
    $stmt = $db->prepare("SELECT p.*, u.full_name, u.email 
                          FROM payments p 
                          LEFT JOIN users u ON p.user_id = u.id 
                          WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
    
    // Add payment audit trail
    $stmt = $db->prepare("INSERT INTO payment_audit (payment_id, action, performed_by, notes) 
                          VALUES (?, 'receipt_sent', ?, 'Receipt resent by admin')");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    
    // Send notification to customer
    if ($payment['user_id']) {
        $message = "A payment receipt has been sent to your email.";
        if ($payment['transaction_id']) {
            $message .= " Transaction ID: " . $payment['transaction_id'];
        }
        
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                              VALUES (?, 'Receipt Sent', ?, 'success')");
        $stmt->execute([$payment['user_id'], $message]);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Receipt sent to customer email successfully'
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}