<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $db = getDB();
    
    // Create backup before clearing
    $backup = $db->query("SELECT * FROM import_export_logs ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($backup)) {
        $backup_dir = '../../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        $backup_file = $backup_dir . 'import_export_history_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($backup_file, json_encode($backup, JSON_PRETTY_PRINT));
    }
    
    // Clear history
    $db->exec("TRUNCATE TABLE import_export_logs");
    
    echo json_encode([
        'success' => true,
        'message' => 'Import/Export history cleared successfully',
        'backup_created' => !empty($backup)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}