<?php
// ajax/export-logs.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

// Get parameters
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
$log_type = isset($_GET['log_type']) ? $_GET['log_type'] : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Load DOMPDF for PDF format
if ($format === 'pdf') {
    // Try multiple possible paths for DOMPDF
    $possible_paths = [
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/dompdf/autoload.inc.php',
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
        dirname(__DIR__, 4) . '/vendor/dompdf/autoload.inc.php',
        dirname(__DIR__, 4) . '/vendor/autoload.php',
        __DIR__ . '/../../../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../../../vendor/autoload.php'
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

// Use statements in global scope
use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($log_type)) {
        $where[] = "ua.activity_type = ?";
        $params[] = $log_type;
    }
    
    if (!empty($user_id)) {
        $where[] = "ua.user_id = ?";
        $params[] = $user_id;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(ua.created_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(ua.created_at) <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($search)) {
        $where[] = "(ua.description LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get data
    $sql = "SELECT ua.id, ua.user_id, ua.activity_type, ua.description, 
                   ua.ip_address, ua.user_agent, ua.created_at,
                   u.username, u.full_name, u.email
            FROM user_activities ua
            LEFT JOIN users u ON ua.user_id = u.id
            WHERE $where_sql
            ORDER BY ua.created_at DESC
            LIMIT 10000"; // Limit for performance
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $total_records = count($logs);
    $unique_users = count(array_unique(array_filter(array_column($logs, 'user_id'))));
    $activity_types = count(array_unique(array_column($logs, 'activity_type')));
    
    // Get date range
    $first_record = !empty($logs) ? $logs[0]['created_at'] : date('Y-m-d H:i:s');
    $last_record = !empty($logs) ? end($logs)['created_at'] : date('Y-m-d H:i:s');
    
    if ($format === 'pdf') {
        // Generate PDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Generate HTML content for PDF
        $html = generatePDFHTML($logs, $total_records, $unique_users, $activity_types, 
                                $first_record, $last_record, $log_type, $user_id, $start_date, $end_date, $search);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        // Output PDF
        $dompdf->stream('system-logs-' . date('Y-m-d') . '.pdf', array('Attachment' => 1));
        exit;
        
    } elseif ($format === 'json') {
        // Export as JSON
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="system-logs-' . date('Y-m-d') . '.json"');
        
        $export_data = [
            'generated' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username'] ?? 'Admin',
            'total_records' => $total_records,
            'filters' => [
                'log_type' => $log_type,
                'user_id' => $user_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'search' => $search
            ],
            'logs' => $logs
        ];
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
        
    } else {
        // Export as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="system-logs-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, [
            'ID', 'User ID', 'Username', 'Full Name', 'Email',
            'Activity Type', 'Description', 'IP Address', 'User Agent', 'Created At'
        ]);
        
        // Data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['user_id'] ?? '',
                $log['username'] ?? 'System',
                $log['full_name'] ?? '',
                $log['email'] ?? '',
                $log['activity_type'],
                $log['description'],
                $log['ip_address'] ?? '',
                $log['user_agent'] ?? '',
                $log['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
} catch(PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    die("Export failed: " . $e->getMessage());
} catch(Exception $e) {
    error_log("PDF Generation error: " . $e->getMessage());
    die("PDF Generation failed: " . $e->getMessage());
}

/**
 * Generate HTML for PDF export
 */
function generatePDFHTML($logs, $total_records, $unique_users, $activity_types, $first_record, $last_record, $log_type, $user_id, $start_date, $end_date, $search) {
    $company_name = getCompanyName();
    $current_time = date('F d, Y H:i:s');
    
    // Build filter description
    $filters = [];
    if (!empty($log_type)) $filters[] = "Type: " . ucwords(str_replace('_', ' ', $log_type));
    if (!empty($user_id)) $filters[] = "User ID: {$user_id}";
    if (!empty($start_date)) $filters[] = "From: {$start_date}";
    if (!empty($end_date)) $filters[] = "To: {$end_date}";
    if (!empty($search)) $filters[] = "Search: {$search}";
    $filter_text = !empty($filters) ? implode(' • ', $filters) : 'All Records';
    
    // Format dates
    $first_date = !empty($first_record) ? date('M d, Y H:i:s', strtotime($first_record)) : 'N/A';
    $last_date = !empty($last_record) ? date('M d, Y H:i:s', strtotime($last_record)) : 'N/A';
    
    $start_display = !empty($start_date) ? $start_date : 'All';
    $end_display = !empty($end_date) ? $end_date : 'All';
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Logs Export</title>
    <style>
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background: #fff;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4361ee;
        }
        
        .header h1 {
            color: #4361ee;
            font-size: 28px;
            margin: 0 0 10px 0;
        }
        
        .header p {
            color: #666;
            margin: 5px 0;
            font-size: 12px;
        }
        
        .report-info {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid #4361ee;
        }
        
        .report-info table {
            width: 100%;
            font-size: 12px;
        }
        
        .report-info td {
            padding: 5px;
        }
        
        .report-info td:first-child {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        
        .stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
        }
        
        .stat-box {
            flex: 1;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        
        .stat-box .number {
            font-size: 28px;
            font-weight: bold;
            color: #4361ee;
            margin-bottom: 5px;
        }
        
        .stat-box .label {
            font-size: 12px;
            color: #666;
        }
        
        .filters {
            background: #fff3e0;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 12px;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 9px;
        }
        
        th {
            background: #4361ee;
            color: white;
            padding: 10px 6px;
            text-align: left;
            font-weight: bold;
        }
        
        td {
            padding: 8px 6px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .activity-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: 600;
            display: inline-block;
        }
        
        .activity-badge.login { background: #d4edda; color: #155724; }
        .activity-badge.logout { background: #fff3cd; color: #856404; }
        .activity-badge.error { background: #f8d7da; color: #721c24; }
        .activity-badge.create { background: #d1ecf1; color: #0c5460; }
        .activity-badge.update { background: #cce5ff; color: #004085; }
        .activity-badge.delete { background: #f8d7da; color: #721c24; }
        .activity-badge.default { background: #e2e3e5; color: #383d41; }
        
        .system-badge {
            background: #e2e3e5;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            display: inline-block;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
        
        .ip-address {
            font-family: monospace;
            font-size: 8px;
        }
        
        .description {
            max-width: 300px;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>System Activity Logs Report</h1>
        <p>Generated on: {$current_time}</p>
        <p>{$company_name}</p>
    </div>
    
    <div class="stats">
        <div class="stat-box">
            <div class="number">{$total_records}</div>
            <div class="label">Total Logs</div>
        </div>
        <div class="stat-box">
            <div class="number">{$unique_users}</div>
            <div class="label">Active Users</div>
        </div>
        <div class="stat-box">
            <div class="number">{$activity_types}</div>
            <div class="label">Activity Types</div>
        </div>
        <div class="stat-box">
            <div class="number">{$first_date}</div>
            <div class="label">First Log</div>
        </div>
    </div>
    
    <div class="filters">
        <strong>Applied Filters:</strong> {$filter_text}
    </div>
    
    <div class="report-info">
        <table>
            <tr>
                <td>Report Type:</td>
                <td>System Activity Logs</td>
                <td>Total Records:</td>
                <td>{$total_records}</td>
            </tr>
            <tr>
                <td>Export Format:</td>
                <td>PDF</td>
                <td>Date Range:</td>
                <td>{$start_display} to {$end_display}</td>
            </tr>
            <tr>
                <td>Last Log:</td>
                <td>{$last_date}</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>
    
    <table>
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="10%">Timestamp</th>
                <th width="10%">User</th>
                <th width="10%">Activity</th>
                <th width="30%">Description</th>
                <th width="10%">IP Address</th>
            </tr>
        </thead>
        <tbody>
HTML;
    
    if (empty($logs)) {
        $html .= '<tr><td colspan="6" style="text-align: center; padding: 50px;">No logs found</td></tr>';
    } else {
        foreach ($logs as $log) {
            // Get badge class
            $badge_class = 'default';
            switch($log['activity_type']) {
                case 'login': $badge_class = 'login'; break;
                case 'logout': $badge_class = 'logout'; break;
                case 'error': $badge_class = 'error'; break;
                case 'create': $badge_class = 'create'; break;
                case 'update': $badge_class = 'update'; break;
                case 'delete': $badge_class = 'delete'; break;
            }
            
            $user_name = $log['username'] ? htmlspecialchars($log['username']) : '<span class="system-badge">System</span>';
            $activity_display = ucwords(str_replace('_', ' ', $log['activity_type']));
            $description = htmlspecialchars(substr($log['description'], 0, 150)) . (strlen($log['description']) > 150 ? '...' : '');
            $date = date('Y-m-d H:i:s', strtotime($log['created_at']));
            $ip = $log['ip_address'] ?? 'N/A';
            
            $html .= <<<ROW
            <tr>
                <td>{$log['id']}</td>
                <td>{$date}</td>
                <td>{$user_name}</td>
                <td><span class="activity-badge {$badge_class}">{$activity_display}</span></td>
                <td class="description">{$description}</td>
                <td class="ip-address">{$ip}</td>
            </tr>
ROW;
        }
    }
    
    $html .= <<<HTML
        </tbody>
    </table>
    
    <div class="footer">
        <p>This report was generated automatically by the system on {$current_time}</p>
        <p>© " . date('Y') . " {$company_name} - All Rights Reserved</p>
    </div>
</body>
</html>
HTML;
    
    return $html;
}

/**
 * Get company name from settings
 */
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