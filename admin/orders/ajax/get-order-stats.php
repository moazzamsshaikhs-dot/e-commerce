<?php
// admin/ajax/get-order-stats.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $db = getDB();

    // Get statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(total_amount) as total_sales,
            AVG(total_amount) as avg_order_value
        FROM orders
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent orders count
    $stmt = $db->query("
        SELECT COUNT(*) as recent_orders
        FROM orders
        WHERE DATE(order_date) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recent = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_orders' => $recent['recent_orders']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}