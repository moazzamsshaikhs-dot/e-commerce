<?php
// admin/includes/payment-admin.php

require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/payment-gateways/StripeGateway.php';
require_once '../../includes/payment-gateways/PayPalGateway.php';
require_once '../../includes/payment-gateways/EasypaisaGateway.php';
require_once '../../includes/payment-gateways/JazzCashGateway.php';

class PaymentAdmin {
    private $db;
    private $stripe;
    private $paypal;
    private $easypaisa;
    private $jazzcash;
    
    public function __construct($db) {
        $this->db = $db;
        $this->stripe = new StripeGateway($db);
        $this->paypal = new PayPalGateway($db);
        $this->easypaisa = new EasypaisaGateway($db);
        $this->jazzcash = new JazzCashGateway($db);
    }
    
    /**
     * Process vendor withdrawal
     */
    public function processWithdrawal($withdrawal_id) {
        try {
            // Get withdrawal details
            $stmt = $this->db->prepare("
                SELECT w.*, u.email, u.full_name,
                       ba.account_holder_name, ba.bank_name, ba.account_number, ba.ifsc_code,
                       ma.mobile_number, ma.account_type as mobile_type,
                       cd.card_last_four, cd.expiry_month, cd.expiry_year
                FROM vendor_withdrawals w
                JOIN users u ON w.vendor_id = u.id
                LEFT JOIN vendor_bank_accounts ba ON w.vendor_id = ba.vendor_id AND ba.is_default = 1
                LEFT JOIN vendor_mobile_accounts ma ON w.vendor_id = ma.vendor_id AND ma.account_type = w.withdrawal_method
                LEFT JOIN vendor_cards cd ON w.vendor_id = cd.vendor_id AND cd.is_default = 1
                WHERE w.id = ? AND w.status = 'pending'
            ");
            $stmt->execute([$withdrawal_id]);
            $withdrawal = $stmt->fetch();
            
            if (!$withdrawal) {
                throw new Exception("Withdrawal not found or already processed");
            }
            
            // Process based on method
            $result = false;
            switch($withdrawal['withdrawal_method']) {
                case 'bank':
                case 'stripe':
                    $result = $this->stripe->processPayout(
                        $withdrawal['vendor_id'],
                        $withdrawal['withdrawal_amount'],
                        $withdrawal['account_number']
                    );
                    break;
                    
                case 'paypal':
                    $result = $this->paypal->processPayout(
                        $withdrawal['email'],
                        $withdrawal['withdrawal_amount'],
                        "Withdrawal for {$withdrawal['full_name']}"
                    );
                    break;
                    
                case 'easypaisa':
                    $result = $this->easypaisa->sendMoney(
                        $withdrawal['mobile_number'],
                        $withdrawal['withdrawal_amount'],
                        $withdrawal['full_name']
                    );
                    break;
                    
                case 'jazzcash':
                    $result = $this->jazzcash->sendMoney(
                        $withdrawal['mobile_number'],
                        $withdrawal['withdrawal_amount'],
                        $withdrawal['full_name']
                    );
                    break;
            }
            
            if ($result && $result['success']) {
                // Update withdrawal status
                $stmt = $this->db->prepare("
                    UPDATE vendor_withdrawals 
                    SET status = 'completed', 
                        transaction_id = ?,
                        processed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$result['transaction_id'] ?? $result['payout_id'] ?? $result['batch_id'] ?? null, $withdrawal_id]);
                
                return ['success' => true, 'message' => 'Withdrawal processed successfully'];
            } else {
                throw new Exception($result['message'] ?? 'Payment processing failed');
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}