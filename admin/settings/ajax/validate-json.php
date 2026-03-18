<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$json = $_POST['json'] ?? '';

if (empty($json)) {
    echo json_encode(['valid' => true]);
    exit;
}

$decoded = json_decode($json, true);

if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'valid' => false,
        'error' => json_last_error_msg()
    ]);
} else {
    echo json_encode(['valid' => true]);
}