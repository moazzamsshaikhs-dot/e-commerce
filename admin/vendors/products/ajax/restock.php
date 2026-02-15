<?php
// admin/vendors/products/ajax/restock.php
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity']);
    exit();
}

try {
    $db = getDB();
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get current stock
    $stmt = $db->prepare("SELECT stock FROM products WHERE id = ? AND vendor_id = ? FOR UPDATE");
    $stmt->execute([$product_id, $vendor_id]);
    $current = $stmt->fetch();
    
    if (!$current) {
        throw new Exception('Product not found');
    }
    
    // Update stock
    $stmt = $db->prepare("
        UPDATE products 
        SET stock = stock + ?, 
            low_stock = CASE 
                WHEN (stock + ?) > 0 AND (stock + ?) < 10 THEN 1 
                ELSE 0 
            END,
            out_of_stock = CASE 
                WHEN (stock + ?) = 0 THEN 1 
                ELSE 0 
            END,
            updated_at = NOW() 
        WHERE id = ? AND vendor_id = ?
    ");
    $stmt->execute([$quantity, $quantity, $quantity, $quantity, $product_id, $vendor_id]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully added $quantity units",
        'new_stock' => $current['stock'] + $quantity
    ]);
    
} catch(Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>