<?php
require_once 'includes/config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to add items to wishlist.',
        'type' => 'warning'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

// Validation
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit();
}

try {
    $db = getDB();
    
    // Check if product exists
    $stmt = $db->prepare("SELECT name FROM products WHERE id = ? AND approved_status = 'approved'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit();
    }
    
    // Check if already in wishlist
    $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Product is already in your wishlist.',
            'type' => 'warning'
        ]);
        exit();
    }
    
    // Add to wishlist
    $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $product_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Product added to wishlist successfully!',
        'type' => 'success'
    ]);
    
} catch(PDOException $e) {
    error_log("Add to wishlist error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error adding product to wishlist. Please try again.']);
}
?>