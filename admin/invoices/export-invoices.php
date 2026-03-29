<?php
// admin/export-invoices.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: index.php');
    exit;
}

// Get format parameter
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';

// Load DOMPDF for PDF format (before use statements)
if ($format === 'pdf') {
    // Try multiple possible paths for DOMPDF
    $possible_paths = [
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/dompdf/autoload.inc.php',
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
        dirname(__DIR__, 3) . '/vendor/dompdf/autoload.inc.php',
        dirname(__DIR__, 3) . '/vendor/autoload.php',
        __DIR__ . '/../../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../../../vendor/autoload.php',
        // Path for e-commerce/vendor/dompdf
        $_SERVER['DOCUMENT_ROOT'] . SITE_URL .'vendor/dompdf/autoload.inc.php',
        $_SERVER['DOCUMENT_ROOT'] . SITE_URL .'vendor/autoload.php'
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
        die("DOMPDF library not found. Please install it via Composer.");
    }
}

// USE STATEMENTS IN GLOBAL SCOPE (after DOMPDF is loaded)
use Dompdf\Dompdf;
use Dompdf\Options;

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'payment_status' => $_GET['payment_status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'customer_id' => $_GET['customer_id'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? ''
];

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = "i.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['payment_status'])) {
        $where[] = "i.payment_status = ?";
        $params[] = $filters['payment_status'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(i.invoice_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filters['customer_id'])) {
        $where[] = "i.user_id = ?";
        $params[] = $filters['customer_id'];
    }
    
    if (!empty($filters['start_date'])) {
        $where[] = "DATE(i.invoice_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $where[] = "DATE(i.invoice_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get data
    $sql = "SELECT i.*, u.full_name, u.email, u.phone, u.address
            FROM invoices i
            LEFT JOIN users u ON i.user_id = u.id
            WHERE $where_sql
            ORDER BY i.invoice_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Get summary stats
    $total_amount = array_sum(array_column($data, 'total_amount'));
    $total_paid = array_sum(array_column($data, 'amount_paid'));
    $total_due = array_sum(array_column($data, 'balance_due'));
    
    if ($format === 'json') {
        exportJSON($data);
    } elseif ($format === 'excel') {
        exportExcel($data, $total_amount, $total_paid, $total_due, $filters);
    } elseif ($format === 'pdf') {
        exportPDF($data, $total_amount, $total_paid, $total_due, $filters);
    } else {
        exportCSV($data);
    }
    
} catch(PDOException $e) {
    die('Export failed: ' . $e->getMessage());
}

/**
 * Export as CSV
 */
function exportCSV($data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Invoice #', 'Customer', 'Email', 'Phone', 'Amount', 'Paid', 'Due',
        'Status', 'Payment Status', 'Date', 'Due Date', 'Notes'
    ]);
    
    // Data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['invoice_number'],
            $row['full_name'] ?? 'N/A',
            $row['email'] ?? 'N/A',
            $row['phone'] ?? 'N/A',
            number_format($row['total_amount'], 2),
            number_format($row['amount_paid'], 2),
            number_format($row['balance_due'], 2),
            $row['status'],
            $row['payment_status'],
            $row['invoice_date'],
            $row['due_date'],
            $row['notes'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

/**
 * Export as Excel (HTML format)
 */
function exportExcel($data, $total_amount, $total_paid, $total_due, $filters) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d') . '.xls"');
    
    $company_name = getCompanyName();
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Invoices Export</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; }';
    echo 'h1 { color: #4361ee; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background: #4361ee; color: white; padding: 10px; text-align: left; }';
    echo 'td { padding: 8px; border-bottom: 1px solid #ddd; }';
    echo '.summary { background: #f8f9fa; padding: 10px; margin-bottom: 20px; }';
    echo '.total { font-weight: bold; color: #4361ee; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Header
    echo '<h1>' . htmlspecialchars($company_name) . '</h1>';
    echo '<h2>Invoices Report</h2>';
    echo '<p>Generated on: ' . date('F d, Y H:i:s') . '</p>';
    
    // Filters
    if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
        echo '<p><strong>Date Range:</strong> ' . ($filters['start_date'] ?? 'All') . ' to ' . ($filters['end_date'] ?? 'All') . '</p>';
    }
    
    // Summary
    echo '<div class="summary">';
    echo '<h3>Summary</h3>';
    echo '<table width="50%">';
    echo '<tr><td><strong>Total Invoices:</strong> ' . count($data) . '</td><td><strong>Total Amount:</strong> PKR ' . number_format($total_amount, 2) . '</td></tr>';
    echo '<tr><td><strong>Total Paid:</strong> PKR ' . number_format($total_paid, 2) . '</td><td><strong>Total Due:</strong> PKR ' . number_format($total_due, 2) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // Table
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Invoice #</th>';
    echo '<th>Customer</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>Amount (PKR)</th>';
    echo '<th>Paid (PKR)</th>';
    echo '<th>Due (PKR)</th>';
    echo '<th>Status</th>';
    echo '<th>Payment Status</th>';
    echo '<th>Date</th>';
    echo '<th>Due Date</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['invoice_number']) . '</td>';
        echo '<td>' . htmlspecialchars($row['full_name'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($row['email'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($row['phone'] ?? 'N/A') . '</td>';
        echo '<td>' . number_format($row['total_amount'], 2) . '</td>';
        echo '<td>' . number_format($row['amount_paid'], 2) . '</td>';
        echo '<td>' . number_format($row['balance_due'], 2) . '</td>';
        echo '<td>' . ucfirst($row['status']) . '</td>';
        echo '<td>' . ucfirst($row['payment_status']) . '</td>';
        echo '<td>' . date('M d, Y', strtotime($row['invoice_date'])) . '</td>';
        echo '<td>' . date('M d, Y', strtotime($row['due_date'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<p style="margin-top: 20px;"><em>Report generated by ' . htmlspecialchars($company_name) . ' on ' . date('Y-m-d H:i:s') . '</em></p>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Export as PDF
 */
function exportPDF($data, $total_amount, $total_paid, $total_due, $filters) {
    global $dompdf_loaded;
    
    if (!isset($dompdf_loaded) || !$dompdf_loaded) {
        die("DOMPDF library not found. Please install it via Composer.");
    }
    
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    $html = generatePDFHTML($data, $total_amount, $total_paid, $total_due, $filters);
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    $dompdf->stream('invoices_' . date('Y-m-d') . '.pdf', array('Attachment' => 1));
    exit;
}

/**
 * Generate PDF HTML
 */
function generatePDFHTML($data, $total_amount, $total_paid, $total_due, $filters) {
    $company_name = getCompanyName();
    $current_time = date('F d, Y H:i:s');
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoices Report</title>
        <style>
            body {
                font-family: "DejaVu Sans", sans-serif;
                margin: 20px;
                font-size: 10px;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 3px solid #4361ee;
            }
            .header h1 {
                color: #4361ee;
                margin: 0;
                font-size: 24px;
            }
            .header p {
                margin: 5px 0;
                color: #666;
                font-size: 11px;
            }
            .summary {
                background: #f8f9fa;
                padding: 10px;
                margin-bottom: 20px;
                border-left: 4px solid #4361ee;
            }
            .summary table {
                width: 100%;
            }
            .summary td {
                padding: 5px;
                font-size: 11px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            th {
                background: #4361ee;
                color: white;
                padding: 8px 6px;
                text-align: left;
                font-size: 10px;
            }
            td {
                padding: 6px;
                border-bottom: 1px solid #ddd;
                font-size: 9px;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
                font-size: 9px;
                color: #666;
            }
            .text-success { color: #06d6a0; }
            .text-danger { color: #ef476f; }
            .text-warning { color: #ffb703; }
            .text-primary { color: #4361ee; }
            .fw-bold { font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . htmlspecialchars($company_name) . '</h1>
            <h2>Invoices Report</h2>
            <p>Generated on: ' . $current_time . '</p>';
    
    if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
        $html .= '<p>Date Range: ' . ($filters['start_date'] ?? 'All') . ' to ' . ($filters['end_date'] ?? 'All') . '</p>';
    }
    
    $html .= '</div>
        
        <div class="summary">
            <h3>Summary</h3>
            <table>
                <tr>
                    <td width="25%"><strong>Total Invoices:</strong> ' . count($data) . '</td>
                    <td width="25%"><strong>Total Amount:</strong> PKR ' . number_format($total_amount, 2) . '</td>
                    <td width="25%"><strong>Total Paid:</strong> PKR ' . number_format($total_paid, 2) . '</td>
                    <td width="25%"><strong>Total Due:</strong> PKR ' . number_format($total_due, 2) . '</td>
                </tr>
            </table>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Amount (PKR)</th>
                    <th>Paid (PKR)</th>
                    <th>Due (PKR)</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($data as $row) {
        $payment_class = '';
        if ($row['payment_status'] == 'paid') $payment_class = 'text-success';
        elseif ($row['payment_status'] == 'unpaid') $payment_class = 'text-danger';
        elseif ($row['payment_status'] == 'partial') $payment_class = 'text-warning';
        
        $html .= '<tr>
                    <td>' . htmlspecialchars($row['invoice_number']) . '</td>
                    <td>' . htmlspecialchars($row['full_name'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['email'] ?? 'N/A') . '</td>
                    <td class="fw-bold">' . number_format($row['total_amount'], 2) . '</td>
                    <td>' . number_format($row['amount_paid'], 2) . '</td>
                    <td class="' . $payment_class . '">' . number_format($row['balance_due'], 2) . '</td>
                    <td>' . ucfirst($row['status']) . '</td>
                    <td>' . ucfirst($row['payment_status']) . '</td>
                    <td>' . date('M d, Y', strtotime($row['invoice_date'])) . '</td>
                    <td>' . date('M d, Y', strtotime($row['due_date'])) . '</td>
                </tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="footer">
            <p>Report generated by ' . htmlspecialchars($company_name) . ' on ' . date('Y-m-d H:i:s') . '</p>
            <p>© ' . date('Y') . ' ' . htmlspecialchars($company_name) . ' - All Rights Reserved</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Export as JSON
 */
function exportJSON($data) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d') . '.json"');
    
    $export_data = [
        'generated_at' => date('Y-m-d H:i:s'),
        'total_records' => count($data),
        'invoices' => $data
    ];
    
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Get company name from settings
 */
function getCompanyName() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : 'ShopEase Pro';
    } catch(Exception $e) {
        return 'ShopEase Pro';
    }
}
?>