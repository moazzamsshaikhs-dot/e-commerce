<?php
// action/bank/delete.php
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
                // Check if this is the only account or default
                $stmt = $db->prepare("SELECT is_default FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                $account = $stmt->fetch();
                
                if (!$account) {
                    throw new Exception('Account not found');
                }
                
                if ($account['is_default']) {
                    // Check if there are other accounts
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vendor_bank_accounts WHERE vendor_id = ? AND id != ?");
                    $stmt->execute([$vendor_id, $id]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count == 0) {
                        throw new Exception('Cannot delete the only default account. Set another account as default first.');
                    }
                }
                
                $stmt = $db->prepare("DELETE FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                break;
                
            case 'easypaisa':
            case 'jazzcash':
                $stmt = $db->prepare("SELECT is_default FROM vendor_mobile_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                $account = $stmt->fetch();
                
                if (!$account) {
                    throw new Exception('Account not found');
                }
                
                if ($account['is_default']) {
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vendor_mobile_accounts WHERE vendor_id = ? AND account_type = ? AND id != ?");
                    $stmt->execute([$vendor_id, $type, $id]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count == 0) {
                        throw new Exception('Cannot delete the only default account. Set another account as default first.');
                    }
                }
                
                $stmt = $db->prepare("DELETE FROM vendor_mobile_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                break;
                
            case 'visa':
            case 'mastercard':
            case 'amex':
                $stmt = $db->prepare("SELECT is_default FROM vendor_cards WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                $card = $stmt->fetch();
                
                if (!$card) {
                    throw new Exception('Card not found');
                }
                
                if ($card['is_default']) {
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM vendor_cards WHERE vendor_id = ? AND id != ?");
                    $stmt->execute([$vendor_id, $id]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count == 0) {
                        throw new Exception('Cannot delete the only default card. Set another card as default first.');
                    }
                }
                
                $stmt = $db->prepare("DELETE FROM vendor_cards WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$id, $vendor_id]);
                break;
                
            default:
                throw new Exception('Invalid method type');
        }
    } else {
        // Handle new payment_methods table - first get the method type
        $stmt = $db->prepare("SELECT method_type FROM vendor_payment_methods WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
        $method_type = $stmt->fetchColumn();
        
        if (!$method_type) {
            throw new Exception('Payment method not found');
        }
        
        // Check if this is the only default
        $stmt = $db->prepare("SELECT is_default FROM vendor_payment_methods WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
        $is_default = $stmt->fetchColumn();
        
        if ($is_default) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM vendor_payment_methods WHERE vendor_id = ? AND method_type = ? AND id != ? AND is_default = 1");
            $stmt->execute([$vendor_id, $method_type, $id]);
            $other_defaults = $stmt->fetchColumn();
            
            if ($other_defaults == 0) {
                // Check if there are any other methods of this type
                $stmt = $db->prepare("SELECT COUNT(*) FROM vendor_payment_methods WHERE vendor_id = ? AND method_type = ? AND id != ?");
                $stmt->execute([$vendor_id, $method_type, $id]);
                $other_methods = $stmt->fetchColumn();
                
                if ($other_methods == 0) {
                    throw new Exception('Cannot delete the only default method of this type');
                }
            }
        }
        
        // Delete from vendor_payment_methods (cascade will handle related tables)
        $stmt = $db->prepare("DELETE FROM vendor_payment_methods WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$id, $vendor_id]);
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'delete', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Deleted {$type} account #{$id}", $ip, $ua]);
    
    $db->commit();
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment method deleted successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>