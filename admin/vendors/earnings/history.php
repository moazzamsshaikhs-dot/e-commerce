<?php
// history.php
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
        header('Location: ' . SITE_URL . 'admin/vendor/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'admin/vendor/dashboard.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get earnings history
try {
    $db = getDB();
    
    // Build query
    $query = "SELECT ve.*, o.order_number, p.name as product_name, u.username as customer_name
              FROM vendor_earnings ve
              JOIN orders o ON ve.order_id = o.id
              JOIN products p ON ve.product_id = p.id
              JOIN users u ON o.user_id = u.id
              WHERE ve.vendor_id = ?";
    
    $params = [$vendor_id];
    $count_params = [$vendor_id];
    
    if (!empty($status)) {
        $query .= " AND ve.status = ?";
        $params[] = $status;
        $count_params[] = $status;
    }
    
    if (!empty($date_from)) {
        $query .= " AND DATE(ve.created_at) >= ?";
        $params[] = $date_from;
        $count_params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(ve.created_at) <= ?";
        $params[] = $date_to;
        $count_params[] = $date_to;
    }
    
    if (!empty($search)) {
        $query .= " AND (o.order_number LIKE ? OR p.name LIKE ? OR u.username LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    $query .= " ORDER BY ve.created_at DESC";
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM vendor_earnings ve
                    JOIN orders o ON ve.order_id = o.id
                    JOIN products p ON ve.product_id = p.id
                    JOIN users u ON o.user_id = u.id
                    WHERE ve.vendor_id = ?";
    
    if (!empty($status)) $count_query .= " AND ve.status = ?";
    if (!empty($date_from)) $count_query .= " AND DATE(ve.created_at) >= ?";
    if (!empty($date_to)) $count_query .= " AND DATE(ve.created_at) <= ?";
    if (!empty($search)) $count_query .= " AND (o.order_number LIKE ? OR p.name LIKE ? OR u.username LIKE ?)";
    
    $stmt = $db->prepare($count_query);
    $stmt->execute($count_params);
    $total_earnings = $stmt->fetchColumn();
    $total_pages = ceil($total_earnings / $limit);
    
    // Get earnings with pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $paramType = PDO::PARAM_STR;
        if ($key === count($params) - 2 || $key === count($params) - 1) {
            $paramType = PDO::PARAM_INT;
            $value = (int)$value;
        }
        $stmt->bindValue($key + 1, $value, $paramType);
    }
    
    $stmt->execute();
    $earnings = $stmt->fetchAll();
    
    // Get summary stats
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(vendor_amount), 0) as total_earnings,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN vendor_amount ELSE 0 END), 0) as paid_earnings,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN vendor_amount ELSE 0 END), 0) as pending_earnings,
            COALESCE(SUM(CASE WHEN status = 'processing' THEN vendor_amount ELSE 0 END), 0) as processing_earnings
        FROM vendor_earnings 
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $summary = $stmt->fetch();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading earnings history: ' . $e->getMessage();
    $earnings = [];
    $total_earnings = 0;
    $total_pages = 1;
    $summary = ['total_earnings' => 0, 'paid_earnings' => 0, 'pending_earnings' => 0, 'processing_earnings' => 0];
}

$page_title = 'Earnings History';
include_once '../../includes/header.php';
?>
<div class="dashboard-container">
    <?php 
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
                    <h1 class="h3 mb-1 fw-bold text-primary">Earnings History</h1>
                    <p class="text-muted mb-0">View all your earnings transactions</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="earnings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Earnings
                    </a>
                    <button class="btn btn-primary export-btn" onclick="exportToCSV()">
                        <i class="fas fa-download me-2"></i> Export CSV
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Stats Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm stat-card border-start border-5 border-primary">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Total Earnings</h6>
                        <h2 class="mb-0">$<?php echo number_format($summary['total_earnings'], 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm stat-card border-start border-5 border-success">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Paid</h6>
                        <h2 class="mb-0">$<?php echo number_format($summary['paid_earnings'], 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm stat-card border-start border-5 border-warning">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Pending</h6>
                        <h2 class="mb-0">$<?php echo number_format($summary['pending_earnings'], 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm stat-card border-start border-5 border-info">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Processing</h6>
                        <h2 class="mb-0">$<?php echo number_format($summary['processing_earnings'], 2); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search order #, product or customer..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="history.php" class="btn btn-outline-secondary w-100" title="Reset">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Earnings Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($earnings)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No earnings found</h4>
                        <p class="text-muted">Earnings will appear here when customers purchase your products</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="earningsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Order #</th>
                                    <th>Product</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Paid Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($earnings as $earning): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($earning['created_at'])); ?></td>
                                    <td>
                                        <a href="../orders/view.php?id=<?php echo $earning['order_id']; ?>" class="text-decoration-none">
                                            #<?php echo htmlspecialchars($earning['order_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($earning['product_name'], 0, 30)); ?>...</td>
                                    <td>@<?php echo htmlspecialchars($earning['customer_name']); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($earning['vendor_amount'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status_color = 'secondary';
                                        if ($earning['status'] == 'paid') $status_color = 'success';
                                        if ($earning['status'] == 'pending') $status_color = 'warning';
                                        if ($earning['status'] == 'processing') $status_color = 'info';
                                        if ($earning['status'] == 'cancelled') $status_color = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo ucfirst($earning['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($earning['paid_date']): ?>
                                            <?php echo date('M d, Y', strtotime($earning['paid_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                        <div class="text-center text-muted mt-2">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?> | Total Records: <?php echo $total_earnings; ?>
                        </div>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Export to CSV function
function exportToCSV() {
    const table = document.getElementById('earningsTable');
    if (!table) {
        alert('No data to export');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Clean data - remove links and HTML
            let data = cols[j].innerText;
            data = data.replace(/#/g, '').trim();
            
            // Escape quotes and wrap in quotes if contains comma
            if (data.includes(',') || data.includes('"')) {
                data = '"' + data.replace(/"/g, '""') + '"';
            }
            
            row.push(data);
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    const csvString = csv.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, 'earnings_history_' + new Date().toISOString().slice(0, 10) + '.csv');
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = 'earnings_history_' + new Date().toISOString().slice(0, 10) + '.csv';
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Auto-close alerts
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

// Date range validation
const filterForm = document.querySelector('form[method="GET"]');
if (filterForm) {
    filterForm.addEventListener('submit', function(e) {
        const dateFrom = this.querySelector('[name="date_from"]');
        const dateTo = this.querySelector('[name="date_to"]');
        
        if (dateFrom && dateTo && dateFrom.value && dateTo.value) {
            const fromDate = new Date(dateFrom.value);
            const toDate = new Date(dateTo.value);
            
            if (fromDate > toDate) {
                e.preventDefault();
                alert('Error: "From Date" cannot be after "To Date"');
                dateFrom.focus();
                return false;
            }
        }
        return true;
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
    
    .stat-card {
        border-radius: 10px;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    
    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 500;
    }
    
    .export-btn {
        min-width: 120px;
    }
    </style>
<?php 
include_once '../../includes/footer.php';

?>
</body>
</html>