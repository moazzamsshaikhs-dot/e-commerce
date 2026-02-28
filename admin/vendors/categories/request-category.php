<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$category_id = (int)($_POST['category_id'] ?? 0);

if (!$category_id) {
    $_SESSION['error'] = 'Please select a category';
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'products.php');
    exit();
}

$db = getDB();

try {
    $db->beginTransaction();
    
    // Check if request already exists
    $stmt = $db->prepare("
        SELECT id FROM category_change_requests 
        WHERE vendor_id = ? AND category_id = ? AND request_type = 'use_category' AND status = 'pending'
    ");
    $stmt->execute([$vendor_id, $category_id]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'You already have a pending request for this category';
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'products.php');
        exit();
    }
    
    // Create request
    $stmt = $db->prepare("
        INSERT INTO category_change_requests (category_id, vendor_id, request_type, created_at)
        VALUES (?, ?, 'use_category', NOW())
    ");
    $stmt->execute([$category_id, $vendor_id]);
    
    // Get category details for notification
    $stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Notify admins
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        SELECT id, 'New Category Usage Request', ?, 'info', NOW()
        FROM users WHERE user_type = 'admin'
    ");
    $stmt->execute(["Vendor wants to use category: {$category['name']}"]);
    
    // Log activity
    logUserActivity($vendor_id, 'category_request', "Requested to use category: {$category['name']}");
    
    $db->commit();
    
    $_SESSION['success'] = 'Your request has been submitted for approval. You\'ll be notified once reviewed.';
    
} catch(Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'products.php');
exit();