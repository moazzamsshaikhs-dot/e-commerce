<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$backup_dir = '../../backups/';
$backups = [];

if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    $all_backups = [];
    
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && (strpos($file, '.sql') !== false || strpos($file, '.zip') !== false)) {
            $filepath = $backup_dir . $file;
            $all_backups[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath),
                'type' => strpos($file, '.zip') !== false ? 'full' : 'database'
            ];
        }
    }
    
    // Sort by modified time (newest first)
    usort($all_backups, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    // Apply pagination
    $backups = array_slice($all_backups, $offset, $limit);
}

echo json_encode([
    'success' => true,
    'backups' => $backups,
    'total' => count($all_backups ?? [])
]);