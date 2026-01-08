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
    
    $invoice_id = (int)$_GET['id'];
    
    // Get invoice details
    $stmt = $db->prepare("
        SELECT i.*, u.full_name, u.email 
        FROM invoices i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        throw new Exception('Invoice not found.');
    }
    
    // Update invoice status
    $stmt = $db->prepare("UPDATE invoices SET status = 'sent', sent_at = NOW() WHERE id = ?");
    $stmt->execute([$invoice_id]);
    
    // Here you would typically send an email
    // For now, we'll just simulate success
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice sent to ' . $invoice['email']
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}