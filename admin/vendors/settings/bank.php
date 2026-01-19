<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Bank & Payment Settings';
require_once '../../includes/header.php';

// Get vendor details and bank accounts
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor basic info
    $stmt = $db->prepare("
        SELECT u.full_name, vs.store_name 
        FROM users u
        LEFT JOIN vendor_settings vs ON u.id = vs.vendor_id
        WHERE u.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Get bank accounts
    $stmt = $db->prepare("
        SELECT * FROM vendor_bank_accounts 
        WHERE vendor_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $bank_accounts = $stmt->fetchAll();
    
    // Get earnings summary
    $stmt = $db->prepare("
        SELECT 
            SUM(vendor_amount) as total_earnings,
            SUM(CASE WHEN status = 'paid' THEN vendor_amount ELSE 0 END) as paid_earnings,
            SUM(CASE WHEN status IN ('pending', 'processing') THEN vendor_amount ELSE 0 END) as pending_earnings
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
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $vendor = [];
    $bank_accounts = [];
    $earnings = ['total_earnings' => 0, 'paid_earnings' => 0, 'pending_earnings' => 0];
    $withdrawals = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_bank_account') {
            $account_holder_name = trim($_POST['account_holder_name'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $ifsc_code = trim($_POST['ifsc_code'] ?? '');
            $branch_name = trim($_POST['branch_name'] ?? '');
            $account_type = $_POST['account_type'] ?? 'savings';
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            // Validation
            if (empty($account_holder_name) || empty($bank_name) || empty($account_number)) {
                throw new Exception('All required fields must be filled.');
            }
            
            // Validate account number (numeric, 9-18 digits)
            if (!preg_match('/^\d{9,18}$/', $account_number)) {
                throw new Exception('Invalid account number. Must be 9-18 digits.');
            }
            
            // Validate IFSC code if provided
            if (!empty($ifsc_code) && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc_code)) {
                throw new Exception('Invalid IFSC code format. Example: SBIN0001234');
            }
            
            // If setting as default, unset other defaults
            if ($is_default) {
                $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 0 WHERE vendor_id = ?");
                $stmt->execute([$vendor_id]);
            }
            
            // Check if account already exists
            $stmt = $db->prepare("SELECT id FROM vendor_bank_accounts WHERE vendor_id = ? AND account_number = ?");
            $stmt->execute([$vendor_id, $account_number]);
            
            if ($stmt->fetch()) {
                throw new Exception('Bank account already exists.');
            }
            
            // Insert new account
            $sql = "INSERT INTO vendor_bank_accounts 
                    (vendor_id, account_holder_name, bank_name, account_number, 
                     ifsc_code, branch_name, account_type, is_default, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $vendor_id, $account_holder_name, $bank_name, $account_number,
                $ifsc_code, $branch_name, $account_type, $is_default
            ]);
            
            $_SESSION['success'] = 'Bank account added successfully!';
            redirect('bank.php');
            
        } elseif ($action === 'update_bank_account') {
            $account_id = $_POST['account_id'] ?? 0;
            $account_holder_name = trim($_POST['account_holder_name'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $ifsc_code = trim($_POST['ifsc_code'] ?? '');
            $branch_name = trim($_POST['branch_name'] ?? '');
            $account_type = $_POST['account_type'] ?? 'savings';
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            // Validation
            if (empty($account_holder_name) || empty($bank_name)) {
                throw new Exception('All required fields must be filled.');
            }
            
            // If setting as default, unset other defaults
            if ($is_default) {
                $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 0 WHERE vendor_id = ? AND id != ?");
                $stmt->execute([$vendor_id, $account_id]);
            }
            
            // Update account
            $sql = "UPDATE vendor_bank_accounts SET 
                    account_holder_name = ?, bank_name = ?, ifsc_code = ?,
                    branch_name = ?, account_type = ?, is_default = ?,
                    updated_at = NOW()
                    WHERE id = ? AND vendor_id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $account_holder_name, $bank_name, $ifsc_code,
                $branch_name, $account_type, $is_default,
                $account_id, $vendor_id
            ]);
            
            $_SESSION['success'] = 'Bank account updated successfully!';
            redirect('bank.php');
            
        } elseif ($action === 'delete_bank_account') {
            $account_id = $_POST['account_id'] ?? 0;
            
            // Check if this is the only account
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM vendor_bank_accounts WHERE vendor_id = ?");
            $stmt->execute([$vendor_id]);
            $count = $stmt->fetch()['count'];
            
            if ($count <= 1) {
                throw new Exception('Cannot delete the only bank account. Add another account first.');
            }
            
            // Check if account is default
            $stmt = $db->prepare("SELECT is_default FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$account_id, $vendor_id]);
            $account = $stmt->fetch();
            
            if ($account && $account['is_default']) {
                throw new Exception('Cannot delete default account. Set another account as default first.');
            }
            
            // Delete account
            $stmt = $db->prepare("DELETE FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$account_id, $vendor_id]);
            
            $_SESSION['success'] = 'Bank account deleted successfully!';
            redirect('bank.php');
            
        } elseif ($action === 'request_withdrawal') {
            $withdrawal_amount = floatval($_POST['withdrawal_amount'] ?? 0);
            $withdrawal_method = $_POST['withdrawal_method'] ?? 'bank';
            $account_id = $_POST['account_id'] ?? 0;
            $notes = trim($_POST['notes'] ?? '');
            
            // Validation
            if ($withdrawal_amount <= 0) {
                throw new Exception('Invalid withdrawal amount.');
            }
            
            // Check available balance
            if ($withdrawal_amount > ($earnings['paid_earnings'] ?? 0)) {
                throw new Exception('Insufficient balance. Available: $' . number_format($earnings['paid_earnings'], 2));
            }
            
            // Get account details for bank method
            $account_details = null;
            if ($withdrawal_method === 'bank' && $account_id) {
                $stmt = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$account_id, $vendor_id]);
                $account_details = $stmt->fetch();
                
                if (!$account_details) {
                    throw new Exception('Selected bank account not found.');
                }
                
                $account_details = json_encode([
                    'bank_name' => $account_details['bank_name'],
                    'account_holder' => $account_details['account_holder_name'],
                    'account_number' => substr($account_details['account_number'], -4), // Last 4 digits only
                    'ifsc_code' => $account_details['ifsc_code']
                ]);
            }
            
            // Create withdrawal request
            $sql = "INSERT INTO vendor_withdrawals 
                    (vendor_id, withdrawal_method, withdrawal_amount, 
                     account_details, notes, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $vendor_id, $withdrawal_method, $withdrawal_amount,
                $account_details, $notes
            ]);
            
            $_SESSION['success'] = 'Withdrawal request submitted successfully!';
            redirect('bank.php');
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Bank & Payment Settings</h1>
                <p class="text-muted mb-0">Manage your bank accounts and withdrawals</p>
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
        
        <!-- Bank Accounts & Withdrawals Tabs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs settings-tabs" id="bankTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="accounts-tab" data-bs-toggle="tab" 
                                data-bs-target="#accounts" type="button">
                            <i class="fas fa-university me-2"></i> Bank Accounts
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="withdraw-tab" data-bs-toggle="tab" 
                                data-bs-target="#withdraw" type="button">
                            <i class="fas fa-money-bill-wave me-2"></i> Request Withdrawal
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" 
                                data-bs-target="#history" type="button">
                            <i class="fas fa-history me-2"></i> Withdrawal History
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Bank Content -->
        <div class="tab-content" id="bankTabContent">
            <!-- Bank Accounts Tab -->
            <div class="tab-pane fade show active" id="accounts" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-university me-2"></i> Bank Accounts
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                            <i class="fas fa-plus me-2"></i> Add Account
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bank_accounts)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-university fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Bank Accounts</h5>
                            <p class="text-muted">Add your bank account to receive payments</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                                <i class="fas fa-plus me-2"></i> Add Your First Account
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Bank Name</th>
                                        <th>Account Holder</th>
                                        <th>Account Number</th>
                                        <th>IFSC Code</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($bank_accounts as $account): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($account['bank_name']); ?></strong>
                                            <?php if ($account['is_default']): ?>
                                            <span class="badge bg-success ms-2">Default</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($account['account_holder_name']); ?></td>
                                        <td>
                                            ****<?php echo substr($account['account_number'], -4); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($account['ifsc_code'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $account['account_type'] === 'savings' ? 'info' : 'primary'; ?>">
                                                <?php echo ucfirst($account['account_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($account['is_verified']): ?>
                                            <span class="badge bg-success">Verified</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="editAccount(<?php echo $account['id']; ?>)"
                                                        data-bs-toggle="modal" data-bs-target="#editAccountModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if (!$account['is_default']): ?>
                                                <button class="btn btn-outline-success" 
                                                        onclick="setDefaultAccount(<?php echo $account['id']; ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="deleteAccount(<?php echo $account['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Security Notice -->
                        <div class="alert alert-info mt-4">
                            <h6 class="fw-bold"><i class="fas fa-shield-alt me-2"></i> Security Notice</h6>
                            <ul class="mb-0">
                                <li>Your bank account details are encrypted and stored securely</li>
                                <li>We only display the last 4 digits of your account number</li>
                                <li>Never share your full account details with anyone</li>
                                <li>All withdrawals are processed within 3-5 business days</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Withdrawal Request Tab -->
            <div class="tab-pane fade" id="withdraw" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-money-bill-wave me-2"></i> Request Withdrawal
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="withdrawalForm">
                            <input type="hidden" name="action" value="request_withdrawal">
                            
                            <div class="row g-4">
                                <!-- Withdrawal Method -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Withdrawal Method *</label>
                                        <select name="withdrawal_method" class="form-select" id="withdrawalMethod" required>
                                            <option value="bank" selected>Bank Transfer</option>
                                            <option value="paypal">PayPal</option>
                                            <option value="stripe">Stripe</option>
                                            <option value="cash">Cash Pickup</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Bank Account Selection -->
                                <div class="col-md-6" id="bankAccountField">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Select Bank Account *</label>
                                        <select name="account_id" class="form-select" id="accountSelect" required>
                                            <option value="">Select Account</option>
                                            <?php foreach($bank_accounts as $account): ?>
                                            <option value="<?php echo $account['id']; ?>"
                                                <?php echo $account['is_default'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($account['bank_name']); ?> 
                                                - ****<?php echo substr($account['account_number'], -4); ?>
                                                <?php echo $account['is_default'] ? ' (Default)' : ''; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($bank_accounts)): ?>
                                        <small class="text-danger">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            No bank accounts added. <a href="#" onclick="switchTab('accounts')">Add account first</a>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- PayPal Email -->
                                <div class="col-md-6 d-none" id="paypalField">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">PayPal Email *</label>
                                        <input type="email" name="paypal_email" class="form-control" 
                                               placeholder="your.email@example.com">
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
                                                   value="<?php echo min(100, $earnings['paid_earnings'] ?? 0); ?>"
                                                   required>
                                        </div>
                                        <small class="text-muted">
                                            Minimum: $10.00 | Available: $<?php echo number_format($earnings['paid_earnings'] ?? 0, 2); ?>
                                        </small>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                                    onclick="setWithdrawalAmount(<?php echo $earnings['paid_earnings'] ?? 0; ?>)">
                                                Withdraw All
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    onclick="setWithdrawalAmount(100)">
                                                $100
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    onclick="setWithdrawalAmount(500)">
                                                $500
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Notes -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Notes (Optional)</label>
                                        <textarea name="notes" class="form-control" rows="3"
                                                  placeholder="Any special instructions..."></textarea>
                                    </div>
                                </div>
                                
                                <!-- Terms Agreement -->
                                <div class="col-12">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                        <label class="form-check-label" for="agreeTerms">
                                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">withdrawal terms and conditions</a>.
                                            Withdrawals are processed within 3-5 business days.
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i> Submit Withdrawal Request
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Withdrawal Info -->
                        <div class="alert alert-warning mt-4">
                            <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> Important Information</h6>
                            <ul class="mb-0">
                                <li>Withdrawals are processed every Monday and Thursday</li>
                                <li>Processing time: 3-5 business days</li>
                                <li>Minimum withdrawal amount: $10.00</li>
                                <li>Transaction fees may apply based on payment method</li>
                                <li>You can only withdraw from "Paid Earnings" balance</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Withdrawal History Tab -->
            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2"></i> Withdrawal History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($withdrawals)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-history fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Withdrawal History</h5>
                            <p class="text-muted">You haven't made any withdrawal requests yet</p>
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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($withdrawals as $withdrawal): 
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'processing' => 'info',
                                            'completed' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $status_icons = [
                                            'pending' => 'clock',
                                            'processing' => 'sync-alt',
                                            'completed' => 'check-circle',
                                            'rejected' => 'times-circle'
                                        ];
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo date('d M Y', strtotime($withdrawal['created_at'])); ?>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($withdrawal['withdrawal_amount'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-<?php echo $withdrawal['withdrawal_method'] === 'bank' ? 'university' : 
                                                                   ($withdrawal['withdrawal_method'] === 'paypal' ? 'paypal' : 
                                                                   ($withdrawal['withdrawal_method'] === 'stripe' ? 'credit-card' : 'money-bill')); ?> me-1"></i>
                                                <?php echo ucfirst($withdrawal['withdrawal_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_colors[$withdrawal['status']] ?? 'secondary'; ?>">
                                                <i class="fas fa-<?php echo $status_icons[$withdrawal['status']] ?? 'question-circle'; ?> me-1"></i>
                                                <?php echo ucfirst($withdrawal['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($withdrawal['transaction_id']): ?>
                                            <code><?php echo substr($withdrawal['transaction_id'], 0, 8) . '...'; ?></code>
                                            <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="viewWithdrawal(<?php echo $withdrawal['id']; ?>)"
                                                    data-bs-toggle="modal" data-bs-target="#viewWithdrawalModal">
                                                <i class="fas fa-eye"></i> Details
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- View More -->
                        <div class="text-center mt-3">
                            <a href="withdrawals.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-2"></i> View All Withdrawals
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i> Add Bank Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addAccountForm">
                <input type="hidden" name="action" value="add_bank_account">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Account Holder Name *</label>
                                <input type="text" name="account_holder_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?>" required>
                                <small class="text-muted">Must match your bank account name</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Bank Name *</label>
                                <input type="text" name="bank_name" class="form-control" required
                                       placeholder="e.g., State Bank of India">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Account Number *</label>
                                <input type="text" name="account_number" class="form-control" 
                                       pattern="\d{9,18}" required>
                                <small class="text-muted">9-18 digit account number</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">IFSC Code</label>
                                <input type="text" name="ifsc_code" class="form-control" 
                                       pattern="[A-Z]{4}0[A-Z0-9]{6}"
                                       placeholder="SBIN0001234">
                                <small class="text-muted">11-character IFSC code (Indian banks)</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Branch Name</label>
                                <input type="text" name="branch_name" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Account Type *</label>
                                <select name="account_type" class="form-select" required>
                                    <option value="savings" selected>Savings Account</option>
                                    <option value="current">Current Account</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="setAsDefault" checked>
                                <label class="form-check-label" for="setAsDefault">
                                    Set as default account for withdrawals
                                </label>
                            </div>
                        </div>
                        
                        <!-- Security Notice -->
                        <div class="col-12 mt-3">
                            <div class="alert alert-warning">
                                <h6 class="fw-bold"><i class="fas fa-shield-alt me-2"></i> Security Notice</h6>
                                <p class="mb-0 small">
                                    Your bank details are encrypted and stored securely. 
                                    We will never share your information with third parties.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i> Edit Bank Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editAccountForm">
                <input type="hidden" name="action" value="update_bank_account">
                <input type="hidden" name="account_id" id="editAccountId">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Account Holder Name *</label>
                                <input type="text" name="account_holder_name" class="form-control" 
                                       id="editAccountHolder" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Bank Name *</label>
                                <input type="text" name="bank_name" class="form-control" 
                                       id="editBankName" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">IFSC Code</label>
                                <input type="text" name="ifsc_code" class="form-control" 
                                       id="editIfscCode">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Branch Name</label>
                                <input type="text" name="branch_name" class="form-control" 
                                       id="editBranchName">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Account Type *</label>
                                <select name="account_type" class="form-select" id="editAccountType" required>
                                    <option value="savings">Savings Account</option>
                                    <option value="current">Current Account</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="editSetAsDefault">
                                <label class="form-check-label" for="editSetAsDefault">
                                    Set as default account for withdrawals
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Terms & Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-contract me-2"></i> Withdrawal Terms & Conditions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="fw-bold">1. Processing Time</h6>
                <p>Withdrawals are processed within 3-5 business days from the request date.</p>
                
                <h6 class="fw-bold">2. Minimum Amount</h6>
                <p>Minimum withdrawal amount is $10.00 or equivalent in local currency.</p>
                
                <h6 class="fw-bold">3. Fees</h6>
                <p>Transaction fees may apply based on the withdrawal method and amount.</p>
                
                <h6 class="fw-bold">4. Verification</h6>
                <p>First-time withdrawals may require additional verification.</p>
                
                <h6 class="fw-bold">5. Cancellation</h6>
                <p>Withdrawal requests can be cancelled within 24 hours of submission.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<!-- Bank JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    const triggerTabList = [].slice.call(document.querySelectorAll('#bankTab button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
    
    // Withdrawal method toggle
    const withdrawalMethod = document.getElementById('withdrawalMethod');
    const bankAccountField = document.getElementById('bankAccountField');
    const paypalField = document.getElementById('paypalField');
    const accountSelect = document.getElementById('accountSelect');
    
    withdrawalMethod.addEventListener('change', function() {
        if (this.value === 'bank') {
            bankAccountField.classList.remove('d-none');
            paypalField.classList.add('d-none');
            accountSelect.required = true;
        } else if (this.value === 'paypal') {
            bankAccountField.classList.add('d-none');
            paypalField.classList.remove('d-none');
            accountSelect.required = false;
        } else {
            bankAccountField.classList.add('d-none');
            paypalField.classList.add('d-none');
            accountSelect.required = false;
        }
    });
    
    // Form submissions
    const forms = ['withdrawalForm', 'addAccountForm', 'editAccountForm'];
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this);
            });
        }
    });
});

function switchTab(tabName) {
    const tab = document.getElementById(tabName + '-tab');
    if (tab) {
        tab.click();
    }
}

function setWithdrawalAmount(amount) {
    const amountInput = document.querySelector('input[name="withdrawal_amount"]');
    if (amountInput) {
        amountInput.value = amount;
    }
}

function editAccount(accountId) {
    // In real implementation, fetch account details via AJAX
    // For demo, we'll simulate with static data
    const account = {
        id: accountId,
        account_holder_name: 'John Doe',
        bank_name: 'Example Bank',
        ifsc_code: 'EXMP0001234',
        branch_name: 'Main Branch',
        account_type: 'savings',
        is_default: false
    };
    
    document.getElementById('editAccountId').value = account.id;
    document.getElementById('editAccountHolder').value = account.account_holder_name;
    document.getElementById('editBankName').value = account.bank_name;
    document.getElementById('editIfscCode').value = account.ifsc_code;
    document.getElementById('editBranchName').value = account.branch_name;
    document.getElementById('editAccountType').value = account.account_type;
    document.getElementById('editSetAsDefault').checked = account.is_default;
}

function setDefaultAccount(accountId) {
    if (confirm('Set this account as default for withdrawals?')) {
        // AJAX call to set default
        fetch('action/set-default-account.php', {
            method: 'POST',
            body: new FormData(document.getElementById('defaultForm_' + accountId))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function deleteAccount(accountId) {
    if (confirm('Are you sure you want to delete this bank account?')) {
        const formData = new FormData();
        formData.append('action', 'delete_bank_account');
        formData.append('account_id', accountId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            window.location.reload();
        });
    }
}

function viewWithdrawal(withdrawalId) {
    // AJAX call to get withdrawal details
    fetch('action/get-withdrawal.php?id=' + withdrawalId)
    .then(response => response.json())
    .then(data => {
        // Display details in modal
        // Implementation depends on your modal structure
    });
}

function submitForm(form) {
    const formData = new FormData(form);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.reload();
    })
    .catch(error => {
        alert('Error: ' + error);
    });
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
</style>

<?php require_once '../../includes/footer.php'; ?>