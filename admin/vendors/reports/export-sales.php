<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    die('Access denied.');
}

$vendor_id = $_SESSION['user_id'];

// Get export parameters
$format = $_POST['format'] ?? 'csv';
$report_type = $_POST['report_type'] ?? 'summary';
$include_charts = isset($_POST['include_charts']) && $_POST['include_charts'] == 'on';
$include_details = isset($_POST['include_details']) && $_POST['include_details'] == 'on';

// Get filter parameters
$filter_start = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$filter_end = $_POST['end_date'] ?? date('Y-m-d');
$filter_product = $_POST['product_id'] ?? '';
$filter_status = $_POST['order_status'] ?? '';

try {
    $db = getDB();
    
    // Get vendor info
    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Build query conditions
    $conditions = ["p.vendor_id = ?"];
    $params = [$vendor_id];
    
    if (!empty($filter_start) && !empty($filter_end)) {
        $conditions[] = "DATE(o.order_date) BETWEEN ? AND ?";
        $params[] = $filter_start;
        $params[] = $filter_end;
    }
    
    if (!empty($filter_product)) {
        $conditions[] = "p.id = ?";
        $params[] = $filter_product;
    }
    
    if (!empty($filter_status)) {
        $conditions[] = "o.status = ?";
        $params[] = $filter_status;
    }
    
    $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    if ($format === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sales-report-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($report_type === 'summary') {
            // Summary report
            fputcsv($output, ['Sales Summary Report']);
            fputcsv($output, ['Vendor:', $vendor['full_name']]);
            fputcsv($output, ['Period:', $filter_start . ' to ' . $filter_end]);
            fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
            fputcsv($output, []); // Empty row
            
            // Get summary data
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COALESCE(SUM(o.total_amount), 0) as total_sales,
                    COALESCE(SUM(oi.quantity), 0) as total_items,
                    COUNT(DISTINCT o.user_id) as total_customers,
                    AVG(o.total_amount) as avg_order_value
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                $where_clause
            ");
            $stmt->execute($params);
            $summary = $stmt->fetch();
            
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Total Orders', $summary['total_orders']]);
            fputcsv($output, ['Total Sales', '$' . number_format($summary['total_sales'], 2)]);
            fputcsv($output, ['Total Items Sold', $summary['total_items']]);
            fputcsv($output, ['Total Customers', $summary['total_customers']]);
            fputcsv($output, ['Average Order Value', '$' . number_format($summary['avg_order_value'], 2)]);
            
        } elseif ($report_type === 'detailed') {
            // Detailed report
            fputcsv($output, ['Detailed Sales Report']);
            fputcsv($output, ['Vendor:', $vendor['full_name']]);
            fputcsv($output, ['Period:', $filter_start . ' to ' . $filter_end]);
            fputcsv($output, []); // Empty row
            
            // Get detailed data
            $stmt = $db->prepare("
                SELECT 
                    o.order_date,
                    o.order_number,
                    u.full_name as customer,
                    p.name as product,
                    oi.quantity,
                    oi.unit_price,
                    oi.subtotal,
                    o.status,
                    o.payment_method,
                    o.payment_status
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN users u ON o.user_id = u.id
                $where_clause
                ORDER BY o.order_date DESC
            ");
            $stmt->execute($params);
            $sales = $stmt->fetchAll();
            
            fputcsv($output, ['Date', 'Order #', 'Customer', 'Product', 'Quantity', 'Unit Price', 'Subtotal', 'Status', 'Payment Method', 'Payment Status']);
            
            foreach($sales as $sale) {
                fputcsv($output, [
                    $sale['order_date'],
                    $sale['order_number'],
                    $sale['customer'],
                    $sale['product'],
                    $sale['quantity'],
                    '$' . number_format($sale['unit_price'], 2),
                    '$' . number_format($sale['subtotal'], 2),
                    $sale['status'],
                    $sale['payment_method'],
                    $sale['payment_status']
                ]);
            }
            
        } elseif ($report_type === 'products') {
            // Product-wise report
            fputcsv($output, ['Product Sales Report']);
            fputcsv($output, ['Vendor:', $vendor['full_name']]);
            fputcsv($output, ['Period:', $filter_start . ' to ' . $filter_end]);
            fputcsv($output, []); // Empty row
            
            $stmt = $db->prepare("
                SELECT 
                    p.name,
                    p.category,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.subtotal) as total_revenue,
                    AVG(p.price) as avg_price
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id
                WHERE p.vendor_id = ?
                " . (!empty($filter_start) && !empty($filter_end) ? "AND DATE(o.order_date) BETWEEN ? AND ?" : "") . "
                GROUP BY p.id
                ORDER BY total_revenue DESC
            ");
            
            $product_params = [$vendor_id];
            if (!empty($filter_start) && !empty($filter_end)) {
                $product_params[] = $filter_start;
                $product_params[] = $filter_end;
            }
            $stmt->execute($product_params);
            $products = $stmt->fetchAll();
            
            fputcsv($output, ['Product Name', 'Category', 'Orders', 'Quantity Sold', 'Total Revenue', 'Average Price']);
            
            foreach($products as $product) {
                fputcsv($output, [
                    $product['name'],
                    $product['category'],
                    $product['order_count'],
                    $product['total_quantity'],
                    '$' . number_format($product['total_revenue'], 2),
                    '$' . number_format($product['avg_price'], 2)
                ]);
            }
        }
        
        fclose($output);
        
    } elseif ($format === 'pdf') {
        // For PDF export, you would need a library like TCPDF or Dompdf
        // This is a basic implementation
        
        $_SESSION['info'] = 'PDF export coming soon. For now, please use CSV format.';
        redirect('sales.php');
        
    } else {
        $_SESSION['error'] = 'Unsupported export format';
        redirect('sales.php');
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Export failed: ' . $e->getMessage();
    redirect('sales.php');
}
?>