<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$code = $input['code'] ?? '';
$status = $input['status'] ?? 0;

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Country code is required']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE countries SET is_active = ?, updated_at = NOW() WHERE code = ?");
    $stmt->execute([$status, $code]);
    
    $action = $status ? 'activated' : 'deactivated';
    
    echo json_encode([
        'success' => true,
        'message' => "Country {$action} successfully"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}