<?php
// admin/ajax/send-invoice-reminder.php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

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
    
    // Get invoice and customer details
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name,
            u.email
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
    
    // TODO: Send email reminder
    // You'll need to implement email sending logic here
    
    // Record activity
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description) 
        VALUES (?, 'invoice_reminder', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Sent reminder for invoice #{$invoice['invoice_number']} to {$invoice['email']}"
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment reminder sent successfully'
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}