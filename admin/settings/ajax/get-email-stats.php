<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $db = getDB();
    
    // Get fresh statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_emails,
        COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as sent,
        COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END), 0) as bounced
        FROM email_logs";
    $stats = $db->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
    
    // Get today's emails
    $today_sql = "SELECT COUNT(*) as count FROM email_logs WHERE DATE(created_at) = CURDATE()";
    $today = $db->query($today_sql)->fetch(PDO::FETCH_ASSOC);
    
    // Get chart data
    $chart_sql = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
        FROM email_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC";
    $chart_data = $db->query($chart_sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'today' => $today['count'] ?? 0,
        'chart_data' => $chart_data
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}