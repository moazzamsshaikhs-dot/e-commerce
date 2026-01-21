<?php
session_start();
require_once '../../../includes/config.php';

// API endpoint for IFSC code validation (can be public)
header('Content-Type: application/json');

if (!isset($_GET['ifsc']) || empty($_GET['ifsc'])) {
    echo json_encode(['valid' => false, 'message' => 'IFSC code is required']);
    exit;
}

$ifsc_code = strtoupper(trim($_GET['ifsc']));

// Basic IFSC format validation
if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc_code)) {
    echo json_encode([
        'valid' => false, 
        'message' => 'Invalid IFSC format. Format: SBIN0001234 (4 letters, 0, 6 alphanumeric)'
    ]);
    exit;
}

try {
    // You can integrate with external IFSC validation API here
    // For now, we'll just validate the format
    
    // Example: Check against known bank codes (partial list)
    $bank_codes = ['SBIN', 'HDFC', 'ICIC', 'UTIB', 'CNRB', 'BARB', 'PUNB', 'IOBA'];
    $first_4 = substr($ifsc_code, 0, 4);
    
    if (!in_array($first_4, $bank_codes)) {
        echo json_encode([
            'valid' => true, 
            'message' => 'Format is valid, but bank code not in our list',
            'warning' => true
        ]);
        exit;
    }
    
    echo json_encode([
        'valid' => true,
        'message' => 'Valid IFSC code',
        'bank_code' => $first_4
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'valid' => false,
        'message' => 'Validation error: ' . $e->getMessage()
    ]);
}
?>