<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$api_key = $_GET['api_key'] ?? '';

if (empty($api_key)) {
    echo json_encode(['valid' => false]);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id FROM api_keys WHERE api_key = ? AND is_active = 1");
    $stmt->execute([$api_key]);
    $exists = $stmt->fetch() ? true : false;
    
    echo json_encode(['valid' => $exists]);
    
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'error' => $e->getMessage()]);
}