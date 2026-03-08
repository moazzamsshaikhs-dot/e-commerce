<?php
// admin/includes/payments/process_order_payment.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';
require_once 'admin_payment_processor.php';

if ($_SESSION['user_type'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$db = getDB();

// Check if it's an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

try {
    $orderId = $_POST['order_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if (!$orderId) {
        throw new Exception('Order ID is required');
    }
    
    $processor = new AdminPaymentProcessor($db);
    
    if ($action == 'process_commissions') {
        $results = $processor->processOrderCommissions($orderId);
        
        $message = 'Processed ' . count($results) . ' commissions';
        $success = true;
        
    } else {
        $vendorId = $_POST['vendor_id'] ?? 0;
        $amount = $_POST['amount'] ?? 0;
        
        if (!$vendorId || !$amount) {
            throw new Exception('Vendor ID and amount are required');
        }
        
        $result = $processor->processVendorPayment($vendorId, $orderId, $amount);
        $success = $result['success'];
        $message = $result['message'];
    }
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'results' => $results ?? null
        ]);
        exit;
    } else {
        if ($success) {
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = $message;
        }
        redirect('../orders/view_order.php?id=' . $orderId);
    }
    
} catch (Exception $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    } else {
        $_SESSION['error'] = $e->getMessage();
        redirect('../orders/view_order.php?id=' . $orderId);
    }
}