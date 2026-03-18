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

if ($api_key_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid API key ID']);
    exit;
}

try {
    $db = getDB();
    
    // Revoke means set to inactive and optionally delete logs
    $stmt = $db->prepare("UPDATE api_keys SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$api_key_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'API key revoked successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}