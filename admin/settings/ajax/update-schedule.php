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
$schedule_type = $input['schedule_type'] ?? '';
$backup_type = $input['backup_type'] ?? '';
$time = $input['time'] ?? '';
$keep_backups = $input['keep_backups'] ?? 30;
$is_active = $input['is_active'] ?? false;

if ($schedule_id <= 0 || empty($schedule_type) || empty($backup_type) || empty($time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $db = getDB();
    
    // Recalculate next run if schedule type changed
    $next_run = null;
    switch ($schedule_type) {
        case 'daily':
            $next_run = date('Y-m-d ' . $time . ':00', strtotime('+1 day'));
            break;
        case 'weekly':
            $next_run = date('Y-m-d ' . $time . ':00', strtotime('next monday'));
            break;
        case 'monthly':
            $next_run = date('Y-m-01 ' . $time . ':00', strtotime('+1 month'));
            break;
    }
    
    $stmt = $db->prepare("
        UPDATE backup_schedules 
        SET schedule_type = ?, backup_type = ?, time = ?, keep_backups = ?, next_run = ?, is_active = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $is_active_int = $is_active ? 1 : 0;
    
    $stmt->execute([$schedule_type, $backup_type, $time, $keep_backups, $next_run, $is_active_int, $schedule_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule updated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}