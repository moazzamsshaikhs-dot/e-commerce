<?php
namespace Ecommerce\Payments;

class JazzCashGateway extends PaymentGateway implements PaymentGatewayInterface {
    
    protected $gatewayCode = 'jazzcash';
    protected $gatewayName = 'JazzCash';
    
    public function initialize($config) {
        $this->config = array_merge($this->config ?? [], $config);
        return $this;
    }
    
    public function processCustomerPayment($order, $paymentData) {
        try {
            if (!isset($order['id']) || !isset($order['user_id']) || !isset($order['total_amount'])) {
                throw new \Exception('Invalid order data');
            }
            
            $mobileNumber = $paymentData['mobile_number'] ?? '';
            
            if (empty($mobileNumber)) {
                return [
                    'success' => false,
                    'message' => 'Mobile number is required for JazzCash payment'
                ];
            }
            
            // Validate mobile number
            if (!preg_match('/^03[0-9]{9}$/', $mobileNumber)) {
                return [
                    'success' => false,
                    'message' => 'Invalid mobile number format. Must be 03XXXXXXXXX'
                ];
            }
            
            // Get admin JazzCash account
            $adminAccount = $this->getDefaultAdminAccount();
            
            // Log transaction
            $transactionId = $this->logTransaction(
                'customer_payment',
                $order['id'],
                $order['user_id'],
                $order['total_amount'],
                'pending',
                [
                    'method' => 'jazzcash', 
                    'mobile' => $mobileNumber,
                    'admin_account' => $adminAccount['account_name'] ?? ''
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
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'JazzCash payment initiated. Please check your phone for OTP.',
                'redirect' => SITE_URL . 'user/orders/order-confirmation.php?id=' . $order['id']
            ];
            
        } catch (\Exception $e) {
            error_log("JazzCash Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'JazzCash payment failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function processVendorPayout($vendorId, $amount, $payoutData) {
        try {
            // Get vendor's JazzCash account
            $stmt = $this->db->prepare("
                SELECT vma.*, vpm.id as method_id
                FROM vendor_mobile_accounts vma
                JOIN vendors_payment_methods vpm ON vma.payment_method_id = vpm.id
                WHERE vma.vendor_id = ? AND vma.account_type = 'jazzcash' AND vma.is_default = 1
                LIMIT 1
            ");
            $stmt->execute([$vendorId]);
            $mobileAccount = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$mobileAccount) {
                return [
                    'success' => false,
                    'message' => 'Vendor has no JazzCash account configured'
                ];
            }
            
            $transactionId = $this->logTransaction(
                'vendor_payout',
                $payoutData['order_id'] ?? 0,
                $vendorId,
                $amount,
                'processing',
                [
                    'mobile' => $mobileAccount['mobile_number'],
                    'account_holder' => $mobileAccount['account_holder_name']
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
                'message' => 'JazzCash payout initiated'
            ];
            
        } catch (\Exception $e) {
            error_log("JazzCash Payout Error: " . $e->getMessage());
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
            'message' => 'Refund not implemented for JazzCash'
        ];
    }
    
    public function getPaymentMethods() {
        return [
            [
                'id' => 'jazzcash', 
                'name' => 'JazzCash', 
                'icon' => 'fas fa-mobile-alt',
                'description' => 'Pay using your JazzCash mobile account'
            ]
        ];
    }
    
    public function validateAccount($accountDetails) {
        return !empty($accountDetails['mobile_number']) && 
               preg_match('/^03[0-9]{9}$/', $accountDetails['mobile_number']);
    }
    
    public function getPaymentForm($order, $userAccounts = []) {
        if (!isset($order['total_amount'])) {
            $order['total_amount'] = 0;
        }
        
        $html = '<div class="jazzcash-form">';
        
        $adminAccounts = $this->getAdminJazzCashAccounts();
        
        if (!empty($adminAccounts)) {
            $html .= '<div class="alert alert-info mb-3">';
            $html .= '<i class="fas fa-info-circle me-2"></i>';
            $html .= 'Send payment to JazzCash account: <strong>' . 
                     htmlspecialchars($adminAccounts[0]['account_name'] ?? 'JazzCash Account') . '</strong>';
            if (!empty($adminAccounts[0]['phone_number'])) {
                $html .= '<br><small>Mobile: ' . htmlspecialchars($adminAccounts[0]['phone_number']) . '</small>';
            }
            $html .= '</div>';
        }
        
        $html .= '<div class="mb-3">';
        $html .= '<label class="form-label">Your Mobile Number (03XXXXXXXXX) <span class="text-danger">*</span></label>';
        $html .= '<input type="tel" name="mobile_number" class="form-control" pattern="03[0-9]{9}" placeholder="03001234567" required>';
        $html .= '<small class="text-muted">Enter the mobile number linked to your JazzCash account</small>';
        $html .= '</div>';
        
        $html .= '<div class="alert alert-warning">';
        $html .= '<i class="fas fa-exclamation-triangle me-2"></i>';
        $html .= 'You will receive an OTP on your mobile to confirm the payment.';
        $html .= '</div>';
        
        $html .= '<div class="alert alert-secondary small">';
        $html .= '<strong>Instructions:</strong><br>';
        $html .= '1. Open JazzCash app on your phone<br>';
        $html .= '2. Go to "Send Money"<br>';
        $html .= '3. Enter the merchant account number shown above<br>';
        $html .= '4. Enter amount: <strong>$' . number_format($order['total_amount'], 2) . '</strong><br>';
        $html .= '5. Enter your mobile number above and complete the payment';
        $html .= '</div>';
        
        $html .= '<input type="hidden" name="payment_method" value="jazzcash">';
        $html .= '</div>';
        
        return $html;
    }
    
    private function getAdminJazzCashAccounts() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM admin_accounts 
                WHERE account_type = 'jazzcash' AND is_active = 1 
                ORDER BY is_default DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error getting admin JazzCash accounts: " . $e->getMessage());
            return [];
        }
    }
}