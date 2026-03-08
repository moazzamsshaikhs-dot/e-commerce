<?php
// vendor/payments/dashboard.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    redirect('../index.php');
}

$page_title = 'Payments Dashboard';
require_once '../includes/header.php';

$db = getDB();
$vendorId = $_SESSION['user_id'];

// Get earnings summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT order_id) as total_orders,
        SUM(amount) as total_earnings,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        MAX(paid_date) as last_paid_date
    FROM vendor_earnings
    WHERE vendor_id = ?
");
$stmt->execute([$vendorId]);
$earnings = $stmt->fetch(PDO::FETCH_ASSOC);

// Get payment methods
$stmt = $db->prepare("
    SELECT * FROM vendors_payment_methods 
    WHERE vendor_id = ? 
    ORDER BY is_default DESC
");
$stmt->execute([$vendorId]);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent withdrawals
$stmt = $db->prepare("
    SELECT * FROM vendor_withdrawal_requests 
    WHERE vendor_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$vendorId]);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <h2>Payments Dashboard</h2>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Earnings</h6>
                    <h3>$<?php echo number_format($earnings['total_earnings'] ?? 0, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Paid Amount</h6>
                    <h3>$<?php echo number_format($earnings['paid_amount'] ?? 0, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Pending Amount</h6>
                    <h3>$<?php echo number_format($earnings['pending_amount'] ?? 0, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Total Orders</h6>
                    <h3><?php echo $earnings['total_orders'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="withdraw.php" class="btn btn-success me-2">
                        <i class="fas fa-hand-holding-usd me-2"></i>Request Withdrawal
                    </a>
                    <a href="methods.php" class="btn btn-info me-2">
                        <i class="fas fa-credit-card me-2"></i>Manage Payment Methods
                    </a>
                    <a href="transactions.php" class="btn btn-secondary">
                        <i class="fas fa-history me-2"></i>Transaction History
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Your Payment Methods</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($paymentMethods)): ?>
                        <div class="alert alert-warning">
                            No payment methods configured. 
                            <a href="methods.php" class="alert-link">Add one now</a>
                        </div>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($paymentMethods as $method): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-<?php echo $method['method_type'] == 'bank' ? 'university' : 'mobile-alt'; ?> me-2"></i>
                                    <?php echo ucfirst($method['method_type']); ?>
                                    <?php if ($method['is_default']): ?>
                                        <span class="badge bg-primary ms-2">Default</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($method['is_verified']): ?>
                                    <span class="badge bg-success">Verified</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending Verification</span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Withdrawals -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Recent Withdrawals</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($withdrawals)): ?>
                        <div class="alert alert-info">No withdrawal requests yet.</div>
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
                                    <?php foreach ($withdrawals as $w): ?>
                                    <tr>
                                        <td><?php echo date('d M', strtotime($w['created_at'])); ?></td>
                                        <td>$<?php echo number_format($w['request_amount'], 2); ?></td>
                                        <td><?php echo ucfirst($w['withdrawal_method']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $w['status'] == 'completed' ? 'success' : 
                                                    ($w['status'] == 'pending' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($w['status']); ?>
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

<?php require_once '../includes/footer.php'; ?>