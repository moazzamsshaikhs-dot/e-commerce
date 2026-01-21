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
$log_id = intval($_GET['id'] ?? 0);

if ($log_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get log details with vendor verification
    $query = "
        SELECT 
            vil.*,
            vi.integration_name,
            vi.integration_type,
            u.username as vendor_username
        FROM vendor_integration_logs vil
        LEFT JOIN vendor_integrations vi ON vil.integration_id = vi.id
        JOIN users u ON vil.vendor_id = u.id
        WHERE vil.id = ? AND vil.vendor_id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$log_id, $vendor_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        // Try webhook logs
        $query = "
            SELECT 
                vwl.*,
                vw.webhook_name,
                u.username as vendor_username
            FROM vendor_webhook_logs vwl
            LEFT JOIN vendor_webhooks vw ON vwl.webhook_id = vw.id
            JOIN users u ON vwl.vendor_id = u.id
            WHERE vwl.id = ? AND vwl.vendor_id = ?
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$log_id, $vendor_id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$log) {
            // Try API logs
            $query = "
                SELECT 
                    val.*,
                    vak.key_name,
                    u.username as vendor_username
                FROM vendor_api_logs val
                LEFT JOIN vendor_api_keys vak ON val.api_key_id = vak.id
                JOIN users u ON val.vendor_id = u.id
                WHERE val.id = ? AND val.vendor_id = ?
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$log_id, $vendor_id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log not found']);
        exit;
    }
    
    // Format dates
    $log['created_at_formatted'] = date('d M Y, h:i:s A', strtotime($log['created_at']));
    
    // Parse JSON fields if they exist
    if (isset($log['payload']) && !empty($log['payload'])) {
        $log['payload_parsed'] = json_decode($log['payload'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log['payload_parsed'] = $log['payload'];
        }
    }
    
    if (isset($log['response']) && !empty($log['response'])) {
        $log['response_parsed'] = json_decode($log['response'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log['response_parsed'] = $log['response'];
        }
    }
    
    // Determine log type
    if (isset($log['integration_type'])) {
        $log['log_type'] = 'integration';
    } elseif (isset($log['webhook_name'])) {
        $log['log_type'] = 'webhook';
    } elseif (isset($log['key_name'])) {
        $log['log_type'] = 'api';
    } else {
        $log['log_type'] = 'unknown';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $log
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>