<?php
// ajax/revert-change.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['change_id']) || !is_numeric($input['change_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid change ID']);
    exit;
}

$change_id = (int)$input['change_id'];

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get the change record
    $sql = "SELECT setting_key, old_value FROM settings_history WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$change_id]);
    $change = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$change) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Change record not found']);
        exit;
    }
    
    // Get current value
    $current_sql = "SELECT setting_value FROM settings WHERE setting_key = ?";
    $current_stmt = $db->prepare($current_sql);
    $current_stmt->execute([$change['setting_key']]);
    $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    $current_value = $current ? $current['setting_value'] : null;
    
    // If already reverted
    if ($current_value == $change['old_value']) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'This change has already been reverted']);
        exit;
    }
    
    // Revert the setting
    if ($change['old_value'] !== null) {
        $update_sql = "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute([$change['old_value'], $change['setting_key']]);
    } else {
        // If old value was NULL, we might need to delete or handle differently
        $delete_sql = "DELETE FROM settings WHERE setting_key = ?";
        $delete_stmt = $db->prepare($delete_sql);
        $delete_stmt->execute([$change['setting_key']]);
    }
    
    // Log the revert in history
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $history_sql = "INSERT INTO settings_history (setting_key, old_value, new_value, changed_by, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $history_stmt = $db->prepare($history_sql);
    $history_stmt->execute([
        $change['setting_key'],
        $current_value,
        $change['old_value'],
        $_SESSION['user_id'],
        $ip_address,
        $user_agent
    ]);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully reverted ' . $change['setting_key'] . ' to previous value'
    ]);
    
} catch(PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error reverting change: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>