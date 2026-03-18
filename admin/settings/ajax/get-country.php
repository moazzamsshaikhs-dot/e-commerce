<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$code = isset($_GET['code']) ? $_GET['code'] : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Country code is required']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM countries WHERE code = ?");
    $stmt->execute([$code]);
    $country = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$country) {
        echo json_encode(['success' => false, 'message' => 'Country not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'country' => $country
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}