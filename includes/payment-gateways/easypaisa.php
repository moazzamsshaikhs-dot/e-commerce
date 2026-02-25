<?php
// includes/payment-gateways/EasypaisaGateway.php

require_once dirname(__DIR__) . '/config/payment_config.php';
require_once dirname(__DIR__) . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class EasypaisaGateway {
    private $merchant_id;
    private $api_key;
    private $api_secret;
    private $store_id;
    private $environment;
    private $client;
    private $logger;
    private $db;
    
    public function __construct($db = null) {
        $this->environment = EASYPAISA_ENVIRONMENT;
        $this->merchant_id = EASYPAISA_MERCHANT_ID;
        $this->api_key = EASYPAISA_API_KEY;
        $this->api_secret = EASYPAISA_API_SECRET;
        $this->store_id = EASYPAISA_STORE_ID;
        $this->db = $db;
        
        // Initialize HTTP client
        $this->client = new Client([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => 30.0,
            'verify' => false // Set to true in production
        ]);
        
        // Setup logging
        $this->logger = new Logger('easypaisa');
        $this->logger->pushHandler(new StreamHandler(PAYMENT_LOG_FILE, Logger::INFO));
    }
    
    /**
     * Get base URL based on environment
     */
    private function getBaseUrl() {
        if ($this->environment === 'production') {
            return 'https://easypaisa.com.pk/api/';
        } else {
            return 'https://sandbox.easypaisa.com.pk/api/';
        }
    }
    
    /**
     * Generate HMAC signature
     */
    private function generateSignature($data) {
        return hash_hmac('sha256', json_encode($data), $this->api_secret);
    }
    
    /**
     * Initiate payment
     */
    public function initiatePayment($order_id, $amount, $mobile_number, $description = '') {
        try {
            $order_ref = 'EP-' . $order_id . '-' . time();
            
            $data = [
                'merchantId' => $this->merchant_id,
                'storeId' => $this->store_id,
                'orderRef' => $order_ref,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'PKR',
                'mobileNumber' => $mobile_number,
                'description' => $description,
                'returnUrl' => SITE_URL . 'payment/easypaisa/response.php',
                'callbackUrl' => SITE_URL . 'payment/easypaisa/callback.php',
                'timestamp' => time()
            ];
            
            $signature = $this->generateSignature($data);
            $data['signature'] = $signature;
            
            $response = $this->client->post('v1/payment/initiate', [
                'json' => $data,
                'headers' => [
                    'API-Key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            $this->logger->info("Easypaisa payment initiated", [
                'order_ref' => $order_ref,
                'response' => $result
            ]);
            
            if ($result['status'] === 'SUCCESS') {
                return [
                    'success' => true,
                    'transaction_id' => $result['transactionId'],
                    'payment_url' => $result['paymentUrl']
                ];
            } else {
                throw new Exception($result['message'] ?? 'Payment initiation failed');
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Easypaisa payment initiation failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify payment status
     */
    public function verifyPayment($transaction_id) {
        try {
            $data = [
                'merchantId' => $this->merchant_id,
                'transactionId' => $transaction_id,
                'timestamp' => time()
            ];
            
            $signature = $this->generateSignature($data);
            
            $response = $this->client->post('v1/payment/status', [
                'json' => $data,
                'headers' => [
                    'API-Key' => $this->api_key,
                    'Signature' => $signature,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            $this->logger->info("Easypaisa payment verified", [
                'transaction_id' => $transaction_id,
                'status' => $result['status']
            ]);
            
            return [
                'success' => true,
                'status' => $result['status'],
                'data' => $result
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Easypaisa verification failed", [
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
            $data = [
                'merchantId' => $this->merchant_id,
                'storeId' => $this->store_id,
                'mobileNumber' => $mobile_number,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'PKR',
                'description' => "Withdrawal for vendor: $vendor_name",
                'reference' => 'WDR-' . time(),
                'timestamp' => time()
            ];
            
            $signature = $this->generateSignature($data);
            
            $response = $this->client->post('v1/payout/send', [
                'json' => $data,
                'headers' => [
                    'API-Key' => $this->api_key,
                    'Signature' => $signature,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            $this->logger->info("Easypaisa payout sent", [
                'reference' => $data['reference'],
                'response' => $result
            ]);
            
            if ($result['status'] === 'SUCCESS') {
                return [
                    'success' => true,
                    'transaction_id' => $result['transactionId']
                ];
            } else {
                throw new Exception($result['message'] ?? 'Payout failed');
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Easypaisa payout failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}