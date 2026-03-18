<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$code = isset($_GET['code']) ? strtoupper($_GET['code']) : '';

if (empty($code)) {
    echo json_encode(['valid' => false]);
    exit;
}

// Validate format (2 letters)
if (!preg_match('/^[A-Z]{2}$/', $code)) {
    echo json_encode(['valid' => false, 'message' => 'Code must be 2 letters']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT code FROM countries WHERE code = ?");
    $stmt->execute([$code]);
    $exists = $stmt->fetch() ? true : false;
    
    echo json_encode([
        'valid' => !$exists,
        'exists' => $exists,
        'message' => $exists ? 'Country code already exists' : 'Country code is available'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'error' => $e->getMessage()]);
}