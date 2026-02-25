<?php
// action/settings/regenerate-api.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Regenerate API Key Started ===");

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
    
    // Generate new API key
    $api_key = 'sk_live_' . bin2hex(random_bytes(16));
    
    $db->beginTransaction();
    
    // Check if vendor_settings exists
    $stmt = $db->prepare("SELECT vendor_id FROM vendor_settings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    
    if ($stmt->fetch()) {
        $stmt = $db->prepare("UPDATE vendor_settings SET api_key = ?, updated_at = NOW() WHERE vendor_id = ?");
        $stmt->execute([$api_key, $vendor_id]);
    } else {
        $stmt = $db->prepare("INSERT INTO vendor_settings (vendor_id, api_key, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$vendor_id, $api_key]);
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'regenerate_api', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Regenerated API key", $ip, $ua]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'API key regenerated successfully!',
        'api_key' => $api_key
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>