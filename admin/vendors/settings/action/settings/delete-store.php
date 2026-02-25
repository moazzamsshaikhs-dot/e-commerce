<?php
// action/settings/delete-store.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Delete Store Started ===");

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
    
    // Get all files to delete (logos, banners)
    $stmt = $db->prepare("SELECT store_logo, store_banner FROM vendor_settings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $files = $stmt->fetch();
    
    if ($files) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/vendors/';
        
        if ($files['store_logo']) {
            $logo_path = $upload_dir . $files['store_logo'];
            if (file_exists($logo_path)) unlink($logo_path);
        }
        
        if ($files['store_banner']) {
            $banner_path = $upload_dir . $files['store_banner'];
            if (file_exists($banner_path)) unlink($banner_path);
        }
    }
    
    // Delete user (cascade will delete all related records)
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    
    // Log activity (though user will be deleted)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    error_log("Vendor $vendor_id deleted their store from IP $ip");
    
    $db->commit();
    
    // Clear session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Store deleted successfully!'
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>