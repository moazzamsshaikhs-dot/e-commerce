<?php
session_start();
require_once '../../includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isLoggedIn()) {
        // Guest user - remove from session cart
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        
        if ($product_id <= 0) {
            $response['message'] = 'Invalid product ID';
            echo json_encode($response);
            exit;
        }
        
        if (isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = array_filter($_SESSION['guest_cart'], function($item) use ($product_id) {
                return $item['product_id'] != $product_id;
            });
            // Re-index array
            $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']);
        }
        
        $response['success'] = true;
        $response['message'] = 'Item removed from cart';
    } else {
        // Logged in user - remove from database
        $db = getDB();
        $user_id = $_SESSION['user_id'];
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        
        if ($product_id <= 0) {
            $response['message'] = 'Invalid product ID';
            echo json_encode($response);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        $response['success'] = true;
        $response['message'] = 'Item removed from cart';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Remove from cart error: " . $e->getMessage());
}

echo json_encode($response);
