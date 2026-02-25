<?php
// action/bank/process-withdrawal.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Process Withdrawal Started ===");
error_log("POST data: " . print_r($_POST, true));

if ($_SESSION['user_type'] !== 'vendor') {
    error_log("Access denied - not vendor");
    echo json_encode(['success' => false, 'message' => 'Access denied']);
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
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$vendor_id = $_SESSION['user_id'];
error_log("Vendor ID: $vendor_id");

try {
    $db = getDB();
    
    // Get withdrawal data
    $withdrawal_amount = floatval($_POST['withdrawal_amount'] ?? 0);
    $withdrawal_method = $_POST['withdrawal_method'] ?? '';
    $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
    $mobile_account_id = isset($_POST['mobile_account_id']) ? (int)$_POST['mobile_account_id'] : 0;
    $card_id = isset($_POST['card_id']) ? (int)$_POST['card_id'] : 0;
    $paypal_email = trim($_POST['paypal_email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    error_log("Withdrawal data: amount=$withdrawal_amount, method=$withdrawal_method");
    
    // Validation
    if ($withdrawal_amount <= 0) {
        throw new Exception('Invalid withdrawal amount');
    }
    
    // Get vendor's available balance
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN vendor_amount ELSE 0 END), 0) as balance 
        FROM vendor_earnings 
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $available_balance = $stmt->fetchColumn();
    
    error_log("Available balance: $available_balance");
    
    if ($withdrawal_amount > $available_balance) {
        throw new Exception("Insufficient balance. Available: $" . number_format($available_balance, 2));
    }
    
    // Get method details
    $stmt = $db->prepare("SELECT * FROM withdrawal_methods WHERE method_code = ? AND is_active = 1");
    $stmt->execute([$withdrawal_method]);
    $method = $stmt->fetch();
    
    if (!$method) {
        throw new Exception('Invalid withdrawal method');
    }
    
    error_log("Method details: " . print_r($method, true));
    
    // Check minimum amount
    if ($withdrawal_amount < $method['min_amount']) {
        throw new Exception("Minimum withdrawal amount is $" . number_format($method['min_amount'], 2));
    }
    
    // Check maximum amount if set
    if (!empty($method['max_amount']) && $withdrawal_amount > $method['max_amount']) {
        throw new Exception("Maximum withdrawal amount is $" . number_format($method['max_amount'], 2));
    }
    
    $db->beginTransaction();
    
    // Calculate fees
    $fee_percentage = $method['fee_percentage'] ?? 0;
    $fee_fixed = $method['fee_fixed'] ?? 0;
    $fee_amount = ($withdrawal_amount * $fee_percentage / 100) + $fee_fixed;
    $net_amount = $withdrawal_amount - $fee_amount;
    
    error_log("Fee calculation: percentage=$fee_percentage, fixed=$fee_fixed, fee=$fee_amount, net=$net_amount");
    
    // Get account details based on method
    $account_details = null;
    $mobile_number = null;
    $card_last_four = null;
    $cnic_number = null;
    
    if ($method['requires_account']) {
        switch($withdrawal_method) {
            case 'bank':
                if (!$account_id) {
                    throw new Exception('Bank account is required');
                }
                
                $stmt = $db->prepare("
                    SELECT * FROM vendor_bank_accounts 
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$account_id, $vendor_id]);
                $account = $stmt->fetch();
                
                if (!$account) {
                    throw new Exception('Bank account not found');
                }
                
                if (!$account['is_verified']) {
                    throw new Exception('Bank account must be verified before withdrawal');
                }
                
                $account_details = json_encode([
                    'bank_name' => $account['bank_name'],
                    'account_holder' => $account['account_holder_name'],
                    'account_number' => substr($account['account_number'], -4),
                    'ifsc_code' => $account['ifsc_code'] ?? null,
                    'swift_code' => $account['swift_code'] ?? null
                ]);
                break;
                
            case 'paypal':
                if (empty($paypal_email)) {
                    throw new Exception('PayPal email is required');
                }
                
                if (!filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Valid PayPal email is required');
                }
                
                $account_details = json_encode(['paypal_email' => $paypal_email]);
                break;
                
            case 'easypaisa':
            case 'jazzcash':
                if (!$mobile_account_id) {
                    throw new Exception('Mobile account is required');
                }
                
                $stmt = $db->prepare("
                    SELECT * FROM vendor_mobile_accounts 
                    WHERE id = ? AND vendor_id = ? AND account_type = ?
                ");
                $stmt->execute([$mobile_account_id, $vendor_id, $withdrawal_method]);
                $mobile = $stmt->fetch();
                
                if (!$mobile) {
                    throw new Exception('Mobile account not found');
                }
                
                if (!$mobile['is_verified']) {
                    throw new Exception('Mobile account must be verified before withdrawal');
                }
                
                $mobile_number = $mobile['mobile_number'];
                $cnic_number = $mobile['cnic_number'] ?? null;
                $account_details = json_encode([
                    'account_type' => $mobile['account_type'],
                    'mobile_number' => substr($mobile['mobile_number'], -4),
                    'account_holder' => $mobile['account_holder_name']
                ]);
                break;
                
            case 'visa':
            case 'mastercard':
            case 'amex':
                if (!$card_id) {
                    throw new Exception('Card is required');
                }
                
                $stmt = $db->prepare("
                    SELECT * FROM vendor_cards 
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$card_id, $vendor_id]);
                $card = $stmt->fetch();
                
                if (!$card) {
                    throw new Exception('Card not found');
                }
                
                if (!$card['is_verified']) {
                    throw new Exception('Card must be verified before withdrawal');
                }
                
                $card_last_four = $card['card_last_four'];
                $account_details = json_encode([
                    'card_type' => $card['card_type'],
                    'card_holder' => $card['card_holder_name'],
                    'card_last_four' => $card['card_last_four']
                ]);
                break;
        }
    }
    
    // Create withdrawal record
    $stmt = $db->prepare("
        INSERT INTO vendor_withdrawals (
            vendor_id, withdrawal_method, withdrawal_amount, fee_amount, net_amount,
            account_details, mobile_number, card_last_four, cnic_number, notes, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $result = $stmt->execute([
        $vendor_id,
        $withdrawal_method,
        $withdrawal_amount,
        $fee_amount,
        $net_amount,
        $account_details,
        $mobile_number,
        $card_last_four,
        $cnic_number,
        $notes
    ]);
    
    if (!$result) {
        throw new Exception('Failed to create withdrawal record');
    }
    
    $withdrawal_id = $db->lastInsertId();
    error_log("Withdrawal ID: $withdrawal_id");
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) 
        VALUES (?, 'withdrawal_request', ?, ?, ?, NOW())
    ");
    $log->execute([
        $vendor_id, 
        "Requested withdrawal #$withdrawal_id of $" . number_format($withdrawal_amount, 2) . " via $withdrawal_method", 
        $ip, 
        $ua
    ]);
    
    $db->commit();
    error_log("Withdrawal request created successfully");
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => 'Withdrawal request submitted successfully. It will be processed within 3-5 business days.',
        'withdrawal_id' => $withdrawal_id,
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("PDO Error in process withdrawal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Withdrawal error for vendor $vendor_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>