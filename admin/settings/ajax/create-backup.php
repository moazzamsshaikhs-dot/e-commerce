<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$type = $input['type'] ?? 'database';
$name = $input['name'] ?? '';
$compress = $input['compress'] ?? true;
$quick = $input['quick'] ?? false;

try {
    $db = getDB();
    
    // Create backups directory if not exists
    $backup_dir = '../../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    // Generate filename
    if (empty($name)) {
        $name = 'backup_' . date('Y-m-d_H-i-s');
    }
    
    $filename = $name . '.sql';
    $filepath = $backup_dir . $filename;
    
    if ($type === 'database' || $type === 'full') {
        // Get all tables
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $sql = "-- Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: " . DB_NAME . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            // Drop table if exists
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            
            // Get create table syntax
            $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sql .= $create[1] . ";\n\n";
            
            // Get table data
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
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write to file
        file_put_contents($filepath, $sql);
    }
    
    if ($type === 'files' || $type === 'full') {
        // For files backup, we would create a zip of uploads directory
        // This is a placeholder - implement based on your needs
    }
    
    // Get file size
    $size = filesize($filepath);
    
    // Log backup creation
    $log_stmt = $db->prepare("
        INSERT INTO backup_logs (filename, type, size, created_by, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $log_stmt->execute([$filename, $type, $size, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup created successfully',
        'filename' => $filename,
        'size' => formatBytes($size),
        'type' => $type
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function formatBytes($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}