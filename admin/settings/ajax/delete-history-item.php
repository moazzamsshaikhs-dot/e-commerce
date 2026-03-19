<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$log_id = isset($input['log_id']) ? (int)$input['log_id'] : 0;

if ($log_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("DELETE FROM import_export_logs WHERE id = ?");
    $stmt->execute([$log_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'History item deleted successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}