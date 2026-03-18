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
    
    // Generate new secret
    $new_secret = bin2hex(random_bytes(32));
    
    $stmt = $db->prepare("UPDATE api_keys SET api_secret = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_secret, $api_key_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'API secret regenerated successfully',
        'api_secret' => $new_secret
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}