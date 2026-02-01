<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

try {
    $db = getDB();
    
    $vendor_id = (int)$_POST['vendor_id'];
    $rating = (int)$_POST['vendor_rating'];
    $review_text = trim($_POST['vendor_review_text']);
    $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    
    // Validate
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid rating']);
        exit;
    }
    
    if (empty($review_text)) {
        echo json_encode(['success' => false, 'message' => 'Please write your review']);
        exit;
    }
    
    // Check if user has purchased from this vendor
    $stmt = $db->prepare("
        SELECT COUNT(*) as has_purchased 
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN products p ON oi.product_id = p.id 
        WHERE o.user_id = ? AND p.vendor_id = ? AND o.status = 'delivered'
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $has_purchased = $stmt->fetch()['has_purchased'] > 0;
    
    if (!$has_purchased) {
        echo json_encode(['success' => false, 'message' => 'You must purchase from this vendor before reviewing']);
        exit;
    }
    
    // Check if already reviewed
    $stmt = $db->prepare("
        SELECT id FROM vendor_reviews 
        WHERE user_id = ? AND vendor_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this vendor']);
        exit;
    }
    
    // Insert review
    $stmt = $db->prepare("
        INSERT INTO vendor_reviews (
            user_id, vendor_id, product_id, rating, review_text, is_approved, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id, $product_id, $rating, $review_text]);
    
    // Update vendor rating
    $stmt = $db->prepare("
        UPDATE users 
        SET vendor_rating = (
            SELECT AVG(rating) FROM vendor_reviews WHERE vendor_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$vendor_id, $vendor_id]);
    
    logUserActivity($_SESSION['user_id'], 'vendor_review', 'Reviewed vendor #' . $vendor_id);
    
    echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
    
} catch (PDOException $e) {
    error_log("Vendor Review Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error submitting review']);
}
?>