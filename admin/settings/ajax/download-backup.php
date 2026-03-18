<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

$filename = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($filename)) {
    die('No file specified');
}

$backup_dir = '../../backups/';
$filepath = $backup_dir . $filename;

if (!file_exists($filepath)) {
    die('File not found');
}

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file
readfile($filepath);
exit;