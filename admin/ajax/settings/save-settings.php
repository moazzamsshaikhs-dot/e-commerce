<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$group = $_POST['group'] ?? '';
$settings = $_POST['settings'] ?? [];

if (empty($settings) || empty($group)) {
    echo json_encode(['success' => false, 'message' => 'No settings to save']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    $saved_count = 0;
    
    foreach ($settings as $key => $value) {
        // Get current value
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $old_value = $stmt->fetchColumn();
        
        // Update setting
        $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
        
        // Record in history if value changed
        if ($old_value != $value) {
            $stmt = $db->prepare("INSERT INTO settings_history (setting_key, old_value, new_value, changed_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$key, $old_value, $value, $_SESSION['user_id']]);
        }
        
        $saved_count++;
    }
    
    $db->commit();
    
    // Clear settings cache if exists
    if (function_exists('apc_delete')) {
        apc_delete('site_settings');
    }
    
    echo json_encode([
        'success' => true,
        'message' => "{$saved_count} settings saved successfully"
    ]);
    
} catch(Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}