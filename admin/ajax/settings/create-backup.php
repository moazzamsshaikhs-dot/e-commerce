<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? 'database';
$name = $data['name'] ?? 'backup_' . date('Y-m-d_H-i-s');
$compress = $data['compress'] ?? true;
$quick = $data['quick'] ?? false;

try {
    $db = getDB();
    
    // Create backup directory if it doesn't exist
    $backup_dir = '../../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $filename = $name;
    $filepath = $backup_dir . $filename;
    
    if ($type === 'database' || $type === 'full') {
        // Database backup
        $db_file = $filepath . '.sql';
        
        // Get all tables
        $tables = [];
        $stmt = $db->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $sql = "-- Database backup generated on " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Backup type: $type\n\n";
        
        foreach ($tables as $table) {
            // Drop table if exists
            $sql .= "DROP TABLE IF EXISTS `$table`;\n\n";
            
            // Create table
            $stmt = $db->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sql .= $row[1] . ";\n\n";
            
            // Insert data
            $stmt = $db->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                $columns = array_keys($rows[0]);
                $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES \n";
                
                $values = [];
                foreach ($rows as $row) {
                    $row_values = [];
                    foreach ($row as $value) {
                        $row_values[] = is_null($value) ? 'NULL' : $db->quote($value);
                    }
                    $values[] = '(' . implode(', ', $row_values) . ')';
                }
                
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        file_put_contents($db_file, $sql);
        
        if ($compress) {
            // Compress the file
            $zip = new ZipArchive();
            $zip_file = $filepath . '.zip';
            
            if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($db_file, basename($db_file));
                
                if ($type === 'full') {
                    // Add important directories
                    $directories = ['../../uploads/', '../../includes/', '../../admin/'];
                    foreach ($directories as $dir) {
                        if (is_dir($dir)) {
                            $files = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($dir),
                                RecursiveIteratorIterator::LEAVES_ONLY
                            );
                            
                            foreach ($files as $file) {
                                if (!$file->isDir()) {
                                    $filePath = $file->getRealPath();
                                    $relativePath = substr($filePath, strlen(dirname(__DIR__)) + 1);
                                    $zip->addFile($filePath, $relativePath);
                                }
                            }
                        }
                    }
                }
                
                $zip->close();
                unlink($db_file); // Remove uncompressed file
                $filename .= '.zip';
            }
        } else {
            $filename .= '.sql';
        }
    }
    
    // Record backup in database
    $stmt = $db->prepare("INSERT INTO backup_schedules (schedule_type, backup_type, last_run) VALUES ('manual', ?, NOW())");
    $stmt->execute([$type]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup created successfully',
        'filename' => $filename,
        'size' => formatBytes(filesize($backup_dir . $filename))
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function formatBytes($bytes, $decimals = 2) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}
?>