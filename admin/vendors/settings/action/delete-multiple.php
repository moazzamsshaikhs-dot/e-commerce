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
        $input = json_decode(file_get_contents('php://input'), true);
        $notification_ids = $input['notification_ids'] ?? [];
        
        if (empty($notification_ids)) {
            throw new Exception('No notifications selected');
        }
        
        // Validate and sanitize IDs
        $valid_ids = array_filter($notification_ids, function($id) {
            return is_numeric($id) && $id > 0;
        });
        
        if (empty($valid_ids)) {
            throw new Exception('Invalid notification IDs');
        }
        
        $db = getDB();
        
        // First, archive the notifications
        $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
        $params = $valid_ids;
        array_unshift($params, $vendor_id);
        
        $stmt = $db->prepare("
            INSERT INTO notifications_archive 
            (original_id, user_id, title, message, type, created_at, deleted_at)
            SELECT id, user_id, title, message, type, created_at, NOW()
            FROM notifications 
            WHERE user_id = ? AND id IN ($placeholders)
        ");
        $stmt->execute($params);
        
        // Now delete the notifications
        $stmt = $db->prepare("
            DELETE FROM notifications 
            WHERE user_id = ? AND id IN ($placeholders)
        ");
        $stmt->execute($params);
        
        $deleted_count = $stmt->rowCount();
        
        // Log activity
        logVendorActivity($vendor_id, 'delete_multiple_notifications', "Deleted $deleted_count notifications");
        
        echo json_encode([
            'success' => true,
            'message' => "$deleted_count notification(s) deleted successfully",
            'count' => $deleted_count
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