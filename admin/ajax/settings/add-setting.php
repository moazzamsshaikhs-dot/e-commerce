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

$setting_key = $_POST['setting_key'] ?? '';
$display_name = $_POST['display_name'] ?? '';
$group = $_POST['group'] ?? '';
$category = $_POST['category'] ?? '';
$setting_type = $_POST['setting_type'] ?? 'text';
$default_value = $_POST['default_value'] ?? '';
$options = $_POST['options'] ?? '';
$validation_rules = $_POST['validation_rules'] ?? '';
$help_text = $_POST['help_text'] ?? '';
$sort_order = $_POST['sort_order'] ?? 0;
$is_required = isset($_POST['is_required']) ? 1 : 0;
$is_public = isset($_POST['is_public']) ? 1 : 0;

if (empty($setting_key) || empty($display_name) || empty($group)) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    exit;
}

// Validate setting key format
if (!preg_match('/^[a-z0-9_]+$/', $setting_key)) {
    echo json_encode(['success' => false, 'message' => 'Setting key must contain only lowercase letters, numbers, and underscores']);
    exit;
}

try {
    $db = getDB();
    
    // Check if setting key already exists
    $stmt = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->execute([$setting_key]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Setting key already exists']);
        exit;
    }
    
    // Insert new setting
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, category, `group`, sort_order, options, validation_rules, help_text, is_required, is_public) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $setting_key,
        $default_value,
        $setting_type,
        $category,
        $group,
        $sort_order,
        $options,
        $validation_rules,
        $help_text,
        $is_required,
        $is_public
    ]);
    
    // Record activity
    $stmt = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description) VALUES (?, 'setting_added', ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        "Added new setting: {$setting_key} ({$display_name}) to group: {$group}"
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Setting added successfully',
        'setting_key' => $setting_key,
        'group' => $group
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>