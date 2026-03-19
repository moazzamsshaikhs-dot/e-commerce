<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$file_ext = isset($_GET['ext']) ? $_GET['ext'] : '';

$valid_extensions = ['json', 'csv', 'xml', 'zip'];
$is_valid = in_array(strtolower($file_ext), $valid_extensions);

echo json_encode([
    'valid' => $is_valid,
    'message' => $is_valid ? 'Valid file format' : 'Invalid file format. Allowed: JSON, CSV, XML, ZIP'
]);