<?php
// admin/vendors/products/update_status.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Define SITE_URL if not defined


// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ' . SITE_URL . 'index.php');
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
        $_SESSION['error'] = 'Your vendor account is not approved.';
        header('Location: ' . SITE_URL . 'vendor/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'vendor/dashboard.php');
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: ' . SITE_URL . 'admin/vendors/products/products.php');
    exit();
}

// Get action type
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch($action) {
    case 'update_stock':
        handleStockUpdate($vendor_id);
        break;
    case 'update_featured':
        handleFeaturedUpdate($vendor_id);
        break;
    case 'bulk_status':
        handleBulkStatusUpdate($vendor_id);
        break;
    default:
        $_SESSION['error'] = 'Invalid action specified.';
        header('Location: ' . SITE_URL . 'admin/vendors/products/products.php');
        exit();
}

/**
 * Handle stock update (mark as out of stock)
 */
function handleStockUpdate($vendor_id) {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $new_stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'products.php';
    
    if ($product_id <= 0) {
        $_SESSION['error'] = 'Invalid product ID.';
        header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
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
            $_SESSION['error'] = 'Product not found or access denied.';
            header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
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
        $activity = "Updated stock for product '{$product['name']}' (ID: $product_id) from $old_stock to $new_stock";
        
        if (function_exists('logUserActivity')) {
            logUserActivity($vendor_id, 'stock_update', $activity);
        }
        
        // Commit transaction
        $db->commit();
        
        // Set success message
        if ($new_stock == 0) {
            $_SESSION['success'] = "Product '{$product['name']}' has been marked as out of stock.";
        } else {
            $_SESSION['success'] = "Stock updated successfully for '{$product['name']}'.";
        }
        
        // Redirect back
        $redirect_url = SITE_URL . 'admin/vendors/products/';
        if ($redirect_to === 'view' && isset($_POST['product_id'])) {
            $redirect_url .= 'view.php?id=' . $product_id;
        } elseif ($redirect_to === 'edit') {
            $redirect_url .= 'edit.php?id=' . $product_id;
        } elseif ($redirect_to === 'low-stock') {
            $redirect_url .= 'low-stock.php';
        } else {
            $redirect_url .= 'products.php';
        }
        
        header('Location: ' . $redirect_url);
        exit();
        
    } catch(Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Stock update error: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating stock: ' . $e->getMessage();
        header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
        exit();
    }
}

/**
 * Handle featured status update
 */
function handleFeaturedUpdate($vendor_id) {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $featured = isset($_POST['featured']) ? (int)$_POST['featured'] : 0;
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'products.php';
    
    if ($product_id <= 0) {
        $_SESSION['error'] = 'Invalid product ID.';
        header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
        exit();
    }
    
    try {
        $db = getDB();
        
        // Get current product info
        $stmt = $db->prepare("SELECT name, featured FROM products WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$product_id, $vendor_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $_SESSION['error'] = 'Product not found or access denied.';
            header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
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
            $activity = "Product '{$product['name']}' (ID: $product_id) $status featured products";
            
            if (function_exists('logUserActivity')) {
                logUserActivity($vendor_id, 'featured_update', $activity);
            }
            
            // Set success message
            if ($featured) {
                $_SESSION['success'] = "Product '{$product['name']}' is now featured.";
            } else {
                $_SESSION['success'] = "Product '{$product['name']}' removed from featured.";
            }
        } else {
            $_SESSION['error'] = 'Failed to update featured status.';
        }
        
        // Redirect back
        $redirect_url = SITE_URL . 'admin/vendors/products/';
        if ($redirect_to === 'view') {
            $redirect_url .= 'view.php?id=' . $product_id;
        } elseif ($redirect_to === 'edit') {
            $redirect_url .= 'edit.php?id=' . $product_id;
        } elseif ($redirect_to === 'low-stock') {
            $redirect_url .= 'low-stock.php';
        } else {
            $redirect_url .= 'products.php';
        }
        
        header('Location: ' . $redirect_url);
        exit();
        
    } catch(PDOException $e) {
        error_log("Featured update error: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating featured status: ' . $e->getMessage();
        header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
        exit();
    }
}

/**
 * Handle bulk status updates
 */
function handleBulkStatusUpdate($vendor_id) {
    $product_ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];
    $bulk_action = isset($_POST['bulk_action']) ? $_POST['bulk_action'] : '';
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'products.php';
    
    if (empty($product_ids) || !is_array($product_ids)) {
        $_SESSION['error'] = 'No products selected.';
        header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
        exit();
    }
    
    // Sanitize product IDs
    $product_ids = array_map('intval', $product_ids);
    $product_ids = array_filter($product_ids);
    
    if (empty($product_ids)) {
        $_SESSION['error'] = 'Invalid product IDs.';
        header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
        exit();
    }
    
    try {
        $db = getDB();
        
        // Begin transaction
        $db->beginTransaction();
        
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $params = $product_ids;
        $params[] = $vendor_id;
        
        $updated_count = 0;
        $product_names = [];
        
        switch($bulk_action) {
            case 'mark_out_of_stock':
                // Get product names first
                $stmt = $db->prepare("SELECT id, name FROM products WHERE id IN ($placeholders) AND vendor_id = ?");
                $stmt->execute($params);
                $products = $stmt->fetchAll();
                
                foreach($products as $p) {
                    $product_names[] = $p['name'];
                }
                
                // Update stock to 0
                $update_params = $product_ids;
                $update_params[] = $vendor_id;
                $stmt = $db->prepare("
                    UPDATE products 
                    SET stock = 0,
                        low_stock = 0,
                        out_of_stock = 1,
                        updated_at = NOW() 
                    WHERE id IN ($placeholders) AND vendor_id = ?
                ");
                $stmt->execute($update_params);
                $updated_count = $stmt->rowCount();
                
                $message = "Marked " . count($product_names) . " products as out of stock";
                break;
                
            case 'mark_in_stock':
                // Get product names first
                $stmt = $db->prepare("SELECT id, name FROM products WHERE id IN ($placeholders) AND vendor_id = ?");
                $stmt->execute($params);
                $products = $stmt->fetchAll();
                
                foreach($products as $p) {
                    $product_names[] = $p['name'];
                }
                
                // Set stock to at least 10
                $update_params = $product_ids;
                $update_params[] = $vendor_id;
                $stmt = $db->prepare("
                    UPDATE products 
                    SET stock = 10,
                        low_stock = CASE WHEN 10 < 10 THEN 1 ELSE 0 END,
                        out_of_stock = 0,
                        updated_at = NOW() 
                    WHERE id IN ($placeholders) AND vendor_id = ? AND stock = 0
                ");
                $stmt->execute($update_params);
                $updated_count = $stmt->rowCount();
                
                $message = "Restocked " . $updated_count . " products";
                break;
                
            case 'feature_selected':
                // Get product names first
                $stmt = $db->prepare("SELECT id, name FROM products WHERE id IN ($placeholders) AND vendor_id = ?");
                $stmt->execute($params);
                $products = $stmt->fetchAll();
                
                foreach($products as $p) {
                    $product_names[] = $p['name'];
                }
                
                // Set featured = 1
                $update_params = $product_ids;
                $update_params[] = $vendor_id;
                $stmt = $db->prepare("
                    UPDATE products 
                    SET featured = 1,
                        updated_at = NOW() 
                    WHERE id IN ($placeholders) AND vendor_id = ?
                ");
                $stmt->execute($update_params);
                $updated_count = $stmt->rowCount();
                
                $message = "Featured " . $updated_count . " products";
                break;
                
            case 'unfeature_selected':
                // Get product names first
                $stmt = $db->prepare("SELECT id, name FROM products WHERE id IN ($placeholders) AND vendor_id = ?");
                $stmt->execute($params);
                $products = $stmt->fetchAll();
                
                foreach($products as $p) {
                    $product_names[] = $p['name'];
                }
                
                // Set featured = 0
                $update_params = $product_ids;
                $update_params[] = $vendor_id;
                $stmt = $db->prepare("
                    UPDATE products 
                    SET featured = 0,
                        updated_at = NOW() 
                    WHERE id IN ($placeholders) AND vendor_id = ?
                ");
                $stmt->execute($update_params);
                $updated_count = $stmt->rowCount();
                
                $message = "Removed featured status from " . $updated_count . " products";
                break;
                
            default:
                $db->rollBack();
                $_SESSION['error'] = 'Invalid bulk action.';
                header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
                exit();
        }
        
        // Log bulk activity
        if (!empty($product_names) && function_exists('logUserActivity')) {
            $product_list = implode(', ', array_slice($product_names, 0, 3));
            if (count($product_names) > 3) {
                $product_list .= ' and ' . (count($product_names) - 3) . ' more';
            }
            $activity = "Bulk action '$bulk_action' on products: $product_list";
            logUserActivity($vendor_id, 'bulk_update', $activity);
        }
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['success'] = $message . ' successfully.';
        
    } catch(Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Bulk update error: " . $e->getMessage());
        $_SESSION['error'] = 'Error performing bulk update: ' . $e->getMessage();
    }
    
    // Redirect back
    header('Location: ' . SITE_URL . 'admin/vendors/products/' . $redirect_to);
    exit();
}
?>