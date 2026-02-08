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

try {
    // Clear all cart items
    $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Log activity
    logUserActivity($user_id, 'cart_clear', 'Cleared entire cart');
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart cleared successfully',
        'cart_count' => 0
    ]);
    
} catch (PDOException $e) {
    error_log("Clear Cart Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>