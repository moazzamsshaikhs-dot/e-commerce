<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . '../index.php');
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=payments_' . date('Y-m-d_H-i') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Add CSV headers
fputcsv($output, [
    'Payment ID',
    'Transaction ID',
    'Customer Name',
    'Customer Email',
    'Order Number',
    'Payment Method',
    'Amount',
    'Currency',
    'Status',
    'Payment Date',
    'Created At'
]);

try {
    $db = getDB();
    
    // Get filter parameters
    $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
    $filter_method = isset($_GET['method']) ? $_GET['method'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    
    // Build query
    $where = ["1=1"];
    $params = [];
    
    if ($filter_status) {
        $where[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if ($filter_method) {
        $where[] = "p.payment_method = ?";
        $params[] = $filter_method;
    }
    
    if ($start_date) {
        $where[] = "DATE(p.created_at) >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $where[] = "DATE(p.created_at) <= ?";
        $params[] = $end_date;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get payments for export
    $payments_sql = "SELECT p.*, 
                            u.full_name as customer_name,
                            u.email as customer_email,
                            o.order_number
                     FROM payments p
                     LEFT JOIN users u ON p.user_id = u.id
                     LEFT JOIN orders o ON p.order_id = o.id
                     WHERE $where_sql
                     ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($payments_sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Add data rows
    foreach ($payments as $payment) {
        fputcsv($output, [
            $payment['id'],
            $payment['transaction_id'],
            $payment['customer_name'],
            $payment['customer_email'],
            $payment['order_number'],
            $payment['payment_method'],
            $payment['amount'],
            $payment['currency'],
            $payment['status'],
            $payment['created_at']
        ]);
    }
    
} catch(PDOException $e) {
    // If error, output as plain text
    header('Content-Type: text/plain');
    echo 'Error exporting payments: ' . $e->getMessage();
}

fclose($output);
exit;?>