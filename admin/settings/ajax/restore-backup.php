<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$filename = $input['filename'] ?? '';
$create_backup = $input['create_backup'] ?? true;
$verify_only = $input['verify_only'] ?? false;

if (empty($filename)) {
    echo json_encode(['success' => false, 'message' => 'No filename provided']);
    exit;
}

$backup_dir = '../../backups/';
$filepath = $backup_dir . $filename;

if (!file_exists($filepath)) {
    echo json_encode(['success' => false, 'message' => 'Backup file not found']);
    exit;
}

try {
    $db = getDB();
    
    if ($verify_only) {
        // Just verify the SQL file
        $content = file_get_contents($filepath);
        if (strpos($content, 'DROP TABLE') === false) {
            throw new Exception('Invalid backup file format');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup file verified successfully',
            'verify_only' => true
        ]);
        exit;
    }
    
    // Create backup before restore if requested
    if ($create_backup) {
        $backup_name = 'pre_restore_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = $backup_dir . $backup_name;
        
        // Get all tables
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $sql = "-- Pre-restore Backup\n";
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
        
        file_put_contents($backup_path, $sql);
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Read and execute SQL file
    $sql = file_get_contents($filepath);
    
    // Split SQL by semicolons
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $db->exec($query);
        }
    }
    
    $db->commit();
    
    // Log restore
    $log_stmt = $db->prepare("
        INSERT INTO restore_logs (filename, created_by, created_at) 
        VALUES (?, ?, NOW())
    ");
    $log_stmt->execute([$filename, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Database restored successfully',
        'backup_created' => $create_backup
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}