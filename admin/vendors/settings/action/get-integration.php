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
$integration_id = intval($_GET['id'] ?? 0);

if ($integration_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid integration ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get integration details with vendor verification
    $stmt = $db->prepare("
        SELECT 
            vi.*,
            u.username as vendor_username,
            u.full_name as vendor_name
        FROM vendor_integrations vi
        JOIN users u ON vi.vendor_id = u.id
        WHERE vi.id = ? AND vi.vendor_id = ?
    ");
    $stmt->execute([$integration_id, $vendor_id]);
    $integration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$integration) {
        echo json_encode(['success' => false, 'message' => 'Integration not found']);
        exit;
    }
    
    // Parse config
    $integration['config_parsed'] = json_decode($integration['config'] ?? '{}', true);
    
    // Hide sensitive data
    if (isset($integration['config_parsed']['api_key'])) {
        $integration['config_parsed']['api_key_masked'] = substr($integration['config_parsed']['api_key'], 0, 8) . '...';
    }
    if (isset($integration['config_parsed']['api_secret'])) {
        $integration['config_parsed']['api_secret_masked'] = '********';
    }
    
    // Format dates
    $integration['created_at_formatted'] = date('d M Y, h:i A', strtotime($integration['created_at']));
    $integration['last_sync_formatted'] = $integration['last_sync'] 
        ? date('d M Y, h:i A', strtotime($integration['last_sync'])) 
        : 'Never';
    
    // Get sync statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_syncs,
            COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_syncs,
            COUNT(CASE WHEN status = 'error' THEN 1 END) as failed_syncs,
            AVG(duration_ms) as avg_sync_duration
        FROM vendor_integration_logs 
        WHERE integration_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$integration_id]);
    $sync_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent syncs
    $stmt = $db->prepare("
        SELECT action, status, message, duration_ms, created_at 
        FROM vendor_integration_logs 
        WHERE integration_id = ?
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$integration_id]);
    $recent_syncs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format syncs
    foreach($recent_syncs as &$sync) {
        $sync['created_at_formatted'] = date('d M, H:i', strtotime($sync['created_at']));
        $sync['status_badge'] = $sync['status'] === 'success' 
            ? '<span class="badge bg-success">Success</span>' 
            : '<span class="badge bg-danger">Error</span>';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $integration,
        'sync_stats' => $sync_stats,
        'recent_syncs' => $recent_syncs
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>