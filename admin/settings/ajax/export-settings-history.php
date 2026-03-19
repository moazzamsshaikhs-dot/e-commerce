<?php
// export-settings-history.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: index.php');
    exit;
}

// Get parameters
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
$filter_setting = isset($_GET['setting']) ? $_GET['setting'] : '';
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Load DOMPDF for PDF format
if ($format === 'pdf') {
    // Try multiple possible paths for DOMPDF
    $possible_paths = [
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/dompdf/autoload.inc.php',
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
        dirname(__DIR__, 3) . '/vendor/dompdf/autoload.inc.php',
        dirname(__DIR__, 3) . '/vendor/autoload.php',
        __DIR__ . '/../../../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../../../vendor/autoload.php',
        // Path based on your working export file
        $_SERVER['DOCUMENT_ROOT'] . '/../e-commerce/vendor/dompdf/autoload.inc.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../vendor/dompdf/autoload.inc.php'
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
        die("DOMPDF library not found. Please install it via Composer or check the installation path.");
    }
}

// Use statements MUST be in global scope
// Yeh tabhi execute honge jab DOMPDF loaded ho chuka hai
use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filter_setting)) {
        $where[] = "sh.setting_key LIKE ?";
        $params[] = "%$filter_setting%";
    }
    
    if (!empty($filter_user)) {
        $where[] = "sh.changed_by = ?";
        $params[] = $filter_user;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(sh.changed_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(sh.changed_at) <= ?";
        $params[] = $end_date;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get data
    $sql = "SELECT sh.id, sh.setting_key, sh.old_value, sh.new_value, 
                   sh.changed_at, u.full_name, u.email
            FROM settings_history sh
            LEFT JOIN users u ON sh.changed_by = u.id
            WHERE $where_sql
            ORDER BY sh.changed_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $total_records = count($data);
    $unique_settings = count(array_unique(array_column($data, 'setting_key')));
    
    // Get date range
    $first_record = !empty($data) ? $data[0]['changed_at'] : date('Y-m-d H:i:s');
    $last_record = !empty($data) ? end($data)['changed_at'] : date('Y-m-d H:i:s');
    
    if ($format === 'pdf') {
        // Generate PDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Generate HTML content for PDF
        $html = generatePDFHTML($data, $total_records, $unique_settings, $first_record, $last_record, $filter_setting, $filter_user, $start_date, $end_date);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        // Output PDF
        $dompdf->stream('settings-history-' . date('Y-m-d') . '.pdf', array('Attachment' => 1));
        exit;
        
    } elseif ($format === 'json') {
        // Export as JSON
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="settings-history-' . date('Y-m-d') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
        
    } else {
        // Export as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="settings-history-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, ['ID', 'Setting Key', 'Old Value', 'New Value', 'Changed By', 'Email', 'Changed At']);
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['setting_key'],
                $row['old_value'],
                $row['new_value'],
                $row['full_name'] ?? 'System',
                $row['email'] ?? '',
                $row['changed_at']
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
function generatePDFHTML($data, $total_records, $unique_settings, $first_record, $last_record, $filter_setting, $filter_user, $start_date, $end_date) {
    $company_name = getCompanyName();
    $current_time = date('F d, Y H:i:s');
    
    // Build filter description
    $filters = [];
    if (!empty($filter_setting)) $filters[] = "Setting: {$filter_setting}";
    if (!empty($filter_user)) {
        $filters[] = "User ID: {$filter_user}";
    }
    if (!empty($start_date)) $filters[] = "From: {$start_date}";
    if (!empty($end_date)) $filters[] = "To: {$end_date}";
    $filter_text = !empty($filters) ? implode(' • ', $filters) : 'All Records';
    
    // Format dates
    $first_date = !empty($first_record) ? date('M d, Y', strtotime($first_record)) : 'N/A';
    $last_date = !empty($last_record) ? date('M d, Y', strtotime($last_record)) : 'N/A';
    
    $start_display = !empty($start_date) ? $start_date : 'All';
    $end_display = !empty($end_date) ? $end_date : 'All';
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings History Export</title>
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
            font-size: 10px;
        }
        
        th {
            background: #4361ee;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .setting-key {
            font-family: monospace;
            color: #4361ee;
            font-weight: 600;
        }
        
        .value-preview {
            font-family: monospace;
            font-size: 9px;
            background: #f8f9fa;
            padding: 4px 6px;
            border-radius: 4px;
            max-width: 200px;
            word-break: break-word;
        }
        
        .system-badge {
            background: #e0e0e0;
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
        
        .value-null {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Settings Change History Report</h1>
        <p>Generated on: {$current_time}</p>
        <p>{$company_name}</p>
    </div>
    
    <div class="stats">
        <div class="stat-box">
            <div class="number">{$total_records}</div>
            <div class="label">Total Changes</div>
        </div>
        <div class="stat-box">
            <div class="number">{$unique_settings}</div>
            <div class="label">Unique Settings</div>
        </div>
        <div class="stat-box">
            <div class="number">{$first_date}</div>
            <div class="label">First Change</div>
        </div>
        <div class="stat-box">
            <div class="number">{$last_date}</div>
            <div class="label">Last Change</div>
        </div>
    </div>
    
    <div class="filters">
        <strong>Applied Filters:</strong> {$filter_text}
    </div>
    
    <div class="report-info">
         <table>
            <tr>
                <td>Report Type:</td>
                <td>Settings Change History</td>
                <td>Total Records:</td>
                <td>{$total_records}</td>
            </tr>
            <tr>
                <td>Export Format:</td>
                <td>PDF</td>
                <td>Date Range:</td>
                <td>{$start_display} to {$end_display}</td>
            </tr>
        </table>
    </div>
    
    <table>
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="15%">Setting Key</th>
                <th width="25%">Old Value</th>
                <th width="25%">New Value</th>
                <th width="15%">Changed By</th>
                <th width="15%">Changed At</th>
            </tr>
        </thead>
        <tbody>
HTML;
    
    if (empty($data)) {
        $html .= '<tr><td colspan="6" style="text-align: center; padding: 50px;">No records found</td></tr>';
    } else {
        foreach ($data as $row) {
            $old_value = $row['old_value'];
            $new_value = $row['new_value'];
            
            // Truncate long values
            if (strlen($old_value) > 100) $old_value = substr($old_value, 0, 100) . '...';
            if (strlen($new_value) > 100) $new_value = substr($new_value, 0, 100) . '...';
            
            $old_display = $old_value !== null ? 
                '<span class="value-preview">' . htmlspecialchars($old_value) . '</span>' : 
                '<span class="value-null">NULL</span>';
                
            $new_display = $new_value !== null ? 
                '<span class="value-preview">' . htmlspecialchars($new_value) . '</span>' : 
                '<span class="value-null">NULL</span>';
            
            $changed_by = !empty($row['full_name']) ? 
                htmlspecialchars($row['full_name']) : 
                '<span class="system-badge">System</span>';
            
            $date = date('Y-m-d H:i:s', strtotime($row['changed_at']));
            
            $html .= '<tr>';
            $html .= '<td>' . $row['id'] . '</td>';
            $html .= '<td><span class="setting-key">' . htmlspecialchars($row['setting_key']) . '</span></td>';
            $html .= '<td>' . $old_display . '</td>';
            $html .= '<td>' . $new_display . '</td>';
            $html .= '<td>' . $changed_by . '</td>';
            $html .= '<td>' . $date . '</td>';
            $html .= '</tr>';
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