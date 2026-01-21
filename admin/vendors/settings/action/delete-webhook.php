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
        
        $webhook_id = intval($_POST['webhook_id'] ?? 0);
        
        if ($webhook_id <= 0) {
            throw new Exception('Invalid webhook ID');
        }
        
        // Verify webhook belongs to vendor
        $stmt = $db->prepare("SELECT webhook_name FROM vendor_webhooks WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$webhook_id, $vendor_id]);
        $webhook = $stmt->fetch();
        
        if (!$webhook) {
            throw new Exception('Webhook not found');
        }
        
        // Delete webhook logs first (cascade)
        $stmt = $db->prepare("DELETE FROM vendor_webhook_logs WHERE webhook_id = ?");
        $stmt->execute([$webhook_id]);
        
        // Delete webhook
        $stmt = $db->prepare("DELETE FROM vendor_webhooks WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$webhook_id, $vendor_id]);
        
        // Log the deletion
        logVendorActivity($vendor_id, 'delete_webhook', "Deleted webhook #$webhook_id - {$webhook['webhook_name']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Webhook deleted successfully'
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