<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}
require_once SITE_URL .'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Load DomPDF if needed for PDF export
$use_pdf = false;
if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
    $use_pdf = true;
}

$format = isset($_GET['format']) ? $_GET['format'] : 'json';

// Get filter parameters
$filters = [
    'template_id' => $_GET['template_id'] ?? '',
    'recipient' => $_GET['recipient'] ?? '',
    'status' => $_GET['status'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? ''
];

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filters['template_id'])) {
        $where[] = "template_key = ?";
        $params[] = $filters['template_id'];
    }
    
    if (!empty($filters['recipient'])) {
        $where[] = "recipient_email LIKE ?";
        $params[] = "%{$filters['recipient']}%";
    }
    
    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['start_date'])) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get filtered logs
    $stmt = $db->prepare("SELECT * FROM email_logs WHERE $where_sql ORDER BY created_at DESC");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats = [
        'total' => count($logs),
        'sent' => 0,
        'failed' => 0,
        'pending' => 0,
        'bounced' => 0
    ];
    
    foreach ($logs as $log) {
        $stats[$log['status']] = ($stats[$log['status']] ?? 0) + 1;
    }
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="email_logs_' . date('Y-m-d') . '.json"');
        
        echo json_encode([
            'generated' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username'] ?? 'Admin',
            'filters' => $filters,
            'statistics' => $stats,
            'logs' => $logs
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="email_logs_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers
        fputcsv($output, ['ID', 'Template', 'Recipient', 'Name', 'Subject', 'Status', 'Error', 'Sent At', 'Created At']);
        
        // Add data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['template_key'] ?? 'Custom',
                $log['recipient_email'],
                $log['recipient_name'] ?? '',
                $log['subject'],
                $log['status'],
                $log['error_message'] ?? '',
                $log['sent_at'] ?? '',
                $log['created_at']
            ]);
        }
        
        fclose($output);
        
    } elseif ($format === 'pdf' && $use_pdf) {
        // Generate HTML for PDF
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Email Logs Export</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #4361ee; border-bottom: 2px solid #4361ee; }
                .filters { background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; font-size: 10px; }
                th { background: #4361ee; color: white; padding: 8px; text-align: left; }
                td { padding: 6px; border-bottom: 1px solid #ddd; }
                .sent { color: #06d6a0; }
                .failed { color: #ef476f; }
                .pending { color: #ffb703; }
                .bounced { color: #4cc9f0; }
            </style>
        </head>
        <body>
            <h1>Email Logs Export</h1>
            <p>Generated: ' . date('Y-m-d H:i:s') . '</p>
            
            <div class="filters">
                <h3>Filters Applied:</h3>
                <ul>';
        
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $html .= '<li><strong>' . ucfirst($key) . ':</strong> ' . htmlspecialchars($value) . '</li>';
            }
        }
        
        $html .= '</ul>
            </div>
            
            <h3>Statistics</h3>
            <table>
                <tr>
                    <th>Total</th>
                    <th>Sent</th>
                    <th>Failed</th>
                    <th>Pending</th>
                    <th>Bounced</th>
                </tr>
                <tr>
                    <td>' . $stats['total'] . '</td>
                    <td class="sent">' . $stats['sent'] . '</td>
                    <td class="failed">' . $stats['failed'] . '</td>
                    <td class="pending">' . $stats['pending'] . '</td>
                    <td class="bounced">' . $stats['bounced'] . '</td>
                </tr>
            </table>
            
            <h3>Email Logs</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Template</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($logs as $log) {
            $html .= '<tr>
                <td>#' . $log['id'] . '</td>
                <td>' . ($log['template_key'] ?? 'Custom') . '</td>
                <td>' . $log['recipient_email'] . '</td>
                <td>' . htmlspecialchars($log['subject']) . '</td>
                <td class="' . $log['status'] . '">' . ucfirst($log['status']) . '</td>
                <td>' . date('Y-m-d H:i', strtotime($log['created_at'])) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </body>
        </html>';
        
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream("email_logs_" . date('Y-m-d') . ".pdf", ['Attachment' => true]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}