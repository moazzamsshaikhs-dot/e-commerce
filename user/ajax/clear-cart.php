<?php
session_start();
require_once '../../includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isLoggedIn()) {
        // Guest user - clear session cart
        $_SESSION['guest_cart'] = [];
        
        $response['success'] = true;
        $response['message'] = 'Cart cleared';
    } else {
        // Logged in user - clear database cart
        $db = getDB();
        $user_id = $_SESSION['user_id'];
        
        $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $response['success'] = true;
        $response['message'] = 'Cart cleared successfully';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Clear cart error: " . $e->getMessage());
}

echo json_encode($response);
