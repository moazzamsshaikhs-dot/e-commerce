<?php
// admin/ajax/get-invoice-details.php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$invoice_id = $_GET['id'] ?? 0;

if (empty($invoice_id)) {
    echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
    exit;
}

try {
    $db = getDB();
    
    // Get invoice details with customer info
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name,
            u.email,
            u.phone,
            u.address,
            u.country,
            u.city
        FROM invoices i
        JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }
    
    // Get invoice items
    $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payments for this invoice
    $stmt = $db->prepare("
        SELECT 
            p.*,
            u.full_name as processed_by_name
        FROM payments p
        LEFT JOIN users u ON p.processed_by = u.id
        WHERE p.invoice_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'invoice' => $invoice,
        'items' => $items,
        'payments' => $payments
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}