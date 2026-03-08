<?php
// admin/includes/payments/payment_actions.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/auth-check.php';
require_once 'admin_payment_processor.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$db = getDB();
$processor = new AdminPaymentProcessor($db);
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$adminId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'process_vendor_payment':
            $orderId = $_POST['order_id'] ?? 0;
            $vendorId = $_POST['vendor_id'] ?? 0;
            $amount = $_POST['amount'] ?? 0;
            
            if (!$orderId || !$vendorId || !$amount) {
                throw new Exception('Missing required parameters');
            }
            
            $result = $processor->processVendorPayment($vendorId, $orderId, $amount);
            echo json_encode($result);
            break;
            
        case 'process_bulk_payments':
            $payments = $_POST['payments'] ?? [];
            if (empty($payments)) {
                throw new Exception('No payments to process');
            }
            
            $result = $processor->processBulkVendorPayments($payments);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'process_withdrawal':
            $requestId = $_POST['request_id'] ?? 0;
            $processAction = $_POST['process_action'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (!$requestId || !$processAction) {
                throw new Exception('Request ID and action are required');
            }
            
            $result = $processor->processWithdrawalRequest($requestId, $processAction, $adminId, $notes);
            echo json_encode($result);
            break;
            
        case 'verify_method':
            $methodId = $_POST['method_id'] ?? 0;
            
            if (!$methodId) {
                throw new Exception('Method ID is required');
            }
            
            $result = $processor->verifyVendorMethod($methodId, $adminId);
            echo json_encode($result);
            break;
            
        case 'reject_method':
            $methodId = $_POST['method_id'] ?? 0;
            $reason = $_POST['reason'] ?? '';
            
            if (!$methodId) {
                throw new Exception('Method ID is required');
            }
            
            $result = $processor->rejectVendorMethod($methodId, $reason, $adminId);
            echo json_encode($result);
            break;
            
        case 'get_vendor_methods':
            $vendorId = $_POST['vendor_id'] ?? 0;
            
            if (!$vendorId) {
                throw new Exception('Vendor ID is required');
            }
            
            $methods = $processor->getVendorPaymentMethods($vendorId);
            echo json_encode(['success' => true, 'data' => $methods]);
            break;
            
        case 'get_pending_payments':
            $vendorId = $_POST['vendor_id'] ?? null;
            $payments = $processor->getPendingVendorPayments($vendorId);
            echo json_encode(['success' => true, 'data' => $payments]);
            break;
            
        case 'get_withdrawal_requests':
            $status = $_GET['status'] ?? null;
            $vendorId = $_GET['vendor_id'] ?? null;
            $requests = $processor->getWithdrawalRequests($status, $vendorId);
            echo json_encode(['success' => true, 'data' => $requests]);
            break;
            
        case 'get_dashboard_stats':
            $stats = $processor->getDashboardStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'get_transactions':
            $type = $_GET['type'] ?? null;
            $limit = $_GET['limit'] ?? 100;
            $transactions = $processor->getTransactionHistory($type, $limit);
            echo json_encode(['success' => true, 'data' => $transactions]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}