<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$results = [];
$total_freed = 0;

function clearDirectory($dir) {
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
                $size += $file->getSize();
                if (@unlink($file->getPathname())) {
                    $count++;
                }
            } elseif ($file->isDir()) {
                @rmdir($file->getPathname());
            }
        }
    } catch (Exception $e) {
        error_log('Cache clear error: ' . $e->getMessage());
    }
    
    return ['count' => $count, 'size' => $size];
}

// Clear OPcache
if (function_exists('opcache_reset')) {
    $results['opcache'] = opcache_reset();
}

// Clear APCu
if (function_exists('apcu_clear_cache')) {
    $results['apcu'] = apcu_clear_cache();
}

// Clear file cache
$cache_dirs = [
    '../../../cache/' => clearDirectory('../../../cache/'),
    '../../../tmp/' => clearDirectory('../../../tmp/'),
    sys_get_temp_dir() . '/ecommerce_cache/' => clearDirectory(sys_get_temp_dir() . '/ecommerce_cache/')
];

foreach ($cache_dirs as $dir => $result) {
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
    'message' => 'Cache cleared successfully. Freed ' . formatBytes($total_freed),
    'freed_space' => formatBytes($total_freed),
    'details' => $results
]);