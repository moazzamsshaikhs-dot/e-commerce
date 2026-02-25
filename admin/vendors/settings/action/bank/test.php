<?php
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Test successful',
    'received' => $_POST
]);