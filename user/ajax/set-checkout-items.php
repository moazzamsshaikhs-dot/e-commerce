<?php
session_start();
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['items']) || !is_array($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid items data']);
    exit;
}

// Validate and store items in session
$_SESSION['checkout_items'] = [];

foreach ($input['items'] as $item) {
    if (isset($item['product_id']) && isset($item['quantity'])) {
        $_SESSION['checkout_items'][] = [
            'product_id' => (int)$item['product_id'],
            'quantity' => (int)$item['quantity']
        ];
    }
}

echo json_encode([
    'success' => true,
    'count' => count($_SESSION['checkout_items'])
]);