<?php
// admin/vendors/earnings/action/delete-bank-account.php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied';
    header('Location: ../withdraw.php');
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = 'Invalid security token';
    header('Location: ../withdraw.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$account_id = intval($_POST['account_id'] ?? 0);

if ($account_id <= 0) {
    $_SESSION['error'] = 'Invalid account ID';
    header('Location: ../withdraw.php');
    exit();
}

try {
    $db = getDB();
    
    // Verify account belongs to vendor and get payment_method_id
    $stmt = $db->prepare("
        SELECT payment_method_id, is_default 
        FROM vendor_bank_accounts 
        WHERE id = ? AND vendor_id = ?
    ");
    $stmt->execute([$account_id, $vendor_id]);
    $account = $stmt->fetch();
    
    if (!$account) {
        $_SESSION['error'] = 'Account not found';
        header('Location: ../withdraw.php');
        exit();
    }
    
    if ($account['is_default']) {
        $_SESSION['error'] = 'Cannot delete default account. Set another account as default first.';
        header('Location: ../withdraw.php');
        exit();
    }
    
    $db->beginTransaction();
    
    // Delete from bank accounts
    $stmt = $db->prepare("DELETE FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$account_id, $vendor_id]);
    
    // Delete from payment methods if exists
    if ($account['payment_method_id']) {
        $stmt = $db->prepare("DELETE FROM vendor_payment_methods WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$account['payment_method_id'], $vendor_id]);
    }
    
    $db->commit();
    
    $_SESSION['success'] = 'Bank account deleted successfully';
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Delete account error: " . $e->getMessage());
    $_SESSION['error'] = 'Error deleting account: ' . $e->getMessage();
}

header('Location: ../withdraw.php');
exit();