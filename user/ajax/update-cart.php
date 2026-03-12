<?php
session_start();
require_once '../../includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isLoggedIn()) {
        // Guest user - update session cart
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        if ($product_id <= 0 || $quantity <= 0) {
            $response['message'] = 'Invalid parameters';
            echo json_encode($response);
            exit;
        }
        
        if (!isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
        }
        
        // Update quantity in session
        foreach ($_SESSION['guest_cart'] as &$item) {
            if ($item['product_id'] == $product_id) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        
        $response['success'] = true;
        $response['message'] = 'Cart updated';
    } else {
        // Logged in user - update database
        $db = getDB();
        $user_id = $_SESSION['user_id'];
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        if ($product_id <= 0 || $quantity <= 0) {
            $response['message'] = 'Invalid parameters';
            echo json_encode($response);
            exit;
        }
        
        // Check stock availability
        $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $response['message'] = 'Product not found';
            echo json_encode($response);
            exit;
        }
        
        if ($quantity > $product['stock']) {
            $response['message'] = 'Requested quantity exceeds available stock';
            echo json_encode($response);
            exit;
        }
        
        // Update quantity in database
        $stmt = $db->prepare("
            UPDATE cart_items 
            SET quantity = ?, updated_at = NOW() 
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$quantity, $user_id, $product_id]);
        
        $response['success'] = true;
        $response['message'] = 'Cart updated successfully';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Update cart error: " . $e->getMessage());
}

echo json_encode($response);
