<?php
// admin/vendors/action/suspend-vendor.php
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
$suspension_reason = trim($_POST['suspension_reason'] ?? '');

if (!$vendor_id) {
    $_SESSION['error'] = 'Invalid vendor ID';
    header('Location: ../vendors.php');
    exit();
}

if (empty($suspension_reason)) {
    $_SESSION['error'] = 'Please provide a reason for suspension';
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
    $stmt = $db->prepare("UPDATE users SET vendor_status = 'suspended', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$vendor_id]);
    
    // Create notification for vendor
    $message = "Your vendor account has been suspended. Reason: $suspension_reason";
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, 'Vendor Account Suspended', ?, 'warning', NOW())
    ");
    $stmt->execute([$vendor_id, $message]);
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
        VALUES (?, 'vendor_suspended', ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], "Suspended vendor #$vendor_id (" . $vendor['email'] . "). Reason: $suspension_reason", $ip, $ua]);
    
    $db->commit();
    
    $_SESSION['success'] = "Vendor " . htmlspecialchars($vendor['full_name'] ?? '') . " has been suspended";
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = 'Error suspending vendor: ' . $e->getMessage();
}

header('Location: ../vendors.php');
exit();