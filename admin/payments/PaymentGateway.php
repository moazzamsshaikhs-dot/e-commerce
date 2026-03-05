<?php
// includes/payments/PaymentGateway.php

abstract class PaymentGateway {
    protected $db;
    protected $config;
    protected $sandbox_mode;
    protected $gateway;
    
    public function __construct() {
        $this->db = getDB();
        $this->sandbox_mode = true; // Default sandbox
        $this->loadConfig();
    }
    
    /**
     * Set test mode (sandbox) on or off
     * @param bool $mode true for sandbox, false for live
     * @return void
     */
    public function setTestMode($mode) {
        $this->sandbox_mode = (bool)$mode;
        
        // Agar gateway object exist karta hai to usme bhi set karo
        if (isset($this->gateway) && method_exists($this->gateway, 'setTestMode')) {
            $this->gateway->setTestMode($this->sandbox_mode);
        }
        
        // Config reload karo agar zaroorat ho
        $this->loadConfig();
    }
    
    /**
     * Get current test mode status
     * @return bool
     */
    public function getTestMode() {
        return $this->sandbox_mode;
    }
    
    abstract protected function loadConfig();
    abstract public function processPayment($amount, $currency, $paymentData);
    abstract public function verifyPayment($transactionId);
    abstract public function refundPayment($transactionId, $amount);
    abstract public function getBalance();
    
    protected function logTransaction($userId, $type, $amount, $status, $response) {
        $stmt = $this->db->prepare("
            INSERT INTO payment_transactions 
            (user_id, transaction_type, gateway, amount, status, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $type, $this->getGatewayName(), $amount, $status, json_encode($response)]);
        return $this->db->lastInsertId();
    }
    
    abstract protected function getGatewayName();
}