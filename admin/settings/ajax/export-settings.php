<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

$format = isset($_GET['format']) ? $_GET['format'] : 'json';
$scope = isset($_GET['scope']) ? $_GET['scope'] : 'all';
$group = isset($_GET['group']) ? $_GET['group'] : '';
$selected = isset($_GET['settings']) ? (array)$_GET['settings'] : [];
$include_metadata = isset($_GET['include_metadata']);
$include_values = isset($_GET['include_values']);
$compress = isset($_GET['compress']);

try {
    $db = getDB();
    
    // Build query based on scope
    $sql = "SELECT * FROM settings";
    $params = [];
    
    if ($scope === 'group' && !empty($group)) {
        $sql .= " WHERE `group` = ?";
        $params[] = $group;
    } elseif ($scope === 'selected' && !empty($selected)) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $sql .= " WHERE setting_key IN ($placeholders)";
        $params = $selected;
    }
    
    $sql .= " ORDER BY `group`, setting_key";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare export data
    $export_data = [];
    
    if ($include_metadata) {
        $export_data = $settings;
    } else {
        foreach ($settings as $setting) {
            $item = ['setting_key' => $setting['setting_key']];
            if ($include_values) {
                $item['setting_value'] = $setting['setting_value'];
            }
            $export_data[] = $item;
        }
    }
    
    // Add metadata
    $result = [
        'generated' => date('Y-m-d H:i:s'),
        'generated_by' => $_SESSION['username'] ?? 'Admin',
        'total_settings' => count($settings),
        'scope' => $scope,
        'filters' => [
            'group' => $group,
            'selected' => $selected
        ],
        'settings' => $export_data
    ];
    
    // Handle different formats
    if ($format === 'json') {
        $output = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'settings_export_' . date('Y-m-d') . '.json';
        $content_type = 'application/json';
    } elseif ($format === 'csv') {
        $output = fopen('php://temp', 'r+');
        
        // Add UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers
        $headers = ['Setting Key'];
        if ($include_values) $headers[] = 'Setting Value';
        if ($include_metadata) {
            $headers = array_merge($headers, ['Type', 'Group', 'Required', 'Public', 'Validation', 'Help Text']);
        }
        fputcsv($output, $headers);
        
        // Add data
        foreach ($settings as $setting) {
            $row = [$setting['setting_key']];
            if ($include_values) $row[] = $setting['setting_value'];
            if ($include_metadata) {
                $row[] = $setting['setting_type'];
                $row[] = $setting['group'];
                $row[] = $setting['is_required'] ? 'Yes' : 'No';
                $row[] = $setting['is_public'] ? 'Yes' : 'No';
                $row[] = $setting['validation_rules'] ?? '';
                $row[] = $setting['help_text'] ?? '';
            }
            fputcsv($output, $row);
        }
        
        rewind($output);
        $output = stream_get_contents($output);
        $filename = 'settings_export_' . date('Y-m-d') . '.csv';
        $content_type = 'text/csv';
    } elseif ($format === 'xml') {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><settings></settings>');
        $xml->addChild('generated', date('Y-m-d H:i:s'));
        $xml->addChild('generated_by', $_SESSION['username'] ?? 'Admin');
        $xml->addChild('total', count($settings));
        
        foreach ($settings as $setting) {
            $item = $xml->addChild('setting');
            $item->addChild('key', $setting['setting_key']);
            if ($include_values) $item->addChild('value', $setting['setting_value']);
            if ($include_metadata) {
                $item->addChild('type', $setting['setting_type']);
                $item->addChild('group', $setting['group']);
                $item->addChild('required', $setting['is_required']);
                $item->addChild('public', $setting['is_public']);
                $item->addChild('validation', $setting['validation_rules'] ?? '');
                $item->addChild('help', $setting['help_text'] ?? '');
            }
        }
        
        $output = $xml->asXML();
        $filename = 'settings_export_' . date('Y-m-d') . '.xml';
        $content_type = 'application/xml';
    } elseif ($format === 'php') {
        $output = "<?php\n\n// Settings Export - Generated " . date('Y-m-d H:i:s') . "\n\nreturn " . var_export($result, true) . ";\n";
        $filename = 'settings_export_' . date('Y-m-d') . '.php';
        $content_type = 'text/plain';
    }
    
    // Log export activity
    $log = $db->prepare("INSERT INTO import_export_logs (type, filename, settings_count, user_id, status, created_at) VALUES ('export', ?, ?, ?, 'success', NOW())");
    $log->execute([$filename, count($settings), $_SESSION['user_id']]);
    
    // Handle compression
    if ($compress && in_array($format, ['json', 'csv', 'xml', 'php'])) {
        $zip = new ZipArchive();
        $zip_filename = sys_get_temp_dir() . '/export_' . uniqid() . '.zip';
        
        if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString($filename, $output);
            $zip->close();
            
            $output = file_get_contents($zip_filename);
            $filename = str_replace('.' . $format, '.zip', $filename);
            $content_type = 'application/zip';
            
            unlink($zip_filename);
        }
    }
    
    // Send headers
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($output));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $output;
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}