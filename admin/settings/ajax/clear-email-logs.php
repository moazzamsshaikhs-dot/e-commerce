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
    $backup = $db->query("SELECT * FROM email_logs ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($backup)) {
        $backup_dir = '../../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        $backup_file = $backup_dir . 'email_logs_backup_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($backup_file, json_encode([
            'backup_date' => date('Y-m-d H:i:s'),
            'backup_by' => $_SESSION['username'] ?? 'Admin',
            'total_logs' => count($backup),
            'logs' => $backup
        ], JSON_PRETTY_PRINT));
        
        $backup_size = filesize($backup_file);
    }
    
    // Clear all logs
    $db->exec("TRUNCATE TABLE email_logs");
    
    echo json_encode([
        'success' => true,
        'message' => 'All email logs cleared successfully',
        'backup_created' => !empty($backup),
        'backup_file' => $backup_file ?? null,
        'backup_size' => formatBytes($backup_size ?? 0)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function formatBytes($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}