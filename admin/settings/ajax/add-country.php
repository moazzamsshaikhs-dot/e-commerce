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
$currency_code = $_POST['currency_code'] ?? 'USD';
$currency_symbol = $_POST['currency_symbol'] ?? '$';
$phone_code = $_POST['phone_code'] ?? '';
$continent = $_POST['continent'] ?? 'Other';
$is_active = isset($_POST['is_active']) ? 1 : 1;

if (empty($code) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Country code and name are required']);
    exit;
}

// Validate code format (2 letters)
if (!preg_match('/^[A-Z]{2}$/', $code)) {
    echo json_encode(['success' => false, 'message' => 'Country code must be 2 uppercase letters']);
    exit;
}

try {
    $db = getDB();
    
    // Check if country already exists
    $stmt = $db->prepare("SELECT code FROM countries WHERE code = ?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Country code already exists']);
        exit;
    }
    
    // Insert new country
    $stmt = $db->prepare("
        INSERT INTO countries (code, name, currency_code, currency_symbol, phone_code, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$code, $name, $currency_code, $currency_symbol, $phone_code, $is_active]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Country added successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}