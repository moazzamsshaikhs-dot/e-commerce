<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$api_key_id = $input['api_key_id'] ?? 0;
$is_active = isset($input['is_active']) ? (int)$input['is_active'] : 0;

if ($api_key_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid API key ID']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE api_keys SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$is_active, $api_key_id]);
    
    $status = $is_active ? 'activated' : 'deactivated';
    
    echo json_encode([
        'success' => true,
        'message' => "API key {$status} successfully"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}