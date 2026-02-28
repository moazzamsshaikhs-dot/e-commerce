<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$category_id = (int)($_POST['category_id'] ?? 0);
$commission_rate = (float)($_POST['commission_rate'] ?? 0);

if (!$category_id || $commission_rate <= 0) {
    $_SESSION['error'] = 'Invalid request';
    header('Location: profile.php');
    exit();
}

$db = getDB();

try {
    $db->beginTransaction();
    
    // Get current commission
    $stmt = $db->prepare("
        SELECT c.commission_rate as default_rate
        FROM categories c
        WHERE c.id = ?
    ");
    $stmt->execute([$category_id]);
    $commission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commission) {
        throw new Exception('Category not found');
    }
    
    // Check for custom commission
    $stmt = $db->prepare("
        SELECT commission_rate FROM vendor_category_commissions 
        WHERE vendor_id = ? AND category_id = ?
    ");
    $stmt->execute([$vendor_id, $category_id]);
    $custom_rate = $stmt->fetchColumn();
    
    $old_rate = $custom_rate ?: $commission['default_rate'];
    
    // Check if request already exists
    $stmt = $db->prepare("
        SELECT id FROM category_change_requests 
        WHERE vendor_id = ? AND category_id = ? AND request_type = 'change_commission' AND status = 'pending'
    ");
    $stmt->execute([$vendor_id, $category_id]);
    
    if ($stmt->fetch()) {
        throw new Exception('You already have a pending commission change request');
    }
    
    // Create request
    $stmt = $db->prepare("
        INSERT INTO category_change_requests (category_id, vendor_id, request_type, old_commission_rate, new_commission_rate, created_at)
        VALUES (?, ?, 'change_commission', ?, ?, NOW())
    ");
    $stmt->execute([$category_id, $vendor_id, $old_rate, $commission_rate]);
    
    // Get category details
    $stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Notify admins
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        SELECT id, 'Commission Change Request', ?, 'info', NOW()
        FROM users WHERE user_type = 'admin'
    ");
    $stmt->execute(["Vendor requests commission change for {$category['name']} from {$old_rate}% to {$commission_rate}%"]);
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
        VALUES (?, 'commission_request', ?, ?, ?, NOW())
    ");
    $stmt->execute([$vendor_id, "Requested commission change for {$category['name']} from {$old_rate}% to {$commission_rate}%", $ip, $ua]);
    
    $db->commit();
    
    $_SESSION['success'] = 'Your commission change request has been submitted for approval.';
    
} catch(Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: profile.php');
exit();