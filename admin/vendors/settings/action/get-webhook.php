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
$webhook_id = intval($_GET['id'] ?? 0);

if ($webhook_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid webhook ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get webhook details with vendor verification
    $stmt = $db->prepare("
        SELECT 
            vw.*,
            u.username as vendor_username,
            u.full_name as vendor_name
        FROM vendor_webhooks vw
        JOIN users u ON vw.vendor_id = u.id
        WHERE vw.id = ? AND vw.vendor_id = ?
    ");
    $stmt->execute([$webhook_id, $vendor_id]);
    $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$webhook) {
        echo json_encode(['success' => false, 'message' => 'Webhook not found']);
        exit;
    }
    
    // Parse events
    $webhook['events_list'] = json_decode($webhook['events'] ?? '[]', true);
    
    // Format dates
    $webhook['created_at_formatted'] = date('d M Y, h:i A', strtotime($webhook['created_at']));
    $webhook['last_delivered_formatted'] = $webhook['last_delivered'] 
        ? date('d M Y, h:i A', strtotime($webhook['last_delivered'])) 
        : 'Never';
    
    // Get delivery statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_deliveries,
            COUNT(CASE WHEN success = 1 THEN 1 END) as successful_deliveries,
            COUNT(CASE WHEN success = 0 THEN 1 END) as failed_deliveries,
            AVG(response_time) as avg_response_time
        FROM vendor_webhook_logs 
        WHERE webhook_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$webhook_id]);
    $delivery_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent deliveries
    $stmt = $db->prepare("
        SELECT event, status_code, response_time, success, error_message, created_at 
        FROM vendor_webhook_logs 
        WHERE webhook_id = ?
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$webhook_id]);
    $recent_deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format deliveries
    foreach($recent_deliveries as &$delivery) {
        $delivery['created_at_formatted'] = date('d M, H:i', strtotime($delivery['created_at']));
        $delivery['status_badge'] = $delivery['success'] == 1 
            ? '<span class="badge bg-success">Success</span>' 
            : '<span class="badge bg-danger">Failed</span>';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $webhook,
        'delivery_stats' => $delivery_stats,
        'recent_deliveries' => $recent_deliveries
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>