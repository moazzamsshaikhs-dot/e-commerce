<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Check if vendor is approved
$vendor_id = $_SESSION['user_id'];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Vendor not approved']);
        exit();
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit();
}

// Validate input
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$delete_reason = isset($_POST['delete_reason']) ? trim($_POST['delete_reason']) : '';
$delete_option = isset($_POST['delete_option']) ? $_POST['delete_option'] : 'soft';
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($product_id <= 0 || $action !== 'soft_delete') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // 1. Get product details
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    // 2. Archive product data to deleted_products table
    $archive_query = "INSERT INTO deleted_products (
        original_id,
        vendor_id,
        name,
        description,
        price,
        old_price,
        image,
        category,
        stock,
        featured,
        views,
        sales_count,
        approved_status,
        low_stock,
        out_of_stock,
        average_rating,
        review_count,
        five_star_count,
        four_star_count,
        three_star_count,
        two_star_count,
        one_star_count,
        delete_reason,
        deleted_by,
        deleted_at,
        original_created_at,
        original_updated_at
    ) VALUES (
        :original_id,
        :vendor_id,
        :name,
        :description,
        :price,
        :old_price,
        :image,
        :category,
        :stock,
        :featured,
        :views,
        :sales_count,
        :approved_status,
        :low_stock,
        :out_of_stock,
        :average_rating,
        :review_count,
        :five_star_count,
        :four_star_count,
        :three_star_count,
        :two_star_count,
        :one_star_count,
        :delete_reason,
        :deleted_by,
        NOW(),
        :created_at,
        :updated_at
    )";
    
    $stmt = $db->prepare($archive_query);
    $stmt->execute([
        ':original_id' => $product['id'],
        ':vendor_id' => $product['vendor_id'],
        ':name' => $product['name'],
        ':description' => $product['description'],
        ':price' => $product['price'],
        ':old_price' => $product['old_price'],
        ':image' => $product['image'],
        ':category' => $product['category'],
        ':stock' => $product['stock'],
        ':featured' => $product['featured'],
        ':views' => $product['views'],
        ':sales_count' => $product['sales_count'],
        ':approved_status' => $product['approved_status'],
        ':low_stock' => $product['low_stock'],
        ':out_of_stock' => $product['out_of_stock'],
        ':average_rating' => $product['average_rating'],
        ':review_count' => $product['review_count'],
        ':five_star_count' => $product['five_star_count'],
        ':four_star_count' => $product['four_star_count'],
        ':three_star_count' => $product['three_star_count'],
        ':two_star_count' => $product['two_star_count'],
        ':one_star_count' => $product['one_star_count'],
        ':delete_reason' => $delete_reason,
        ':deleted_by' => $vendor_id,
        ':created_at' => $product['created_at'],
        ':updated_at' => $product['updated_at']
    ]);
    
    // 3. Archive reviews
    $stmt = $db->prepare("INSERT INTO deleted_reviews SELECT * FROM reviews WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // 4. Remove from cart items
    $stmt = $db->prepare("DELETE FROM cart_items WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // 5. Remove from wishlist
    $stmt = $db->prepare("DELETE FROM wishlist WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // 6. Delete the product from main table
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    
    // 7. Decrement vendor's total products count
    $stmt = $db->prepare("UPDATE users SET total_products = total_products - 1 WHERE id = ?");
    $stmt->execute([$vendor_id]);
    
    // 8. Log the activity
    $activity_log = "Soft deleted product: {$product['name']} (ID: $product_id)";
    if ($delete_reason) {
        $activity_log .= " - Reason: $delete_reason";
    }
    logUserActivity($vendor_id, 'product_soft_delete', $activity_log);
    
    // Commit transaction
    $db->commit();
    
    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Product moved to archive successfully.',
        'product_name' => $product['name']
    ]);
    
} catch(PDOException $e) {
    // Rollback on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Soft Delete Error - Product ID $product_id: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete product. Please try again.'
    ]);
}