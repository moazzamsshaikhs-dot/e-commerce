<?php
// action/settings/deactivate-store.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Deactivate Store Started ===");

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
    
    // Update vendor status
    $stmt = $db->prepare("UPDATE users SET vendor_status = 'suspended', account_status = 'suspended' WHERE id = ?");
    $stmt->execute([$vendor_id]);
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'deactivate_store', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Deactivated store", $ip, $ua]);
    
    $db->commit();
    
    // Clear session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Store deactivated successfully!'
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>