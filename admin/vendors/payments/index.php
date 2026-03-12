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
                            <h3 class="mb-0">$<?php echo number_format($stats['admin_balance'] ?? 0, 2); ?></h3>
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
                            <h3 class="mb-0">$<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></h3>
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
                            <h3 class="mb-0">$<?php echo number_format($stats['total_pending'] ?? 0, 2); ?></h3>
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
                            <h3 class="mb-0"><?php echo $stats['vendors_with_earnings'] ?? 0; ?></h3>
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
                                    No admin accounts found. <a href="../settings/bank.php" class="alert-link">Add accounts</a>
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
                                        <h4 class="mb-2">$<?php echo number_format($account['total_balance'] ?? 0, 2); ?></h4>
                                        <div class="small">
                                            <div class="text-success">Credited: $<?php echo number_format($account['total_credited'] ?? 0, 2); ?></div>
                                            <div class="text-danger">Debited: $<?php echo number_format($account['total_debited'] ?? 0, 2); ?></div>
                                            <div>Accounts: <?php echo $account['total_accounts'] ?? 0; ?></div>
                                            <?php if (!empty($account['last_transaction'])): ?>
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
                                                #<?php echo $payment['order_number'] ?? $payment['order_id']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($payment['vendor_name'] ?? 'Unknown'); ?></small>
                                        </td>
                                        <td><strong>$<?php echo number_format($payment['commission_amount'] ?? $payment['amount'] ?? 0, 2); ?></strong></td>
                                        <td><small><?php echo date('d M', strtotime($payment['created_at'] ?? $payment['order_date'] ?? date('Y-m-d'))); ?></small></td>
                                        <td>
                                            <button class="btn btn-sm btn-success pay-now" 
                                                    data-order="<?php echo $payment['order_id']; ?>"
                                                    data-vendor="<?php echo $payment['vendor_id']; ?>"
                                                    data-amount="<?php echo $payment['commission_amount'] ?? $payment['amount'] ?? 0; ?>">
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
                                            <small><?php echo htmlspecialchars($request['vendor_name'] ?? 'Unknown'); ?></small>
                                        </td>
                                        <td><strong>$<?php echo number_format($request['request_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($request['withdrawal_method'] ?? 'bank'); ?></span>
                                        </td>
                                        <td><small><?php echo date('d M', strtotime($request['created_at'] ?? date('Y-m-d'))); ?></small></td>
                                        <td>
                                            <a href="withdraw.php?id=<?php echo $request['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($withdrawals) > 5): ?>
                            <div class="p-2 text-center">
                                <a href="withdraw.php" class="btn btn-sm btn-outline-info">
                                    View All (<?php echo count($withdrawals); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Vendor Earnings Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Vendor Earnings Summary</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($vendorEarnings)): ?>
                        <div class="alert alert-info">No vendor earnings found yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th>Total Orders</th>
                                        <th>Total Earnings</th>
                                        <th>Paid</th>
                                        <th>Pending</th>
                                        <th>Last Paid</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($vendorEarnings, 0, 10) as $earning): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($earning['vendor_name'] ?? 'Unknown'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($earning['email'] ?? $earning['vendor_email'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo $earning['total_orders'] ?? 0; ?></td>
                                        <td><strong><?php echo number_format($earning['total_earnings'] ?? 0, 2); ?></strong></td>
                                        <td><span class="text-success">$<?php echo number_format($earning['paid_amount'] ?? 0, 2); ?></span></td>
                                        <td><span class="text-warning">$<?php echo number_format($earning['pending_amount'] ?? 0, 2); ?></span></td>
                                        <td>
                                            <?php if (!empty($earning['last_paid_date'])): ?>
                                                <small><?php echo date('d M Y', strtotime($earning['last_paid_date'])); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Never</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="../view-vendor.php?id=<?php echo $earning['vendor_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="transactions.php?vendor=<?php echo $earning['vendor_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-history"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($vendorEarnings) > 10): ?>
                            <div class="p-2 text-center">
                                <a href="earnings.php" class="btn btn-sm btn-outline-success">
                                    View All Vendors (<?php echo count($vendorEarnings); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="transactions.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-history me-2"></i>View Transactions
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="methods.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-credit-card me-2"></i>Manage Payment Methods
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="../earnings/earnings.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-dollar-sign me-2"></i>Vendor Earnings
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="../settings/bank.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-university me-2"></i>Admin Bank Accounts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Processing Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Process Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="paymentContent">
                    <p><strong>Order:</strong> <span id="modalOrderId"></span></p>
                    <p><strong>Vendor:</strong> <span id="modalVendorName"></span></p>
                    <p><strong>Amount:</strong> <span id="modalAmount" class="text-success h4"></span></p>
                    <hr>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will process the payment to the vendor's default payment method.
                    </div>
                </div>
                <div id="paymentLoading" style="display: none;" class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Processing payment...</p>
                </div>
                <div id="paymentResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="confirmPayment">
                    <i class="fas fa-check me-2"></i>Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="paymentToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<script>
let currentPaymentData = null;

$(document).ready(function() {
    // Pay Now button click
    $('.pay-now').click(function() {
        const orderId = $(this).data('order');
        const vendorId = $(this).data('vendor');
        const amount = $(this).data('amount');
        const vendorName = $(this).closest('tr').find('td:eq(1)').text().trim();
        
        currentPaymentData = {
            order_id: orderId,
            vendor_id: vendorId,
            amount: amount
        };
        
        $('#modalOrderId').text('#' + orderId);
        $('#modalVendorName').text(vendorName);
        $('#modalAmount').text('$ ' + parseFloat(amount).toFixed(2));
        
        $('#paymentModal').modal('show');
    });
    
    // Confirm Payment
    $('#confirmPayment').click(function() {
        if (!currentPaymentData) return;
        
        $('#paymentContent').hide();
        $('#paymentLoading').show();
        $('#confirmPayment').prop('disabled', true);
        
        $.ajax({
            url: '../vendors/ajax/payment-handler.php',
            method: 'POST',
            data: {
                action: 'process_vendor_payment',
                vendor_id: currentPaymentData.vendor_id,
                order_id: currentPaymentData.order_id,
                amount: currentPaymentData.amount
            },
            dataType: 'json',
            success: function(response) {
                $('#paymentLoading').hide();
                
                if (response.success) {
                    $('#paymentResult').html(`
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            ${response.message}
                            <br>
                            <small class="text-muted">Transaction ID: ${response.transaction_id || 'N/A'}</small>
                        </div>
                    `);
                    
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#paymentResult').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            ${response.message}
                        </div>
                    `);
                    $('#paymentContent').show();
                }
            },
            error: function() {
                $('#paymentLoading').hide();
                $('#paymentResult').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        An error occurred while processing the payment.
                    </div>
                `);
                $('#paymentContent').show();
            },
            complete: function() {
                $('#confirmPayment').prop('disabled', false);
            }
        });
    });
    
    // Reset modal on close
    $('#paymentModal').on('hidden.bs.modal', function() {
        $('#paymentContent').show();
        $('#paymentLoading').hide();
        $('#paymentResult').html('');
        currentPaymentData = null;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

