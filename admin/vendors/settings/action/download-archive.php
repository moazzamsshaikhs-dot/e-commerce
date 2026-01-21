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
    // In production, you would create a ZIP file with all notifications
    // This is a simplified version that returns a CSV
    
    $db = getDB();
    
    // Get all notifications including archived
    $stmt = $db->prepare("
        SELECT 
            'active' as source,
            id,
            title,
            message,
            type,
            is_read,
            created_at,
            NULL as deleted_at
        FROM notifications 
        WHERE user_id = ?
        
        UNION ALL
        
        SELECT 
            'archive' as source,
            original_id as id,
            title,
            message,
            type,
            1 as is_read,
            created_at,
            deleted_at
        FROM notifications_archive 
        WHERE user_id = ?
        
        ORDER BY created_at DESC
    ");
    $stmt->execute([$vendor_id, $vendor_id]);
    $all_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create CSV content
    $csv_data = "Source,ID,Title,Message,Type,Status,Created Date,Created Time,Deleted Date\n";
    
    foreach($all_notifications as $notification) {
        $csv_data .= sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
            $notification['source'],
            $notification['id'],
            str_replace('"', '""', $notification['title']),
            str_replace('"', '""', strip_tags($notification['message'])),
            ucfirst($notification['type']),
            $notification['is_read'] ? 'Read' : 'Unread',
            date('Y-m-d', strtotime($notification['created_at'])),
            date('H:i:s', strtotime($notification['created_at'])),
            $notification['deleted_at'] ? date('Y-m-d H:i:s', strtotime($notification['deleted_at'])) : ''
        );
    }
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=notifications-archive-' . date('Y-m-d') . '.csv');
    header('Content-Length: ' . strlen($csv_data));
    
    echo $csv_data;
    
    // Log activity
    logVendorActivity($vendor_id, 'download_notifications_archive', 'Downloaded notifications archive');
    
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