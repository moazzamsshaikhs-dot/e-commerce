<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$settings = [
    'success' => true,
    'message' => 'Cache settings loaded',
    'opcache' => [
        'enabled' => function_exists('opcache_get_status') || 
                    (extension_loaded('Zend OPcache') && ini_get('opcache.enable')),
        'memory_consumption' => ini_get('opcache.memory_consumption'),
        'max_accelerated_files' => ini_get('opcache.max_accelerated_files'),
        'validate_timestamps' => ini_get('opcache.validate_timestamps')
    ],
    'apcu' => [
        'enabled' => function_exists('apcu_cache_info') && extension_loaded('apcu'),
        'shm_size' => ini_get('apc.shm_size')
    ],
    'directories' => []
];

// Check cache directories
$directories_to_check = ['../../cache/', '../../tmp/', sys_get_temp_dir()];

foreach ($directories_to_check as $dir) {
    if ($dir && is_dir($dir)) {
        $settings['directories'][] = [
            'path' => $dir,
            'writable' => is_writable($dir)
        ];
    }
}

echo json_encode($settings);
?>