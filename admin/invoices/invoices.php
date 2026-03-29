<?php
// admin/invoices.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

// Helper functions
function formatCurrency($amount) {
    return 'PKR ' . number_format($amount ?? 0, 2);
}

function formatNumber($number) {
    return number_format($number ?? 0);
}

function getInvoiceStatusBadge($status) {
    $badges = [
        'draft' => 'secondary',
        'sent' => 'info',
        'viewed' => 'primary',
        'approved' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'dark'
    ];
    $color = $badges[$status] ?? 'secondary';
    return '<span class="badge-status badge-' . $color . '">' . ucfirst($status) . '</span>';
}

function getPaymentStatusBadge($status) {
    $colors = [
        'paid' => 'success',
        'unpaid' => 'danger',
        'partial' => 'warning',
        'overdue' => 'danger',
        'refunded' => 'secondary'
    ];
    $color = $colors[$status] ?? 'secondary';
    return '<span class="badge-status badge-' . $color . '">' . ucfirst($status) . '</span>';
}

function time_ago($datetime) {
    if (empty($datetime)) return 'N/A';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// Initialize variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'payment_status' => $_GET['payment_status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'customer_id' => $_GET['customer_id'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? ''
];

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = "i.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['payment_status'])) {
        $where[] = "i.payment_status = ?";
        $params[] = $filters['payment_status'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(i.invoice_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filters['customer_id'])) {
        $where[] = "i.user_id = ?";
        $params[] = $filters['customer_id'];
    }
    
    if (!empty($filters['start_date'])) {
        $where[] = "DATE(i.invoice_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $where[] = "DATE(i.invoice_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM invoices i WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get invoices with customer info
    $invoices_sql = "SELECT i.*, u.full_name, u.email, u.phone
                     FROM invoices i
                     LEFT JOIN users u ON i.user_id = u.id
                     WHERE $where_sql
                     ORDER BY i.invoice_date DESC, i.id DESC
                     LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    $stmt = $db->prepare($invoices_sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
    
    // Get statistics
    $today = date('Y-m-d');
    $this_month = date('Y-m');
    $this_year = date('Y');
    
    // Today's stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as amount,
            COALESCE(SUM(amount_paid), 0) as paid_amount
        FROM invoices 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch();
    
    // This month stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as amount,
            COALESCE(SUM(amount_paid), 0) as paid_amount
        FROM invoices 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$this_month]);
    $month_stats = $stmt->fetch();
    
    // This year stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as amount,
            COALESCE(SUM(amount_paid), 0) as paid_amount
        FROM invoices 
        WHERE YEAR(created_at) = ?
    ");
    $stmt->execute([$this_year]);
    $year_stats = $stmt->fetch();
    
    // Status distribution
    $stmt = $db->query("
        SELECT 
            payment_status,
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(amount_paid), 0) as paid_amount,
            COALESCE(SUM(balance_due), 0) as due_amount
        FROM invoices
        GROUP BY payment_status
    ");
    $status_stats = $stmt->fetchAll();
    
    // Get customers for filter dropdown
    $stmt = $db->query("
        SELECT DISTINCT u.id, u.full_name, u.email 
        FROM invoices i
        JOIN users u ON i.user_id = u.id
        ORDER BY u.full_name
        LIMIT 100
    ");
    $customers = $stmt->fetchAll();
    
    // Get recent activities
    $stmt = $db->query("
        SELECT 
            i.invoice_number,
            u.full_name,
            i.status,
            i.payment_status,
            i.total_amount,
            i.created_at,
            CASE 
                WHEN i.payment_status = 'paid' THEN 'Payment received'
                WHEN i.status = 'sent' THEN 'Invoice sent'
                WHEN i.status = 'viewed' THEN 'Invoice viewed'
                ELSE 'Invoice created'
            END as activity
        FROM invoices i
        LEFT JOIN users u ON i.user_id = u.id
        ORDER BY i.updated_at DESC
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll();
    
    // Calculate summary stats
    $summary_stats = [
        'total_invoices' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'due_amount' => 0,
        'overdue_amount' => 0
    ];
    
    foreach ($status_stats as $stat) {
        $summary_stats['total_invoices'] += $stat['count'];
        $summary_stats['total_amount'] += $stat['total_amount'];
        $summary_stats['paid_amount'] += $stat['paid_amount'];
        
        if ($stat['payment_status'] == 'unpaid' || $stat['payment_status'] == 'partial') {
            $summary_stats['due_amount'] += $stat['due_amount'];
        }
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading invoices: ' . $e->getMessage();
    $invoices = [];
    $status_stats = [];
    $customers = [];
    $summary_stats = [
        'total_invoices' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'due_amount' => 0,
        'overdue_amount' => 0
    ];
    $total_records = 0;
    $recent_activities = [];
    $today_stats = ['count' => 0, 'amount' => 0, 'paid_amount' => 0];
    $month_stats = ['count' => 0, 'amount' => 0, 'paid_amount' => 0];
    $year_stats = ['count' => 0, 'amount' => 0, 'paid_amount' => 0];
}

$page_title = 'Invoice Management';
require_once '../includes/header.php';
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --primary-light: #4895ef;
    --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
    --success: #06d6a0;
    --success-dark: #0ca678;
    --warning: #ffb703;
    --warning-dark: #f77f00;
    --danger: #ef476f;
    --danger-dark: #d62828;
    --info: #4cc9f0;
    --info-dark: #0096c7;
    --dark: #2b2d42;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --border-radius-sm: 8px;
    --border-radius-md: 12px;
    --border-radius-lg: 16px;
    --border-radius-xl: 20px;
    --border-radius-full: 9999px;
}

.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--gray-100);
}

.main-content {
    flex: 1;
    padding: 2rem;
    background: var(--gray-100);
    transition: var(--transition);
    width: 100%;
}

@media (max-width: 992px) {
    .main-content {
        padding: 1rem;
    }
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success), var(--warning), var(--danger));
}

.page-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 1rem;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    border: 1px solid var(--gray-200);
    text-align: center;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stat-card .stat-icon {
    width: 45px;
    height: 45px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin: 0 auto 0.75rem;
}

.stat-card .stat-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    font-size: 0.65rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.filter-card .form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-size: 0.7rem;
    text-transform: uppercase;
}

.filter-card .form-control,
.filter-card .form-select {
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-300);
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    outline: none;
}

.btn-filter {
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--border-radius-md);
    padding: 0.5rem 1rem;
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

/* Invoices Table */
.invoices-table {
    background: white;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.table-header {
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.table-header h6 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.table-custom {
    margin-bottom: 0;
}

.table-custom th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.75rem;
    border-bottom: 2px solid var(--gray-300);
}

.table-custom td {
    padding: 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.85rem;
}

.table-custom tbody tr:hover {
    background: var(--gray-100);
}

/* Badge Styles */
.badge-status {
    padding: 0.25rem 0.6rem;
    border-radius: var(--border-radius-full);
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.badge-success { background: rgba(6, 214, 160, 0.15); color: var(--success); }
.badge-danger { background: rgba(239, 71, 111, 0.15); color: var(--danger); }
.badge-warning { background: rgba(255, 183, 3, 0.15); color: var(--warning); }
.badge-info { background: rgba(76, 201, 240, 0.15); color: var(--info); }
.badge-primary { background: rgba(67, 97, 238, 0.15); color: var(--primary); }
.badge-secondary { background: rgba(108, 117, 125, 0.15); color: var(--gray-600); }

/* Button Styles */
.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: var(--border-radius-md);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.btn-icon:hover {
    transform: translateY(-2px);
}

/* Activity Feed */
.activity-feed {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--gray-200);
    transition: var(--transition);
}

.activity-item:hover {
    background: var(--gray-100);
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--border-radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

/* Pagination */
.pagination {
    gap: 0.25rem;
}

.page-link {
    border: none;
    border-radius: var(--border-radius-md) !important;
    padding: 0.5rem 0.75rem;
    color: var(--gray-700);
    font-weight: 500;
    transition: var(--transition);
    background: var(--gray-100);
}

.page-link:hover {
    background: var(--primary);
    color: white;
}

.page-item.active .page-link {
    background: var(--primary-gradient);
    color: white;
}

/* Responsive Table */
@media (max-width: 992px) {
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-custom {
        min-width: 800px;
    }
}

/* Dropdown */
.dropdown-menu {
    border: none;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    padding: 0.5rem 0;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    transition: var(--transition);
}

.dropdown-item:hover {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
}

/* Modal */
.modal-content {
    border: none;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
}

.modal-header {
    background: var(--primary-gradient);
    color: white;
    border-bottom: none;
    padding: 1rem 1.5rem;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: var(--border-radius-full);
}
</style>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1>
                        <i class="fas fa-file-invoice"></i>
                        Invoice Management
                    </h1>
                    <p class="text-muted mb-0">Manage and track all customer invoices</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="../dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i> Dashboard
                    </a>
                    <a href="create-invoice.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Create Invoice
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="exportData('csv')"><i class="fas fa-file-csv me-2"></i> CSV Export</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportData('excel')"><i class="fas fa-file-excel me-2"></i> Excel Export</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportData('pdf')"><i class="fas fa-file-pdf me-2"></i> PDF Export</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                    <i class="fas fa-file-invoice text-primary"></i>
                </div>
                <div class="stat-value"><?php echo formatNumber($summary_stats['total_invoices']); ?></div>
                <div class="stat-label">Total Invoices</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                    <i class="fas fa-dollar-sign text-success"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($summary_stats['total_amount']); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($summary_stats['paid_amount']); ?></div>
                <div class="stat-label">Paid Amount</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($summary_stats['due_amount']); ?></div>
                <div class="stat-label">Due Amount</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1);">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($summary_stats['overdue_amount']); ?></div>
                <div class="stat-label">Overdue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                    <i class="fas fa-calendar-alt text-info"></i>
                </div>
                <div class="stat-value"><?php echo formatCurrency($month_stats['amount'] ?? 0); ?></div>
                <div class="stat-label">This Month</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="draft" <?php echo $filters['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo $filters['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="viewed" <?php echo $filters['status'] == 'viewed' ? 'selected' : ''; ?>>Viewed</option>
                        <option value="approved" <?php echo $filters['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="cancelled" <?php echo $filters['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-2 col-sm-6">
                    <select name="payment_status" class="form-select">
                        <option value="">Payment Status</option>
                        <option value="paid" <?php echo $filters['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="unpaid" <?php echo $filters['payment_status'] == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="partial" <?php echo $filters['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="overdue" <?php echo $filters['payment_status'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <select name="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        <?php foreach($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>" <?php echo $filters['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($customer['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2 col-sm-6">
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo htmlspecialchars($filters['start_date']); ?>" 
                           placeholder="Start Date">
                </div>
                
                <div class="col-md-2 col-sm-6">
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo htmlspecialchars($filters['end_date']); ?>" 
                           placeholder="End Date">
                </div>
                
                <div class="col-md-2 col-sm-6">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-filter w-100">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                        <a href="invoices.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-12">
                    <input type="text" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($filters['search']); ?>" 
                           placeholder="Search by invoice number, customer name or email...">
                </div>
            </form>
        </div>

        <!-- Invoices Table -->
        <div class="invoices-table">
            <div class="table-header">
                <h6>
                    <i class="fas fa-list"></i>
                    Invoices
                    <span class="badge bg-primary ms-2"><?php echo formatNumber($total_records); ?> Records</span>
                </h6>
                <div>
                    <button class="btn btn-sm btn-outline-danger" onclick="bulkDelete()">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <?php if (empty($invoices)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h5>No Invoices Found</h5>
                    <p class="text-muted">No invoices match your filters</p>
                </div>
                <?php else: ?>
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th width="5%"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <th width="12%">Invoice #</th>
                            <th width="18%">Customer</th>
                            <th width="10%">Amount</th>
                            <th width="10%">Date</th>
                            <th width="10%">Due Date</th>
                            <th width="10%">Status</th>
                            <th width="10%">Payment</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($invoices as $invoice): 
                            $is_overdue = ($invoice['payment_status'] == 'unpaid' || $invoice['payment_status'] == 'partial') && 
                                         strtotime($invoice['due_date']) < time();
                        ?>
                        <tr <?php echo $is_overdue ? 'class="table-danger"' : ''; ?>>
                            <td><input type="checkbox" class="invoice-checkbox" value="<?php echo $invoice['id']; ?>"></td>
                            <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                            <td>
                                <div class="fw-medium"><?php echo htmlspecialchars($invoice['full_name'] ?? 'N/A'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($invoice['email'] ?? ''); ?></small>
                            </td>
                            <td class="fw-bold"><?php echo formatCurrency($invoice['total_amount']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
                            <td>
                                <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                                <?php if($is_overdue): ?>
                                <br><small class="text-danger"><i class="fas fa-exclamation-circle"></i> Overdue</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo getInvoiceStatusBadge($invoice['status']); ?></td>
                            <td><?php echo getPaymentStatusBadge($invoice['payment_status']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-icon btn-outline-primary" target="_blank" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="print-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-icon btn-outline-info" target="_blank" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php if($invoice['payment_status'] != 'paid'): ?>
                                    <button class="btn btn-icon btn-outline-warning" onclick="recordPayment(<?php echo $invoice['id']; ?>)" title="Record Payment">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-icon btn-outline-danger" onclick="deleteInvoice(<?php echo $invoice['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activity -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="analytics-card">
                    <div class="card-header">
                        <h6><i class="fas fa-history"></i> Recent Activity</h6>
                    </div>
                    <div class="card-body">
                        <div class="activity-feed">
                            <?php foreach($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="d-flex align-items-center">
                                    <div class="activity-icon bg-<?php 
                                        echo $activity['payment_status'] == 'paid' ? 'success' : 
                                        ($activity['status'] == 'sent' ? 'info' : 'secondary');
                                    ?>">
                                        <i class="fas fa-<?php 
                                            echo $activity['payment_status'] == 'paid' ? 'check' : 
                                            ($activity['status'] == 'sent' ? 'paper-plane' : 'file-invoice');
                                        ?> text-white"></i>
                                    </div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="fw-medium"><?php echo htmlspecialchars($activity['activity']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($activity['invoice_number']); ?> • 
                                            <?php echo formatCurrency($activity['total_amount']); ?> • 
                                            <?php echo time_ago($activity['created_at']); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Amount</label>
                    <input type="number" id="paymentAmount" class="form-control" placeholder="0.00" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select id="paymentMethod" class="form-select">
                        <option value="cash">Cash</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="easypaisa">Easypaisa</option>
                        <option value="jazzcash">JazzCash</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Date</label>
                    <input type="date" id="paymentDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea id="paymentNotes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitPayment()">Record Payment</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentInvoiceId = null;

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function getSelectedInvoices() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function bulkDelete() {
    const selected = getSelectedInvoices();
    if (selected.length === 0) {
        Swal.fire('Warning', 'Please select invoices to delete.', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Delete Invoices',
        html: `Delete ${selected.length} selected invoice(s)?<br><small class="text-danger">This action cannot be undone!</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            sendBulkAction('delete', selected);
        }
    });
}

function recordPayment(invoiceId) {
    currentInvoiceId = invoiceId;
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

function submitPayment() {
    const amount = document.getElementById('paymentAmount').value;
    if (!amount || amount <= 0) {
        Swal.fire('Error', 'Please enter a valid amount', 'error');
        return;
    }
    
    const data = {
        invoice_id: currentInvoiceId,
        amount: amount,
        method: document.getElementById('paymentMethod').value,
        date: document.getElementById('paymentDate').value,
        notes: document.getElementById('paymentNotes').value
    };
    
    fetch('ajax/record-invoice-payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Success', data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'An error occurred.', 'error');
    });
}

function deleteInvoice(invoiceId) {
    Swal.fire({
        title: 'Delete Invoice',
        text: 'Are you sure you want to delete this invoice?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            sendBulkAction('delete', [invoiceId]);
        }
    });
}

function sendBulkAction(action, invoiceIds) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('invoice_ids', JSON.stringify(invoiceIds));
    
    fetch('ajax/bulk-invoice-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.fire(data.success ? 'Success' : 'Error', data.message, data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => location.reload(), 1500);
        }
    })
    .catch(error => {
        Swal.fire('Error', 'An error occurred.', 'error');
    });
}

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    params.set('export', '1');
    window.open('export-invoices.php?' + params.toString(), '_blank');
}
</script>

<?php require_once '../includes/footer.php'; ?>