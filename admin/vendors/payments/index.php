<?php
// admin/vendors/payments/index.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/admin-access-check.php';
require_once '../../includes/payments/admin_payment_processor.php';

$page_title = 'Vendor Payments';
require_once '../../includes/header.php';

$db = getDB();
$processor = new AdminPaymentProcessor($db);

// Get statistics
$stats = $processor->getDashboardStats();

// Get pending payments
$pendingPayments = $processor->getPendingVendorPayments();

// Get withdrawal requests
$withdrawals = $processor->getWithdrawalRequests('pending');

// Get admin accounts summary
$accountsSummary = $processor->getAdminAccountsSummary();

// Get vendor earnings summary
$vendorEarnings = $processor->getVendorEarningsSummary();
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-credit-card me-2"></i>Vendor Payments Management</h2>
        <div>
            <a href="withdraw.php" class="btn btn-warning me-2">
                <i class="fas fa-hand-holding-usd me-2"></i>Withdrawal Requests 
                <?php if (!empty($withdrawals)): ?>
                    <span class="badge bg-danger ms-1"><?php echo count($withdrawals); ?></span>
                <?php endif; ?>
            </a>
            <a href="methods.php" class="btn btn-info">
                <i class="fas fa-credit-card me-2"></i>Payment Methods
            </a>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Admin Balance</h6>
                            <h3 class="mb-0">$<?php echo number_format($stats['admin_balance'], 2); ?></h3>
                        </div>
                        <i class="fas fa-university fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Paid</h6>
                            <h3 class="mb-0">$<?php echo number_format($stats['total_paid'], 2); ?></h3>
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
                            <h3 class="mb-0">$<?php echo number_format($stats['total_pending'], 2); ?></h3>
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
                            <h6 class="text-uppercase">Vendors</h6>
                            <h3 class="mb-0"><?php echo $stats['vendors_with_earnings']; ?></h3>
                        </div>
                        <i class="fas fa-users fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Admin Accounts Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Admin Accounts Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (empty($accountsSummary)): ?>
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    No admin accounts found. <a href="../accounts.php" class="alert-link">Add accounts</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($accountsSummary as $account): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="text-uppercase text-muted">
                                            <i class="fas fa-<?php 
                                                echo $account['account_type'] == 'bank' ? 'university' : 
                                                    ($account['account_type'] == 'paypal' ? 'paypal' : 
                                                    ($account['account_type'] == 'stripe' ? 'stripe' : 'mobile-alt')); 
                                            ?> me-1"></i>
                                            <?php echo ucfirst($account['account_type']); ?>
                                        </h6>
                                        <h4 class="mb-2">$<?php echo number_format($account['total_balance'], 2); ?></h4>
                                        <div class="small">
                                            <div class="text-success">Credited: $<?php echo number_format($account['total_credited'], 2); ?></div>
                                            <div class="text-danger">Debited: $<?php echo number_format($account['total_debited'], 2); ?></div>
                                            <div>Accounts: <?php echo $account['total_accounts']; ?></div>
                                            <?php if ($account['last_transaction']): ?>
                                                <div class="text-muted mt-1">
                                                    Last: <?php echo date('d M', strtotime($account['last_transaction'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Payments & Withdrawals -->
    <div class="row mb-4">
        <!-- Pending Payments -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Payments</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingPayments)): ?>
                        <div class="p-4 text-center">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">No pending payments found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Vendor</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($pendingPayments, 0, 5) as $payment): ?>
                                    <tr>
                                        <td>
                                            <a href="../orders/view_order.php?id=<?php echo $payment['order_id']; ?>">
                                                #<?php echo $payment['order_number']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($payment['vendor_name']); ?></small>
                                        </td>
                                        <td><strong>$<?php echo number_format($payment['commission_amount'], 2); ?></strong></td>
                                        <td><small><?php echo date('d M', strtotime($payment['created_at'])); ?></small></td>
                                        <td>
                                            <button class="btn btn-sm btn-success pay-now" 
                                                    data-order="<?php echo $payment['order_id']; ?>"
                                                    data-vendor="<?php echo $payment['vendor_id']; ?>"
                                                    data-amount="<?php echo $payment['commission_amount']; ?>">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($pendingPayments) > 5): ?>
                            <div class="p-2 text-center">
                                <a href="pending.php" class="btn btn-sm btn-outline-warning">
                                    View All (<?php echo count($pendingPayments); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Withdrawal Requests -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i>Withdrawal Requests</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($withdrawals)): ?>
                        <div class="p-4 text-center">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No pending withdrawal requests.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($withdrawals, 0, 5) as $request): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo htmlspecialchars($request['vendor_name']); ?></small>
                                        </td>
                                        <td><strong>$<?php echo number_format($request['request_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($request['withdrawal_method']); ?></span>
                                        </td>
                                        <td><small><?php echo date('d M', strtotime($request['created_at'])); ?></small></td>
                                        <td>
                                            <a href="withdraw.php?id=<?php echo $request['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-500px"></i>
                                                withdraw
</a>
