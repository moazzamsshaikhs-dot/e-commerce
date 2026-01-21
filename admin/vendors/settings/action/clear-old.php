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
        
        // Get count before deletion
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$vendor_id]);
        $old_count = $stmt->fetch()['count'];
        
        if ($old_count == 0) {
            echo json_encode([
                'success' => true,
                'message' => 'No old notifications found',
                'count' => 0
            ]);
            exit;
        }
        
        // First, archive old notifications
        $stmt = $db->prepare("
            INSERT INTO notifications_archive 
            (original_id, user_id, title, message, type, created_at, deleted_at)
            SELECT id, user_id, title, message, type, created_at, NOW()
            FROM notifications 
            WHERE user_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$vendor_id]);
        
        // Now delete old notifications
        $stmt = $db->prepare("
            DELETE FROM notifications 
            WHERE user_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$vendor_id]);
        
        $deleted_count = $stmt->rowCount();
        
        // Log activity
        logVendorActivity($vendor_id, 'clear_old_notifications', "Cleared $deleted_count old notifications (older than 30 days)");
        
        echo json_encode([
            'success' => true,
            'message' => "$deleted_count old notification(s) cleared successfully",
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