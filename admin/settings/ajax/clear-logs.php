<?php
// ajax/clear-logs.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Get count before deletion
    $count_sql = "SELECT COUNT(*) as total FROM user_activities";
    $count_stmt = $db->query($count_sql);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($total == 0) {
        echo json_encode(['success' => true, 'message' => 'Logs are already empty']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Log this action before clearing
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Insert clear action into admin_logs
    $log_sql = "INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, 'clear_logs', ?, ?, ?, NOW())";
    $log_stmt = $db->prepare($log_sql);
    $log_stmt->execute([
        $_SESSION['user_id'],
        "Cleared {$total} system activity logs",
        $ip_address,
        $user_agent
    ]);
    
    // Clear the logs
    $delete_sql = "TRUNCATE TABLE user_activities";
    $delete_stmt = $db->prepare($delete_sql);
    $delete_stmt->execute();
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully cleared {$total} log records"
    ]);
    
} catch(PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error clearing logs: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>