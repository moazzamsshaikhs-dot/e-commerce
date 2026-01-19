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
$action = $_POST['action'] ?? '';

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Verify product belongs to vendor
    $stmt = $db->prepare("SELECT id, name FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    if ($action === 'hide') {
        // You could add a status field to hide products, or set stock to -1
        // For now, we'll use a custom field or just update stock to 0 with a flag
        $stmt = $db->prepare("UPDATE products SET stock = 0, out_of_stock = 1 WHERE id = ?");
        $message = "Product hidden from store";
    } else {
        $stmt = $db->prepare("UPDATE products SET out_of_stock = 0 WHERE id = ?");
        $message = "Product made visible in store";
    }
    
    $stmt->execute([$product_id]);
    
    logUserActivity($vendor_id, 'product_status_toggle', 
        "{$action}d product: {$product['name']}");
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>