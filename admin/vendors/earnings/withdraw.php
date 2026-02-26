<?php
// admin/vendors/earnings/withdraw.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

// Check if vendor is approved
$vendor_id = $_SESSION['user_id'];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor_status = $stmt->fetchColumn();

    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved.';
        header('Location: ' . SITE_URL . 'admin/vendors/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'admin/vendors/dashboard.php');
    exit();
}

$page_title = 'Withdraw Earnings';

// Initialize variables
$bank_accounts = [];
$paypal_accounts = [];
$stripe_accounts = [];
$easypaisa_accounts = [];
$jazzcash_accounts = [];
$cards = [];
$withdrawals = [];
$notifications = [];
$paid_earnings = 0;
$processing_amount = 0;
$available_balance = 0;

// Get vendor earnings and payment methods
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];

    // Get vendor details
    $stmt = $db->prepare("SELECT full_name, email, country FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vendor) {
        $vendor = ['full_name' => '', 'email' => '', 'country' => ''];
    }

    // Get paid earnings from vendor_earnings
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(vendor_amount), 0) as paid_earnings 
        FROM vendor_earnings 
        WHERE vendor_id = ? AND status = 'paid'
    ");
    $stmt->execute([$vendor_id]);
    $paid_earnings = $stmt->fetchColumn();
    
    // Get processing withdrawals amount
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(withdrawal_amount), 0) as processing_amount
        FROM vendor_withdrawals
        WHERE vendor_id = ? AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$vendor_id]);
    $processing_amount = $stmt->fetchColumn();
    
    $available_balance = $paid_earnings - $processing_amount;

    // Get bank accounts
    $stmt = $db->prepare("
        SELECT * FROM vendor_bank_accounts 
        WHERE vendor_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get PayPal accounts
    try {
        $stmt = $db->prepare("
            SELECT * FROM vendor_paypal_accounts 
            WHERE vendor_id = ? 
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$vendor_id]);
        $paypal_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $paypal_accounts = [];
    }

    // Get Stripe accounts
    try {
        $stmt = $db->prepare("
            SELECT * FROM vendor_stripe_accounts 
            WHERE vendor_id = ? 
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$vendor_id]);
        $stripe_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stripe_accounts = [];
    }

    // Get Easypaisa accounts
    try {
        $stmt = $db->prepare("
            SELECT * FROM vendor_mobile_accounts 
            WHERE vendor_id = ? AND account_type = 'easypaisa'
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$vendor_id]);
        $easypaisa_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $easypaisa_accounts = [];
    }

    // Get JazzCash accounts
    try {
        $stmt = $db->prepare("
            SELECT * FROM vendor_mobile_accounts 
            WHERE vendor_id = ? AND account_type = 'jazzcash'
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$vendor_id]);
        $jazzcash_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $jazzcash_accounts = [];
    }

    // Get cards
    try {
        $stmt = $db->prepare("
            SELECT * FROM vendor_cards 
            WHERE vendor_id = ? 
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$vendor_id]);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cards = [];
    }

    // Get withdrawal history
    $stmt = $db->prepare("
        SELECT * FROM vendor_withdrawals 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$vendor_id]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get notifications
    try {
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$vendor_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $notifications = [];
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error loading withdrawal data: ' . $e->getMessage();
    error_log("Withdraw page error: " . $e->getMessage());
}

$min_withdrawal = 50.00;

// Calculate counts for quick stats
$bank_count = count($bank_accounts);
$bank_verified = count(array_filter($bank_accounts, function($acc) { return !empty($acc['is_verified']); }));

$paypal_count = count($paypal_accounts);
$paypal_verified = count(array_filter($paypal_accounts, function($acc) { return !empty($acc['is_verified']); }));

$stripe_count = count($stripe_accounts);
$stripe_verified = count(array_filter($stripe_accounts, function($acc) { return !empty($acc['is_verified']); }));

$easypaisa_count = count($easypaisa_accounts);
$easypaisa_verified = count(array_filter($easypaisa_accounts, function($acc) { return !empty($acc['is_verified']); }));

$jazzcash_count = count($jazzcash_accounts);
$jazzcash_verified = count(array_filter($jazzcash_accounts, function($acc) { return !empty($acc['is_verified']); }));

$cards_count = count($cards);
$cards_verified = count(array_filter($cards, function($acc) { return !empty($acc['is_verified']); }));

// Helper functions
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'processing' => 'info',
        'completed' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'secondary'
    ];
    $class = $classes[$status] ?? 'secondary';
    return "<span class='badge bg-{$class} px-3 py-2 rounded-pill'>{$status}</span>";
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Include header
require_once '../../includes/header.php';
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
    --warning-gradient: linear-gradient(135deg, #fad961 0%, #f76b1c 100%);
    --info-gradient: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
    --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: #f4f7fc;
}

.main-content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
}

/* Stats Cards */
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: var(--primary-gradient);
}

.stat-card.success::before { background: var(--success-gradient); }
.stat-card.warning::before { background: var(--warning-gradient); }
.stat-card.primary::before { background: var(--primary-gradient); }

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
}

.stat-card.success .stat-icon {
    background: rgba(132, 250, 176, 0.1);
    color: #84fab0;
}

.stat-card.warning .stat-icon {
    background: rgba(250, 217, 97, 0.1);
    color: #fad961;
}

.stat-card.primary .stat-icon {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
}

/* Payment Method Cards */
.method-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.method-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.method-card.active {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
}

.method-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 15px;
}

.method-card.bank .method-icon { background: rgba(102, 126, 234, 0.1); color: #667eea; }
.method-card.paypal .method-icon { background: rgba(0, 115, 177, 0.1); color: #0073b1; }
.method-card.stripe .method-icon { background: rgba(106, 27, 154, 0.1); color: #6a1b9a; }
.method-card.easypaisa .method-icon { background: rgba(40, 167, 69, 0.1); color: #28a745; }
.method-card.jazzcash .method-icon { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
.method-card.cards .method-icon { background: rgba(255, 193, 7, 0.1); color: #ffc107; }

.method-count {
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 20px;
    background: #f0f0f0;
    display: inline-block;
    margin-top: 10px;
}

/* Account Cards */
.account-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid #dee2e6;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.account-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.account-card.default {
    border-left-color: #28a745;
    background: linear-gradient(135deg, #f8fff9 0%, #ffffff 100%);
}

.account-card.verified {
    border-left-color: #17a2b8;
}

.account-card.pending {
    border-left-color: #ffc107;
    opacity: 0.9;
}

/* Amount Input */
.amount-input-group {
    display: flex;
    align-items: stretch;
    border-radius: 15px;
    overflow: hidden;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.amount-input-group:focus-within {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.amount-input-group .input-group-text {
    background: #f8f9fa;
    border: none;
    font-size: 18px;
    font-weight: 600;
    padding: 12px 20px;
}

.amount-input-group input {
    border: none;
    padding: 12px 20px;
    font-size: 18px;
    font-weight: 600;
    background: white;
}

.amount-input-group input:focus {
    outline: none;
    box-shadow: none;
}

.amount-input-group .btn-max {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.amount-input-group .btn-max:hover {
    background: #5a67d8;
}

/* Custom Buttons */
.btn-gradient {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

/* Method options */
.method-option {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    padding: 15px 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.method-option i {
    font-size: 24px;
    display: block;
    margin-bottom: 8px;
}

.method-option span {
    font-size: 13px;
    font-weight: 500;
}

.method-option.active {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
}

.method-option.bank-option.active i,
.method-option.bank-option.active span { color: #667eea; }

.method-option.paypal-option.active i,
.method-option.paypal-option.active span { color: #0073b1; }

.method-option.stripe-option.active i,
.method-option.stripe-option.active span { color: #6a1b9a; }

.method-option.easypaisa-option.active i,
.method-option.easypaisa-option.active span { color: #28a745; }

.method-option.jazzcash-option.active i,
.method-option.jazzcash-option.active span { color: #dc3545; }

.method-option.cards-option.active i,
.method-option.cards-option.active span { color: #ffc107; }

/* Progress bars */
.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
}

/* Tab styling */
.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    padding: 12px 20px;
    border-radius: 12px 12px 0 0;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    background: #f8f9fa;
    color: #495057;
}

.nav-tabs .nav-link.active {
    color: #667eea;
    background: white;
    border-bottom: 3px solid #667eea;
}

.nav-tabs .badge {
    font-size: 11px;
    padding: 4px 8px;
}

/* Timeline */
.timeline-item {
    position: relative;
    padding-left: 30px;
    padding-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item::after {
    content: '';
    position: absolute;
    left: -4px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #667eea;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item:last-child::before {
    display: none;
}

/* Rounded utilities */
.rounded-15 { border-radius: 15px; }
.rounded-20 { border-radius: 20px; }

/* Opacity utilities */
.opacity-25 { opacity: 0.25; }

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-in {
    animation: slideIn 0.5s ease forwards;
}

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
</style>

<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>

    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 animate-slide-in">
            <div>
                <h1 class="h2 fw-bold text-dark mb-1">Withdraw Earnings</h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-clock me-2"></i>Last updated: <?php echo date('F j, Y g:i A'); ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="earnings.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i>Back to Earnings
                </a>
                <button class="btn btn-outline-primary rounded-pill px-4" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (!empty($notifications)): ?>
            <div class="mb-4 animate-slide-in delay-1">
                <?php foreach ($notifications as $note): ?>
                    <div class="alert alert-<?php echo $note['type']; ?> alert-dismissible fade show rounded-15 shadow-sm" role="alert">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-<?php echo $note['type'] == 'success' ? 'check-circle' : ($note['type'] == 'error' ? 'exclamation-circle' : 'info-circle'); ?> fa-2x"></i>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($note['title']); ?></strong><br>
                                <?php echo htmlspecialchars($note['message']); ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Error/Success Messages -->
        <?php if (isset($_SESSION['form_errors'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-15 shadow-sm mb-4 animate-slide-in delay-1" role="alert">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-exclamation-circle fa-2x"></i>
                    </div>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($_SESSION['form_errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['form_errors']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-15 shadow-sm mb-4 animate-slide-in delay-1" role="alert">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-exclamation-circle fa-2x"></i>
                    </div>
                    <div>
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-15 shadow-sm mb-4 animate-slide-in delay-1" role="alert">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div>
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-4 animate-slide-in delay-1">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-2">Paid Earnings</p>
                            <h2 class="fw-bold mb-2"><?php echo formatCurrency($paid_earnings); ?></h2>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-arrow-up text-success me-1"></i>
                                Total earnings paid to you
                            </p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 animate-slide-in delay-2">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-2">Processing</p>
                            <h2 class="fw-bold mb-2"><?php echo formatCurrency($processing_amount); ?></h2>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-clock text-warning me-1"></i>
                                Pending withdrawal requests
                            </p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 animate-slide-in delay-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-2">Available for Withdrawal</p>
                            <h2 class="fw-bold mb-2"><?php echo formatCurrency($available_balance); ?></h2>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle text-primary me-1"></i>
                                Min: <?php echo formatCurrency($min_withdrawal); ?>
                            </p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods Quick Access -->
        <div class="row g-3 mb-5">
            <div class="col-12">
                <h5 class="fw-bold mb-3">Quick Access</h5>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="method-card bank" onclick="showMethod('bank')">
                    <div class="method-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <h6 class="mb-1">Bank Accounts</h6>
                    <span class="method-count"><?php echo $bank_verified; ?>/<?php echo $bank_count; ?> Verified</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="method-card paypal" onclick="showMethod('paypal')">
                    <div class="method-icon">
                        <i class="fab fa-paypal"></i>
                    </div>
                    <h6 class="mb-1">PayPal</h6>
                    <span class="method-count"><?php echo $paypal_verified; ?>/<?php echo $paypal_count; ?> Verified</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="method-card stripe" onclick="showMethod('stripe')">
                    <div class="method-icon">
                        <i class="fab fa-stripe"></i>
                    </div>
                    <h6 class="mb-1">Stripe</h6>
                    <span class="method-count"><?php echo $stripe_verified; ?>/<?php echo $stripe_count; ?> Verified</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="method-card easypaisa" onclick="showMethod('easypaisa')">
                    <div class="method-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h6 class="mb-1">Easypaisa</h6>
                    <span class="method-count"><?php echo $easypaisa_verified; ?>/<?php echo $easypaisa_count; ?> Verified</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="method-card jazzcash" onclick="showMethod('jazzcash')">
                    <div class="method-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h6 class="mb-1">JazzCash</h6>
                    <span class="method-count"><?php echo $jazzcash_verified; ?>/<?php echo $jazzcash_count; ?> Verified</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="method-card cards" onclick="showMethod('cards')">
                    <div class="method-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h6 class="mb-1">Cards & Stripe</h6>
                    <span class="method-count"><?php echo $cards_verified + $stripe_verified; ?>/<?php echo $cards_count + $stripe_count; ?> Verified</span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column - Withdrawal Form & Accounts -->
            <div class="col-lg-7">
                <!-- Withdrawal Form Card -->
                <div class="card border-0 shadow-sm rounded-20 mb-4 animate-slide-in">
                    <div class="card-header bg-white border-0 p-4">
                        <h5 class="mb-0 fw-bold">
                            <span class="bg-primary bg-opacity-10 rounded-15 p-2 me-2">
                                <i class="fas fa-paper-plane text-primary"></i>
                            </span>
                            Request New Withdrawal
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <?php if ($available_balance < $min_withdrawal): ?>
                            <div class="alert alert-info rounded-15 border-0 bg-info bg-opacity-10">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-info-circle fa-2x text-info"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Insufficient Balance</h6>
                                        <p class="mb-0">You need <?php echo formatCurrency($min_withdrawal - $available_balance); ?> more to request a withdrawal. 
                                        <a href="../dashboard.php" class="text-info fw-bold">Continue selling →</a></p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="withdrawalForm" action="action/process-withdrawal.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-muted mb-2">Withdrawal Amount</label>
                                    <div class="amount-input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" 
                                               name="withdrawal_amount" 
                                               class="form-control" 
                                               step="0.01" 
                                               min="<?php echo $min_withdrawal; ?>" 
                                               max="<?php echo $available_balance; ?>"
                                               value="<?php echo min($available_balance, $available_balance); ?>"
                                               id="withdrawalAmount"
                                               required>
                                        <button type="button" class="btn-max" onclick="setMaxAmount()">MAX</button>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted">Min: <?php echo formatCurrency($min_withdrawal); ?></small>
                                        <small class="text-muted">Max: <?php echo formatCurrency($available_balance); ?></small>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-muted mb-2">Payment Method</label>
                                    <div class="row g-2" id="methodSelection">
                                        <div class="col-6">
                                            <div class="method-option bank-option active" onclick="selectMethod('bank')" data-method="bank">
                                                <i class="fas fa-university"></i>
                                                <span>Bank Transfer</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="method-option paypal-option" onclick="selectMethod('paypal')" data-method="paypal">
                                                <i class="fab fa-paypal"></i>
                                                <span>PayPal</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="method-option stripe-option" onclick="selectMethod('stripe')" data-method="stripe">
                                                <i class="fab fa-stripe"></i>
                                                <span>Stripe</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="method-option easypaisa-option" onclick="selectMethod('easypaisa')" data-method="easypaisa">
                                                <i class="fas fa-mobile-alt"></i>
                                                <span>Easypaisa</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="method-option jazzcash-option" onclick="selectMethod('jazzcash')" data-method="jazzcash">
                                                <i class="fas fa-mobile-alt"></i>
                                                <span>JazzCash</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="method-option cards-option" onclick="selectMethod('cards')" data-method="cards">
                                                <i class="fas fa-credit-card"></i>
                                                <span>Cards/Stripe</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bank Account Selection -->
                                <div class="mb-4 method-fields" id="bankField">
                                    <label class="form-label fw-bold text-muted mb-2">Select Bank Account</label>
                                    <?php 
                                    $verified_banks = array_filter($bank_accounts, function($acc) { return !empty($acc['is_verified']); });
                                    if (empty($verified_banks)): ?>
                                        <div class="alert alert-warning rounded-15">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            No verified bank accounts. 
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#addBankModal" class="alert-link">Add one now</a>
                                        </div>
                                    <?php else: ?>
                                        <select name="account_id" class="form-select form-select-lg rounded-15">
                                            <option value="">Choose account</option>
                                            <?php foreach ($verified_banks as $acc): ?>
                                                <option value="<?php echo $acc['id']; ?>" <?php echo !empty($acc['is_default']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($acc['bank_name']); ?> - 
                                                    ****<?php echo substr($acc['account_number'], -4); ?>
                                                    <?php echo !empty($acc['is_default']) ? '(Default)' : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>

                                <!-- PayPal Field -->
                                <div class="mb-4 method-fields d-none" id="paypalField">
                                    <label class="form-label fw-bold text-muted mb-2">PayPal Email</label>
                                    <input type="email" name="paypal_email" class="form-control form-control-lg rounded-15" 
                                           placeholder="your@email.com">
                                    <small class="text-muted">Enter the email associated with your PayPal account</small>
                                </div>

                                <!-- Stripe Field -->
                                <div class="mb-4 method-fields d-none" id="stripeField">
                                    <label class="form-label fw-bold text-muted mb-2">Stripe Account ID</label>
                                    <input type="text" name="stripe_account_id" class="form-control form-control-lg rounded-15" 
                                           placeholder="acct_...">
                                    <small class="text-muted">Your Stripe account ID (starts with acct_)</small>
                                </div>

                                <!-- Mobile Account Selection (Easypaisa/JazzCash) -->
                                <div class="mb-4 method-fields d-none" id="mobileField">
                                    <label class="form-label fw-bold text-muted mb-2" id="mobileFieldLabel">Select Account</label>
                                    <select name="mobile_account_id" class="form-select form-select-lg rounded-15" id="mobileSelect">
                                        <option value="">Choose account</option>
                                    </select>
                                </div>

                                <!-- Card/Stripe Selection -->
                                <div class="mb-4 method-fields d-none" id="cardField">
                                    <label class="form-label fw-bold text-muted mb-2">Select Payment Method</label>
                                    
                                    <!-- Stripe Accounts -->
                                    <?php if (!empty($stripe_accounts)): ?>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Stripe Accounts</label>
                                            <select name="stripe_account_id" class="form-select form-select-lg rounded-15 mb-3">
                                                <option value="">Choose Stripe account</option>
                                                <?php foreach ($stripe_accounts as $acc): ?>
                                                    <?php if (!empty($acc['is_verified'])): ?>
                                                    <option value="<?php echo $acc['id']; ?>" <?php echo !empty($acc['is_default']) ? 'selected' : ''; ?>>
                                                        <i class="fab fa-stripe me-1"></i>
                                                        Stripe - <?php echo htmlspecialchars($acc['account_email']); ?> 
                                                        (<?php echo substr($acc['stripe_account_id'], 0, 8); ?>...)
                                                        <?php echo !empty($acc['is_default']) ? ' (Default)' : ''; ?>
                                                    </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Regular Cards -->
                                    <?php if (!empty($cards)): ?>
                                        <div>
                                            <label class="form-label text-muted small">Credit/Debit Cards</label>
                                            <select name="card_id" class="form-select form-select-lg rounded-15">
                                                <option value="">Choose card</option>
                                                <?php foreach ($cards as $card): ?>
                                                    <?php if (!empty($card['is_verified'])): ?>
                                                    <option value="<?php echo $card['id']; ?>" <?php echo !empty($card['is_default']) ? 'selected' : ''; ?>>
                                                        <?php 
                                                        $card_icon = '';
                                                        if ($card['card_type'] == 'visa') $card_icon = '💳 Visa';
                                                        elseif ($card['card_type'] == 'mastercard') $card_icon = '💳 Mastercard';
                                                        elseif ($card['card_type'] == 'amex') $card_icon = '💳 Amex';
                                                        else $card_icon = '💳 Card';
                                                        ?>
                                                        <?php echo $card_icon; ?> - 
                                                        **** **** **** <?php echo $card['card_last_four']; ?>
                                                        (Exp: <?php echo $card['expiry_month']; ?>/<?php echo $card['expiry_year']; ?>)
                                                        <?php echo !empty($card['is_default']) ? ' (Default)' : ''; ?>
                                                    </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($stripe_accounts) && empty($cards)): ?>
                                        <div class="alert alert-warning rounded-15">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            No verified cards or Stripe accounts. 
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#addCardModal" class="alert-link">Add a card</a> or 
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#addStripeModal" class="alert-link">connect Stripe</a>.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-muted mb-2">Notes (Optional)</label>
                                    <textarea name="notes" class="form-control rounded-15" rows="3" 
                                              placeholder="Any special instructions or notes..."></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                        <label class="form-check-label" for="agreeTerms">
                                            I confirm that the above details are correct and I understand that 
                                            withdrawals are processed within 3-5 business days.
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn-gradient w-100 py-3" id="submitBtn">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Withdrawal Request
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tabs for Payment Methods -->
                <div class="card border-0 shadow-sm rounded-20 animate-slide-in">
                    <div class="card-header bg-white border-0 p-4 pb-0">
                        <ul class="nav nav-tabs border-0" id="paymentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank" type="button">
                                    <i class="fas fa-university me-2"></i>Bank Accounts
                                    <?php if ($bank_count > 0): ?>
                                        <span class="badge bg-primary ms-2"><?php echo $bank_count; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="paypal-tab" data-bs-toggle="tab" data-bs-target="#paypal" type="button">
                                    <i class="fab fa-paypal me-2"></i>PayPal
                                    <?php if ($paypal_count > 0): ?>
                                        <span class="badge bg-info ms-2"><?php echo $paypal_count; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="stripe-tab" data-bs-toggle="tab" data-bs-target="#stripe" type="button">
                                    <i class="fab fa-stripe me-2"></i>Stripe
                                    <?php if ($stripe_count > 0): ?>
                                        <span class="badge bg-purple ms-2"><?php echo $stripe_count; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="easypaisa-tab" data-bs-toggle="tab" data-bs-target="#easypaisa" type="button">
                                    <i class="fas fa-mobile-alt me-2"></i>Easypaisa
                                    <?php if ($easypaisa_count > 0): ?>
                                        <span class="badge bg-success ms-2"><?php echo $easypaisa_count; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="jazzcash-tab" data-bs-toggle="tab" data-bs-target="#jazzcash" type="button">
                                    <i class="fas fa-mobile-alt me-2"></i>JazzCash
                                    <?php if ($jazzcash_count > 0): ?>
                                        <span class="badge bg-danger ms-2"><?php echo $jazzcash_count; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="cards-tab" data-bs-toggle="tab" data-bs-target="#cards" type="button">
                                    <i class="fas fa-credit-card me-2"></i>Cards & Stripe
                                    <?php if ($cards_count + $stripe_count > 0): ?>
                                        <span class="badge bg-warning ms-2"><?php echo $cards_count + $stripe_count; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-4">
                        <div class="tab-content">
                            <!-- Bank Tab -->
                            <div class="tab-pane fade show active" id="bank" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0">Your Bank Accounts</h6>
                                    <button class="btn btn-sm btn-gradient rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addBankModal">
                                        <i class="fas fa-plus me-1"></i> Add New
                                    </button>
                                </div>
                                <?php if (empty($bank_accounts)): ?>
                                    <div class="text-center py-5">
                                        <div class="mb-3">
                                            <i class="fas fa-university fa-4x text-muted opacity-25"></i>
                                        </div>
                                        <h6 class="text-muted mb-2">No Bank Accounts Added</h6>
                                        <p class="text-muted small mb-3">Add your first bank account to start withdrawing</p>
                                        <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addBankModal">
                                            <i class="fas fa-plus me-2"></i>Add Bank Account
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($bank_accounts as $acc): ?>
                                        <div class="account-card <?php echo !empty($acc['is_default']) ? 'default' : ''; ?> <?php echo !empty($acc['is_verified']) ? 'verified' : 'pending'; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($acc['bank_name']); ?></h6>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($acc['account_holder_name']); ?><br>
                                                        <i class="fas fa-credit-card me-1 text-muted"></i>****<?php echo substr($acc['account_number'], -4); ?>
                                                        <?php if (!empty($acc['ifsc_code'])): ?>
                                                            <br><i class="fas fa-code me-1 text-muted"></i>IFSC: <?php echo $acc['ifsc_code']; ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <?php if (!empty($acc['is_default'])): ?>
                                                        <span class="badge bg-success mb-2">Default</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <?php if (!empty($acc['is_verified'])): ?>
                                                        <span class="badge bg-info">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <?php if (empty($acc['is_default']) && !empty($acc['is_verified'])): ?>
                                                    <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="setDefault('bank', <?php echo $acc['id']; ?>)">
                                                        <i class="fas fa-check me-1"></i>Set Default
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="deleteMethod('bank', <?php echo $acc['id']; ?>)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- PayPal Tab -->
                            <div class="tab-pane fade" id="paypal" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0">PayPal Accounts</h6>
                                    <button class="btn btn-sm btn-gradient rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addPayPalModal">
                                        <i class="fas fa-plus me-1"></i> Add New
                                    </button>
                                </div>
                                <?php if (empty($paypal_accounts)): ?>
                                    <div class="text-center py-5">
                                        <i class="fab fa-paypal fa-4x text-muted opacity-25 mb-3"></i>
                                        <h6 class="text-muted mb-2">No PayPal Accounts Added</h6>
                                        <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addPayPalModal">
                                            <i class="fas fa-plus me-2"></i>Add PayPal
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($paypal_accounts as $acc): ?>
                                        <div class="account-card <?php echo !empty($acc['is_default']) ? 'default' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($acc['paypal_email']); ?></h6>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($acc['account_holder_name']); ?>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <?php if (!empty($acc['is_default'])): ?>
                                                        <span class="badge bg-success mb-2">Default</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <?php if (!empty($acc['is_verified'])): ?>
                                                        <span class="badge bg-info">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <?php if (empty($acc['is_default']) && !empty($acc['is_verified'])): ?>
                                                    <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="setDefault('paypal', <?php echo $acc['id']; ?>)">
                                                        <i class="fas fa-check me-1"></i>Set Default
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="deleteMethod('paypal', <?php echo $acc['id']; ?>)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Stripe Tab -->
                            <div class="tab-pane fade" id="stripe" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0">Stripe Accounts</h6>
                                    <button class="btn btn-sm btn-gradient rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addStripeModal">
                                        <i class="fas fa-plus me-1"></i> Connect New
                                    </button>
                                </div>
                                <?php if (empty($stripe_accounts)): ?>
                                    <div class="text-center py-5">
                                        <i class="fab fa-stripe fa-4x text-muted opacity-25 mb-3"></i>
                                        <h6 class="text-muted mb-2">No Stripe Accounts Connected</h6>
                                        <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addStripeModal">
                                            <i class="fas fa-plus me-2"></i>Connect Stripe
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($stripe_accounts as $acc): ?>
                                        <div class="account-card <?php echo !empty($acc['is_default']) ? 'default' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fab fa-stripe text-primary me-2" style="font-size: 20px;"></i>
                                                        <h6 class="fw-bold mb-0">Stripe Account</h6>
                                                        <?php if (!empty($acc['is_default'])): ?>
                                                            <span class="badge bg-success ms-2">Default</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($acc['is_verified'])): ?>
                                                            <span class="badge bg-info ms-2">Verified</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning ms-2">Pending</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-envelope me-1 text-muted"></i><?php echo htmlspecialchars($acc['account_email']); ?><br>
                                                        <i class="fas fa-id-card me-1 text-muted"></i>ID: <?php echo substr($acc['stripe_account_id'], 0, 8); ?>...<br>
                                                        <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($acc['account_holder_name']); ?>
                                                        <?php if (!empty($acc['stripe_publishable_key'])): ?>
                                                            <br><i class="fas fa-key me-1 text-muted"></i>PK: <?php echo substr($acc['stripe_publishable_key'], 0, 8); ?>...
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <?php if (empty($acc['is_default']) && !empty($acc['is_verified'])): ?>
                                                    <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="setDefault('stripe', <?php echo $acc['id']; ?>)">
                                                        <i class="fas fa-check me-1"></i>Set Default
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="deleteMethod('stripe', <?php echo $acc['id']; ?>)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Easypaisa Tab -->
                            <div class="tab-pane fade" id="easypaisa" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0">Easypaisa Accounts</h6>
                                    <button class="btn btn-sm btn-gradient rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addEasypaisaModal">
                                        <i class="fas fa-plus me-1"></i> Add New
                                    </button>
                                </div>
                                <?php if (empty($easypaisa_accounts)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-mobile-alt fa-4x text-muted opacity-25 mb-3"></i>
                                        <h6 class="text-muted mb-2">No Easypaisa Accounts Added</h6>
                                        <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addEasypaisaModal">
                                            <i class="fas fa-plus me-2"></i>Add Easypaisa
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($easypaisa_accounts as $acc): ?>
                                        <div class="account-card">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold mb-1">****<?php echo substr($acc['mobile_number'], -4); ?></h6>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($acc['account_holder_name']); ?><br>
                                                        <?php if (!empty($acc['cnic_number'])): ?>
                                                            <i class="fas fa-id-card me-1 text-muted"></i><?php echo $acc['cnic_number']; ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div>
                                                    <?php if (!empty($acc['is_verified'])): ?>
                                                        <span class="badge bg-info">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="deleteMethod('easypaisa', <?php echo $acc['id']; ?>)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- JazzCash Tab -->
                            <div class="tab-pane fade" id="jazzcash" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0">JazzCash Accounts</h6>
                                    <button class="btn btn-sm btn-gradient rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addJazzCashModal">
                                        <i class="fas fa-plus me-1"></i> Add New
                                    </button>
                                </div>
                                <?php if (empty($jazzcash_accounts)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-mobile-alt fa-4x text-muted opacity-25 mb-3"></i>
                                        <h6 class="text-muted mb-2">No JazzCash Accounts Added</h6>
                                        <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addJazzCashModal">
                                            <i class="fas fa-plus me-2"></i>Add JazzCash
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($jazzcash_accounts as $acc): ?>
                                        <div class="account-card">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold mb-1">****<?php echo substr($acc['mobile_number'], -4); ?></h6>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($acc['account_holder_name']); ?><br>
                                                        <?php if (!empty($acc['cnic_number'])): ?>
                                                            <i class="fas fa-id-card me-1 text-muted"></i><?php echo $acc['cnic_number']; ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div>
                                                    <?php if (!empty($acc['is_verified'])): ?>
                                                        <span class="badge bg-info">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="deleteMethod('jazzcash', <?php echo $acc['id']; ?>)">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Cards Tab (Combined Cards & Stripe) -->
                            <div class="tab-pane fade" id="cards" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0">Credit/Debit Cards & Stripe</h6>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-gradient rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addCardModal">
                                            <i class="fas fa-plus me-1"></i> Add Card
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addStripeModal">
                                            <i class="fab fa-stripe me-1"></i> Add Stripe
                                        </button>
                                    </div>
                                </div>
                                
                                <?php if (empty($cards) && empty($stripe_accounts)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-credit-card fa-4x text-muted opacity-25 mb-3"></i>
                                        <h6 class="text-muted mb-2">No Cards or Stripe Accounts Added</h6>
                                        <p class="text-muted small mb-3">Add a credit/debit card or connect your Stripe account</p>
                                        <div class="d-flex justify-content-center gap-2">
                                            <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addCardModal">
                                                <i class="fas fa-plus me-2"></i>Add Card
                                            </button>
                                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStripeModal">
                                                <i class="fab fa-stripe me-2"></i>Connect Stripe
                                            </button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- First show Stripe Accounts -->
                                    <?php if (!empty($stripe_accounts)): ?>
                                        <h6 class="fw-bold mt-3 mb-2">Stripe Accounts</h6>
                                        <?php foreach ($stripe_accounts as $acc): ?>
                                            <div class="account-card <?php echo !empty($acc['is_default']) ? 'default' : ''; ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="fab fa-stripe text-primary me-2" style="font-size: 20px;"></i>
                                                            <h6 class="fw-bold mb-0">Stripe Account</h6>
                                                            <?php if (!empty($acc['is_default'])): ?>
                                                                <span class="badge bg-success ms-2">Default</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($acc['is_verified'])): ?>
                                                                <span class="badge bg-info ms-2">Verified</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning ms-2">Pending</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="mb-1 small">
                                                            <i class="fas fa-envelope me-1 text-muted"></i><?php echo htmlspecialchars($acc['account_email']); ?><br>
                                                            <i class="fas fa-id-card me-1 text-muted"></i>ID: <?php echo substr($acc['stripe_account_id'], 0, 8); ?>...<br>
                                                            <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($acc['account_holder_name']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-2 mt-3">
                                                    <?php if (empty($acc['is_default']) && !empty($acc['is_verified'])): ?>
                                                        <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="setDefault('stripe', <?php echo $acc['id']; ?>)">
                                                            <i class="fas fa-check me-1"></i>Set Default
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="deleteMethod('stripe', <?php echo $acc['id']; ?>)">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- Then show Regular Cards -->
                                    <?php if (!empty($cards)): ?>
                                        <h6 class="fw-bold mt-4 mb-2">Credit/Debit Cards</h6>
                                        <?php foreach ($cards as $card): ?>
                                            <div class="account-card <?php echo !empty($card['is_default']) ? 'default' : ''; ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <div class="d-flex align-items-center mb-2">
                                                            <?php 
                                                            $card_icons = [
                                                                'visa' => 'fab fa-cc-visa',
                                                                'mastercard' => 'fab fa-cc-mastercard',
                                                                'amex' => 'fab fa-cc-amex'
                                                            ];
                                                            $icon = $card_icons[$card['card_type']] ?? 'fas fa-credit-card';
                                                            ?>
                                                            <i class="<?php echo $icon; ?> me-2" style="font-size: 20px; color: #667eea;"></i>
                                                            <h6 class="fw-bold mb-0">
                                                                <?php echo ucfirst($card['card_type']); ?> 
                                                                **** **** **** <?php echo $card['card_last_four']; ?>
                                                            </h6>
                                                            <?php if (!empty($card['is_default'])): ?>
                                                                <span class="badge bg-success ms-2">Default</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($card['is_verified'])): ?>
                                                                <span class="badge bg-info ms-2">Verified</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning ms-2">Pending</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="mb-1 small">
                                                            <i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($card['card_holder_name']); ?><br>
                                                            <i class="fas fa-calendar me-1 text-muted"></i>Expires: <?php echo $card['expiry_month']; ?>/<?php echo $card['expiry_year']; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-2 mt-3">
                                                    <?php if (empty($card['is_default']) && !empty($card['is_verified'])): ?>
                                                        <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="setDefault('card', <?php echo $card['id']; ?>)">
                                                            <i class="fas fa-check me-1"></i>Set Default
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="deleteMethod('card', <?php echo $card['id']; ?>)">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="alert alert-info bg-info bg-opacity-10 border-0 rounded-15 mt-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-shield-alt fa-2x text-info me-3"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Security Notice</h6>
                                    <p class="small text-muted mb-0">
                                        All payment methods must be verified before withdrawal. Verification typically takes 24-48 hours.
                                        We only store the last 4 digits of your accounts for security.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Withdrawal History & Info -->
            <div class="col-lg-5">
                <!-- Withdrawal History Card -->
                <div class="card border-0 shadow-sm rounded-20 mb-4 animate-slide-in">
                    <div class="card-header bg-white border-0 p-4">
                        <h5 class="mb-0 fw-bold">
                            <span class="bg-primary bg-opacity-10 rounded-15 p-2 me-2">
                                <i class="fas fa-history text-primary"></i>
                            </span>
                            Recent Withdrawals
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <?php if (empty($withdrawals)): ?>
                            <div class="text-center py-5">
                                <div class="mb-3">
                                    <i class="fas fa-history fa-4x text-muted opacity-25"></i>
                                </div>
                                <h6 class="text-muted">No withdrawal history yet</h6>
                                <p class="text-muted small">Your withdrawal requests will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($withdrawals as $w): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="fw-bold fs-5"><?php echo formatCurrency($w['withdrawal_amount']); ?></span>
                                                <?php echo getStatusBadge($w['status']); ?>
                                            </div>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($w['created_at'])); ?></small>
                                        </div>
                                        <p class="small text-muted mb-1">
                                            <i class="fas fa-<?php 
                                                echo $w['withdrawal_method'] == 'bank' ? 'university' : 
                                                    ($w['withdrawal_method'] == 'paypal' ? 'paypal' : 
                                                    ($w['withdrawal_method'] == 'stripe' ? 'stripe' : 
                                                    (in_array($w['withdrawal_method'], ['easypaisa', 'jazzcash']) ? 'mobile-alt' : 'credit-card'))); 
                                            ?> me-1"></i>
                                            <?php echo ucfirst($w['withdrawal_method']); ?>
                                            <?php if ($w['status'] == 'completed' && !empty($w['transaction_id'])): ?>
                                                <br><span class="text-muted">TXID: <?php echo substr($w['transaction_id'], 0, 12); ?>...</span>
                                            <?php endif; ?>
                                            <?php if ($w['status'] == 'rejected' && !empty($w['notes'])): ?>
                                                <br><span class="text-danger">Reason: <?php echo htmlspecialchars($w['notes']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($withdrawals) >= 20): ?>
                                <div class="text-center mt-3">
                                    <a href="withdrawal-history.php" class="btn btn-link text-primary">View All History →</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats Card -->
                <div class="card border-0 shadow-sm rounded-20 mb-4 animate-slide-in">
                    <div class="card-header bg-white border-0 p-4">
                        <h5 class="mb-0 fw-bold">
                            <span class="bg-primary bg-opacity-10 rounded-15 p-2 me-2">
                                <i class="fas fa-chart-pie text-primary"></i>
                            </span>
                            Quick Stats
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <?php
                        $total_withdrawn = 0;
                        $completed_count = 0;
                        foreach ($withdrawals as $w) {
                            if ($w['status'] == 'completed') {
                                $total_withdrawn += $w['withdrawal_amount'];
                                $completed_count++;
                            }
                        }
                        $avg_withdrawal = $completed_count > 0 ? $total_withdrawn / $completed_count : 0;
                        $pending_count = count(array_filter($withdrawals, function($w) { return $w['status'] == 'pending'; }));
                        ?>
                        
                        <div class="row g-4 text-center">
                            <div class="col-4">
                                <div class="p-3 bg-primary bg-opacity-10 rounded-15">
                                    <h4 class="fw-bold text-primary mb-1"><?php echo count($withdrawals); ?></h4>
                                    <small class="text-muted">Total Requests</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-success bg-opacity-10 rounded-15">
                                    <h4 class="fw-bold text-success mb-1"><?php echo formatCurrency($total_withdrawn); ?></h4>
                                    <small class="text-muted">Total Withdrawn</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-info bg-opacity-10 rounded-15">
                                    <h4 class="fw-bold text-info mb-1"><?php echo formatCurrency($avg_withdrawal); ?></h4>
                                    <small class="text-muted">Average</small>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Pending Requests</span>
                                <span class="fw-bold"><?php echo $pending_count; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo count($withdrawals) > 0 ? ($pending_count / count($withdrawals)) * 100 : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal Info Card -->
                <div class="card border-0 shadow-sm rounded-20 animate-slide-in">
                    <div class="card-header bg-white border-0 p-4">
                        <h5 class="mb-0 fw-bold">
                            <span class="bg-primary bg-opacity-10 rounded-15 p-2 me-2">
                                <i class="fas fa-info-circle text-primary"></i>
                            </span>
                            Withdrawal Information
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 border-0 d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-check text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Minimum Amount</h6>
                                    <small class="text-muted"><?php echo formatCurrency($min_withdrawal); ?> per withdrawal</small>
                                </div>
                            </div>
                            <div class="list-group-item px-0 border-0 d-flex align-items-center">
                                <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-clock text-success"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Processing Time</h6>
                                    <small class="text-muted">3-5 business days after approval</small>
                                </div>
                            </div>
                            <div class="list-group-item px-0 border-0 d-flex align-items-center">
                                <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-shield-alt text-info"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Verification Required</h6>
                                    <small class="text-muted">All payment methods must be verified</small>
                                </div>
                            </div>
                            <div class="list-group-item px-0 border-0 d-flex align-items-center">
                                <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-clock text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Cut-off Time</h6>
                                    <small class="text-muted">Requests before 2 PM processed same day</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning bg-warning bg-opacity-10 border-0 rounded-15 mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <small>Please ensure all account details are correct. Incorrect details may delay your withdrawal.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modals -->
<?php include 'modals.php'; ?>

<!-- JavaScript Functions -->
<script>
// Global variables
let currentMethod = 'bank';
let csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

// Set max amount
function setMaxAmount() {
    const maxAmount = <?php echo $available_balance; ?>;
    document.getElementById('withdrawalAmount').value = maxAmount.toFixed(2);
}

// Select payment method
function selectMethod(method) {
    currentMethod = method;
    
    // Update UI
    document.querySelectorAll('.method-option').forEach(el => {
        el.classList.remove('active');
    });
    document.querySelector(`.${method}-option`).classList.add('active');
    
    // Hide all fields
    document.querySelectorAll('.method-fields').forEach(el => {
        el.classList.add('d-none');
    });
    
    // Show selected field
    if (method === 'bank') {
        document.getElementById('bankField').classList.remove('d-none');
    } else if (method === 'paypal') {
        document.getElementById('paypalField').classList.remove('d-none');
    } else if (method === 'stripe') {
        document.getElementById('stripeField').classList.remove('d-none');
    } else if (method === 'easypaisa' || method === 'jazzcash') {
        document.getElementById('mobileField').classList.remove('d-none');
        updateMobileOptions(method);
    } else if (method === 'cards') {
        document.getElementById('cardField').classList.remove('d-none');
    }
}

// Update mobile options based on type
function updateMobileOptions(type) {
    const select = document.getElementById('mobileSelect');
    select.innerHTML = '<option value="">Choose account</option>';
    
    <?php 
    // Easypaisa options
    foreach ($easypaisa_accounts as $acc): 
        if (!empty($acc['is_verified'])): 
    ?>
        if (type === 'easypaisa') {
            select.innerHTML += `<option value="<?php echo $acc['id']; ?>" <?php echo !empty($acc['is_default']) ? 'selected' : ''; ?>>
                ****<?php echo substr($acc['mobile_number'], -4); ?> - <?php echo htmlspecialchars($acc['account_holder_name']); ?>
            </option>`;
        }
    <?php 
        endif; 
    endforeach; 
    
    // JazzCash options
    foreach ($jazzcash_accounts as $acc): 
        if (!empty($acc['is_verified'])): 
    ?>
        if (type === 'jazzcash') {
            select.innerHTML += `<option value="<?php echo $acc['id']; ?>" <?php echo !empty($acc['is_default']) ? 'selected' : ''; ?>>
                ****<?php echo substr($acc['mobile_number'], -4); ?> - <?php echo htmlspecialchars($acc['account_holder_name']); ?>
            </option>`;
        }
    <?php 
        endif; 
    endforeach; 
    ?>
    
    document.getElementById('mobileFieldLabel').textContent = 
        type === 'easypaisa' ? 'Select Easypaisa Account' : 'Select JazzCash Account';
}

// Show method tab from quick access
function showMethod(method) {
    const tabMap = {
        'bank': '#bank-tab',
        'paypal': '#paypal-tab',
        'stripe': '#stripe-tab',
        'easypaisa': '#easypaisa-tab',
        'jazzcash': '#jazzcash-tab',
        'cards': '#cards-tab'
    };
    
    if (tabMap[method]) {
        const tab = new bootstrap.Tab(document.querySelector(tabMap[method]));
        tab.show();
    }
}

// Set default account
function setDefault(type, id) {
    if (!confirm('Set this as your default payment method?')) return;
    
    fetch('action/set-default.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `type=${type}&id=${id}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Default method updated');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Network error occurred');
    });
}

// Delete payment method
function deleteMethod(type, id) {
    if (!confirm('Are you sure you want to delete this payment method? This action cannot be undone.')) return;
    
    fetch('action/delete-payment-method.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `type=${type}&id=${id}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Payment method deleted');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Network error occurred');
    });
}

// Show notification
function showNotification(type, message) {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toastId = 'toast-' + Date.now();
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        new bootstrap.Toast(toast, { autohide: true, delay: 3000 }).show();
    } else {
        toast.style.display = 'block';
        setTimeout(() => toast.remove(), 3000);
    }
    
    setTimeout(() => toast.remove(), 3500);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Form submission handler
document.getElementById('withdrawalForm')?.addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
    }
});

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            } else {
                alert.style.display = 'none';
            }
        } catch(e) {}
    });
}, 5000);

// Card number formatting and validation
document.addEventListener('DOMContentLoaded', function() {
    // Set default method
    selectMethod('bank');
    
    // Add card number formatting
    const cardNumberInput = document.querySelector('input[name="card_number"]');
    const cardTypeSelect = document.querySelector('select[name="card_type"]');
    const cvvInput = document.querySelector('input[name="cvv"]');
    
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = this.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = '';
            
            // Format based on card type or length
            if (cardTypeSelect && cardTypeSelect.value === 'amex') {
                // American Express: 4-6-5 format
                for (let i = 0; i < value.length; i++) {
                    if (i == 4 || i == 10) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
            } else {
                // Visa/Mastercard: 4-4-4-4 format
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formattedValue += ' ';
                    }
                    formattedValue += value[i];
                }
            }
            
            this.value = formattedValue.trim();
            
            // Update card type based on number
            if (value.length >= 4) {
                const firstDigit = value.charAt(0);
                if (firstDigit === '4') {
                    if (cardTypeSelect) cardTypeSelect.value = 'visa';
                } else if (firstDigit === '5') {
                    if (cardTypeSelect) cardTypeSelect.value = 'mastercard';
                } else if (firstDigit === '3' && (value.charAt(1) === '4' || value.charAt(1) === '7')) {
                    if (cardTypeSelect) cardTypeSelect.value = 'amex';
                }
            }
        });
    }
    
    // CVV formatting
    if (cvvInput) {
        cvvInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 4);
        });
    }
    
    // Update CVV maxlength based on card type
    if (cardTypeSelect && cvvInput) {
        cardTypeSelect.addEventListener('change', function() {
            if (this.value === 'amex') {
                cvvInput.maxLength = 4;
                cvvInput.placeholder = '4 digits';
            } else {
                cvvInput.maxLength = 3;
                cvvInput.placeholder = '3 digits';
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>