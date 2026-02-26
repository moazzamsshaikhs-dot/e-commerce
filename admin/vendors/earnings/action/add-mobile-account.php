<?php
// admin/vendors/earnings/action/add-mobile-account.php
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
$mobile_type = $_POST['mobile_type'] ?? ''; // 'easypaisa' or 'jazzcash'

try {
    $db = getDB();
    
    $mobile_number = trim(preg_replace('/[^0-9]/', '', $_POST['mobile_number'] ?? ''));
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $cnic_number = trim(preg_replace('/[^0-9-]/', '', $_POST['cnic_number'] ?? ''));
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (!in_array($mobile_type, ['easypaisa', 'jazzcash'])) {
        $errors[] = 'Invalid mobile wallet type';
    }
    
    if (empty($mobile_number)) {
        $errors[] = 'Mobile number is required';
    } elseif (!preg_match('/^03\d{9}$/', $mobile_number)) {
        $errors[] = 'Invalid Pakistani mobile number. Format: 03XXXXXXXXX';
    }
    
    if (empty($account_holder_name)) {
        $errors[] = 'Account holder name is required';
    } elseif (strlen($account_holder_name) < 3) {
        $errors[] = 'Account holder name must be at least 3 characters';
    }
    
    // Format CNIC if 13 digits
    if (!empty($cnic_number) && preg_match('/^\d{13}$/', $cnic_number)) {
        $cnic_number = substr($cnic_number, 0, 5) . '-' . substr($cnic_number, 5, 7) . '-' . substr($cnic_number, 12, 1);
    }
    
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header('Location: ../withdraw.php');
        exit();
    }
    
    // Check if exists
    $stmt = $db->prepare("
        SELECT id FROM vendor_mobile_accounts 
        WHERE vendor_id = ? AND mobile_number = ? AND account_type = ?
    ");
    $stmt->execute([$vendor_id, $mobile_number, $mobile_type]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'This ' . ucfirst($mobile_type) . ' account is already registered';
        header('Location: ../withdraw.php');
        exit();
    }
    
    $db->beginTransaction();
    
    // Unset other defaults
    if ($is_default) {
        $stmt = $db->prepare("
            UPDATE vendor_payment_methods SET is_default = 0 
            WHERE vendor_id = ? AND method_type IN ('easypaisa', 'jazzcash')
        ");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert payment method
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, is_default, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$vendor_id, $mobile_type, $is_default]);
    $payment_method_id = $db->lastInsertId();
    
    // Insert mobile account
    $stmt = $db->prepare("
        INSERT INTO vendor_mobile_accounts 
        (payment_method_id, vendor_id, account_type, mobile_number, account_holder_name, cnic_number, is_default, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $payment_method_id, 
        $vendor_id, 
        $mobile_type, 
        $mobile_number, 
        $account_holder_name, 
        $cnic_number ?: null, 
        $is_default
    ]);
    
    $db->commit();
    
    $_SESSION['success'] = ucfirst($mobile_type) . ' account added successfully! It will be verified within 24-48 hours.';
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Add mobile error: " . $e->getMessage());
    $_SESSION['error'] = 'Error adding account: ' . $e->getMessage();
}

header('Location: ../withdraw.php');
exit();