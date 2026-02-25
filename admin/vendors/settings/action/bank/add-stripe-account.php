<?php
// action/bank/add-stripe-account.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Add Stripe Account Started ===");
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

// Check if function exists (for PHP 8+)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

try {
    $db = getDB();
    
    // Get and sanitize form data
    $stripe_account_id = trim($_POST['stripe_account_id'] ?? '');
    $account_email = trim(strtolower($_POST['account_email'] ?? ''));
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $stripe_publishable_key = trim($_POST['stripe_publishable_key'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    error_log("Form data: stripe_id=$stripe_account_id, email=$account_email, holder=$account_holder_name");
    
    // Validation
    $errors = [];
    
    if (empty($stripe_account_id)) {
        $errors[] = 'Stripe account ID is required';
    } elseif (!str_starts_with($stripe_account_id, 'acct_')) {
        $errors[] = 'Stripe account ID must start with "acct_" (e.g., acct_123456789)';
    } elseif (strlen($stripe_account_id) < 10) {
        $errors[] = 'Invalid Stripe account ID format';
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
        error_log("Validation errors: " . implode('. ', $errors));
        throw new Exception(implode('. ', $errors));
    }
    
    // Get vendor's country
    $stmt = $db->prepare("SELECT country FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $country = $stmt->fetchColumn() ?: 'US';
    
    // Check if account already exists
    $stmt = $db->prepare("SELECT id FROM vendor_stripe_accounts WHERE vendor_id = ? AND stripe_account_id = ?");
    $stmt->execute([$vendor_id, $stripe_account_id]);
    if ($stmt->fetch()) {
        throw new Exception('This Stripe account is already connected');
    }
    
    $db->beginTransaction();
    
    // If setting as default, unset other defaults
    if ($is_default) {
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type = 'stripe'");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert into vendor_payment_methods
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, country_code, is_default, created_at)
        VALUES (?, 'stripe', ?, ?, NOW())
    ");
    $result = $stmt->execute([$vendor_id, $country, $is_default]);
    
    if (!$result) {
        throw new Exception('Failed to create payment method record');
    }
    
    $payment_method_id = $db->lastInsertId();
    error_log("Payment method ID: $payment_method_id");
    
    // Insert into vendor_stripe_accounts
    $stmt = $db->prepare("
        INSERT INTO vendor_stripe_accounts 
        (payment_method_id, vendor_id, stripe_account_id, stripe_publishable_key, account_holder_name, account_email, is_default, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $payment_method_id, 
        $vendor_id, 
        $stripe_account_id, 
        $stripe_publishable_key ?: null, 
        $account_holder_name, 
        $account_email,
        $is_default
    ]);
    
    if (!$result) {
        throw new Exception('Failed to insert Stripe account details');
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'add_stripe', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Connected Stripe account: " . substr($stripe_account_id, 0, 10) . "...", $ip, $ua]);
    
    $db->commit();
    error_log("Stripe account added successfully");
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => 'Stripe account connected successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("PDO Error in add Stripe: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Add Stripe error for vendor $vendor_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>