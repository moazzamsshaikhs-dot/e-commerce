<?php
// ajax/get-change-details.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid change ID']);
    exit;
}

$change_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    $sql = "SELECT sh.*, u.full_name, u.email, u.username
            FROM settings_history sh
            LEFT JOIN users u ON sh.changed_by = u.id
            WHERE sh.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$change_id]);
    $change = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$change) {
        echo json_encode(['success' => false, 'message' => 'Change record not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'change' => [
            'id' => $change['id'],
            'setting_key' => $change['setting_key'],
            'old_value' => $change['old_value'],
            'new_value' => $change['new_value'],
            'changed_at' => $change['changed_at'],
            'full_name' => $change['full_name'],
            'email' => $change['email'],
            'username' => $change['username'],
            'ip_address' => $change['ip_address'] ?? null,
            'user_agent' => $change['user_agent'] ?? null
        ]
    ]);
    
} catch(PDOException $e) {
    error_log("Error fetching change details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>