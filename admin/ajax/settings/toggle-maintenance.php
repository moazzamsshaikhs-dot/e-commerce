<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$enabled = $input['enabled'] ?? false;

try {
    $db = getDB();
    
    // Update maintenance setting
    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
    $stmt->execute([$enabled ? '1' : '0']);
    
    // Create maintenance file if enabled
    if ($enabled) {
        $maintenance_file = '../../maintenance.lock';
        file_put_contents($maintenance_file, 'Maintenance mode enabled at ' . date('Y-m-d H:i:s'));
    } else {
        // Remove maintenance file
        $maintenance_file = '../../maintenance.lock';
        if (file_exists($maintenance_file)) {
            unlink($maintenance_file);
        }
    }
    
    // Record activity
    $status = $enabled ? 'enabled' : 'disabled';
    $stmt = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description) VALUES (?, 'maintenance_mode', ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        "Maintenance mode {$status}"
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "Maintenance mode {$status} successfully"
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>