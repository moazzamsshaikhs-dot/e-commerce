<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$key = $_GET['key'] ?? '';

if (empty($key)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $exists = $stmt->fetch() ? true : false;
    
    echo json_encode(['exists' => $exists]);
    
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}