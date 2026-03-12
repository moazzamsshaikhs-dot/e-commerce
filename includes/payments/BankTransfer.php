<?php
namespace Ecommerce\Payments;

class BankTransfer extends PaymentGateway implements PaymentGatewayInterface {
    
    protected $gatewayCode = 'bank';
    protected $gatewayName = 'Bank Transfer';
    
    public function initialize($config) {
        $this->config = array_merge($this->config ?? [], $config);
        return $this;
    }
    
    public function processCustomerPayment($order, $paymentData) {
    try {
        if (!isset($order['id']) || !isset($order['user_id']) || !isset($order['total_amount'])) {
            throw new \Exception('Invalid order data');
        }
        
        $bankAccountId = $paymentData['bank_account_id'] ?? null;
        
        // Get admin bank account details
        $bankDetails = null;
        if ($bankAccountId) {
            $stmt = $this->db->prepare("
                SELECT * FROM admin_accounts 
                WHERE id = ? AND account_type IN ('bank', 'bank_transfer')
            ");
            $stmt->execute([$bankAccountId]);
            $bankDetails = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$bankDetails) {
            // Get default admin bank account
            $stmt = $this->db->prepare("
                SELECT * FROM admin_accounts 
                WHERE account_type IN ('bank', 'bank_transfer') AND is_default = 1
                LIMIT 1
            ");
            $stmt->execute();
            $bankDetails = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        $transactionId = $this->logTransaction(
            'customer_payment',
            $order['id'],
            $order['user_id'],
            $order['total_amount'],
            'pending',
            [
                'method' => 'bank_transfer', 
                'bank_account_id' => $bankDetails['id'] ?? null,
                'bank_name' => $bankDetails['bank_name'] ?? ''
            ]
        );
        
        // Update order payment status
        $stmt = $this->db->prepare("
            UPDATE orders SET 
            payment_status = 'pending', 
            transaction_id = ?,
            updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transactionId, $order['id']]);
        
        // Store bank details in session for confirmation page
        $_SESSION['bank_details'] = $bankDetails;
        
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'message' => 'Bank transfer instructions sent. Please complete the payment.',
            'redirect' => SITE_URL . 'user/orders/order-confirmation.php?id=' . $order['id'],
            'bank_details' => $bankDetails
        ];
        
    } catch (\Exception $e) {
        error_log("Bank Transfer Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Payment processing failed: ' . $e->getMessage()
        ];
    }
}
    
    public function processVendorPayout($vendorId, $amount, $payoutData) {
        try {
            // Get vendor's bank account
            $stmt = $this->db->prepare("
                SELECT vba.*, vpm.id as method_id
                FROM vendor_bank_accounts vba
                JOIN vendors_payment_methods vpm ON vba.payment_method_id = vpm.id
                WHERE vba.vendor_id = ? AND vpm.method_type = 'bank' AND vba.is_default = 1
                LIMIT 1
            ");
            $stmt->execute([$vendorId]);
            $bankAccount = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bankAccount) {
                return [
                    'success' => false,
                    'message' => 'Vendor has no bank account configured'
                ];
            }
            
            // For bank transfers, we just record the transaction
            // Actual transfer would be done manually or via batch file
            
            $transactionId = $this->logTransaction(
                'vendor_payout',
                $payoutData['order_id'] ?? 0,
                $vendorId,
                $amount,
                'processing',
                [
                    'bank_account' => $bankAccount['account_number'],
                    'bank_name' => $bankAccount['bank_name'],
                    'account_holder' => $bankAccount['account_holder_name']
                ]
            );
            
            // Update admin account balance
            $adminAccount = $this->getDefaultAdminAccount();
            if ($adminAccount) {
                $stmt = $this->db->prepare("
                    UPDATE admin_accounts 
                    SET current_balance = current_balance - ?,
                        total_debited = total_debited + ?,
                        last_transaction_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$amount, $amount, $adminAccount['id']]);
            }
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Bank transfer initiated'
            ];
            
        } catch (\Exception $e) {
            error_log("Vendor Payout Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function verifyPayment($transactionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM payment_transactions 
                WHERE transaction_id = ? OR id = ?
            ");
            $stmt->execute([$transactionId, $transactionId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function refundPayment($transactionId, $amount = null) {
        return [
            'success' => false, 
            'message' => 'Refund not available for bank transfers'
        ];
    }
    
    public function getPaymentMethods() {
        return [
            [
                'id' => 'bank_transfer', 
                'name' => 'Bank Transfer', 
                'icon' => 'fas fa-university',
                'description' => 'Transfer directly to our bank account'
            ]
        ];
    }
    
    public function validateAccount($accountDetails) {
        return !empty($accountDetails['account_number']) && 
               !empty($accountDetails['account_holder']);
    }
    
    public function getPaymentForm($order, $userAccounts = []) {
        if (!isset($order['total_amount'])) {
            $order['total_amount'] = 0;
        }
        
        $html = '<div class="bank-transfer-form">';
        
        // Get admin bank accounts
        $adminAccounts = $this->getAdminBankAccounts();
        
        if (!empty($adminAccounts)) {
            $html .= '<h6 class="mb-3">Transfer to Bank Account</h6>';
            $html .= '<div class="list-group mb-4">';
            
            foreach ($adminAccounts as $account) {
                $html .= '<div class="list-group-item">';
                $html .= '<div class="form-check">';
                $html .= '<input class="form-check-input" type="radio" name="bank_account_id" value="' . $account['id'] . '" ';
                $html .= $account['is_default'] ? 'checked' : '';
                $html .= ' id="bank_' . $account['id'] . '" required>';
                $html .= '<label class="form-check-label w-100" for="bank_' . $account['id'] . '">';
                $html .= '<strong>' . htmlspecialchars($account['account_name']) . '</strong><br>';
                $html .= '<small class="text-muted d-block">Bank: ' . htmlspecialchars($account['bank_name'] ?? 'N/A') . '</small>';
                $html .= '<small class="text-muted d-block">Account: ' . htmlspecialchars($account['account_number'] ?? 'N/A') . '</small>';
                if (!empty($account['iban'])) {
                    $html .= '<small class="text-muted d-block">IBAN: ' . htmlspecialchars($account['iban']) . '</small>';
                }
                $html .= '</label>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="alert alert-danger">No bank accounts configured. Please contact support.</div>';
        }
        
        $html .= '<div class="alert alert-info mt-3">';
        $html .= '<h6 class="alert-heading">Bank Transfer Instructions:</h6>';
        $html .= '<p class="mb-2">Please transfer the exact amount to the selected bank account.</p>';
        $html .= '<p class="mb-1"><strong>Amount to Pay:</strong> $' . number_format($order['total_amount'], 2) . '</p>';
        $html .= '<p class="mb-1"><strong>Reference:</strong> Order #' . ($order['order_number'] ?? '') . '</p>';
        $html .= '<p class="mb-0 small text-danger">Include order number in transfer description for faster processing.</p>';
        $html .= '</div>';
        
        $html .= '<input type="hidden" name="payment_method" value="bank_transfer">';
        $html .= '</div>';
        
        return $html;
    }
    
    private function getAdminBankAccounts() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM admin_accounts 
                WHERE account_type IN ('bank', 'bank_transfer') 
                AND is_active = 1 
                ORDER BY is_default DESC, id ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error getting admin bank accounts: " . $e->getMessage());
            return [];
        }
    }
}