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
        
        $type = $input['type'] ?? '';
        $status = $input['status'] ?? '';
        $from_date = $input['from_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $to_date = $input['to_date'] ?? date('Y-m-d');
        
        $db = getDB();
        
        // Build query with filters
        $query = "
            SELECT 
                vil.*,
                vi.integration_name,
                vi.integration_type
            FROM vendor_integration_logs vil
            LEFT JOIN vendor_integrations vi ON vil.integration_id = vi.id
            WHERE vil.vendor_id = ?
            AND DATE(vil.created_at) BETWEEN ? AND ?
        ";
        
        $params = [$vendor_id, $from_date, $to_date];
        
        if (!empty($type)) {
            $query .= " AND vil.integration_type = ?";
            $params[] = $type;
        }
        
        if (!empty($status)) {
            $query .= " AND vil.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY vil.created_at DESC LIMIT 100";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format logs
        foreach($logs as &$log) {
            $log['created_at_formatted'] = date('d M, H:i:s', strtotime($log['created_at']));
            
            $status_colors = [
                'success' => 'success',
                'error' => 'danger',
                'warning' => 'warning',
                'info' => 'info'
            ];
            
            $status_icons = [
                'success' => 'check-circle',
                'error' => 'times-circle',
                'warning' => 'exclamation-triangle',
                'info' => 'info-circle'
            ];
            
            $log['status_color'] = $status_colors[$log['status']] ?? 'secondary';
            $log['status_icon'] = $status_icons[$log['status']] ?? 'question-circle';
        }
        
        // Get statistics
        $stats_query = "
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as success,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as error,
                AVG(duration_ms) as avg_duration
            FROM vendor_integration_logs 
            WHERE vendor_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
        ";
        
        $stats_params = [$vendor_id, $from_date, $to_date];
        
        $stmt = $db->prepare($stats_query);
        $stmt->execute($stats_params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'stats' => $stats,
            'filters' => [
                'type' => $type,
                'status' => $status,
                'from_date' => $from_date,
                'to_date' => $to_date
            ]
        ]);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}
?>