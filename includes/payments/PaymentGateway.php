<?php
namespace Ecommerce\Payments;

use PDO;

abstract class PaymentGateway {
    protected $config;
    protected $db;
    protected $gatewayCode;
    protected $gatewayName;
    protected $testMode = true;
    protected $adminAccounts = [];
    
    public function __construct($db = null) {
        $this->db = $db ?: $this->getDB();
        $this->loadConfig();
        $this->loadAdminAccounts();
    }
    
    protected function getDB() {
        global $db;
        if (isset($db) && $db instanceof PDO) {
            return $db;
        }
        
        if (function_exists('getDB')) {
            return getDB();
        }
        
        throw new \Exception("Database connection not available");
    }
    
    protected function loadConfig() {
        $stmt = $this->db->prepare("
            SELECT * FROM payment_gateways 
            WHERE gateway_code = ? AND is_active = 1
        ");
        $stmt->execute([$this->gatewayCode]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            $this->config = $config;
            $this->testMode = $config['test_mode'] == 1;
            
            if (!empty($config['additional_settings'])) {
                $additional = json_decode($config['additional_settings'], true);
                if (is_array($additional)) {
                    foreach ($additional as $key => $value) {
                        $this->config[$key] = $value;
                    }
                }
            }
        } else {
            // Fallback to admin_accounts
            $this->loadFromAdminAccounts();
        }
    }
    
    protected function loadFromAdminAccounts() {
        $stmt = $this->db->prepare("
            SELECT * FROM admin_accounts 
            WHERE account_type = ? AND is_active = 1 
            ORDER BY is_default DESC LIMIT 1
        ");
        $stmt->execute([$this->gatewayCode]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            $this->config = [
                'gateway_code' => $this->gatewayCode,
                'gateway_name' => $this->gatewayName,
                'is_active' => $account['is_active'],
                'test_mode' => 1,
                'api_key' => $account['account_email'] ?? '',
                'api_secret' => $account['account_number'] ?? '',
                'merchant_id' => $account['account_holder'] ?? '',
                'account_email' => $account['account_email'] ?? ''
            ];
        }
    }
    
    protected function loadAdminAccounts() {
        $stmt = $this->db->prepare("
            SELECT * FROM admin_accounts 
            WHERE account_type = ? AND is_active = 1 
            ORDER BY is_default DESC
        ");
        $stmt->execute([$this->gatewayCode]);
        $this->adminAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process payment from customer to admin
     */
    abstract public function processCustomerPayment($order, $paymentData);
    
    /**
     * Process payout from admin to vendor
     */
    abstract public function processVendorPayout($vendorId, $amount, $payoutData);
    
    /**
     * Get vendor's payment methods
     */
    public function getVendorPaymentMethods($vendorId) {
        $methods = [];
        
        // Get from vendors_payment_methods
        $stmt = $this->db->prepare("
            SELECT * FROM vendors_payment_methods 
            WHERE vendor_id = ? AND method_type = ?
            ORDER BY is_default DESC
        ");
        $stmt->execute([$vendorId, $this->gatewayCode]);
        $vendorMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($vendorMethods as $method) {
            $details = $this->getVendorMethodDetails($method);
            if ($details) {
                $methods[] = array_merge($method, $details);
            }
        }
        
        return $methods;
    }
    
    /**
     * Get vendor method details from specific tables
     */
    protected function getVendorMethodDetails($method) {
        switch ($this->gatewayCode) {
            case 'bank':
                $stmt = $this->db->prepare("
                    SELECT * FROM vendor_bank_accounts 
                    WHERE payment_method_id = ? OR vendor_id = ?
                    ORDER BY is_default DESC LIMIT 1
                ");
                $stmt->execute([$method['id'], $method['vendor_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
                
            case 'paypal':
                $stmt = $this->db->prepare("
                    SELECT * FROM vendor_paypal_accounts 
                    WHERE payment_method_id = ? OR vendor_id = ?
                    ORDER BY is_default DESC LIMIT 1
                ");
                $stmt->execute([$method['id'], $method['vendor_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
                
            case 'stripe':
                $stmt = $this->db->prepare("
                    SELECT * FROM vendor_stripe_accounts 
                    WHERE payment_method_id = ? OR vendor_id = ?
                    ORDER BY is_default DESC LIMIT 1
                ");
                $stmt->execute([$method['id'], $method['vendor_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
                
            case 'easypaisa':
            case 'jazzcash':
                $stmt = $this->db->prepare("
                    SELECT * FROM vendor_mobile_accounts 
                    WHERE payment_method_id = ? OR vendor_id = ?
                    ORDER BY is_default DESC LIMIT 1
                ");
                $stmt->execute([$method['id'], $method['vendor_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    /**
     * Get admin accounts for this gateway
     */
    public function getAdminAccounts() {
        return $this->adminAccounts;
    }
    
    /**
     * Get default admin account
     */
    public function getDefaultAdminAccount() {
        foreach ($this->adminAccounts as $account) {
            if ($account['is_default']) {
                return $account;
            }
        }
        return $this->adminAccounts[0] ?? null;
    }
    
    /**
     * Log transaction
     */
    protected function logTransaction($type, $referenceId, $userId, $amount, $status, $details = null) {
        $transactionId = $this->generateTransactionId();
        
        if ($type == 'customer_payment') {
            $stmt = $this->db->prepare("
                INSERT INTO payment_transactions 
                (transaction_id, order_id, user_id, gateway, amount, status, gateway_response, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $transactionId,
                $referenceId,
                $userId,
                $this->gatewayCode,
                $amount,
                $status,
                $details ? json_encode($details) : null
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO withdrawal_transactions 
                (transaction_id, vendor_id, amount, status, transaction_data, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $transactionId,
                $userId,
                $amount,
                $status,
                $details ? json_encode($details) : null
            ]);
        }
        
        return $transactionId;
    }
    
    protected function generateTransactionId() {
        return strtoupper($this->gatewayCode) . '-' . date('Ymd') . '-' . 
               strtoupper(substr(uniqid(), -8)) . '-' . rand(1000, 9999);
    }
    
    public function isActive() {
        return $this->config && ($this->config['is_active'] ?? 0) == 1;
    }
    
    public function getGatewayCode() {
        return $this->gatewayCode;
    }
    
    public function getGatewayName() {
        return $this->gatewayName;
    }
    
    abstract public function getPaymentForm($order, $userAccounts = []);
    abstract public function validateAccount($accountDetails);
}