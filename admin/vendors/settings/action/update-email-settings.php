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
        
        $primary_email = filter_var($_POST['primary_email'] ?? '', FILTER_SANITIZE_EMAIL);
        $secondary_email = filter_var($_POST['secondary_email'] ?? '', FILTER_SANITIZE_EMAIL);
        $email_format = $_POST['email_format'] ?? 'html';
        $email_language = $_POST['email_language'] ?? 'en';
        $email_footer = trim($_POST['email_footer'] ?? '');
        
        // Validate primary email
        if (empty($primary_email) || !filter_var($primary_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid primary email address');
        }
        
        // Validate secondary email if provided
        if (!empty($secondary_email) && !filter_var($secondary_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid secondary email address');
        }
        
        // Update email settings in settings table
        $email_settings = [
            'primary_email' => $primary_email,
            'secondary_email' => $secondary_email,
            'email_format' => $email_format,
            'email_language' => $email_language,
            'email_footer' => $email_footer,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $db->prepare("
            INSERT INTO settings 
            (setting_key, setting_value, user_id, created_at)
            VALUES ('vendor_email_settings', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([
            json_encode($email_settings),
            $vendor_id,
            json_encode($email_settings)
        ]);
        
        // Also update user's email if primary email is different
        $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$vendor_id]);
        $current_email = $stmt->fetch()['email'];
        
        if ($primary_email !== $current_email) {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$primary_email, $vendor_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('Email address is already in use by another account');
            }
            
            // Update user email
            $stmt = $db->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$primary_email, $vendor_id]);
            
            // Update session
            $_SESSION['email'] = $primary_email;
        }
        
        // Log activity
        logVendorActivity($vendor_id, 'update_email_settings', 'Updated email notification settings');
        
        echo json_encode([
            'success' => true,
            'message' => 'Email settings updated successfully'
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