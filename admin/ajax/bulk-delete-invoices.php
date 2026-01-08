<?php
// admin/ajax/bulk-delete-invoices.php
// session_start();
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
    
    $placeholders = str_repeat('?,', count($invoice_ids) - 1) . '?';
    
    // Get invoice numbers for log
    $stmt = $db->prepare("
        SELECT GROUP_CONCAT(invoice_number) as invoice_numbers 
        FROM invoices 
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($invoice_ids);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $invoice_numbers = $result['invoice_numbers'] ?? '';
    
    // Delete invoices
    $stmt = $db->prepare("DELETE FROM invoices WHERE id IN ($placeholders)");
    $stmt->execute($invoice_ids);
    
    $deleted = $stmt->rowCount();
    
    // Record activity
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description) 
        VALUES (?, 'bulk_invoice_deleted', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Deleted $deleted invoices: $invoice_numbers"
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "$deleted invoices deleted successfully"
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}