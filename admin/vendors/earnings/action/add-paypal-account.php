<?php
// admin/vendors/earnings/action/add-paypal-account.php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

try {
    $db = getDB();
    
    $paypal_email = trim(strtolower($_POST['paypal_email'] ?? ''));
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $paypal_account_id = trim($_POST['paypal_account_id'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($paypal_email)) {
        $errors[] = 'PayPal email is required';
    } elseif (!filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($account_holder_name)) {
        $errors[] = 'Account holder name is required';
    } elseif (strlen($account_holder_name) < 2) {
        $errors[] = 'Account holder name must be at least 2 characters';
    }
    
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header('Location: ../withdraw.php');
        exit();
    }
    
    // Check if exists
    $stmt = $db->prepare("SELECT id FROM vendor_paypal_accounts WHERE vendor_id = ? AND paypal_email = ?");
    $stmt->execute([$vendor_id, $paypal_email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'This PayPal account is already registered';
        header('Location: ../withdraw.php');
        exit();
    }
    
    $db->beginTransaction();
    
    // Unset other defaults
    if ($is_default) {
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type = 'paypal'");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert payment method
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, is_default, created_at)
        VALUES (?, 'paypal', ?, NOW())
    ");
    $stmt->execute([$vendor_id, $is_default]);
    $payment_method_id = $db->lastInsertId();
    
    // Insert PayPal account
    $stmt = $db->prepare("
        INSERT INTO vendor_paypal_accounts 
        (payment_method_id, vendor_id, paypal_email, account_holder_name, paypal_account_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$payment_method_id, $vendor_id, $paypal_email, $account_holder_name, $paypal_account_id ?: null]);
    
    $db->commit();
    
    $_SESSION['success'] = 'PayPal account added successfully! It will be verified within 24-48 hours.';
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Add PayPal error: " . $e->getMessage());
    $_SESSION['error'] = 'Error adding PayPal account: ' . $e->getMessage();
}

header('Location: ../withdraw.php');
exit();