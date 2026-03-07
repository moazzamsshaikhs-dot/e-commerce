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
$action = isset($_POST['action']) ? $_POST['action'] : 'toggle';

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

try {
    if ($action === 'add') {
        // Check if already in wishlist
        $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Already in wishlist', 'action' => 'already_added']);
            exit();
        }
        
        // Add to wishlist
        $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id, added_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $product_id]);
        
        // Get wishlist count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        logUserActivity($user_id, 'wishlist_add', 'Added product to wishlist ID: ' . $product_id);
        echo json_encode(['success' => true, 'action' => 'added', 'count' => $count]);
        
    } elseif ($action === 'remove') {
        // Remove from wishlist
        $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        // Get wishlist count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        logUserActivity($user_id, 'wishlist_remove', 'Removed product from wishlist ID: ' . $product_id);
        echo json_encode(['success' => true, 'action' => 'removed', 'count' => $count]);
        
    } else {
        // Toggle (default)
        $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        if ($stmt->fetch()) {
            // Remove
            $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $in_wishlist = false;
            $message = 'Removed from wishlist';
            $action = 'removed';
        } else {
            // Add
            $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id, added_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $product_id]);
            $in_wishlist = true;
            $message = 'Added to wishlist';
            $action = 'added';
        }
        
        // Get wishlist count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        logUserActivity($user_id, 'wishlist_toggle', 'Toggled wishlist for product ID: ' . $product_id);
        echo json_encode([
            'success' => true, 
            'in_wishlist' => $in_wishlist, 
            'message' => $message,
            'action' => $action,
            'count' => $count
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Wishlist Toggle Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>