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
    
    if (empty($data['invoice_id'])) {
        throw new Exception('Invoice ID is required.');
    }
    
    $invoice_id = (int)$data['invoice_id'];
    
    // Check if invoice exists
    $stmt = $db->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        throw new Exception('Invoice not found.');
    }
    
    // Delete invoice (cascade will delete items and payments)
    $stmt = $db->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice deleted successfully!',
        'invoice_number' => $invoice['invoice_number']
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}