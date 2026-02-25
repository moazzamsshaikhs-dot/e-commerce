<?php
// action/bank/process-withdrawal.php

require_once '../../../../../../includes/config.php';
require_once '../../../../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF
$submitted_token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // Get withdrawal data
    $withdrawal_amount = floatval($_POST['withdrawal_amount'] ?? 0);
    $withdrawal_method = $_POST['withdrawal_method'] ?? '';
    $account_id = intval($_POST['account_id'] ?? 0);
    $mobile_account_id = intval($_POST['mobile_account_id'] ?? 0);
    $card_id = intval($_POST['card_id'] ?? 0);
    $paypal_email = trim($_POST['paypal_email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if ($withdrawal_amount <= 0) {
        throw new Exception('Invalid withdrawal amount');
    }
    
    // Get method details
    $stmt = $db->prepare("SELECT * FROM withdrawal_methods WHERE method_code = ? AND is_active = 1");
    $stmt->execute([$withdrawal_method]);
    $method = $stmt->fetch();
    
    if (!$method) {
        throw new Exception('Invalid withdrawal method');
    }
    
    // Check minimum amount
    if ($withdrawal_amount < $method['min_amount']) {
        throw new Exception("Minimum withdrawal amount is $" . number_format($method['min_amount'], 2));
    }
    
    // Check maximum amount
    if (!empty($method['max_amount']) && $withdrawal_amount > $method['max_amount']) {
        throw new Exception("Maximum withdrawal amount is $" . number_format($method['max_amount'], 2));
    }
    
    // Get available balance
    $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN vendor_amount ELSE 0 END), 0) as balance FROM vendor_earnings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $balance = $stmt->fetchColumn();
    
    if ($withdrawal_amount > $balance) {
        throw new Exception("Insufficient balance. Available: $" . number_format($balance, 2));
    }
    
    $db->beginTransaction();
    
    // Calculate fees
    $fee_amount = ($withdrawal_amount * ($method['fee_percentage'] / 100)) + ($method['fee_fixed'] ?? 0);
    $net_amount = $withdrawal_amount - $fee_amount;
    
    // Get account details
    $account_details = null;
    $mobile_number = null;
    $card_last_four = null;
    $cnic_number = null;
    
    if ($method['requires_account']) {
        switch($withdrawal_method) {
            case 'bank':
                $stmt = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$account_id, $vendor_id]);
                $account = $stmt->fetch();
                
                if (!$account) {
                    throw new Exception('Bank account not found');
                }
                
                if (!$account['is_verified']) {
                    throw new Exception('Bank account must be verified');
                }
                
                $account_details = json_encode([
                    'bank_name' => $account['bank_name'],
                    'account_holder' => $account['account_holder_name'],
                    'account_number' => substr($account['account_number'], -4),
                    'ifsc_code' => $account['ifsc_code']
                ]);
                break;
                
            case 'paypal':
                if (empty($paypal_email) || !filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Valid PayPal email required');
                }
                $account_details = json_encode(['paypal_email' => $paypal_email]);
                break;
                
            case 'visa':
            case 'mastercard':
            case 'amex':
                $stmt = $db->prepare("SELECT * FROM vendor_cards WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$card_id, $vendor_id]);
                $card = $stmt->fetch();
                
                if (!$card) {
                    throw new Exception('Card not found');
                }
                
                if (!$card['is_verified']) {
                    throw new Exception('Card must be verified');
                }
                
                $card_last_four = $card['card_last_four'];
                $account_details = json_encode([
                    'card_type' => $card['card_type'],
                    'card_holder' => $card['card_holder_name'],
                    'card_last_four' => $card['card_last_four']
                ]);
                break;
                
            case 'easypaisa':
            case 'jazzcash':
                $stmt = $db->prepare("SELECT * FROM vendor_mobile_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$mobile_account_id, $vendor_id]);
                $mobile = $stmt->fetch();
                
                if (!$mobile) {
                    throw new Exception('Mobile account not found');
                }
                
                if (!$mobile['is_verified']) {
                    throw new Exception('Mobile account must be verified');
                }
                
                $mobile_number = $mobile['mobile_number'];
                $cnic_number = $mobile['cnic_number'] ?? null;
                $account_details = json_encode([
                    'account_type' => $mobile['account_type'],
                    'mobile_number' => substr($mobile['mobile_number'], -4),
                    'account_holder' => $mobile['account_holder_name']
                ]);
                break;
        }
    }
    
    // Create withdrawal record
    $stmt = $db->prepare("
        INSERT INTO vendor_withdrawals (
            vendor_id, 
            withdrawal_method, 
            withdrawal_amount, 
            fee_amount,
            net_amount,
            account_details,
            mobile_number,
            card_last_four,
            cnic_number,
            notes,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
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
    
    $withdrawal_id = $db->lastInsertId();
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'withdrawal_request', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Requested withdrawal #$withdrawal_id of $$withdrawal_amount via $withdrawal_method", $ip, $ua]);
    
    $db->commit();
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => 'Withdrawal request submitted successfully',
        'withdrawal_id' => $withdrawal_id,
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}