<?php
// admin/ajax/delete-invoice.php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$invoice_id = $data['invoice_id'] ?? 0;

if (empty($invoice_id)) {
    echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
    exit;
}

try {
    $db = getDB();
    
    // Get invoice number for log
    $stmt = $db->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }
    
    // Delete invoice (cascade will delete invoice_items)
    $stmt = $db->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    
    // Record activity
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description) 
        VALUES (?, 'invoice_deleted', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Deleted invoice #{$invoice['invoice_number']}"
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice deleted successfully'
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}