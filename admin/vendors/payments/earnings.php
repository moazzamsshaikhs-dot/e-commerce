<?php
// admin/vendors/payments/earnings.php - Vendor Earnings History
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/admin-access-check.php';
require_once '../../includes/payments/admin_payment_processor.php';

$page_title = 'Vendor Earnings';
require_once '../../includes/header.php';

$db = getDB();
$processor = new AdminPaymentProcessor($db);

// Handle filters
$vendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Get vendor earnings
$sql = "
    SELECT 
        ve.*,
        u.full_name as vendor_name,
        u.email as vendor_email,
        u.username,
        o.order_number,
        o.created_at as order_date,
        p.name as product_name
    FROM vendor_earnings ve
    JOIN users u ON ve.vendor_id = u.id
    LEFT JOIN orders o ON ve.order_id = o.id
    LEFT JOIN products p ON ve.product_id = p.id
    WHERE 1=1
";

$params = [];

if ($vendorId) {
    $sql .= " AND ve.vendor_id = ?";
    $params[] = $vendorId;
}

if ($status) {
    $sql .= " AND ve.status = ?";
    $params[] = $status;
}

if ($dateFrom) {
    $sql .= " AND DATE(ve.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND DATE(ve.created_at) <= ?";
    $params[] = $dateTo;
}

$sql .= " ORDER BY ve.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all vendors for filter
$stmt = $db->query("
    SELECT id, full_name, email, username 
    FROM users 
    WHERE user_type = 'vendor' AND vendor_status = 'approved'
    ORDER BY full_name
");
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalEarnings = array_sum(array_column($earnings, 'amount'));
$totalPaid = array_sum(array_filter($earnings, function($e) { return $e['status'] === 'paid'; }));
$totalPending = array_sum(array_filter($earnings, function($e) { return $e['status'] === 'pending'; }));
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-dollar-sign me-2"></i>Vendor Earnings History</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-uppercase">Total Earnings</h6>
                    <h3 class="mb-0">$<?php echo number_format($totalEarnings, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-uppercase">Total Paid</h6>
                    <h3 class="mb-0">$<?php echo number_format($totalPaid, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="text-uppercase">Pending</h6>
                    <h3 class="mb-0">$<?php echo number_format($totalPending, 2); ?></h3>
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
                <div class="col-md-3">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-select">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option value="<?php echo $vendor['id']; ?>" <?php echo $vendorId == $vendor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vendor['full_name'] . ' (' . $vendor['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
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
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="earnings.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Earnings Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Earnings Records (<?php echo count($earnings); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($earnings)): ?>
                <div class="alert alert-info">No earnings found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="earningsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vendor</th>
                                <th>Order</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Transaction ID</th>
                                <th>Date</th>
                                <th>Paid Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($earnings as $earning): ?>
                            <tr>
                                <td>#<?php echo $earning['id']; ?></td>
                                <td>
                                    <a href="../view-vendor.php?id=<?php echo $earning['vendor_id']; ?>">
                                        <?php echo htmlspecialchars($earning['vendor_name']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?php echo $earning['email']; ?></small>
                                </td>
                                <td>
                                    <?php if ($earning['order_number']): ?>
                                        <a href="../orders/view_order.php?id=<?php echo $earning['order_id']; ?>">
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
                                <td>
                                    <small><?php echo $earning['transaction_id'] ?? 'N/A'; ?></small>
                                </td>
                                <td><?php echo date('d M Y', strtotime($earning['created_at'])); ?></td>
                                <td>
                                    <?php echo $earning['paid_date'] ? date('d M Y', strtotime($earning['paid_date'])) : '<span class="text-muted">-</span>'; ?>
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

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Export Earnings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Export earnings data as CSV file.</p>
                <form id="exportForm" method="POST" action="action/export-earnings.php">
                    <input type="hidden" name="vendor_id" value="<?php echo $vendorId; ?>">
                    <input type="hidden" name="status" value="<?php echo $status; ?>">
                    <input type="hidden" name="date_from" value="<?php echo $dateFrom; ?>">
                    <input type="hidden" name="date_to" value="<?php echo $dateTo; ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="exportForm" class="btn btn-success">
                    <i class="fas fa-download me-1"></i>Export CSV
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#earningsTable').DataTable({
        "order": [[7, "desc"]],
        "pageLength": 25,
        "columns": [
            null,
            null,
            null,
            null,
            { "orderable": true },
            null,
            null,
            null,
            null
        ]
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

