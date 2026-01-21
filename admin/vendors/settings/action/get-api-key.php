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
$key_id = intval($_GET['id'] ?? 0);

if ($key_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid API key ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get API key details with vendor verification
    $stmt = $db->prepare("
        SELECT 
            vk.*,
            u.username as vendor_username,
            u.full_name as vendor_name
        FROM vendor_api_keys vk
        JOIN users u ON vk.vendor_id = u.id
        WHERE vk.id = ? AND vk.vendor_id = ?
    ");
    $stmt->execute([$key_id, $vendor_id]);
    $api_key = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$api_key) {
        echo json_encode(['success' => false, 'message' => 'API key not found']);
        exit;
    }
    
    // Parse permissions
    $api_key['permissions_list'] = json_decode($api_key['permissions'] ?? '[]', true);
    
    // Format dates
    $api_key['created_at_formatted'] = date('d M Y, h:i A', strtotime($api_key['created_at']));
    $api_key['last_used_formatted'] = $api_key['last_used'] 
        ? date('d M Y, h:i A', strtotime($api_key['last_used'])) 
        : 'Never';
    
    $api_key['expiry_date_formatted'] = $api_key['expiry_date'] 
        ? date('d M Y', strtotime($api_key['expiry_date'])) 
        : 'Never';
    
    // Check if expired
    $api_key['is_expired'] = $api_key['expiry_date'] && strtotime($api_key['expiry_date']) < time();
    
    // Get usage statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_requests,
            COUNT(DISTINCT DATE(created_at)) as days_used,
            COUNT(CASE WHEN status = 'success' THEN 1 END) as success_requests,
            COUNT(CASE WHEN status = 'error' THEN 1 END) as error_requests
        FROM vendor_api_logs 
        WHERE api_key_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$key_id]);
    $usage_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activities
    $stmt = $db->prepare("
        SELECT action, endpoint, status, created_at 
        FROM vendor_api_logs 
        WHERE api_key_id = ?
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$key_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format activities
    foreach($recent_activities as &$activity) {
        $activity['created_at_formatted'] = date('d M, H:i', strtotime($activity['created_at']));
        $activity['status_badge'] = $activity['status'] === 'success' 
            ? '<span class="badge bg-success">Success</span>' 
            : '<span class="badge bg-danger">Error</span>';
    }
    
    // Mask API key for display
    $api_key['api_key_masked'] = substr($api_key['api_key'], 0, 8) . '...' . substr($api_key['api_key'], -8);
    
    echo json_encode([
        'success' => true,
        'data' => $api_key,
        'usage_stats' => $usage_stats,
        'recent_activities' => $recent_activities
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>