<?php
// admin/vendors/earnings/action/add-bank-account.php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log file
$log_file = dirname(__DIR__, 3) . '/logs/bank_debug.log';
$log_dir = dirname($log_file);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

function debug_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

debug_log("=== Add Bank Account Started ===");
debug_log("POST data: " . print_r($_POST, true));

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    debug_log("Access denied - not vendor");
    $_SESSION['error'] = 'Access denied';
    header('Location: ../withdraw.php');
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    debug_log("CSRF token mismatch");
    $_SESSION['error'] = 'Invalid security token';
    header('Location: ../withdraw.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
debug_log("Vendor ID: $vendor_id");

try {
    $db = getDB();
    
    // Get and sanitize form data
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim(preg_replace('/\s+/', '', $_POST['account_number'] ?? ''));
    $ifsc_code = trim(strtoupper(preg_replace('/\s+/', '', $_POST['ifsc_code'] ?? '')));
    $branch_name = trim($_POST['branch_name'] ?? '');
    $account_type = $_POST['account_type'] ?? 'savings';
    $swift_code = trim(strtoupper(preg_replace('/\s+/', '', $_POST['swift_code'] ?? '')));
    $routing_number = trim(preg_replace('/\s+/', '', $_POST['routing_number'] ?? ''));
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    debug_log("Form data: holder=$account_holder_name, bank=$bank_name, acct=" . substr($account_number, -4));
    
    // Validation
    $errors = [];
    
    if (empty($account_holder_name)) {
        $errors[] = 'Account holder name is required';
    } elseif (strlen($account_holder_name) < 3) {
        $errors[] = 'Account holder name must be at least 3 characters';
    }
    
    if (empty($bank_name)) {
        $errors[] = 'Bank name is required';
    }
    
    if (empty($account_number)) {
        $errors[] = 'Account number is required';
    } elseif (!preg_match('/^\d{9,18}$/', $account_number)) {
        $errors[] = 'Account number must be 9-18 digits';
    }
    
    if (empty($ifsc_code)) {
        $errors[] = 'IFSC code is required';
    } elseif (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc_code)) {
        $errors[] = 'Invalid IFSC code format (e.g., ABCD0123456)';
    }
    
    if (!empty($errors)) {
        debug_log("Validation errors: " . implode('. ', $errors));
        $_SESSION['form_errors'] = $errors;
        header('Location: ../withdraw.php');
        exit();
    }
    
    // Get vendor's country
    $stmt = $db->prepare("SELECT country FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $country = $stmt->fetchColumn();
    debug_log("Vendor country: $country");
    
    if (!$country) {
        $country = 'IN';
    }
    
    // Check if account already exists
    $last_four = substr($account_number, -4);
    $stmt = $db->prepare("
        SELECT ba.id 
        FROM vendor_bank_accounts ba
        WHERE ba.vendor_id = ? AND RIGHT(ba.account_number, 4) = ?
    ");
    $stmt->execute([$vendor_id, $last_four]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'A bank account with these last 4 digits already exists';
        header('Location: ../withdraw.php');
        exit();
    }
    
    $db->beginTransaction();
    debug_log("Transaction started");
    
    // If setting as default, unset other defaults
    if ($is_default) {
        debug_log("Setting as default, unsetting others");
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type = 'bank'");
        $stmt->execute([$vendor_id]);
        
        $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert into vendor_payment_methods
    debug_log("Inserting into vendor_payment_methods");
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, country_code, is_default, created_at)
        VALUES (?, 'bank', ?, ?, NOW())
    ");
    $stmt->execute([$vendor_id, $country, $is_default]);
    
    $payment_method_id = $db->lastInsertId();
    debug_log("Payment method ID: $payment_method_id");
    
    // Insert into vendor_bank_accounts
    debug_log("Inserting into vendor_bank_accounts");
    
    $stmt = $db->prepare("
        INSERT INTO vendor_bank_accounts (
            payment_method_id, vendor_id, account_holder_name, bank_name, 
            account_number, ifsc_code, branch_name, account_type, 
            swift_code, routing_number, is_default, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $payment_method_id,
        $vendor_id,
        $account_holder_name,
        $bank_name,
        $account_number,
        $ifsc_code,
        $branch_name ?: null,
        $account_type,
        $swift_code ?: null,
        $routing_number ?: null,
        $is_default
    ]);
    
    // Log activity
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) 
            VALUES (?, 'add_bank', ?, ?, ?, NOW())
        ");
        $log->execute([$vendor_id, "Added bank account: $bank_name ending in " . substr($account_number, -4), $ip, $ua]);
    } catch(Exception $e) {
        debug_log("Activity log failed: " . $e->getMessage());
    }
    
    $db->commit();
    debug_log("Transaction committed");
    
    $_SESSION['success'] = "Bank account added successfully! It will be verified within 24-48 hours.";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        debug_log("Transaction rolled back");
    }
    debug_log("ERROR: " . $e->getMessage());
    $_SESSION['error'] = 'Error adding bank account: ' . $e->getMessage();
}

header('Location: ../withdraw.php');
exit();