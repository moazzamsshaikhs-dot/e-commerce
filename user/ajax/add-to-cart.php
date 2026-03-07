<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

// Enable error logging
error_log("Add to cart - Starting request");

if (!isLoggedIn()) {
    error_log("Add to cart - User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Add to cart - Invalid method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get POST data
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

error_log("Add to cart - Product ID: $product_id, Quantity: $quantity, User ID: " . $_SESSION['user_id']);

if ($product_id <= 0) {
    error_log("Add to cart - Invalid product ID: $product_id");
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

if ($quantity <= 0) {
    error_log("Add to cart - Invalid quantity: $quantity");
    echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
    exit();
}

try {
    $db = getDB();
    
    // Check if product exists and is approved with stock
    $stmt = $db->prepare("SELECT id, name, stock, approved_status FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Add to cart - Product query result: " . print_r($product, true));
    
    if (!$product) {
        error_log("Add to cart - Product not found: $product_id");
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    if ($product['approved_status'] !== 'approved') {
        error_log("Add to cart - Product not approved: " . $product['approved_status']);
        echo json_encode(['success' => false, 'message' => 'Product is not available for purchase']);
        exit();
    }
    
    if ($product['stock'] < $quantity) {
        error_log("Add to cart - Insufficient stock. Available: " . $product['stock'] . ", Requested: " . $quantity);
        echo json_encode(['success' => false, 'message' => 'Insufficient stock. Only ' . $product['stock'] . ' available']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Check if product already in cart
    $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Add to cart - Cart check result: " . print_r($cart_item, true));
    
    if ($cart_item) {
        // Update existing cart item
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        // Check stock again for new quantity
        if ($new_quantity > $product['stock']) {
            error_log("Add to cart - New quantity exceeds stock: $new_quantity > " . $product['stock']);
            echo json_encode(['success' => false, 'message' => 'Cannot add more than available stock. Only ' . $product['stock'] . ' available']);
            exit();
        }
        
        $stmt = $db->prepare("UPDATE cart_items SET quantity = ?, added_at = NOW() WHERE user_id = ? AND product_id = ?");
        $result = $stmt->execute([$new_quantity, $user_id, $product_id]);
        
        error_log("Add to cart - Update result: " . ($result ? 'success' : 'failed') . ", Rows affected: " . $stmt->rowCount());
    } else {
        // Insert new cart item
        $stmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
        $result = $stmt->execute([$user_id, $product_id, $quantity]);
        
        error_log("Add to cart - Insert result: " . ($result ? 'success' : 'failed') . ", Rows affected: " . $stmt->rowCount());
    }
    
    // Get updated cart count
    $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    error_log("Add to cart - New cart count: $cart_count");
    
    // Log activity
    logUserActivity($user_id, 'cart_add', 'Added product to cart: ' . $product['name']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully',
        'cart_count' => (int)$cart_count
    ]);
    
} catch (PDOException $e) {
    error_log("Add to Cart Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Add to Cart General Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>