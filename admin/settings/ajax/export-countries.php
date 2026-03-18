<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

try {
    $db = getDB();
    
    // Get all countries
    $stmt = $db->query("SELECT code, name, currency_code, currency_symbol, phone_code, is_active FROM countries ORDER BY name");
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="countries_export_' . date('Y-m-d') . '.json"');
        echo json_encode($countries, JSON_PRETTY_PRINT);
        
    } elseif ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="countries_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, ['Code', 'Name', 'Currency Code', 'Currency Symbol', 'Phone Code', 'Status']);
        
        // Add data
        foreach ($countries as $country) {
            fputcsv($output, [
                $country['code'],
                $country['name'],
                $country['currency_code'] ?? 'USD',
                $country['currency_symbol'] ?? '$',
                $country['phone_code'] ?? '',
                $country['is_active'] ? 'Active' : 'Inactive'
            ]);
        }
        
        fclose($output);
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}