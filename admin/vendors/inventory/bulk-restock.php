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

$product_ids = json_decode($_POST['product_ids'] ?? '[]', true);
$stock_quantity = (int)($_POST['stock_quantity'] ?? 0);

if (empty($product_ids) || $stock_quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Verify all products belong to vendor
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE id IN ($placeholders) AND vendor_id = ?");
    $stmt->execute(array_merge($product_ids, [$vendor_id]));
    $result = $stmt->fetch();
    
    if ($result['count'] != count($product_ids)) {
        echo json_encode(['success' => false, 'message' => 'Some products not found or not owned by you']);
        exit;
    }
    
    // Update all products
    $stmt = $db->prepare("UPDATE products SET stock = ?, updated_at = NOW() WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$stock_quantity], $product_ids));
    
    // Log the bulk restock
    logUserActivity($vendor_id, 'bulk_restock', 
        "Bulk restocked " . count($product_ids) . " products to {$stock_quantity} units each");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Successfully restocked ' . count($product_ids) . ' products to ' . $stock_quantity . ' units each'
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>