<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$filename = $input['filename'] ?? '';

if (empty($filename)) {
    echo json_encode(['success' => false, 'message' => 'No filename provided']);
    exit;
}

// Security: prevent directory traversal
$filename = basename($filename);
$backup_dir = '../../backups/';
$filepath = $backup_dir . $filename;

if (!file_exists($filepath)) {
    echo json_encode(['success' => false, 'message' => 'Backup file not found']);
    exit;
}

try {
    // Delete the file
    if (unlink($filepath)) {
        echo json_encode([
            'success' => true,
            'message' => 'Backup deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete backup file');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}