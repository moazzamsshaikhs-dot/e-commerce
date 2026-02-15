<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

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

// Get export parameters
$export_type = $_GET['type'] ?? 'reviews';
$format = $_GET['format'] ?? 'csv';
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
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
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
        $sql = "SELECT 
                    r.id as review_id,
                    p.name as product_name,
                    u.username as customer_username,
                    u.email as customer_email,
                    u.full_name as customer_name,
                    r.rating,
                    r.review_text,
                    r.is_approved,
                    r.vendor_responded,
                    DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i:%s') as review_date,
                    (SELECT COUNT(*) FROM review_likes WHERE review_id = r.id) as likes_count
                FROM reviews r
                JOIN products p ON r.product_id = p.id
                JOIN users u ON r.user_id = u.id
                $where_clause
                ORDER BY r.created_at DESC";
                
        $filename = "vendor_reviews_export_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            'Review ID', 'Product Name', 'Customer Username',
            'Customer Email', 'Customer Name', 'Rating', 'Review Text',
            'Is Approved', 'Vendor Responded', 'Review Date', 'Likes Count'
        ];
        
    } elseif ($export_type === 'responses') {
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
                    DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i:%s') as review_date,
                    DATE_FORMAT(vr.created_at, '%Y-%m-%d %H:%i:%s') as response_date
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
            'Is Public', 'Review Date', 'Response Date'
        ];
        
    } elseif ($export_type === 'ratings') {
        $sql = "SELECT 
                    p.id as product_id,
                    p.name as product_name,
                    COUNT(r.id) as total_reviews,
                    AVG(r.rating) as average_rating,
                    SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as one_star
                FROM products p
                LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
                WHERE p.vendor_id = :vendor_id
                GROUP BY p.id
                ORDER BY average_rating DESC";
                
        $filename = "vendor_ratings_export_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            'Product ID', 'Product Name', 'Total Reviews',
            'Average Rating', '5-Star', '4-Star', '3-Star', '2-Star', '1-Star'
        ];
        
    } else {
        $_SESSION['error'] = 'Invalid export type.';
        header('Location: reviews.php');
        exit();
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Export based on format
    if ($format === 'csv') {
        exportCSV($data, $headers, $filename);
    } else {
        exportCSV($data, $headers, $filename); // Default to CSV
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error exporting data: ' . $e->getMessage();
    header('Location: reviews.php');
    exit();
}

/**
 * Export to CSV - FIXED: Line 225 issue resolved
 */
function exportCSV($data, $headers, $filename) {
    // Clear any output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 (Excel compatibility)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Write headers - FIXED: Line 257 issue resolved
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        $formatted_row = [];
        foreach ($row as $key => $value) {
            // Handle special cases
            if (is_null($value)) {
                $formatted_row[] = '';
            } elseif (is_bool($value)) {
                $formatted_row[] = $value ? 'Yes' : 'No';
            } else {
                $formatted_row[] = $value;
            }
        }
        fputcsv($output, $formatted_row);
    }
    
    fclose($output);
    exit();
}
?>