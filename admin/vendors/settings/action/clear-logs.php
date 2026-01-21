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
        
        // Delete logs older than 30 days
        $stmt = $db->prepare("
            DELETE FROM vendor_integration_logs 
            WHERE vendor_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$vendor_id]);
        
        $deleted_rows = $stmt->rowCount();
        
        // Also delete old webhook logs
        $stmt = $db->prepare("
            DELETE FROM vendor_webhook_logs 
            WHERE vendor_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$vendor_id]);
        
        $webhook_deleted = $stmt->rowCount();
        
        // Also delete old API logs
        $stmt = $db->prepare("
            DELETE FROM vendor_api_logs 
            WHERE vendor_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$vendor_id]);
        
        $api_deleted = $stmt->rowCount();
        
        // Log the cleanup
        logVendorActivity($vendor_id, 'clear_logs', "Cleaned up old logs (Integration: $deleted_rows, Webhook: $webhook_deleted, API: $api_deleted)");
        
        echo json_encode([
            'success' => true,
            'message' => 'Logs cleared successfully',
            'data' => [
                'integration_logs_deleted' => $deleted_rows,
                'webhook_logs_deleted' => $webhook_deleted,
                'api_logs_deleted' => $api_deleted
            ]
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