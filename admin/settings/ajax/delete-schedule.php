<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$schedule_id = $input['schedule_id'] ?? 0;

if ($schedule_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("DELETE FROM backup_schedules WHERE id = ?");
    $stmt->execute([$schedule_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule deleted successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}