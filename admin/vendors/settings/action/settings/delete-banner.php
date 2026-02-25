<?php
// action/settings/delete-banner.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Delete Banner Started ===");

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    $db->beginTransaction();
    
    // Get current banner
    $stmt = $db->prepare("SELECT store_banner FROM vendor_settings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $banner = $stmt->fetchColumn();
    
    if ($banner) {
        // Delete file
        $file_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/vendors/' . $banner;
        if (file_exists($file_path)) {
            unlink($file_path);
            error_log("Deleted banner file: $file_path");
        }
        
        // Update database
        $stmt = $db->prepare("UPDATE vendor_settings SET store_banner = NULL, updated_at = NOW() WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'delete_banner', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Deleted store banner", $ip, $ua]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Banner deleted successfully!'
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>