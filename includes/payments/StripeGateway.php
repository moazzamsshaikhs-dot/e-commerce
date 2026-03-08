<?php
namespace Ecommerce\Payments;

class StripeGateway extends PaymentGateway implements PaymentGatewayInterface {
    
    protected $gatewayCode = 'stripe';
    protected $gatewayName = 'Stripe';
    private $stripeInitialized = false;
    
    public function initialize($config) {
        $this->config = array_merge($this->config ?? [], $config);
        
        // Initialize Stripe if class exists
        if (class_exists('\Stripe\Stripe')) {
            $secretKey = $config['api_secret'] ?? $this->config['api_secret'] ?? '';
            if (!empty($secretKey)) {
                \Stripe\Stripe::setApiKey($secretKey);
                \Stripe\Stripe::setApiVersion('2023-10-16');
                $this->stripeInitialized = true;
            }
        }
        
        return $this;
    }
    
    public function processCustomerPayment($order, $paymentData) {
        try {
            if (!isset($order['id']) || !isset($order['user_id']) || !isset($order['total_amount'])) {
                throw new \Exception('Invalid order data');
            }
            
            if (!$this->stripeInitialized) {
                // Fallback to manual card processing
                return $this->processManualCardPayment($order, $paymentData);
            }
            
            // Get payment method ID from form
            $paymentMethodId = $paymentData['payment_method_id'] ?? 
                              ($paymentData['saved_card'] ?? null);
            
            $intentParams = [
                'amount' => round($order['total_amount'] * 100), // Convert to cents
                'currency' => 'usd',
                'description' => 'Order #' . ($order['order_number'] ?? $order['id']),
                'metadata' => [
                    'order_id' => $order['id'],
                    'order_number' => $order['order_number'] ?? '',
                    'user_id' => $order['user_id']
                ]
            ];
            
            if ($paymentMethodId) {
                $intentParams['payment_method'] = $paymentMethodId;
                $intentParams['confirm'] = true;
                $intentParams['return_url'] = SITE_URL . 'user/orders/payment-success.php?order_id=' . $order['id'];
            }
            
            // Create payment intent
            $intent = \Stripe\PaymentIntent::create($intentParams);
            
            $status = ($intent->status == 'succeeded' || $intent->status == 'requires_capture') ? 'completed' : 'pending';
            
            $transactionId = $this->logTransaction(
                'customer_payment',
                $order['id'],
                $order['user_id'],
                $order['total_amount'],
                $status,
                ['payment_intent' => $intent->id, 'status' => $intent->status]
            );
            
            // Update order payment status
            $stmt = $this->db->prepare("
                UPDATE orders SET 
                payment_status = ?, 
                transaction_id = ?,
                updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $transactionId, $order['id']]);
            
            if ($intent->status == 'requires_action' || $intent->status == 'requires_confirmation') {
                return [
                    'success' => true,
                    'requires_action' => true,
                    'payment_intent_client_secret' => $intent->client_secret,
                    'transaction_id' => $transactionId,
                    'redirect' => SITE_URL . 'user/orders/payment-processing.php?intent=' . $intent->id
                ];
            } else {
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'redirect' => SITE_URL . 'user/orders/order-confirmation.php?id=' . $order['id']
                ];
            }
            
        } catch (\Exception $e) {
            error_log("Stripe Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Stripe payment failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Fallback method for manual card processing when Stripe PHP library is not available
     */
    private function processManualCardPayment($order, $paymentData) {
        try {
            // Validate card details
            $cardNumber = $paymentData['card_number'] ?? '';
            $cardExpiry = $paymentData['card_expiry'] ?? '';
            $cardCvc = $paymentData['card_cvc'] ?? '';
            
            if (empty($cardNumber) || empty($cardExpiry) || empty($cardCvc)) {
                return [
                    'success' => false,
                    'message' => 'Card details are required'
                ];
            }
            
            // For demo purposes, we'll just record the transaction
            // In production, you would integrate with a payment processor
            
            $transactionId = $this->logTransaction(
                'customer_payment',
                $order['id'],
                $order['user_id'],
                $order['total_amount'],
                'processing',
                [
                    'method' => 'manual_card',
                    'card_last4' => substr($cardNumber, -4)
                ]
            );
            
            // Update order payment status
            $stmt = $this->db->prepare("
                UPDATE orders SET 
                payment_status = 'processing', 
                transaction_id = ?,
                updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transactionId, $order['id']]);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Card payment is being processed.',
                'redirect' => SITE_URL . 'user/orders/order-confirmation.php?id=' . $order['id']
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Card payment failed: ' . $e->getMessage()
            ];
        }
    }
    
    public function processVendorPayout($vendorId, $amount, $payoutData) {
        try {
            // Get vendor's Stripe account
            $stmt = $this->db->prepare("
                SELECT vsa.*, vpm.id as method_id
                FROM vendor_stripe_accounts vsa
                JOIN vendors_payment_methods vpm ON vsa.payment_method_id = vpm.id
                WHERE vsa.vendor_id = ? AND vpm.method_type = 'stripe' AND vsa.is_default = 1
                LIMIT 1
            ");
            $stmt->execute([$vendorId]);
            $stripeAccount = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$stripeAccount) {
                return [
                    'success' => false,
                    'message' => 'Vendor has no Stripe account configured'
                ];
            }
            
            if ($this->stripeInitialized) {
                // Create a transfer to vendor's Stripe account
                $transfer = \Stripe\Transfer::create([
                    'amount' => round($amount * 100),
                    'currency' => 'usd',
                    'destination' => $stripeAccount['stripe_account_id'],
                    'transfer_group' => 'order_' . ($payoutData['order_id'] ?? '')
                ]);
                
                $transferId = $transfer->id;
            } else {
                $transferId = 'STRIPE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
            }
            
            $transactionId = $this->logTransaction(
                'vendor_payout',
                $payoutData['order_id'] ?? 0,
                $vendorId,
                $amount,
                'processing',
                [
                    'stripe_account' => $stripeAccount['stripe_account_id'],
                    'transfer_id' => $transferId ?? null
                ]
            );
            
            // Update admin account balance
            $adminAccount = $this->getDefaultAdminAccount();
            if ($adminAccount) {
                $stmt = $this->db->prepare("
                    UPDATE admin_accounts 
                    SET current_balance = current_balance - ?,
                        total_debited = total_debited + ?,
                        last_transaction_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$amount, $amount, $adminAccount['id']]);
            }
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Stripe payout initiated'
            ];
            
        } catch (\Exception $e) {
            error_log("Stripe Payout Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function verifyPayment($transactionId) {
        try {
            // Check if it's a Stripe Payment Intent ID
            if ($this->stripeInitialized && strpos($transactionId, 'pi_') === 0) {
                $intent = \Stripe\PaymentIntent::retrieve($transactionId);
                return [
                    'success' => true,
                    'status' => $intent->status,
                    'data' => $intent->toArray()
                ];
            }
            
            // Check local database
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
        try {
            if (!$this->stripeInitialized) {
                return [
                    'success' => false,
                    'message' => 'Stripe library not available for refund'
                ];
            }
            
            $refundParams = ['payment_intent' => $transactionId];
            if ($amount) {
                $refundParams['amount'] = round($amount * 100);
            }
            
            $refund = \Stripe\Refund::create($refundParams);
            
            // Log refund
            $this->logTransaction(
                'refund',
                0,
                0,
                $amount ?? 0,
                'completed',
                ['refund_id' => $refund->id, 'payment_intent' => $transactionId]
            );
            
            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100
            ];
            
        } catch (\Exception $e) {
            error_log("Stripe Refund Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getPaymentMethods() {
        return [
            [
                'id' => 'card', 
                'name' => 'Credit/Debit Card', 
                'icon' => 'fas fa-credit-card',
                'description' => 'Pay using Visa, Mastercard, or American Express'
            ]
        ];
    }
    
    public function validateAccount($accountDetails) {
        return !empty($accountDetails['stripe_token']) || 
               (!empty($accountDetails['card_number']) && !empty($accountDetails['card_cvc']));
    }
    
    public function getPaymentForm($order, $userAccounts = []) {
        if (!isset($order['total_amount'])) {
            $order['total_amount'] = 0;
        }
        
        $html = '<div class="stripe-form">';
        
        if ($this->stripeInitialized) {
            // Stripe Elements form
            $html .= '<div class="mb-3">';
            $html .= '<label class="form-label">Card Number</label>';
            $html .= '<div id="card-number-element" class="form-control" style="height:45px; padding:12px;"></div>';
            $html .= '</div>';
            
            $html .= '<div class="row mb-3">';
            $html .= '<div class="col-md-6">';
            $html .= '<label class="form-label">Expiry Date</label>';
            $html .= '<div id="card-expiry-element" class="form-control" style="height:45px; padding:12px;"></div>';
            $html .= '</div>';
            $html .= '<div class="col-md-6">';
            $html .= '<label class="form-label">CVC</label>';
            $html .= '<div id="card-cvc-element" class="form-control" style="height:45px; padding:12px;"></div>';
            $html .= '</div>';
            $html .= '</div>';
            
            $html .= '<div id="card-errors" class="text-danger small mb-3" role="alert"></div>';
        } else {
            // Fallback manual card form
            $html .= '<div class="mb-3">';
            $html .= '<label class="form-label">Card Number</label>';
            $html .= '<input type="text" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" required>';
            $html .= '</div>';
            
            $html .= '<div class="row mb-3">';
            $html .= '<div class="col-md-6">';
            $html .= '<label class="form-label">Expiry Date (MM/YY)</label>';
            $html .= '<input type="text" name="card_expiry" class="form-control" placeholder="MM/YY" required>';
            $html .= '</div>';
            $html .= '<div class="col-md-6">';
            $html .= '<label class="form-label">CVC</label>';
            $html .= '<input type="text" name="card_cvc" class="form-control" placeholder="123" required>';
            $html .= '</div>';
            $html .= '</div>';
            
            $html .= '<div class="alert alert-warning small">';
            $html .= '<i class="fas fa-info-circle me-2"></i>';
            $html .= 'Your card information is encrypted and secure.';
            $html .= '</div>';
        }
        
        if (!empty($userAccounts)) {
            $html .= '<hr>';
            $html .= '<h6 class="mb-3">Your Saved Cards</h6>';
            $html .= '<div class="list-group mb-3">';
            foreach ($userAccounts as $account) {
                $details = json_decode($account['account_details'] ?? '{}', true);
                $html .= '<label class="list-group-item list-group-item-action">';
                $html .= '<input type="radio" name="saved_card" value="' . $account['stripe_payment_method_id'] . '" class="form-check-input me-2">';
                $html .= '<i class="fas fa-credit-card me-2 text-primary"></i>';
                $html .= '**** **** **** ' . ($details['last4'] ?? '') . ' ';
                $html .= '<small class="text-muted">' . ($details['brand'] ?? 'Card') . '</small>';
                $html .= '</label>';
            }
            $html .= '</div>';
        }
        
        // Add Stripe JS if initialized
        if ($this->stripeInitialized) {
            $html .= '<script src="https://js.stripe.com/v3/"></script>';
            $html .= '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    try {
                        const stripe = Stripe("' . ($this->config['api_key'] ?? '') . '");
                        const elements = stripe.elements();
                        
                        const cardNumber = elements.create("cardNumber", {
                            style: {
                                base: {
                                    fontSize: "16px",
                                    color: "#32325d",
                                    "::placeholder": { color: "#aab7c4" }
                                }
                            }
                        });
                        cardNumber.mount("#card-number-element");
                        
                        const cardExpiry = elements.create("cardExpiry", {
                            style: {
                                base: {
                                    fontSize: "16px",
                                    color: "#32325d",
                                    "::placeholder": { color: "#aab7c4" }
                                }
                            }
                        });
                        cardExpiry.mount("#card-expiry-element");
                        
                        const cardCvc = elements.create("cardCvc", {
                            style: {
                                base: {
                                    fontSize: "16px",
                                    color: "#32325d",
                                    "::placeholder": { color: "#aab7c4" }
                                }
                            }
                        });
                        cardCvc.mount("#card-cvc-element");
                        
                        cardNumber.on("change", function(event) {
                            const displayError = document.getElementById("card-errors");
                            if (event.error) {
                                displayError.textContent = event.error.message;
                            } else {
                                displayError.textContent = "";
                            }
                        });
                        
                        const form = document.getElementById("checkoutForm");
                        if (form) {
                            form.addEventListener("submit", async function(event) {
                                const selectedGateway = document.querySelector("input[name=\'gateway_code\']:checked");
                                if (!selectedGateway || selectedGateway.value !== "stripe") {
                                    return;
                                }
                                
                                const savedCard = document.querySelector("input[name=\'saved_card\']:checked");
                                if (savedCard) {
                                    return;
                                }
                                
                                event.preventDefault();
                                
                                const {paymentMethod, error} = await stripe.createPaymentMethod({
                                    type: "card",
                                    card: cardNumber,
                                });
                                
                                if (error) {
                                    document.getElementById("card-errors").textContent = error.message;
                                } else {
                                    const hiddenInput = document.createElement("input");
                                    hiddenInput.setAttribute("type", "hidden");
                                    hiddenInput.setAttribute("name", "payment_method_id");
                                    hiddenInput.setAttribute("value", paymentMethod.id);
                                    form.appendChild(hiddenInput);
                                    form.submit();
                                }
                            });
                        }
                    } catch (e) {
                        console.error("Stripe initialization error:", e);
                    }
                });
            </script>';
        }
        
        $html .= '<input type="hidden" name="payment_method" value="stripe">';
        $html .= '</div>';
        
        return $html;
    }
}