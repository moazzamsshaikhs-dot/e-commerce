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
        
        $notification_id = intval($_POST['notification_id'] ?? 0);
        
        if ($notification_id <= 0) {
            throw new Exception('Invalid notification ID');
        }
        
        // Verify notification belongs to vendor
        $stmt = $db->prepare("SELECT title, type FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $vendor_id]);
        $notification = $stmt->fetch();
        
        if (!$notification) {
            throw new Exception('Notification not found');
        }
        
        // Archive the notification before deletion
        $stmt = $db->prepare("
            INSERT INTO notifications_archive 
            (original_id, user_id, title, message, type, created_at, deleted_at)
            SELECT id, user_id, title, message, type, created_at, NOW()
            FROM notifications WHERE id = ?
        ");
        $stmt->execute([$notification_id]);
        
        // Delete the notification
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $vendor_id]);
        
        // Log activity
        logVendorActivity($vendor_id, 'delete_notification', "Deleted notification #$notification_id: {$notification['title']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification deleted successfully'
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