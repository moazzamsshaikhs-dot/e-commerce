<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/payment-config.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

$page_title = 'Payment Successful';
require_once '../../includes/header.php';

$db = getDB();
$paymentGateway = new PaymentGateway();

// Get payment parameters
$session_id = $_GET['session_id'] ?? '';
$payment_id = $_GET['payment_id'] ?? '';
$gateway = $_GET['gateway'] ?? '';

// Verify payment
if ($session_id || $payment_id) {
    $result = $paymentGateway->verifyPayment($session_id ?: $payment_id, $gateway);
    
    if (!$result['success']) {
        $_SESSION['error'] = $result['message'] ?? 'Payment verification failed';
        redirect('upgrade.php');
    }
} else {
    $_SESSION['error'] = 'Invalid payment response';
    redirect('upgrade.php');
}

// Log activity
logUserActivity($_SESSION['user_id'], 'payment_success', 'Payment completed successfully');
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Success Message -->
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-5">
                <div class="mb-4">
                    <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" 
                         style="width: 100px; height: 100px;">
                        <i class="fas fa-check-circle fa-3x text-success"></i>
                    </div>
                </div>
                
                <h1 class="mb-3">Payment Successful!</h1>
                <p class="text-muted mb-4">
                    Thank you for your payment. Your subscription has been activated.
                </p>
                
                <div class="row justify-content-center mb-5">
                    <div class="col-md-8">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row text-start">
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Transaction ID</small>
                                        <strong><?php echo $session_id ?: $payment_id; ?></strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Date</small>
                                        <strong><?php echo date('F d, Y h:i A'); ?></strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">Payment Method</small>
                                        <strong><?php echo ucfirst($gateway); ?></strong>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <small class="text-muted d-block">New Plan</small>
                                        <strong class="text-success"><?php echo ucfirst($_SESSION['subscription_plan']); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="../dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i> Go to Dashboard
                    </a>
                    <a href="<?php echo SITE_URL; ?>user/settings/settings.php?tab=billing" class="btn btn-outline-primary">
                        <i class="fas fa-receipt me-2"></i> View Invoice
                    </a>
                    <a href="../orders/shop.php" class="btn btn-success">
                        <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                    </a>
                </div>
            </div>
        </div>
        
        <!-- What's Next -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-rocket fa-2x text-primary mb-3"></i>
                        <h5>Explore Features</h5>
                        <p class="text-muted small">
                            Check out all the new features available with your upgraded plan
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-book fa-2x text-success mb-3"></i>
                        <h5>Read Documentation</h5>
                        <p class="text-muted small">
                            Learn how to make the most of your new subscription
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-headset fa-2x text-info mb-3"></i>
                        <h5>Need Help?</h5>
                        <p class="text-muted small">
                            Our support team is ready to help you get started
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.avatar-lg {
    animation: bounce 1s ease;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
</style>

<?php require_once '../../includes/footer.php'; ?>