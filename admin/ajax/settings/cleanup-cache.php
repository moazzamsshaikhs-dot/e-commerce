<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

function cleanDirectory($dir, $max_age = 604800) { // 7 days default
    $count = 0;
    $size = 0;
    
    if (!is_dir($dir)) return ['count' => 0, 'size' => 0];
    
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $age = time() - $file->getMTime();
                if ($age > $max_age) {
                    $size += $file->getSize();
                    if (@unlink($file->getPathname())) {
                        $count++;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Cleanup error: ' . $e->getMessage());
    }
    
    return ['count' => $count, 'size' => $size];
}

$results = [];
$total_freed = 0;

// Cache directories
$cache_dirs = [
    'Main Cache' => __DIR__ . '/../../cache/',
    'Temp Directory' => __DIR__ . '/../../tmp/',
    'System Temp' => sys_get_temp_dir() . '/ecommerce_cache/'
];

foreach ($cache_dirs as $name => $dir) {
    $result = cleanDirectory($dir, 604800); // 7 days
    $results[$name] = $result;
    $total_freed += $result['size'];
}

function formatBytes($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

echo json_encode([
    'success' => true,
    'message' => 'Old cache files cleaned successfully',
    'freed_space' => formatBytes($total_freed),
    'details' => $results
]);
?>