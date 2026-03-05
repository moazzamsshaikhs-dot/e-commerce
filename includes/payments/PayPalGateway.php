<?php
// includes/payments/PayPalGateway.php

require_once 'PaymentGateway.php';

class PayPalGateway extends PaymentGateway {
    private $clientId;
    private $secret;
    private $apiUrl;
    private $apiContext;
    
    protected function loadConfig() {
        // Load from database
        $stmt = $this->db->prepare("
            SELECT * FROM admin_accounts 
            WHERE account_type = 'paypal' AND is_active = 1 
            ORDER BY is_default DESC LIMIT 1
        ");
        $stmt->execute();
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            $this->clientId = $account['account_email'] ?? ''; // PayPal uses email as client ID
            $this->secret = $account['account_number'] ?? ''; // Store secret in account_number
            
            // Set API URL based on mode
            $this->setApiUrl();
        }
    }
    
    /**
     * Set API URL based on sandbox mode
     */
    private function setApiUrl() {
        if ($this->sandbox_mode) {
            $this->apiUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $this->apiUrl = 'https://api-m.paypal.com';
        }
    }
    
    /**
     * Override setTestMode to update API URL
     */
    public function setTestMode($mode) {
        parent::setTestMode($mode);
        $this->setApiUrl(); // Update API URL when mode changes
    }
    
    protected function getGatewayName() {
        return 'paypal';
    }
    
    public function processPayment($amount, $currency, $paymentData) {
        try {
            // Get access token
            $token = $this->getAccessToken();
            
            // Create payment
            $payment = [
                'intent' => 'sale',
                'payer' => [
                    'payment_method' => 'paypal'
                ],
                'transactions' => [[
                    'amount' => [
                        'total' => number_format($amount, 2, '.', ''),
                        'currency' => $currency
                    ],
                    'description' => 'Payment from E-Commerce Store'
                ]],
                'redirect_urls' => [
                    'return_url' => SITE_URL . 'payment-success.php',
                    'cancel_url' => SITE_URL . 'payment-cancel.php'
                ]
            ];
            
            // Make API call
            $ch = curl_init($this->apiUrl . '/v1/payments/payment');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode == 201) {
                // Log transaction
                $this->logTransaction(
                    $paymentData['user_id'] ?? 0,
                    'payment',
                    $amount,
                    'pending',
                    $result
                );
                
                return [
                    'success' => true,
                    'transaction_id' => $result['id'],
                    'approval_url' => $this->getApprovalUrl($result['links'])
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['message'] ?? 'Payment processing failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getAccessToken() {
        $ch = curl_init($this->apiUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->clientId . ':' . $this->secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? '';
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
        $ch = curl_init($this->apiUrl . '/v1/payments/payment/' . $transactionId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->getAccessToken()
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return [
            'success' => ($result['state'] ?? '') == 'approved',
            'data' => $result
        ];
    }
    
    public function refundPayment($transactionId, $amount) {
        // Get sale ID from payment
        $payment = $this->verifyPayment($transactionId);
        if (!$payment['success']) {
            return ['success' => false, 'error' => 'Payment not found'];
        }
        
        $saleId = $payment['data']['transactions'][0]['related_resources'][0]['sale']['id'] ?? '';
        
        if (!$saleId) {
            return ['success' => false, 'error' => 'Sale ID not found'];
        }
        
        $refund = [
            'amount' => [
                'total' => number_format($amount, 2, '.', ''),
                'currency' => 'USD'
            ]
        ];
        
        $ch = curl_init($this->apiUrl . '/v1/payments/sale/' . $saleId . '/refund');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($refund));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessToken()
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return [
            'success' => isset($result['id']),
            'refund_id' => $result['id'] ?? null,
            'data' => $result
        ];
    }
    
    public function getBalance() {
        $ch = curl_init($this->apiUrl . '/v1/reporting/balances');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->getAccessToken()
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        $balance = 0;
        if (isset($result['balances'])) {
            foreach ($result['balances'] as $bal) {
                if ($bal['currency'] == 'USD') {
                    $balance = $bal['total_balance']['value'] ?? 0;
                    break;
                }
            }
        }
        
        return [
            'success' => true,
            'balance' => $balance
        ];
    }
}