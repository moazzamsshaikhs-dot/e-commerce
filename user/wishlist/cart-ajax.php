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
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$product_id = intval($data['product_id'] ?? 0);
$quantity = intval($data['quantity'] ?? 1);

try {
    switch ($action) {
        case 'add':
            if (!$product_id) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                exit();
            }
            
            // Check product stock
            $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }
            
            if ($product['stock'] < $quantity) {
                echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                exit();
            }
            
            // Check if already in cart
            $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $cart_item = $stmt->fetch();
            
            if ($cart_item) {
                // Update quantity
                $new_quantity = $cart_item['quantity'] + $quantity;
                $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $cart_item['id']]);
            } else {
                // Add new item
                $stmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $product_id, $quantity]);
            }
            
            // Get cart count
            $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $cart_count = $stmt->fetch()['total'] ?? 0;
            
            logUserActivity($user_id, 'cart_add', 'Added product to cart: ' . $product_id);
            echo json_encode(['success' => true, 'message' => 'Added to cart', 'cart_count' => $cart_count]);
            break;
            
        case 'update':
            if (!$product_id) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                exit();
            }
            
            // Check product stock
            $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }
            
            if ($product['stock'] < $quantity) {
                echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                exit();
            }
            
            // Update quantity
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $user_id, $product_id]);
            
            // Get cart count
            $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $cart_count = $stmt->fetch()['total'] ?? 0;
            
            logUserActivity($user_id, 'cart_update', 'Updated cart quantity: ' . $product_id . ' to ' . $quantity);
            echo json_encode(['success' => true, 'message' => 'Quantity updated', 'cart_count' => $cart_count]);
            break;
            
        case 'remove':
            if (!$product_id) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                exit();
            }
            
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            
            // Get cart count
            $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $cart_count = $stmt->fetch()['total'] ?? 0;
            
            logUserActivity($user_id, 'cart_remove', 'Removed product from cart: ' . $product_id);
            echo json_encode(['success' => true, 'message' => 'Removed from cart', 'cart_count' => $cart_count]);
            break;
            
        case 'clear':
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            logUserActivity($user_id, 'cart_clear', 'Cleared entire cart');
            echo json_encode(['success' => true, 'message' => 'Cart cleared', 'cart_count' => 0]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Cart AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}