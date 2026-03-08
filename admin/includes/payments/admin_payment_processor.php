<?php
// admin/includes/payments/admin_payment_processor.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/payments/PaymentFactory.php';

use Ecommerce\Payments\PaymentFactory;

class AdminPaymentProcessor {
    
    private $db;
    private $payoutGateways = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->payoutGateways = PaymentFactory::getActivePayoutGateways($db);
    }
    
    /**
     * Process payment to vendor when order is delivered
     */
    public function processVendorPayment($vendorId, $orderId, $amount) {
        try {
            $this->db->beginTransaction();
            
            // Check if already paid
            $stmt = $this->db->prepare("
                SELECT id FROM vendor_earnings 
                WHERE vendor_id = ? AND order_id = ? AND status = 'paid'
            ");
            $stmt->execute([$vendorId, $orderId]);
            if ($stmt->fetch()) {
                throw new Exception("Payment for this order has already been processed");
            }
            
            // Get vendor's default payment method
            $vendorMethod = $this->getVendorDefaultMethod($vendorId);
            
            if (!$vendorMethod) {
                throw new Exception("Vendor has no payment method configured");
            }
            
            $gatewayCode = $vendorMethod['method_type'];
            
            if (!isset($this->payoutGateways[$gatewayCode])) {
                throw new Exception("Payment gateway {$gatewayCode} is not available for payouts");
            }
            
            $gateway = $this->payoutGateways[$gatewayCode]['instance'];
            
            // Process payout
            $result = $gateway->processVendorPayout($vendorId, $amount, [
                'order_id' => $orderId,
                'method_id' => $vendorMethod['id']
            ]);
            
            if ($result['success']) {
                // Record in vendor_earnings
                $this->recordVendorEarning($vendorId, $orderId, $amount, $result['transaction_id']);
                
                // Update commission status
                $this->updateCommissionStatus($orderId, $vendorId, 'paid');
                
                // Update admin account balance history
                $this->addToBalanceHistory($vendorId, $orderId, $amount, 'debit');
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'transaction_id' => $result['transaction_id']
                ];
            } else {
                $this->db->rollBack();
                return $result;
            }
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Admin payment processing error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process bulk payments to multiple vendors
     */
    public function processBulkVendorPayments($payments) {
        $results = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($payments as $payment) {
            $result = $this->processVendorPayment(
                $payment['vendor_id'],
                $payment['order_id'],
                $payment['amount']
            );
            
            if ($result['success']) {
                $results['success'][] = [
                    'vendor_id' => $payment['vendor_id'],
                    'order_id' => $payment['order_id'],
                    'amount' => $payment['amount'],
                    'transaction_id' => $result['transaction_id']
                ];
            } else {
                $results['failed'][] = [
                    'vendor_id' => $payment['vendor_id'],
                    'order_id' => $payment['order_id'],
                    'amount' => $payment['amount'],
                    'error' => $result['message']
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get vendor's default payment method
     */
    public function getVendorDefaultMethod($vendorId) {
        // Try vendors_payment_methods first
        $stmt = $this->db->prepare("
            SELECT * FROM vendors_payment_methods 
            WHERE vendor_id = ? AND is_default = 1
            LIMIT 1
        ");
        $stmt->execute([$vendorId]);
        $method = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($method) {
            return $method;
        }
        
        // If no default, get any method
        $stmt = $this->db->prepare("
            SELECT * FROM vendors_payment_methods 
            WHERE vendor_id = ? 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$vendorId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all payment methods for a vendor with details
     */
    public function getVendorPaymentMethods($vendorId) {
        $methods = [];
        
        // Get from vendors_payment_methods
        $stmt = $this->db->prepare("
            SELECT * FROM vendors_payment_methods 
            WHERE vendor_id = ? 
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$vendorId]);
        $baseMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($baseMethods as $baseMethod) {
            $details = $this->getMethodDetails($baseMethod);
            if ($details) {
                $methods[] = array_merge($baseMethod, ['details' => $details]);
            }
        }
        
        return $methods;
    }
    
    /**
     * Get detailed information for a payment method
     */
    private function getMethodDetails($method) {
        switch ($method['method_type']) {
            case 'bank':
                $stmt = $this->db->prepare("
                    SELECT * FROM vendor_bank_accounts 
                    WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                    ORDER BY is_default DESC LIMIT 1
                ");
                $stmt->execute([$method['id'], $method['vendor_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
                
            case 'paypal':
                $stmt = $this->db->prepare("
                    SELECT * FROM vendor_paypal_accounts 
                    WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                    ORDER BY is_default DESC LIMIT 1
                ");
                $stmt->execute([$method['id'], $method['vendor_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
                
            case 'stripe':
                $stmt = $this->db->prepare("
                    SELECT * FROM vendor_stripe_accounts 
                    WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                    ORDER BY is_default DESC LIMIT 1
                ");
                $stmt->execute([$method['id'], $method['vendor_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
                
            case 'easypaisa':
            case 'jazzcash':
                $stmt = $this->db->prepare("
                    SELECT * FROM vendor_mobile_accounts 
                    WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                    ORDER BY is_default DESC LIMIT 1
                ");
                $stmt->execute([$method['id'], $method['vendor_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    /**
     * Verify a vendor's payment method
     */
    public function verifyVendorMethod($methodId, $verifiedBy) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                UPDATE vendors_payment_methods 
                SET is_verified = 1, verified_by = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$verifiedBy, $methodId]);
            
            // Also update the specific account table
            $stmt = $this->db->prepare("
                SELECT method_type, vendor_id FROM vendors_payment_methods WHERE id = ?
            ");
            $stmt->execute([$methodId]);
            $method = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($method) {
                switch ($method['method_type']) {
                    case 'bank':
                        $updateStmt = $this->db->prepare("
                            UPDATE vendor_bank_accounts 
                            SET is_verified = 1, verified_by = ?, verified_at = NOW()
                            WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                        ");
                        $updateStmt->execute([$verifiedBy, $methodId, $method['vendor_id']]);
                        break;
                        
                    case 'paypal':
                        $updateStmt = $this->db->prepare("
                            UPDATE vendor_paypal_accounts 
                            SET is_verified = 1, verified_by = ?, verified_at = NOW()
                            WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                        ");
                        $updateStmt->execute([$verifiedBy, $methodId, $method['vendor_id']]);
                        break;
                        
                    case 'stripe':
                        $updateStmt = $this->db->prepare("
                            UPDATE vendor_stripe_accounts 
                            SET is_verified = 1, verified_by = ?, verified_at = NOW()
                            WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                        ");
                        $updateStmt->execute([$verifiedBy, $methodId, $method['vendor_id']]);
                        break;
                        
                    case 'easypaisa':
                    case 'jazzcash':
                        $updateStmt = $this->db->prepare("
                            UPDATE vendor_mobile_accounts 
                            SET is_verified = 1, verified_by = ?, verified_at = NOW()
                            WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                        ");
                        $updateStmt->execute([$verifiedBy, $methodId, $method['vendor_id']]);
                        break;
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Payment method verified successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reject a vendor's payment method
     */
    public function rejectVendorMethod($methodId, $reason, $rejectedBy) {
        try {
            $stmt = $this->db->prepare("
                UPDATE vendors_payment_methods 
                SET verification_notes = ?, rejection_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$reason, $reason, $methodId]);
            
            return [
                'success' => true,
                'message' => 'Payment method rejected'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get pending vendor payments (commissions)
     */
    public function getPendingVendorPayments($vendorId = null) {
        $sql = "
            SELECT 
                vc.*,
                o.order_number,
                o.created_at as order_date,
                o.total_amount as order_total,
                u.full_name as vendor_name,
                u.email as vendor_email,
                u.username,
                p.name as product_name,
                p.id as product_id
            FROM vendor_commissions vc
            JOIN orders o ON vc.order_id = o.id
            JOIN users u ON vc.vendor_id = u.id
            JOIN products p ON vc.product_id = p.id
            WHERE vc.status = 'pending'
        ";
        
        $params = [];
        if ($vendorId) {
            $sql .= " AND vc.vendor_id = ?";
            $params[] = $vendorId;
        }
        
        $sql .= " ORDER BY vc.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get vendor earnings summary
     */
    public function getVendorEarningsSummary($vendorId = null) {
        $sql = "
            SELECT 
                ve.vendor_id,
                u.full_name as vendor_name,
                u.email as vendor_email,
                u.username,
                COUNT(DISTINCT ve.order_id) as total_orders,
                COALESCE(SUM(ve.amount), 0) as total_earnings,
                COALESCE(SUM(CASE WHEN ve.status = 'paid' THEN ve.amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN ve.status = 'pending' THEN ve.amount ELSE 0 END), 0) as pending_amount,
                MAX(CASE WHEN ve.status = 'paid' THEN ve.paid_date ELSE NULL END) as last_paid_date,
                (SELECT COUNT(*) FROM vendor_withdrawal_requests WHERE vendor_id = ve.vendor_id AND status = 'pending') as pending_withdrawals
            FROM vendor_earnings ve
            JOIN users u ON ve.vendor_id = u.id
            WHERE u.user_type = 'vendor'
        ";
        
        $params = [];
        if ($vendorId) {
            $sql .= " AND ve.vendor_id = ?";
            $params[] = $vendorId;
        }
        
        $sql .= " GROUP BY ve.vendor_id, u.full_name, u.email, u.username ORDER BY total_earnings DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get vendor earnings history
     */
    public function getVendorEarningsHistory($vendorId, $limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT 
                ve.*,
                o.order_number,
                o.created_at as order_date,
                p.name as product_name
            FROM vendor_earnings ve
            JOIN orders o ON ve.order_id = o.id
            JOIN products p ON ve.product_id = p.id
            WHERE ve.vendor_id = ?
            ORDER BY ve.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$vendorId, $limit, $offset]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get withdrawal requests
     */
    public function getWithdrawalRequests($status = null, $vendorId = null) {
        $sql = "
            SELECT 
                vwr.*,
                u.full_name as vendor_name,
                u.email as vendor_email,
                u.username,
                CONCAT(u.full_name, ' (', u.email, ')') as vendor_display
            FROM vendor_withdrawal_requests vwr
            JOIN users u ON vwr.vendor_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        if ($status) {
            $sql .= " AND vwr.status = ?";
            $params[] = $status;
        }
        
        if ($vendorId) {
            $sql .= " AND vwr.vendor_id = ?";
            $params[] = $vendorId;
        }
        
        $sql .= " ORDER BY 
            CASE vwr.status 
                WHEN 'pending' THEN 1 
                WHEN 'processing' THEN 2 
                WHEN 'approved' THEN 3
                WHEN 'completed' THEN 4
                ELSE 5 
            END,
            vwr.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process withdrawal request
     */
    public function processWithdrawalRequest($requestId, $action, $adminId, $notes = null) {
        try {
            $this->db->beginTransaction();
            
            // Get withdrawal request
            $stmt = $this->db->prepare("
                SELECT * FROM vendor_withdrawal_requests WHERE id = ?
            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                throw new Exception("Withdrawal request not found");
            }
            
            if ($request['status'] != 'pending') {
                throw new Exception("Withdrawal request is already " . $request['status']);
            }
            
            if ($action == 'approve') {
                // Process payment
                $result = $this->processVendorPayment(
                    $request['vendor_id'],
                    $request['order_id'] ?? 0,
                    $request['request_amount']
                );
                
                if ($result['success']) {
                    // Update request status
                    $updateStmt = $this->db->prepare("
                        UPDATE vendor_withdrawal_requests 
                        SET status = 'completed', 
                            processed_by = ?, 
                            processed_at = NOW(), 
                            transaction_id = ?,
                            admin_notes = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$adminId, $result['transaction_id'], $notes, $requestId]);
                    
                    $this->db->commit();
                    
                    return [
                        'success' => true,
                        'message' => 'Withdrawal approved and paid',
                        'transaction_id' => $result['transaction_id']
                    ];
                } else {
                    $this->db->rollBack();
                    return $result;
                }
                
            } elseif ($action == 'reject') {
                // Reject request
                $updateStmt = $this->db->prepare("
                    UPDATE vendor_withdrawal_requests 
                    SET status = 'rejected', 
                        processed_by = ?, 
                        processed_at = NOW(),
                        admin_notes = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$adminId, $notes, $requestId]);
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'message' => 'Withdrawal request rejected'
                ];
            }
            
            throw new Exception("Invalid action: " . $action);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get admin accounts summary
     */
    public function getAdminAccountsSummary() {
        $stmt = $this->db->query("
            SELECT 
                account_type,
                COUNT(*) as total_accounts,
                SUM(current_balance) as total_balance,
                SUM(total_credited) as total_credited,
                SUM(total_debited) as total_debited,
                SUM(CASE WHEN is_default = 1 THEN 1 ELSE 0 END) as default_count,
                MAX(last_transaction_at) as last_transaction
            FROM admin_accounts
            WHERE is_active = 1
            GROUP BY account_type
            ORDER BY total_balance DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($type = null, $limit = 100) {
        $transactions = [];
        
        if (!$type || $type == 'customer') {
            $stmt = $this->db->prepare("
                SELECT 
                    'customer' as transaction_type,
                    pt.id,
                    pt.transaction_id,
                    pt.order_id,
                    pt.user_id,
                    u.full_name as user_name,
                    pt.gateway,
                    pt.amount,
                    pt.status,
                    pt.created_at,
                    o.order_number
                FROM payment_transactions pt
                LEFT JOIN users u ON pt.user_id = u.id
                LEFT JOIN orders o ON pt.order_id = o.id
                ORDER BY pt.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        if (!$type || $type == 'vendor') {
            $stmt = $this->db->prepare("
                SELECT 
                    'vendor' as transaction_type,
                    wt.id,
                    wt.transaction_id,
                    wt.order_id,
                    wt.vendor_id as user_id,
                    u.full_name as user_name,
                    'payout' as gateway,
                    wt.amount,
                    wt.status,
                    wt.created_at,
                    o.order_number
                FROM withdrawal_transactions wt
                LEFT JOIN users u ON wt.vendor_id = u.id
                LEFT JOIN orders o ON wt.order_id = o.id
                ORDER BY wt.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        // Sort by created_at descending
        usort($transactions, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Limit results
        $transactions = array_slice($transactions, 0, $limit);
        
        return $transactions;
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Total vendors with earnings
        $stmt = $this->db->query("
            SELECT COUNT(DISTINCT vendor_id) as total_vendors_with_earnings
            FROM vendor_earnings
        ");
        $stats['vendors_with_earnings'] = $stmt->fetchColumn();
        
        // Total pending payments
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(commission_amount), 0) as total_pending
            FROM vendor_commissions
            WHERE status = 'pending'
        ");
        $stats['total_pending'] = $stmt->fetchColumn();
        
        // Total paid
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total_paid
            FROM vendor_earnings
            WHERE status = 'paid'
        ");
        $stats['total_paid'] = $stmt->fetchColumn();
        
        // Pending withdrawal requests
        $stmt = $this->db->query("
            SELECT COUNT(*) as pending_requests, COALESCE(SUM(request_amount), 0) as pending_amount
            FROM vendor_withdrawal_requests
            WHERE status = 'pending'
        ");
        $stats['withdrawals'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Admin account balance
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(current_balance), 0) as total_balance
            FROM admin_accounts
            WHERE is_active = 1
        ");
        $stats['admin_balance'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    private function recordVendorEarning($vendorId, $orderId, $amount, $transactionId) {
        // Get product IDs from order
        $stmt = $this->db->prepare("
            SELECT product_id FROM order_items WHERE order_id = ?
        ");
        $stmt->execute([$orderId]);
        $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($products as $productId) {
            $stmt = $this->db->prepare("
                INSERT INTO vendor_earnings 
                (vendor_id, order_id, product_id, amount, transaction_id, status, paid_date)
                VALUES (?, ?, ?, ?, ?, 'paid', NOW())
            ");
            $stmt->execute([$vendorId, $orderId, $productId, $amount, $transactionId]);
        }
    }
    
    private function updateCommissionStatus($orderId, $vendorId, $status) {
        $stmt = $this->db->prepare("
            UPDATE vendor_commissions 
            SET status = ?, paid_at = NOW() 
            WHERE order_id = ? AND vendor_id = ?
        ");
        $stmt->execute([$status, $orderId, $vendorId]);
    }
    
    private function addToBalanceHistory($vendorId, $orderId, $amount, $type) {
        // Get admin account used
        $stmt = $this->db->prepare("
            SELECT id, current_balance FROM admin_accounts 
            WHERE account_type = (SELECT method_type FROM vendors_payment_methods WHERE vendor_id = ? AND is_default = 1)
            AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$vendorId]);
        $adminAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($adminAccount) {
            $balanceStmt = $this->db->prepare("
                INSERT INTO account_balance_history 
                (admin_account_id, balance, change_amount, change_type, reference_id, reference_type, notes)
                VALUES (?, ?, ?, ?, ?, 'order', ?)
            ");
            $balanceStmt->execute([
                $adminAccount['id'],
                $adminAccount['current_balance'] - $amount,
                $amount,
                $type,
                $orderId,
                'Payment to vendor #' . $vendorId
            ]);
        }
    }
}