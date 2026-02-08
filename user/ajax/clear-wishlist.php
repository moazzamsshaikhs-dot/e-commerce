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
    // Clear all wishlist items
    $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Log activity
    logUserActivity($user_id, 'wishlist_clear', 'Cleared entire wishlist');
    
    echo json_encode([
        'success' => true,
        'message' => 'Wishlist cleared successfully',
        'count' => 0
    ]);
    
} catch (PDOException $e) {
    error_log("Clear Wishlist Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>