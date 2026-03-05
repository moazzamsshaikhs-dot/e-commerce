<?php
// C:\xampp\htdocs\e-commerce\payment\index.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once dirname(__DIR__) . '/includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}

// Get payment details from URL
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$currency = isset($_GET['currency']) ? strtoupper($_GET['currency']) : 'USD';
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Validate amount
if ($amount <= 0) {
    header('Location: ' . SITE_URL . 'cart.php?error=invalid_amount');
    exit();
}

// Get Stripe publishable key from database
$db = getDB();
$stmt = $db->prepare("
    SELECT account_email FROM admin_accounts 
    WHERE account_type = 'stripe' AND is_active = 1 
    ORDER BY is_default DESC LIMIT 1
");
$stmt->execute();
$stripe_account = $stmt->fetch(PDO::FETCH_ASSOC);
$stripe_publishable_key = $stripe_account['account_email'] ?? '';

$page_title = 'Payment Gateway';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
:root {
    --primary: #4361ee;
    --success: #06d6a0;
    --warning: #ffb703;
    --danger: #ef476f;
    --info: #4cc9f0;
    --dark: #2b2d42;
    --light: #f8f9fa;
}

.payment-container {
    padding: 40px 20px;
    min-height: calc(100vh - 200px);
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.payment-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s ease;
}

.payment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(67, 97, 238, 0.15);
}

.payment-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
    color: white;
    padding: 25px;
    text-align: center;
}

.payment-body {
    padding: 30px;
}

.amount-box {
    background: var(--light);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    margin-bottom: 30px;
    border: 2px dashed var(--primary);
}

.amount-box .amount {
    font-size: 36px;
    font-weight: 700;
    color: var(--primary);
}

.amount-box .currency {
    font-size: 18px;
    color: var(--dark);
    opacity: 0.7;
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.method-card {
    background: white;
    border: 2px solid var(--border);
    border-radius: 15px;
    padding: 20px 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.method-card:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(67, 97, 238, 0.1);
}

.method-card.selected {
    border-color: var(--primary);
    background: var(--primary-light);
}

.method-card input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.method-card .method-icon {
    font-size: 32px;
    margin-bottom: 10px;
}

.method-card .method-name {
    font-weight: 600;
    color: var(--dark);
}

.method-card.paypal .method-icon { color: #003087; }
.method-card.stripe .method-icon { color: #635bff; }
.method-card.easypaisa .method-icon { color: #27aae1; }
.method-card.jazzcash .method-icon { color: #ed1c24; }
.method-card.visa .method-icon { color: #1a1f71; }
.method-card.mastercard .method-icon { color: #eb001b; }

.payment-details {
    margin-top: 30px;
    padding: 20px;
    background: var(--light);
    border-radius: 15px;
    border: 1px solid var(--border);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px var(--primary-light);
}

.btn-pay {
    background: var(--primary);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 10px;
    font-size: 18px;
    font-weight: 600;
    width: 100%;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-pay::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-pay:active::after {
    width: 300px;
    height: 300px;
}

.btn-pay:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
}

.btn-pay:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.card-element {
    padding: 15px;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: white;
    transition: all 0.3s ease;
}

.card-element:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.card-errors {
    color: var(--danger);
    font-size: 14px;
    margin-top: 8px;
    min-height: 20px;
}

.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .payment-methods {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .payment-methods {
        grid-template-columns: 1fr;
    }
    
    .row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="payment-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="payment-card">
                    <div class="payment-header">
                        <h2 class="mb-0">Complete Your Payment</h2>
                        <p class="mb-0 mt-2 opacity-75">Choose your preferred payment method</p>
                    </div>
                    
                    <div class="payment-body">
                        <!-- Amount Display -->
                        <div class="amount-box">
                            <div class="amount">$<?php echo number_format($amount, 2); ?></div>
                            <div class="currency"><?php echo $currency; ?></div>
                        </div>
                        
                        <!-- Payment Method Selection -->
                        <form id="payment-form">
                            <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                            <input type="hidden" name="currency" value="<?php echo $currency; ?>">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            
                            <div class="payment-methods">
                                <!-- PayPal -->
                                <label class="method-card paypal">
                                    <input type="radio" name="payment_method" value="paypal" class="method-radio">
                                    <div class="method-icon">
                                        <i class="fab fa-paypal"></i>
                                    </div>
                                    <div class="method-name">PayPal</div>
                                </label>
                                
                                <!-- Stripe -->
                                <label class="method-card stripe">
                                    <input type="radio" name="payment_method" value="stripe" class="method-radio">
                                    <div class="method-icon">
                                        <i class="fab fa-stripe"></i>
                                    </div>
                                    <div class="method-name">Stripe</div>
                                </label>
                                
                                <!-- Easypaisa -->
                                <label class="method-card easypaisa">
                                    <input type="radio" name="payment_method" value="easypaisa" class="method-radio">
                                    <div class="method-icon">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <div class="method-name">Easypaisa</div>
                                </label>
                                
                                <!-- JazzCash -->
                                <label class="method-card jazzcash">
                                    <input type="radio" name="payment_method" value="jazzcash" class="method-radio">
                                    <div class="method-icon">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <div class="method-name">JazzCash</div>
                                </label>
                                
                                <!-- Visa -->
                                <label class="method-card visa">
                                    <input type="radio" name="payment_method" value="visa" class="method-radio">
                                    <div class="method-icon">
                                        <i class="fab fa-cc-visa"></i>
                                    </div>
                                    <div class="method-name">Visa</div>
                                </label>
                                
                                <!-- Mastercard -->
                                <label class="method-card mastercard">
                                    <input type="radio" name="payment_method" value="mastercard" class="method-radio">
                                    <div class="method-icon">
                                        <i class="fab fa-cc-mastercard"></i>
                                    </div>
                                    <div class="method-name">Mastercard</div>
                                </label>
                            </div>
                            
                            <!-- Dynamic Payment Details -->
                            <div id="payment-details" class="payment-details" style="display: none;">
                                <!-- Content will be loaded dynamically -->
                            </div>
                            
                            <!-- Pay Button -->
                            <button type="button" id="pay-button" class="btn-pay mt-4" disabled>
                                Select Payment Method
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i> Payment Successful
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                <h4>Thank You!</h4>
                <p class="text-muted">Your payment has been processed successfully.</p>
                <div class="alert alert-light" id="transaction-details"></div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo SITE_URL; ?>" class="btn btn-primary">Go to Home</a>
                <a href="<?php echo SITE_URL; ?>orders.php" class="btn btn-outline-primary">View Orders</a>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-circle me-2"></i> Payment Failed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-times-circle fa-5x text-danger mb-3"></i>
                <h4>Oops!</h4>
                <p class="text-muted" id="error-message">Something went wrong. Please try again.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="location.reload()">Try Again</button>
            </div>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script src="<?php echo SITE_URL; ?>assets/js/payment-handler.js"></script>
<script>
// Initialize Stripe
const stripePublishableKey = '<?php echo $stripe_publishable_key; ?>';
let stripe = null;

if (stripePublishableKey) {
    stripe = Stripe(stripePublishableKey);
}

// Payment method selection
document.querySelectorAll('.method-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remove selected class from all cards
        document.querySelectorAll('.method-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to parent card
        this.closest('.method-card').classList.add('selected');
        
        // Show payment details
        showPaymentDetails(this.value);
        
        // Enable pay button
        document.getElementById('pay-button').disabled = false;
        document.getElementById('pay-button').innerHTML = `Pay $${<?php echo $amount; ?>} with ${getMethodName(this.value)}`;
    });
});

// Get method name
function getMethodName(method) {
    const names = {
        'paypal': 'PayPal',
        'stripe': 'Stripe',
        'easypaisa': 'Easypaisa',
        'jazzcash': 'JazzCash',
        'visa': 'Visa',
        'mastercard': 'Mastercard'
    };
    return names[method] || method;
}

// Show payment details
function showPaymentDetails(method) {
    const detailsDiv = document.getElementById('payment-details');
    let html = '';
    
    switch(method) {
        case 'stripe':
            html = `
                <div class="form-group">
                    <label>Card Details</label>
                    <div id="card-element" class="card-element">
                        <!-- Stripe Element will be inserted here -->
                    </div>
                    <div id="card-errors" class="card-errors"></div>
                </div>
            `;
            break;
            
        case 'visa':
        case 'mastercard':
            html = `
                <div class="form-group">
                    <label>Card Number</label>
                    <input type="text" class="form-control" id="card-number" placeholder="1234 5678 9012 3456">
                </div>
                <div class="row">
                    <div class="col">
                        <label>Expiry</label>
                        <input type="text" class="form-control" id="card-expiry" placeholder="MM/YY">
                    </div>
                    <div class="col">
                        <label>CVV</label>
                        <input type="text" class="form-control" id="card-cvv" placeholder="123">
                    </div>
                </div>
            `;
            break;
            
        case 'easypaisa':
        case 'jazzcash':
            html = `
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="tel" class="form-control" id="mobile-number" placeholder="03XXXXXXXXX">
                </div>
                <div class="form-group">
                    <label>Email (Optional)</label>
                    <input type="email" class="form-control" id="email" placeholder="your@email.com">
                </div>
            `;
            break;
            
        case 'paypal':
            html = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You will be redirected to PayPal to complete your payment.
                </div>
            `;
            break;
    }
    
    detailsDiv.innerHTML = html;
    detailsDiv.style.display = 'block';
    
    // Initialize Stripe if needed
    if (method === 'stripe' && stripe) {
        const elements = stripe.elements();
        const card = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                }
            }
        });
        card.mount('#card-element');
        
        card.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
        
        window.stripeCard = card;
    }
}

// Handle payment
document.getElementById('pay-button').addEventListener('click', async function() {
    const method = document.querySelector('input[name="payment_method"]:checked')?.value;
    if (!method) return;
    
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    try {
        let result;
        
        switch(method) {
            case 'paypal':
                window.location.href = `<?php echo SITE_URL; ?>payment/paypal.php?amount=<?php echo $amount; ?>&currency=<?php echo $currency; ?>&order_id=<?php echo $order_id; ?>`;
                return;
                
            case 'stripe':
                if (!window.stripeCard) throw new Error('Stripe not initialized');
                
                const {paymentMethod, error} = await stripe.createPaymentMethod({
                    type: 'card',
                    card: window.stripeCard,
                });
                
                if (error) throw error;
                
                result = await processStripePayment(paymentMethod.id);
                break;
                
            case 'visa':
            case 'mastercard':
                const cardNumber = document.getElementById('card-number')?.value;
                const cardExpiry = document.getElementById('card-expiry')?.value.split('/');
                const cardCvv = document.getElementById('card-cvv')?.value;
                
                if (!paymentHandler.validateCardNumber(cardNumber)) {
                    throw new Error('Invalid card number');
                }
                
                result = await processCardPayment({
                    number: cardNumber,
                    exp_month: cardExpiry[0]?.trim(),
                    exp_year: '20' + (cardExpiry[1]?.trim() || ''),
                    cvc: cardCvv,
                    type: method
                });
                break;
                
            case 'easypaisa':
                const epMobile = document.getElementById('mobile-number')?.value;
                const epEmail = document.getElementById('email')?.value;
                
                if (!epMobile) throw new Error('Please enter mobile number');
                
                await paymentHandler.processEasypaisa(<?php echo $amount; ?>, epMobile, epEmail);
                return;
                
            case 'jazzcash':
                const jcMobile = document.getElementById('mobile-number')?.value;
                const jcEmail = document.getElementById('email')?.value;
                
                if (!jcMobile) throw new Error('Please enter mobile number');
                
                await paymentHandler.processJazzCash(<?php echo $amount; ?>, jcMobile, jcEmail);
                return;
        }
        
        if (result && result.success) {
            showSuccess(result.transaction_id);
        } else {
            throw new Error(result?.error || 'Payment failed');
        }
        
    } catch (error) {
        showError(error.message);
    } finally {
        this.disabled = false;
        this.innerHTML = `Pay $${<?php echo $amount; ?>} with ${getMethodName(method)}`;
    }
});

// Process Stripe payment
async function processStripePayment(paymentMethodId) {
    const response = await fetch('<?php echo SITE_URL; ?>api/create-payment-intent.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            amount: <?php echo $amount; ?>,
            currency: '<?php echo $currency; ?>',
            payment_method_id: paymentMethodId,
            order_id: <?php echo $order_id; ?>
        })
    });
    
    return await response.json();
}

// Process card payment (Visa/Mastercard)
async function processCardPayment(cardData) {
    // First create payment method via backend
    const pmResponse = await fetch('<?php echo SITE_URL; ?>api/create-payment-method.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(cardData)
    });
    
    const pmResult = await pmResponse.json();
    
    if (!pmResult.success) {
        throw new Error(pmResult.error);
    }
    
    // Then process payment
    return processStripePayment(pmResult.payment_method_id);
}

// Show success modal
function showSuccess(transactionId) {
    document.getElementById('transaction-details').innerHTML = `
        <strong>Transaction ID:</strong> ${transactionId}<br>
        <strong>Amount:</strong> $<?php echo number_format($amount, 2); ?><br>
        <strong>Date:</strong> ${new Date().toLocaleString()}
    `;
    new bootstrap.Modal(document.getElementById('successModal')).show();
}

// Show error modal
function showError(message) {
    document.getElementById('error-message').textContent = message;
    new bootstrap.Modal(document.getElementById('errorModal')).show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>