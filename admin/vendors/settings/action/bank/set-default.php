<?php
// action/bank/set-default.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
$submitted_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$vendor_id = $_SESSION['user_id'];
$id = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';
$source = $_POST['source'] ?? 'new';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

if (empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid method type']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    if ($source === 'old') {
        // Handle old tables
        switch($type) {
            case 'bank':
                // First verify account belongs to vendor
                $stmt = $db->prepare("SELECT id FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('Account not found');
                }
                
                // Unset all defaults
                $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 0 WHERE vendor_id = ?");
                $stmt->execute([$vendor_id]);
                
                // Set new default
                $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 1 WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                break;
                
            case 'easypaisa':
            case 'jazzcash':
                $stmt = $db->prepare("SELECT id FROM vendor_mobile_accounts WHERE id = ? AND vendor_id = ? AND account_type = ?");
                $stmt->execute([$id, $vendor_id, $type]);
                if (!$stmt->fetch()) {
                    throw new Exception('Account not found');
                }
                
                $stmt = $db->prepare("UPDATE vendor_mobile_accounts SET is_default = 0 WHERE vendor_id = ? AND account_type = ?");
                $stmt->execute([$vendor_id, $type]);
                
                $stmt = $db->prepare("UPDATE vendor_mobile_accounts SET is_default = 1 WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                break;
                
            case 'visa':
            case 'mastercard':
            case 'amex':
                $stmt = $db->prepare("SELECT id FROM vendor_cards WHERE id = ? AND vendor_id = ? AND card_type = ?");
                $stmt->execute([$id, $vendor_id, $type]);
                if (!$stmt->fetch()) {
                    throw new Exception('Card not found');
                }
                
                $stmt = $db->prepare("UPDATE vendor_cards SET is_default = 0 WHERE vendor_id = ?");
                $stmt->execute([$vendor_id]);
                
                $stmt = $db->prepare("UPDATE vendor_cards SET is_default = 1 WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                break;
                
            default:
                throw new Exception('Invalid method type for old source');
        }
    } else {
        // Handle new payment_methods table
        // First verify the payment method belongs to vendor
        $stmt = $db->prepare("SELECT id FROM vendor_payment_methods WHERE id = ? AND vendor_id = ? AND method_type = ?");
        $stmt->execute([$id, $vendor_id, $type]);
        if (!$stmt->fetch()) {
            throw new Exception('Payment method not found');
        }
        
        // Unset all defaults for this method type
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type = ?");
        $stmt->execute([$vendor_id, $type]);
        
        // Set new default
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 1 WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'set_default', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Set {$type} account #{$id} as default", $ip, $ua]);
    
    $db->commit();
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => 'Default payment method updated successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Set default error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>