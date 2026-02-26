<?php
// admin/vendors/earnings/action/add-stripe-account.php
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
    
    $stripe_account_id = trim($_POST['stripe_account_id'] ?? '');
    $account_email = trim(strtolower($_POST['account_email'] ?? ''));
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $stripe_publishable_key = trim($_POST['stripe_publishable_key'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($stripe_account_id)) {
        $errors[] = 'Stripe account ID is required';
    } elseif (!str_starts_with($stripe_account_id, 'acct_')) {
        $errors[] = 'Stripe account ID must start with "acct_"';
    }
    
    if (empty($account_email)) {
        $errors[] = 'Account email is required';
    } elseif (!filter_var($account_email, FILTER_VALIDATE_EMAIL)) {
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
    $stmt = $db->prepare("SELECT id FROM vendor_stripe_accounts WHERE vendor_id = ? AND stripe_account_id = ?");
    $stmt->execute([$vendor_id, $stripe_account_id]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'This Stripe account is already connected';
        header('Location: ../withdraw.php');
        exit();
    }
    
    $db->beginTransaction();
    
    // Unset other defaults
    if ($is_default) {
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type = 'stripe'");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert payment method
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, is_default, created_at)
        VALUES (?, 'stripe', ?, NOW())
    ");
    $stmt->execute([$vendor_id, $is_default]);
    $payment_method_id = $db->lastInsertId();
    
    // Insert Stripe account
    $stmt = $db->prepare("
        INSERT INTO vendor_stripe_accounts 
        (payment_method_id, vendor_id, stripe_account_id, stripe_publishable_key, account_holder_name, account_email, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $payment_method_id, 
        $vendor_id, 
        $stripe_account_id, 
        $stripe_publishable_key ?: null, 
        $account_holder_name, 
        $account_email
    ]);
    
    $db->commit();
    
    $_SESSION['success'] = 'Stripe account connected successfully! It will be verified within 24-48 hours.';
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Add Stripe error: " . $e->getMessage());
    $_SESSION['error'] = 'Error connecting Stripe account: ' . $e->getMessage();
}

header('Location: ../withdraw.php');
exit();