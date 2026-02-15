<?php
// admin/vendors/products/ajax/update_status.php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Set header for JSON response
header('Content-Type: application/json');

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/e-commerce/');
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get action type
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch($action) {
    case 'update_stock':
        ajaxUpdateStock($vendor_id);
        break;
    case 'update_featured':
        ajaxUpdateFeatured($vendor_id);
        break;
    case 'toggle_featured':
        ajaxToggleFeatured($vendor_id);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
}

/**
 * AJAX: Update stock
 */
function ajaxUpdateStock($vendor_id) {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $new_stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    if ($new_stock < 0) {
        echo json_encode(['success' => false, 'message' => 'Stock cannot be negative']);
        exit();
    }
    
    try {
        $db = getDB();
        
        // Begin transaction
        $db->beginTransaction();
        
        // Get current product info
        $stmt = $db->prepare("SELECT name, stock FROM products WHERE id = ? AND vendor_id = ? FOR UPDATE");
        $stmt->execute([$product_id, $vendor_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }
        
        // Update stock
        $stmt = $db->prepare("
            UPDATE products 
            SET stock = :stock,
                low_stock = CASE 
                    WHEN :stock > 0 AND :stock < 10 THEN 1 
                    ELSE 0 
                END,
                out_of_stock = CASE 
                    WHEN :stock = 0 THEN 1 
                    ELSE 0 
                END,
                updated_at = NOW() 
            WHERE id = :id AND vendor_id = :vendor_id
        ");
        
        $result = $stmt->execute([
            ':stock' => $new_stock,
            ':id' => $product_id,
            ':vendor_id' => $vendor_id
        ]);
        
        if (!$result) {
            throw new Exception('Failed to update stock');
        }
        
        // Log activity
        $old_stock = $product['stock'];
        if (function_exists('logUserActivity')) {
            $activity = "AJAX updated stock for '{$product['name']}' from $old_stock to $new_stock";
            logUserActivity($vendor_id, 'ajax_stock_update', $activity);
        }
        
        // Commit transaction
        $db->commit();
        
        // Determine stock status
        $status = 'normal';
        $status_text = 'In Stock';
        $status_color = 'success';
        
        if ($new_stock == 0) {
            $status = 'out_of_stock';
            $status_text = 'Out of Stock';
            $status_color = 'danger';
        } elseif ($new_stock < 5) {
            $status = 'critical';
            $status_text = 'Critical';
            $status_color = 'warning';
        } elseif ($new_stock < 10) {
            $status = 'low';
            $status_text = 'Low Stock';
            $status_color = 'info';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock updated successfully',
            'new_stock' => $new_stock,
            'old_stock' => $old_stock,
            'product_name' => $product['name'],
            'status' => $status,
            'status_text' => $status_text,
            'status_color' => $status_color
        ]);
        
    } catch(Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("AJAX stock update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * AJAX: Update featured status
 */
function ajaxUpdateFeatured($vendor_id) {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $featured = isset($_POST['featured']) ? (int)$_POST['featured'] : 0;
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    try {
        $db = getDB();
        
        // Get current product info
        $stmt = $db->prepare("SELECT name, featured FROM products WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$product_id, $vendor_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }
        
        // Update featured status
        $stmt = $db->prepare("
            UPDATE products 
            SET featured = :featured,
                updated_at = NOW() 
            WHERE id = :id AND vendor_id = :vendor_id
        ");
        
        $result = $stmt->execute([
            ':featured' => $featured,
            ':id' => $product_id,
            ':vendor_id' => $vendor_id
        ]);
        
        if ($result) {
            // Log activity
            $status = $featured ? 'added to' : 'removed from';
            if (function_exists('logUserActivity')) {
                $activity = "AJAX updated featured for '{$product['name']}': $status featured";
                logUserActivity($vendor_id, 'ajax_featured_update', $activity);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $featured ? 'Product added to featured' : 'Product removed from featured',
                'featured' => $featured,
                'product_name' => $product['name']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update featured status']);
        }
        
    } catch(PDOException $e) {
        error_log("AJAX featured update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * AJAX: Toggle featured status
 */
function ajaxToggleFeatured($vendor_id) {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    try {
        $db = getDB();
        
        // Get current product info
        $stmt = $db->prepare("SELECT name, featured FROM products WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$product_id, $vendor_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }
        
        $new_featured = $product['featured'] ? 0 : 1;
        
        // Update featured status
        $stmt = $db->prepare("
            UPDATE products 
            SET featured = :featured,
                updated_at = NOW() 
            WHERE id = :id AND vendor_id = :vendor_id
        ");
        
        $result = $stmt->execute([
            ':featured' => $new_featured,
            ':id' => $product_id,
            ':vendor_id' => $vendor_id
        ]);
        
        if ($result) {
            // Log activity
            $status = $new_featured ? 'added to' : 'removed from';
            if (function_exists('logUserActivity')) {
                $activity = "AJAX toggled featured for '{$product['name']}': $status featured";
                logUserActivity($vendor_id, 'ajax_featured_toggle', $activity);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $new_featured ? 'Product added to featured' : 'Product removed from featured',
                'featured' => $new_featured,
                'product_name' => $product['name']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to toggle featured status']);
        }
        
    } catch(PDOException $e) {
        error_log("AJAX featured toggle error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>