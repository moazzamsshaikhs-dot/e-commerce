<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$product_id = (int)($_GET['id'] ?? 0);
$redirect = $_GET['redirect'] ?? 'products.php';

if (!$product_id) {
    $_SESSION['error'] = 'Invalid product ID';
    header('Location: ' . $redirect);
    exit();
}

$db = getDB();

try {
    $db->beginTransaction();
    
    // Get current featured status
    $stmt = $db->prepare("SELECT is_featured, vendor_id, name FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    $new_status = $product['is_featured'] ? 0 : 1;
    
    // Update product
    $stmt = $db->prepare("UPDATE products SET is_featured = ? WHERE id = ?");
    $stmt->execute([$new_status, $product_id]);
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $action = $new_status ? 'featured' : 'unfeatured';
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
        VALUES (?, 'product_featured', ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], "Product '{$product['name']}' (ID: {$product_id}) {$action}", $ip, $ua]);
    
    // Create notification for vendor
    $message = "Your product '{$product['name']}' has been " . ($new_status ? 'featured' : 'removed from featured') . " by admin.";
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, 'Product Update', ?, 'info', NOW())
    ");
    $stmt->execute([$product['vendor_id'], $message]);
    
    $db->commit();
    
    $_SESSION['success'] = "Product " . ($new_status ? 'marked as featured' : 'removed from featured') . " successfully!";
    
} catch(Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: ' . $redirect);
exit();