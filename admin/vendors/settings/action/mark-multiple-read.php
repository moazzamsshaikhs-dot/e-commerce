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
        
        // Mark selected notifications as read
        $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
        $params = $valid_ids;
        array_unshift($params, $vendor_id);
        
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND id IN ($placeholders)
        ");
        $stmt->execute($params);
        
        $updated_count = $stmt->rowCount();
        
        // Log activity
        logVendorActivity($vendor_id, 'mark_multiple_read', "Marked $updated_count notifications as read");
        
        echo json_encode([
            'success' => true,
            'message' => "$updated_count notification(s) marked as read",
            'count' => $updated_count
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