<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get form data
$code = $_POST['code'] ?? '';
$name = $_POST['name'] ?? '';
$currency_code = $_POST['currency_code'] ?? '';
$currency_symbol = $_POST['currency_symbol'] ?? '';
$phone_code = $_POST['phone_code'] ?? '';
$continent = $_POST['continent'] ?? '';
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (empty($code) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Country code and name are required']);
    exit;
}

try {
    $db = getDB();
    
    // Check if country exists
    $stmt = $db->prepare("SELECT * FROM countries WHERE code = ?");
    $stmt->execute([$code]);
    $country = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$country) {
        echo json_encode(['success' => false, 'message' => 'Country not found']);
        exit;
    }
    
    // Update country
    $stmt = $db->prepare("
        UPDATE countries 
        SET name = ?, currency_code = ?, currency_symbol = ?, phone_code = ?, is_active = ?, updated_at = NOW()
        WHERE code = ?
    ");
    
    $stmt->execute([$name, $currency_code, $currency_symbol, $phone_code, $is_active, $code]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Country updated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}