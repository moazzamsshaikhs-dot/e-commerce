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
        
        // First, archive all notifications
        $stmt = $db->prepare("
            INSERT INTO notifications_archive 
            (original_id, user_id, title, message, type, created_at, deleted_at)
            SELECT id, user_id, title, message, type, created_at, NOW()
            FROM notifications 
            WHERE user_id = ?
        ");
        $stmt->execute([$vendor_id]);
        
        // Get count before deletion
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
        $stmt->execute([$vendor_id]);
        $total_count = $stmt->fetch()['count'];
        
        // Now delete all notifications
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$vendor_id]);
        
        $deleted_count = $stmt->rowCount();
        
        // Log activity
        logVendorActivity($vendor_id, 'clear_all_notifications', "Cleared all $deleted_count notifications");
        
        echo json_encode([
            'success' => true,
            'message' => "All $deleted_count notification(s) cleared successfully",
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