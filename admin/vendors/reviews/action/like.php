<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to like reviews.']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$review_id = intval($_POST['review_id'] ?? 0);
$action = $_POST['action'] ?? 'like'; // 'like' or 'unlike'

if ($review_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID.']);
    exit;
}

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Check if review exists and is approved
    $stmt = $db->prepare("
        SELECT r.id, r.is_approved, p.vendor_id
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        WHERE r.id = ? AND r.is_approved = 1
    ");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch();
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Review not found or not approved.']);
        exit;
    }
    
    if ($action === 'like') {
        // Check if already liked
        $stmt = $db->prepare("SELECT id FROM review_likes WHERE review_id = ? AND user_id = ?");
        $stmt->execute([$review_id, $user_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You already liked this review.']);
            exit;
        }
        
        // Add like
        $stmt = $db->prepare("INSERT INTO review_likes (review_id, user_id) VALUES (?, ?)");
        $stmt->execute([$review_id, $user_id]);
        
        // Get new like count
        $stmt = $db->prepare("SELECT COUNT(*) as like_count FROM review_likes WHERE review_id = ?");
        $stmt->execute([$review_id]);
        $like_count = $stmt->fetchColumn();
        
        // Log activity
        logUserActivity($user_id, 'review_liked', "Liked review #{$review_id}");
        
        // Notify review author if not self-like
        if ($review['vendor_id'] != $user_id) {
            notifyReviewLike($review_id, $user_id);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Review liked!',
            'like_count' => $like_count,
            'action' => 'liked'
        ]);
        
    } elseif ($action === 'unlike') {
        // Remove like
        $stmt = $db->prepare("DELETE FROM review_likes WHERE review_id = ? AND user_id = ?");
        $stmt->execute([$review_id, $user_id]);
        
        // Get new like count
        $stmt = $db->prepare("SELECT COUNT(*) as like_count FROM review_likes WHERE review_id = ?");
        $stmt->execute([$review_id]);
        $like_count = $stmt->fetchColumn();
        
        // Log activity
        logUserActivity($user_id, 'review_unliked', "Unliked review #{$review_id}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Review unliked.',
            'like_count' => $like_count,
            'action' => 'unliked'
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Review Like Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Helper function to notify review author
function notifyReviewLike($review_id, $liker_id) {
    global $db;
    
    try {
        // Get review author and liker details
        $stmt = $db->prepare("
            SELECT 
                r.user_id as author_id,
                u1.username as author_username,
                u1.email as author_email,
                u2.username as liker_username,
                u2.full_name as liker_name
            FROM reviews r
            JOIN users u1 ON r.user_id = u1.id
            JOIN users u2 ON u2.id = ?
            WHERE r.id = ?
        ");
        $stmt->execute([$liker_id, $review_id]);
        $data = $stmt->fetch();
        
        if (!$data || $data['author_id'] == $liker_id) {
            return false; // Don't notify self-likes
        }
        
        // Add notification to database
        $stmt = $db->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        $title = "Someone liked your review!";
        $message = "{$data['liker_name']} (@{$data['liker_username']}) liked your product review.";
        
        $stmt->execute([
            $data['author_id'],
            $title,
            $message,
            'info'
        ]);
        
        return true;
        
    } catch(PDOException $e) {
        error_log("Like Notification Error: " . $e->getMessage());
        return false;
    }
}
?>