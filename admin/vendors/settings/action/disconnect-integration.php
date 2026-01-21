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
        
        $integration_id = intval($_POST['integration_id'] ?? 0);
        
        if ($integration_id <= 0) {
            throw new Exception('Invalid integration ID');
        }
        
        // Verify integration belongs to vendor
        $stmt = $db->prepare("SELECT integration_name FROM vendor_integrations WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$integration_id, $vendor_id]);
        $integration = $stmt->fetch();
        
        if (!$integration) {
            throw new Exception('Integration not found');
        }
        
        // Deactivate integration instead of deleting to keep logs
        $stmt = $db->prepare("
            UPDATE vendor_integrations 
            SET is_active = 0,
                disconnected_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([$integration_id, $vendor_id]);
        
        // Log the disconnection
        logVendorActivity($vendor_id, 'disconnect_integration', "Disconnected integration #$integration_id - {$integration['integration_name']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Integration disconnected successfully'
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