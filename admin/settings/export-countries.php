<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

// Check if DomPDF exists
$vendor_autoload = __DIR__ . '/../../vendor/autoload.php';

if (!file_exists($vendor_autoload)) {
    // Try alternative paths
    $alt_paths = [
        __DIR__ . '/../../../vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/vendor/autoload.php'
    ];
    
    foreach ($alt_paths as $path) {
        if (file_exists($path)) {
            $vendor_autoload = $path;
            break;
        }
    }
}

// Load DomPDF if available
$dompdf_available = false;
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
    $dompdf_available = class_exists('Dompdf\Dompdf');
} else {
    error_log("Vendor autoload not found at: " . __DIR__ . '/../../vendor/autoload.php');
}

use Dompdf\Dompdf;
use Dompdf\Options;

$format = isset($_GET['format']) ? $_GET['format'] : 'json';

try {
    $db = getDB();
    
    // Get all countries
    $stmt = $db->query("SELECT code, name, currency_code, currency_symbol, phone_code, is_active FROM countries ORDER BY name");
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $total_countries = count($countries);
    $active_countries = 0;
    foreach ($countries as $c) {
        if ($c['is_active']) $active_countries++;
    }
    
    // Get continent groupings
    $continents = [
        'Asia' => ['PK', 'IN', 'BD', 'AE', 'SA', 'MY', 'SG', 'ID', 'AF', 'CN', 'JP', 'KR', 'TH', 'VN', 'LK', 'NP'],
        'Europe' => ['GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'CH', 'SE', 'NO', 'DK', 'FI', 'PT', 'GR', 'IE', 'AT', 'PL', 'CZ', 'HU'],
        'North America' => ['US', 'CA', 'MX', 'GT', 'CU', 'DO', 'HT', 'JM', 'PR', 'BS'],
        'South America' => ['BR', 'AR', 'CL', 'CO', 'PE', 'VE', 'EC', 'BO', 'PY', 'UY', 'GY', 'SR'],
        'Africa' => ['ZA', 'NG', 'EG', 'KE', 'GH', 'TN', 'MA', 'DZ', 'UG', 'TZ', 'ZW', 'AO', 'CM'],
        'Oceania' => ['AU', 'NZ', 'PG', 'FJ', 'SB', 'VU', 'NC'],
        'Other' => []
    ];
    
    // Assign continent to each country
    foreach ($countries as &$country) {
        $country['continent'] = 'Other';
        foreach ($continents as $cont => $codes) {
            if (in_array($country['code'], $codes)) {
                $country['continent'] = $cont;
                break;
            }
        }
    }
    
    if ($format === 'json') {
        // JSON Export
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="countries_' . date('Y-m-d') . '.json"');
        
        $export_data = [
            'generated' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username'] ?? 'Admin',
            'statistics' => [
                'total_countries' => $total_countries,
                'active_countries' => $active_countries,
                'inactive_countries' => $total_countries - $active_countries
            ],
            'countries' => $countries
        ];
        
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($format === 'csv') {
        // CSV Export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="countries_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers
        fputcsv($output, ['Code', 'Country Name', 'Currency Code', 'Currency Symbol', 'Phone Code', 'Continent', 'Status']);
        
        // Add data
        foreach ($countries as $country) {
            fputcsv($output, [
                $country['code'],
                $country['name'],
                $country['currency_code'] ?? 'USD',
                $country['currency_symbol'] ?? '$',
                $country['phone_code'] ?? '',
                $country['continent'],
                $country['is_active'] ? 'Active' : 'Inactive'
            ]);
        }
        
        fclose($output);
        
    } elseif ($format === 'pdf') {
        // Check if DomPDF is available
        if (!$dompdf_available) {
            // Fallback to HTML if DomPDF not available
            header('Content-Type: text/html');
            echo '<html><head><title>Countries Export</title>';
            echo '<style>body{font-family:Arial; margin:20px;} table{border-collapse:collapse; width:100%;} th{background:#4361ee; color:white; padding:10px;} td{padding:8px; border-bottom:1px solid #ddd;}</style>';
            echo '</head><body>';
            echo '<h1>Countries List</h1>';
            echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
            echo '<table>';
            echo '<tr><th>Code</th><th>Country</th><th>Currency</th><th>Phone</th><th>Status</th></tr>';
            
            foreach ($countries as $country) {
                $status = $country['is_active'] ? 'Active' : 'Inactive';
                $currency = $country['currency_symbol'] . ' ' . $country['currency_code'];
                echo '<tr>';
                echo '<td>' . $country['code'] . '</td>';
                echo '<td>' . $country['name'] . '</td>';
                echo '<td>' . trim($currency) . '</td>';
                echo '<td>' . ($country['phone_code'] ?? 'N/A') . '</td>';
                echo '<td>' . $status . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            echo '</body></html>';
            exit;
        }
        
        // PDF Export with DomPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Generate HTML for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Countries Export</title>
            <style>
                body { font-family: Helvetica, Arial, sans-serif; margin: 20px; color: #333; }
                h1 { color: #4361ee; border-bottom: 2px solid #4361ee; padding-bottom: 10px; }
                .header-info { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
                .stats { display: flex; justify-content: space-between; margin: 20px 0; }
                .stat-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; text-align: center; flex: 1; margin: 0 10px; }
                .stat-value { font-size: 24px; font-weight: bold; color: #4361ee; }
                .stat-label { color: #6c757d; font-size: 12px; text-transform: uppercase; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px; }
                th { background: #4361ee; color: white; padding: 8px; text-align: left; }
                td { padding: 6px 8px; border-bottom: 1px solid #dee2e6; }
                tr:nth-child(even) { background: #f8f9fa; }
                .status-active { color: #06d6a0; font-weight: bold; }
                .status-inactive { color: #ef476f; font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; color: #6c757d; font-size: 10px; border-top: 1px solid #dee2e6; padding-top: 10px; }
                .continent-section { margin-top: 20px; }
                .continent-title { background: #e9ecef; padding: 8px 12px; border-radius: 5px; font-weight: bold; color: #495057; }
            </style>
        </head>
        <body>
            <h1>🌍 Countries List</h1>
            
            <div class="header-info">
                <table style="width: 100%; color: white; background: transparent;">
                    <tr>
                        <td><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</td>
                        <td><strong>Generated By:</strong> ' . ($_SESSION['username'] ?? 'Admin') . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-value">' . $total_countries . '</div>
                    <div class="stat-label">Total Countries</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">' . $active_countries . '</div>
                    <div class="stat-label">Active Countries</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">' . ($total_countries - $active_countries) . '</div>
                    <div class="stat-label">Inactive Countries</div>
                </div>
            </div>';
        
        // Group by continent
        $grouped = [];
        foreach ($countries as $country) {
            $grouped[$country['continent']][] = $country;
        }
        
        foreach ($grouped as $continent => $cont_countries) {
            $html .= '
            <div class="continent-section">
                <div class="continent-title">' . $continent . ' (' . count($cont_countries) . ' countries)</div>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Country Name</th>
                            <th>Currency</th>
                            <th>Phone Code</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($cont_countries as $country) {
                $status_class = $country['is_active'] ? 'status-active' : 'status-inactive';
                $status_text = $country['is_active'] ? 'Active' : 'Inactive';
                $currency = $country['currency_symbol'] . ' ' . $country['currency_code'];
                
                $html .= '
                        <tr>
                            <td><strong>' . $country['code'] . '</strong></td>
                            <td>' . $country['name'] . '</td>
                            <td>' . trim($currency) . '</td>
                            <td>' . ($country['phone_code'] ?? 'N/A') . '</td>
                            <td class="' . $status_class . '">' . $status_text . '</td>
                        </tr>';
            }
            
            $html .= '
                    </tbody>
                </table>
            </div>';
        }
        
        $html .= '
            <div class="footer">
                Generated by E-Commerce System | Page 1 of 1
            </div>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        // Output PDF
        $dompdf->stream("countries_" . date('Y-m-d') . ".pdf", ['Attachment' => true]);
        
    } else {
        http_response_code(400);
        echo 'Invalid format specified';
    }
    
    // Log export activity
    try {
        $log = $db->prepare("INSERT INTO import_export_logs (type, filename, settings_count, user_id, status, created_at) VALUES ('export', ?, ?, ?, 'success', NOW())");
        $log->execute(['countries_export_' . date('Y-m-d') . '.' . $format, $total_countries, $_SESSION['user_id']]);
    } catch (Exception $e) {
        // Silently fail if log table doesn't exist
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
?>