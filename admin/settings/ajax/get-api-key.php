<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid API key ID']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT ak.*, u.username, u.email 
        FROM api_keys ak 
        LEFT JOIN users u ON ak.user_id = u.id 
        WHERE ak.id = ?
    ");
    $stmt->execute([$id]);
    $api_key = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$api_key) {
        echo json_encode(['success' => false, 'message' => 'API key not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'api_key' => $api_key
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}