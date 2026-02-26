<?php
// admin/products/product-approval.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    redirect('products.php');
}

// Get form data
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

if (!$product_id) {
    $_SESSION['error'] = 'Invalid product ID';
    redirect('products.php');
}

if (!in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = 'Invalid action';
    redirect('products.php');
}

if ($action === 'reject' && empty($rejection_reason)) {
    $_SESSION['error'] = 'Rejection reason is required';
    redirect('products.php');
}

try {
    $db = getDB();
    
    // Get product details
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error'] = 'Product not found';
        redirect('products.php');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    if ($action === 'approve') {
        // Update product status to approved
        $stmt = $db->prepare("UPDATE products SET approved_status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$product_id]);
        
        // Create notification (you can implement this if you have a notifications table)
        $message = "Product '{$product['name']}' has been approved and is now visible to customers.";
        
        // Log activity
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, 'product_approved', ?, ?, ?, NOW())
        ");
        $log->execute([$_SESSION['user_id'], "Approved product: {$product['name']} (ID: {$product_id})", $ip, $ua]);
        
        $_SESSION['success'] = "Product '{$product['name']}' has been approved successfully!";
        
    } elseif ($action === 'reject') {
        // Update product status to rejected
        $stmt = $db->prepare("UPDATE products SET approved_status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$product_id]);
        
        // Store rejection reason (you might want to add a column for this)
        // For now, we'll just log it
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, 'product_rejected', ?, ?, ?, NOW())
        ");
        $log->execute([$_SESSION['user_id'], "Rejected product: {$product['name']} (ID: {$product_id}). Reason: {$rejection_reason}", $ip, $ua]);
        
        $_SESSION['success'] = "Product '{$product['name']}' has been rejected.";
    }
    
    $db->commit();
    
} catch(PDOException $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    error_log("Product approval error: " . $e->getMessage());
}

redirect('products.php');
?>