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
$name = $input['name'] ?? '';
$rate_limit = $input['rate_limit'] ?? 100;
$expires_at = $input['expires_at'] ?? null;
$is_active = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

if ($api_key_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid API key ID']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'API key name is required']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        UPDATE api_keys 
        SET name = ?, rate_limit = ?, expires_at = ?, is_active = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $name,
        $rate_limit,
        $expires_at,
        $is_active,
        $api_key_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'API key updated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}