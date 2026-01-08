<?php
// admin/ajax/bulk-mark-paid.php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$invoice_ids = $data['invoice_ids'] ?? [];

if (empty($invoice_ids)) {
    echo json_encode(['success' => false, 'message' => 'No invoices selected']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    $placeholders = str_repeat('?,', count($invoice_ids) - 1) . '?';
    
    // Update invoices status
    $stmt = $db->prepare("
        UPDATE invoices 
        SET status = 'paid', 
            amount_paid = total_amount,
            balance_due = 0,
            updated_at = NOW()
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($invoice_ids);
    
    $affected = $stmt->rowCount();
    
    // Create payments for each invoice
    $stmt = $db->prepare("
        INSERT INTO payments (invoice_id, user_id, amount, payment_method, status, transaction_id)
        SELECT 
            i.id,
            i.user_id,
            i.total_amount,
            'manual',
            'completed',
            CONCAT('MAN-', UNIX_TIMESTAMP(), '-', i.id)
        FROM invoices i
        WHERE i.id IN ($placeholders)
    ");
    $stmt->execute($invoice_ids);
    
    // Record activity
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description) 
        VALUES (?, 'bulk_invoice_paid', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Marked $affected invoices as paid"
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "$affected invoices marked as paid successfully"
    ]);
    
} catch(Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}