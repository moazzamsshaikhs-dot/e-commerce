<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit;
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Vendor account not approved.']);
        exit;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$review_id = intval($_POST['review_id'] ?? 0);
$delete_reason = trim($_POST['reason'] ?? '');

if ($review_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID.']);
    exit;
}

try {
    // Verify review belongs to vendor's product
    $stmt = $db->prepare("
        SELECT r.*, p.name as product_name, u.username as customer_name
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ? AND p.vendor_id = ?
    ");
    $stmt->execute([$review_id, $_SESSION['user_id']]);
    $review = $stmt->fetch();
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Review not found or access denied.']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Archive review before deletion (optional - for record keeping)
        $stmt = $db->prepare("
            INSERT INTO deleted_reviews 
            (original_id, product_id, user_id, rating, review_text, is_approved, 
             vendor_id, delete_reason, deleted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $review['id'],
            $review['product_id'],
            $review['user_id'],
            $review['rating'],
            $review['review_text'],
            $review['is_approved'],
            $_SESSION['user_id'],
            $delete_reason
        ]);
        
        // Delete review likes
        $stmt = $db->prepare("DELETE FROM review_likes WHERE review_id = ?");
        $stmt->execute([$review_id]);
        
        // Delete review reports
        $stmt = $db->prepare("DELETE FROM review_reports WHERE review_id = ?");
        $stmt->execute([$review_id]);
        
        // Delete vendor responses
        $stmt = $db->prepare("DELETE FROM vendor_responses WHERE review_id = ?");
        $stmt->execute([$review_id]);
        
        // Finally delete the review
        $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$review_id]);
        
        // Update product statistics
        updateProductStats($review['product_id']);
        
        $db->commit();
        
        // Log the action
        logUserActivity($_SESSION['user_id'], 'review_deleted', 
                       "Deleted review #{$review_id} for product: {$review['product_name']}. Reason: {$delete_reason}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Review deleted successfully!',
            'review_id' => $review_id
        ]);
        
    } catch(Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch(PDOException $e) {
    error_log("Review Deletion Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Helper function to update product statistics
function updateProductStats($product_id) {
    global $db;
    
    try {
        // Recalculate product ratings
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as review_count,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM reviews 
            WHERE product_id = ? AND is_approved = 1
        ");
        $stmt->execute([$product_id]);
        $stats = $stmt->fetch();
        
        // Update product table
        $stmt = $db->prepare("
            UPDATE products 
            SET 
                review_count = ?,
                average_rating = ?,
                five_star_count = ?,
                four_star_count = ?,
                three_star_count = ?,
                two_star_count = ?,
                one_star_count = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $stats['review_count'] ?? 0,
            $stats['avg_rating'] ?? 0,
            $stats['five_star'] ?? 0,
            $stats['four_star'] ?? 0,
            $stats['three_star'] ?? 0,
            $stats['two_star'] ?? 0,
            $stats['one_star'] ?? 0,
            $product_id
        ]);
        
    } catch(PDOException $e) {
        error_log("Update Product Stats Error: " . $e->getMessage());
    }
}
?>