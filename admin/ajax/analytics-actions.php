<?php
// admin/analytics-actions.php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    $db = getDB();
    
    switch($action) {
        case 'clear_data':
            clearOldData($db);
            break;
            
        case 'reset_analytics':
            resetAnalytics($db);
            break;
            
        case 'get_realtime_data':
            getRealtimeData($db);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function clearOldData($db) {
    $period = $_GET['period'] ?? '365';
    
    if($period == 'all') {
        // Clear all analytics-related data
        $tables = ['user_sessions', 'user_activities', 'login_attempts'];
        
        foreach($tables as $table) {
            $db->exec("TRUNCATE TABLE $table");
        }
        
        // Clear old orders data (keep recent 2 years)
        $cutoff_date = date('Y-m-d', strtotime('-2 years'));
        $stmt = $db->prepare("DELETE FROM orders WHERE order_date < ?");
        $stmt->execute([$cutoff_date]);
    } else {
        $cutoff_date = date('Y-m-d', strtotime("-$period days"));
        
        // Clear old sessions
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE login_time < ?");
        $stmt->execute([$cutoff_date]);
        
        // Clear old activities
        $stmt = $db->prepare("DELETE FROM user_activities WHERE created_at < ?");
        $stmt->execute([$cutoff_date]);
        
        // Clear old login attempts
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
        $stmt->execute([$cutoff_date]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Data cleared successfully']);
}

function resetAnalytics($db) {
    // This is a dangerous operation - should only be done during system reset
    $tables = ['user_sessions', 'user_activities', 'login_attempts'];
    
    foreach($tables as $table) {
        $db->exec("TRUNCATE TABLE $table");
    }
    
    // Reset user login counts
    $db->exec("UPDATE users SET login_count = 0, last_login = NULL");
    
    echo json_encode(['success' => true, 'message' => 'Analytics reset successfully']);
}

function getRealtimeData($db) {
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    // Current hour stats
    $current_hour = date('H');
    $hour_start = date('Y-m-d ' . $current_hour . ':00:00');
    $hour_end = date('Y-m-d ' . $current_hour . ':59:59');
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as orders,
            COALESCE(SUM(total_amount), 0) as revenue,
            COUNT(DISTINCT user_id) as customers
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
    ");
    $stmt->execute([$hour_start, $hour_end]);
    $hour_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Active users
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT user_id) as active_users 
        FROM user_sessions 
        WHERE is_active = 1
    ");
    $stmt->execute();
    $active_users = $stmt->fetchColumn();
    
    // Today's summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as orders_today,
            COALESCE(SUM(total_amount), 0) as revenue_today,
            COUNT(DISTINCT user_id) as customers_today
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
    ");
    $stmt->execute([$today_start, $today_end]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent orders
    $stmt = $db->prepare("
        SELECT 
            o.order_number,
            o.total_amount,
            o.status,
            u.full_name,
            TIMESTAMPDIFF(MINUTE, o.order_date, NOW()) as minutes_ago
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.order_date >= ?
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-1 hour'))]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('H:i:s'),
        'data' => [
            'hour_stats' => $hour_stats,
            'active_users' => $active_users,
            'today_stats' => $today_stats,
            'recent_orders' => $recent_orders
        ]
    ]);
}