<?php
// admin/vendors/ajax/payment-handler.php - AJAX Handler for Vendor Payments
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/admin-access-check.php';
require_once '../../includes/payments/admin_payment_processor.php';

header('Content-Type: application/json');

$db = getDB();
$processor = new AdminPaymentProcessor($db);
$adminId = $_SESSION['user_id'] ?? 0;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        // Process vendor payment
        case 'process_vendor_payment':
            $vendorId = (int)$_POST['vendor_id'];
            $orderId = (int)$_POST['order_id'];
            $amount = floatval($_POST['amount']);
            
            if (!$vendorId || !$orderId || $amount <= 0) {
                echo jsonResponse(false, 'Invalid parameters');
                exit;
            }
            
            $result = $processor->processVendorPayment($vendorId, $orderId, $amount);
            echo json_encode($result);
            exit;
            
        // Process bulk payments
        case 'process_bulk_payments':
            $payments = json_decode($_POST['payments'] ?? '[]', true);
            
            if (empty($payments)) {
                echo jsonResponse(false, 'No payments to process');
                exit;
            }
            
            $result = $processor->processBulkVendorPayments($payments);
            echo json_encode([
                'success' => true,
                'message' => 'Processed ' . count($result['success']) . ' payments, ' . count($result['failed']) . ' failed',
                'results' => $result
            ]);
            exit;
            
        // Process withdrawal request
        case 'process_withdrawal':
            $requestId = (int)$_POST['request_id'];
            $processAction = $_POST['process_action']; // 'approve' or 'reject'
            $notes = $_POST['notes'] ?? '';
            
            if (!$requestId || !in_array($processAction, ['approve', 'reject'])) {
                echo jsonResponse(false, 'Invalid parameters');
                exit;
            }
            
            $result = $processor->processWithdrawalRequest($requestId, $processAction, $adminId, $notes);
            echo json_encode($result);
            exit;
            
        // Verify payment method
        case 'verify_method':
            $methodId = (int)$_POST['method_id'];
            
            if (!$methodId) {
                echo jsonResponse(false, 'Invalid method ID');
                exit;
            }
            
            $result = $processor->verifyVendorMethod($methodId, $adminId);
            echo json_encode($result);
            exit;
            
        // Reject payment method
        case 'reject_method':
            $methodId = (int)$_POST['method_id'];
            $reason = $_POST['reason'] ?? 'Verification rejected';
            
            if (!$methodId) {
                echo jsonResponse(false, 'Invalid method ID');
                exit;
            }
            
            $result = $processor->rejectVendorMethod($methodId, $reason, $adminId);
            echo json_encode($result);
            exit;
            
        // Get vendor payment details
        case 'get_vendor_payment_details':
            $vendorId = (int)$_POST['vendor_id'];
            
            if (!$vendorId) {
                echo jsonResponse(false, 'Invalid vendor ID');
                exit;
            }
            
            $methods = $processor->getVendorPaymentMethods($vendorId);
            $earnings = $processor->getVendorEarningsHistory($vendorId);
            $withdrawals = $processor->getWithdrawalRequests(null, $vendorId);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'methods' => $methods,
                    'earnings' => $earnings,
                    'withdrawals' => $withdrawals
                ]
            ]);
            exit;
            
        // Get dashboard stats
        case 'get_dashboard_stats':
            $stats = $processor->getDashboardStats();
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            exit;
            
        // Get transaction history
        case 'get_transactions':
            $type = $_POST['type'] ?? null;
            $limit = (int)($_POST['limit'] ?? 100);
            
            $transactions = $processor->getTransactionHistory($type, $limit);
            echo json_encode([
                'success' => true,
                'data' => $transactions
            ]);
            exit;
            
        // Get pending payments
        case 'get_pending_payments':
            $vendorId = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
            
            $payments = $processor->getPendingVendorPayments($vendorId);
            echo json_encode([
                'success' => true,
                'data' => $payments
            ]);
            exit;
            
        // Get withdrawal requests
        case 'get_withdrawal_requests':
            $status = $_POST['status'] ?? null;
            $vendorId = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
            
            $requests = $processor->getWithdrawalRequests($status, $vendorId);
            echo json_encode([
                'success' => true,
                'data' => $requests
            ]);
            exit;
            
        // Cancel withdrawal
        case 'cancel_withdrawal':
            $requestId = (int)$_POST['request_id'];
            
            try {
                $stmt = $db->prepare("
                    UPDATE vendor_withdrawal_requests 
                    SET status = 'cancelled', 
                        processed_by = ?, 
                        processed_at = NOW(),
                        admin_notes = 'Cancelled by admin'
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$adminId, $requestId]);
                
                if ($stmt->rowCount() > 0) {
                    echo jsonResponse(true, 'Withdrawal cancelled successfully');
                } else {
                    echo jsonResponse(false, 'Withdrawal not found or already processed');
                }
            } catch (Exception $e) {
                echo jsonResponse(false, 'Error: ' . $e->getMessage());
            }
            exit;
            
        // Update payment method status
        case 'update_method_status':
            $methodId = (int)$_POST['method_id'];
            $isActive = (int)$_POST['is_active'];
            
            try {
                $stmt = $db->prepare("UPDATE vendors_payment_methods SET is_active = ? WHERE id = ?");
                $stmt->execute([$isActive, $methodId]);
                
                echo jsonResponse(true, 'Payment method status updated');
            } catch (Exception $e) {
                echo jsonResponse(false, 'Error: ' . $e->getMessage());
            }
            exit;
            
        // Set default payment method
        case 'set_default_method':
            $methodId = (int)$_POST['method_id'];
            $vendorId = (int)$_POST['vendor_id'];
            
            try {
                $db->beginTransaction();
                
                // Remove default from all vendor's methods
                $stmt = $db->prepare("UPDATE vendors_payment_methods SET is_default = 0 WHERE vendor_id = ?");
                $stmt->execute([$vendorId]);
                
                // Set new default
                $stmt = $db->prepare("UPDATE vendors_payment_methods SET is_default = 1 WHERE id = ?");
                $stmt->execute([$methodId]);
                
                $db->commit();
                
                echo jsonResponse(true, 'Default payment method updated');
            } catch (Exception $e) {
                $db->rollBack();
                echo jsonResponse(false, 'Error: ' . $e->getMessage());
            }
            exit;
            
        // Get admin accounts summary
        case 'get_admin_accounts':
            $accounts = $processor->getAdminAccountsSummary();
            echo json_encode([
                'success' => true,
                'data' => $accounts
            ]);
            exit;
            
        // Add admin account (for testing/demo)
        case 'add_admin_account':
            $accountType = $_POST['account_type'];
            $accountName = $_POST['account_name'];
            $initialBalance = floatval($_POST['initial_balance'] ?? 0);
            
            try {
                $stmt = $db->prepare("
                    INSERT INTO admin_accounts 
                    (account_type, account_name, current_balance, total_credited, total_debited, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$accountType, $accountName, $initialBalance, $initialBalance, 0]);
                
                echo jsonResponse(true, 'Admin account added successfully');
            } catch (Exception $e) {
                echo jsonResponse(false, 'Error: ' . $e->getMessage());
            }
            exit;
            
        default:
            echo jsonResponse(false, 'Unknown action: ' . $action);
            exit;
    }
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        // Get vendor earnings summary
        case 'get_vendor_earnings':
            $vendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
            
            $earnings = $processor->getVendorEarningsSummary($vendorId);
            echo json_encode([
                'success' => true,
                'data' => $earnings
            ]);
            exit;
            
        // Get all vendors for autocomplete
        case 'search_vendors':
            $query = trim($_GET['q'] ?? '');
            
            if (strlen($query) < 2) {
                echo jsonResponse(false, 'Query too short');
                exit;
            }
            
            $stmt = $db->prepare("
                SELECT id, full_name, email, username 
                FROM users 
                WHERE user_type = 'vendor' 
                AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)
                LIMIT 10
            ");
            $searchTerm = "%$query%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $vendors
            ]);
            exit;
            
        default:
            echo jsonResponse(false, 'Unknown action');
            exit;
    }
}

// Default response
echo jsonResponse(false, 'Invalid request');

