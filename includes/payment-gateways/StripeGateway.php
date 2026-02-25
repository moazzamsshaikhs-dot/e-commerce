<?php
// includes/payment-gateways/StripeGateway.php

require_once dirname(__DIR__) . '/config/payment_config.php';
require_once dirname(__DIR__) . '/../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Transfer;
use Stripe\Payout;
use Stripe\Account;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class StripeGateway {
    private $api_key;
    private $api_secret;
    private $environment;
    private $logger;
    private $db;
    
    public function __construct($db = null) {
        $this->environment = STRIPE_ENVIRONMENT;
        $this->api_key = ($this->environment === 'production') ? STRIPE_LIVE_KEY : STRIPE_TEST_KEY;
        $this->api_secret = ($this->environment === 'production') ? STRIPE_LIVE_SECRET : STRIPE_TEST_SECRET;
        $this->db = $db;
        
        // Initialize Stripe
        Stripe::setApiKey($this->api_secret);
        
        // Setup logging
        $this->logger = new Logger('stripe');
        $this->logger->pushHandler(new StreamHandler(PAYMENT_LOG_FILE, Logger::INFO));
    }
    
    /**
     * Create payment intent for customer payment
     */
    public function createPaymentIntent($amount, $currency = 'usd', $metadata = []) {
        try {
            $this->logger->info("Creating payment intent", [
                'amount' => $amount,
                'currency' => $currency
            ]);
            
            $paymentIntent = PaymentIntent::create([
                'amount' => $this->convertToCents($amount),
                'currency' => $currency,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
            
            $this->logger->info("Payment intent created", [
                'id' => $paymentIntent->id
            ]);
            
            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'intent_id' => $paymentIntent->id
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Payment intent creation failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process payout to vendor
     */
    public function processPayout($vendor_id, $amount, $destination) {
        try {
            $this->logger->info("Processing payout", [
                'vendor_id' => $vendor_id,
                'amount' => $amount,
                'destination' => $destination
            ]);
            
            // Create a payout
            $payout = Payout::create([
                'amount' => $this->convertToCents($amount),
                'currency' => 'usd',
                'destination' => $destination,
                'method' => 'standard',
                'metadata' => [
                    'vendor_id' => $vendor_id
                ]
            ]);
            
            $this->logger->info("Payout processed", [
                'payout_id' => $payout->id
            ]);
            
            return [
                'success' => true,
                'payout_id' => $payout->id,
                'status' => $payout->status
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Payout failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create connected account for vendor
     */
    public function createConnectedAccount($vendor_id, $email) {
        try {
            $account = Account::create([
                'type' => 'express',
                'country' => 'US',
                'email' => $email,
                'capabilities' => [
                    'transfers' => ['requested' => true],
                ],
                'metadata' => [
                    'vendor_id' => $vendor_id
                ]
            ]);
            
            // Generate account link for onboarding
            $accountLink = \Stripe\AccountLink::create([
                'account' => $account->id,
                'refresh_url' => SITE_URL . "vendor/stripe/refresh.php",
                'return_url' => SITE_URL . "vendor/stripe/complete.php",
                'type' => 'account_onboarding',
            ]);
            
            return [
                'success' => true,
                'account_id' => $account->id,
                'onboarding_url' => $accountLink->url
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Connected account creation failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhook($payload, $sig_header) {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, STRIPE_WEBHOOK_SECRET
            );
            
            $this->logger->info("Webhook verified", [
                'type' => $event->type
            ]);
            
            return [
                'success' => true,
                'event' => $event
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Webhook verification failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Convert dollars to cents
     */
    private function convertToCents($amount) {
        return (int) round($amount * 100);
    }
}