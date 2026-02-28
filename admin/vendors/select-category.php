<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$category_id = (int)($_POST['category_id'] ?? 0);

// Debug log
error_log("=== Category Selection Started ===");
error_log("Vendor ID: " . $vendor_id);
error_log("Category ID: " . $category_id);
error_log("POST Data: " . print_r($_POST, true));

if (!$category_id) {
    error_log("Error: No category selected");
    $_SESSION['error'] = 'Please select a category';
    header('Location: profile.php');
    exit();
}

$db = getDB();

try {
    $db->beginTransaction();
    
    // Get current vendor category
    $stmt = $db->prepare("SELECT vendor_category FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $current_category = $stmt->fetchColumn();
    error_log("Current category: " . ($current_category ?: 'NULL'));
    
    // Get selected category details
    $stmt = $db->prepare("SELECT name, commission_rate FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        error_log("Error: Category not found with ID: " . $category_id);
        throw new Exception('Category not found');
    }
    
    error_log("Selected Category: " . $category['name']);
    error_log("Commission Rate: " . $category['commission_rate']);
    
    // Check if already have pending request
    $stmt = $db->prepare("
        SELECT id FROM category_change_requests 
        WHERE vendor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$vendor_id]);
    $pending = $stmt->fetch();
    
    if ($pending) {
        error_log("Error: Pending request exists");
        throw new Exception('You already have a pending category change request');
    }
    
    // If same category, no need to request
    if ($current_category == $category_id) {
        error_log("Info: Same category selected");
        $_SESSION['info'] = 'This is already your current category';
        $db->commit();
        header('Location: profile.php');
        exit();
    }
    
    // Insert into category_change_requests
    $stmt = $db->prepare("
        INSERT INTO category_change_requests 
        (vendor_id, category_id, old_category_id, old_commission_rate, new_commission_rate, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $result = $stmt->execute([
        $vendor_id,
        $category_id,
        $current_category ?: null,
        $current_category ? $category['commission_rate'] : null,
        $category['commission_rate']
    ]);
    
    if (!$result) {
        error_log("Error: Failed to insert request");
        throw new Exception('Failed to create request');
    }
    
    $request_id = $db->lastInsertId();
    error_log("Request inserted with ID: " . $request_id);
    
    // Get vendor details for notification
    $stmt = $db->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Notify all admins
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        SELECT id, 'New Category Change Request', ?, 'info', NOW()
        FROM users WHERE user_type = 'admin'
    ");
    $message = "Vendor " . ($vendor['full_name'] ?: $vendor['username']) . " wants to change category to: " . $category['name'];
    $stmt->execute([$message]);
    error_log("Admin notifications sent");
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
        VALUES (?, 'category_request', ?, ?, ?, NOW())
    ");
    $stmt->execute([$vendor_id, "Requested category change to: {$category['name']}", $ip, $ua]);
    error_log("Activity logged");
    
    $db->commit();
    error_log("Transaction committed successfully");
    
    $_SESSION['success'] = "Category change request submitted for admin approval. You'll be notified once reviewed.";
    
} catch(Exception $e) {
    $db->rollBack();
    error_log("ERROR: " . $e->getMessage());
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

error_log("=== Category Selection Ended ===");
header('Location: profile.php');
exit();