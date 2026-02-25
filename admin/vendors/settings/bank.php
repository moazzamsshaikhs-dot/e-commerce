<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}
// error reporting on
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all errors to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

$page_title = 'Bank & Payment Settings';
require_once '../../includes/header.php';

// Initialize variables
$vendor = [];
$countries = [];
$payment_methods = [];
$bank_accounts = [];
$mobile_accounts = [];
$cards = [];
$withdrawal_methods = [];
$earnings = ['total_earnings' => 0, 'paid_earnings' => 0, 'pending_earnings' => 0];
$withdrawals = [];

// Get vendor details and payment methods
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor basic info and country
    $stmt = $db->prepare("
        SELECT u.full_name, u.country, vs.store_name 
        FROM users u
        LEFT JOIN vendor_settings vs ON u.id = vs.vendor_id
        WHERE u.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Get countries list
    $stmt = $db->prepare("SELECT * FROM countries WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $countries = $stmt->fetchAll();
    
    // Get bank accounts
    $stmt = $db->prepare("
        SELECT * FROM vendor_bank_accounts 
        WHERE vendor_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $bank_accounts = $stmt->fetchAll();
    
    // Get mobile accounts (Easypaisa/JazzCash)
    $stmt = $db->prepare("
        SELECT * FROM vendor_mobile_accounts 
        WHERE vendor_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $mobile_accounts = $stmt->fetchAll();
    
    // Get cards
    $stmt = $db->prepare("
        SELECT * FROM vendor_cards 
        WHERE vendor_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $cards = $stmt->fetchAll();
    
    // Get all payment methods for this vendor
    $stmt = $db->prepare("
        SELECT * FROM vendor_payment_methods 
        WHERE vendor_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $payment_methods = $stmt->fetchAll();
    
    // Get detailed payment method info
    foreach ($payment_methods as &$method) {
        $method['details'] = getPaymentMethodDetails($db, $method['id'], $method['method_type']);
    }
    
    // Get withdrawal methods
    $stmt = $db->prepare("
        SELECT * FROM withdrawal_methods 
        WHERE is_active = 1 
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $withdrawal_methods = $stmt->fetchAll();
    
    // Get earnings summary
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(vendor_amount), 0) as total_earnings,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN vendor_amount ELSE 0 END), 0) as paid_earnings,
            COALESCE(SUM(CASE WHEN status IN ('pending', 'processing') THEN vendor_amount ELSE 0 END), 0) as pending_earnings
        FROM vendor_earnings 
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $earnings = $stmt->fetch();
    
    // Get withdrawal history
    $stmt = $db->prepare("
        SELECT * FROM vendor_withdrawals 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$vendor_id]);
    $withdrawals = $stmt->fetchAll();
    
    // Get bank formats for vendor's country
    if (!empty($vendor['country'])) {
        $stmt = $db->prepare("SELECT bank_format FROM countries WHERE code = ?");
        $stmt->execute([$vendor['country']]);
        $bank_format_json = $stmt->fetchColumn();
        $bank_format = $bank_format_json ? json_decode($bank_format_json, true) : null;
    } else {
        $bank_format = null;
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    error_log("Bank page error: " . $e->getMessage());
}

// Helper function to get payment method details
function getPaymentMethodDetails($db, $method_id, $method_type) {
    try {
        switch($method_type) {
            case 'bank':
                $stmt = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE payment_method_id = ?");
                $stmt->execute([$method_id]);
                return $stmt->fetch();
            case 'paypal':
                $stmt = $db->prepare("SELECT * FROM vendor_paypal_accounts WHERE payment_method_id = ?");
                $stmt->execute([$method_id]);
                return $stmt->fetch();
            case 'stripe':
                $stmt = $db->prepare("SELECT * FROM vendor_stripe_accounts WHERE payment_method_id = ?");
                $stmt->execute([$method_id]);
                return $stmt->fetch();
            case 'easypaisa':
            case 'jazzcash':
                $stmt = $db->prepare("SELECT * FROM vendor_mobile_accounts WHERE payment_method_id = ?");
                $stmt->execute([$method_id]);
                return $stmt->fetch();
            case 'visa':
            case 'mastercard':
            case 'amex':
                $stmt = $db->prepare("SELECT * FROM vendor_cards WHERE payment_method_id = ?");
                $stmt->execute([$method_id]);
                return $stmt->fetch();
            default:
                return null;
        }
    } catch(Exception $e) {
        return null;
    }
}

// Helper functions
function getCountryName($country_code) {
    if (empty($country_code)) return 'Not Set';
    
    $countries = [
        'US' => 'United States', 'GB' => 'United Kingdom', 'PK' => 'Pakistan',
        'IN' => 'India', 'AE' => 'UAE', 'SA' => 'Saudi Arabia',
        'CA' => 'Canada', 'AU' => 'Australia', 'DE' => 'Germany',
        'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain',
        'NL' => 'Netherlands', 'BE' => 'Belgium', 'CH' => 'Switzerland',
        'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
        'JP' => 'Japan', 'CN' => 'China', 'BR' => 'Brazil',
        'ZA' => 'South Africa', 'NG' => 'Nigeria', 'RU' => 'Russia',
        'TR' => 'Turkey', 'ID' => 'Indonesia', 'MY' => 'Malaysia',
        'SG' => 'Singapore', 'BD' => 'Bangladesh'
    ];
    return $countries[$country_code] ?? $country_code;
}

function getCurrencySymbol($country_code) {
    $currencies = [
        'US' => '$', 'GB' => '£', 'PK' => '₨', 'IN' => '₹',
        'AE' => 'د.إ', 'SA' => '﷼', 'CA' => 'C$', 'AU' => 'A$',
        'DE' => '€', 'FR' => '€', 'IT' => '€', 'ES' => '€',
        'NL' => '€', 'BE' => '€', 'CH' => 'Fr', 'SE' => 'kr',
        'NO' => 'kr', 'DK' => 'kr', 'JP' => '¥', 'CN' => '¥',
        'BR' => 'R$', 'ZA' => 'R', 'NG' => '₦', 'RU' => '₽',
        'TR' => '₺', 'ID' => 'Rp', 'MY' => 'RM', 'SG' => 'S$',
        'BD' => '৳'
    ];
    return $currencies[$country_code] ?? '$';
}

function getMethodIcon($method_type) {
    $icons = [
        'bank' => 'university',
        'paypal' => 'paypal',
        'stripe' => 'stripe',
        'easypaisa' => 'mobile-alt',
        'jazzcash' => 'mobile-alt',
        'visa' => 'credit-card',
        'mastercard' => 'credit-card',
        'amex' => 'credit-card'
    ];
    return $icons[$method_type] ?? 'money-bill';
}

function getMethodName($method_type) {
    $names = [
        'bank' => 'Bank Account',
        'paypal' => 'PayPal',
        'stripe' => 'Stripe',
        'easypaisa' => 'Easypaisa',
        'jazzcash' => 'JazzCash',
        'visa' => 'Visa Card',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express'
    ];
    return $names[$method_type] ?? ucfirst($method_type);
}

function getMethodDisplay($details, $method_type) {
    if (!$details) return 'Details not available';
    
    switch($method_type) {
        case 'bank':
            return ($details['bank_name'] ?? 'Bank') . ' - ****' . substr($details['account_number'] ?? '', -4);
        case 'paypal':
            return $details['paypal_email'] ?? '';
        case 'stripe':
            return 'Stripe: ' . ($details['stripe_account_id'] ?? '');
        case 'easypaisa':
        case 'jazzcash':
            return '****' . substr($details['mobile_number'] ?? '', -4);
        case 'visa':
        case 'mastercard':
        case 'amex':
            return '**** **** **** ' . ($details['card_last_four'] ?? '');
        default:
            return '';
    }
}

// Store CSRF token in variable for JavaScript
$js_csrf_token = $_SESSION['csrf_token'];
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Bank & Payment Settings</h1>
                <p class="text-muted mb-0">Manage your payment methods and withdrawals</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Earnings Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Total Earnings</h6>
                                <h3 class="fw-bold text-success">$<?php echo number_format($earnings['total_earnings'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-money-bill-wave text-success fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Paid Earnings</h6>
                                <h3 class="fw-bold text-primary">$<?php echo number_format($earnings['paid_earnings'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-check-circle text-primary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Pending Earnings</h6>
                                <h3 class="fw-bold text-warning">$<?php echo number_format($earnings['pending_earnings'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-clock text-warning fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Country Selection Alert -->
        <?php if (empty($vendor['country'])): ?>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Please <a href="../profile.php" class="alert-link">update your profile</a> with your country to see country-specific payment options.
        </div>
        <?php else: ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            Showing payment methods for <strong><?php echo getCountryName($vendor['country']); ?></strong> 
            (<?php echo getCurrencySymbol($vendor['country']); ?>)
        </div>
        <?php endif; ?>

        <!-- Payment Methods Tabs -->
        <ul class="nav nav-pills mb-4" id="paymentMethodTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="pill" data-bs-target="#all" type="button">
                    <i class="fas fa-list me-2"></i> All Methods
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="bank-tab" data-bs-toggle="pill" data-bs-target="#bank" type="button">
                    <i class="fas fa-university me-2"></i> Bank Accounts
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="paypal-tab" data-bs-toggle="pill" data-bs-target="#paypal" type="button">
                    <i class="fab fa-paypal me-2"></i> PayPal
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stripe-tab" data-bs-toggle="pill" data-bs-target="#stripe" type="button">
                    <i class="fab fa-stripe me-2"></i> Stripe
                </button>
            </li>
            <?php if (!empty($vendor['country']) && $vendor['country'] === 'PK'): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="easypaisa-tab" data-bs-toggle="pill" data-bs-target="#easypaisa" type="button">
                    <i class="fas fa-mobile-alt me-2"></i> Easypaisa
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="jazzcash-tab" data-bs-toggle="pill" data-bs-target="#jazzcash" type="button">
                    <i class="fas fa-mobile-alt me-2"></i> JazzCash
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cards-tab" data-bs-toggle="pill" data-bs-target="#cards" type="button">
                    <i class="fas fa-credit-card me-2"></i> Cards
                </button>
            </li>
        </ul>

        <div class="tab-content" id="paymentMethodTabContent">
            <!-- All Methods Tab -->
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <div class="row">
                    <?php 
                    // Combine all payment methods for display
                    $all_methods = [];
                    
                    foreach($bank_accounts as $account) {
                        $all_methods[] = [
                            'id' => $account['id'],
                            'method_type' => 'bank',
                            'is_default' => $account['is_default'] ?? 0,
                            'is_verified' => $account['is_verified'] ?? 0,
                            'details' => $account,
                            'source' => 'old'
                        ];
                    }
                    
                    foreach($mobile_accounts as $account) {
                        $all_methods[] = [
                            'id' => $account['id'],
                            'method_type' => $account['account_type'] ?? 'mobile',
                            'is_default' => $account['is_default'] ?? 0,
                            'is_verified' => $account['is_verified'] ?? 0,
                            'details' => $account,
                            'source' => 'old'
                        ];
                    }
                    
                    foreach($cards as $card) {
                        $all_methods[] = [
                            'id' => $card['id'],
                            'method_type' => $card['card_type'] ?? 'card',
                            'is_default' => $card['is_default'] ?? 0,
                            'is_verified' => $card['is_verified'] ?? 0,
                            'details' => $card,
                            'source' => 'old'
                        ];
                    }
                    
                    foreach($payment_methods as $method) {
                        $all_methods[] = $method;
                    }
                    
                    if (!empty($all_methods)): 
                        foreach($all_methods as $method): 
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card payment-method-card <?php echo ($method['is_default'] ?? 0) ? 'border-success' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-<?php echo getMethodIcon($method['method_type']); ?> me-2"></i>
                                            <?php echo getMethodName($method['method_type']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo getMethodDisplay($method['details'], $method['method_type']); ?>
                                        </small>
                                    </div>
                                    <?php if ($method['is_default'] ?? 0): ?>
                                    <span class="badge bg-success">Default</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2">
                                    <?php if ($method['is_verified'] ?? 0): ?>
                                    <span class="badge bg-success">Verified</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3 btn-group btn-group-sm w-100">
                                    <button class="btn btn-outline-primary" onclick="editPaymentMethod(<?php echo $method['id']; ?>, '<?php echo $method['method_type']; ?>', '<?php echo $method['source'] ?? 'new'; ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if (!($method['is_default'] ?? 0)): ?>
                                    <button class="btn btn-outline-success" onclick="setDefaultMethod(<?php echo $method['id']; ?>, '<?php echo $method['method_type']; ?>', '<?php echo $method['source'] ?? 'new'; ?>')">
                                        <i class="fas fa-check"></i> Default
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deletePaymentMethod(<?php echo $method['id']; ?>, '<?php echo $method['method_type']; ?>', '<?php echo $method['source'] ?? 'new'; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No Payment Methods Added</h5>
                        <p class="text-muted">Add your first payment method using the tabs above</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bank Accounts Tab -->
            <div class="tab-pane fade" id="bank" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Bank Accounts</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBankModal">
                            <i class="fas fa-plus me-2"></i> Add Bank Account
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($bank_accounts)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-university fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No bank accounts added yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Bank Name</th>
                                        <th>Account Holder</th>
                                        <th>Account Number</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($bank_accounts as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($account['bank_name']); ?></td>
                                        <td><?php echo htmlspecialchars($account['account_holder_name']); ?></td>
                                        <td>****<?php echo substr($account['account_number'] ?? '', -4); ?></td>
                                        <td><?php echo ucfirst($account['account_type'] ?? 'savings'); ?></td>
                                        <td>
                                            <?php if ($account['is_verified'] ?? 0): ?>
                                            <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editPaymentMethod(<?php echo $account['id']; ?>, 'bank', 'old')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!($account['is_default'] ?? 0)): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletePaymentMethod(<?php echo $account['id']; ?>, 'bank', 'old')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- PayPal Tab -->
            <div class="tab-pane fade" id="paypal" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">PayPal Accounts</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPayPalModal">
                            <i class="fas fa-plus me-2"></i> Add PayPal
                        </button>
                    </div>
                    <div class="card-body">
                        <?php 
                        $paypal_methods = array_filter($payment_methods, function($m) { return $m['method_type'] === 'paypal'; });
                        if (empty($paypal_methods)): 
                        ?>
                        <div class="text-center py-4">
                            <i class="fab fa-paypal fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No PayPal accounts added yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PayPal Email</th>
                                        <th>Account Holder</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($paypal_methods as $method): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($method['details']['paypal_email'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($method['details']['account_holder_name'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($method['is_verified'] ?? 0): ?>
                                            <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editPaymentMethod(<?php echo $method['id']; ?>, 'paypal', 'new')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!($method['is_default'] ?? 0)): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletePaymentMethod(<?php echo $method['id']; ?>, 'paypal', 'new')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stripe Tab -->
            <div class="tab-pane fade" id="stripe" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Stripe Accounts</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStripeModal">
                            <i class="fas fa-plus me-2"></i> Connect Stripe
                        </button>
                    </div>
                    <div class="card-body">
                        <?php 
                        $stripe_methods = array_filter($payment_methods, function($m) { return $m['method_type'] === 'stripe'; });
                        if (empty($stripe_methods)): 
                        ?>
                        <div class="text-center py-4">
                            <i class="fab fa-stripe fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No Stripe accounts connected yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Account ID</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($stripe_methods as $method): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($method['details']['stripe_account_id'] ?? '', 0, 8) . '...'); ?></td>
                                        <td><?php echo htmlspecialchars($method['details']['account_email'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($method['is_verified'] ?? 0): ?>
                                            <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editPaymentMethod(<?php echo $method['id']; ?>, 'stripe', 'new')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!($method['is_default'] ?? 0)): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletePaymentMethod(<?php echo $method['id']; ?>, 'stripe', 'new')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Easypaisa Tab -->
            <?php if (!empty($vendor['country']) && $vendor['country'] === 'PK'): ?>
            <div class="tab-pane fade" id="easypaisa" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Easypaisa Accounts</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEasypaisaModal">
                            <i class="fas fa-plus me-2"></i> Add Easypaisa
                        </button>
                    </div>
                    <div class="card-body">
                        <?php 
                        $easypaisa_methods = array_filter($mobile_accounts, function($a) { return $a['account_type'] === 'easypaisa'; });
                        if (empty($easypaisa_methods)): 
                        ?>
                        <div class="text-center py-4">
                            <i class="fas fa-mobile-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No Easypaisa accounts added yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mobile Number</th>
                                        <th>Account Holder</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($easypaisa_methods as $account): ?>
                                    <tr>
                                        <td>****<?php echo substr($account['mobile_number'] ?? '', -4); ?></td>
                                        <td><?php echo htmlspecialchars($account['account_holder_name'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($account['is_verified'] ?? 0): ?>
                                            <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editPaymentMethod(<?php echo $account['id']; ?>, 'easypaisa', 'old')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!($account['is_default'] ?? 0)): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletePaymentMethod(<?php echo $account['id']; ?>, 'easypaisa', 'old')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- JazzCash Tab -->
            <div class="tab-pane fade" id="jazzcash" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">JazzCash Accounts</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addJazzCashModal">
                            <i class="fas fa-plus me-2"></i> Add JazzCash
                        </button>
                    </div>
                    <div class="card-body">
                        <?php 
                        $jazzcash_methods = array_filter($mobile_accounts, function($a) { return $a['account_type'] === 'jazzcash'; });
                        if (empty($jazzcash_methods)): 
                        ?>
                        <div class="text-center py-4">
                            <i class="fas fa-mobile-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No JazzCash accounts added yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mobile Number</th>
                                        <th>Account Holder</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($jazzcash_methods as $account): ?>
                                    <tr>
                                        <td>****<?php echo substr($account['mobile_number'] ?? '', -4); ?></td>
                                        <td><?php echo htmlspecialchars($account['account_holder_name'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($account['is_verified'] ?? 0): ?>
                                            <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editPaymentMethod(<?php echo $account['id']; ?>, 'jazzcash', 'old')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!($account['is_default'] ?? 0)): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletePaymentMethod(<?php echo $account['id']; ?>, 'jazzcash', 'old')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Cards Tab -->
            <div class="tab-pane fade" id="cards" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Credit/Debit Cards</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCardModal">
                            <i class="fas fa-plus me-2"></i> Add Card
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cards)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No cards added yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Card Type</th>
                                        <th>Card Number</th>
                                        <th>Card Holder</th>
                                        <th>Expiry</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($cards as $card): ?>
                                    <tr>
                                        <td><?php echo ucfirst($card['card_type'] ?? ''); ?></td>
                                        <td>**** **** **** <?php echo $card['card_last_four'] ?? ''; ?></td>
                                        <td><?php echo htmlspecialchars($card['card_holder_name'] ?? ''); ?></td>
                                        <td><?php echo ($card['expiry_month'] ?? '') . '/' . ($card['expiry_year'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($card['is_verified'] ?? 0): ?>
                                            <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editPaymentMethod(<?php echo $card['id']; ?>, '<?php echo $card['card_type']; ?>', 'old')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!($card['is_default'] ?? 0)): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deletePaymentMethod(<?php echo $card['id']; ?>, '<?php echo $card['card_type']; ?>', 'old')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Security Notice -->
        <div class="alert alert-info mt-4">
            <h6 class="fw-bold"><i class="fas fa-shield-alt me-2"></i> Security Notice</h6>
            <ul class="mb-0">
                <li>Your payment details are encrypted and stored securely</li>
                <li>We only display the last 4 digits of your account numbers</li>
                <li>Never share your full account details with anyone</li>
                <li>All withdrawals are processed within 3-5 business days</li>
            </ul>
        </div>
        
        <!-- Withdrawal Request Section -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-money-bill-wave me-2"></i> Request Withdrawal
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="withdrawalForm" action="action/bank/process-withdrawal.php">
                    <input type="hidden" name="action" value="request_withdrawal">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row g-4">
                        <!-- Withdrawal Method -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Withdrawal Method *</label>
                                <select name="withdrawal_method" class="form-select" id="withdrawalMethod" required>
                                    <option value="">-- Select Withdrawal Method --</option>
                                    <?php foreach($withdrawal_methods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method['method_code']); ?>" 
                                            data-requires-account="<?php echo $method['requires_account']; ?>"
                                            data-min-amount="<?php echo $method['min_amount'] ?? 10; ?>"
                                            data-fee-percentage="<?php echo $method['fee_percentage'] ?? 0; ?>"
                                            data-fee-fixed="<?php echo $method['fee_fixed'] ?? 0; ?>">
                                        <?php echo htmlspecialchars($method['method_name']); ?>
                                        <?php if(!empty($method['fee_percentage']) || !empty($method['fee_fixed'])): ?>
                                            (Fee: 
                                            <?php if(!empty($method['fee_percentage'])): echo $method['fee_percentage'] . '%'; endif; ?>
                                            <?php if(!empty($method['fee_fixed'])): echo ' + $' . number_format($method['fee_fixed'], 2); endif; ?>
                                            )
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted" id="methodDescription"></small>
                            </div>
                        </div>
                        
                        <!-- Bank Account Selection -->
                        <div class="col-md-6 d-none" id="bankAccountField">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Bank Account *</label>
                                <select name="account_id" class="form-select" id="accountSelect">
                                    <option value="">Select Account</option>
                                    <?php foreach($bank_accounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>" 
                                            <?php echo ($account['is_default'] ?? 0) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($account['bank_name']); ?> - 
                                        ****<?php echo substr($account['account_number'], -4); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- PayPal Email Field -->
                        <div class="col-md-6 d-none" id="paypalField">
                            <div class="mb-3">
                                <label class="form-label fw-bold">PayPal Email *</label>
                                <input type="email" name="paypal_email" class="form-control" placeholder="your@email.com">
                            </div>
                        </div>
                        
                        <!-- Mobile Account Field -->
                        <div class="col-md-6 d-none" id="mobileAccountField">
                            <div class="mb-3">
                                <label class="form-label fw-bold" id="mobileAccountLabel">Mobile Account *</label>
                                <select name="mobile_account_id" class="form-select" id="mobileAccountSelect">
                                    <option value="">Select Account</option>
                                    <?php foreach($mobile_accounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>" 
                                            data-type="<?php echo $account['account_type']; ?>"
                                            <?php echo ($account['is_default'] ?? 0) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($account['account_type']); ?> - 
                                        ****<?php echo substr($account['mobile_number'], -4); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Card Field -->
                        <div class="col-md-6 d-none" id="cardField">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Card *</label>
                                <select name="card_id" class="form-select" id="cardSelect">
                                    <option value="">Select Card</option>
                                    <?php foreach($cards as $card): ?>
                                    <option value="<?php echo $card['id']; ?>" 
                                            <?php echo ($card['is_default'] ?? 0) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($card['card_type']); ?> - 
                                        **** **** **** <?php echo $card['card_last_four']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Withdrawal Amount -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Amount to Withdraw *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="withdrawal_amount" class="form-control" 
                                           step="0.01" min="10" max="<?php echo $earnings['paid_earnings'] ?? 0; ?>"
                                           value="<?php echo min(100, $earnings['paid_earnings'] ?? 0); ?>" required>
                                </div>
                                <small class="text-muted">
                                    Available: $<?php echo number_format($earnings['paid_earnings'] ?? 0, 2); ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Notes (Optional)</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Any special instructions..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Terms Agreement -->
                        <div class="col-12">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    I agree to the withdrawal terms and conditions
                                </label>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Submit Withdrawal Request
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Withdrawal History -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-history me-2"></i> Withdrawal History
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($withdrawals)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No withdrawal history yet</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Transaction ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($withdrawals as $withdrawal): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($withdrawal['created_at'])); ?></td>
                                <td>$<?php echo number_format($withdrawal['withdrawal_amount'], 2); ?></td>
                                <td><?php echo ucfirst($withdrawal['withdrawal_method']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $withdrawal['status'] == 'completed' ? 'success' : 
                                            ($withdrawal['status'] == 'pending' ? 'warning' : 
                                            ($withdrawal['status'] == 'rejected' ? 'danger' : 'secondary')); ?>">
                                        <?php echo ucfirst($withdrawal['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($withdrawal['transaction_id']): ?>
                                    <code><?php echo substr($withdrawal['transaction_id'], 0, 8); ?>...</code>
                                    <?php else: ?>
                                    <span class="text-muted">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Add Bank Account Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Bank Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addBankForm" action="action/bank/add-bank-account.php">
                <input type="text" name="action" value="add_bank_account">
                <input type="text" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Holder Name *</label>
                            <input type="text" name="account_holder_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Bank Name *</label>
                            <input type="text" name="bank_name" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Number *</label>
                            <input type="text" name="account_number" class="form-control" 
                                   pattern="\d{9,18}" required>
                        </div>
                        
                        <?php if ($bank_format && !empty($bank_format['routing_required'])): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold"><?php echo $bank_format['routing_label'] ?? 'Routing Number'; ?> *</label>
                            <input type="text" name="routing_number" class="form-control" required>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($bank_format && !empty($bank_format['swift_required'])): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SWIFT Code</label>
                            <input type="text" name="swift_code" class="form-control">
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($bank_format && !empty($bank_format['ifsc_required'])): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">IFSC Code *</label>
                            <input type="text" name="ifsc_code" class="form-control" 
                                   pattern="[A-Z]{4}0[A-Z0-9]{6}" required>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($bank_format && !empty($bank_format['iban_required'])): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">IBAN *</label>
                            <input type="text" name="iban" class="form-control" required>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Type</label>
                            <select name="account_type" class="form-select">
                                <option value="savings">Savings</option>
                                <option value="current">Current</option>
                                <option value="business">Business</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="addBankDefault" checked>
                                <label class="form-check-label" for="addBankDefault">
                                    Set as default payment method
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Bank Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add PayPal Modal -->
<div class="modal fade" id="addPayPalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add PayPal Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addPayPalForm" action="action/bank/add-paypal-account.php">
                <input type="hidden" name="action" value="add_paypal_account">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">PayPal Email *</label>
                        <input type="email" name="paypal_email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">PayPal Account ID (Optional)</label>
                        <input type="text" name="paypal_account_id" class="form-control">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="addPayPalDefault" checked>
                        <label class="form-check-label" for="addPayPalDefault">
                            Set as default payment method
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add PayPal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Stripe Modal -->
<div class="modal fade" id="addStripeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Connect Stripe Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addStripeForm" action="action/bank/add-stripe-account.php">
                <input type="hidden" name="action" value="add_stripe_account">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Stripe Account ID *</label>
                        <input type="text" name="stripe_account_id" class="form-control" required>
                        <small class="text-muted">Starts with 'acct_'</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Email *</label>
                        <input type="email" name="account_email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Stripe Publishable Key (Optional)</label>
                        <input type="text" name="stripe_publishable_key" class="form-control">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="addStripeDefault" checked>
                        <label class="form-check-label" for="addStripeDefault">
                            Set as default payment method
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Connect Stripe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Easypaisa Modal -->
<div class="modal fade" id="addEasypaisaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Easypaisa Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addEasypaisaForm" action="action/bank/add-mobile-account.php">
                <input type="hidden" name="action" value="add_mobile_account">
                <input type="hidden" name="mobile_type" value="easypaisa">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number *</label>
                        <input type="text" name="mobile_number" class="form-control" pattern="03\d{9}" placeholder="03XXXXXXXXX" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">CNIC Number (Optional)</label>
                        <input type="text" name="cnic_number" class="form-control" placeholder="12345-1234567-1">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="addEasypaisaDefault" checked>
                        <label class="form-check-label" for="addEasypaisaDefault">
                            Set as default payment method
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Easypaisa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add JazzCash Modal -->
<div class="modal fade" id="addJazzCashModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add JazzCash Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addJazzCashForm" action="action/bank/add-mobile-account.php">
                <input type="hidden" name="action" value="add_mobile_account">
                <input type="hidden" name="mobile_type" value="jazzcash">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number *</label>
                        <input type="text" name="mobile_number" class="form-control" pattern="03\d{9}" placeholder="03XXXXXXXXX" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" name="account_holder_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">CNIC Number (Optional)</label>
                        <input type="text" name="cnic_number" class="form-control" placeholder="12345-1234567-1">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="addJazzCashDefault" checked>
                        <label class="form-check-label" for="addJazzCashDefault">
                            Set as default payment method
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add JazzCash</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Card Modal -->
<div class="modal fade" id="addCardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Credit/Debit Card</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addCardForm" action="action/bank/add-card.php">
                <input type="hidden" name="action" value="add_card">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Card Type *</label>
                        <select name="card_type" class="form-select" required>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="amex">American Express</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Card Holder Name *</label>
                        <input type="text" name="card_holder_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Card Number *</label>
                        <input type="text" name="card_number" class="form-control card-number" 
                               placeholder="1234 5678 9012 3456" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Expiry Month *</label>
                            <select name="expiry_month" class="form-select" required>
                                <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>">
                                    <?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Expiry Year *</label>
                            <select name="expiry_year" class="form-select" required>
                                <?php for($y=date('Y'); $y<=date('Y')+10; $y++): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">CVV *</label>
                            <input type="text" name="cvv" class="form-control" pattern="\d{3,4}" maxlength="4" required>
                        </div>
                    </div>
                    
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="addCardDefault" checked>
                        <label class="form-check-label" for="addCardDefault">
                            Set as default payment method
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Card</button>
                </div>
            </form>
        </div>
    </div>
</div>

// Bank JavaScript
<script>
// Store CSRF token in global variable
let currentCsrfToken = '<?php echo $js_csrf_token; ?>';

document.addEventListener('DOMContentLoaded', function() {
    console.log('Bank page loaded, CSRF token:', currentCsrfToken);
    
    // Update all forms with current token
    updateAllFormsToken();
    
    // Setup withdrawal method change handler
    setupWithdrawalMethodHandler();
    
    // Setup form submissions
    setupFormSubmissions();
    
    // Card number formatting
    document.querySelectorAll('.card-number').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = this.value.replace(/\s/g, '');
            if (value.length > 0) {
                value = value.match(new RegExp('.{1,4}', 'g'))?.join(' ') || value;
            }
            this.value = value;
        });
    });
});

function updateAllFormsToken() {
    document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
        input.value = currentCsrfToken;
        console.log('Updated form token:', input.value);
    });
}

function setupWithdrawalMethodHandler() {
    const withdrawalMethod = document.getElementById('withdrawalMethod');
    if (!withdrawalMethod) return;
    
    withdrawalMethod.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const methodCode = this.value;
        
        // Hide all fields first
        document.getElementById('bankAccountField')?.classList.add('d-none');
        document.getElementById('paypalField')?.classList.add('d-none');
        document.getElementById('mobileAccountField')?.classList.add('d-none');
        document.getElementById('cardField')?.classList.add('d-none');
        
        if (!methodCode) {
            document.getElementById('methodDescription').innerHTML = '';
            return;
        }
        
        // Show relevant field based on method
        if (methodCode === 'bank') {
            document.getElementById('bankAccountField')?.classList.remove('d-none');
        } else if (methodCode === 'paypal') {
            document.getElementById('paypalField')?.classList.remove('d-none');
        } else if (methodCode === 'visa' || methodCode === 'mastercard' || methodCode === 'amex') {
            document.getElementById('cardField')?.classList.remove('d-none');
        } else if (methodCode === 'easypaisa' || methodCode === 'jazzcash') {
            document.getElementById('mobileAccountField')?.classList.remove('d-none');
            const label = document.getElementById('mobileAccountLabel');
            if (label) {
                label.textContent = methodCode === 'easypaisa' ? 'Easypaisa Account *' : 'JazzCash Account *';
            }
        }
        
        // Update method description
        updateMethodDescription(selectedOption);
    });
}

function updateMethodDescription(option) {
    const minAmount = option.getAttribute('data-min-amount') || 10;
    const feePercentage = parseFloat(option.getAttribute('data-fee-percentage')) || 0;
    const feeFixed = parseFloat(option.getAttribute('data-fee-fixed')) || 0;
    
    let description = `Min: $${minAmount}`;
    if (feePercentage > 0 || feeFixed > 0) {
        description += ' | Fee: ';
        if (feePercentage > 0) description += feePercentage + '%';
        if (feeFixed > 0) description += (feePercentage > 0 ? ' + ' : '') + '$' + feeFixed.toFixed(2);
    }
    
    const descEl = document.getElementById('methodDescription');
    if (descEl) {
        descEl.innerHTML = `<i class="fas fa-info-circle me-1"></i>${description}`;
    }
}

function setupFormSubmissions() {
    const forms = ['withdrawalForm', 'addBankForm', 'addPayPalForm', 'addStripeForm', 
                   'addEasypaisaForm', 'addJazzCashForm', 'addCardForm'];
    
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Form submitted:', formId);
                submitForm(this);
            });
        }
    });
}

function setWithdrawalAmount(amount) {
    const amountInput = document.querySelector('input[name="withdrawal_amount"]');
    if (amountInput) {
        amountInput.value = amount;
    }
}

function submitForm(form) {
    const formData = new FormData(form);
    formData.set('csrf_token', currentCsrfToken);
    
    // Log form data for debugging
    console.log('Submitting form to:', form.action);
    console.log('Form data:');
    for (let pair of formData.entries()) {
        if (pair[0] === 'card_number') {
            console.log(pair[0], '****' + pair[1].slice(-4));
        } else {
            console.log(pair[0], pair[1]);
        }
    }
    
    // Show loading notification
    const loadingId = 'loading-' + Date.now();
    showNotification('info', 'Processing your request...', loadingId);
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text(); // First get as text to see raw response
    })
    .then(text => {
        console.log('Raw response:', text);
        
        // Try to parse as JSON
        try {
            const data = JSON.parse(text);
            
            // Remove loading notification
            document.getElementById(loadingId)?.remove();
            
            if (data.success) {
                console.log('Success:', data.message);
                if (data.csrf_token) {
                    currentCsrfToken = data.csrf_token;
                    updateAllFormsToken();
                }
                showNotification('success', data.message);
                // Reload after success
                setTimeout(() => window.location.reload(), 1500);
            } else {
                console.error('Server returned error:', data.message);
                showNotification('error', data.message || 'An error occurred');
            }
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            console.error('Response was not JSON:', text);
            
            // Remove loading notification
            document.getElementById(loadingId)?.remove();
            
            // Show raw response in notification (for debugging)
            showNotification('error', 'Server returned invalid response. Check console.');
            
            // Create a debug div to show the error
            const debugDiv = document.createElement('div');
            debugDiv.className = 'alert alert-dark mt-3';
            debugDiv.innerHTML = `<strong>Debug Response:</strong><br><pre>${text.substring(0, 500)}</pre>`;
            document.querySelector('.main-content').prepend(debugDiv);
        }
    })
    .catch(error => {
        // Remove loading notification
        document.getElementById(loadingId)?.remove();
        
        console.error('Fetch error:', error);
        showNotification('error', 'Network error: ' + error.message);
    });
}

function editPaymentMethod(id, type, source) {
    console.log('Edit payment method:', id, type, source);
    showNotification('info', 'Redirecting to edit page...');
    
    // Redirect to edit page based on type
    if (type === 'bank') {
        window.location.href = `action/bank/edit-bank.php?id=${id}`;
    } else if (type === 'easypaisa' || type === 'jazzcash') {
        window.location.href = `action/bank/edit-mobile.php?id=${id}&type=${type}`;
    } else if (type === 'visa' || type === 'mastercard' || type === 'amex') {
        window.location.href = `action/bank/edit-card.php?id=${id}`;
    } else {
        window.location.href = `action/bank/edit-payment-method.php?id=${id}&type=${type}`;
    }
}

function setDefaultMethod(id, type, source) {
    if (!confirm('Set this as default payment method?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('type', type);
    formData.append('source', source);
    formData.append('csrf_token', currentCsrfToken);
    
    showNotification('info', 'Setting as default...');
    
    fetch('action/bank/set-default.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.csrf_token) {
                currentCsrfToken = data.csrf_token;
                updateAllFormsToken();
            }
            showNotification('success', data.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Network error occurred');
    });
}

function deletePaymentMethod(id, type, source) {
    if (!confirm('Are you sure you want to delete this payment method? This action cannot be undone.')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('type', type);
    formData.append('source', source);
    formData.append('csrf_token', currentCsrfToken);
    
    showNotification('info', 'Deleting...');
    
    fetch('action/bank/delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.csrf_token) {
                currentCsrfToken = data.csrf_token;
                updateAllFormsToken();
            }
            showNotification('success', data.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Network error occurred');
    });
}

function showNotification(type, message, id = null) {
    // Remove existing notifications with same id if provided
    if (id) {
        document.getElementById(id)?.remove();
    } else {
        // Remove all notifications if no specific id
        document.querySelectorAll('.alert-notification').forEach(el => el.remove());
    }
    
    const notificationId = id || 'notification-' + Date.now();
    const notification = document.createElement('div');
    notification.id = notificationId;
    notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} alert-notification alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
    
    // Set icon based on type
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${icon} me-2 fa-lg"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds for success/error, keep info until updated
    if (type !== 'info') {
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
    
    return notificationId;
}
</script>
<style>
.bank-account-card {
    border-left: 4px solid #0d6efd;
}
.bank-account-card.default {
    border-left-color: #198754;
    background-color: #f8fff8;
}
.payment-method-card {
    transition: all 0.3s ease;
    height: 100%;
}
.payment-method-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.alert-notification {
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}
</style>

<?php require_once '../../includes/footer.php'; ?>