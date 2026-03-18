<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

$format = isset($_GET['format']) ? $_GET['format'] : 'json';

try {
    $db = getDB();
    
    // Get all settings
    $stmt = $db->query("SELECT * FROM settings ORDER BY `group`, setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="settings_export_' . date('Y-m-d') . '.json"');
        echo json_encode($settings, JSON_PRETTY_PRINT);
    } elseif ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="settings_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        if (!empty($settings)) {
            fputcsv($output, array_keys($settings[0]));
            
            // Add data
            foreach ($settings as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
    }
    
    // Log export
    $log = $db->prepare("INSERT INTO import_export_logs (type, filename, settings_count, user_id, status, created_at) VALUES ('export', ?, ?, ?, 'success', NOW())");
    $log->execute(['settings_export_' . date('Y-m-d') . '.' . $format, count($settings), $_SESSION['user_id']]);
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}