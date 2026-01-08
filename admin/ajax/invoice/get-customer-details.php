<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

header('Content-Type: application/json');

try {
    $db = getDB();
    
    $customer_id = (int)$_GET['id'];
    
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, address 
        FROM users 
        WHERE id = ? AND user_type = 'user'
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        echo json_encode([
            'success' => true,
            'customer' => $customer
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found.'
        ]);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}