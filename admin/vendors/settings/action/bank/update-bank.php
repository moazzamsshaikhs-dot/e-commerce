<?php
// action/bank/update-bank.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
$submitted_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$vendor_id = $_SESSION['user_id'];
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit;
}

try {
    $db = getDB();
    
    // Verify account belongs to vendor
    $stmt = $db->prepare("SELECT id FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$id, $vendor_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Account not found');
    }
    
    // Get form data
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $routing_number = trim($_POST['routing_number'] ?? '');
    $swift_code = trim($_POST['swift_code'] ?? '');
    $ifsc_code = trim($_POST['ifsc_code'] ?? '');
    $iban = trim($_POST['iban'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $branch_code = trim($_POST['branch_code'] ?? '');
    $account_type = $_POST['account_type'] ?? 'savings';
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Validation
    if (empty($account_holder_name) || empty($bank_name)) {
        throw new Exception('Account holder name and bank name are required');
    }
    
    $db->beginTransaction();
    
    // If setting as default, unset other defaults
    if ($is_default) {
        $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 0 WHERE vendor_id = ? AND id != ?");
        $stmt->execute([$vendor_id, $id]);
    }
    
    // Update account - all columns exist in your table
    $stmt = $db->prepare("
        UPDATE vendor_bank_accounts SET 
            account_holder_name = ?,
            bank_name = ?,
            routing_number = ?,
            swift_code = ?,
            ifsc_code = ?,
            iban = ?,
            branch_name = ?,
            branch_code = ?,
            account_type = ?,
            is_default = ?,
            updated_at = NOW()
        WHERE id = ? AND vendor_id = ?
    ");
    
    $result = $stmt->execute([
        $account_holder_name,
        $bank_name,
        $routing_number,
        $swift_code,
        $ifsc_code,
        $iban,
        $branch_name,
        $branch_code,
        $account_type,
        $is_default,
        $id,
        $vendor_id
    ]);
    
    if (!$result) {
        throw new Exception('Failed to update account');
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'update_bank', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Updated bank account #{$id}", $ip, $ua]);
    
    $db->commit();
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => 'Account updated successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Update bank error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}