<?php
require_once 'admin/includes/config.php';

if($_SESSION['user_type'] !== 'vendor') {
    header('Location: index.php');
    exit();
}
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
        'message' => 'Please login to add items to cart.',
        'redirect' => 'login.php?redirect=' . urlencode($_SERVER['HTTP_REFERER'] ?? 'index.php'),
        'type' => 'warning'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Validation
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit();
}

if ($quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1.']);
    exit();
}

try {
    $db = getDB();
    
    // Check product stock
    $stmt = $db->prepare("SELECT stock, name FROM products WHERE id = ? AND approved_status = 'approved' AND stock > 0");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not available.']);
        exit();
    }
    
    if ($quantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Only ' . $product['stock'] . ' items available in stock.']);
        exit();
    }
    
    // Check if product already in cart
    $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing_item = $stmt->fetch();
    
    if ($existing_item) {
        // Update quantity
        $new_quantity = $existing_item['quantity'] + $quantity;
        if ($new_quantity <= $product['stock']) {
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $existing_item['id']]);
            $message = 'Product quantity updated in cart!';
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot add more than available stock.']);
            exit();
        }
    } else {
        // Add new item to cart
        $stmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $product_id, $quantity]);
        $message = 'Product added to cart successfully!';
    }
    
    // Get updated cart count
    $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = (int)$stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'cart_count' => $cart_count,
        'product_name' => $product['name']
    ]);
    
} catch(PDOException $e) {
    error_log("Add to cart error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error adding product to cart. Please try again.']);
}
?>