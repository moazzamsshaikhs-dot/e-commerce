<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid input data.');
    }
    
    // Validate required fields
    if (empty($data['user_id']) || empty($data['invoice_date']) || empty($data['due_date'])) {
        throw new Exception('Required fields are missing.');
    }
    
    if (empty($data['items']) || !is_array($data['items'])) {
        throw new Exception('Invoice must have at least one item.');
    }
    
    $db->beginTransaction();
    
    // Calculate totals
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        if (empty($item['description']) || $item['quantity'] <= 0 || $item['unit_price'] <= 0) {
            throw new Exception('Invalid item data.');
        }
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    
    $tax_rate = floatval($data['tax_rate'] ?? 10);
    $tax_amount = ($subtotal * $tax_rate) / 100;
    $total_amount = $subtotal + $tax_amount;
    
    // Generate invoice number
    $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check for duplicate invoice number
    $stmt = $db->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
    $stmt->execute([$invoice_number]);
    if ($stmt->fetch()) {
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Insert invoice
    $stmt = $db->prepare("
        INSERT INTO invoices (
            invoice_number, user_id, invoice_date, due_date,
            subtotal, tax_rate, tax_amount, total_amount,
            amount_paid, balance_due, payment_status, status,
            notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $payment_status = $data['payment_status'] ?? 'unpaid';
    $amount_paid = $payment_status == 'paid' ? $total_amount : 0;
    $balance_due = $total_amount - $amount_paid;
    
    $stmt->execute([
        $invoice_number,
        $data['user_id'],
        $data['invoice_date'],
        $data['due_date'],
        $subtotal,
        $tax_rate,
        $tax_amount,
        $total_amount,
        $amount_paid,
        $balance_due,
        $payment_status,
        $data['status'] ?? 'draft',
        $data['notes'] ?? ''
    ]);
    
    $invoice_id = $db->lastInsertId();
    
    // Insert items
    $stmt = $db->prepare("
        INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, subtotal, product_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($data['items'] as $item) {
        $stmt->execute([
            $invoice_id,
            $item['description'],
            $item['quantity'],
            $item['unit_price'],
            $item['quantity'] * $item['unit_price'],
            $item['product_id'] ?? null
        ]);
    }
    
    // If paid, record payment
    if ($payment_status == 'paid') {
        $stmt = $db->prepare("
            INSERT INTO invoice_payments (invoice_id, user_id, amount, payment_method, payment_date, status)
            VALUES (?, ?, ?, 'manual', CURDATE(), 'completed')
        ");
        $stmt->execute([$invoice_id, $data['user_id'], $total_amount]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice saved successfully!',
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number
    ]);
    
} catch(PDOException $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}