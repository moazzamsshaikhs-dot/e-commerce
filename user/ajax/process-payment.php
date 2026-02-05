<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate data
$required_fields = ['order_id', 'payment_method', 'amount'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Process payment based on method
    $payment_method = $data['payment_method'];
    $transaction_id = generateTransactionId();
    
    // Insert payment record
    $stmt = $db->prepare("
        INSERT INTO payments (
            user_id, order_id, payment_method, transaction_id, amount, currency, status, payment_details
        ) VALUES (?, ?, ?, ?, ?, 'USD', 'completed', ?)
    ");
    
    $payment_details = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    
    $stmt->execute([
        $_SESSION['user_id'],
        $data['order_id'],
        $payment_method,
        $transaction_id,
        $data['amount'],
        $payment_details
    ]);
    
    // Update order payment status
    $stmt = $db->prepare("
        UPDATE orders 
        SET payment_status = 'completed', 
            status = 'processing',
            updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$data['order_id'], $_SESSION['user_id']]);
    
    // Generate invoice
    $invoice_number = 'INV-' . date('Ymd') . '-' . mt_rand(1000, 9999);
    
    // Get order details for invoice
    $stmt = $db->prepare("
        SELECT o.*, u.full_name, u.email, u.address
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$data['order_id']]);
    $order = $stmt->fetch();
    
    // Insert invoice
    $stmt = $db->prepare("
        INSERT INTO invoices (
            invoice_number, user_id, order_id, subtotal, tax_rate, tax_amount, 
            total_amount, amount_paid, balance_due, payment_status,
            invoice_date, due_date, status
        ) VALUES (?, ?, ?, ?, 10.00, ?, ?, ?, 0.00, 'paid',
                  CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'sent')
    ");
    $stmt->execute([
        $invoice_number,
        $_SESSION['user_id'],
        $data['order_id'],
        $data['amount'] / 1.1, // Assuming 10% tax
        $data['amount'] * 0.1,
        $data['amount'],
        $data['amount']
    ]);
    
    $invoice_id = $db->lastInsertId();
    
    // Log payment audit
    $stmt = $db->prepare("
        INSERT INTO payment_audit (
            payment_id, action, old_status, new_status, performed_by, notes
        ) VALUES (?, 'payment_processed', 'pending', 'completed', ?, 'Online payment processed')
    ");
    $stmt->execute([$db->lastInsertId(), $_SESSION['user_id']]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'transaction_id' => $transaction_id,
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function generateTransactionId() {
    return 'TXN' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
}