<?php
// ajax/start-update.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$version = isset($input['version']) ? $input['version'] : '';

if (empty($version)) {
    echo json_encode(['success' => false, 'message' => 'Version not specified']);
    exit;
}

try {
    $db = getDB();
    
    // Create update session
    $session_id = uniqid('update_', true);
    $update_logs = [];
    $update_progress = 0;
    
    // Initialize update session in database
    $stmt = $db->prepare("INSERT INTO update_sessions (session_id, version, status, progress, logs, started_at) 
                          VALUES (?, ?, 'running', 0, ?, NOW())");
    $stmt->execute([$session_id, $version, json_encode($update_logs)]);
    
    // Start update process in background (or handle synchronously)
    // For this example, we'll simulate the update steps
    
    // Create a log file for this update
    $log_file = __DIR__ . '/../../logs/update_' . date('Y-m-d_H-i-s') . '.log';
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0777, true);
    }
    
    // Log start
    $log_message = date('Y-m-d H:i:s') . " - Update started for version {$version}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'message' => 'Update process started',
        'session_id' => $session_id,
        'log_file' => basename($log_file)
    ]);
    
} catch(PDOException $e) {
    error_log("Start update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>