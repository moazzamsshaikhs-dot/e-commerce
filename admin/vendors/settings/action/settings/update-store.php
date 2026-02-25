<?php
// action/settings/update-store.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Update Store Settings Started ===");
error_log("POST data: " . print_r($_POST, true));

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
    
    // Get business hours
    $business_hours = [];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    
    foreach ($days as $day) {
        $enabled = isset($_POST['business_hours'][$day]['enabled']);
        $open = $_POST['business_hours'][$day]['open'] ?? '';
        $close = $_POST['business_hours'][$day]['close'] ?? '';
        
        if ($enabled && !empty($open) && !empty($close)) {
            $business_hours[$day] = [
                'open' => $open,
                'close' => $close,
                'enabled' => true
            ];
        } else {
            $business_hours[$day] = [
                'open' => '',
                'close' => '',
                'enabled' => false
            ];
        }
    }
    
    $business_hours_json = json_encode($business_hours);
    
    // Get other settings
    $store_address = trim($_POST['store_address'] ?? '');
    $min_order_amount = floatval($_POST['min_order_amount'] ?? 0);
    $free_shipping_threshold = floatval($_POST['free_shipping_threshold'] ?? 0);
    $low_stock_notify = isset($_POST['low_stock_notify']) ? 1 : 0;
    $auto_hide_out_of_stock = isset($_POST['auto_hide_out_of_stock']) ? 1 : 0;
    $allow_backorders = isset($_POST['allow_backorders']) ? 1 : 0;
    
    error_log("Form data: address=$store_address, min_order=$min_order_amount");
    
    $db->beginTransaction();
    
    // Check if vendor_settings exists
    $stmt = $db->prepare("SELECT vendor_id FROM vendor_settings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    
    if ($stmt->fetch()) {
        // Update
        $sql = "UPDATE vendor_settings SET 
                store_address = ?, business_hours = ?,
                min_order_amount = ?, free_shipping_threshold = ?,
                low_stock_notify = ?, auto_hide_out_of_stock = ?,
                allow_backorders = ?, updated_at = NOW()
                WHERE vendor_id = ?";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $store_address, $business_hours_json,
            $min_order_amount, $free_shipping_threshold,
            $low_stock_notify, $auto_hide_out_of_stock,
            $allow_backorders, $vendor_id
        ]);
    } else {
        // Insert
        $sql = "INSERT INTO vendor_settings 
                (vendor_id, store_address, business_hours, min_order_amount,
                 free_shipping_threshold, low_stock_notify, auto_hide_out_of_stock,
                 allow_backorders, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $vendor_id, $store_address, $business_hours_json,
            $min_order_amount, $free_shipping_threshold,
            $low_stock_notify, $auto_hide_out_of_stock,
            $allow_backorders
        ]);
    }
    
    if (!$result) {
        throw new Exception('Failed to update store settings');
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'update_store', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Updated store details", $ip, $ua]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Store settings updated successfully!'
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>