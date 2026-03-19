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
    
    // Get all templates
    $stmt = $db->query("SELECT * FROM email_templates ORDER BY id DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="email_templates_' . date('Y-m-d') . '.json"');
        
        $export_data = [
            'generated' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username'] ?? 'Admin',
            'total_templates' => count($templates),
            'templates' => $templates
        ];
        
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="email_templates_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers
        fputcsv($output, ['ID', 'Template Key', 'Name', 'Subject', 'Variables', 'Active', 'Created', 'Updated']);
        
        // Add data
        foreach ($templates as $template) {
            fputcsv($output, [
                $template['id'],
                $template['template_key'],
                $template['name'],
                $template['subject'],
                $template['variables'] ?? '',
                $template['is_active'] ? 'Yes' : 'No',
                $template['created_at'],
                $template['updated_at']
            ]);
        }
        
        fclose($output);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}