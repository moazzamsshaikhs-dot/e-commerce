<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('../index.php');
}

$page_title = 'Payment Management';
require_once '../includes/header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter variables
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_method = isset($_GET['method']) ? $_GET['method'] : '';
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filter_status)) {
        $where[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_method)) {
        $where[] = "p.payment_method = ?";
        $params[] = $filter_method;
    }
    
    if (!empty($filter_user)) {
        $where[] = "p.user_id = ?";
        $params[] = $filter_user;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(p.created_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(p.created_at) <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($search)) {
        $where[] = "(p.transaction_id LIKE ? OR o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR p.id LIKE ?)";
        $search_term = "%$search%";
        array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total payments count
    $count_sql = "SELECT COUNT(*) as total 
                  FROM payments p
                  LEFT JOIN users u ON p.user_id = u.id
                  LEFT JOIN orders o ON p.order_id = o.id
                  WHERE $where_sql";
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_payments = $stmt->fetch()['total'];
    $total_pages = ceil($total_payments / $limit);
    
    // Get payments with details
    $payments_sql = "SELECT p.*, 
                            u.full_name as customer_name,
                            u.email as customer_email,
                            o.order_number,
                            o.total_amount as order_total,
                            (SELECT COUNT(*) FROM refunds r WHERE r.payment_id = p.id) as refund_count,
                            (SELECT SUM(r.refund_amount) FROM refunds r WHERE r.payment_id = p.id AND r.status = 'completed') as total_refunded
                     FROM payments p
                     LEFT JOIN users u ON p.user_id = u.id
                     LEFT JOIN orders o ON p.order_id = o.id
                     WHERE $where_sql
                     ORDER BY p.created_at DESC
                     LIMIT ? OFFSET ?";
    
    $all_params = array_merge($params, [$limit, $offset]);
    
    $stmt = $db->prepare($payments_sql);
    $stmt->execute($all_params);
    $payments = $stmt->fetchAll();
    
    // Get statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                  FROM payments
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $stmt = $db->query($stats_sql);
    $stats = $stmt->fetch();
    
    // Get payment methods
    $stmt = $db->query("SELECT DISTINCT payment_method FROM payments WHERE payment_method != '' ORDER BY payment_method");
    $payment_methods = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get users for filter
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE user_type = 'user' ORDER BY full_name");
    $customers = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error loading payments: ' . $e->getMessage();
    $payments = [];
    $total_payments = 0;
    $stats = [];
    $payment_methods = [];
    $customers = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Payment Management</h1>
                <p class="text-muted mb-0">Total <?php echo number_format($total_payments); ?> payments</p>
            </div>
            <div>
                <a href="<?php echo SITE_URL ?>/admin/payments/payment-status.php" class="btn btn-outline-primary me-2">
                    <i class="fa-solid fa-money-check me-2"></i> Payment Status Report
                </a>
                <a href="export-payments.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-file-export me-2"></i> Export
                </a>
                <a href="manual-payment.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Manual Payment
                </a>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Payments
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_payments'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-credit-card fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Completed
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['completed'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Pending
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['pending'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Failed
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['failed'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Refunded
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['refunded'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-undo fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Amount (30 days)
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?php echo number_format($stats['total_amount'] ?? 0, 2); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search by TXN ID, Order #, Name, Email"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $filter_status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $filter_status == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="method" class="form-select">
                            <option value="">All Methods</option>
                            <?php foreach($payment_methods as $method): ?>
                            <option value="<?php echo $method; ?>" <?php echo $filter_method == $method ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" 
                               name="start_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($start_date); ?>"
                               placeholder="Start Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" 
                               name="end_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($end_date); ?>"
                               placeholder="End Date">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Advanced Filters -->
                <div class="row mt-3">
                    <div class="col-md-4">
                        <select name="user_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Customers</option>
                            <?php foreach($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo $filter_user == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['full_name'] . ' (' . $customer['email'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 text-end">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-redo me-2"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payments Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">ID</th>
                                <th width="10%">Transaction ID</th>
                                <th width="15%">Customer</th>
                                <th width="10%">Order #</th>
                                <th width="10%">Method</th>
                                <th width="10%">Amount</th>
                                <th width="10%">Status</th>
                                <th width="10%">Date</th>
                                <th width="10%">Refunds</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                    <h5>No Payments Found</h5>
                                    <p class="text-muted">No payments match your search criteria</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($payments as $payment): 
                                    // Status badge color
                                    $status_color = 'secondary';
                                    $status_icon = 'clock';
                                    
                                    switch($payment['status']) {
                                        case 'pending':
                                            $status_color = 'warning';
                                            $status_icon = 'clock';
                                            break;
                                        case 'completed':
                                            $status_color = 'success';
                                            $status_icon = 'check-circle';
                                            break;
                                        case 'failed':
                                            $status_color = 'danger';
                                            $status_icon = 'times-circle';
                                            break;
                                        case 'refunded':
                                            $status_color = 'info';
                                            $status_icon = 'undo';
                                            break;
                                    }
                                    
                                    // Format date
                                    $payment_date = date('d M Y', strtotime($payment['created_at']));
                                    $payment_time = date('h:i A', strtotime($payment['created_at']));
                                    
                                    // Calculate net amount after refunds
                                    $net_amount = $payment['amount'] - ($payment['total_refunded'] ?? 0);
                                ?>
                                <tr>
                                    <td>#<?php echo $payment['id']; ?></td>
                                    <td>
                                        <?php if ($payment['transaction_id']): ?>
                                        <span class="badge bg-light text-dark" title="<?php echo htmlspecialchars($payment['transaction_id']); ?>">
                                            <?php echo substr($payment['transaction_id'], 0, 15) . '...'; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($payment['customer_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($payment['customer_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($payment['order_number']): ?>
                                        <a href="../orders/order-details.php?id=<?php echo $payment['order_id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo $payment['order_number']; ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted">Manual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo strtoupper($payment['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold">$<?php echo number_format($payment['amount'], 2); ?></div>
                                        <?php if ($payment['refund_count'] > 0): ?>
                                        <small class="text-danger">
                                            -$<?php echo number_format($payment['total_refunded'] ?? 0, 2); ?> refunded
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo $payment_date; ?></div>
                                        <small class="text-muted"><?php echo $payment_time; ?></small>
                                    </td>
                                    <td>
                                        <?php if ($payment['refund_count'] > 0): ?>
                                        <span class="badge bg-info">
                                            <?php echo $payment['refund_count']; ?> refund(s)
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="payment-details.php?id=<?php echo $payment['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" 
                                                        class="btn btn-outline-secondary dropdown-toggle"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="payment-details.php?id=<?php echo $payment['id']; ?>">
                                                            <i class="fas fa-eye me-2"></i> View Details
                                                        </a>
                                                    </li>
                                                    <?php if ($payment['status'] == 'pending'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-success" href="#" 
                                                           onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'completed')">
                                                            <i class="fas fa-check me-2"></i> Mark as Completed
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" 
                                                           onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'failed')">
                                                            <i class="fas fa-times me-2"></i> Mark as Failed
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($payment['status'] == 'completed' && $net_amount > 0): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-warning" href="refund.php?payment_id=<?php echo $payment['id']; ?>">
                                                            <i class="fas fa-undo me-2"></i> Process Refund
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="resendReceipt(<?php echo $payment['id']; ?>)">
                                                            <i class="fas fa-paper-plane me-2"></i> Resend Receipt
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-info" href="#" onclick="downloadReceipt(<?php echo $payment['id']; ?>)">
                                                            <i class="fas fa-download me-2"></i> Download Receipt
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer border-0 bg-white">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm justify-content-end mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Update payment status
function updatePaymentStatus(paymentId, newStatus) {
    Swal.fire({
        title: 'Update Payment Status',
        text: `Are you sure you want to mark this payment as ${newStatus}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/update-payment-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: paymentId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Resend receipt
function resendReceipt(paymentId) {
    Swal.fire({
        title: 'Resend Receipt',
        text: 'Send payment receipt to customer email?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Send Receipt'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/resend-receipt.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: paymentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Download receipt
function downloadReceipt(paymentId) {
    window.open(`receipt.php?id=${paymentId}`, '_blank');
}
</script>

<?php require_once '../includes/footer.php'; ?>