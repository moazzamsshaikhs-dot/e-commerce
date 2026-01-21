<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $key_id = intval($_POST['key_id'] ?? 0);
        
        if ($key_id <= 0) {
            throw new Exception('Invalid API key ID');
        }
        
        // Verify key belongs to vendor
        $stmt = $db->prepare("SELECT key_name FROM vendor_api_keys WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$key_id, $vendor_id]);
        $key_data = $stmt->fetch();
        
        if (!$key_data) {
            throw new Exception('API key not found');
        }
        
        // Check if this is the last active key
        $stmt = $db->prepare("SELECT COUNT(*) as active_keys FROM vendor_api_keys WHERE vendor_id = ? AND is_active = 1 AND id != ?");
        $stmt->execute([$vendor_id, $key_id]);
        $active_keys = $stmt->fetch()['active_keys'];
        
        if ($active_keys == 0) {
            throw new Exception('Cannot revoke the last active API key. Generate a new key first.');
        }
        
        // Revoke the key
        $stmt = $db->prepare("
            UPDATE vendor_api_keys 
            SET is_active = 0,
                revoked_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([$key_id, $vendor_id]);
        
        // Log the revocation
        logVendorActivity($vendor_id, 'revoke_api_key', "Revoked API key #$key_id - {$key_data['key_name']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'API key revoked successfully'
        ]);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function logVendorActivity($vendor_id, $activity_type, $description) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_activities 
            (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $vendor_id,
            $activity_type,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch(Exception $e) {
        // Silently fail logging
    }
}
?>