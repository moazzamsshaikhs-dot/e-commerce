<?php
// vendor/payments/dashboard.php - Vendor Payment Dashboard
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/vendor-access-check.php';

$page_title = 'Payment Dashboard';
require_once '../includes/header.php';

$db = getDB();
$vendorId = $_SESSION['user_id'];

// Get vendor earnings summary
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(amount), 0) as total_earnings,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount,
        COUNT(DISTINCT order_id) as total_orders
    FROM vendor_earnings 
    WHERE vendor_id = ?
");
$stmt->execute([$vendorId]);
$earningsSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent earnings
$stmt = $db->prepare("
    SELECT 
        ve.*,
        o.order_number,
        p.name as product_name
    FROM vendor_earnings ve
    LEFT JOIN orders o ON ve.order_id = o.id
    LEFT JOIN products p ON ve.product_id = p.id
    WHERE ve.vendor_id = ?
    ORDER BY ve.created_at DESC
    LIMIT 10
");
$stmt->execute([$vendorId]);
$recentEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending withdrawals
$stmt = $db->prepare("
    SELECT * FROM vendor_withdrawal_requests 
    WHERE vendor_id = ? AND status IN ('pending', 'processing')
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$vendorId]);
$pendingWithdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment methods
$stmt = $db->prepare("
    SELECT * FROM vendors_payment_methods 
    WHERE vendor_id = ? AND is_active = 1
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$vendorId]);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vendor's bank account details
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

// Calculate available balance (earnings - withdrawn)
$availableBalance = $earningsSummary['total_earnings'] - $earningsSummary['paid_amount'];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-wallet me-2"></i>Payment Dashboard</h2>
        <div>
            <a href="withdraw.php" class="btn btn-warning me-2">
                <i class="fas fa-hand-holding-usd me-1"></i>Request Withdrawal
            </a>
            <a href="settings.php" class="btn btn-primary">
                <i class="fas fa-cog me-1"></i>Payment Settings
            </a>
        </div>
    </div>
    
    <!-- Balance Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Earnings</h6>
                            <h3 class="mb-0">$<?php echo number_format($earningsSummary['total_earnings'], 2); ?></h3>
                        </div>
                        <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Paid Amount</h6>
                            <h3 class="mb-0">$<?php echo number_format($earningsSummary['paid_amount'], 2); ?></h3>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Pending</h6>
                            <h3 class="mb-0">$<?php echo number_format($earningsSummary['pending_amount'], 2); ?></h3>
                        </div>
                        <i class="fas fa-clock fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Orders</h6>
                            <h3 class="mb-0"><?php echo $earningsSummary['total_orders']; ?></h3>
                        </div>
                        <i class="fas fa-shopping-bag fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Earnings -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Earnings</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentEarnings)): ?>
                        <div class="alert alert-info">No earnings yet. Start selling to earn!</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEarnings as $earning): ?>
                                    <tr>
                                        <td>
                                            <?php if ($earning['order_number']): ?>
                                                <a href="../orders/view.php?id=<?php echo $earning['order_id']; ?>">
                                                    <?php echo $earning['order_number']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($earning['product_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <strong class="text-success">$<?php echo number_format($earning['amount'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'pending' => 'warning',
                                                'paid' => 'success',
                                                'failed' => 'danger'
                                            ][$earning['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($earning['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($earning['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="earnings.php" class="btn btn-outline-primary">View All Earnings</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Payment Methods -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Methods</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($paymentMethods)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No payment method added yet.
                        </div>
                        <a href="settings.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Add Payment Method
                        </a>
                    <?php else: ?>
                        <?php foreach ($paymentMethods as $method): ?>
                        <div class="d-flex align-items-center mb-3 p-2 border rounded">
                            <i class="fas <?php 
                                echo $method['method_type'] === 'bank' ? 'fa-university' : 
                                    ($method['method_type'] === 'paypal' ? 'fa-paypal' : 
                                    ($method['method_type'] === 'stripe' ? 'fa-stripe' : 'fa-mobile-alt'));
                            ?> fa-2x me-3 text-primary"></i>
                            <div class="flex-grow-1">
                                <strong><?php echo ucfirst($method['method_type']); ?></strong>
                                <?php if ($method['is_default']): ?>
                                    <span class="badge bg-primary ms-1">Default</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">
                                    <?php 
                                    if ($method['method_type'] === 'bank' && isset($method['details']['bank_name'])) {
                                        echo htmlspecialchars($method['details']['bank_name']);
                                    } elseif ($method['method_type'] === 'paypal' && isset($method['details']['paypal_email'])) {
                                        echo htmlspecialchars($method['details']['paypal_email']);
                                    } elseif (in_array($method['method_type'], ['easypaisa', 'jazzcash']) && isset($method['details']['mobile_number'])) {
                                        echo htmlspecialchars($method['details']['mobile_number']);
                                    } else {
                                        echo 'Connected';
                                    }
                                    ?>
                                </small>
                            </div>
                            <?php if ($method['is_verified']): ?>
                                <i class="fas fa-check-circle text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-clock text-warning"></i>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <a href="settings.php" class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-cog me-1"></i>Manage Methods
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Withdrawals -->
            <?php if (!empty($pendingWithdrawals)): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-hourglass-half me-2"></i>Pending Withdrawals</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <strong>$<?php echo number_format($withdrawal['request_amount'], 2); ?></strong>
                            <span class="badge bg-<?php echo $withdrawal['status'] === 'processing' ? 'info' : 'warning'; ?>">
                                <?php echo ucfirst($withdrawal['status']); ?>
                            </span>
                        </div>
                        <small class="text-muted"><?php echo date('d M Y', strtotime($withdrawal['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="withdraw.php" class="btn btn-warning w-100 mb-2">
                        <i class="fas fa-hand-holding-usd me-2"></i>Request Withdrawal
                    </a>
                    <a href="settings.php" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-plus-circle me-2"></i>Add Payment Method
                    </a>
                    <a href="../earnings/history.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-file-alt me-2"></i>View Earnings History
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

