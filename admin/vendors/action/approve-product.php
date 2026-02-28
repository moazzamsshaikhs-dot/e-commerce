<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}
$product_id = (int)($_GET['id'] ?? 0);
$redirect = $_GET['redirect'] ?? '../products.php';
if (!$product_id) {
    $_SESSION['error'] = 'Invalid product ID';
    header('Location: ' . $redirect);
    exit();
}
$db = getDB();
try {
    $db->beginTransaction();
    
    // Get product details
    $stmt = $db->prepare("SELECT p.*, u.id as vendor_id, u.email, u.full_name FROM products p JOIN users u ON p.vendor_id = u.id WHERE p.id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    // Update product status to approved
    $stmt = $db->prepare("UPDATE products SET approved_status = 'approved' WHERE id = ?");
    $stmt->execute([$product_id]);
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
        VALUES (?, 'product_approved', ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], "Approved product '{$product['name']}' (ID: {$product_id})", $ip, $ua]);
    
    // Create notification for vendor
    $message = "Your product '{$product['name']}' has been approved by admin.";
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, 'Product Approved', ?, 'success', NOW())
    ");
    $stmt->execute([$product['vendor_id'], $message]);
    
    $db->commit();
 
    $_SESSION['success'] = "Product approved successfully!";
    redirect('Location: ' . $redirect);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}