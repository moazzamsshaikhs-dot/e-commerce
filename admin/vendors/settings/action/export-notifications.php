<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$vendor_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // Get all notifications for export
    $stmt = $db->prepare("
        SELECT 
            id,
            title,
            message,
            type,
            is_read,
            created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=notifications-' . date('Y-m-d') . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, "\xEF\xBB\xBF");
    
    // Add CSV headers
    fputcsv($output, [
        'ID',
        'Title',
        'Message',
        'Type',
        'Status',
        'Created Date',
        'Created Time'
    ]);
    
    // Add data rows
    foreach($notifications as $notification) {
        fputcsv($output, [
            $notification['id'],
            $notification['title'],
            strip_tags($notification['message']), // Remove HTML tags
            ucfirst($notification['type']),
            $notification['is_read'] ? 'Read' : 'Unread',
            date('Y-m-d', strtotime($notification['created_at'])),
            date('H:i:s', strtotime($notification['created_at']))
        ]);
    }
    
    fclose($output);
    
    // Log activity
    logVendorActivity($vendor_id, 'export_notifications', 'Exported notifications to CSV');
    
} catch(Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
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