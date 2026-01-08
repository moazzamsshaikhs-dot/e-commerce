<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$cleared = [];

// Clear OPcache if enabled
if (function_exists('opcache_reset')) {
    @opcache_reset();
    $cleared[] = 'OPcache';
}

// Clear APC cache if enabled
if (function_exists('apc_clear_cache')) {
    @apc_clear_cache();
    @apc_clear_cache('user');
    $cleared[] = 'APC cache';
}

// Clear APCu cache if enabled
if (function_exists('apcu_clear_cache')) {
    @apcu_clear_cache();
    $cleared[] = 'APCu cache';
}

// Clear file cache
$cache_dir = '../cache/';
if (is_dir($cache_dir)) {
    // Use glob instead of RecursiveIteratorIterator for safety
    $files = glob($cache_dir . '*');
    $deleted_count = 0;
    foreach ($files as $file) {
        if (is_file($file) && !is_dir($file)) {
            if (@unlink($file)) {
                $deleted_count++;
            }
        }
    }
    if ($deleted_count > 0) {
        $cleared[] = 'File cache (' . $deleted_count . ' files)';
    }
}

// Clear session data (only for current session)
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
session_start(); // Restart session for admin
$cleared[] = 'Session cache';

// Also clear browser cache headers
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

echo json_encode([
    'success' => true,
    'message' => 'Cache cleared successfully',
    'cleared_items' => $cleared
]);
?>