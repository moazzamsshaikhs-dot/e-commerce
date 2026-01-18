<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Vendor account not approved.';
        redirect(SITE_URL . 'admin/vendors/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error.';
    redirect(SITE_URL . 'index.php');
}

// Get export parameters
$export_type = $_GET['type'] ?? 'reviews'; // 'reviews', 'responses', 'ratings'
$format = $_GET['format'] ?? 'csv'; // 'csv', 'excel', 'json'
$time_filter = $_GET['time'] ?? 'all';
$product_id = $_GET['product'] ?? 0;

// Set date range
$date_conditions = [];
$params = [':vendor_id' => $_SESSION['user_id']];

if ($time_filter !== 'all') {
    $end_date = date('Y-m-d');
    switch($time_filter) {
        case 'today':
            $start_date = date('Y-m-d');
            break;
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'quarter':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'year':
            $start_date = date('Y-m-d', strtotime('-365 days'));
            break;
    }
    $date_conditions[] = "r.created_at BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
    $params[':end_date'] = $end_date . ' 23:59:59';
}

if ($product_id > 0) {
    $date_conditions[] = "r.product_id = :product_id";
    $params[':product_id'] = $product_id;
}

$where_clause = "WHERE p.vendor_id = :vendor_id";
if (!empty($date_conditions)) {
    $where_clause .= " AND " . implode(" AND ", $date_conditions);
}

try {
    if ($export_type === 'reviews') {
        // Export reviews data
        $sql = "SELECT 
                    r.id as review_id,
                    p.name as product_name,
                    p.sku as product_sku,
                    u.username as customer_username,
                    u.email as customer_email,
                    u.full_name as customer_name,
                    r.rating,
                    r.review_text,
                    r.is_approved,
                    r.vendor_responded,
                    DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i:%s') as review_date,
                    (SELECT COUNT(*) FROM review_likes WHERE review_id = r.id) as likes_count,
                    (SELECT COUNT(*) FROM review_reports WHERE review_id = r.id) as reports_count,
                    vr.response_text as vendor_response,
                    DATE_FORMAT(vr.created_at, '%Y-%m-%d %H:%i:%s') as response_date,
                    vr.is_public as response_public
                FROM reviews r
                JOIN products p ON r.product_id = p.id
                JOIN users u ON r.user_id = u.id
                LEFT JOIN vendor_responses vr ON r.id = vr.review_id
                $where_clause
                ORDER BY r.created_at DESC";
                
        $filename = "vendor_reviews_export_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            'Review ID', 'Product Name', 'Product SKU', 'Customer Username',
            'Customer Email', 'Customer Name', 'Rating', 'Review Text',
            'Is Approved', 'Vendor Responded', 'Review Date', 'Likes Count',
            'Reports Count', 'Vendor Response', 'Response Date', 'Response Public'
        ];
        
    } elseif ($export_type === 'responses') {
        // Export responses data
        $sql = "SELECT 
                    vr.id as response_id,
                    r.id as review_id,
                    p.name as product_name,
                    u.username as customer_username,
                    u.full_name as customer_name,
                    r.rating,
                    r.review_text,
                    vr.response_text,
                    vr.is_public,
                    vr.is_edited,
                    DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i:%s') as review_date,
                    DATE_FORMAT(vr.created_at, '%Y-%m-%d %H:%i:%s') as response_date,
                    DATE_FORMAT(vr.updated_at, '%Y-%m-%d %H:%i:%s') as last_updated,
                    TIMESTAMPDIFF(HOUR, r.created_at, vr.created_at) as response_time_hours
                FROM vendor_responses vr
                JOIN reviews r ON vr.review_id = r.id
                JOIN products p ON r.product_id = p.id
                JOIN users u ON r.user_id = u.id
                $where_clause
                ORDER BY vr.created_at DESC";
                
        $filename = "vendor_responses_export_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            'Response ID', 'Review ID', 'Product Name', 'Customer Username',
            'Customer Name', 'Rating', 'Review Text', 'Response Text',
            'Is Public', 'Is Edited', 'Review Date', 'Response Date',
            'Last Updated', 'Response Time (Hours)'
        ];
        
    } elseif ($export_type === 'ratings') {
        // Export ratings analytics
        $sql = "SELECT 
                    p.id as product_id,
                    p.name as product_name,
                    p.sku as product_sku,
                    COUNT(r.id) as total_reviews,
                    AVG(r.rating) as average_rating,
                    SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as one_star,
                    MIN(r.created_at) as first_review_date,
                    MAX(r.created_at) as last_review_date,
                    COUNT(DISTINCT r.user_id) as unique_customers
                FROM products p
                LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
                WHERE p.vendor_id = :vendor_id
                GROUP BY p.id
                ORDER BY average_rating DESC";
                
        $filename = "vendor_ratings_export_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            'Product ID', 'Product Name', 'Product SKU', 'Total Reviews',
            'Average Rating', '5-Star Reviews', '4-Star Reviews',
            '3-Star Reviews', '2-Star Reviews', '1-Star Reviews',
            'First Review Date', 'Last Review Date', 'Unique Customers'
        ];
        
    } else {
        $_SESSION['error'] = 'Invalid export type.';
        redirect('reviews.php');
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Export data
    if ($format === 'csv') {
        exportToCSV($data, $headers, $filename);
    } elseif ($format === 'excel') {
        exportToExcel($data, $headers, $filename);
    } elseif ($format === 'json') {
        exportToJSON($data, $filename);
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error exporting data: ' . $e->getMessage();
    redirect('reviews.php');
}

// CSV Export Function
function exportToCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        // Clean data for CSV
        foreach ($row as &$value) {
            if (is_string($value)) {
                $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
                $value = str_replace('"', '""', $value);
                $value = '"' . $value . '"';
            }
        }
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Excel Export Function
function exportToExcel($data, $headers, $filename) {
    require_once '../../../includes/phpspreadsheet/vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $column = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($column . '1', $header);
        $sheet->getStyle($column . '1')->getFont()->setBold(true);
        $column++;
    }
    
    // Set data
    $row = 2;
    foreach ($data as $item) {
        $column = 'A';
        foreach ($item as $value) {
            $sheet->setCellValue($column . $row, $value);
            $column++;
        }
        $row++;
    }
    
    // Auto size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . str_replace('.csv', '.xlsx', $filename) . '"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

// JSON Export Function
function exportToJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . str_replace('.csv', '.json', $filename) . '"');
    
    echo json_encode([
        'export_date' => date('Y-m-d H:i:s'),
        'vendor_id' => $_SESSION['user_id'],
        'total_records' => count($data),
        'data' => $data
    ], JSON_PRETTY_PRINT);
    exit;
}
?>