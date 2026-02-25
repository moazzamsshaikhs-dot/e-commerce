<?php
// action/bank/add-paypal-account.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Add PayPal Account Started ===");
error_log("POST data: " . print_r($_POST, true));

if ($_SESSION['user_type'] !== 'vendor') {
    error_log("Access denied - not vendor");
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
$submitted_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    error_log("CSRF token mismatch");
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$vendor_id = $_SESSION['user_id'];
error_log("Vendor ID: $vendor_id");

try {
    $db = getDB();
    
    // Get and sanitize form data
    $paypal_email = trim(strtolower($_POST['paypal_email'] ?? ''));
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $paypal_account_id = trim($_POST['paypal_account_id'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    error_log("Form data: email=$paypal_email, holder=$account_holder_name");
    
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
        error_log("Validation errors: " . implode('. ', $errors));
        throw new Exception(implode('. ', $errors));
    }
    
    // Get vendor's country
    $stmt = $db->prepare("SELECT country FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $country = $stmt->fetchColumn() ?: 'US';
    
    // Check if account already exists
    $stmt = $db->prepare("SELECT id FROM vendor_paypal_accounts WHERE vendor_id = ? AND paypal_email = ?");
    $stmt->execute([$vendor_id, $paypal_email]);
    if ($stmt->fetch()) {
        throw new Exception('This PayPal account is already registered');
    }
    
    $db->beginTransaction();
    
    // If setting as default, unset other defaults
    if ($is_default) {
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type = 'paypal'");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert into vendor_payment_methods
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, country_code, is_default, created_at)
        VALUES (?, 'paypal', ?, ?, NOW())
    ");
    $result = $stmt->execute([$vendor_id, $country, $is_default]);
    
    if (!$result) {
        throw new Exception('Failed to create payment method record');
    }
    
    $payment_method_id = $db->lastInsertId();
    error_log("Payment method ID: $payment_method_id");
    
    // Insert into vendor_paypal_accounts
    $stmt = $db->prepare("
        INSERT INTO vendor_paypal_accounts 
        (payment_method_id, vendor_id, paypal_email, account_holder_name, paypal_account_id, is_default, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $payment_method_id, 
        $vendor_id, 
        $paypal_email, 
        $account_holder_name, 
        $paypal_account_id ?: null,
        $is_default
    ]);
    
    if (!$result) {
        throw new Exception('Failed to insert PayPal account details');
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'add_paypal', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Added PayPal account: $paypal_email", $ip, $ua]);
    
    $db->commit();
    error_log("PayPal account added successfully");
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => 'PayPal account added successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("PDO Error in add PayPal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Add PayPal error for vendor $vendor_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>