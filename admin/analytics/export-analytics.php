<?php
// admin/export-analytics.php
session_start();

require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}

// Get export parameters
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$type = isset($_GET['type']) ? $_GET['type'] : 'summary';

try {
    $db = getDB();
    
    // Fetch analytics data based on type
    switch($type) {
        case 'revenue':
            $data = exportRevenueData($db, $start_date, $end_date);
            $filename = "revenue_report_{$start_date}_to_{$end_date}";
            break;
        case 'products':
            $data = exportProductsData($db, $start_date, $end_date);
            $filename = "products_report_{$start_date}_to_{$end_date}";
            break;
        case 'customers':
            $data = exportCustomersData($db, $start_date, $end_date);
            $filename = "customers_report_{$start_date}_to_{$end_date}";
            break;
        case 'traffic':
            $data = exportTrafficData($db, $start_date, $end_date);
            $filename = "traffic_report_{$start_date}_to_{$end_date}";
            break;
        default: // summary
            $data = exportSummaryData($db, $start_date, $end_date);
            $filename = "analytics_summary_{$start_date}_to_{$end_date}";
    }
    
    // Generate export based on format
    switch($format) {
        case 'excel':
            exportExcel($data, $filename);
            break;
        case 'csv':
            exportCSV($data, $filename);
            break;
        case 'pdf':
        default:
            exportPDF($data, $filename);
            break;
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error generating export: ' . $e->getMessage();
    header('Location: analytics-dashboard.php');
    exit();
}

// Data export functions
function exportSummaryData($db, $start_date, $end_date) {
    $data = [];
    
    // Overview metrics
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COUNT(DISTINCT user_id) as total_customers,
        AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['overview'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Daily trend
    $stmt = $db->prepare("SELECT 
        DATE(order_date) as date,
        COUNT(*) as orders,
        SUM(total_amount) as revenue,
        COUNT(DISTINCT user_id) as customers
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
        GROUP BY DATE(order_date)
        ORDER BY date");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['daily_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top products
    $stmt = $db->prepare("SELECT 
        p.name,
        p.category,
        SUM(oi.quantity) as units_sold,
        SUM(oi.quantity * oi.unit_price) as revenue,
        COUNT(DISTINCT o.id) as orders
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.id, p.name, p.category
        ORDER BY revenue DESC
        LIMIT 10");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top customers
    $stmt = $db->prepare("SELECT 
        u.full_name,
        u.email,
        COUNT(o.id) as order_count,
        SUM(o.total_amount) as total_spent,
        MAX(o.order_date) as last_order
        FROM users u
        JOIN orders o ON u.id = o.user_id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY u.id, u.full_name, u.email
        ORDER BY total_spent DESC
        LIMIT 10");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Export metadata
    $data['metadata'] = [
        'report_type' => 'summary',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s'),
        'generated_by' => $_SESSION['user_id']
    ];
    
    return $data;
}

function exportRevenueData($db, $start_date, $end_date) {
    $data = [];
    
    // Revenue by date
    $stmt = $db->prepare("SELECT 
        DATE(order_date) as date,
        COUNT(*) as orders,
        SUM(total_amount) as revenue,
        AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
        GROUP BY DATE(order_date)
        ORDER BY date");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['revenue_by_date'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revenue by category
    $stmt = $db->prepare("SELECT 
        p.category,
        COUNT(DISTINCT o.id) as orders,
        SUM(o.total_amount) as revenue,
        SUM(oi.quantity) as units_sold
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.category
        ORDER BY revenue DESC");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['revenue_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revenue by payment method
    $stmt = $db->prepare("SELECT 
        payment_method,
        COUNT(*) as orders,
        SUM(total_amount) as revenue,
        AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')
        GROUP BY payment_method
        ORDER BY revenue DESC");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['revenue_by_payment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data['metadata'] = [
        'report_type' => 'revenue',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

function exportProductsData($db, $start_date, $end_date) {
    $data = [];
    
    // All products performance
    $stmt = $db->prepare("SELECT 
        p.id,
        p.name,
        p.category,
        p.price,
        p.stock,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue,
        COUNT(DISTINCT o.id) as orders
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.order_date BETWEEN ? AND ? AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY p.id, p.name, p.category, p.price, p.stock
        ORDER BY revenue DESC");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stock status
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock > 0 AND stock < 10 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock >= 10 THEN 1 ELSE 0 END) as in_stock
        FROM products");
    $stmt->execute();
    $data['stock_status'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $data['metadata'] = [
        'report_type' => 'products',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

function exportCustomersData($db, $start_date, $end_date) {
    $data = [];
    
    // Customer segmentation
    $stmt = $db->prepare("SELECT 
        u.id,
        u.full_name,
        u.email,
        u.created_at,
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_spent,
        MAX(o.order_date) as last_order_date,
        DATEDIFF(NOW(), MAX(o.order_date)) as days_since_last_order
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id AND o.status NOT IN ('cancelled', 'failed')
        WHERE u.user_type = 'user'
        GROUP BY u.id, u.full_name, u.email, u.created_at
        ORDER BY total_spent DESC");
    $stmt->execute();
    $data['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Customer acquisition
    $stmt = $db->prepare("SELECT 
        DATE(created_at) as date,
        COUNT(*) as new_customers
        FROM users 
        WHERE user_type = 'user'
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['acquisition'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Repeat customer analysis
    $stmt = $db->prepare("SELECT 
        CASE 
            WHEN order_count = 1 THEN 'First-time'
            WHEN order_count BETWEEN 2 AND 5 THEN 'Repeat'
            ELSE 'Loyal'
        END as segment,
        COUNT(*) as customer_count,
        SUM(total_spent) as total_revenue,
        AVG(total_spent) as avg_spent
        FROM (
            SELECT 
                u.id,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_spent
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE o.order_date BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled', 'failed')
            GROUP BY u.id
        ) as customer_stats
        GROUP BY segment");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['segmentation'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data['metadata'] = [
        'report_type' => 'customers',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

function exportTrafficData($db, $start_date, $end_date) {
    $data = [];
    
    // Daily traffic
    $stmt = $db->prepare("SELECT 
        DATE(login_time) as date,
        COUNT(*) as sessions,
        COUNT(DISTINCT ip_address) as unique_visitors,
        AVG(TIMESTAMPDIFF(SECOND, login_time, COALESCE(logout_time, NOW()))) as avg_session_duration
        FROM user_sessions 
        WHERE login_time BETWEEN ? AND ?
        GROUP BY DATE(login_time)
        ORDER BY date");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['daily_traffic'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Device breakdown
    $stmt = $db->prepare("SELECT 
        CASE 
            WHEN user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%' OR user_agent LIKE '%iPhone%' THEN 'Mobile'
            WHEN user_agent LIKE '%Tablet%' OR user_agent LIKE '%iPad%' THEN 'Tablet'
            ELSE 'Desktop'
        END as device_type,
        COUNT(*) as sessions,
        COUNT(DISTINCT ip_address) as visitors
        FROM user_sessions 
        WHERE login_time BETWEEN ? AND ?
        GROUP BY device_type");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['device_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hourly traffic pattern
    $stmt = $db->prepare("SELECT 
        HOUR(login_time) as hour,
        COUNT(*) as sessions,
        COUNT(DISTINCT ip_address) as visitors
        FROM user_sessions 
        WHERE login_time BETWEEN ? AND ?
        GROUP BY HOUR(login_time)
        ORDER BY hour");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['hourly_pattern'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data['metadata'] = [
        'report_type' => 'traffic',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

// Export functions
function exportExcel($data, $filename) {
    // Simplified Excel export - in production, use a library like PhpSpreadsheet
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo "<table border='1'>";
    
    // Output metadata
    echo "<tr><th colspan='4'>Report Summary</th></tr>";
    foreach($data['metadata'] as $key => $value) {
        echo "<tr><td><strong>" . ucfirst(str_replace('_', ' ', $key)) . "</strong></td><td colspan='3'>$value</td></tr>";
    }
    
    // Output data tables
    foreach($data as $table => $rows) {
        if($table == 'metadata') continue;
        
        echo "<tr><th colspan='4' style='background-color: #f2f2f2;'>" . ucfirst(str_replace('_', ' ', $table)) . "</th></tr>";
        
        if(count($rows) > 0) {
            // Headers
            echo "<tr>";
            foreach(array_keys($rows[0]) as $header) {
                echo "<th>" . ucfirst(str_replace('_', ' ', $header)) . "</th>";
            }
            echo "</tr>";
            
            // Data rows
            foreach($rows as $row) {
                echo "<tr>";
                foreach($row as $cell) {
                    echo "<td>" . htmlspecialchars($cell) . "</td>";
                }
                echo "</tr>";
            }
        }
    }
    
    echo "</table>";
    exit();
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Output metadata
    fputcsv($output, ['Report Summary']);
    foreach($data['metadata'] as $key => $value) {
        fputcsv($output, [ucfirst(str_replace('_', ' ', $key)), $value]);
    }
    fputcsv($output, []); // Empty row
    
    // Output data tables
    foreach($data as $table => $rows) {
        if($table == 'metadata') continue;
        
        fputcsv($output, [ucfirst(str_replace('_', ' ', $table))]);
        
        if(count($rows) > 0) {
            // Headers
            fputcsv($output, array_keys($rows[0]));
            
            // Data rows
            foreach($rows as $row) {
                fputcsv($output, $row);
            }
        }
        
        fputcsv($output, []); // Empty row between tables
    }
    
    fclose($output);
    exit();
}

function exportPDF($data, $filename) {
    // Simplified PDF export - in production, use a library like TCPDF or Dompdf
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    
    $html = "<html><body>";
    $html .= "<h1>Analytics Report</h1>";
    
    foreach($data['metadata'] as $key => $value) {
        $html .= "<p><strong>" . ucfirst(str_replace('_', ' ', $key)) . ":</strong> $value</p>";
    }
    
    // Convert to PDF (simplified)
    // In production, use: require_once('tcpdf/tcpdf.php');
    echo $html . "</body></html>";
    exit();
}
?>