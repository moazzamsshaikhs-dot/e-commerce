<?php
require_once 'includes/config.php';
require_once 'includes/auth-check.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login to submit a review.';
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_title = trim($_POST['review_title'] ?? '');
$review_text = trim($_POST['review_text'] ?? '');

// Validation
$errors = [];

if ($product_id <= 0) {
    $errors[] = 'Invalid product.';
}

if ($rating < 1 || $rating > 5) {
    $errors[] = 'Please select a valid rating (1-5 stars).';
}

if (empty($review_title)) {
    $errors[] = 'Review title is required.';
} elseif (strlen($review_title) < 5) {
    $errors[] = 'Review title must be at least 5 characters.';
}

if (empty($review_text)) {
    $errors[] = 'Review text is required.';
} elseif (strlen($review_text) < 20) {
    $errors[] = 'Review must be at least 20 characters.';
}

// Check if user has purchased this product
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT o.id 
                         FROM orders o 
                         JOIN order_items oi ON o.id = oi.order_id 
                         WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered' 
                         LIMIT 1");
    $stmt->execute([$user_id, $product_id]);
    $has_purchased = $stmt->rowCount() > 0;
    
    if (!$has_purchased) {
        $errors[] = 'You can only review products you have purchased.';
    }
    
    // Check if already reviewed
    $stmt = $db->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    if ($stmt->rowCount() > 0) {
        $errors[] = 'You have already reviewed this product.';
    }
    
} catch(PDOException $e) {
    $errors[] = 'Error checking purchase history.';
}

if (empty($errors)) {
    try {
        $db = getDB();
        
        // Insert review
        $stmt = $db->prepare("INSERT INTO reviews (user_id, product_id, rating, review_text, is_approved) 
                              VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$user_id, $product_id, $rating, $review_text]);
        
        // Update product rating statistics
        $stmt = $db->prepare("UPDATE products SET 
                              average_rating = (SELECT AVG(rating) FROM reviews WHERE product_id = ?),
                              review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ?),
                              five_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 5),
                              four_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 4),
                              three_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 3),
                              two_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 2),
                              one_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 1)
                              WHERE id = ?");
        $stmt->execute([$product_id, $product_id, $product_id, $product_id, $product_id, $product_id, $product_id, $product_id]);
        
        $_SESSION['success'] = 'Thank you for your review! It has been submitted successfully.';
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error submitting review. Please try again.';
    }
} else {
    $_SESSION['error'] = implode('<br>', $errors);
}

// Redirect back to product page
header('Location: product-details.php?id=' . $product_id);
exit();
?>
