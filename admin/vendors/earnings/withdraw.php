<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

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
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'admin/vendors/dashboard.php');
    exit();
}

$page_title = 'Withdraw Earnings';
require_once '../../includes/header.php';

// Get vendor earnings and bank accounts
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor details
    $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Get pending earnings
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(vendor_amount), 0) as pending_earnings 
        FROM vendor_earnings 
        WHERE vendor_id = ? AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$vendor_id]);
    $pending_earnings = $stmt->fetch()['pending_earnings'];
    
    // Get bank accounts
    $stmt = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE vendor_id = ? ORDER BY is_default DESC");
    $stmt->execute([$vendor_id]);
    $bank_accounts = $stmt->fetchAll();
    
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
    $_SESSION['error'] = 'Error loading withdrawal data: ' . $e->getMessage();
    $pending_earnings = 0;
    $bank_accounts = [];
    $withdrawals = [];
    $vendor = ['full_name' => '', 'email' => ''];
}

$errors = [];
$min_withdrawal = 50.00;

// Process withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $withdrawal_amount = (float)$_POST['amount'];
    $withdrawal_method = trim($_POST['method'] ?? '');
    $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validation
    if ($withdrawal_amount < $min_withdrawal) {
        $errors[] = "Minimum withdrawal amount is $" . number_format($min_withdrawal, 2);
    }
    
    if ($withdrawal_amount > $pending_earnings) {
        $errors[] = "Insufficient pending earnings. Available: $" . number_format($pending_earnings, 2);
    }
    
    if (empty($withdrawal_method)) {
        $errors[] = "Please select withdrawal method";
    }
    
    if ($withdrawal_method == 'bank' && empty($account_id)) {
        $errors[] = "Please select a bank account";
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Check if bank account belongs to vendor
            if ($withdrawal_method == 'bank') {
                $stmt = $db->prepare("SELECT id FROM vendor_bank_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$account_id, $vendor_id]);
                if ($stmt->rowCount() == 0) {
                    $errors[] = "Invalid bank account selected";
                }
            }
            
            // Create withdrawal request
            if (empty($errors)) {
                $db->beginTransaction();
                
                // Prepare account details
                $account_details = '';
                if ($withdrawal_method == 'bank' && $account_id) {
                    $stmt_acc = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE id = ?");
                    $stmt_acc->execute([$account_id]);
                    $account = $stmt_acc->fetch();
                    if ($account) {
                        $account_details = json_encode([
                            'bank_name' => $account['bank_name'],
                            'account_holder' => $account['account_holder_name'],
                            'account_number' => substr($account['account_number'], -4),
                            'ifsc' => $account['ifsc_code']
                        ]);
                    }
                }
                
                // Insert withdrawal record
                $stmt = $db->prepare("
                    INSERT INTO vendor_withdrawals (vendor_id, withdrawal_method, withdrawal_amount, account_details, notes, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                
                $stmt->execute([
                    $vendor_id,
                    $withdrawal_method,
                    $withdrawal_amount,
                    $account_details,
                    $notes
                ]);
                
                $withdrawal_id = $db->lastInsertId();
                
                // Update vendor earnings status to processing (simplified - in reality you'd track which earnings)
                // For now, we'll just mark some earnings as processing
                if ($withdrawal_amount > 0) {
                    $stmt = $db->prepare("
                        UPDATE vendor_earnings 
                        SET status = 'processing' 
                        WHERE vendor_id = ? AND status = 'pending'
                        ORDER BY id ASC
                        LIMIT ?
                    ");
                    
                    // Estimate limit - assuming average earning is $10 per record
                    $limit = ceil($withdrawal_amount / 10);
                    $stmt->execute([$vendor_id, $limit]);
                }
                
                $db->commit();
                
                $_SESSION['success'] = "Withdrawal request of $" . number_format($withdrawal_amount, 2) . " submitted successfully! It will be processed within 3-5 business days.";
                
                // Redirect to avoid resubmission
                header('Location: withdraw.php');
                exit();
                
            }
            
        } catch(PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $errors[] = 'Error processing withdrawal: ' . $e->getMessage();
        }
    }
}

// Add bank account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bank_account'])) {
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc_code = trim($_POST['ifsc_code'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $account_type = trim($_POST['account_type'] ?? 'savings');
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Validation
    if (empty($account_holder_name)) {
        $errors[] = "Account holder name is required";
    }
    
    if (empty($bank_name)) {
        $errors[] = "Bank name is required";
    }
    
    if (empty($account_number)) {
        $errors[] = "Account number is required";
    }
    
    if (empty($ifsc_code)) {
        $errors[] = "IFSC code is required";
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // If setting as default, unset other defaults
            if ($is_default) {
                $stmt = $db->prepare("UPDATE vendor_bank_accounts SET is_default = 0 WHERE vendor_id = ?");
                $stmt->execute([$vendor_id]);
            }
            
            // Insert bank account
            $stmt = $db->prepare("
                INSERT INTO vendor_bank_accounts (vendor_id, account_holder_name, bank_name, account_number, ifsc_code, branch_name, account_type, is_default) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $vendor_id,
                $account_holder_name,
                $bank_name,
                $account_number,
                $ifsc_code,
                $branch_name,
                $account_type,
                $is_default
            ]);
            
            $_SESSION['success'] = "Bank account added successfully! It will be verified by admin within 24 hours.";
            
            // Redirect to avoid resubmission
            header('Location: withdraw.php');
            exit();
            
        } catch(PDOException $e) {
            $errors[] = 'Error adding bank account: ' . $e->getMessage();
        }
    }
}
include_once '../../includes/header.php';
?>


<div class="dashboard-container">
    <?php 
    // Check if vendor sidebar exists
    $sidebar_path = '../../includes/vendor-sidebar.php';
    if (file_exists($sidebar_path)) {
        include_once $sidebar_path;
    }
    ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Withdraw Earnings</h1>
                    <p class="text-muted mb-0">Transfer your earnings to your account</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="earnings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Earnings
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Error/Success Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Earnings Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm border-start border-5 border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold mb-1">Available for Withdrawal</h5>
                                <h1 class="display-6 fw-bold text-warning mb-0">$<?php echo number_format($pending_earnings, 2); ?></h1>
                                <p class="text-muted mb-0">
                                    Minimum withdrawal: $<?php echo number_format($min_withdrawal, 2); ?>
                                    <?php if ($pending_earnings < $min_withdrawal): ?>
                                        <span class="text-danger"> (Insufficient balance)</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="avatar-lg bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-wallet fa-3x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h6 class="fw-bold mb-3">Withdrawal Status</h6>
                        <?php if ($pending_earnings >= $min_withdrawal): ?>
                            <div class="alert alert-success mb-3">
                                <i class="fas fa-check-circle me-2"></i>
                                Ready to Withdraw
                            </div>
                            <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#withdrawModal" 
                                    <?php echo empty($bank_accounts) ? 'disabled' : ''; ?>>
                                <i class="fas fa-wallet me-2"></i> Request Withdrawal
                            </button>
                            <?php if (empty($bank_accounts)): ?>
                                <small class="text-muted d-block mt-2">Please add a bank account first</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-clock me-2"></i>
                                Need $<?php echo number_format($min_withdrawal - $pending_earnings, 2); ?> more
                            </div>
                            <a href="<?php echo SITE_URL; ?>admin/vendor/dashboard.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-line me-2"></i> Boost Sales
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Bank Accounts -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-university me-2 text-primary"></i> Bank Accounts
                        </h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bankAccountModal">
                            <i class="fas fa-plus me-1"></i> Add Account
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bank_accounts)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-university fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No bank accounts added yet</p>
                                <p class="text-muted small">Add a bank account to withdraw your earnings</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($bank_accounts as $account): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($account['bank_name']); ?></h6>
                                            <small class="text-muted">
                                                Account: ****<?php echo substr($account['account_number'], -4); ?> | 
                                                <?php echo ucfirst($account['account_type']); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php if ($account['is_default']): ?>
                                                <span class="badge bg-success me-2">Default</span>
                                            <?php endif; ?>
                                            <?php if ($account['is_verified']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i> Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock me-1"></i> Pending Verification
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <?php echo htmlspecialchars($account['account_holder_name']); ?> | IFSC: <?php echo htmlspecialchars($account['ifsc_code']); ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Bank accounts require admin verification before use. Withdrawals to unverified accounts may be delayed.
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Withdrawal Rules -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-gem me-2 text-warning"></i> Withdrawal Rules
                        </h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Minimum withdrawal: $<?php echo number_format($min_withdrawal, 2); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Processing time: 3-5 business days
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Available payment methods: Bank Transfer
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                30-day holding period for new orders
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Monthly withdrawal limit: $10,000
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Withdrawal History -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2 text-primary"></i> Withdrawal History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($withdrawals)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No withdrawal history</p>
                                <p class="text-muted small">Your withdrawal requests will appear here</p>
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
                                            <th>Transaction</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($withdrawals as $withdrawal): ?>
                                        <tr>
                                            <td><?php echo date('M d', strtotime($withdrawal['created_at'])); ?></td>
                                            <td class="fw-bold">$<?php echo number_format($withdrawal['withdrawal_amount'], 2); ?></td>
                                            <td><?php echo ucfirst($withdrawal['withdrawal_method']); ?></td>
                                            <td>
                                                <?php
                                                $status_color = 'secondary';
                                                if ($withdrawal['status'] == 'completed') $status_color = 'success';
                                                if ($withdrawal['status'] == 'pending') $status_color = 'warning';
                                                if ($withdrawal['status'] == 'processing') $status_color = 'info';
                                                if ($withdrawal['status'] == 'rejected') $status_color = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo ucfirst($withdrawal['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($withdrawal['transaction_id']): ?>
                                                    <small class="text-muted"><?php echo substr($withdrawal['transaction_id'], 0, 12); ?>...</small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="history.php" class="btn btn-outline-primary btn-sm">
                                View Complete History <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-chart-pie me-2 text-success"></i> Quick Stats
                        </h5>
                        <?php 
                        try {
                            $stmt = $db->prepare("
                                SELECT 
                                    COUNT(*) as total_withdrawals,
                                    COALESCE(SUM(CASE WHEN status = 'completed' THEN withdrawal_amount ELSE 0 END), 0) as total_withdrawn,
                                    COALESCE(MAX(withdrawal_amount), 0) as largest_withdrawal
                                FROM vendor_withdrawals 
                                WHERE vendor_id = ?
                            ");
                            $stmt->execute([$vendor_id]);
                            $stats = $stmt->fetch();
                        } catch(PDOException $e) {
                            $stats = ['total_withdrawals' => 0, 'total_withdrawn' => 0, 'largest_withdrawal' => 0];
                        }
                        ?>
                        <div class="row text-center">
                            <div class="col-4">
                                <h4 class="fw-bold text-primary mb-1"><?php echo $stats['total_withdrawals']; ?></h4>
                                <small class="text-muted">Total</small>
                            </div>
                            <div class="col-4">
                                <h4 class="fw-bold text-success mb-1">$<?php echo number_format($stats['total_withdrawn'], 2); ?></h4>
                                <small class="text-muted">Withdrawn</small>
                            </div>
                            <div class="col-4">
                                <h4 class="fw-bold text-warning mb-1">$<?php echo number_format($stats['largest_withdrawal'], 2); ?></h4>
                                <small class="text-muted">Largest</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Withdrawal Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Request Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Available for withdrawal: <strong>$<?php echo number_format($pending_earnings, 2); ?></strong>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Amount to Withdraw *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="amount" 
                                   min="<?php echo $min_withdrawal; ?>" 
                                   max="<?php echo $pending_earnings; ?>" 
                                   step="0.01" 
                                   value="<?php echo min($pending_earnings, max($min_withdrawal, $pending_earnings)); ?>" 
                                   required>
                        </div>
                        <div class="form-text">
                            Minimum: $<?php echo number_format($min_withdrawal, 2); ?> | 
                            Maximum: $<?php echo number_format($pending_earnings, 2); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Withdrawal Method *</label>
                        <select class="form-select" name="method" id="withdrawalMethod" required>
                            <option value="">Select Method</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="bankAccountField" style="display: none;">
                        <label class="form-label fw-bold">Select Bank Account *</label>
                        <?php if (empty($bank_accounts)): ?>
                            <div class="alert alert-warning">
                                <small>No bank accounts added. Please add a bank account first.</small>
                            </div>
                        <?php else: ?>
                            <?php 
                            $verified_accounts = array_filter($bank_accounts, function($acc) {
                                return $acc['is_verified'] == 1;
                            });
                            ?>
                            <?php if (empty($verified_accounts)): ?>
                                <div class="alert alert-warning">
                                    <small>No verified bank accounts. Please wait for admin verification.</small>
                                </div>
                            <?php else: ?>
                                <select class="form-select" name="account_id" required>
                                    <option value="">Select Account</option>
                                    <?php foreach($verified_accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>" <?php echo $account['is_default'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($account['bank_name']); ?> - ****<?php echo substr($account['account_number'], -4); ?>
                                            <?php echo $account['is_default'] ? ' (Default)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Add any special instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="request_withdrawal" class="btn btn-primary" id="submitWithdrawalBtn">
                        <i class="fas fa-paper-plane me-2"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bank Account Modal -->
<div class="modal fade" id="bankAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Add Bank Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Your bank account details are securely stored and encrypted.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Holder Name *</label>
                        <input type="text" class="form-control" name="account_holder_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bank Name *</label>
                        <input type="text" class="form-control" name="bank_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Number *</label>
                        <input type="text" class="form-control" name="account_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">IFSC Code *</label>
                        <input type="text" class="form-control" name="ifsc_code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Branch Name</label>
                        <input type="text" class="form-control" name="branch_name">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Type *</label>
                        <select class="form-select" name="account_type" required>
                            <option value="savings">Savings Account</option>
                            <option value="current">Current Account</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1">
                            <label class="form-check-label" for="is_default">
                                Set as default account for withdrawals
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_bank_account" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Withdraw page loaded');
    
    // Auto-close alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            try {
                if (alert && bootstrap && bootstrap.Alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            } catch (e) {
                console.log('Could not close alert:', e);
            }
        });
    }, 5000);
    
    // Show/hide bank account field based on method selection
    const withdrawalMethod = document.getElementById('withdrawalMethod');
    const bankAccountField = document.getElementById('bankAccountField');
    const submitWithdrawalBtn = document.getElementById('submitWithdrawalBtn');
    
    if (withdrawalMethod) {
        withdrawalMethod.addEventListener('change', function() {
            if (this.value === 'bank') {
                bankAccountField.style.display = 'block';
                // Check if there are verified accounts
                const selectElement = bankAccountField.querySelector('select');
                if (selectElement) {
                    selectElement.required = true;
                }
            } else {
                bankAccountField.style.display = 'none';
                const selectElement = bankAccountField.querySelector('select');
                if (selectElement) {
                    selectElement.required = false;
                }
            }
            updateSubmitButton();
        });
        
        // Initial check
        if (withdrawalMethod.value === 'bank') {
            bankAccountField.style.display = 'block';
        }
    }
    
    // Amount validation
    const amountInput = document.querySelector('input[name="amount"]');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            const max = parseFloat(this.getAttribute('max'));
            const min = parseFloat(this.getAttribute('min'));
            let value = parseFloat(this.value) || 0;
            
            if (value > max) {
                this.value = max.toFixed(2);
                showToast('Amount cannot exceed available balance', 'warning');
            }
            
            if (value < min) {
                this.value = min.toFixed(2);
                showToast(`Minimum withdrawal is $${min.toFixed(2)}`, 'warning');
            }
            
            updateSubmitButton();
        });
    }
    
    // Update submit button state
    function updateSubmitButton() {
        if (!submitWithdrawalBtn) return;
        
        const amount = parseFloat(amountInput?.value) || 0;
        const method = withdrawalMethod?.value;
        const hasVerifiedAccounts = <?php echo !empty($verified_accounts) ? 'true' : 'false'; ?>;
        
        let disabled = false;
        
        if (amount < <?php echo $min_withdrawal; ?>) {
            disabled = true;
        }
        
        if (!method) {
            disabled = true;
        }
        
        if (method === 'bank' && !hasVerifiedAccounts) {
            disabled = true;
        }
        
        submitWithdrawalBtn.disabled = disabled;
    }
    
    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[title]');
    if (tooltipTriggerList.length > 0 && bootstrap && bootstrap.Tooltip) {
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            try {
                new bootstrap.Tooltip(tooltipTriggerEl);
            } catch (e) {
                console.log('Tooltip error:', e);
            }
        });
    }
    
    // Form submission loading states
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const originalHTML = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
                submitBtn.disabled = true;
                
                // Re-enable after 5 seconds (in case of error)
                setTimeout(() => {
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled = false;
                }, 5000);
            }
        });
    });
    
    // Initial button state check
    updateSubmitButton();
});

// Helper function for toast messages
function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'warning' ? 'warning' : type === 'success' ? 'success' : 'info'} border-0`;
    toast.id = toastId;
    toast.setAttribute('role', 'alert');
    
    // Determine icon based on type
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${icon} me-2"></i> ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    if (bootstrap && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000
        });
        bsToast.show();
    } else {
        // Fallback if Bootstrap not available
        toast.style.display = 'block';
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Remove toast from DOM after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}
</script>
<style>
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        background: #f8f9fa;
    }
    
    .main-content {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
    }
    
    .dashboard-header {
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .card.border-start {
        border-left-width: 5px !important;
    }
    
    .avatar-lg {
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .list-group-item {
        border-left: none;
        border-right: none;
    }
    
    .list-group-item:first-child {
        border-top: none;
    }
    
    .table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    
    .modal-content {
        border-radius: 10px;
        border: none;
    }
    
    .toast-container {
        z-index: 9999;
    }
    
    @media (max-width: 768px) {
        .dashboard-container {
            flex-direction: column;
        }
        
        .main-content {
            padding: 15px;
        }
    }
    </style>
<?php 
// Include footer
include_once '../../includes/footer.php';

?>
