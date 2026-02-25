<?php
// includes/payment-gateways/JazzCashGateway.php

require_once dirname(__DIR__) . '/config/payment_config.php';
require_once dirname(__DIR__) . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class JazzCashGateway {
    private $merchant_id;
    private $password;
    private $integrity_salt;
    private $environment;
    private $client;
    private $logger;
    private $db;
    
    public function __construct($db = null) {
        $this->environment = JAZZCASH_ENVIRONMENT;
        $this->merchant_id = JAZZCASH_MERCHANT_ID;
        $this->password = JAZZCASH_PASSWORD;
        $this->integrity_salt = JAZZCASH_INTEGERITY_SALT;
        $this->db = $db;
        
        // Initialize HTTP client
        $this->client = new Client([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => 30.0,
            'verify' => false // Set to true in production
        ]);
        
        // Setup logging
        $this->logger = new Logger('jazzcash');
        $this->logger->pushHandler(new StreamHandler(PAYMENT_LOG_FILE, Logger::INFO));
    }
    
    /**
     * Get base URL based on environment
     */
    private function getBaseUrl() {
        if ($this->environment === 'production') {
            return 'https://payments.jazzcash.com.pk/';
        } else {
            return 'https://sandbox.jazzcash.com.pk/';
        }
    }
    
    /**
     * Generate integrity hash
     */
    private function generateIntegrityHash($data) {
        $hashString = $this->integrity_salt . '&' . implode('&', $data);
        return hash_hmac('sha256', $hashString, $this->integrity_salt);
    }
    
    /**
     * Initiate payment
     */
    public function initiatePayment($order_id, $amount, $mobile_number, $description = '') {
        try {
            $pp_TxnRefNo = 'JC' . $order_id . time();
            $pp_Amount = $amount * 100; // Convert to paisa
            
            $data = [
                'pp_Version' => '2.0',
                'pp_TxnType' => 'MPAY',
                'pp_Language' => 'EN',
                'pp_MerchantID' => $this->merchant_id,
                'pp_SubMerchantID' => '',
                'pp_Password' => $this->password,
                'pp_TxnRefNo' => $pp_TxnRefNo,
                'pp_Amount' => $pp_Amount,
                'pp_TxnCurrency' => 'PKR',
                'pp_TxnDateTime' => date('YmdHis'),
                'pp_BillReference' => 'bill_' . $order_id,
                'pp_Description' => $description,
                'pp_TxnExpiryDateTime' => date('YmdHis', strtotime('+1 day')),
                'pp_ReturnURL' => JAZZCASH_RETURN_URL,
                'pp_SecureHash' => '',
                'ppmpf_1' => $mobile_number,
                'ppmpf_2' => $order_id,
                'ppmpf_3' => '',
                'ppmpf_4' => '',
                'ppmpf_5' => ''
            ];
            
            // Generate secure hash
            $hashData = [
                $data['pp_Amount'],
                $data['pp_BillReference'],
                $data['pp_Description'],
                $data['pp_Language'],
                $data['pp_MerchantID'],
                $data['pp_Password'],
                $data['pp_ReturnURL'],
                $data['pp_TxnCurrency'],
                $data['pp_TxnDateTime'],
                $data['pp_TxnExpiryDateTime'],
                $data['pp_TxnRefNo'],
                $data['pp_TxnType'],
                $data['pp_Version'],
                $data['pp_SubMerchantID'],
                $data['ppmpf_1'],
                $data['ppmpf_2'],
                $data['ppmpf_3'],
                $data['ppmpf_4'],
                $data['ppmpf_5']
            ];
            
            $data['pp_SecureHash'] = $this->generateIntegrityHash($hashData);
            
            $this->logger->info("JazzCash payment initiated", [
                'txn_ref' => $pp_TxnRefNo,
                'amount' => $amount
            ]);
            
            return [
                'success' => true,
                'payment_data' => $data,
                'payment_url' => $this->getBaseUrl() . 'Payment/Index'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("JazzCash payment initiation failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify payment response
     */
    public function verifyPayment($post_data) {
        try {
            $required_fields = [
                'pp_ResponseCode',
                'pp_ResponseMessage',
                'pp_TxnRefNo',
                'pp_Amount',
                'pp_MerchantID',
                'pp_TxnCurrency',
                'pp_TxnDateTime',
                'pp_BillReference',
                'pp_SecureHash'
            ];
            
            // Verify all fields exist
            foreach ($required_fields as $field) {
                if (!isset($post_data[$field])) {
                    throw new Exception("Missing field: $field");
                }
            }
            
            // Verify response code
            if ($post_data['pp_ResponseCode'] !== '000') {
                throw new Exception($post_data['pp_ResponseMessage']);
            }
            
            // Verify hash
            $hashData = [
                $post_data['pp_Amount'],
                $post_data['pp_BillReference'],
                $post_data['pp_Description'] ?? '',
                $post_data['pp_Language'] ?? '',
                $post_data['pp_MerchantID'],
                $post_data['pp_Password'] ?? $this->password,
                $post_data['pp_ReturnURL'] ?? '',
                $post_data['pp_TxnCurrency'],
                $post_data['pp_TxnDateTime'],
                $post_data['pp_TxnExpiryDateTime'] ?? '',
                $post_data['pp_TxnRefNo'],
                $post_data['pp_TxnType'] ?? '',
                $post_data['pp_Version'] ?? '',
                $post_data['pp_SubMerchantID'] ?? '',
                $post_data['ppmpf_1'] ?? '',
                $post_data['ppmpf_2'] ?? '',
                $post_data['ppmpf_3'] ?? '',
                $post_data['ppmpf_4'] ?? '',
                $post_data['ppmpf_5'] ?? ''
            ];
            
            $calculated_hash = $this->generateIntegrityHash($hashData);
            
            if ($calculated_hash !== $post_data['pp_SecureHash']) {
                throw new Exception("Invalid hash");
            }
            
            $this->logger->info("JazzCash payment verified", [
                'txn_ref' => $post_data['pp_TxnRefNo']
            ]);
            
            return [
                'success' => true,
                'data' => $post_data
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("JazzCash verification failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send money to vendor (payout)
     */
    public function sendMoney($mobile_number, $amount, $vendor_name) {
        try {
            $txn_ref = 'JCWDR' . time();
            
            $data = [
                'pp_Version' => '2.0',
                'pp_TxnType' => 'MWALLET',
                'pp_Language' => 'EN',
                'pp_MerchantID' => $this->merchant_id,
                'pp_Password' => $this->password,
                'pp_TxnRefNo' => $txn_ref,
                'pp_Amount' => $amount * 100,
                'pp_TxnCurrency' => 'PKR',
                'pp_TxnDateTime' => date('YmdHis'),
                'pp_Description' => "Withdrawal for vendor: $vendor_name",
                'pp_MobileNumber' => $mobile_number
            ];
            
            $response = $this->client->post('api/v1/payout', [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            $this->logger->info("JazzCash payout sent", [
                'txn_ref' => $txn_ref,
                'response' => $result
            ]);
            
            if ($result['pp_ResponseCode'] === '000') {
                return [
                    'success' => true,
                    'transaction_id' => $result['pp_TxnRefNo']
                ];
            } else {
                throw new Exception($result['pp_ResponseMessage'] ?? 'Payout failed');
            }
            
        } catch (\Exception $e) {
            $this->logger->error("JazzCash payout failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}