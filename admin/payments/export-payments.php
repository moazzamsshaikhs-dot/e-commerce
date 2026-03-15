<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

requireSystemAdmin();

// Load Dompdf
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$db = getDB();

// Get filter parameters
$report_type = $_POST['report_type'] ?? 'transactions';
$start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_POST['end_date'] ?? date('Y-m-d');
$format = $_POST['format'] ?? 'pdf';
$include_summary = isset($_POST['include_summary']);
$include_charts = isset($_POST['include_charts']);
$include_transactions = isset($_POST['include_transactions']);
$include_accounts = isset($_POST['include_accounts']);

// Get data based on report type
$data = [];

// Summary statistics
if ($include_summary) {
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT id) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(AVG(total_amount), 0) as avg_order_value
        FROM orders 
        WHERE payment_status = 'completed' 
        AND DATE(order_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $data['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Payment methods breakdown
$stmt = $db->prepare("
    SELECT 
        payment_method,
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM orders 
    WHERE payment_status = 'completed'
    AND DATE(order_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total DESC
");
$stmt->execute([$start_date, $end_date]);
$data['payment_methods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Transactions
if ($include_transactions) {
    $stmt = $db->prepare("
        SELECT 
            pt.*,
            o.order_number,
            u.full_name as user_name,
            u.email as user_email
        FROM payment_transactions pt
        LEFT JOIN orders o ON pt.order_id = o.id
        LEFT JOIN users u ON pt.user_id = u.id
        WHERE DATE(pt.created_at) BETWEEN ? AND ?
        ORDER BY pt.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $data['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Admin accounts
if ($include_accounts) {
    $stmt = $db->query("
        SELECT 
            account_type,
            COUNT(*) as count,
            COALESCE(SUM(current_balance), 0) as total_balance,
            COALESCE(SUM(total_credited), 0) as total_credited,
            COALESCE(SUM(total_debited), 0) as total_debited
        FROM admin_accounts 
        WHERE is_active = 1
        GROUP BY account_type
        ORDER BY total_balance DESC
    ");
    $data['accounts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Account details
    $stmt = $db->query("
        SELECT * FROM admin_accounts 
        WHERE is_active = 1 
        ORDER BY account_type, is_default DESC
    ");
    $data['account_details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Withdrawals for withdrawal report
if ($report_type == 'withdrawals') {
    $stmt = $db->prepare("
        SELECT 
            vwr.*,
            u.full_name as vendor_name,
            u.email as vendor_email
        FROM vendor_withdrawal_requests vwr
        JOIN users u ON vwr.vendor_id = u.id
        WHERE DATE(vwr.created_at) BETWEEN ? AND ?
        ORDER BY vwr.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $data['withdrawals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Generate HTML report
ob_start();
include 'templates/export-template.php';
$html = ob_get_clean();

if ($format == 'pdf') {
    // PDF generation with Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Output PDF
    $filename = 'payment_report_' . date('Y-m-d') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    
} elseif ($format == 'excel') {
    // Excel export (simplified - you can use PhpSpreadsheet for better formatting)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="payment_report_' . date('Y-m-d') . '.xls"');
    echo $html;
    
} elseif ($format == 'csv') {
    // CSV export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payment_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Transaction ID', 'Date', 'Order #', 'Customer', 'Gateway', 'Amount', 'Status']);
    
    // Add data
    foreach ($data['transactions'] as $txn) {
        fputcsv($output, [
            $txn['transaction_id'],
            date('Y-m-d H:i', strtotime($txn['created_at'])),
            $txn['order_number'],
            $txn['user_name'],
            $txn['gateway'],
            $txn['amount'],
            $txn['status']
        ]);
    }
    
    fclose($output);
}

exit;