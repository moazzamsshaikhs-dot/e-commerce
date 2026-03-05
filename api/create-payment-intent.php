<?php
// C:\xampp\htdocs\e-commerce\api\create-payment-intent.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/payments/StripeGateway.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit();
}

// Validate required fields
if (!isset($input['amount']) || !isset($input['currency'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

try {
    // Initialize Stripe gateway
    $gateway = new StripeGateway();
    
    // Process payment
    $result = $gateway->processPayment(
        $input['amount'],
        $input['currency'],
        [
            'payment_method_id' => $input['payment_method_id'] ?? null,
            'user_id' => $_SESSION['user_id'],
            'order_id' => $input['order_id'] ?? 0
        ]
    );
    
    // Return result
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}