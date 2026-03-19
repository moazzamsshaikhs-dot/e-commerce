<?php
// ajax/clear-history.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Check for CSRF token (optional but recommended)
if (!isset($_POST['csrf_token']) && !isset($_GET['confirm'])) {
    echo json_encode(['success' => false, 'message' => 'Please confirm clearing history']);
    exit;
}

try {
    $db = getDB();
    
    // Get count before deletion
    $count_sql = "SELECT COUNT(*) as total FROM settings_history";
    $count_stmt = $db->query($count_sql);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($total == 0) {
        echo json_encode(['success' => true, 'message' => 'History is already empty']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Log this action before clearing
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $log_sql = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) 
                VALUES (?, 'clear_history', ?, ?, ?)";
    $log_stmt = $db->prepare($log_sql);
    $log_stmt->execute([
        $_SESSION['user_id'],
        "Cleared {$total} settings history records",
        $ip_address,
        $user_agent
    ]);
    
    // Clear the history
    $delete_sql = "TRUNCATE TABLE settings_history";
    $delete_stmt = $db->prepare($delete_sql);
    $delete_stmt->execute();
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully cleared {$total} history records"
    ]);
    // In clear-history.php, replace the log insertion with:
$log_sql = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) 
            VALUES (?, 'clear_history', ?, ?, ?)";
$log_stmt = $db->prepare($log_sql);
$log_stmt->execute([
    $_SESSION['user_id'],
    "Cleared {$total} settings history records",
    $ip_address,
    $user_agent
]);
} catch(PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error clearing history: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>