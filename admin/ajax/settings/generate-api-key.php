<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = $data['name'] ?? '';
$user_id = $data['user_id'] ?? null;
$rate_limit = $data['rate_limit'] ?? 100;
$expires_at = $data['expires_at'] ?? null;
$permissions = $data['permissions'] ?? [];

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'API key name is required']);
    exit;
}

try {
    $db = getDB();
    
    // Generate unique API key and secret
    $api_key = bin2hex(random_bytes(32));
    $api_secret = bin2hex(random_bytes(32));
    
    // Insert API key
    $stmt = $db->prepare("INSERT INTO api_keys (name, api_key, api_secret, user_id, rate_limit, expires_at, permissions) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $name,
        $api_key,
        $api_secret,
        $user_id,
        $rate_limit,
        $expires_at,
        json_encode($permissions)
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'API key generated successfully',
        'name' => $name,
        'api_key' => $api_key,
        'api_secret' => $api_secret
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>