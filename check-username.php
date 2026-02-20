<?php
// check-username.php
require_once 'includes/config.php';

header('Content-Type: application/json');

$username = isset($_GET['username']) ? sanitize($_GET['username']) : '';

if (empty($username) || strlen($username) < 3) {
    echo json_encode(['available' => false, 'message' => 'Username too short']);
    exit();
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    $available = $stmt->rowCount() === 0;
    
    echo json_encode([
        'available' => $available,
        'message' => $available ? 'Username available' : 'Username taken'
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['available' => false, 'message' => 'Error checking username']);
}
?>