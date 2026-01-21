<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);
        
        if ($withdrawal_id <= 0) {
            throw new Exception('Invalid withdrawal ID');
        }
        
        // Verify withdrawal belongs to vendor and is pending
        $stmt = $db->prepare("
            SELECT id, status, withdrawal_amount 
            FROM vendor_withdrawals 
            WHERE id = ? AND vendor_id = ? AND status = 'pending'
        ");
        $stmt->execute([$withdrawal_id, $vendor_id]);
        $withdrawal = $stmt->fetch();
        
        if (!$withdrawal) {
            throw new Exception('Withdrawal not found or cannot be cancelled');
        }
        
        // Check if withdrawal was made within last 24 hours
        $stmt = $db->prepare("
            SELECT TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_ago 
            FROM vendor_withdrawals 
            WHERE id = ?
        ");
        $stmt->execute([$withdrawal_id]);
        $time_check = $stmt->fetch();
        
        if ($time_check && $time_check['hours_ago'] > 24) {
            throw new Exception('Withdrawal can only be cancelled within 24 hours');
        }
        
        // Cancel the withdrawal
        $stmt = $db->prepare("
            UPDATE vendor_withdrawals 
            SET status = 'cancelled', 
                notes = CONCAT(COALESCE(notes, ''), '\nCancelled by vendor on ', NOW()),
                updated_at = NOW()
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([$withdrawal_id, $vendor_id]);
        
        // Log activity
        logVendorActivity($vendor_id, 'cancel_withdrawal', "Cancelled withdrawal #$withdrawal_id");
        
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal cancelled successfully'
        ]);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function logVendorActivity($vendor_id, $activity_type, $description) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_activities 
            (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $vendor_id,
            $activity_type,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch(Exception $e) {
        // Silently fail logging
    }
}
?>