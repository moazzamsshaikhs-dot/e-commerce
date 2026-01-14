<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// GET request for count
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_count') {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->fetch()['count'];
        
        echo json_encode(['success' => true, 'count' => $count]);
        exit();
    }
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$product_id = intval($data['product_id'] ?? 0);

try {
    switch ($action) {
        case 'add':
            if (!$product_id) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                exit();
            }
            
            // Check if already in wishlist
            $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Already in wishlist']);
                exit();
            }
            
            // Add to wishlist
            $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $product_id]);
            
            // Get new count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $count = $stmt->fetch()['count'];
            
            logUserActivity($user_id, 'wishlist_add', 'Added product to wishlist: ' . $product_id);
            echo json_encode(['success' => true, 'message' => 'Added to wishlist', 'count' => $count]);
            break;
            
        case 'remove':
            if (!$product_id) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                exit();
            }
            
            $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            
            // Get new count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $count = $stmt->fetch()['count'];
            
            logUserActivity($user_id, 'wishlist_remove', 'Removed product from wishlist: ' . $product_id);
            echo json_encode(['success' => true, 'message' => 'Removed from wishlist', 'count' => $count]);
            break;
            
        case 'toggle':
            if (!$product_id) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                exit();
            }
            
            // Check if exists
            $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            
            if ($stmt->fetch()) {
                // Remove
                $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $in_wishlist = false;
                $message = 'Removed from wishlist';
            } else {
                // Add
                $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $product_id]);
                $in_wishlist = true;
                $message = 'Added to wishlist';
            }
            
            // Get new count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $count = $stmt->fetch()['count'];
            
            logUserActivity($user_id, 'wishlist_toggle', 'Toggled wishlist for product: ' . $product_id);
            echo json_encode([
                'success' => true, 
                'in_wishlist' => $in_wishlist, 
                'message' => $message,
                'count' => $count
            ]);
            break;
            
        case 'clear_all':
            $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            logUserActivity($user_id, 'wishlist_clear', 'Cleared entire wishlist');
            echo json_encode(['success' => true, 'message' => 'Wishlist cleared']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Wishlist AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}