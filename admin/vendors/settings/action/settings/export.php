<?php
// action/settings/export.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

error_log("=== Export Data Started ===");

if ($_SESSION['user_type'] !== 'vendor') {
    die('Access denied');
}

$vendor_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? '';

if (!in_array($type, ['products', 'orders', 'analytics'])) {
    die('Invalid export type');
}

try {
    $db = getDB();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="vendor_' . $type . '_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($type === 'products') {
        // Products export
        fputcsv($output, ['ID', 'Name', 'Price', 'Stock', 'Category', 'Status', 'Created Date']);
        
        $stmt = $db->prepare("
            SELECT id, name, price, stock, category, approved_status, created_at 
            FROM products 
            WHERE vendor_id = ? 
            ORDER BY id DESC
        ");
        $stmt->execute([$vendor_id]);
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
        
    } elseif ($type === 'orders') {
        // Orders export
        fputcsv($output, ['Order ID', 'Order Number', 'Amount', 'Status', 'Payment Method', 'Customer', 'Date']);
        
        $stmt = $db->prepare("
            SELECT o.id, o.order_number, o.total_amount, o.status, 
                   o.payment_method, u.username, o.created_at
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            JOIN users u ON o.user_id = u.id
            WHERE p.vendor_id = ?
            GROUP BY o.id
            ORDER BY o.id DESC
        ");
        $stmt->execute([$vendor_id]);
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
        
    } elseif ($type === 'analytics') {
        // Analytics export
        fputcsv($output, ['Date', 'Product Views', 'Sales Count', 'Revenue', 'Unique Visitors']);
        
        // This would need an analytics table - placeholder
        fputcsv($output, [date('Y-m-d'), 0, 0, 0.00, 0]);
    }
    
    fclose($output);
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'export_data', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Exported $type data", $ip, $ua]);
    
} catch(Exception $e) {
    error_log("Export error: " . $e->getMessage());
    die('Export failed: ' . $e->getMessage());
}
?>