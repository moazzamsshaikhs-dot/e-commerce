<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$template_id = $input['template_id'] ?? 0;

if ($template_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Template deleted successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}