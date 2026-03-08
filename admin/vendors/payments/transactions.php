<?php
// admin/vendors/payments/transactions.php - Transaction History
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/admin-access-check.php';
require_once '../../includes/payments/admin_payment_processor.php';

$page_title = 'Transaction History';
require_once '../../includes/header.php';

$db = getDB();
$processor = new AdminPaymentProcessor($db);

// Handle filters
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : null;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get transactions based on type
$transactions = [];

if ($type === 'all' || $type === 'customer') {
    // Customer payments
    $sql = "
        SELECT 
            'customer' as transaction_type,
            pt.id,
            pt.transaction_id,
            pt.order_id,
            pt.user_id,
            u.full_name as user_name,
            u.email as user_email,
            pt.gateway,
            pt.amount,
            pt.status,
            pt.created_at,
            o.order_number
        FROM payment_transactions pt
        LEFT JOIN users u ON pt.user_id = u.id
        LEFT JOIN orders o ON pt.order_id = o.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status) {
        $sql .= " AND pt.status = ?";
        $params[] = $status;
    }
    
    if ($dateFrom) {
        $sql .= " AND DATE(pt.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $sql .= " AND DATE(pt.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if ($search) {
        $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR pt.transaction_id LIKE ? OR o.order_number LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $sql .= " ORDER BY pt.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($type === 'all' || $type === 'vendor') {
    // Vendor payouts
    $sql = "
        SELECT 
            'vendor' as transaction_type,
            ve.id,
            ve.transaction_id,
            ve.order_id,
            ve.vendor_id as user_id,
            u.full_name as user_name,
            u.email as user_email,
            'payout' as gateway,
            ve.amount,
            ve.status,
            ve.created_at,
            o.order_number
        FROM vendor_earnings ve
        LEFT JOIN users u ON ve.vendor_id = u.id
        LEFT JOIN orders o ON ve.order_id = o.id
        WHERE ve.status = 'paid'
    ";
    
    $params = [];
    
    if ($dateFrom) {
        $sql .= " AND DATE(ve.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $sql .= " AND DATE(ve.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if ($search) {
        $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR ve.transaction_id LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $sql .= " ORDER BY ve.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Sort by date descending
usort($transactions, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Calculate totals
$totalCustomerPayments = array_sum(array_filter($transactions, function($t) { return $t['transaction_type'] === 'customer'; }));
$totalVendorPayouts = array_sum(array_filter($transactions, function($t) { return $t['transaction_type'] === 'vendor'; }));
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-history me-2"></i>Transaction History</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-uppercase">Total Customer Payments</h6>
                    <h3 class="mb-0">$<?php echo number_format($totalCustomerPayments, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div name="card-body">
                    <h6 class="text-uppercase">Total Vendor Payouts</h6>
                    <h3 class="mb-0">$<?php echo number_format($totalVendorPayouts, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-uppercase">Total Transactions</h6>
                    <h3 class="mb-0"><?php echo count($transactions); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="customer" <?php echo $type === 'customer' ? 'selected' : ''; ?>>Customer Payments</option>
                        <option value="vendor" <?php echo $type === 'vendor' ? 'selected' : ''; ?>>Vendor Payouts</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Email, Transaction ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Transactions (<?php echo count($transactions); ?>)</h5>
            <button class="btn btn-success btn-sm" onclick="exportCSV()">
                <i class="fas fa-download me-1"></i>Export CSV
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($transactions)): ?>
                <div class="alert alert-info">No transactions found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Transaction ID</th>
                                <th>User</th>
                                <th>Order</th>
                                <th>Gateway</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td>#<?php echo $txn['id']; ?></td>
                                <td>
                                    <?php if ($txn['transaction_type'] === 'customer'): ?>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-shopping-cart me-1"></i>Payment
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-arrow-up me-1"></i>Payout
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo $txn['transaction_id'] ?? 'N/A'; ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($txn['user_name'] ?? 'Unknown'); ?>
                                    <br>
                                    <small class="text-muted"><?php echo $txn['user_email'] ?? ''; ?></small>
                                </td>
                                <td>
                                    <?php if ($txn['order_number']): ?>
                                        <a href="../orders/view_order.php?id=<?php echo $txn['order_id']; ?>">
                                            <?php echo $txn['order_number']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $gatewayIcons = [
                                        'paypal' => 'fa-paypal',
                                        'stripe' => 'fa-stripe',
                                        'bank' => 'fa-university',
                                        'cod' => 'fa-money-bill',
                                        'payout' => 'fa-hand-holding-usd'
                                    ];
                                    $icon = $gatewayIcons[$txn['gateway']] ?? 'fa-credit-card';
                                    ?>
                                    <i class="fas <?php echo $icon; ?> me-1"></i>
                                    <?php echo ucfirst($txn['gateway']); ?>
                                </td>
                                <td>
                                    <strong class="<?php echo $txn['transaction_type'] === 'customer' ? 'text-success' : 'text-warning'; ?>">
                                        <?php echo $txn['transaction_type'] === 'customer' ? '+' : '-'; ?>$<?php echo number_format($txn['amount'], 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'completed' => 'success',
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        'cancelled' => 'secondary'
                                    ][$txn['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($txn['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y h:i A', strtotime($txn['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#transactionsTable').DataTable({
        "order": [[8, "desc"]],
        "pageLength": 25
    });
});

function exportCSV() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}

// Handle export parameter
<?php if (isset($_GET['export']) && $_GET['export'] === 'csv'): ?>
(function() {
    const table = document.getElementById('transactionsTable');
    let csv = [];
    
    // Headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => headers.push(th.textContent.trim()));
    csv.push(headers.join(','));
    
    // Rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            row.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
        });
        csv.push(row.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'transactions_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
})();
<?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>

