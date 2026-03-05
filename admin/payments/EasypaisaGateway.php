<?php
// includes/payments/EasypaisaGateway.php

require_once 'PaymentGateway.php';
use Omnipay\Omnipay;
use Omnipay\Easypaisa\Gateway as OmnipayEasypaisaGateway; // Adjust namespace as per your package

class EasypaisaGateway extends PaymentGateway {
    private $storeId;
    private $username;
    private $password;
    private $accountNum;
    
    /**
     * @var OmnipayEasypaisaGateway|null
     */
    private $omnipayGateway; // Renamed to avoid confusion with base class $gateway
    
    protected function loadConfig() {
        $stmt = $this->db->prepare("
            SELECT * FROM admin_accounts 
            WHERE account_type = 'easypaisa' AND is_active = 1 
            ORDER BY is_default DESC LIMIT 1
        ");
        $stmt->execute();
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            $this->storeId = $account['account_number'] ?? '';
            $this->username = $account['account_holder'] ?? ''; 
            $this->password = $account['swift_code'] ?? ''; 
            $this->accountNum = $account['phone_number'] ?? '';
            
            // Initialize Omnipay gateway
            $this->omnipayGateway = Omnipay::create('Easypaisa');
            
            if ($this->omnipayGateway) {
                $this->omnipayGateway->initialize([
                    'storeId' => $this->storeId,
                    'username' => $this->username,
                    'password' => $this->password,
                    'accountNum' => $this->accountNum,
                ]);
                
                // Set test mode using the method
                if (method_exists($this->omnipayGateway, 'setTestMode')) {
                    $this->omnipayGateway->setTestMode($this->sandbox_mode);
                }
            }
        }
    }
    
    protected function getGatewayName() {
        return 'easypaisa';
    }
    
    /**
     * Override setTestMode to update gateway
     */
    public function setTestMode($mode) {
        parent::setTestMode($mode);
        
        // Update omnipay gateway if it exists
        if (isset($this->omnipayGateway) && method_exists($this->omnipayGateway, 'setTestMode')) {
            $this->omnipayGateway->setTestMode($this->sandbox_mode);
        }
        
        $this->loadConfig();
    }
    
    /**
     * Get the omnipay gateway instance
     * @return OmnipayEasypaisaGateway|null
     */
    protected function getOmnipayGateway() {
        return $this->omnipayGateway;
    }
    
    public function processPayment($amount, $currency, $paymentData) {
        try {
            // Check if gateway is initialized
            if (!$this->omnipayGateway) {
                throw new \Exception('Easypaisa gateway not initialized');
            }
            
            $transactionId = 'EP' . time() . rand(1000, 9999);
            
            $parameters = [
                'transactionId' => $transactionId,
                'amount' => $amount,
                'paymentMethod' => $paymentData['payment_method'] ?? 'MA', // MA or OTC
                'emailAddress' => $paymentData['email'] ?? '',
                'mobileNumber' => $paymentData['mobile_number'] ?? '',
                'tokenExpiry' => 30 * 60, // 30 minutes
                'extra' => [
                    'user_id' => $paymentData['user_id'] ?? '',
                    'order_id' => $paymentData['order_id'] ?? '',
                    'mode' => $this->sandbox_mode ? 'test' : 'live'
                ]
            ];
            
            $response = $this->omnipayGateway->purchase($parameters)->send();
            
            if ($response->isSuccessful()) {
                $data = $response->getData();
                
                $this->logTransaction(
                    $paymentData['user_id'] ?? 0,
                    'payment',
                    $amount,
                    'completed',
                    $data
                );
                
                return [
                    'success' => true,
                    'transaction_id' => $data['transactionId'] ?? $transactionId,
                    'data' => $data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->getMessage() ?? 'Payment failed'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function verifyPayment($transactionId) {
        try {
            if (!$this->omnipayGateway) {
                throw new \Exception('Easypaisa gateway not initialized');
            }
            
            $response = $this->omnipayGateway->fetchTransaction([
                'transactionId' => $transactionId
            ])->send();
            
            return [
                'success' => $response->isSuccessful(),
                'data' => $response->getData()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function refundPayment($transactionId, $amount) {
        return [
            'success' => false,
            'error' => 'Refund not implemented for Easypaisa'
        ];
    }
    
    public function getBalance() {
        return [
            'success' => true,
            'balance' => 0,
            'note' => 'Balance query not supported'
        ];
    }
}