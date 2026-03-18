<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$enabled = $input['enabled'] ?? false;

try {
    $db = getDB();
    
    // Check if maintenance setting exists
    $stmt = $db->prepare("SELECT id FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing
        $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = 'maintenance_mode'");
        $stmt->execute([$enabled ? '1' : '0']);
    } else {
        // Insert new
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, `group`, created_at, updated_at) VALUES ('maintenance_mode', ?, 'boolean', 'general', NOW(), NOW())");
        $stmt->execute([$enabled ? '1' : '0']);
    }
    
    // Log the change
    $stmt = $db->prepare("INSERT INTO settings_history (setting_key, old_value, new_value, changed_by, changed_at) VALUES ('maintenance_mode', ?, ?, ?, NOW())");
    $stmt->execute([$enabled ? '0' : '1', $enabled ? '1' : '0', $_SESSION['user_id']]);
    
    // Create or remove .maintenance file
    $maintenance_file = '../../../.maintenance';
    if ($enabled) {
        file_put_contents($maintenance_file, 'Maintenance mode active since: ' . date('Y-m-d H:i:s'));
    } else {
        if (file_exists($maintenance_file)) {
            unlink($maintenance_file);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Maintenance mode ' . ($enabled ? 'enabled' : 'disabled')]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}