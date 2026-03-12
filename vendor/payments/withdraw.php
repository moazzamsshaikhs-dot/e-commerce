<?php
// vendor/payments/withdraw.php - Vendor Withdrawal Request
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/vendor-access-check.php';

$page_title = 'Request Withdrawal';
require_once '../../includes/header.php';

$db = getDB();
$vendorId = $_SESSION['user_id'];

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $methodId = (int)$_POST['method_id'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    // Validate
    if ($amount <= 0) {
        $message = 'Please enter a valid amount.';
        $messageType = 'danger';
    } elseif (!$methodId) {
        $message = 'Please select a payment method.';
        $messageType = 'danger';
    } else {
        // Check vendor's available balance
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount
            FROM vendor_earnings 
            WHERE vendor_id = ?
        ");
        $stmt->execute([$vendorId]);
        $paidAmount = $stmt->fetchColumn();
        
        // Check already withdrawn
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(request_amount), 0) as withdrawn
            FROM vendor_withdrawal_requests 
            WHERE vendor_id = ? AND status IN ('pending', 'processing', 'completed')
        ");
        $stmt->execute([$vendorId]);
        $withdrawnAmount = $stmt->fetchColumn();
        
        $availableBalance = $paidAmount - $withdrawnAmount;
        
        if ($amount > $availableBalance) {
            $message = 'Insufficient balance. Your available balance is $' . number_format($availableBalance, 2);
            $messageType = 'danger';
        } else {
            // Check minimum withdrawal
            $minWithdrawal = 10; // Minimum $10
            if ($amount < $minWithdrawal) {
                $message = 'Minimum withdrawal amount is $' . $minWithdrawal;
                $messageType = 'danger';
            } else {
                // Check if method belongs to vendor
                $stmt = $db->prepare("
                    SELECT * FROM vendors_payment_methods 
                    WHERE id = ? AND vendor_id = ? AND is_active = 1
                ");
                $stmt->execute([$methodId, $vendorId]);
                $method = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$method) {
                    $message = 'Invalid payment method selected.';
                    $messageType = 'danger';
                } else {
                    // Create withdrawal request
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO vendor_withdrawal_requests 
                            (vendor_id, request_amount, withdrawal_method, payment_method_id, notes, status, created_at)
                            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([$vendorId, $amount, $method['method_type'], $methodId, $notes]);
                        
                        $message = 'Withdrawal request submitted successfully!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}

// Get vendor's balance info
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as total_pending
    FROM vendor_earnings 
    WHERE vendor_id = ?
");
$stmt->execute([$vendorId]);
$balance = $stmt->fetch(PDO::FETCH_ASSOC);

// Get already withdrawn amount
$stmt = $db->prepare("
    SELECT COALESCE(SUM(request_amount), 0) as withdrawn
    FROM vendor_withdrawal_requests 
    WHERE vendor_id = ? AND status IN ('pending', 'processing', 'completed')
");
$stmt->execute([$vendorId]);
$withdrawnAmount = $stmt->fetchColumn();

$availableBalance = $balance['total_paid'] - $withdrawnAmount;

// Get payment methods
$stmt = $db->prepare("
    SELECT * FROM vendors_payment_methods 
    WHERE vendor_id = ? AND is_active = 1
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$vendorId]);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get method details
foreach ($paymentMethods as &$method) {
    switch ($method['method_type']) {
        case 'bank':
            $stmt = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE vendor_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$vendorId]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'paypal':
            $stmt = $db->prepare("SELECT * FROM vendor_paypal_accounts WHERE vendor_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$vendorId]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'stripe':
            $stmt = $db->prepare("SELECT * FROM vendor_stripe_accounts WHERE vendor_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$vendorId]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'easypaisa':
        case 'jazzcash':
            $stmt = $db->prepare("SELECT * FROM vendor_mobile_accounts WHERE vendor_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$vendorId]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
    }
}
unset($method);

// Get recent withdrawal requests
$stmt = $db->prepare("
    SELECT * FROM vendor_withdrawal_requests 
    WHERE vendor_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$vendorId]);
$withdrawalHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-hand-holding-usd me-2"></i>Request Withdrawal</h2>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Balance Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-uppercase">Available Balance</h6>
                    <h3 class="mb-0">$<?php echo number_format($availableBalance, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="text-uppercase">Pending Earnings</h6>
                    <h3 class="mb-0">$<?php echo number_format($balance['total_pending'], 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-uppercase">Total Withdrawn</h6>
                    <h3 class="mb-0">$<?php echo number_format($withdrawnAmount, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Withdrawal Form -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Withdrawal Request</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($paymentMethods)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You need to add a payment method before requesting a withdrawal.
                        </div>
                        <a href="settings.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Add Payment Method
                        </a>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Amount ($)</label>
                                <input type="number" name="amount" class="form-control" 
                                       step="0.01" min="10" max="<?php echo $availableBalance; ?>" 
                                       placeholder="Enter amount (min $10)" required>
                                <small class="text-muted">
                                    Available: $<?php echo number_format($availableBalance, 2); ?>
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="method_id" class="form-select" required>
                                    <option value="">Select payment method</option>
                                    <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?php echo $method['id']; ?>">
                                        <?php echo ucfirst($method['method_type']); ?> - 
                                        <?php 
                                        if ($method['method_type'] === 'bank' && isset($method['details']['bank_name'])) {
                                            echo htmlspecialchars($method['details']['bank_name'] . ' (' . substr($method['details']['account_number'], -4) . ')');
                                        } elseif ($method['method_type'] === 'paypal' && isset($method['details']['paypal_email'])) {
                                            echo htmlspecialchars($method['details']['paypal_email']);
                                        } elseif (in_array($method['method_type'], ['easypaisa', 'jazzcash']) && isset($method['details']['mobile_number'])) {
                                            echo htmlspecialchars($method['details']['mobile_number']);
                                        } else {
                                            echo 'Connected';
                                        }
                                        ?>
                                        <?php if ($method['is_default']): ?> (Default)<?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Any special instructions..."></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> 
                                <ul class="mb-0">
                                    <li>Minimum withdrawal amount: $10</li>
                                    <li>Processing time: 2-5 business days</li>
                                    <li>A verification may be required for first withdrawals</li>
                                </ul>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Withdrawals -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Withdrawal History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($withdrawalHistory)): ?>
                        <div class="alert alert-info">No withdrawal history yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($withdrawalHistory as $withdrawal): ?>
                                    <tr>
                                        <td><small><?php echo date('d M Y', strtotime($withdrawal['created_at'])); ?></small></td>
                                        <td>
                                            <strong>$<?php echo number_format($withdrawal['request_amount'], 2); ?></strong>
                                        </td>
                                        <td><small><?php echo ucfirst($withdrawal['withdrawal_method']); ?></small></td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'completed' => 'success',
                                                'rejected' => 'danger',
                                                'cancelled' => 'secondary'
                                            ][$withdrawal['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($withdrawal['status']); ?>
                                            </span>
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
</div>

<?php require_once '../../includes/footer.php'; ?>

