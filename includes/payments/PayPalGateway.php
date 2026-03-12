<?php
namespace Ecommerce\Payments;

class PayPalGateway extends PaymentGateway implements PaymentGatewayInterface {
    
    protected $gatewayCode = 'paypal';
    protected $gatewayName = 'PayPal';
    private $clientId;
    private $secret;
    private $apiUrl;
    
    public function initialize($config) {
        $this->config = array_merge($this->config, $config);
        $this->clientId = $config['api_key'] ?? $this->config['account_email'] ?? '';
        $this->secret = $config['api_secret'] ?? $this->config['account_number'] ?? '';
        $this->setApiUrl();
        return $this;
    }
    
    private function setApiUrl() {
        if ($this->testMode) {
            $this->apiUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $this->apiUrl = 'https://api-m.paypal.com';
        }
    }
    
    public function processCustomerPayment($order, $paymentData) {
    try {
        // For demo, simulate PayPal payment
        $transactionId = $this->logTransaction(
            'customer_payment',
            $order['id'],
            $order['user_id'],
            $order['total_amount'],
            'completed',
            ['method' => 'paypal']
        );
        
        // Update order payment status
        $stmt = $this->db->prepare("
            UPDATE orders SET 
            payment_status = 'completed', 
            transaction_id = ?,
            updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transactionId, $order['id']]);
        
        // Update admin account balance (for PayPal)
        $adminAccount = $this->getDefaultAdminAccount();
        if ($adminAccount) {
            $stmt = $this->db->prepare("
                UPDATE admin_accounts 
                SET current_balance = current_balance + ?,
                    total_credited = total_credited + ?,
                    last_transaction_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$order['total_amount'], $order['total_amount'], $adminAccount['id']]);
            
            // Add to balance history
            $stmt = $this->db->prepare("
                INSERT INTO account_balance_history 
                (admin_account_id, balance, change_amount, change_type, reference_id, reference_type, notes)
                VALUES (?, (SELECT current_balance FROM admin_accounts WHERE id = ?), ?, 'credit', ?, 'order', ?)
            ");
            $stmt->execute([
                $adminAccount['id'], 
                $adminAccount['id'],
                $order['total_amount'], 
                $order['id'], 
                "PayPal payment for order #{$order['order_number']}"
            ]);
        }
        
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'message' => 'Payment processed via PayPal',
            'redirect' => SITE_URL . 'user/orders/order-confirmation.php?id=' . $order['id']
        ];
        
    } catch (\Exception $e) {
        error_log("PayPal Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'PayPal payment failed: ' . $e->getMessage()
        ];
    }
}
    
    public function processVendorPayout($vendorId, $amount, $payoutData) {
        try {
            $token = $this->getAccessToken();
            
            if (!$token) {
                return ['success' => false, 'message' => 'Failed to authenticate with PayPal'];
            }
            
            // Get vendor's PayPal account
            $vendorAccount = $this->getVendorPaymentMethods($vendorId)[0] ?? null;
            
            if (!$vendorAccount || empty($vendorAccount['paypal_email'])) {
                return ['success' => false, 'message' => 'Vendor has no PayPal account configured'];
            }
            
            // Create payout
            $payout = [
                'sender_batch_header' => [
                    'sender_batch_id' => 'Payout_' . uniqid(),
                    'email_subject' => 'You have received a payment',
                    'email_message' => 'You have received a payment of $' . number_format($amount, 2)
                ],
                'items' => [[
                    'recipient_type' => 'EMAIL',
                    'amount' => [
                        'value' => number_format($amount, 2, '.', ''),
                        'currency' => 'USD'
                    ],
                    'receiver' => $vendorAccount['paypal_email'],
                    'note' => 'Payment for order #' . ($payoutData['order_id'] ?? ''),
                    'sender_item_id' => 'item_' . uniqid()
                ]]
            ];
            
            $ch = curl_init($this->apiUrl . '/v1/payments/payouts');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payout));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode == 201) {
                $transactionId = $this->logTransaction(
                    'vendor_payout',
                    $payoutData['order_id'] ?? 0,
                    $vendorId,
                    $amount,
                    'completed',
                    $result
                );
                
                // Update admin account balance
                $adminAccount = $this->getDefaultAdminAccount();
                if ($adminAccount) {
                    $stmt = $this->db->prepare("
                        UPDATE admin_accounts 
                        SET current_balance = current_balance - ?,
                            total_debited = total_debited + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$amount, $amount, $adminAccount['id']]);
                }
                
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'payout_batch_id' => $result['batch_header']['payout_batch_id'] ?? null,
                    'message' => 'Payout sent successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Payout failed'
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function getAccessToken() {
        $ch = curl_init($this->apiUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->clientId . ':' . $this->secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? '';
        }
        
        return '';
    }
    
    private function getApprovalUrl($links) {
        foreach ($links as $link) {
            if ($link['rel'] == 'approval_url') {
                return $link['href'];
            }
        }
        return '';
    }
    
    public function verifyPayment($transactionId) {
        try {
            $token = $this->getAccessToken();
            
            if (!$token) {
                return ['success' => false, 'message' => 'Authentication failed'];
            }
            
            $ch = curl_init($this->apiUrl . '/v1/payments/payment/' . $transactionId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                $result = json_decode($response, true);
                
                $stmt = $this->db->prepare("
                    UPDATE payment_transactions 
                    SET status = ?, gateway_response = ? 
                    WHERE transaction_id = ? OR gateway_response LIKE ?
                ");
                
                $status = ($result['state'] ?? '') == 'approved' ? 'completed' : 'pending';
                $stmt->execute([$status, json_encode($result), $transactionId, '%' . $transactionId . '%']);
                
                return [
                    'success' => true,
                    'status' => $status,
                    'data' => $result
                ];
            }
            
            return ['success' => false, 'message' => 'Payment not found'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function refundPayment($transactionId, $amount = null) {
        // Implementation for refund
        return ['success' => false, 'message' => 'Refund not implemented'];
    }
    
    public function getPaymentMethods() {
        return [
            ['id' => 'paypal', 'name' => 'PayPal', 'icon' => 'fab fa-paypal']
        ];
    }
    
    public function validateAccount($accountDetails) {
        return !empty($accountDetails['paypal_email']) && 
               filter_var($accountDetails['paypal_email'], FILTER_VALIDATE_EMAIL);
    }
    
    public function getPaymentForm($order, $userAccounts = []) {
        $html = '<div class="paypal-form text-center">';
        $html .= '<div class="mb-4">';
        $html .= '<img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/PP_logo_h_100x26.png" alt="PayPal">';
        $html .= '</div>';
        
        $html .= '<div class="alert alert-info mb-4">';
        $html .= '<p class="mb-0">You will be redirected to PayPal to complete your payment securely.</p>';
        $html .= '</div>';
        
        if (!empty($userAccounts)) {
            $html .= '<h6 class="mb-3">Your Saved PayPal Accounts</h6>';
            $html .= '<div class="list-group mb-4">';
            foreach ($userAccounts as $account) {
                $details = json_decode($account['account_details'] ?? '{}', true);
                $html .= '<label class="list-group-item list-group-item-action">';
                $html .= '<input type="radio" name="paypal_account" value="' . $account['id'] . '" class="form-check-input me-2">';
                $html .= '<i class="fab fa-paypal me-2 text-primary"></i>';
                $html .= htmlspecialchars($details['paypal_email'] ?? 'PayPal Account');
                $html .= '</label>';
            }
            $html .= '</div>';
        }
        
        $html .= '<p class="text-muted small">';
        $html .= '<i class="fas fa-lock me-1"></i> Secure payment processed by PayPal';
        $html .= '</p>';
        $html .= '</div>';
        
        return $html;
    }
}