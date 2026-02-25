<?php
// action/settings/update-policies.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Update Policies Started ===");

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
    
    // Get form data
    $store_policy = trim($_POST['store_policy'] ?? '');
    $return_policy = trim($_POST['return_policy'] ?? '');
    $shipping_policy = trim($_POST['shipping_policy'] ?? '');
    $payment_methods = isset($_POST['payment_methods']) ? json_encode($_POST['payment_methods']) : json_encode([]);
    
    error_log("Payment methods: " . $payment_methods);
    
    $db->beginTransaction();
    
    // Check if vendor_settings exists
    $stmt = $db->prepare("SELECT vendor_id FROM vendor_settings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    
    if ($stmt->fetch()) {
        // Update
        $sql = "UPDATE vendor_settings SET 
                store_policy = ?, return_policy = ?, 
                shipping_policy = ?, payment_methods = ?,
                updated_at = NOW()
                WHERE vendor_id = ?";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $store_policy, $return_policy,
            $shipping_policy, $payment_methods,
            $vendor_id
        ]);
    } else {
        // Insert
        $sql = "INSERT INTO vendor_settings 
                (vendor_id, store_policy, return_policy, shipping_policy,
                 payment_methods, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $vendor_id, $store_policy, $return_policy,
            $shipping_policy, $payment_methods
        ]);
    }
    
    if (!$result) {
        throw new Exception('Failed to update policies');
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'update_policies', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Updated store policies", $ip, $ua]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Store policies updated successfully!'
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>