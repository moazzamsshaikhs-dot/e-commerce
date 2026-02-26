<?php
// admin/vendors/earnings/action/delete-payment-method.php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$vendor_id = $_SESSION['user_id'];
$id = intval($_POST['id'] ?? 0);
$type = $_POST['type'] ?? ''; // paypal, stripe, easypaisa, jazzcash, card

if ($id <= 0 || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $db = getDB();
    
    $db->beginTransaction();
    
    // Get payment_method_id first
    $payment_method_id = null;
    
    switch($type) {
        case 'paypal':
            $stmt = $db->prepare("SELECT payment_method_id FROM vendor_paypal_accounts WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$id, $vendor_id]);
            $row = $stmt->fetch();
            $payment_method_id = $row['payment_method_id'] ?? null;
            
            $stmt = $db->prepare("DELETE FROM vendor_paypal_accounts WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$id, $vendor_id]);
            break;
            
        case 'stripe':
            $stmt = $db->prepare("SELECT payment_method_id FROM vendor_stripe_accounts WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$id, $vendor_id]);
            $row = $stmt->fetch();
            $payment_method_id = $row['payment_method_id'] ?? null;
            
            $stmt = $db->prepare("DELETE FROM vendor_stripe_accounts WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$id, $vendor_id]);
            break;
            
        case 'easypaisa':
        case 'jazzcash':
            $stmt = $db->prepare("SELECT payment_method_id FROM vendor_mobile_accounts WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$id, $vendor_id]);
            $row = $stmt->fetch();
            $payment_method_id = $row['payment_method_id'] ?? null;
            
            $stmt = $db->prepare("DELETE FROM vendor_mobile_accounts WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$id, $vendor_id]);
            break;
            
        case 'card':
            $stmt = $db->prepare("SELECT payment_method_id FROM vendor_cards WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$id, $vendor_id]);
            $row = $stmt->fetch();
            $payment_method_id = $row['payment_method_id'] ?? null;
            
            $stmt = $db->prepare("DELETE FROM vendor_cards WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$id, $vendor_id]);
            break;
            
        default:
            throw new Exception('Invalid type');
    }
    
    // Delete from payment methods
    if ($payment_method_id) {
        $stmt = $db->prepare("DELETE FROM vendor_payment_methods WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$payment_method_id, $vendor_id]);
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}