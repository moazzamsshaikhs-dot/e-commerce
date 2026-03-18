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
    
    // Get schedule details
    $stmt = $db->prepare("SELECT * FROM backup_schedules WHERE id = ?");
    $stmt->execute([$schedule_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$schedule) {
        throw new Exception('Schedule not found');
    }
    
    // Create backup based on schedule type
    $backup_name = 'scheduled_' . $schedule['backup_type'] . '_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_dir = '../../backups/';
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $filepath = $backup_dir . $backup_name;
    
    // Get all tables
    $tables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $sql = "-- Scheduled Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            $sql .= "INSERT INTO `$table` VALUES\n";
            $values = [];
            foreach ($rows as $row) {
                $row_values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $row_values[] = 'NULL';
                    } else {
                        $row_values[] = "'" . addslashes($value) . "'";
                    }
                }
                $values[] = "(" . implode(',', $row_values) . ")";
            }
            $sql .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    file_put_contents($filepath, $sql);
    
    // Update last run time
    $next_run = null;
    switch ($schedule['schedule_type']) {
        case 'daily':
            $next_run = date('Y-m-d ' . $schedule['time'] . ':00', strtotime('+1 day'));
            break;
        case 'weekly':
            $next_run = date('Y-m-d ' . $schedule['time'] . ':00', strtotime('next monday'));
            break;
        case 'monthly':
            $next_run = date('Y-m-01 ' . $schedule['time'] . ':00', strtotime('+1 month'));
            break;
    }
    
    $update_stmt = $db->prepare("
        UPDATE backup_schedules 
        SET last_run = NOW(), next_run = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $update_stmt->execute([$next_run, $schedule_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule executed successfully',
        'backup_file' => $backup_name
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}