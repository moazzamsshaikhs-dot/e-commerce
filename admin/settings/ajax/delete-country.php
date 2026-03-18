<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$code = $input['code'] ?? '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Country code is required']);
    exit;
}

try {
    $db = getDB();
    
    // Check if country is in use (optional - you might want to prevent deletion if used elsewhere)
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE country = ?");
    $stmt->execute([$code]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete country that is in use']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM countries WHERE code = ?");
    $stmt->execute([$code]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Country deleted successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}