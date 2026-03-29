<?php
// admin/analytics/export-analytics.php
session_start();

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

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

// Load DOMPDF for PDF format
if ($format === 'pdf') {
    // Get document root and base path
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    $base_path = rtrim(str_replace('/admin/analytics', '', __DIR__), '/');
    
    $possible_paths = [
        // Absolute path from document root
        $doc_root . '/vendor/dompdf/autoload.inc.php',
        $doc_root . '/vendor/autoload.php',
        // Path using SITE_URL (converted to filesystem path)
        $doc_root . parse_url(SITE_URL, PHP_URL_PATH) . 'vendor/dompdf/autoload.inc.php',
        $doc_root . parse_url(SITE_URL, PHP_URL_PATH) . 'vendor/autoload.php',
        // Relative paths from current file
        $base_path . '/vendor/dompdf/autoload.inc.php',
        $base_path . '/vendor/autoload.php',
        __DIR__ . '/../../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../../../vendor/autoload.php',
        // e-commerce specific path
        $doc_root . '/e-commerce/vendor/dompdf/autoload.inc.php',
        $doc_root . '/e-commerce/vendor/autoload.php',
        $doc_root . '/../vendor/dompdf/autoload.inc.php',
        $doc_root . '/../vendor/autoload.php'
    ];
    
    $dompdf_loaded = false;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $dompdf_loaded = true;
            break;
        }
    }
    
    if (!$dompdf_loaded) {
        die("DOMPDF library not found. Please install it via Composer. Checked paths: " . implode(', ', $possible_paths));
    }
}

// Use statements in GLOBAL SCOPE
use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $db = getDB();
    
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
        default:
            $data = exportSummaryData($db, $start_date, $end_date);
            $filename = "analytics_summary_{$start_date}_to_{$end_date}";
    }
    
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

// ==================== DATA EXPORT FUNCTIONS ====================

function exportSummaryData($db, $start_date, $end_date) {
    $data = [];
    
    // Overview metrics
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COUNT(DISTINCT user_id) as total_customers,
        COALESCE(AVG(total_amount), 0) as avg_order_value
        FROM orders 
        WHERE order_date BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'failed')");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['overview'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
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
    $data['daily_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Top products
    $stmt = $db->prepare("SELECT 
        p.name,
        p.category,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue,
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
    $data['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Top customers
    $stmt = $db->prepare("SELECT 
        u.full_name,
        u.email,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total_amount), 0) as total_spent,
        MAX(o.order_date) as last_order
        FROM users u
        JOIN orders o ON u.id = o.user_id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'failed')
        GROUP BY u.id, u.full_name, u.email
        ORDER BY total_spent DESC
        LIMIT 10");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
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
    $data['revenue_by_date'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
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
    $data['revenue_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
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
    $data['revenue_by_payment'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $data['metadata'] = [
        'report_type' => 'revenue',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

function exportProductsData($db, $start_date, $end_date) {
    $data = [];
    
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
    $data['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock > 0 AND stock < 10 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock >= 10 THEN 1 ELSE 0 END) as in_stock
        FROM products");
    $stmt->execute();
    $data['stock_status'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $data['metadata'] = [
        'report_type' => 'products',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

function exportCustomersData($db, $start_date, $end_date) {
    $data = [];
    
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
    $data['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $db->prepare("SELECT 
        DATE(created_at) as date,
        COUNT(*) as new_customers
        FROM users 
        WHERE user_type = 'user'
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['acquisition'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
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
                COALESCE(SUM(o.total_amount), 0) as total_spent
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE o.order_date BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled', 'failed')
            GROUP BY u.id
        ) as customer_stats
        GROUP BY segment");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['segmentation'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $data['metadata'] = [
        'report_type' => 'customers',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

function exportTrafficData($db, $start_date, $end_date) {
    $data = [];
    
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
    $data['daily_traffic'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
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
    $data['device_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $db->prepare("SELECT 
        HOUR(login_time) as hour,
        COUNT(*) as sessions,
        COUNT(DISTINCT ip_address) as visitors
        FROM user_sessions 
        WHERE login_time BETWEEN ? AND ?
        GROUP BY HOUR(login_time)
        ORDER BY hour");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data['hourly_pattern'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $data['metadata'] = [
        'report_type' => 'traffic',
        'date_range' => $start_date . ' to ' . $end_date,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    return $data;
}

// ==================== EXPORT FUNCTIONS ====================

function exportExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    
    // Title
    echo "<tr><th colspan='4' style='background-color: #4361ee; color: white; font-size: 16px;'>Analytics Report</th></tr>";
    
    // Metadata section
    echo "<tr><th colspan='4' style='background-color: #f2f2f2;'>Report Summary</th></tr>";
    foreach($data['metadata'] as $key => $value) {
        echo "<tr>";
        echo "<td><strong>" . ucfirst(str_replace('_', ' ', $key)) . "</strong></td>";
        echo "<td colspan='3'>" . htmlspecialchars((string)$value) . "</td>";
        echo "</tr>";
    }
    
    // Data tables
    foreach($data as $table => $rows) {
        if($table == 'metadata') continue;
        
        echo "<tr><th colspan='4' style='background-color: #e9ecef;'>" . ucfirst(str_replace('_', ' ', $table)) . "</th></tr>";
        
        if(is_array($rows) && !empty($rows)) {
            // Headers
            $firstRow = $rows[0];
            if(is_array($firstRow) && !empty($firstRow)) {
                echo "<tr>";
                foreach(array_keys($firstRow) as $header) {
                    echo "<th style='background-color: #dee2e6;'>" . ucfirst(str_replace('_', ' ', $header)) . "</th>";
                }
                echo "</tr>";
            }
            
            // Data rows
            foreach($rows as $row) {
                if(is_array($row) && !empty($row)) {
                    echo "<tr>";
                    foreach($row as $cell) {
                        echo "<td>" . htmlspecialchars((string)$cell) . "</td>";
                    }
                    echo "</tr>";
                }
            }
        } else {
            echo "<tr><td colspan='4'>No data available</td></tr>";
        }
    }
    
    echo "</table></body></html>";
    exit();
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Title
    fputcsv($output, ['Analytics Report']);
    fputcsv($output, []);
    
    // Metadata section
    fputcsv($output, ['Report Summary']);
    foreach($data['metadata'] as $key => $value) {
        fputcsv($output, [ucfirst(str_replace('_', ' ', $key)), (string)$value]);
    }
    fputcsv($output, []);
    
    // Data tables
    foreach($data as $table => $rows) {
        if($table == 'metadata') continue;
        
        fputcsv($output, [ucfirst(str_replace('_', ' ', $table))]);
        
        if(is_array($rows) && !empty($rows)) {
            $firstRow = $rows[0];
            if(is_array($firstRow) && !empty($firstRow)) {
                // Headers
                fputcsv($output, array_keys($firstRow));
                
                // Data rows
                foreach($rows as $row) {
                    if(is_array($row)) {
                        fputcsv($output, $row);
                    }
                }
            }
        } else {
            fputcsv($output, ['No data available']);
        }
        fputcsv($output, []); // Empty row between tables
    }
    
    fclose($output);
    exit();
}

function exportPDF($data, $filename) {
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    $html = generatePDFHTML($data);
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    $dompdf->stream($filename . '.pdf', array('Attachment' => 1));
    exit();
}

function generatePDFHTML($data) {
    $company_name = getCompanyName();
    $current_time = date('F d, Y H:i:s');
    $report_type = ucfirst(str_replace('_', ' ', $data['metadata']['report_type'] ?? 'Analytics'));
    $date_range = $data['metadata']['date_range'] ?? 'N/A';
    
    $overview = $data['overview'] ?? [];
    $total_revenue = isset($overview['total_revenue']) ? number_format($overview['total_revenue'], 2) : 0;
    $total_orders = isset($overview['total_orders']) ? number_format($overview['total_orders']) : 0;
    $total_customers = isset($overview['total_customers']) ? number_format($overview['total_customers']) : 0;
    $avg_order_value = isset($overview['avg_order_value']) ? number_format($overview['avg_order_value'], 2) : 0;
    
    $html = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Analytics Report</title>
        <style>
            body { font-family: "DejaVu Sans", sans-serif; margin: 20px; font-size: 10px; }
            .header { text-align: center; border-bottom: 3px solid #4361ee; padding-bottom: 15px; margin-bottom: 20px; }
            .header h1 { color: #4361ee; font-size: 24px; margin: 0; }
            .header h2 { font-size: 18px; margin: 5px 0; color: #555; }
            .section-title { background: #4361ee; color: white; padding: 8px 12px; margin: 15px 0 10px; font-weight: bold; border-radius: 4px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th { background: #e9ecef; padding: 8px; border: 1px solid #dee2e6; text-align: left; font-weight: bold; }
            td { padding: 6px; border: 1px solid #dee2e6; }
            .overview-cards { display: flex; gap: 10px; margin-bottom: 20px; }
            .overview-card { flex: 1; background: #f8f9fa; padding: 10px; text-align: center; border: 1px solid #e9ecef; border-radius: 5px; }
            .overview-card .value { font-size: 18px; font-weight: bold; color: #4361ee; }
            .overview-card .label { font-size: 9px; color: #6c757d; margin-top: 4px; }
            .footer { text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 9px; color: #666; }
            .text-danger { color: #ef476f; }
            .text-warning { color: #ffb703; }
            .text-success { color: #06d6a0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . htmlspecialchars($company_name) . '</h1>
            <h2>Analytics Report - ' . htmlspecialchars($report_type) . '</h2>
            <p>Generated: ' . htmlspecialchars($current_time) . ' | Date Range: ' . htmlspecialchars($date_range) . '</p>
        </div>
        
        <div class="overview-cards">
            <div class="overview-card"><div class="value">$' . $total_revenue . '</div><div class="label">Total Revenue</div></div>
            <div class="overview-card"><div class="value">' . $total_orders . '</div><div class="label">Total Orders</div></div>
            <div class="overview-card"><div class="value">' . $total_customers . '</div><div class="label">Total Customers</div></div>
            <div class="overview-card"><div class="value">$' . $avg_order_value . '</div><div class="label">Avg Order Value</div></div>
        </div>';
    
    // Daily Trend
    if (!empty($data['daily_trend'])) {
        $html .= '<div class="section-title">Daily Performance Trend</div>
        <table>
            <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Customers</th></tr></thead><tbody>';
        foreach($data['daily_trend'] as $row) {
            $html .= '<tr>
                <td>' . date('M d, Y', strtotime($row['date'])) . '</td>
                <td>' . number_format($row['orders']) . '</td>
                <td>$' . number_format($row['revenue'], 2) . '</td>
                <td>' . number_format($row['customers']) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';
    }
    
    // Revenue by Category
    if (!empty($data['revenue_by_category'])) {
        $html .= '<div class="section-title">Revenue by Category</div>
        <table>
            <thead><tr><th>Category</th><th>Orders</th><th>Revenue</th><th>Units Sold</th></tr></thead><tbody>';
        foreach($data['revenue_by_category'] as $row) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['category']) . '</td>
                <td>' . number_format($row['orders']) . '</td>
                <td>$' . number_format($row['revenue'], 2) . '</td>
                <td>' . number_format($row['units_sold']) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';
    }
    
    // Top Products
    if (!empty($data['top_products'])) {
        $html .= '<div class="section-title">Top 10 Products</div>
        <table>
            <thead><tr><th>Product</th><th>Category</th><th>Units Sold</th><th>Revenue</th><th>Orders</th></tr></thead><tbody>';
        foreach($data['top_products'] as $row) {
            $product_name = substr($row['name'], 0, 40);
            if(strlen($row['name']) > 40) $product_name .= '...';
            $html .= '<tr>
                <td>' . htmlspecialchars($product_name) . '</td>
                <td>' . htmlspecialchars($row['category'] ?? 'N/A') . '</td>
                <td>' . number_format($row['units_sold']) . '</td>
                <td>$' . number_format($row['revenue'], 2) . '</td>
                <td>' . number_format($row['orders']) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';
    }
    
    // Products Performance
    if (!empty($data['products'])) {
        $html .= '<div class="section-title">Products Performance</div>
        <table>
            <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Units Sold</th><th>Revenue</th></tr></thead><tbody>';
        foreach($data['products'] as $row) {
            $stock_class = '';
            if($row['stock'] <= 0) $stock_class = 'text-danger';
            elseif($row['stock'] < 10) $stock_class = 'text-warning';
            else $stock_class = 'text-success';
            
            $product_name = substr($row['name'], 0, 40);
            if(strlen($row['name']) > 40) $product_name .= '...';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($product_name) . '</td>
                <td>' . htmlspecialchars($row['category'] ?? 'N/A') . '</td>
                <td>$' . number_format($row['price'], 2) . '</td>
                <td class="' . $stock_class . '">' . number_format($row['stock']) . '</td>
                <td>' . number_format($row['units_sold']) . '</td>
                <td>$' . number_format($row['revenue'], 2) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';
    }
    
    // Stock Status
    if (!empty($data['stock_status'])) {
        $stock = $data['stock_status'];
        $total = max($stock['total_products'], 1);
        $html .= '<div class="section-title">Stock Status</div>
        <table>
            <thead><tr><th>Status</th><th>Count</th><th>Percentage</th></tr></thead><tbody>
            <tr><td>In Stock</td><td>' . number_format($stock['in_stock']) . '</td><td>' . number_format(($stock['in_stock'] / $total) * 100, 1) . '%</td></tr>
            <tr><td>Low Stock</td><td>' . number_format($stock['low_stock']) . '</td><td>' . number_format(($stock['low_stock'] / $total) * 100, 1) . '%</td></tr>
            <tr><td>Out of Stock</td><td>' . number_format($stock['out_of_stock']) . '</td><td>' . number_format(($stock['out_of_stock'] / $total) * 100, 1) . '%</td></tr>
            </tbody>
        </table>';
    }
    
    // Customer Segmentation
    if (!empty($data['segmentation'])) {
        $html .= '<div class="section-title">Customer Segmentation</div>
        <table>
            <thead><tr><th>Segment</th><th>Customers</th><th>Revenue</th><th>Avg Spent</th></tr></thead><tbody>';
        foreach($data['segmentation'] as $row) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['segment']) . '</td>
                <td>' . number_format($row['customer_count']) . '</td>
                <td>$' . number_format($row['total_revenue'], 2) . '</td>
                <td>$' . number_format($row['avg_spent'], 2) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';
    }
    
    $html .= '<div class="footer">
        <p>Report generated by ' . htmlspecialchars($company_name) . ' on ' . htmlspecialchars($current_time) . '</p>
        <p>© ' . date('Y') . ' ' . htmlspecialchars($company_name) . ' - All Rights Reserved</p>
    </div>
    </body>
    </html>';
    
    return $html;
}

function getCompanyName() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : 'E-Commerce Store';
    } catch(Exception $e) {
        return 'E-Commerce Store';
    }
}
?>