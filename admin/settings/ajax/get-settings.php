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
    
    $group = isset($_GET['group']) ? $_GET['group'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $sql = "SELECT setting_key, `group`, setting_type FROM settings";
    $params = [];
    
    if (!empty($group)) {
        $sql .= " WHERE `group` = ?";
        $params[] = $group;
    }
    
    if (!empty($search)) {
        $sql .= empty($params) ? " WHERE" : " AND";
        $sql .= " (setting_key LIKE ? OR `group` LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY `group`, setting_key";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}