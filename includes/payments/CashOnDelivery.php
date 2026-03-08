<?php
namespace Ecommerce\Payments;

class CashOnDelivery extends PaymentGateway implements PaymentGatewayInterface {
    
    protected $gatewayCode = 'cod';
    protected $gatewayName = 'Cash on Delivery';
    
    public function initialize($config) {
        $this->config = array_merge($this->config ?? [], $config);
        return $this;
    }
    
    public function processCustomerPayment($order, $paymentData) {
        try {
            // Validate order data
            if (!isset($order['id']) || !isset($order['user_id']) || !isset($order['total_amount'])) {
                throw new \Exception('Invalid order data');
            }
            
            $transactionId = $this->logTransaction(
                'customer_payment',
                $order['id'],
                $order['user_id'],
                $order['total_amount'],
                'pending',
                ['method' => 'cod', 'notes' => $paymentData['alt_phone'] ?? '']
            );
            
            // Update order payment status
            $stmt = $this->db->prepare("
                UPDATE orders SET 
                payment_status = 'pending', 
                transaction_id = ?,
                updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transactionId, $order['id']]);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Order placed successfully. Pay on delivery.',
                'redirect' => SITE_URL . 'user/orders/order-confirmation.php?id=' . $order['id']
            ];
            
        } catch (\Exception $e) {
            error_log("COD Payment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to process COD order: ' . $e->getMessage()
            ];
        }
    }
    
    public function processVendorPayout($vendorId, $amount, $payoutData) {
        // COD doesn't support vendor payouts directly
        return [
            'success' => false, 
            'message' => 'Vendor payouts not available for COD'
        ];
    }
    
    public function verifyPayment($transactionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM payment_transactions 
                WHERE transaction_id = ? OR id = ?
            ");
            $stmt->execute([$transactionId, $transactionId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'success' => true,
                    'status' => $result['status'],
                    'data' => $result
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Transaction not found'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function refundPayment($transactionId, $amount = null) {
        return [
            'success' => false, 
            'message' => 'Refund not available for COD'
        ];
    }
    
    public function getPaymentMethods() {
        return [
            [
                'id' => 'cod', 
                'name' => 'Cash on Delivery', 
                'icon' => 'fas fa-money-bill-wave',
                'description' => 'Pay when you receive your order'
            ]
        ];
    }
    
    public function validateAccount($accountDetails) {
        return true; // No validation needed for COD
    }
    
    public function getPaymentForm($order, $userAccounts = []) {
        if (!isset($order['total_amount'])) {
            $order['total_amount'] = 0;
        }
        
        $html = '<div class="cod-form">';
        $html .= '<div class="alert alert-success">';
        $html .= '<i class="fas fa-check-circle me-2"></i>';
        $html .= '<strong>Cash on Delivery Selected</strong>';
        $html .= '<p class="mb-0 mt-2">You will pay when you receive your order. Please have the exact amount ready.</p>';
        $html .= '</div>';
        
        $html .= '<div class="mb-3">';
        $html .= '<label class="form-label">Alternative Phone Number (Optional)</label>';
        $html .= '<input type="tel" name="alt_phone" class="form-control" placeholder="03XXXXXXXXX">';
        $html .= '</div>';
        
        $html .= '<div class="alert alert-warning small">';
        $html .= '<i class="fas fa-info-circle me-2"></i>';
        $html .= 'A delivery person will contact you before delivery.';
        $html .= '</div>';
        
        $html .= '<input type="hidden" name="payment_method" value="cod">';
        $html .= '</div>';
        
        return $html;
    }
}