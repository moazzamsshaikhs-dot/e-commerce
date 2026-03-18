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
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM backup_schedules WHERE id = ?");
    $stmt->execute([$id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$schedule) {
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'schedule' => $schedule
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}