<?php
// admin/vendors/action/approve-vendor.php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vendors.php');
    exit();
}

$vendor_id = (int)($_POST['vendor_id'] ?? 0);

if (!$vendor_id) {
    $_SESSION['error'] = 'Invalid vendor ID';
    header('Location: ../vendors.php');
    exit();
}

try {
    $db = getDB();
    
    // Check if vendor exists
    $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND user_type = 'vendor'");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        throw new Exception('Vendor not found');
    }
    
    $db->beginTransaction();
    
    // Update vendor status
    $stmt = $db->prepare("UPDATE users SET vendor_status = 'approved', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$vendor_id]);
    
    // Create notification for vendor
    $message = "Congratulations! Your vendor account has been approved. You can now start selling products.";
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, 'Vendor Account Approved', ?, 'success', NOW())
    ");
    $stmt->execute([$vendor_id, $message]);
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
        VALUES (?, 'vendor_approved', ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], "Approved vendor #$vendor_id (" . $vendor['email'] . ")", $ip, $ua]);
    
    $db->commit();
    
    $_SESSION['success'] = "Vendor " . htmlspecialchars($vendor['full_name'] ?? '') . " has been approved successfully";
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = 'Error approving vendor: ' . $e->getMessage();
}

header('Location: ../vendors.php');
exit();