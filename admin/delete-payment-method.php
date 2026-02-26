<?php
// admin/delete-payment-method.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: withdrawals.php');
    exit();
}

$method_id = (int)($_POST['method_id'] ?? 0);
$vendor_id = (int)($_POST['vendor_id'] ?? 0);
$method_type = $_POST['method_type'] ?? '';
$rejection_reason = trim($_POST['rejection_reason'] ?? '');

if (!$method_id || !$vendor_id || !$method_type) {
    $_SESSION['error'] = 'Missing required fields';
    header('Location: withdrawals.php');
    exit();
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Delete from appropriate table
    if ($method_type == 'bank') {
        $stmt = $db->prepare("DELETE FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$method_id, $vendor_id]);
    } elseif (in_array($method_type, ['easypaisa', 'jazzcash'])) {
        $stmt = $db->prepare("DELETE FROM vendor_mobile_accounts WHERE id = ? AND vendor_id = ? AND account_type = ?");
        $stmt->execute([$method_id, $vendor_id, $method_type]);
    } elseif ($method_type == 'paypal') {
        $stmt = $db->prepare("DELETE FROM vendor_paypal_accounts WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$method_id, $vendor_id]);
    } elseif ($method_type == 'stripe') {
        $stmt = $db->prepare("DELETE FROM vendor_stripe_accounts WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$method_id, $vendor_id]);
    } elseif (in_array($method_type, ['visa', 'mastercard', 'amex'])) {
        $stmt = $db->prepare("DELETE FROM vendor_cards WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$method_id, $vendor_id]);
    }
    
    // Create notification for vendor
    $message = "Your " . ucfirst($method_type) . " account was rejected. Reason: " . ($rejection_reason ?: 'No reason provided');
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, 'Payment Method Rejected', ?, 'error', NOW())
    ");
    $stmt->execute([$vendor_id, $message]);
    
    $db->commit();
    $_SESSION['success'] = "Payment method rejected and deleted";
    
} catch(Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: withdrawals.php');
exit();