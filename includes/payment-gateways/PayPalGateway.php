<?php
// includes/payment-gateways/PayPalGateway.php

require_once dirname(__DIR__) . '/config/payment_config.php';
require_once dirname(__DIR__) . '/../vendor/autoload.php';

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Payout;
use PayPal\Api\PayoutSenderBatchHeader;
use PayPal\Api\PayoutItem;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class PayPalGateway {
    private $api_context;
    private $environment;
    private $logger;
    private $db;
    
    public function __construct($db = null) {
        $this->environment = PAYPAL_ENVIRONMENT;
        $this->db = $db;
        
        // Setup API context
        $this->api_context = new ApiContext(
            new OAuthTokenCredential(
                PAYPAL_CLIENT_ID,
                PAYPAL_CLIENT_SECRET
            )
        );
        
        $this->api_context->setConfig([
            'mode' => $this->environment === 'production' ? 'live' : 'sandbox',
            'http.ConnectionTimeOut' => 30,
            'log.LogEnabled' => true,
            'log.FileName' => dirname(__DIR__) . '/logs/paypal.log',
            'log.LogLevel' => 'FINE',
            'validation.level' => 'log'
        ]);
        
        // Setup logging
        $this->logger = new Logger('paypal');
        $this->logger->pushHandler(new StreamHandler(PAYMENT_LOG_FILE, Logger::INFO));
    }
    
    /**
     * Create PayPal payment
     */
    public function createPayment($amount, $currency = 'USD', $return_url, $cancel_url, $description = '') {
        try {
            $payer = new Payer();
            $payer->setPaymentMethod('paypal');
            
            $amount_obj = new Amount();
            $amount_obj->setCurrency($currency)
                       ->setTotal(number_format($amount, 2, '.', ''));
            
            $transaction = new Transaction();
            $transaction->setAmount($amount_obj)
                       ->setDescription($description);
            
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl($return_url)
                        ->setCancelUrl($cancel_url);
            
            $payment = new Payment();
            $payment->setIntent('sale')
                   ->setPayer($payer)
                   ->setTransactions([$transaction])
                   ->setRedirectUrls($redirectUrls);
            
            $payment->create($this->api_context);
            
            $this->logger->info("Payment created", [
                'payment_id' => $payment->getId(),
                'amount' => $amount
            ]);
            
            return [
                'success' => true,
                'payment_id' => $payment->getId(),
                'approval_url' => $payment->getApprovalLink()
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Payment creation failed", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute PayPal payment
     */
    public function executePayment($payment_id, $payer_id) {
        try {
            $payment = Payment::get($payment_id, $this->api_context);
            
            $execution = new PaymentExecution();
            $execution->setPayerId($payer_id);
            
            $result = $payment->execute($execution, $this->api_context);
            
            $this->logger->info("Payment executed", [
                'payment_id' => $payment_id,
                'state' => $result->getState()
            ]);
            
            return [
                'success' => true,
                'payment' => $result
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Payment execution failed", [
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
    public function processPayout($vendor_email, $amount, $note = '') {
        try {
            $payouts = new Payout();
            
            $senderBatchHeader = new PayoutSenderBatchHeader();
            $senderBatchHeader->setSenderBatchId(uniqid())
                             ->setEmailSubject("You have a payout from " . SITE_NAME);
            
            $payoutItem = new PayoutItem();
            $payoutItem->setRecipientType('Email')
                      ->setReceiver($vendor_email)
                      ->setAmount(new \PayPal\Api\Currency([
                          'value' => number_format($amount, 2, '.', ''),
                          'currency' => 'USD'
                      ]))
                      ->setNote($note)
                      ->setSenderItemId("payout_" . uniqid());
            
            $payouts->setSenderBatchHeader($senderBatchHeader)
                   ->addItem($payoutItem);
            
            $output = $payouts->create(null, $this->api_context);
            
            $this->logger->info("Payout processed", [
                'batch_id' => $output->getBatchHeader()->getPayoutBatchId(),
                'amount' => $amount
            ]);
            
            return [
                'success' => true,
                'batch_id' => $output->getBatchHeader()->getPayoutBatchId()
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
}