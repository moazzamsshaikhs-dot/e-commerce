<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set header for JSON response
header('Content-Type: application/json');

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/e-commerce/');
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if user is vendor
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Check if vendor is approved
$vendor_id = (int)$_SESSION['user_id'];
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
    error_log("Vendor status check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit();
}

// Validate input
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$delete_reason = isset($_POST['delete_reason']) ? trim($_POST['delete_reason']) : '';
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
    
    // 1. Get product details with LOCK FOR UPDATE to prevent concurrent access
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ? FOR UPDATE");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    // 2. Check if deleted_products table exists
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'deleted_products'");
        $table_exists = $stmt->rowCount() > 0;
        
        if (!$table_exists) {
            // Create deleted_products table if it doesn't exist
            $db->exec("
                CREATE TABLE IF NOT EXISTS `deleted_products` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `original_id` int(11) NOT NULL,
                    `vendor_id` int(11) NOT NULL,
                    `name` varchar(255) NOT NULL,
                    `description` text DEFAULT NULL,
                    `price` decimal(10,2) NOT NULL,
                    `old_price` decimal(10,2) DEFAULT NULL,
                    `image` varchar(255) DEFAULT NULL,
                    `category` varchar(100) DEFAULT NULL,
                    `stock` int(11) DEFAULT 0,
                    `featured` tinyint(1) DEFAULT 0,
                    `views` int(11) DEFAULT 0,
                    `sales_count` int(11) DEFAULT 0,
                    `approved_status` enum('pending','approved','rejected') DEFAULT 'pending',
                    `low_stock` tinyint(1) DEFAULT 0,
                    `out_of_stock` tinyint(1) DEFAULT 0,
                    `average_rating` decimal(3,2) DEFAULT 0.00,
                    `review_count` int(11) DEFAULT 0,
                    `five_star_count` int(11) DEFAULT 0,
                    `four_star_count` int(11) DEFAULT 0,
                    `three_star_count` int(11) DEFAULT 0,
                    `two_star_count` int(11) DEFAULT 0,
                    `one_star_count` int(11) DEFAULT 0,
                    `delete_reason` varchar(255) DEFAULT NULL,
                    `deleted_by` int(11) DEFAULT NULL,
                    `deleted_at` datetime DEFAULT current_timestamp(),
                    `original_created_at` timestamp NULL DEFAULT NULL,
                    `original_updated_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `vendor_id` (`vendor_id`),
                    KEY `original_id` (`original_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");
        }
    } catch(PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
    }
    
    // 3. Archive product data to deleted_products table
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
        :created_at,
        :updated_at
    )";
    
    $stmt = $db->prepare($archive_query);
    $result = $stmt->execute([
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
    
    if (!$result) {
        throw new Exception('Failed to archive product');
    }
    
    // 4. Archive reviews if deleted_reviews table exists
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'deleted_reviews'");
        $reviews_table_exists = $stmt->rowCount() > 0;
        
        if ($reviews_table_exists) {
            $stmt = $db->prepare("INSERT INTO deleted_reviews SELECT * FROM reviews WHERE product_id = ?");
            $stmt->execute([$product_id]);
        }
    } catch(PDOException $e) {
        error_log("Reviews archive error: " . $e->getMessage());
    }
    
    // 5. Remove from cart items
    $stmt = $db->prepare("DELETE FROM cart_items WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // 6. Remove from wishlist
    $stmt = $db->prepare("DELETE FROM wishlist WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // 7. Delete the product from main table
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    
    // 8. Decrement vendor's total products count
    $stmt = $db->prepare("UPDATE users SET total_products = total_products - 1 WHERE id = ?");
    $stmt->execute([$vendor_id]);
    
    // 9. Log the activity
    $activity_log = "Soft deleted product: {$product['name']} (ID: $product_id)";
    if ($delete_reason) {
        $activity_log .= " - Reason: $delete_reason";
    }
    
    // Check if logUserActivity function exists
    if (function_exists('logUserActivity')) {
        logUserActivity($vendor_id, 'product_soft_delete', $activity_log);
    }
    
    // Commit transaction
    $db->commit();
    
    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Product moved to archive successfully.',
        'product_name' => $product['name']
    ]);
    
} catch(Exception $e) {
    // Rollback on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Soft Delete Error - Product ID $product_id: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete product: ' . $e->getMessage()
    ]);
}