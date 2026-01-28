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
        
        // Get current profile picture
        $stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $stmt->execute([$vendor_id]);
        $vendor = $stmt->fetch();
        
        if (!$vendor) {
            throw new Exception('Vendor not found');
        }
        
        $current_avatar = $vendor['profile_pic'];
        
        // Don't delete if it's already default
        if ($current_avatar === 'default.png') {
            echo json_encode([
                'success' => true,
                'message' => 'Profile picture is already default'
            ]);
            exit;
        }
        
        // Delete the file from server
        $avatar_path = SITE_URL . 'assets/images/avatars/' . $current_avatar;
        
        if (file_exists($avatar_path)) {
            if (!unlink($avatar_path)) {
                error_log("Failed to delete avatar file: $avatar_path");
            }
        }
        
        // Update database to default
        $stmt = $db->prepare("UPDATE users SET profile_pic = 'default.png', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$vendor_id]);
        
        // Update session
        $_SESSION['profile_pic'] = 'default.png';
        
        // Log activity
        logVendorActivity($vendor_id, 'delete_avatar', 'Deleted profile picture');
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture removed successfully'
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