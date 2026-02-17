<?php
// admin/vendors/reports/export-sales.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// ============================================
// USE STATEMENTS - MUST BE AT TOP LEVEL
// ============================================
use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Vendor account not approved.';
        header('Location: ' . SITE_URL . 'admin/vendors/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
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

// Validate dates
if (strtotime($filter_start) > strtotime($filter_end)) {
    $_SESSION['error'] = 'Start date cannot be after end date.';
    header('Location: sales.php');
    exit();
}

try {
    $db = getDB();
    
    // Get vendor info
    $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        throw new Exception('Vendor not found');
    }
    
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
        exportCSV($db, $vendor, $where_clause, $params, $report_type, $filter_start, $filter_end);
    } elseif ($format === 'excel') {
        exportExcel($db, $vendor, $where_clause, $params, $report_type, $filter_start, $filter_end);
    } elseif ($format === 'pdf') {
        exportPDF($db, $vendor, $where_clause, $params, $report_type, $filter_start, $filter_end, $include_charts, $include_details);
    } else {
        $_SESSION['error'] = 'Unsupported export format';
        header('Location: sales.php');
        exit();
    }
    
} catch(PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    $_SESSION['error'] = 'Export failed: ' . $e->getMessage();
    header('Location: sales.php');
    exit();
} catch(Exception $e) {
    error_log("Export error: " . $e->getMessage());
    $_SESSION['error'] = 'Export failed: ' . $e->getMessage();
    header('Location: sales.php');
    exit();
}

// ============================================
// EXPORT FUNCTIONS
// ============================================

/**
 * Export to CSV format
 */
function exportCSV($db, $vendor, $where_clause, $params, $report_type, $start_date, $end_date) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-report-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    if ($report_type === 'summary') {
        exportSummaryCSV($db, $output, $vendor, $where_clause, $params, $start_date, $end_date);
    } elseif ($report_type === 'detailed') {
        exportDetailedCSV($db, $output, $vendor, $where_clause, $params, $start_date, $end_date);
    } elseif ($report_type === 'products') {
        exportProductsCSV($db, $output, $vendor, $params, $start_date, $end_date, $where_clause);
    } elseif ($report_type === 'customers') {
        exportCustomersCSV($db, $output, $vendor, $params, $start_date, $end_date, $where_clause);
    }
    
    fclose($output);
    exit();
}

/**
 * Export Summary CSV
 */
function exportSummaryCSV($db, $output, $vendor, $where_clause, $params, $start_date, $end_date) {
    // Header
    fputcsv($output, ['SALES SUMMARY REPORT']);
    fputcsv($output, ['Vendor:', $vendor['full_name']]);
    fputcsv($output, ['Email:', $vendor['email']]);
    fputcsv($output, ['Period:', date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date))]);
    fputcsv($output, ['Generated:', date('M d, Y h:i A')]);
    fputcsv($output, []); // Empty row
    
    // Get summary data
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_sales,
            COALESCE(SUM(oi.quantity), 0) as total_items,
            COUNT(DISTINCT o.user_id) as total_customers,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        $where_clause
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Orders', $summary['total_orders']]);
    fputcsv($output, ['Total Sales', '$' . number_format($summary['total_sales'], 2)]);
    fputcsv($output, ['Total Items Sold', $summary['total_items']]);
    fputcsv($output, ['Total Customers', $summary['total_customers']]);
    fputcsv($output, ['Average Order Value', '$' . number_format($summary['avg_order_value'], 2)]);
    
    fputcsv($output, []); // Empty row
    
    // Daily breakdown
    fputcsv($output, ['DAILY BREAKDOWN']);
    fputcsv($output, ['Date', 'Orders', 'Sales', 'Items', 'Customers']);
    
    $daily_params = $params;
    $daily_sql = "
        SELECT 
            DATE(o.order_date) as date,
            COUNT(DISTINCT o.id) as orders,
            COALESCE(SUM(o.total_amount), 0) as sales,
            COALESCE(SUM(oi.quantity), 0) as items,
            COUNT(DISTINCT o.user_id) as customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        $where_clause
        GROUP BY DATE(o.order_date)
        ORDER BY date DESC
    ";
    
    $stmt = $db->prepare($daily_sql);
    $stmt->execute($params);
    $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($daily as $row) {
        fputcsv($output, [
            $row['date'],
            $row['orders'],
            '$' . number_format($row['sales'], 2),
            $row['items'],
            $row['customers']
        ]);
    }
}

/**
 * Export Detailed CSV
 */
function exportDetailedCSV($db, $output, $vendor, $where_clause, $params, $start_date, $end_date) {
    // Header
    fputcsv($output, ['DETAILED SALES REPORT']);
    fputcsv($output, ['Vendor:', $vendor['full_name']]);
    fputcsv($output, ['Email:', $vendor['email']]);
    fputcsv($output, ['Period:', date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date))]);
    fputcsv($output, ['Generated:', date('M d, Y h:i A')]);
    fputcsv($output, []); // Empty row
    
    // Get detailed data
    $stmt = $db->prepare("
        SELECT 
            o.order_date,
            o.order_number,
            u.full_name as customer_name,
            u.email as customer_email,
            p.name as product_name,
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            o.total_amount as order_total,
            o.status,
            o.payment_method,
            o.payment_status
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        $where_clause
        ORDER BY o.order_date DESC, o.order_number DESC
    ");
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    fputcsv($output, ['Date', 'Order #', 'Customer', 'Customer Email', 'Product', 'Qty', 'Unit Price', 'Subtotal', 'Order Total', 'Status', 'Payment Method', 'Payment Status']);
    
    foreach($sales as $sale) {
        fputcsv($output, [
            $sale['order_date'],
            $sale['order_number'],
            $sale['customer_name'],
            $sale['customer_email'],
            $sale['product_name'],
            $sale['quantity'],
            '$' . number_format($sale['unit_price'], 2),
            '$' . number_format($sale['subtotal'], 2),
            '$' . number_format($sale['order_total'], 2),
            $sale['status'],
            $sale['payment_method'] ?? 'N/A',
            $sale['payment_status'] ?? 'N/A'
        ]);
    }
    
    // Summary at bottom
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Total Records:', count($sales)]);
    
    $total_sales = array_sum(array_column($sales, 'order_total'));
    $total_items = array_sum(array_column($sales, 'quantity'));
    fputcsv($output, ['Total Sales:', '$' . number_format($total_sales, 2)]);
    fputcsv($output, ['Total Items:', $total_items]);
}

/**
 * Export Products CSV
 */
function exportProductsCSV($db, $output, $vendor, $params, $start_date, $end_date, $where_clause) {
    // Header
    fputcsv($output, ['PRODUCT SALES REPORT']);
    fputcsv($output, ['Vendor:', $vendor['full_name']]);
    fputcsv($output, ['Email:', $vendor['email']]);
    fputcsv($output, ['Period:', date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date))]);
    fputcsv($output, ['Generated:', date('M d, Y h:i A')]);
    fputcsv($output, []); // Empty row
    
    $product_params = [$params[0]]; // vendor_id
    if (count($params) > 1) {
        // Add date filters if they exist
        $product_params[] = $params[1];
        $product_params[] = $params[2];
    }
    
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.category,
            p.price,
            p.stock,
            COUNT(DISTINCT oi.order_id) as order_count,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
            p.views as product_views,
            CASE 
                WHEN p.views > 0 THEN (COALESCE(SUM(oi.quantity), 0) / p.views) * 100
                ELSE 0 
            END as conversion_rate,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(r.id) as review_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.vendor_id = ?
        " . (count($params) > 1 ? "AND DATE(o.order_date) BETWEEN ? AND ?" : "") . "
        GROUP BY p.id
        ORDER BY total_revenue DESC
    ");
    
    $stmt->execute($product_params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    fputcsv($output, ['ID', 'Product Name', 'Category', 'Price', 'Stock', 'Orders', 'Qty Sold', 'Revenue', 'Views', 'Conversion %', 'Avg Rating', 'Reviews']);
    
    foreach($products as $product) {
        fputcsv($output, [
            $product['id'],
            $product['name'],
            $product['category'] ?? 'Uncategorized',
            '$' . number_format($product['price'], 2),
            $product['stock'],
            $product['order_count'] ?? 0,
            $product['total_quantity'] ?? 0,
            '$' . number_format($product['total_revenue'] ?? 0, 2),
            $product['product_views'] ?? 0,
            number_format($product['conversion_rate'] ?? 0, 2) . '%',
            number_format($product['avg_rating'] ?? 0, 1),
            $product['review_count'] ?? 0
        ]);
    }
}

/**
 * Export Customers CSV
 */
function exportCustomersCSV($db, $output, $vendor, $params, $start_date, $end_date, $where_clause) {
    // Header
    fputcsv($output, ['CUSTOMER REPORT']);
    fputcsv($output, ['Vendor:', $vendor['full_name']]);
    fputcsv($output, ['Email:', $vendor['email']]);
    fputcsv($output, ['Period:', date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date))]);
    fputcsv($output, ['Generated:', date('M d, Y h:i A')]);
    fputcsv($output, []); // Empty row
    
    $customer_params = [$params[0]];
    if (count($params) > 1) {
        $customer_params[] = $params[1];
        $customer_params[] = $params[2];
    }
    
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.email,
            COUNT(DISTINCT o.id) as order_count,
            SUM(o.total_amount) as total_spent,
            AVG(o.total_amount) as avg_order_value,
            MIN(o.order_date) as first_order_date,
            MAX(o.order_date) as last_order_date
        FROM users u
        JOIN orders o ON u.id = o.user_id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        " . (count($params) > 1 ? "AND DATE(o.order_date) BETWEEN ? AND ?" : "") . "
        GROUP BY u.id
        ORDER BY total_spent DESC
    ");
    
    $stmt->execute($customer_params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    fputcsv($output, ['ID', 'Username', 'Full Name', 'Email', 'Orders', 'Total Spent', 'Avg Order', 'First Order', 'Last Order']);
    
    foreach($customers as $customer) {
        fputcsv($output, [
            $customer['id'],
            $customer['username'],
            $customer['full_name'],
            $customer['email'],
            $customer['order_count'],
            '$' . number_format($customer['total_spent'], 2),
            '$' . number_format($customer['avg_order_value'], 2),
            $customer['first_order_date'] ?? 'N/A',
            $customer['last_order_date'] ?? 'N/A'
        ]);
    }
}

/**
 * Export to Excel format (CSV with .xls extension)
 */
function exportExcel($db, $vendor, $where_clause, $params, $report_type, $start_date, $end_date) {
    // Excel is essentially CSV with .xls extension
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="sales-report-' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add HTML table format for better Excel compatibility
    echo "<table>\n";
    
    if ($report_type === 'summary') {
        echo exportSummaryHTML($db, $vendor, $where_clause, $params, $start_date, $end_date);
    } elseif ($report_type === 'detailed') {
        echo exportDetailedHTML($db, $vendor, $where_clause, $params, $start_date, $end_date);
    } elseif ($report_type === 'products') {
        echo exportProductsHTML($db, $vendor, $params, $start_date, $end_date, $where_clause);
    } elseif ($report_type === 'customers') {
        echo exportCustomersHTML($db, $vendor, $params, $start_date, $end_date, $where_clause);
    }
    
    echo "</table>";
    exit();
}

/**
 * Export to PDF format - FIXED: No use statements inside function
 */
function exportPDF($db, $vendor, $where_clause, $params, $report_type, $start_date, $end_date, $include_charts, $include_details) {
    // Generate HTML content for PDF
    $html = generatePDFHTML($db, $vendor, $where_clause, $params, $report_type, $start_date, $end_date, $include_charts, $include_details);
    
    // Try to use Dompdf if available
    $dompdf_path = __DIR__ . '/../../../vendor/autoload.php';
    
    if (file_exists($dompdf_path)) {
        require_once $dompdf_path;
        
        // Dompdf classes are already imported at the top with 'use' statements
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        $dompdf->stream("sales-report-" . date('Y-m-d') . ".pdf", array('Attachment' => true));
        exit();
    } else {
        // Fallback: Force download as HTML
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="sales-report-' . date('Y-m-d') . '.html"');
        echo $html;
        exit();
    }
}

/**
 * Generate HTML for Summary Report (Excel)
 */
function exportSummaryHTML($db, $vendor, $where_clause, $params, $start_date, $end_date) {
    ob_start();
    ?>
    <tr><th colspan="2">SALES SUMMARY REPORT</th></tr>
    <tr><td>Vendor:</td><td><?php echo htmlspecialchars($vendor['full_name']); ?></td></tr>
    <tr><td>Email:</td><td><?php echo htmlspecialchars($vendor['email']); ?></td></tr>
    <tr><td>Period:</td><td><?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></td></tr>
    <tr><td>Generated:</td><td><?php echo date('M d, Y h:i A'); ?></td></tr>
    <tr><td colspan="2">&nbsp;</td></tr>
    
    <?php
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_sales,
            COALESCE(SUM(oi.quantity), 0) as total_items,
            COUNT(DISTINCT o.user_id) as total_customers,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        $where_clause
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>
    
    <tr><th>Metric</th><th>Value</th></tr>
    <tr><td>Total Orders</td><td><?php echo $summary['total_orders']; ?></td></tr>
    <tr><td>Total Sales</td><td>$<?php echo number_format($summary['total_sales'], 2); ?></td></tr>
    <tr><td>Total Items Sold</td><td><?php echo $summary['total_items']; ?></td></tr>
    <tr><td>Total Customers</td><td><?php echo $summary['total_customers']; ?></td></tr>
    <tr><td>Average Order Value</td><td>$<?php echo number_format($summary['avg_order_value'], 2); ?></td></tr>
    
    <tr><td colspan="2">&nbsp;</td></tr>
    <tr><th colspan="2">DAILY BREAKDOWN</th></tr>
    <tr><th>Date</th><th>Orders</th><th>Sales</th><th>Items</th><th>Customers</th></tr>
    
    <?php
    $daily_sql = "
        SELECT 
            DATE(o.order_date) as date,
            COUNT(DISTINCT o.id) as orders,
            COALESCE(SUM(o.total_amount), 0) as sales,
            COALESCE(SUM(oi.quantity), 0) as items,
            COUNT(DISTINCT o.user_id) as customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        $where_clause
        GROUP BY DATE(o.order_date)
        ORDER BY date DESC
    ";
    
    $stmt = $db->prepare($daily_sql);
    $stmt->execute($params);
    $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($daily as $row) {
        echo "<tr>";
        echo "<td>{$row['date']}</td>";
        echo "<td>{$row['orders']}</td>";
        echo "<td>$" . number_format($row['sales'], 2) . "</td>";
        echo "<td>{$row['items']}</td>";
        echo "<td>{$row['customers']}</td>";
        echo "</tr>\n";
    }
    
    return ob_get_clean();
}

/**
 * Generate HTML for Detailed Report (Excel)
 */
function exportDetailedHTML($db, $vendor, $where_clause, $params, $start_date, $end_date) {
    ob_start();
    ?>
    <tr><th colspan="12">DETAILED SALES REPORT</th></tr>
    <tr><td>Vendor:</td><td colspan="11"><?php echo htmlspecialchars($vendor['full_name']); ?></td></tr>
    <tr><td>Period:</td><td colspan="11"><?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></td></tr>
    <tr><td colspan="12">&nbsp;</td></tr>
    
    <tr>
        <th>Date</th>
        <th>Order #</th>
        <th>Customer</th>
        <th>Email</th>
        <th>Product</th>
        <th>Qty</th>
        <th>Unit Price</th>
        <th>Subtotal</th>
        <th>Order Total</th>
        <th>Status</th>
        <th>Payment Method</th>
        <th>Payment Status</th>
    </tr>
    
    <?php
    $stmt = $db->prepare("
        SELECT 
            o.order_date,
            o.order_number,
            u.full_name as customer_name,
            u.email as customer_email,
            p.name as product_name,
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            o.total_amount as order_total,
            o.status,
            o.payment_method,
            o.payment_status
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        $where_clause
        ORDER BY o.order_date DESC, o.order_number DESC
    ");
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_sales = 0;
    $total_items = 0;
    
    foreach($sales as $sale) {
        $total_sales += $sale['order_total'];
        $total_items += $sale['quantity'];
        echo "<tr>";
        echo "<td>{$sale['order_date']}</td>";
        echo "<td>{$sale['order_number']}</td>";
        echo "<td>" . htmlspecialchars($sale['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($sale['customer_email']) . "</td>";
        echo "<td>" . htmlspecialchars($sale['product_name']) . "</td>";
        echo "<td>{$sale['quantity']}</td>";
        echo "<td>$" . number_format($sale['unit_price'], 2) . "</td>";
        echo "<td>$" . number_format($sale['subtotal'], 2) . "</td>";
        echo "<td>$" . number_format($sale['order_total'], 2) . "</td>";
        echo "<td>{$sale['status']}</td>";
        echo "<td>" . ($sale['payment_method'] ?? 'N/A') . "</td>";
        echo "<td>" . ($sale['payment_status'] ?? 'N/A') . "</td>";
        echo "</tr>\n";
    }
    
    echo "<tr><td colspan='12'>&nbsp;</td></tr>";
    echo "<tr><td colspan='5'><strong>Total Records:</strong></td><td colspan='7'>" . count($sales) . "</td></tr>";
    echo "<tr><td colspan='5'><strong>Total Sales:</strong></td><td colspan='7'>$" . number_format($total_sales, 2) . "</td></tr>";
    echo "<tr><td colspan='5'><strong>Total Items:</strong></td><td colspan='7'>" . $total_items . "</td></tr>";
    
    return ob_get_clean();
}

/**
 * Generate HTML for Products Report (Excel)
 */
function exportProductsHTML($db, $vendor, $params, $start_date, $end_date, $where_clause) {
    ob_start();
    ?>
    <tr><th colspan="12">PRODUCT SALES REPORT</th></tr>
    <tr><td>Vendor:</td><td colspan="11"><?php echo htmlspecialchars($vendor['full_name']); ?></td></tr>
    <tr><td>Period:</td><td colspan="11"><?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></td></tr>
    <tr><td colspan="12">&nbsp;</td></tr>
    
    <tr>
        <th>ID</th>
        <th>Product Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Orders</th>
        <th>Qty Sold</th>
        <th>Revenue</th>
        <th>Views</th>
        <th>Conversion %</th>
        <th>Avg Rating</th>
        <th>Reviews</th>
    </tr>
    
    <?php
    $product_params = [$params[0]];
    if (count($params) > 1) {
        $product_params[] = $params[1];
        $product_params[] = $params[2];
    }
    
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.category,
            p.price,
            p.stock,
            COUNT(DISTINCT oi.order_id) as order_count,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
            p.views as product_views,
            CASE 
                WHEN p.views > 0 THEN (COALESCE(SUM(oi.quantity), 0) / p.views) * 100
                ELSE 0 
            END as conversion_rate,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(r.id) as review_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.vendor_id = ?
        " . (count($params) > 1 ? "AND DATE(o.order_date) BETWEEN ? AND ?" : "") . "
        GROUP BY p.id
        ORDER BY total_revenue DESC
    ");
    
    $stmt->execute($product_params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($products as $product) {
        echo "<tr>";
        echo "<td>{$product['id']}</td>";
        echo "<td>" . htmlspecialchars($product['name']) . "</td>";
        echo "<td>" . htmlspecialchars($product['category'] ?? 'Uncategorized') . "</td>";
        echo "<td>$" . number_format($product['price'], 2) . "</td>";
        echo "<td>{$product['stock']}</td>";
        echo "<td>" . ($product['order_count'] ?? 0) . "</td>";
        echo "<td>" . ($product['total_quantity'] ?? 0) . "</td>";
        echo "<td>$" . number_format($product['total_revenue'] ?? 0, 2) . "</td>";
        echo "<td>" . ($product['product_views'] ?? 0) . "</td>";
        echo "<td>" . number_format($product['conversion_rate'] ?? 0, 2) . "%</td>";
        echo "<td>" . number_format($product['avg_rating'] ?? 0, 1) . "</td>";
        echo "<td>" . ($product['review_count'] ?? 0) . "</td>";
        echo "</tr>\n";
    }
    
    return ob_get_clean();
}

/**
 * Generate HTML for Customers Report (Excel)
 */
function exportCustomersHTML($db, $vendor, $params, $start_date, $end_date, $where_clause) {
    ob_start();
    ?>
    <tr><th colspan="9">CUSTOMER REPORT</th></tr>
    <tr><td>Vendor:</td><td colspan="8"><?php echo htmlspecialchars($vendor['full_name']); ?></td></tr>
    <tr><td>Period:</td><td colspan="8"><?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></td></tr>
    <tr><td colspan="9">&nbsp;</td></tr>
    
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Orders</th>
        <th>Total Spent</th>
        <th>Avg Order</th>
        <th>First Order</th>
        <th>Last Order</th>
    </tr>
    
    <?php
    $customer_params = [$params[0]];
    if (count($params) > 1) {
        $customer_params[] = $params[1];
        $customer_params[] = $params[2];
    }
    
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.email,
            COUNT(DISTINCT o.id) as order_count,
            SUM(o.total_amount) as total_spent,
            AVG(o.total_amount) as avg_order_value,
            MIN(o.order_date) as first_order_date,
            MAX(o.order_date) as last_order_date
        FROM users u
        JOIN orders o ON u.id = o.user_id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        " . (count($params) > 1 ? "AND DATE(o.order_date) BETWEEN ? AND ?" : "") . "
        GROUP BY u.id
        ORDER BY total_spent DESC
    ");
    
    $stmt->execute($customer_params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($customers as $customer) {
        echo "<tr>";
        echo "<td>{$customer['id']}</td>";
        echo "<td>" . htmlspecialchars($customer['username']) . "</td>";
        echo "<td>" . htmlspecialchars($customer['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($customer['email']) . "</td>";
        echo "<td>{$customer['order_count']}</td>";
        echo "<td>$" . number_format($customer['total_spent'], 2) . "</td>";
        echo "<td>$" . number_format($customer['avg_order_value'], 2) . "</td>";
        echo "<td>" . ($customer['first_order_date'] ?? 'N/A') . "</td>";
        echo "<td>" . ($customer['last_order_date'] ?? 'N/A') . "</td>";
        echo "</tr>\n";
    }
    
    return ob_get_clean();
}

/**
 * Generate PDF HTML
 */
function generatePDFHTML($db, $vendor, $where_clause, $params, $report_type, $start_date, $end_date, $include_charts, $include_details) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sales Report - <?php echo date('Y-m-d'); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #4361ee; border-bottom: 2px solid #4361ee; padding-bottom: 10px; }
            h2 { color: #333; margin-top: 20px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }
            th { background: #4361ee; color: white; padding: 8px; text-align: left; }
            td { padding: 6px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f9f9f9; }
            .header { margin-bottom: 30px; }
            .footer { margin-top: 30px; text-align: center; color: #666; font-size: 11px; }
            .summary-box { background: #f0f4ff; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
            .summary-item { background: white; padding: 15px; border-radius: 6px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .summary-item .label { color: #666; font-size: 12px; margin-bottom: 5px; }
            .summary-item .value { font-size: 18px; font-weight: bold; color: #4361ee; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Sales Report</h1>
            <p><strong>Vendor:</strong> <?php echo htmlspecialchars($vendor['full_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($vendor['email']); ?></p>
            <p><strong>Period:</strong> <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            <p><strong>Generated:</strong> <?php echo date('M d, Y h:i A'); ?></p>
        </div>
        
        <?php
        if ($report_type === 'summary') {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COALESCE(SUM(o.total_amount), 0) as total_sales,
                    COALESCE(SUM(oi.quantity), 0) as total_items,
                    COUNT(DISTINCT o.user_id) as total_customers,
                    COALESCE(AVG(o.total_amount), 0) as avg_order_value
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                $where_clause
            ");
            $stmt->execute($params);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="label">Total Orders</div>
                    <div class="value"><?php echo $summary['total_orders']; ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Total Sales</div>
                    <div class="value">$<?php echo number_format($summary['total_sales'], 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Items Sold</div>
                    <div class="value"><?php echo $summary['total_items']; ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Avg Order</div>
                    <div class="value">$<?php echo number_format($summary['avg_order_value'], 2); ?></div>
                </div>
            </div>
            
            <?php if ($include_details): ?>
            <h2>Daily Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Orders</th>
                        <th>Sales</th>
                        <th>Items</th>
                        <th>Customers</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $daily_sql = "
                    SELECT 
                        DATE(o.order_date) as date,
                        COUNT(DISTINCT o.id) as orders,
                        COALESCE(SUM(o.total_amount), 0) as sales,
                        COALESCE(SUM(oi.quantity), 0) as items,
                        COUNT(DISTINCT o.user_id) as customers
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    $where_clause
                    GROUP BY DATE(o.order_date)
                    ORDER BY date DESC
                ";
                
                $stmt = $db->prepare($daily_sql);
                $stmt->execute($params);
                $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach($daily as $row):
                ?>
                    <tr>
                        <td><?php echo $row['date']; ?></td>
                        <td><?php echo $row['orders']; ?></td>
                        <td>$<?php echo number_format($row['sales'], 2); ?></td>
                        <td><?php echo $row['items']; ?></td>
                        <td><?php echo $row['customers']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
        <?php
        } elseif ($report_type === 'detailed') {
            $stmt = $db->prepare("
                SELECT 
                    o.order_date,
                    o.order_number,
                    u.full_name as customer_name,
                    u.email as customer_email,
                    p.name as product_name,
                    oi.quantity,
                    oi.unit_price,
                    oi.subtotal,
                    o.total_amount as order_total,
                    o.status
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN users u ON o.user_id = u.id
                $where_clause
                ORDER BY o.order_date DESC
            ");
            $stmt->execute($params);
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <h2>Detailed Sales</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($sales as $sale): ?>
                    <tr>
                        <td><?php echo $sale['order_date']; ?></td>
                        <td><?php echo $sale['order_number']; ?></td>
                        <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                        <td><?php echo $sale['quantity']; ?></td>
                        <td>$<?php echo number_format($sale['unit_price'], 2); ?></td>
                        <td>$<?php echo number_format($sale['subtotal'], 2); ?></td>
                        <td>$<?php echo number_format($sale['order_total'], 2); ?></td>
                        <td><?php echo $sale['status']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php
        } elseif ($report_type === 'products') {
            $product_params = [$params[0]];
            if (count($params) > 1) {
                $product_params[] = $params[1];
                $product_params[] = $params[2];
            }
            
            $stmt = $db->prepare("
                SELECT 
                    p.name,
                    p.category,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    COALESCE(SUM(oi.quantity), 0) as total_quantity,
                    COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                    p.price,
                    p.stock,
                    COALESCE(AVG(r.rating), 0) as avg_rating
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id
                LEFT JOIN reviews r ON p.id = r.product_id
                WHERE p.vendor_id = ?
                " . (count($params) > 1 ? "AND DATE(o.order_date) BETWEEN ? AND ?" : "") . "
                GROUP BY p.id
                ORDER BY total_revenue DESC
            ");
            $stmt->execute($product_params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <h2>Product Sales</h2>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Orders</th>
                        <th>Qty Sold</th>
                        <th>Revenue</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo $product['order_count'] ?? 0; ?></td>
                        <td><?php echo $product['total_quantity'] ?? 0; ?></td>
                        <td>$<?php echo number_format($product['total_revenue'] ?? 0, 2); ?></td>
                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['stock']; ?></td>
                        <td><?php echo number_format($product['avg_rating'] ?? 0, 1); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php } ?>
        
        <div class="footer">
            <p>Generated by E-Commerce System | <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>