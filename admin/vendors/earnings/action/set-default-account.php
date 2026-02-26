<?php
// admin/vendors/earnings/action/set-default.php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$vendor_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0 || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    if ($type == 'bank') {
        // Unset all bank defaults
        $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        
        // Set new default
        $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 1 WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
        
    } elseif ($type == 'paypal') {
        $stmt = $db->prepare("UPDATE vendor_paypal_accounts SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        
        $stmt = $db->prepare("UPDATE vendor_paypal_accounts SET is_default = 1 WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
        
    } elseif ($type == 'stripe') {
        $stmt = $db->prepare("UPDATE vendor_stripe_accounts SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        
        $stmt = $db->prepare("UPDATE vendor_stripe_accounts SET is_default = 1 WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
        
    } elseif ($type == 'easypaisa' || $type == 'jazzcash') {
        $stmt = $db->prepare("UPDATE vendor_mobile_accounts SET is_default = 0 WHERE vendor_id = ? AND account_type = ?");
        $stmt->execute([$vendor_id, $type]);
        
        $stmt = $db->prepare("UPDATE vendor_mobile_accounts SET is_default = 1 WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
        
    } elseif ($type == 'card') {
        $stmt = $db->prepare("UPDATE vendor_cards SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        
        $stmt = $db->prepare("UPDATE vendor_cards SET is_default = 1 WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) 
        VALUES (?, 'set_default', ?, ?, ?, NOW())
    ");
    $log->execute([$vendor_id, "Set {$type} account #{$id} as default", $ip, $ua]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Default method updated']);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Set default error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}