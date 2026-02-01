<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/payment-config.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

$page_title = 'Upgrade Plan - Payment';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$paymentGateway = new PaymentGateway();

// Get subscription plans
$stmt = $db->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC");
$stmt->execute();
$plans = $stmt->fetchAll();

// Get user's current subscription
$stmt = $db->prepare("SELECT subscription_plan, subscription_expiry FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

// Process payment if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_slug = $_POST['plan_slug'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $coupon_code = $_POST['coupon_code'] ?? '';
    
    // Get plan details
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE slug = ?");
    $stmt->execute([$plan_slug]);
    $plan_data = $stmt->fetch();
    
    if (!$plan_data) {
        $_SESSION['error'] = 'Invalid plan selected';
        redirect('upgrade.php');
    }
    
    // Apply coupon if provided
    $original_price = $plan_data['price'];
    $final_price = $original_price;
    $discount = 0;
    $discount_message = '';
    
    if (!empty($coupon_code)) {
        $coupon_result = $paymentGateway->applyCoupon($coupon_code, $original_price);
        if ($coupon_result['success']) {
            $final_price = $coupon_result['final_price'];
            $discount = $coupon_result['discount'];
            $discount_message = $coupon_result['discount_message'];
            $_SESSION['success'] = "Coupon applied: $discount_message";
        } else {
            $_SESSION['error'] = $coupon_result['message'];
        }
    }
    
    // Process payment based on method
    switch ($payment_method) {
        case 'stripe':
            $return_url = SITE_URL . 'user/upgrade/payment-success.php';
            $result = $paymentGateway->createStripeCheckout($user_id, $plan_data, $return_url);
            
            if ($result['success']) {
                // Redirect to Stripe Checkout
                header('Location: https://checkout.stripe.com/pay/' . $result['session_id']);
                exit;
            } else {
                $_SESSION['error'] = $result['message'];
            }
            break;
            
        case 'paypal':
            $return_url = SITE_URL . 'user/upgrade/payment-success.php';
            $result = $paymentGateway->createPayPalOrder($user_id, $plan_data, $return_url);
            
            if ($result['success']) {
                // Redirect to PayPal
                header('Location: ' . $result['approve_url']);
                exit;
            } else {
                $_SESSION['error'] = $result['message'];
            }
            break;
            
        case 'bank_transfer':
            $payment_details = [
                'bank_name' => $_POST['bank_name'] ?? '',
                'account_name' => $_POST['account_name'] ?? '',
                'account_number' => $_POST['account_number'] ?? '',
                'transaction_reference' => $_POST['transaction_reference'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
            
            $result = $paymentGateway->processManualPayment($user_id, $plan_data, $payment_details);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                redirect('upgrade.php');
            } else {
                $_SESSION['error'] = $result['message'];
            }
            break;
            
        default:
            $_SESSION['error'] = 'Please select a payment method';
    }
}

// Get available payment methods
$payment_methods = $paymentGateway->getPaymentMethods();

// Log activity
logUserActivity($user_id, 'upgrade_page', 'Accessed upgrade page');
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Upgrade Your Plan</h1>
                    <p class="text-muted mb-0">Choose a plan and complete payment</p>
                </div>
                <div>
                    <span class="badge bg-<?php 
                        echo $user_info['subscription_plan'] == 'premium' ? 'warning' : 
                             ($user_info['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                    ?> fs-6">
                        Current: <?php echo ucfirst($user_info['subscription_plan']); ?> Plan
                    </span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Plan Selection -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h4 class="mb-0">Select Your Plan</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <?php foreach ($plans as $plan): 
                                $is_current_plan = $plan['slug'] === $user_info['subscription_plan'] . '-plan';
                                $features = json_decode($plan['features'] ?? '[]', true);
                            ?>
                                <div class="col-md-6">
                                    <div class="card h-100 border <?php echo $plan['is_popular'] ? 'border-primary border-2' : 'border-light'; ?>">
                                        <?php if ($plan['is_popular']): ?>
                                            <div class="card-header bg-primary text-white text-center py-2">
                                                <small><i class="fas fa-crown me-1"></i> Most Popular</small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-body d-flex flex-column">
                                            <div class="text-center mb-3">
                                                <h5 class="mb-1"><?php echo $plan['name']; ?></h5>
                                                <div class="price h3 fw-bold my-2">
                                                    <?php if ($plan['price'] == 0): ?>
                                                        FREE
                                                    <?php else: ?>
                                                        $<?php echo number_format($plan['price'], 2); ?>
                                                        <small class="text-muted fs-6">/month</small>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-muted small"><?php echo $plan['description']; ?></p>
                                            </div>
                                            
                                            <ul class="list-unstyled mb-4">
                                                <?php if (is_array($features)): ?>
                                                    <?php foreach ($features as $feature): ?>
                                                        <li class="mb-1">
                                                            <i class="fas fa-check text-success me-2"></i>
                                                            <small><?php echo htmlspecialchars($feature); ?></small>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                            
                                            <div class="mt-auto text-center">
                                                <?php if ($is_current_plan): ?>
                                                    <button class="btn btn-outline-primary w-100" disabled>
                                                        <i class="fas fa-check me-2"></i> Current Plan
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-primary w-100 select-plan-btn" 
                                                            data-plan-slug="<?php echo $plan['slug']; ?>"
                                                            data-plan-name="<?php echo $plan['name']; ?>"
                                                            data-plan-price="<?php echo $plan['price']; ?>">
                                                        <i class="fas fa-arrow-up me-2"></i> Select Plan
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Summary -->
            <div class="col-lg-4">
                <div class="sticky-top" style="top: 20px;">
                    <!-- Selected Plan Summary -->
                    <div class="card border-0 shadow-sm mb-4" id="planSummary" style="display: none;">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div id="selectedPlanDetails">
                                <!-- Will be populated by JavaScript -->
                            </div>
                            
                            <!-- Coupon Section -->
                            <div class="mb-3">
                                <label class="form-label small">Have a coupon?</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="couponCode" placeholder="Enter coupon code">
                                    <button class="btn btn-outline-secondary" type="button" id="applyCouponBtn">
                                        Apply
                                    </button>
                                </div>
                                <div id="couponMessage" class="small mt-2"></div>
                            </div>
                            
                            <!-- Total -->
                            <div class="border-top pt-3 mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal</span>
                                    <span id="subtotalAmount">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2" id="discountRow" style="display: none;">
                                    <span class="text-muted">Discount</span>
                                    <span class="text-success" id="discountAmount">-$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total</span>
                                    <span id="totalAmount">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Methods -->
                    <div class="card border-0 shadow-sm" id="paymentMethods" style="display: none;">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <form id="paymentForm" method="POST">
                                <input type="hidden" name="plan_slug" id="selectedPlanSlug">
                                <input type="hidden" name="coupon_code" id="appliedCouponCode">
                                
                                <!-- Payment Method Selection -->
                                <div class="mb-4">
                                    <?php foreach ($payment_methods as $method_key => $method): 
                                        if ($method['enabled']):
                                    ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" 
                                                   type="radio" 
                                                   name="payment_method" 
                                                   id="method_<?php echo $method_key; ?>"
                                                   value="<?php echo $method_key; ?>"
                                                   data-method="<?php echo $method_key; ?>"
                                                   required>
                                            <label class="form-check-label d-flex align-items-center" for="method_<?php echo $method_key; ?>">
                                                <i class="<?php echo $method['icon']; ?> fa-lg me-3 text-primary"></i>
                                                <div>
                                                    <strong><?php echo $method['name']; ?></strong>
                                                    <div class="small text-muted"><?php echo $method['description']; ?></div>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endif; endforeach; ?>
                                </div>
                                
                                <!-- Bank Transfer Details (Hidden by default) -->
                                <div id="bankTransferDetails" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Bank Name</label>
                                        <input type="text" class="form-control" name="bank_name">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Account Holder Name</label>
                                        <input type="text" class="form-control" name="account_name">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Account Number</label>
                                        <input type="text" class="form-control" name="account_number">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Transaction Reference</label>
                                        <input type="text" class="form-control" name="transaction_reference" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Additional Notes</label>
                                        <textarea class="form-control" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                                
                                <!-- Terms and Conditions -->
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="termsAgreement" required>
                                    <label class="form-check-label small" for="termsAgreement">
                                        I agree to the <a href="<?php echo SITE_URL; ?>terms.php" target="_blank">Terms of Service</a> 
                                        and authorize the charge for the selected plan
                                    </label>
                                </div>
                                
                                <!-- Submit Button -->
                                <button type="submit" class="btn btn-primary w-100 btn-lg" id="payNowBtn" disabled>
                                    <i class="fas fa-lock me-2"></i> Pay Now
                                </button>
                                
                                <p class="text-muted small text-center mt-3">
                                    <i class="fas fa-shield-alt me-1"></i> Secure payment • 256-bit SSL encryption
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- FAQ Section -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h4 class="mb-0">Billing Questions</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-sync-alt text-primary me-2"></i> Billing Cycle</h6>
                        <p class="small text-muted">All plans are billed monthly. You can cancel anytime.</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-shield-alt text-success me-2"></i> Security</h6>
                        <p class="small text-muted">Your payment information is encrypted and secure.</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-undo text-info me-2"></i> Refund Policy</h6>
                        <p class="small text-muted">30-day money-back guarantee for all paid plans.</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-headset text-warning me-2"></i> Support</h6>
                        <p class="small text-muted">Contact support for billing questions or assistance.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Payment Success Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Processing Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5>Redirecting to payment gateway...</h5>
                <p class="text-muted">Please wait while we redirect you to complete your payment.</p>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    let selectedPlan = null;
    let appliedCoupon = null;
    
    // Plan selection
    $('.select-plan-btn').click(function() {
        selectedPlan = {
            slug: $(this).data('plan-slug'),
            name: $(this).data('plan-name'),
            price: parseFloat($(this).data('plan-price'))
        };
        
        updatePlanSummary();
        showPaymentSection();
        
        // Scroll to payment section
        $('html, body').animate({
            scrollTop: $('#paymentMethods').offset().top - 100
        }, 1000);
    });
    
    // Update plan summary
    function updatePlanSummary() {
        if (!selectedPlan) return;
        
        const subtotal = selectedPlan.price;
        const discount = appliedCoupon ? appliedCoupon.discount : 0;
        const total = subtotal - discount;
        
        // Update plan details
        $('#selectedPlanDetails').html(`
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h6 class="mb-1">${selectedPlan.name}</h6>
                    <small class="text-muted">Monthly subscription</small>
                </div>
                <div class="text-end">
                    <h6 class="mb-0">$${subtotal.toFixed(2)}</h6>
                    ${appliedCoupon ? `<small class="text-success">${appliedCoupon.message}</small>` : ''}
                </div>
            </div>
        `);
        
        // Update amounts
        $('#subtotalAmount').text('$' + subtotal.toFixed(2));
        $('#totalAmount').text('$' + total.toFixed(2));
        
        if (appliedCoupon) {
            $('#discountRow').show();
            $('#discountAmount').text('- $' + discount.toFixed(2));
        } else {
            $('#discountRow').hide();
        }
        
        // Update hidden fields
        $('#selectedPlanSlug').val(selectedPlan.slug);
    }
    
    // Show payment section
    function showPaymentSection() {
        $('#planSummary').slideDown();
        $('#paymentMethods').slideDown();
    }
    
    // Apply coupon
    $('#applyCouponBtn').click(function() {
        const couponCode = $('#couponCode').val().trim();
        
        if (!couponCode) {
            $('#couponMessage').html('<span class="text-danger">Please enter a coupon code</span>');
            return;
        }
        
        if (!selectedPlan) {
            $('#couponMessage').html('<span class="text-danger">Please select a plan first</span>');
            return;
        }
        
        $.ajax({
            url: 'apply-coupon-ajax.php',
            type: 'POST',
            data: {
                coupon_code: couponCode,
                plan_price: selectedPlan.price
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    appliedCoupon = {
                        code: couponCode,
                        discount: response.discount,
                        message: response.discount_message
                    };
                    
                    $('#appliedCouponCode').val(couponCode);
                    $('#couponMessage').html(`<span class="text-success"><i class="fas fa-check me-1"></i> ${response.discount_message}</span>`);
                    updatePlanSummary();
                } else {
                    appliedCoupon = null;
                    $('#appliedCouponCode').val('');
                    $('#couponMessage').html(`<span class="text-danger"><i class="fas fa-times me-1"></i> ${response.message}</span>`);
                    updatePlanSummary();
                }
            }
        });
    });
    
    // Payment method selection
    $('input[name="payment_method"]').change(function() {
        const method = $(this).data('method');
        
        // Show/hide bank transfer details
        if (method === 'bank_transfer') {
            $('#bankTransferDetails').slideDown();
        } else {
            $('#bankTransferDetails').slideUp();
        }
        
        // Enable/disable pay button
        updatePayButton();
    });
    
    // Terms agreement
    $('#termsAgreement').change(function() {
        updatePayButton();
    });
    
    // Update pay button state
    function updatePayButton() {
        const paymentMethodSelected = $('input[name="payment_method"]:checked').length > 0;
        const termsAgreed = $('#termsAgreement').prop('checked');
        
        if (paymentMethodSelected && termsAgreed) {
            $('#payNowBtn').prop('disabled', false);
        } else {
            $('#payNowBtn').prop('disabled', true);
        }
    }
    
    // Form submission
    $('#paymentForm').submit(function(e) {
        e.preventDefault();
        
        if (!selectedPlan) {
            showToast('Please select a plan first', 'error');
            return;
        }
        
        const paymentMethod = $('input[name="payment_method"]:checked').val();
        
        if (!paymentMethod) {
            showToast('Please select a payment method', 'error');
            return;
        }
        
        // Show loading modal for online payments
        if (paymentMethod === 'stripe' || paymentMethod === 'paypal') {
            $('#paymentModal').modal('show');
            
            // Auto-submit form after showing modal
            setTimeout(() => {
                this.submit();
            }, 1000);
        } else {
            // For bank transfer, submit normally
            this.submit();
        }
    });
    
    // Toast notification
    function showToast(message, type = 'info') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0 position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
});
</script>

<style>
.sticky-top {
    position: -webkit-sticky;
    position: sticky;
}

.price {
    background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

#planSummary, #paymentMethods {
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-check-input:checked {
    background-color: #2575fc;
    border-color: #2575fc;
}

#payNowBtn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    transition: transform 0.3s ease;
}

#payNowBtn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}
</style>

<?php require_once '../../includes/footer.php'; ?>