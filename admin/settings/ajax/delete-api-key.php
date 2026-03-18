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
    
    $db->beginTransaction();
    
    // Delete related logs first
    $stmt = $db->prepare("DELETE FROM api_logs WHERE api_key_id = ?");
    $stmt->execute([$api_key_id]);
    
    // Delete API key
    $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ?");
    $stmt->execute([$api_key_id]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'API key deleted successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}