<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

if ($quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
    exit();
}

try {
    // Check stock availability
    $stmt = $db->prepare("SELECT stock FROM products WHERE id = ? AND approved_status = 'approved'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    if ($quantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Cannot add more than available stock. Only ' . $product['stock'] . ' available']);
        exit();
    }
    
    // Update cart item
    $stmt = $db->prepare("UPDATE cart_items SET quantity = ?, added_at = NOW() WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$quantity, $user_id, $product_id]);
    
    // Check if any rows were affected
    if ($stmt->rowCount() == 0) {
        // Item not in cart, add it
        $stmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $product_id, $quantity]);
    }
    
    // Get updated cart count
    $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = $stmt->fetch()['total'] ?? 0;
    
    // Log activity
    logUserActivity($user_id, 'cart_update', 'Updated cart quantity for product: ' . $product_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart updated successfully',
        'cart_count' => (int)$cart_count
    ]);
    
} catch (PDOException $e) {
    error_log("Update Cart Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>