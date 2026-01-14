<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'clear_all':
            $stmt = $db->prepare("DELETE FROM user_activities WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            logUserActivity($user_id, 'activity_clear', 'Cleared all activity logs');
            echo json_encode(['success' => true, 'message' => 'Activity logs cleared']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Activity AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}