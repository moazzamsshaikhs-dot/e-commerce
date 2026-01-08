<?php
// admin/real-time-data.php
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
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = getDB();
    
    // Current time
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    // Active users (last 15 minutes)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as active_users FROM user_sessions 
                         WHERE is_active = 1 
                         AND login_time >= ?");
    $stmt->execute([$last_hour]);
    $active_users = $stmt->fetchColumn();
    
    // Orders today
    $stmt = $db->prepare("SELECT COUNT(*) as orders_today FROM orders 
                         WHERE order_date BETWEEN ? AND ? 
                         AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$today_start, $today_end]);
    $orders_today = $stmt->fetchColumn();
    
    // Revenue today
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue_today FROM orders 
                         WHERE order_date BETWEEN ? AND ? 
                         AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$today_start, $today_end]);
    $revenue_today = $stmt->fetchColumn();
    
    // New customers today
    $stmt = $db->prepare("SELECT COUNT(*) as new_customers FROM users 
                         WHERE created_at BETWEEN ? AND ? 
                         AND user_type = 'user'");
    $stmt->execute([$today_start, $today_end]);
    $new_customers = $stmt->fetchColumn();
    
    // Pending orders
    $stmt = $db->prepare("SELECT COUNT(*) as pending_orders FROM orders 
                         WHERE status = 'pending'");
    $stmt->execute();
    $pending_orders = $stmt->fetchColumn();
    
    // Low stock products
    $stmt = $db->prepare("SELECT COUNT(*) as low_stock FROM products 
                         WHERE stock > 0 AND stock < 10");
    $stmt->execute();
    $low_stock = $stmt->fetchColumn();
    
    // Today's sessions
    $stmt = $db->prepare("SELECT COUNT(DISTINCT ip_address) as today_sessions FROM user_sessions 
                         WHERE login_time BETWEEN ? AND ?");
    $stmt->execute([$today_start, $today_end]);
    $today_sessions = $stmt->fetchColumn();
    
    // Current hour performance
    $current_hour = date('H');
    $hour_start = date('Y-m-d ' . $current_hour . ':00:00');
    $hour_end = date('Y-m-d ' . $current_hour . ':59:59');
    
    $stmt = $db->prepare("SELECT 
                          COUNT(*) as hour_orders,
                          COALESCE(SUM(total_amount), 0) as hour_revenue
                          FROM orders 
                          WHERE order_date BETWEEN ? AND ? 
                          AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$hour_start, $hour_end]);
    $hour_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent activities
    $stmt = $db->prepare("SELECT 
                          activity_type,
                          description,
                          created_at
                          FROM user_activities 
                          ORDER BY created_at DESC 
                          LIMIT 5");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Response data
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'active_users' => (int)$active_users,
            'orders_today' => (int)$orders_today,
            'revenue_today' => (float)$revenue_today,
            'new_customers' => (int)$new_customers,
            'pending_orders' => (int)$pending_orders,
            'low_stock' => (int)$low_stock,
            'today_sessions' => (int)$today_sessions,
            'hour_performance' => $hour_data,
            'recent_activities' => $recent_activities
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>