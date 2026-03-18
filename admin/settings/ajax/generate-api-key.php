<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$name = $input['name'] ?? '';
$user_id = $input['user_id'] ?? null;
$rate_limit = $input['rate_limit'] ?? 100;
$expires_at = $input['expires_at'] ?? null;
$permissions = $input['permissions'] ?? [];

// Validate input
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'API key name is required']);
    exit;
}

try {
    $db = getDB();
    
    // Generate unique API key and secret
    $api_key = bin2hex(random_bytes(16));
    $api_secret = bin2hex(random_bytes(32));
    
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        INSERT INTO api_keys (name, api_key, api_secret, user_id, rate_limit, expires_at, permissions, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $permissions_json = json_encode($permissions);
    
    $stmt->execute([
        $name,
        $api_key,
        $api_secret,
        $user_id,
        $rate_limit,
        $expires_at,
        $permissions_json
    ]);
    
    $api_key_id = $db->lastInsertId();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'API key generated successfully',
        'api_key_id' => $api_key_id,
        'api_key' => $api_key,
        'api_secret' => $api_secret,
        'name' => $name
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}