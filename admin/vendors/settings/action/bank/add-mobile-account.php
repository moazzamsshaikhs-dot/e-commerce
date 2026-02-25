<?php
// action/bank/add-mobile-account.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Add Mobile Account Started ===");
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
    $mobile_type = $_POST['mobile_type'] ?? '';
    $mobile_number = trim(preg_replace('/[^0-9]/', '', $_POST['mobile_number'] ?? ''));
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $cnic_number = trim(preg_replace('/[^0-9-]/', '', $_POST['cnic_number'] ?? ''));
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    error_log("Form data: type=$mobile_type, mobile=$mobile_number, holder=$account_holder_name");
    
    // Validation
    $errors = [];
    
    if (!in_array($mobile_type, ['easypaisa', 'jazzcash'])) {
        $errors[] = 'Invalid mobile wallet type. Must be Easypaisa or JazzCash';
    }
    
    if (empty($mobile_number)) {
        $errors[] = 'Mobile number is required';
    } elseif (!preg_match('/^03\d{9}$/', $mobile_number)) {
        $errors[] = 'Invalid Pakistani mobile number. Format: 03XXXXXXXXX (11 digits starting with 03)';
    }
    
    if (empty($account_holder_name)) {
        $errors[] = 'Account holder name is required';
    } elseif (strlen($account_holder_name) < 3) {
        $errors[] = 'Account holder name must be at least 3 characters';
    }
    
    // Format CNIC if 13 digits without dashes
    if (!empty($cnic_number) && preg_match('/^\d{13}$/', $cnic_number)) {
        $cnic_number = substr($cnic_number, 0, 5) . '-' . substr($cnic_number, 5, 7) . '-' . substr($cnic_number, 12, 1);
    }
    
    if (!empty($errors)) {
        error_log("Validation errors: " . implode('. ', $errors));
        throw new Exception(implode('. ', $errors));
    }
    
    // Get vendor's country
    $stmt = $db->prepare("SELECT country FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $country = $stmt->fetchColumn() ?: 'PK';
    
    // Check if account already exists
    $stmt = $db->prepare("
        SELECT id FROM vendor_mobile_accounts 
        WHERE vendor_id = ? AND mobile_number = ? AND account_type = ?
    ");
    $stmt->execute([$vendor_id, $mobile_number, $mobile_type]);
    if ($stmt->fetch()) {
        throw new Exception('This ' . ucfirst($mobile_type) . ' account is already registered');
    }
    
    $db->beginTransaction();
    
    // If setting as default, unset other defaults
    if ($is_default) {
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type IN ('easypaisa', 'jazzcash')");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert into vendor_payment_methods
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, country_code, is_default, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $result = $stmt->execute([$vendor_id, $mobile_type, $country, $is_default]);
    
    if (!$result) {
        throw new Exception('Failed to create payment method record');
    }
    
    $payment_method_id = $db->lastInsertId();
    error_log("Payment method ID: $payment_method_id");
    
    // Insert into vendor_mobile_accounts
    $stmt = $db->prepare("
        INSERT INTO vendor_mobile_accounts 
        (payment_method_id, vendor_id, account_type, mobile_number, account_holder_name, cnic_number, is_default, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $payment_method_id, 
        $vendor_id, 
        $mobile_type, 
        $mobile_number, 
        $account_holder_name, 
        $cnic_number ?: null, 
        $is_default
    ]);
    
    if (!$result) {
        throw new Exception('Failed to insert mobile account details');
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'add_mobile', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Added $mobile_type account: " . substr($mobile_number, -4), $ip, $ua]);
    
    $db->commit();
    error_log("Mobile account added successfully");
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($mobile_type) . ' account added successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("PDO Error in add mobile: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Add mobile error for vendor $vendor_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>