<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$product_id = $_POST['product_id'] ?? 0;
$new_stock = $_POST['new_stock'] ?? 0;
$reason = $_POST['reason'] ?? '';

if (!$product_id || !is_numeric($new_stock) || $new_stock < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Verify product belongs to vendor
    $stmt = $db->prepare("SELECT id, name, stock FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Update stock
    $stmt = $db->prepare("UPDATE products SET stock = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_stock, $product_id]);
    
    // Log the stock update
    $old_stock = $product['stock'];
    $change = $new_stock - $old_stock;
    
    logUserActivity($vendor_id, 'stock_update', 
        "Updated stock for {$product['name']}: {$old_stock} → {$new_stock} (Change: {$change}) - Reason: {$reason}");
    
    echo json_encode(['success' => true, 'message' => 'Stock updated successfully']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>