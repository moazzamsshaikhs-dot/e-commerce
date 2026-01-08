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
$cache_type = $data['cache_type'] ?? '';

if (empty($cache_type)) {
    echo json_encode(['success' => false, 'message' => 'Cache type is required']);
    exit;
}

$message = '';
$success = false;

try {
    switch ($cache_type) {
        case 'opcache':
            if (function_exists('opcache_reset')) {
                if (@opcache_reset()) {
                    $success = true;
                    $message = 'OPcache cleared successfully';
                } else {
                    $message = 'Failed to clear OPcache';
                }
            } else {
                $message = 'OPcache is not enabled on your server';
            }
            break;
            
        case 'apc':
            if (function_exists('apc_clear_cache')) {
                @apc_clear_cache();
                @apc_clear_cache('user');
                $success = true;
                $message = 'APC cache cleared successfully';
            } else {
                $message = 'APC is not enabled on your server';
            }
            break;
            
        case 'apcu':
            if (function_exists('apcu_clear_cache')) {
                if (@apcu_clear_cache()) {
                    $success = true;
                    $message = 'APCu cache cleared successfully';
                } else {
                    $message = 'Failed to clear APCu cache';
                }
            } else {
                $message = 'APCu is not enabled on your server';
            }
            break;
            
        case 'file_cache':
            $cache_dir = '../../cache/';
            $deleted_count = 0;
            
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '*');
                foreach ($files as $file) {
                    if (is_file($file) && !is_dir($file)) {
                        if (@unlink($file)) {
                            $deleted_count++;
                        }
                    }
                }
                $success = true;
                $message = 'File cache cleared successfully (' . $deleted_count . ' files)';
            } else {
                $message = 'Cache directory not found';
            }
            break;
            
        case 'database_cache':
            // Clear database cache by deleting cache table entries
            try {
                $db = getDB();
                // Assuming you have a cache table
                $db->query("DELETE FROM cache WHERE expires_at < NOW()");
                $success = true;
                $message = 'Database cache cleared successfully';
            } catch (Exception $e) {
                $message = 'No database cache found or error clearing it';
            }
            break;
            
        case 'session_cache':
            // Clear current session
            session_start();
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            session_start(); // Restart session
            $success = true;
            $message = 'Session cache cleared successfully';
            break;
            
        default:
            $message = 'Unknown cache type';
            break;
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'cache_type' => $cache_type
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>