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
    
    // Get all groups
    $groups = $db->query("SELECT slug, name FROM settings_groups WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get settings by group
    $group_counts = [];
    foreach ($groups as $group) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE `group` = ?");
        $stmt->execute([$group['slug']]);
        $group_counts[$group['slug']] = $stmt->fetchColumn();
    }
    
    // Get all settings for selection
    $settings = $db->query("SELECT setting_key, `group` FROM settings ORDER BY `group`, setting_key")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'groups' => $groups,
        'group_counts' => $group_counts,
        'settings' => $settings
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}