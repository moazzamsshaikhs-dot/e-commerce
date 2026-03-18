<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$schedule_type = $_POST['schedule_type'] ?? '';
$backup_type = $_POST['backup_type'] ?? '';
$time = $_POST['time'] ?? '';
$keep_backups = $_POST['keep_backups'] ?? 30;

if (empty($schedule_type) || empty($backup_type) || empty($time)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

try {
    $db = getDB();
    
    // Calculate next run time
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
        INSERT INTO backup_schedules (schedule_type, backup_type, time, keep_backups, next_run, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    
    $stmt->execute([$schedule_type, $backup_type, $time, $keep_backups, $next_run]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup schedule created successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}