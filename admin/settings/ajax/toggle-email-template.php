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
$is_active = $input['is_active'] ?? 0;

if ($template_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE email_templates SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$is_active, $template_id]);
    
    $status = $is_active ? 'activated' : 'deactivated';
    
    echo json_encode([
        'success' => true,
        'message' => "Template {$status} successfully"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}   