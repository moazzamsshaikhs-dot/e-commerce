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
$withdrawal_id = intval($_GET['id'] ?? 0);

if ($withdrawal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get withdrawal details with vendor verification
    $stmt = $db->prepare("
        SELECT 
            w.*,
            u.full_name as vendor_name,
            u.username as vendor_username
        FROM vendor_withdrawals w
        JOIN users u ON w.vendor_id = u.id
        WHERE w.id = ? AND w.vendor_id = ?
    ");
    $stmt->execute([$withdrawal_id, $vendor_id]);
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$withdrawal) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
        exit;
    }
    
    // Format dates
    $withdrawal['created_at_formatted'] = date('d M Y, h:i A', strtotime($withdrawal['created_at']));
    $withdrawal['processed_at_formatted'] = $withdrawal['processed_at'] 
        ? date('d M Y, h:i A', strtotime($withdrawal['processed_at'])) 
        : 'Not processed yet';
    
    // Format status with badge
    $status_badges = [
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'processing' => '<span class="badge bg-info">Processing</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>'
    ];
    
    $withdrawal['status_badge'] = $status_badges[$withdrawal['status']] ?? '<span class="badge bg-secondary">Unknown</span>';
    
    // Format method
    $method_icons = [
        'bank' => 'fa-university',
        'paypal' => 'fa-paypal',
        'stripe' => 'fa-credit-card',
        'cash' => 'fa-money-bill'
    ];
    
    $withdrawal['method_icon'] = $method_icons[$withdrawal['withdrawal_method']] ?? 'fa-question-circle';
    $withdrawal['method_text'] = ucfirst($withdrawal['withdrawal_method']);
    
    // Parse account details if available
    if ($withdrawal['account_details']) {
        $account_details = json_decode($withdrawal['account_details'], true);
        $withdrawal['account_info'] = $account_details;
    } else {
        $withdrawal['account_info'] = null;
    }
    
    // Get processor info if processed
    if ($withdrawal['processed_by']) {
        $stmt = $db->prepare("SELECT username, full_name FROM users WHERE id = ?");
        $stmt->execute([$withdrawal['processed_by']]);
        $processor = $stmt->fetch(PDO::FETCH_ASSOC);
        $withdrawal['processor_info'] = $processor;
    } else {
        $withdrawal['processor_info'] = null;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $withdrawal
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>