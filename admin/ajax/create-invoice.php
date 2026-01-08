<?php
// admin/ajax/create-invoice.php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_POST['user_id'] ?? '';
$invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
$due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
$status = $_POST['status'] ?? 'pending';
$notes = $_POST['notes'] ?? '';
$items = $_POST['items'] ?? [];

if (empty($user_id) || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Customer and items are required']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Generate invoice number
    $invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    // Calculate totals
    $subtotal = 0;
    $tax_rate = 10; // Default tax rate
    $tax_amount = 0;
    
    foreach ($items as $item) {
        $quantity = floatval($item['quantity'] ?? 1);
        $unit_price = floatval($item['unit_price'] ?? 0);
        $item_subtotal = $quantity * $unit_price;
        $subtotal += $item_subtotal;
    }
    
    $tax_amount = $subtotal * ($tax_rate / 100);
    $total_amount = $subtotal + $tax_amount;
    $balance_due = $total_amount;
    
    // Insert invoice
    $stmt = $db->prepare("
        INSERT INTO invoices (invoice_number, user_id, subtotal, tax_rate, tax_amount, total_amount, balance_due, status, due_date, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $invoice_number,
        $user_id,
        $subtotal,
        $tax_rate,
        $tax_amount,
        $total_amount,
        $balance_due,
        $status,
        $due_date,
        $notes
    ]);
    
    $invoice_id = $db->lastInsertId();
    
    // Insert invoice items
    $stmt = $db->prepare("
        INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, subtotal) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($items as $item) {
        $quantity = floatval($item['quantity'] ?? 1);
        $unit_price = floatval($item['unit_price'] ?? 0);
        $item_subtotal = $quantity * $unit_price;
        $description = $item['description'] ?? 'Item';
        
        $stmt->execute([
            $invoice_id,
            $description,
            $quantity,
            $unit_price,
            $item_subtotal
        ]);
    }
    
    // Record activity
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description) 
        VALUES (?, 'invoice_created', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Created invoice #$invoice_number for customer ID: $user_id"
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number
    ]);
    
} catch(Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}