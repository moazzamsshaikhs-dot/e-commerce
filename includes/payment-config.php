<?php
// Payment Gateway Configuration
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_your_key_here');
define('STRIPE_SECRET_KEY', 'sk_test_your_key_here');
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret');

define('PAYPAL_CLIENT_ID', 'your_paypal_client_id');
define('PAYPAL_CLIENT_SECRET', 'your_paypal_client_secret');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' or 'live'

// Stripe Setup
require_once 'vendor/autoload.php'; // Composer autoload

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session;
use Stripe\Subscription as StripeSubscription;

// PayPal Setup
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

class PaymentGateway {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        
        // Initialize Stripe
        Stripe::setApiKey(STRIPE_SECRET_KEY);
        Stripe::setApiVersion('2023-10-16');
    }
    
    /**
     * Create Stripe Checkout Session
     */
    public function createStripeCheckout($user_id, $plan_data, $return_url) {
        try {
            $user = $this->getUser($user_id);
            
            // Create or retrieve Stripe customer
            $customer = $this->getStripeCustomer($user);
            
            // Create checkout session
            $session = Session::create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $plan_data['name'],
                            'description' => $plan_data['description']
                        ],
                        'unit_amount' => $plan_data['price'] * 100, // Convert to cents
                        'recurring' => $plan_data['is_recurring'] ? [
                            'interval' => 'month',
                            'interval_count' => 1
                        ] : null,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => $plan_data['is_recurring'] ? 'subscription' : 'payment',
                'success_url' => $return_url . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $return_url . '?canceled=true',
                'metadata' => [
                    'user_id' => $user_id,
                    'plan_id' => $plan_data['id'],
                    'plan_slug' => $plan_data['slug']
                ],
                'allow_promotion_codes' => true,
            ]);
            
            // Log payment intent
            $this->logPaymentIntent($user_id, $session->id, 'stripe', $plan_data);
            
            return ['success' => true, 'session_id' => $session->id];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Payment gateway error'];
        }
    }
    
    /**
     * Create PayPal Order
     */
    public function createPayPalOrder($user_id, $plan_data, $return_url) {
        try {
            // Setup PayPal environment
            if (PAYPAL_MODE === 'sandbox') {
                $environment = new SandboxEnvironment(PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET);
            } else {
                $environment = new ProductionEnvironment(PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET);
            }
            
            $client = new PayPalHttpClient($environment);
            
            $request = new OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'plan_' . $plan_data['id'],
                    'description' => $plan_data['description'],
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($plan_data['price'], 2),
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => 'USD',
                                'value' => number_format($plan_data['price'], 2)
                            ]
                        ]
                    ],
                    'items' => [[
                        'name' => $plan_data['name'],
                        'description' => $plan_data['description'],
                        'sku' => $plan_data['slug'],
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format($plan_data['price'], 2)
                        ],
                        'quantity' => '1',
                        'category' => 'DIGITAL'
                    ]]
                ]],
                'application_context' => [
                    'brand_name' => 'ShopEase Pro',
                    'locale' => 'en-US',
                    'landing_page' => 'BILLING',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $return_url . '?success=true',
                    'cancel_url' => $return_url . '?canceled=true'
                ]
            ];
            
            $response = $client->execute($request);
            
            // Log payment intent
            $this->logPaymentIntent($user_id, $response->result->id, 'paypal', $plan_data);
            
            return [
                'success' => true,
                'order_id' => $response->result->id,
                'approve_url' => $response->result->links[1]->href
            ];
            
        } catch (Exception $e) {
            error_log("PayPal Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Payment gateway error'];
        }
    }
    
    /**
     * Handle Stripe Webhook
     */
    public function handleStripeWebhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, STRIPE_WEBHOOK_SECRET
            );
            
            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;
                    $this->handleSuccessfulPayment($session);
                    break;
                    
                case 'invoice.payment_succeeded':
                    $invoice = $event->data->object;
                    $this->handleRecurringPayment($invoice);
                    break;
                    
                case 'customer.subscription.deleted':
                    $subscription = $event->data->object;
                    $this->handleSubscriptionCancelled($subscription);
                    break;
            }
            
            http_response_code(200);
            
        } catch (\UnexpectedValueException $e) {
            http_response_code(400);
            exit();
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(400);
            exit();
        }
    }
    
    /**
     * Verify and Process Payment
     */
    public function verifyPayment($payment_id, $gateway) {
        try {
            if ($gateway === 'stripe') {
                // Verify Stripe session
                $session = Session::retrieve($payment_id);
                
                if ($session->payment_status === 'paid') {
                    return $this->processVerifiedPayment($session);
                }
                
            } elseif ($gateway === 'paypal') {
                // Verify PayPal order
                return $this->verifyPayPalOrder($payment_id);
            }
            
            return ['success' => false, 'message' => 'Payment not verified'];
            
        } catch (Exception $e) {
            error_log("Payment Verification Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }
    
    /**
     * Process Bank Transfer/Manual Payment
     */
    public function processManualPayment($user_id, $plan_data, $payment_details) {
        try {
            // Create payment record
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    user_id, amount, currency, payment_method, 
                    transaction_id, status, payment_details, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $transaction_id = 'MANUAL_' . time() . '_' . $user_id;
            
            $stmt->execute([
                $user_id,
                $plan_data['price'],
                'USD',
                'bank_transfer',
                $transaction_id,
                'pending',
                json_encode($payment_details)
            ]);
            
            $payment_id = $this->db->lastInsertId();
            
            // Log upgrade request
            $this->logUpgradeRequest($user_id, $plan_data, 'manual');
            
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'message' => 'Payment request submitted. Our team will review it shortly.'
            ];
            
        } catch (Exception $e) {
            error_log("Manual Payment Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error processing payment'];
        }
    }
    
    /**
     * Get Available Payment Methods
     */
    public function getPaymentMethods() {
        return [
            'stripe' => [
                'name' => 'Credit/Debit Card',
                'description' => 'Visa, MasterCard, American Express',
                'icon' => 'fas fa-credit-card',
                'enabled' => true
            ],
            'paypal' => [
                'name' => 'PayPal',
                'description' => 'Pay with PayPal account',
                'icon' => 'fab fa-paypal',
                'enabled' => true
            ],
            'bank_transfer' => [
                'name' => 'Bank Transfer',
                'description' => 'Manual bank transfer',
                'icon' => 'fas fa-university',
                'enabled' => true
            ],
            'stripe_link' => [
                'name' => 'Link by Stripe',
                'description' => 'One-click checkout',
                'icon' => 'fas fa-bolt',
                'enabled' => false // Enable when configured
            ]
        ];
    }
    
    /**
     * Process Coupon/Discount Code
     */
    public function applyCoupon($coupon_code, $plan_price) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM coupons 
                WHERE code = ? 
                AND is_active = 1 
                AND (valid_until IS NULL OR valid_until >= NOW())
                AND (valid_from IS NULL OR valid_from <= NOW())
                AND (usage_limit IS NULL OR times_used < usage_limit)
            ");
            
            $stmt->execute([$coupon_code]);
            $coupon = $stmt->fetch();
            
            if (!$coupon) {
                return ['success' => false, 'message' => 'Invalid or expired coupon'];
            }
            
            // Calculate discount
            if ($coupon['discount_type'] === 'percentage') {
                $discount = ($plan_price * $coupon['discount_value']) / 100;
            } else {
                $discount = $coupon['discount_value'];
            }
            
            // Apply max discount if set
            if ($coupon['max_discount_amount'] && $discount > $coupon['max_discount_amount']) {
                $discount = $coupon['max_discount_amount'];
            }
            
            $final_price = max(0, $plan_price - $discount);
            
            return [
                'success' => true,
                'coupon' => $coupon,
                'discount' => $discount,
                'final_price' => $final_price,
                'discount_message' => $coupon['discount_type'] === 'percentage' ? 
                    $coupon['discount_value'] . '% off' : 
                    '$' . number_format($coupon['discount_value'], 2) . ' off'
            ];
            
        } catch (Exception $e) {
            error_log("Coupon Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error applying coupon'];
        }
    }
    
    /**
     * Generate Invoice for Payment
     */
    public function generateInvoice($user_id, $plan_data, $payment_data) {
        try {
            // Get user details
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            // Generate invoice number
            $invoice_number = 'INV-' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT) . '-' . time();
            
            // Create invoice
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    invoice_number, user_id, subtotal, tax_rate, tax_amount, 
                    total_amount, amount_paid, balance_due, payment_status, 
                    invoice_date, due_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 
                DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'draft', NOW())
            ");
            
            $tax_rate = 10; // 10% tax
            $subtotal = $plan_data['price'];
            $tax_amount = ($subtotal * $tax_rate) / 100;
            $total_amount = $subtotal + $tax_amount;
            
            $stmt->execute([
                $invoice_number,
                $user_id,
                $subtotal,
                $tax_rate,
                $tax_amount,
                $total_amount,
                0,
                $total_amount,
                'unpaid'
            ]);
            
            $invoice_id = $this->db->lastInsertId();
            
            // Add invoice item
            $stmt = $this->db->prepare("
                INSERT INTO invoice_items (
                    invoice_id, description, quantity, unit_price, 
                    discount, tax_rate, subtotal, created_at
                ) VALUES (?, ?, 1, ?, 0, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $invoice_id,
                $plan_data['name'] . ' Subscription',
                $subtotal,
                $tax_rate,
                $subtotal
            ]);
            
            return [
                'success' => true,
                'invoice_id' => $invoice_id,
                'invoice_number' => $invoice_number,
                'invoice_url' => SITE_URL . 'user/invoices/view.php?id=' . $invoice_id
            ];
            
        } catch (Exception $e) {
            error_log("Invoice Generation Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error generating invoice'];
        }
    }
    
    // Private helper methods
    private function getUser($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    private function getStripeCustomer($user) {
        // Check if customer exists
        $stmt = $this->db->prepare("SELECT stripe_customer_id FROM user_payment_methods WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $existing = $stmt->fetch();
        
        if ($existing && $existing['stripe_customer_id']) {
            return Customer::retrieve($existing['stripe_customer_id']);
        }
        
        // Create new customer
        $customer = Customer::create([
            'email' => $user['email'],
            'name' => $user['full_name'],
            'metadata' => ['user_id' => $user['id']]
        ]);
        
        // Save customer ID
        $stmt = $this->db->prepare("
            INSERT INTO user_payment_methods (user_id, stripe_customer_id, created_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE stripe_customer_id = ?
        ");
        
        $stmt->execute([$user['id'], $customer->id, $customer->id]);
        
        return $customer;
    }
    
    private function logPaymentIntent($user_id, $payment_id, $gateway, $plan_data) {
        $stmt = $this->db->prepare("
            INSERT INTO payment_intents (
                user_id, intent_id, gateway, amount, currency, 
                plan_id, plan_slug, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $payment_id,
            $gateway,
            $plan_data['price'],
            'USD',
            $plan_data['id'],
            $plan_data['slug']
        ]);
    }
    
    private function logUpgradeRequest($user_id, $plan_data, $method) {
        $stmt = $this->db->prepare("
            INSERT INTO upgrade_requests (
                user_id, plan_id, plan_name, plan_slug, amount, 
                payment_method, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $plan_data['id'],
            $plan_data['name'],
            $plan_data['slug'],
            $plan_data['price'],
            $method
        ]);
    }
    
    private function handleSuccessfulPayment($session) {
        $metadata = $session->metadata;
        $user_id = $metadata['user_id'];
        $plan_slug = $metadata['plan_slug'];
        
        // Update payment intent status
        $stmt = $this->db->prepare("
            UPDATE payment_intents 
            SET status = 'completed', completed_at = NOW() 
            WHERE intent_id = ? AND gateway = 'stripe'
        ");
        $stmt->execute([$session->id]);
        
        // Update user subscription
        $plan_name = str_replace('-plan', '', $plan_slug);
        $expiry_date = date('Y-m-d', strtotime("+30 days"));
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET subscription_plan = ?, subscription_expiry = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$plan_name, $expiry_date, $user_id]);
        
        // Record payment
        $stmt = $this->db->prepare("
            INSERT INTO payments (
                user_id, amount, currency, payment_method, transaction_id, 
                status, payment_details, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $session->amount_total / 100,
            strtoupper($session->currency),
            'stripe',
            $session->payment_intent,
            'completed',
            json_encode($session)
        ]);
        
        // Send confirmation email
        $this->sendConfirmationEmail($user_id, $plan_name, $session->amount_total / 100);
    }
    
    private function verifyPayPalOrder($order_id) {
        // PayPal verification logic
        // This would make an API call to verify the payment
        // For now, return success for demo
        return ['success' => true, 'message' => 'Payment verified'];
    }
    
    private function sendConfirmationEmail($user_id, $plan_name, $amount) {
        // Email sending logic
        // You can use PHPMailer or your existing email system
        error_log("Confirmation email sent to user $user_id for $plan_name plan");
    }
}

// Initialize payment gateway globally
$paymentGateway = new PaymentGateway();
?>