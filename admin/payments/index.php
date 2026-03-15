<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

requireSystemAdmin();

$page_title = 'Payments Management';
require_once '../includes/header.php';

$db = getDB();

// Date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get summary statistics
$summary = [];

// Total revenue (completed orders)
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT id) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_order_value
    FROM orders 
    WHERE payment_status = 'completed' 
    AND DATE(order_date) BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$summary['orders'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Payment methods breakdown
$stmt = $db->prepare("
    SELECT 
        payment_method,
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM orders 
    WHERE payment_status = 'completed'
    AND DATE(order_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total DESC
");
$stmt->execute([$start_date, $end_date]);
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent transactions
$stmt = $db->prepare("
    SELECT 
        pt.*,
        u.full_name as user_name,
        u.email as user_email,
        o.order_number,
        o.id as order_id
    FROM payment_transactions pt
    LEFT JOIN users u ON pt.user_id = u.id
    LEFT JOIN orders o ON pt.invoice_id = o.id
    WHERE DATE(pt.created_at) BETWEEN ? AND ?
    ORDER BY pt.created_at DESC
    LIMIT 50
");
$stmt->execute([$start_date, $end_date]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Admin accounts balance
$stmt = $db->query("
    SELECT 
        account_type,
        COUNT(*) as count,
        COALESCE(SUM(current_balance), 0) as total_balance,
        COALESCE(SUM(total_credited), 0) as total_credited,
        COALESCE(SUM(total_debited), 0) as total_debited
    FROM admin_accounts 
    WHERE is_active = 1
    GROUP BY account_type
    ORDER BY total_balance DESC
");
$admin_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending withdrawals
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(request_amount), 0) as total
    FROM vendor_withdrawal_requests 
    WHERE status = 'pending'
");
$stmt->execute();
$pending_withdrawals = $stmt->fetch(PDO::FETCH_ASSOC);

// Chart data for last 30 days - FIXED
$chart_data = [
    'dates' => [],
    'orders' => [],
    'revenue' => []
];

$chart_stmt = $db->prepare("
    SELECT 
        DATE(order_date) as date,
        COUNT(*) as orders,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM orders 
    WHERE payment_status = 'completed'
    AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(order_date)
    ORDER BY date ASC
");
$chart_stmt->execute();
$chart_results = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of dates for the last 30 days
$dates_map = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates_map[$date] = [
        'orders' => 0,
        'revenue' => 0,
        'formatted' => date('M d', strtotime($date))
    ];
}

// Fill in actual data
foreach ($chart_results as $row) {
    if (isset($dates_map[$row['date']])) {
        $dates_map[$row['date']]['orders'] = (int)$row['orders'];
        $dates_map[$row['date']]['revenue'] = (float)$row['revenue'];
    }
}

// Prepare chart data
foreach ($dates_map as $date => $data) {
    $chart_data['dates'][] = $data['formatted'];
    $chart_data['orders'][] = $data['orders'];
    $chart_data['revenue'][] = $data['revenue'];
}

// Payment methods for chart - ensure we have data
$payment_method_labels = [];
$payment_method_totals = [];

if (!empty($payment_methods)) {
    foreach ($payment_methods as $method) {
        $payment_method_labels[] = ucfirst(str_replace('_', ' ', $method['payment_method']));
        $payment_method_totals[] = (float)$method['total'];
    }
} else {
    // Default data if no payment methods
    $payment_method_labels = ['No Data'];
    $payment_method_totals = [1];
}

// Encode for JavaScript - with json_encode options
$chart_data_json = json_encode($chart_data);
$payment_method_labels_json = json_encode($payment_method_labels);
$payment_method_totals_json = json_encode($payment_method_totals);
?>

<style>
:root {
    --primary: #4361ee;
    --success: #06d6a0;
    --warning: #ffb703;
    --danger: #ef476f;
    --info: #4cc9f0;
    --dark: #2b2d42;
    --light: #f8f9fa;
}

/* Card Styles */
.stat-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
    position: relative;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stat-card .card-body {
    padding: 1.5rem;
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .stat-change {
    font-size: 0.85rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    display: inline-block;
}

.stat-card.primary-gradient {
    background: linear-gradient(135deg, var(--primary), #3a0ca3);
    color: white;
}

.stat-card.success-gradient {
    background: linear-gradient(135deg, var(--success), #0ca678);
    color: white;
}

.stat-card.warning-gradient {
    background: linear-gradient(135deg, var(--warning), #f77f00);
    color: white;
}

.stat-card.danger-gradient {
    background: linear-gradient(135deg, var(--danger), #d62828);
    color: white;
}

.stat-card.info-gradient {
    background: linear-gradient(135deg, var(--info), #0096c7);
    color: white;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table thead th {
    background: linear-gradient(135deg, var(--dark), #1e1f2b);
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 1rem;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

.table tbody tr:hover {
    background: rgba(67, 97, 238, 0.05);
}

/* Status Badges */
.status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.status-completed, .status-approved, .status-success {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.status-pending, .status-processing {
    background: rgba(255, 183, 3, 0.15);
    color: var(--warning);
    border: 1px solid rgba(255, 183, 3, 0.3);
}

.status-failed, .status-rejected, .status-cancelled {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.status-refunded {
    background: rgba(76, 201, 240, 0.15);
    color: var(--info);
    border: 1px solid rgba(76, 201, 240, 0.3);
}

/* Button Styles */
.btn-primary {
    background: var(--primary);
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #3a0ca3;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
}

.btn-outline-primary {
    color: var(--primary);
    border-color: var(--primary);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
}

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}

.filter-section .form-control, 
.filter-section .form-select {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    padding: 0.6rem 1rem;
}

.filter-section .form-control:focus,
.filter-section .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
}

/* Chart Container */
.chart-container {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
    position: relative;
    min-height: 400px;
}

/* Payment Method Icons */
.payment-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.icon-bank { background: linear-gradient(135deg, #2b2d42, #1e1f2b); }
.icon-paypal { background: linear-gradient(135deg, #003087, #009cde); }
.icon-stripe { background: linear-gradient(135deg, #6772e5, #555abf); }
.icon-easypaisa { background: linear-gradient(135deg, #1a4d2e, #2e7d32); }
.icon-jazzcash { background: linear-gradient(135deg, #ed1c24, #b31217); }
.icon-cod { background: linear-gradient(135deg, #06d6a0, #0ca678); }

/* Loading Spinner */
.spinner-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    display: none;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
    color: var(--primary);
}

/* Export Modal */
.modal-content {
    border-radius: 15px;
    border: none;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary), #3a0ca3);
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 1.5rem;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: none;
    padding: 1.5rem;
}

/* Account Cards */
.account-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.account-card:hover {
    border-color: var(--primary);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.1);
}

.account-card .balance {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}

/* Print Styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    .stat-card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .table-container {
        break-inside: avoid;
        box-shadow: none;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2><i class="fas fa-credit-card me-3 text-primary"></i>Payments Management</h2>
        <div>
            <button class="btn btn-primary me-2" onclick="showExportModal()">
                <i class="fas fa-file-export me-2"></i>Export Report
            </button>
            <button class="btn btn-success me-2" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <a href="accounts.php" class="btn btn-outline-primary">
                <i class="fas fa-university me-2"></i>Manage Accounts
            </a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="filter-section no-print">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label fw-bold">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Apply
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="setDateRange('today')">
                    Today
                </button>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="setDateRange('week')">
                    This Week
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card primary-gradient">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">$<?php echo number_format($summary['orders']['total_revenue'] ?? 0, 2); ?></div>
                            <div class="stat-label text-white-50">Total Revenue</div>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="stat-change bg-white bg-opacity-25 text-white">
                            <i class="fas fa-chart-line me-1"></i>
                            Avg: $<?php echo number_format($summary['orders']['avg_order_value'] ?? 0, 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card success-gradient">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($summary['orders']['total_orders'] ?? 0); ?></div>
                            <div class="stat-label text-white-50">Completed Orders</div>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="stat-change bg-white bg-opacity-25 text-white">
                            <i class="fas fa-calendar me-1"></i>
                            Selected Period
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card warning-gradient">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">$<?php echo number_format($pending_withdrawals['total'] ?? 0, 2); ?></div>
                            <div class="stat-label text-white-50">Pending Withdrawals</div>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="stat-change bg-white bg-opacity-25 text-white">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $pending_withdrawals['count'] ?? 0; ?> requests
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card info-gradient">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">$<?php echo number_format(array_sum(array_column($admin_accounts, 'total_balance')), 2); ?></div>
                            <div class="stat-label text-white-50">Account Balance</div>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="fas fa-university"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="stat-change bg-white bg-opacity-25 text-white">
                            <i class="fas fa-arrow-up me-1"></i>
                            <?php echo count($admin_accounts); ?> active accounts
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart and Payment Methods - FIXED -->
    <div class="row mb-4 no-print">
        <div class="col-xl-8 mb-4">
            <div class="chart-container">
                <h5 class="mb-4"><i class="fas fa-chart-line me-2 text-primary"></i>Revenue Trend (Last 30 Days)</h5>
                <canvas id="revenueChart" height="300"></canvas>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="chart-container">
                <h5 class="mb-4"><i class="fas fa-chart-pie me-2 text-primary"></i>Payment Methods</h5>
                <canvas id="paymentChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Admin Accounts Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-container">
                <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-university me-2 text-primary"></i>Admin Accounts Summary</h5>
                    <button class="btn btn-sm btn-outline-primary no-print" onclick="printSection('accounts')">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Account Type</th>
                                <th>Count</th>
                                <th>Current Balance</th>
                                <th>Total Credited</th>
                                <th>Total Debited</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admin_accounts as $account): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="payment-icon icon-<?php echo $account['account_type']; ?> me-3">
                                            <?php
                                            $icons = [
                                                'bank' => 'university',
                                                'paypal' => 'fa-paypal',
                                                'stripe' => 'fa-stripe',
                                                'easypaisa' => 'mobile-alt',
                                                'jazzcash' => 'mobile-alt'
                                                // 'visa' => 'fa-visa'
                                            ];
                                            $icon = $icons[$account['account_type']] ?? 'credit-card';
                                            ?>
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo ucfirst($account['account_type']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $account['count']; ?></td>
                                <td class="fw-bold text-primary">$<?php echo number_format($account['total_balance'], 2); ?></td>
                                <td class="text-success">$<?php echo number_format($account['total_credited'], 2); ?></td>
                                <td class="text-danger">$<?php echo number_format($account['total_debited'], 2); ?></td>
                                <td class="no-print">
                                    <a href="accounts.php?type=<?php echo $account['account_type']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="table-container">
                <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2 text-primary"></i>Recent Transactions</h5>
                    <div class="no-print">
                        <button class="btn btn-sm btn-outline-success me-2" onclick="exportTransactions()">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="printSection('transactions')">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="refreshTransactions()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Gateway</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Order #</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                    No transactions found for selected period
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?php echo substr($txn['gateway_transaction_id'] ?? $txn['id'], 0, 15); ?>...</small>
                                    </td>
                                    <td><?php echo date('d M Y H:i', strtotime($txn['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($txn['transaction_type'] ?? 'payment'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($txn['user_name'] ?? 'N/A'); ?></div>
                                        <small class="text-muted"><?php echo $txn['user_email'] ?? ''; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <i class="fab fa-<?php echo $txn['gateway']; ?> me-1"></i>
                                            <?php echo ucfirst($txn['gateway']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold">$<?php echo number_format($txn['amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $txn['status']; ?>">
                                            <?php echo ucfirst($txn['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($txn['order_number'])): ?>
                                            <a href="../orders/view.php?id=<?php echo $txn['order_id']; ?>" class="text-decoration-none">
                                                <?php echo $txn['order_number']; ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <button class="btn btn-sm btn-outline-info" onclick="viewTransaction(<?php echo $txn['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-export me-2"></i>Export Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="export-payments.php" method="POST" target="_blank">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Report Type</label>
                        <select name="report_type" class="form-select" required>
                            <option value="transactions">Transactions Report</option>
                            <option value="payment_methods">Payment Methods Summary</option>
                            <option value="withdrawals">Withdrawals Report</option>
                            <option value="accounts">Accounts Balance Report</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date Range</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
                            </div>
                            <div class="col-6">
                                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Format</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatPDF" value="pdf" checked>
                                <label class="form-check-label" for="formatPDF">
                                    <i class="fas fa-file-pdf me-1 text-danger"></i> PDF
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatExcel" value="excel">
                                <label class="form-check-label" for="formatExcel">
                                    <i class="fas fa-file-excel me-1 text-success"></i> Excel
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatCSV" value="csv">
                                <label class="form-check-label" for="formatCSV">
                                    <i class="fas fa-file-csv me-1 text-primary"></i> CSV
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Include</label>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_summary" value="1" checked>
                                    <label class="form-check-label">Summary Statistics</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_charts" value="1" checked>
                                    <label class="form-check-label">Charts & Graphs</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_transactions" value="1" checked>
                                    <label class="form-check-label">Transaction List</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_accounts" value="1" checked>
                                    <label class="form-check-label">Account Details</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                Loading...
            </div>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div class="spinner-overlay" id="loadingSpinner">
    <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>
<!-- Charts Section - Professional -->
<div class="row mb-4 no-print">
    <div class="col-xl-8 mb-4">
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Revenue Trend (Last 30 Days)</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary chart-period active" data-period="revenue">Revenue</button>
                    <button type="button" class="btn btn-outline-primary chart-period" data-period="orders">Orders</button>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-4 mb-4">
        <div class="chart-container">
            <h5 class="mb-4"><i class="fas fa-chart-pie me-2 text-primary"></i>Payment Methods Distribution</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="paymentDistributionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 no-print">
    <div class="col-xl-6 mb-4">
        <div class="chart-container">
            <h5 class="mb-4"><i class="fas fa-chart-bar me-2 text-primary"></i>Daily Transaction Volume</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="transactionVolumeChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6 mb-4">
        <div class="chart-container">
            <h5 class="mb-4"><i class="fas fa-chart-pie me-2 text-primary"></i>Account Balances</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="accountBalanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Chart Data PHP -->
<?php
// 1. Revenue Trend Data (Last 30 Days)
$revenue_data = [
    'labels' => [],
    'revenue' => [],
    'orders' => []
];

// Get daily data for last 30 days
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $revenue_data['labels'][] = date('M d', strtotime($date));
    
    // Get data for this date
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as daily_revenue,
            COUNT(*) as daily_orders
        FROM orders 
        WHERE payment_status = 'completed' 
        AND DATE(order_date) = ?
    ");
    $stmt->execute([$date]);
    $daily = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $revenue_data['revenue'][] = (float)($daily['daily_revenue'] ?? 0);
    $revenue_data['orders'][] = (int)($daily['daily_orders'] ?? 0);
}

// 2. Payment Methods Distribution
$payment_distribution = [
    'labels' => [],
    'data' => [],
    'colors' => []
];

$payment_colors = [
    'paypal' => '#003087',
    'stripe' => '#6772e5',
    'bank' => '#2b2d42',
    'easypaisa' => '#1a4d2e',
    'jazzcash' => '#ed1c24',
    'cod' => '#06d6a0',
    'visa' => '#1a1f71',
    'mastercard' => '#f79e1b'
];

$payment_icons = [
    'paypal' => 'fab fa-paypal',
    'stripe' => 'fab fa-stripe',
    'bank' => 'fas fa-university',
    'easypaisa' => 'fas fa-mobile-alt',
    'jazzcash' => 'fas fa-mobile-alt',
    'cod' => 'fas fa-money-bill-wave',
    'visa' => 'fab fa-cc-visa',
    'mastercard' => 'fab fa-cc-mastercard'
];

// Get payment method totals
$stmt = $db->prepare("
    SELECT 
        payment_method,
        COUNT(*) as method_count,
        COALESCE(SUM(total_amount), 0) as method_total
    FROM orders 
    WHERE payment_status = 'completed'
    AND DATE(order_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY method_total DESC
");
$stmt->execute([$start_date, $end_date]);
$payment_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_payment_amount = 0;
foreach ($payment_totals as $pt) {
    $total_payment_amount += $pt['method_total'];
}

foreach ($payment_totals as $pt) {
    $method = $pt['payment_method'];
    $payment_distribution['labels'][] = ucfirst(str_replace('_', ' ', $method));
    $payment_distribution['data'][] = (float)$pt['method_total'];
    $payment_distribution['colors'][] = $payment_colors[$method] ?? '#6c757d';
    $payment_distribution['counts'][] = $pt['method_count'];
}

// 3. Daily Transaction Volume (Last 7 Days)
$volume_data = [
    'labels' => [],
    'count' => [],
    'amount' => []
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $volume_data['labels'][] = date('D', strtotime($date));
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as txn_count,
            COALESCE(SUM(amount), 0) as txn_amount
        FROM payment_transactions 
        WHERE status = 'completed'
        AND DATE(created_at) = ?
    ");
    $stmt->execute([$date]);
    $daily_txn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $volume_data['count'][] = (int)($daily_txn['txn_count'] ?? 0);
    $volume_data['amount'][] = (float)($daily_txn['txn_amount'] ?? 0);
}

// 4. Account Balances
$account_balances = [
    'labels' => [],
    'balances' => [],
    'colors' => [],
    'details' => []
];

$account_colors = [
    'paypal' => '#003087',
    'stripe' => '#6772e5',
    'bank' => '#2b2d42',
    'easypaisa' => '#1a4d2e',
    'jazzcash' => '#ed1c24',
    'visa' => '#1a1f71',
    'mastercard' => '#f79e1b'
];

$account_stmt = $db->query("
    SELECT 
        account_type,
        COUNT(*) as account_count,
        COALESCE(SUM(current_balance), 0) as total_balance,
        COALESCE(AVG(current_balance), 0) as avg_balance,
        COALESCE(MAX(current_balance), 0) as max_balance
    FROM admin_accounts 
    WHERE is_active = 1
    GROUP BY account_type
    ORDER BY total_balance DESC
");

while ($acc = $account_stmt->fetch(PDO::FETCH_ASSOC)) {
    $type = $acc['account_type'];
    $account_balances['labels'][] = ucfirst($type) . ' (' . $acc['account_count'] . ')';
    $account_balances['balances'][] = (float)$acc['total_balance'];
    $account_balances['colors'][] = $account_colors[$type] ?? '#6c757d';
    $account_balances['details'][$type] = [
        'count' => $acc['account_count'],
        'total' => $acc['total_balance'],
        'avg' => $acc['avg_balance'],
        'max' => $acc['max_balance']
    ];
}

// Get recent transactions for mini chart
$recent_stmt = $db->query("
    SELECT 
        DATE(created_at) as txn_date,
        COUNT(*) as txn_count
    FROM payment_transactions 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY txn_date ASC
");
$recent_txns = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Chart.js Script with Professional Configuration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Chart configuration
    Chart.defaults.font.family = "'Poppins', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#64748b';
    
    // 1. Revenue Trend Chart
    const revenueCtx = document.getElementById('revenueTrendChart')?.getContext('2d');
    if (revenueCtx) {
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenue_data['labels']); ?>,
                datasets: [
                    {
                        label: 'Revenue ($)',
                        data: <?php echo json_encode($revenue_data['revenue']); ?>,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: <?php echo json_encode($revenue_data['orders']); ?>,
                        borderColor: '#06d6a0',
                        backgroundColor: 'rgba(6, 214, 160, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#06d6a0',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#4361ee',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.label.includes('Revenue')) {
                                    label += '$' + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Revenue ($)',
                            color: '#64748b'
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Orders Count',
                            color: '#64748b'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                if (Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            }
        });
        
        // Period toggle functionality
        document.querySelectorAll('.chart-period').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-period').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const period = this.dataset.period;
                if (period === 'revenue') {
                    revenueChart.data.datasets[0].hidden = false;
                    revenueChart.data.datasets[1].hidden = true;
                } else {
                    revenueChart.data.datasets[0].hidden = true;
                    revenueChart.data.datasets[1].hidden = false;
                }
                revenueChart.update();
            });
        });
    }
    
    // 2. Payment Distribution Chart
    const paymentCtx = document.getElementById('paymentDistributionChart')?.getContext('2d');
    if (paymentCtx && <?php echo count($payment_distribution['data']); ?> > 0) {
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($payment_distribution['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($payment_distribution['data']); ?>,
                    backgroundColor: <?php echo json_encode($payment_distribution['colors']); ?>,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return {
                                            text: label + ': $' + value.toFixed(2) + ' (' + percentage + '%)',
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                const count = <?php echo json_encode($payment_distribution['counts'] ?? []); ?>[context.dataIndex] || 0;
                                return [
                                    label + ': $' + value.toFixed(2) + ' (' + percentage + '%)',
                                    'Transactions: ' + count
                                ];
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 3. Transaction Volume Chart
    const volumeCtx = document.getElementById('transactionVolumeChart')?.getContext('2d');
    if (volumeCtx) {
        new Chart(volumeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($volume_data['labels']); ?>,
                datasets: [
                    {
                        label: 'Transaction Count',
                        data: <?php echo json_encode($volume_data['count']); ?>,
                        backgroundColor: '#4361ee',
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Transaction Amount ($)',
                        data: <?php echo json_encode($volume_data['amount']); ?>,
                        backgroundColor: '#06d6a0',
                        borderRadius: 6,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rect'
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Transaction Count'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Amount ($)'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 4. Account Balance Chart
    const balanceCtx = document.getElementById('accountBalanceChart')?.getContext('2d');
    if (balanceCtx && <?php echo count($account_balances['balances']); ?> > 0) {
        new Chart(balanceCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($account_balances['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($account_balances['balances']); ?>,
                    backgroundColor: <?php echo json_encode($account_balances['colors']); ?>,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                const details = <?php echo json_encode($account_balances['details']); ?>;
                                const type = label.split(' ')[0].toLowerCase();
                                const detail = details[type] || {};
                                return [
                                    label + ': $' + value.toFixed(2) + ' (' + percentage + '%)',
                                    'Accounts: ' + (detail.count || 0),
                                    'Average: $' + (detail.avg || 0).toFixed(2),
                                    'Highest: $' + (detail.max || 0).toFixed(2)
                                ];
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<!-- Account Icons and Summary -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-credit-card me-2 text-primary"></i>Payment Gateways Overview</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
                    $gateway_stmt = $db->query("
                        SELECT 
                            account_type,
                            COUNT(*) as count,
                            SUM(current_balance) as total_balance,
                            SUM(is_default) as default_count
                        FROM admin_accounts 
                        WHERE is_active = 1
                        GROUP BY account_type
                        ORDER BY total_balance DESC
                    ");
                    
                    while ($gateway = $gateway_stmt->fetch(PDO::FETCH_ASSOC)):
                        $type = $gateway['account_type'];
                        $icon = $payment_icons[$type] ?? 'fas fa-credit-card';
                        $color = $payment_colors[$type] ?? '#6c757d';
                    ?>
                    <div class="col-md-3 col-6">
                        <div class="gateway-card p-3 rounded" style="border-left: 4px solid <?php echo $color; ?>;">
                            <div class="d-flex align-items-center mb-2">
                                <i class="<?php echo $icon; ?> me-2" style="color: <?php echo $color; ?>; font-size: 1.5rem;"></i>
                                <div>
                                    <h6 class="mb-0"><?php echo ucfirst($type); ?></h6>
                                    <small class="text-muted"><?php echo $gateway['count']; ?> account(s)</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary">$<?php echo number_format($gateway['total_balance'], 2); ?></span>
                                <?php if ($gateway['default_count'] > 0): ?>
                                    <span class="badge bg-success">Default</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gateway-card {
    background: white;
    border-radius: 10px;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.gateway-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.chart-period.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.chart-period {
    transition: all 0.3s ease;
}

.chart-period:hover {
    background: rgba(67, 97, 238, 0.1);
}
</style>
<?php require_once '../includes/footer.php'; ?>