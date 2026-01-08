<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

header('Content-Type: application/json');

try {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['invoice_id']) || empty($data['amount'])) {
        throw new Exception('Invoice ID and amount are required.');
    }
    
    $db->beginTransaction();
    
    // Record payment
    $stmt = $db->prepare("
        INSERT INTO invoice_payments (invoice_id, user_id, amount, payment_method, payment_date, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, 'completed')
    ");
    
    $stmt->execute([
        $data['invoice_id'],
        $data['user_id'] ?? $_SESSION['user_id'],
        $data['amount'],
        $data['method'] ?? 'manual',
        $data['date'] ?? date('Y-m-d'),
        $data['notes'] ?? ''
    ]);
    
    // Update invoice payment status
    $stmt = $db->prepare("
        UPDATE invoices 
        SET amount_paid = amount_paid + ?,
            balance_due = total_amount - (amount_paid + ?),
            payment_status = CASE 
                WHEN total_amount <= (amount_paid + ?) THEN 'paid'
                ELSE 'partial'
            END,
            paid_at = CASE 
                WHEN total_amount <= (amount_paid + ?) THEN NOW()
                ELSE paid_at
            END
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['amount'], $data['amount'], $data['amount'], $data['amount'],
        $data['invoice_id']
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded successfully!'
    ]);
    
} catch(Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}