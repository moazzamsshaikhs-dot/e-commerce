<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$vendor_id = $_SESSION['user_id'];
$account_id = intval($_GET['id'] ?? 0);

if ($account_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get account details with vendor verification
    $stmt = $db->prepare("
        SELECT 
            id,
            account_holder_name,
            bank_name,
            account_number,
            ifsc_code,
            branch_name,
            account_type,
            is_default,
            is_verified,
            created_at
        FROM vendor_bank_accounts 
        WHERE id = ? AND vendor_id = ?
    ");
    $stmt->execute([$account_id, $vendor_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }
    
    // Mask account number for display
    $account_number = $account['account_number'];
    $account['account_number_masked'] = '****' . substr($account_number, -4);
    
    // Format dates
    $account['created_at_formatted'] = date('d M Y', strtotime($account['created_at']));
    
    echo json_encode([
        'success' => true,
        'data' => $account
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>