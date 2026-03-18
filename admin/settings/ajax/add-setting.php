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
    
    // Get form data
    $setting_key = $_POST['setting_key'] ?? '';
    $display_name = $_POST['display_name'] ?? '';
    $group = $_POST['group'] ?? 'general';
    $category = $_POST['category'] ?? '';
    $setting_type = $_POST['setting_type'] ?? 'text';
    $default_value = $_POST['default_value'] ?? '';
    $options = $_POST['options'] ?? '';
    $validation_rules = $_POST['validation_rules'] ?? '';
    $help_text = $_POST['help_text'] ?? '';
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    // Validate required fields
    if (empty($setting_key)) {
        throw new Exception('Setting key is required');
    }
    
    if (empty($display_name)) {
        throw new Exception('Display name is required');
    }
    
    if (empty($group)) {
        throw new Exception('Setting group is required');
    }
    
    // Validate setting key format
    if (!preg_match('/^[a-z0-9_]+$/', $setting_key)) {
        throw new Exception('Setting key must contain only lowercase letters, numbers, and underscores');
    }
    
    // Check if setting key already exists
    $check_stmt = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $check_stmt->execute([$setting_key]);
    if ($check_stmt->fetch()) {
        throw new Exception('Setting key already exists');
    }
    
    // Validate options for select type
    if ($setting_type === 'select' && !empty($options)) {
        $decoded_options = json_decode($options, true);
        if ($decoded_options === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format for options');
        }
    }
    
    $db->beginTransaction();
    
    // Insert new setting
    $insert_stmt = $db->prepare("
        INSERT INTO settings (
            setting_key, 
            setting_value, 
            setting_type, 
            validation_rules, 
            is_public, 
            is_required, 
            help_text, 
            `group`, 
            category,
            sort_order,
            options,
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $insert_stmt->execute([
        $setting_key,
        $default_value,
        $setting_type,
        $validation_rules,
        $is_public,
        $is_required,
        $help_text,
        $group,
        $category,
        $sort_order,
        $options
    ]);
    
    $setting_id = $db->lastInsertId();
    
    // Log the creation in settings history
    $log_stmt = $db->prepare("
        INSERT INTO settings_history (
            setting_key, 
            old_value, 
            new_value, 
            changed_by, 
            changed_at
        ) VALUES (?, 'NEW', ?, ?, NOW())
    ");
    $log_stmt->execute([$setting_key, $default_value, $_SESSION['user_id']]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Setting created successfully',
        'setting_id' => $setting_id,
        'setting_key' => $setting_key,
        'group' => $group
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}