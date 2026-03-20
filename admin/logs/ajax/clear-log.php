<?php
// admin/logs/ajax/clear-log.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$log_file = dirname(dirname(__DIR__)) . '/logs/update_uploads.log';

if (file_exists($log_file)) {
    $new_content = "# Update Uploads Log\n";
    $new_content .= "# Cleared: " . date('Y-m-d H:i:s') . "\n";
    $new_content .= "# ========================================\n\n";
    
    if (file_put_contents($log_file, $new_content)) {
        echo json_encode(['success' => true, 'message' => 'Log cleared successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to clear log']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Log file not found']);
}
?>