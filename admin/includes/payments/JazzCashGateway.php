<?php
// includes/payments/JazzCashGateway.php

require_once 'PaymentGateway.php';
use Omnipay\Omnipay;
use Omnipay\Jazzcash\Gateway as OmnipayJazzcashGateway;

class JazzCashGateway extends PaymentGateway {
    private $merchantId;
    private $password;
    private $secretKey;
    
    /**
     * @var OmnipayJazzcashGateway|null
     */
    private $omnipayGateway;
    
    protected function loadConfig() {
        $stmt = $this->db->prepare("
            SELECT * FROM admin_accounts 
            WHERE account_type = 'jazzcash' AND is_active = 1 
            ORDER BY is_default DESC LIMIT 1
        ");
        $stmt->execute();
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            $this->merchantId = $account['account_number'] ?? '';
            $this->password = $account['phone_number'] ?? '';
            $this->secretKey = $account['swift_code'] ?? '';
            
            // Initialize Omnipay gateway
            $this->omnipayGateway = Omnipay::create('Jazzcash');
            
            if ($this->omnipayGateway) {
                $this->omnipayGateway->initialize([
                    'merchantId' => $this->merchantId,
                    'password' => $this->password,
                    'secretKey' => $this->secretKey,
                ]);
                
                // Set test mode
                if (method_exists($this->omnipayGateway, 'setTestMode')) {
                    $this->omnipayGateway->setTestMode($this->sandbox_mode);
                }
            }
        }
    }
    
    protected function getGatewayName() {
        return 'jazzcash';
    }
    
    public function setTestMode($mode) {
        parent::setTestMode($mode);
        
        if (isset($this->omnipayGateway) && method_exists($this->omnipayGateway, 'setTestMode')) {
            $this->omnipayGateway->setTestMode($this->sandbox_mode);
        }
        
        $this->loadConfig();
    }
    
    public function processPayment($amount, $currency, $paymentData) {
        try {
            if (!$this->omnipayGateway) {
                throw new \Exception('JazzCash gateway not initialized');
            }
            
            $date = new \DateTime('now', new \DateTimeZone('Asia/Karachi'));
            $transactionId = $date->getTimestamp();
            
            $parameters = [
                [
                    'paymentMethod' => $paymentData['payment_method'] ?? 'MWALLET',
                    'transactionTimestamp' => $date,
                    'transactionExpiryTimestamp' => $date->modify('+30 minutes'),
                    'billReference' => (string) $transactionId,
                    'description' => 'Payment from E-Commerce Store',
                    'amount' => $amount,
                    'language' => 'EN',
                    'currency' => $currency,
                    'transactionId' => $transactionId,
                    'extra' => [
                        'field_1' => $paymentData['user_id'] ?? '',
                        'field_2' => $paymentData['order_id'] ?? '',
                        'field_3' => $paymentData['mobile_number'] ?? '',
                        'field_4' => $paymentData['email'] ?? '',
                        'field_5' => ''
                    ],
                    'returnUrl' => SITE_URL . 'payment-success.php'
                ]
            ];
            
            // Send purchase request
            /** @var mixed $response */
            $response = $this->omnipayGateway->purchase($parameters)->send();
            
            if ($response->isSuccessful()) {
                // Get response data
                $data = [];
                if (method_exists($response, 'getData')) {
                    $data = $response->getData();
                }
                
                $this->logTransaction(
                    $paymentData['user_id'] ?? 0,
                    'payment',
                    $amount,
                    'pending',
                    $data
                );
                
                $transactionRef = $data['pp_TxnRefNo'] ?? $data['transactionId'] ?? $transactionId;
                
                // ===== SIMPLE FIX: Check if redirect URL exists =====
                $redirectUrl = null;
                
                // Method 1: Direct method call with @ suppression (PHP 8+)
                if (method_exists($response, 'isRedirect') && $response->isRedirect()) {
                    // @phan-suppress-next-line - Ignore VSCode error
                    $redirectUrl = $response->getRedirectUrl();
                }
                
                // Method 2: Alternative - check in data array
                if (!$redirectUrl && is_array($data)) {
                    if (isset($data['redirect_url'])) {
                        $redirectUrl = $data['redirect_url'];
                    } elseif (isset($data['payment_url'])) {
                        $redirectUrl = $data['payment_url'];
                    } elseif (isset($data['url'])) {
                        $redirectUrl = $data['url'];
                    }
                }
                
                if ($redirectUrl) {
                    return [
                        'success' => true,
                        'redirect_url' => $redirectUrl,
                        'transaction_id' => $transactionRef
                    ];
                }
                
                return [
                    'success' => true,
                    'transaction_id' => $transactionRef,
                    'data' => $data
                ];
                
            } else {
                $errorMessage = method_exists($response, 'getMessage') ? $response->getMessage() : 'Payment failed';
                return [
                    'success' => false,
                    'error' => $errorMessage
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
                throw new \Exception('JazzCash gateway not initialized');
            }
            
            /** @var mixed $response */
            $response = $this->omnipayGateway->fetchTransaction([
                'transactionReference' => $transactionId
            ])->send();
            
            $data = [];
            if (method_exists($response, 'getData')) {
                $data = $response->getData();
            }
            
            $isComplete = ($data['pp_Status'] ?? '') === 'Complete';
            
            return [
                'success' => $isComplete,
                'data' => $data
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
            'error' => 'Refund not implemented'
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