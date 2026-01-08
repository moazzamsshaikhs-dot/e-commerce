<?php
// admin/includes/analytics-functions.php

function getAnalyticsOverview($db, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COUNT(DISTINCT user_id) as unique_customers,
            AVG(total_amount) as avg_order_value,
            MIN(order_date) as first_order,
            MAX(order_date) as last_order
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTopProducts($db, $start_date, $end_date, $limit = 10) {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.category,
            p.price,
            SUM(oi.quantity) as units_sold,
            SUM(oi.quantity * oi.unit_price) as revenue,
            COUNT(DISTINCT o.id) as order_count
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.id, p.name, p.category, p.price
        ORDER BY revenue DESC
        LIMIT ?
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59', $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSalesTrend($db, $period = 'daily', $limit = 30) {
    switch($period) {
        case 'weekly':
            $sql = "SELECT 
                    YEARWEEK(order_date) as period,
                    COUNT(*) as orders,
                    SUM(total_amount) as revenue
                FROM orders 
                WHERE status NOT IN ('cancelled', 'failed')
                GROUP BY YEARWEEK(order_date)
                ORDER BY period DESC
                LIMIT ?";
            break;
        case 'monthly':
            $sql = "SELECT 
                    DATE_FORMAT(order_date, '%Y-%m') as period,
                    COUNT(*) as orders,
                    SUM(total_amount) as revenue
                FROM orders 
                WHERE status NOT IN ('cancelled', 'failed')
                GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                ORDER BY period DESC
                LIMIT ?";
            break;
        case 'yearly':
            $sql = "SELECT 
                    YEAR(order_date) as period,
                    COUNT(*) as orders,
                    SUM(total_amount) as revenue
                FROM orders 
                WHERE status NOT IN ('cancelled', 'failed')
                GROUP BY YEAR(order_date)
                ORDER BY period DESC
                LIMIT ?";
            break;
        default: // daily
            $sql = "SELECT 
                    DATE(order_date) as period,
                    COUNT(*) as orders,
                    SUM(total_amount) as revenue
                FROM orders 
                WHERE status NOT IN ('cancelled', 'failed')
                GROUP BY DATE(order_date)
                ORDER BY period DESC
                LIMIT ?";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerSegmentation($db, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            segment,
            COUNT(*) as customer_count,
            SUM(total_spent) as total_revenue,
            AVG(total_spent) as avg_spent,
            AVG(order_count) as avg_orders
        FROM (
            SELECT 
                u.id,
                CASE 
                    WHEN COUNT(o.id) = 1 THEN 'First-time'
                    WHEN COUNT(o.id) BETWEEN 2 AND 5 THEN 'Repeat'
                    ELSE 'Loyal'
                END as segment,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_spent
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE o.order_date BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled', 'failed')
            GROUP BY u.id
        ) as customer_stats
        GROUP BY segment
        ORDER BY FIELD(segment, 'First-time', 'Repeat', 'Loyal')
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTrafficSources($db, $start_date, $end_date) {
    // This is a simplified version. In production, you would integrate with Google Analytics API
    $stmt = $db->prepare("
        SELECT 
            'Direct' as source,
            COUNT(*) as sessions,
            COUNT(DISTINCT ip_address) as visitors
        FROM user_sessions 
        WHERE login_time BETWEEN ? AND ?
        -- Add more sophisticated source detection logic here
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGeographicData($db, $start_date, $end_date, $limit = 10) {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(u.country, 'Unknown') as country,
            COALESCE(u.city, 'Unknown') as city,
            COUNT(DISTINCT o.id) as orders,
            SUM(o.total_amount) as revenue,
            COUNT(DISTINCT o.user_id) as customers
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY u.country, u.city
        ORDER BY revenue DESC
        LIMIT ?
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59', $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateConversionRate($db, $start_date, $end_date) {
    // Get total visitors (approximation)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ip_address) as visitors 
        FROM user_sessions 
        WHERE login_time BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $visitors = $stmt->fetchColumn();
    
    // Get total orders
    $stmt = $db->prepare("
        SELECT COUNT(*) as orders 
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $orders = $stmt->fetchColumn();
    
    return $visitors > 0 ? ($orders / $visitors) * 100 : 0;
}

function getHourlyPerformance($db, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            HOUR(order_date) as hour,
            COUNT(*) as orders,
            SUM(total_amount) as revenue,
            AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
        GROUP BY HOUR(order_date)
        ORDER BY hour
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCartAbandonmentRate($db, $start_date, $end_date) {
    // Get carts created
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT user_id) as carts 
        FROM cart_items 
        WHERE added_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $carts = $stmt->fetchColumn();
    
    // Get purchases completed
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT user_id) as purchases 
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $purchases = $stmt->fetchColumn();
    
    return $carts > 0 ? (($carts - $purchases) / $carts) * 100 : 0;
}
?>