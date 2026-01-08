<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . '../index.php');
}

$page_title = 'Payment Audit Trail';
require_once '../includes/header.php';
// Create audit table if not exists
try {
    $db = getDB();
    
    // Check if payment_audit table exists, create if not
    $stmt = $db->query("SHOW TABLES LIKE 'payment_audit'");
    if (!$stmt->fetch()) {
        $create_audit_table = "
        CREATE TABLE payment_audit (
            id INT PRIMARY KEY AUTO_INCREMENT,
            payment_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            old_status VARCHAR(50),
            new_status VARCHAR(50),
            details TEXT,
            performed_by INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $db->exec($create_audit_table);
    }
    
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    // Filters
    $filter_action = $_GET['action'] ?? '';
    $filter_user = $_GET['user_id'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    // Build query
    $where = ["1=1"];
    $params = [];
    
    if ($filter_action) {
        $where[] = "pa.action = ?";
        $params[] = $filter_action;
    }
    
    if ($filter_user) {
        $where[] = "pa.performed_by = ?";
        $params[] = $filter_user;
    }
    
    if ($start_date) {
        $where[] = "DATE(pa.created_at) >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $where[] = "DATE(pa.created_at) <= ?";
        $params[] = $end_date;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM payment_audit pa WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_audits = $stmt->fetch()['total'];
    $total_pages = ceil($total_audits / $limit);
    
    // Get audit logs
    $audit_sql = "SELECT pa.*, 
                         u.full_name as performer_name,
                         u.email as performer_email,
                         p.transaction_id,
                         p.amount,
                         p.payment_method
                  FROM payment_audit pa
                  LEFT JOIN users u ON pa.performed_by = u.id
                  LEFT JOIN payments p ON pa.payment_id = p.id
                  WHERE $where_sql
                  ORDER BY pa.created_at DESC
                  LIMIT ? OFFSET ?";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($audit_sql);
    $stmt->execute($all_params);
    $audits = $stmt->fetchAll();
    
    // Get unique actions
    $stmt = $db->query("SELECT DISTINCT action FROM payment_audit ORDER BY action");
    $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get admins
    $stmt = $db->query("SELECT id, full_name FROM users WHERE user_type = 'admin' ORDER BY full_name");
    $admins = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error loading audit trail: ' . $e->getMessage();
    $audits = [];
    $total_audits = 0;
    $actions = [];
    $admins = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Payment Audit Trail</h1>
                <p class="text-muted mb-0">Track all payment-related activities</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Payments
                </a>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Logs
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($total_audits); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-history fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Last 24 Hours
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php
                                    try {
                                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM payment_audit 
                                                              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
                                        $stmt->execute();
                                        $last24 = $stmt->fetch()['count'];
                                        echo number_format($last24);
                                    } catch(Exception $e) {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Status Changes
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php
                                    try {
                                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM payment_audit 
                                                              WHERE action = 'status_update'");
                                        $stmt->execute();
                                        $status_changes = $stmt->fetch()['count'];
                                        echo number_format($status_changes);
                                    } catch(Exception $e) {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Refunds
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php
                                    try {
                                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM payment_audit 
                                                              WHERE action = 'refund'");
                                        $stmt->execute();
                                        $refunds = $stmt->fetch()['count'];
                                        echo number_format($refunds);
                                    } catch(Exception $e) {
                                        echo '0';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-undo fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select name="action" class="form-select">
                            <option value="">All Actions</option>
                            <?php foreach($actions as $action): ?>
                            <option value="<?php echo $action; ?>" <?php echo $filter_action == $action ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select name="user_id" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>" <?php echo $filter_user == $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['full_name']); ?>
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
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Audit Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Payment</th>
                                <th>Action</th>
                                <th>Performed By</th>
                                <th>Details</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($audits)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h5>No Audit Logs Found</h5>
                                    <p class="text-muted">No audit logs match your search criteria</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($audits as $audit): 
                                    // Action badge color
                                    $action_color = 'secondary';
                                    if ($audit['action'] === 'status_update') $action_color = 'info';
                                    if ($audit['action'] === 'refund') $action_color = 'warning';
                                    if ($audit['action'] === 'manual_payment') $action_color = 'success';
                                    if ($audit['action'] === 'receipt_sent') $action_color = 'primary';
                                ?>
                                <tr>
                                    <td>
                                        <div><?php echo date('d M Y', strtotime($audit['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($audit['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($audit['transaction_id']): ?>
                                        <a href="payment-details.php?id=<?php echo $audit['payment_id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo substr($audit['transaction_id'], 0, 15) . '...'; ?>
                                        </a>
                                        <div class="text-muted small">
                                            $<?php echo number_format($audit['amount'], 2); ?> via <?php echo $audit['payment_method']; ?>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">Payment #<?php echo $audit['payment_id']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $action_color; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $audit['action'])); ?>
                                        </span>
                                        <?php if ($audit['old_status'] && $audit['new_status']): ?>
                                        <div class="small text-muted">
                                            <?php echo $audit['old_status']; ?> → <?php echo $audit['new_status']; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($audit['performer_name']): ?>
                                        <div><?php echo htmlspecialchars($audit['performer_name']); ?></div>
                                        <small class="text-muted"><?php echo $audit['performer_email']; ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($audit['details']): ?>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="showDetails('<?php echo htmlspecialchars($audit['details']); ?>')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($audit['notes'] ?? ''); ?>
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

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Audit Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="detailsContent" class="bg-light p-3" style="max-height: 400px; overflow: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Show details in modal
function showDetails(details) {
    try {
        const detailsObj = JSON.parse(details);
        document.getElementById('detailsContent').textContent = JSON.stringify(detailsObj, null, 2);
    } catch(e) {
        document.getElementById('detailsContent').textContent = details;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>