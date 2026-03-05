<?php
// includes/payments/StripeGateway.php

require_once 'PaymentGateway.php';

class StripeGateway extends PaymentGateway {
    private $apiKey;
    private $publishableKey;
    private $webhookSecret;
    
    protected function loadConfig() {
        $stmt = $this->db->prepare("
            SELECT * FROM admin_accounts 
            WHERE account_type = 'stripe' AND is_active = 1 
            ORDER BY is_default DESC LIMIT 1
        ");
        $stmt->execute();
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            // Stripe mein sandbox mode ke liye alag keys hoti hain
            if ($this->sandbox_mode) {
                // Test keys
                $this->apiKey = $account['account_number'] ?? ''; // Test secret key
                $this->publishableKey = $account['account_email'] ?? ''; // Test publishable key
            } else {
                // Live keys - agar alag se store hain to use karo
                $this->apiKey = $account['swift_code'] ?? $account['account_number'] ?? ''; 
                $this->publishableKey = $account['iban'] ?? $account['account_email'] ?? '';
            }
        }
        
        // Load Stripe PHP library from vendor
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        \Stripe\Stripe::setApiKey($this->apiKey);
    }
    
    protected function getGatewayName() {
        return 'stripe';
    }
    
    public function setTestMode($mode) {
        parent::setTestMode($mode);
        $this->loadConfig(); // Reload config with new mode
    }
    
    public function processPayment($amount, $currency, $paymentData) {
        try {
            // Create payment intent
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => strtolower($currency),
                'payment_method' => $paymentData['payment_method_id'] ?? null,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => SITE_URL . 'payment-success.php',
                'metadata' => [
                    'user_id' => $paymentData['user_id'] ?? 0,
                    'order_id' => $paymentData['order_id'] ?? 0,
                    'mode' => $this->sandbox_mode ? 'test' : 'live'
                ]
            ]);
            
            // Log transaction
            $this->logTransaction(
                $paymentData['user_id'] ?? 0,
                'payment',
                $amount,
                $intent->status,
                $intent->toArray()
            );
            
            if ($intent->status == 'requires_action' || $intent->status == 'requires_source_action') {
                return [
                    'success' => true,
                    'requires_action' => true,
                    'payment_intent_client_secret' => $intent->client_secret
                ];
            } elseif ($intent->status == 'succeeded') {
                return [
                    'success' => true,
                    'transaction_id' => $intent->id
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Payment failed'
                ];
            }
            
        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function verifyPayment($transactionId) {
        try {
            $intent = \Stripe\PaymentIntent::retrieve($transactionId);
            
            return [
                'success' => $intent->status == 'succeeded',
                'data' => $intent->toArray()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function refundPayment($transactionId, $amount) {
        try {
            $refund = \Stripe\Refund::create([
                'payment_intent' => $transactionId,
                'amount' => $amount * 100
            ]);
            
            return [
                'success' => true,
                'refund_id' => $refund->id,
                'data' => $refund->toArray()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getBalance() {
        try {
            $balance = \Stripe\Balance::retrieve();
            
            $available = 0;
            foreach ($balance->available as $bal) {
                if ($bal->currency == 'usd') {
                    $available = $bal->amount / 100;
                    break;
                }
            }
            
            return [
                'success' => true,
                'balance' => $available
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function createPaymentMethod($cardData) {
        try {
            $paymentMethod = \Stripe\PaymentMethod::create([
                'type' => 'card',
                'card' => [
                    'number' => $cardData['number'],
                    'exp_month' => $cardData['exp_month'],
                    'exp_year' => $cardData['exp_year'],
                    'cvc' => $cardData['cvc']
                ]
            ]);
            
            return [
                'success' => true,
                'payment_method_id' => $paymentMethod->id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}