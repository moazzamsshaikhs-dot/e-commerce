<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $account_id = intval($_POST['account_id'] ?? 0);
        
        if ($account_id <= 0) {
            throw new Exception('Invalid account ID');
        }
        
        // Verify account belongs to vendor
        $stmt = $db->prepare("SELECT id FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$account_id, $vendor_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Account not found');
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Unset all other defaults
        $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        
        // Set new default
        $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 1 WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$account_id, $vendor_id]);
        
        // Log activity
        logVendorActivity($vendor_id, 'set_default_bank', "Set bank account #$account_id as default");
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Default account updated successfully'
        ]);
        
    } catch(Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Helper function for activity logging
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