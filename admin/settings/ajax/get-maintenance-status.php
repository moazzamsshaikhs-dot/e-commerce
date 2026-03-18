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
    
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    $maintenance_mode = $result ? ($result['setting_value'] == '1') : false;
    
    // Also check for .maintenance file
    if (file_exists('../../../.maintenance')) {
        $maintenance_mode = true;
    }
    
    echo json_encode([
        'success' => true,
        'maintenance_mode' => $maintenance_mode
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}