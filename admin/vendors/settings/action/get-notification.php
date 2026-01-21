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
$notification_id = intval($_GET['id'] ?? 0);

if ($notification_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get notification details with vendor verification
    $stmt = $db->prepare("
        SELECT 
            n.*,
            u.username as user_username,
            u.full_name as user_name
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        WHERE n.id = ? AND n.user_id = ?
    ");
    $stmt->execute([$notification_id, $vendor_id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$notification) {
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
        exit;
    }
    
    // Format dates
    $notification['created_at_formatted'] = date('d M Y, h:i A', strtotime($notification['created_at']));
    
    // Add formatted type
    $notification['type_formatted'] = ucfirst($notification['type']);
    $notification['type_icon'] = getNotificationIcon($notification['type']);
    
    echo json_encode([
        'success' => true,
        'data' => $notification
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

function getNotificationIcon($type) {
    $icons = [
        'info' => 'info-circle',
        'success' => 'check-circle',
        'warning' => 'exclamation-triangle',
        'error' => 'times-circle'
    ];
    return $icons[$type] ?? 'bell';
}
?>