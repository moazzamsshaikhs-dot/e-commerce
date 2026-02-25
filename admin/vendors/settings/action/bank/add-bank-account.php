<?php
// action/bank/add-bank-account.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log file path
$log_file = __DIR__ . '/../../../logs/bank_debug.log';
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
debug_log("SESSION: " . print_r($_SESSION, true));

header('Content-Type: application/json');

try {
    // Check if user is vendor
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendor') {
        debug_log("Access denied - not vendor");
        throw new Exception('Access denied. Vendor only.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        debug_log("Invalid request method");
        throw new Exception('Invalid request method');
    }

    // Verify CSRF token
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        debug_log("CSRF token mismatch - Session: " . ($_SESSION['csrf_token'] ?? 'none') . " Submitted: $submitted_token");
        throw new Exception('Invalid security token. Please refresh the page.');
    }

    $vendor_id = $_SESSION['user_id'];
    debug_log("Vendor ID: $vendor_id");

    // Include config
    require_once '../../../../includes/config.php';
    $db = getDB();
    
    // Get and sanitize form data
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim(preg_replace('/\s+/', '', $_POST['account_number'] ?? ''));
    $routing_number = trim(preg_replace('/\s+/', '', $_POST['routing_number'] ?? ''));
    $swift_code = trim(strtoupper(preg_replace('/\s+/', '', $_POST['swift_code'] ?? '')));
    $ifsc_code = trim(strtoupper(preg_replace('/\s+/', '', $_POST['ifsc_code'] ?? '')));
    $iban = trim(strtoupper(preg_replace('/\s+/', '', $_POST['iban'] ?? '')));
    $branch_name = trim($_POST['branch_name'] ?? '');
    $account_type = $_POST['account_type'] ?? 'savings';
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
    
    if (!empty($errors)) {
        debug_log("Validation errors: " . implode('. ', $errors));
        throw new Exception(implode('. ', $errors));
    }
    
    // Get vendor's country
    $stmt = $db->prepare("SELECT country FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $country = $stmt->fetchColumn();
    debug_log("Vendor country: $country");
    
    if (!$country) {
        $country = 'US';
        debug_log("Using default country: US");
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
        throw new Exception('A bank account with these last 4 digits already exists');
    }
    
    $db->beginTransaction();
    debug_log("Transaction started");
    
    // If setting as default, unset other defaults
    if ($is_default) {
        debug_log("Setting as default, unsetting others");
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type = 'bank'");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert into vendor_payment_methods
    debug_log("Inserting into vendor_payment_methods");
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, country_code, is_default, created_at)
        VALUES (?, 'bank', ?, ?, NOW())
    ");
    $result = $stmt->execute([$vendor_id, $country, $is_default]);
    
    if (!$result) {
        $error = $stmt->errorInfo();
        debug_log("Failed to insert payment method: " . print_r($error, true));
        throw new Exception('Failed to create payment method record: ' . $error[2]);
    }
    
    $payment_method_id = $db->lastInsertId();
    debug_log("Payment method ID: $payment_method_id");
    
    // Insert into vendor_bank_accounts
    debug_log("Inserting into vendor_bank_accounts");
    $stmt = $db->prepare("
        INSERT INTO vendor_bank_accounts 
        (payment_method_id, vendor_id, account_holder_name, bank_name, account_number, 
         routing_number, swift_code, ifsc_code, iban, branch_name, account_type, is_default, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $params = [
        $payment_method_id, 
        $vendor_id, 
        $account_holder_name, 
        $bank_name, 
        $account_number,
        $routing_number ?: null, 
        $swift_code ?: null, 
        $ifsc_code ?: null, 
        $iban ?: null, 
        $branch_name ?: null, 
        $account_type, 
        $is_default
    ];
    
    debug_log("Bank account params: " . print_r($params, true));
    
    $result = $stmt->execute($params);
    
    if (!$result) {
        $error = $stmt->errorInfo();
        debug_log("Failed to insert bank account: " . print_r($error, true));
        throw new Exception('Failed to insert bank account details: ' . $error[2]);
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'add_bank', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Added bank account: $bank_name ending in " . substr($account_number, -4), $ip, $ua]);
    
    $db->commit();
    debug_log("Transaction committed successfully");
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    debug_log("New CSRF token generated");
    
    echo json_encode([
        'success' => true,
        'message' => 'Bank account added successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        debug_log("Transaction rolled back");
    }
    debug_log("ERROR: " . $e->getMessage());
    debug_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>