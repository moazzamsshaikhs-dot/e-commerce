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
    
    $db->beginTransaction();
    
    // Calculate totals
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    
    $tax_rate = floatval($data['tax_rate'] ?? 10);
    $tax_amount = ($subtotal * $tax_rate) / 100;
    $total_amount = $subtotal + $tax_amount;
    
    // Update invoice
    $stmt = $db->prepare("
        UPDATE invoices SET
            user_id = ?,
            invoice_date = ?,
            due_date = ?,
            subtotal = ?,
            tax_rate = ?,
            tax_amount = ?,
            total_amount = ?,
            payment_status = ?,
            status = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['user_id'],
        $data['invoice_date'],
        $data['due_date'],
        $subtotal,
        $tax_rate,
        $tax_amount,
        $total_amount,
        $data['payment_status'] ?? 'unpaid',
        $data['status'] ?? 'draft',
        $data['notes'] ?? '',
        $data['invoice_id']
    ]);
    
    // Delete old items
    $stmt = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$data['invoice_id']]);
    
    // Insert new items
    $stmt = $db->prepare("
        INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, subtotal, product_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($data['items'] as $item) {
        $stmt->execute([
            $data['invoice_id'],
            $item['description'],
            $item['quantity'],
            $item['unit_price'],
            $item['quantity'] * $item['unit_price'],
            $item['product_id'] ?? null
        ]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice updated successfully!'
    ]);
    
} catch(Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}