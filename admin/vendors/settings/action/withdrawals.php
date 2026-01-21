<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Withdrawal History';
require_once '../../includes/header.php';

// Get vendor details and withdrawals
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM vendor_withdrawals WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $total = $stmt->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
    // Get withdrawals with pagination
    $stmt = $db->prepare("
        SELECT 
            w.*,
            u.username as processed_by_username,
            u.full_name as processed_by_name
        FROM vendor_withdrawals w
        LEFT JOIN users u ON w.processed_by = u.id
        WHERE w.vendor_id = ?
        ORDER BY w.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $vendor_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $withdrawals = $stmt->fetchAll();
    
    // Get earnings summary
    $stmt = $db->prepare("
        SELECT 
            SUM(vendor_amount) as total_earnings,
            SUM(CASE WHEN status = 'paid' THEN vendor_amount ELSE 0 END) as paid_earnings
        FROM vendor_earnings 
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $earnings = $stmt->fetch();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $withdrawals = [];
    $earnings = ['total_earnings' => 0, 'paid_earnings' => 0];
    $total_pages = 0;
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Withdrawal History</h1>
                <p class="text-muted mb-0">View all your withdrawal requests</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
                <a href="bank.php" class="btn btn-primary">
                    <i class="fas fa-university me-2"></i> Bank Settings
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Available for Withdrawal</h6>
                                <h3 class="fw-bold text-success">$<?php echo number_format($earnings['paid_earnings'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-money-bill-wave text-success fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Total Withdrawals</h6>
                                <h3 class="fw-bold text-primary"><?php echo $total; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-history text-primary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Withdrawals Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-list me-2"></i> All Withdrawal Requests
                </h5>
                <div>
                    <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Print
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="exportToCSV()">
                        <i class="fas fa-file-export me-2"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" id="filterStatus" onchange="filterTable()">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" id="filterMethod" onchange="filterTable()">
                            <option value="">All Methods</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="paypal">PayPal</option>
                            <option value="stripe">Stripe</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="date" class="form-control form-control-sm" id="filterDate" onchange="filterTable()">
                    </div>
                </div>
                
                <?php if (empty($withdrawals)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-history fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Withdrawal History</h5>
                    <p class="text-muted">You haven't made any withdrawal requests yet</p>
                    <a href="bank.php" class="btn btn-primary">
                        <i class="fas fa-money-bill-wave me-2"></i> Request Withdrawal
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="withdrawalsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Processed By</th>
                                <th>Transaction ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($withdrawals as $withdrawal): 
                                $status_colors = [
                                    'pending' => 'warning',
                                    'processing' => 'info',
                                    'completed' => 'success',
                                    'rejected' => 'danger'
                                ];
                                $status_icons = [
                                    'pending' => 'clock',
                                    'processing' => 'sync-alt',
                                    'completed' => 'check-circle',
                                    'rejected' => 'times-circle'
                                ];
                                $method_icons = [
                                    'bank' => 'university',
                                    'paypal' => 'paypal',
                                    'stripe' => 'credit-card',
                                    'cash' => 'money-bill'
                                ];
                            ?>
                            <tr data-status="<?php echo $withdrawal['status']; ?>" 
                                data-method="<?php echo $withdrawal['withdrawal_method']; ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($withdrawal['created_at'])); ?>">
                                <td>
                                    <strong>#<?php echo str_pad($withdrawal['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($withdrawal['created_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('h:i A', strtotime($withdrawal['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($withdrawal['withdrawal_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-<?php echo $method_icons[$withdrawal['withdrawal_method']] ?? 'question-circle'; ?> me-1"></i>
                                        <?php echo ucfirst($withdrawal['withdrawal_method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_colors[$withdrawal['status']] ?? 'secondary'; ?>">
                                        <i class="fas fa-<?php echo $status_icons[$withdrawal['status']] ?? 'question-circle'; ?> me-1"></i>
                                        <?php echo ucfirst($withdrawal['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($withdrawal['processed_by']): ?>
                                    <span class="text-success">
                                        <i class="fas fa-user-check me-1"></i>
                                        <?php echo htmlspecialchars($withdrawal['processed_by_name']); ?>
                                        <?php if ($withdrawal['processed_at']): ?>
                                        <br><small><?php echo date('d M', strtotime($withdrawal['processed_at'])); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($withdrawal['transaction_id']): ?>
                                    <code class="small"><?php echo substr($withdrawal['transaction_id'], 0, 12); ?>...</code>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick="viewWithdrawalDetails(<?php echo $withdrawal['id']; ?>)"
                                            data-bs-toggle="modal" data-bs-target="#viewWithdrawalModal">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($withdrawal['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="cancelWithdrawal(<?php echo $withdrawal['id']; ?>)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Withdrawal pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- View Withdrawal Modal -->
<div class="modal fade" id="viewWithdrawalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-receipt me-2"></i> Withdrawal Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="withdrawalDetails">
                <!-- Content loaded via AJAX -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print me-2"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const status = document.getElementById('filterStatus').value;
    const method = document.getElementById('filterMethod').value;
    const date = document.getElementById('filterDate').value;
    
    const rows = document.querySelectorAll('#withdrawalsTable tbody tr');
    
    rows.forEach(row => {
        let show = true;
        
        if (status && row.dataset.status !== status) {
            show = false;
        }
        
        if (method && row.dataset.method !== method) {
            show = false;
        }
        
        if (date && row.dataset.date !== date) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function viewWithdrawalDetails(withdrawalId) {
    const detailsDiv = document.getElementById('withdrawalDetails');
    detailsDiv.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    fetch('action/get-withdrawal.php?id=' + withdrawalId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const w = data.data;
                detailsDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Withdrawal Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Request ID:</th>
                                    <td>#${w.id.toString().padStart(6, '0')}</td>
                                </tr>
                                <tr>
                                    <th>Date:</th>
                                    <td>${w.created_at_formatted}</td>
                                </tr>
                                <tr>
                                    <th>Amount:</th>
                                    <td><strong>$${parseFloat(w.withdrawal_amount).toFixed(2)}</strong></td>
                                </tr>
                                <tr>
                                    <th>Method:</th>
                                    <td>
                                        <i class="fas fa-${w.method_icon} me-2"></i>
                                        ${w.method_text}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>${w.status_badge}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="fw-bold">Processing Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Processed:</th>
                                    <td>${w.processed_at_formatted}</td>
                                </tr>
                                ${w.processor_info ? `
                                <tr>
                                    <th>By:</th>
                                    <td>${w.processor_info.full_name} (${w.processor_info.username})</td>
                                </tr>
                                ` : ''}
                                ${w.transaction_id ? `
                                <tr>
                                    <th>Transaction ID:</th>
                                    <td><code>${w.transaction_id}</code></td>
                                </tr>
                                ` : ''}
                            </table>
                        </div>
                    </div>
                    
                    ${w.account_info ? `
                    <div class="mt-4">
                        <h6 class="fw-bold">Bank Account Details</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="30%">Bank Name:</th>
                                <td>${w.account_info.bank_name || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th>Account Holder:</th>
                                <td>${w.account_info.account_holder || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th>Account Number:</th>
                                <td>****${w.account_info.account_number || ''}</td>
                            </tr>
                            ${w.account_info.ifsc_code ? `
                            <tr>
                                <th>IFSC Code:</th>
                                <td>${w.account_info.ifsc_code}</td>
                            </tr>
                            ` : ''}
                        </table>
                    </div>
                    ` : ''}
                    
                    ${w.notes ? `
                    <div class="mt-4">
                        <h6 class="fw-bold">Notes</h6>
                        <div class="alert alert-light">
                            ${w.notes}
                        </div>
                    </div>
                    ` : ''}
                    
                    ${w.status === 'rejected' && w.notes ? `
                    <div class="mt-4">
                        <h6 class="fw-bold">Rejection Reason</h6>
                        <div class="alert alert-danger">
                            ${w.notes}
                        </div>
                    </div>
                    ` : ''}
                `;
            } else {
                detailsDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            detailsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading details: ${error}
                </div>
            `;
        });
}

function cancelWithdrawal(withdrawalId) {
    if (confirm('Are you sure you want to cancel this withdrawal request?')) {
        fetch('action/cancel-withdrawal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'withdrawal_id=' + withdrawalId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Withdrawal cancelled successfully');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
    }
}

function printReceipt() {
    const detailsDiv = document.getElementById('withdrawalDetails');
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Withdrawal Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .receipt-header { text-align: center; margin-bottom: 30px; }
                .receipt-info { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f8f9fa; }
                .total { font-size: 18px; font-weight: bold; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="receipt-header">
                <h2>Withdrawal Receipt</h2>
                <p>Date: ${new Date().toLocaleDateString()}</p>
            </div>
            <div id="receipt-content">
                ${detailsDiv.innerHTML}
            </div>
            <div class="no-print" style="margin-top: 30px; text-align: center;">
                <button onclick="window.print()">Print Receipt</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function exportToCSV() {
    // In a real implementation, this would make an AJAX call to export data
    alert('Export feature would generate a CSV file with all withdrawal data.');
    // Example implementation:
    // window.location.href = 'action/export-withdrawals.php';
}
</script>

<?php require_once '../../includes/footer.php'; ?>