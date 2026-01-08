<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

// Helper functions
function formatCurrency($amount) {
    return '$' . number_format($amount ?? 0, 2);
}

function getInvoiceStatusBadge($status) {
    $badges = [
        'draft' => 'secondary',
        'sent' => 'info',
        'viewed' => 'primary',
        'approved' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'dark',
        'paid' => 'success',
        'unpaid' => 'warning',
        'partial' => 'info',
        'overdue' => 'danger',
        'refunded' => 'dark'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    $status_text = ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge bg-' . $color . '">' . $status_text . '</span>';
}

function getPaymentStatusBadge($status) {
    $colors = [
        'paid' => 'success',
        'unpaid' => 'danger',
        'partial' => 'warning',
        'overdue' => 'danger',
        'refunded' => 'dark'
    ];
    
    $color = $colors[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

// Initialize variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'payment_status' => $_GET['payment_status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'customer_id' => $_GET['customer_id'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'min_amount' => $_GET['min_amount'] ?? '',
    'max_amount' => $_GET['max_amount'] ?? '',
    'month' => $_GET['month'] ?? '',
    'year' => $_GET['year'] ?? ''
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
        $where[] = "(i.invoice_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params[] = $search_term;
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
    
    if (!empty($filters['min_amount'])) {
        $where[] = "i.total_amount >= ?";
        $params[] = $filters['min_amount'];
    }
    
    if (!empty($filters['max_amount'])) {
        $where[] = "i.total_amount <= ?";
        $params[] = $filters['max_amount'];
    }
    
    if (!empty($filters['month'])) {
        $where[] = "MONTH(i.invoice_date) = ?";
        $params[] = $filters['month'];
    }
    
    if (!empty($filters['year'])) {
        $where[] = "YEAR(i.invoice_date) = ?";
        $params[] = $filters['year'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM invoices i WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get invoices with customer info
    $invoices_sql = "SELECT i.*, 
                            u.full_name, 
                            u.email, 
                            u.phone,
                            u.address as customer_address,
                            (SELECT COUNT(*) FROM invoice_payments ip WHERE ip.invoice_id = i.id) as payment_count,
                            (SELECT SUM(amount) FROM invoice_payments ip WHERE ip.invoice_id = i.id AND status = 'completed') as total_paid
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
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $this_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));
    $this_year = date('Y');
    
    // Today's stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(total_amount) as amount,
            SUM(amount_paid) as paid_amount
        FROM invoices 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch();
    
    // Yesterday's stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(total_amount) as amount,
            SUM(amount_paid) as paid_amount
        FROM invoices 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$yesterday]);
    $yesterday_stats = $stmt->fetch();
    
    // This month stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(total_amount) as amount,
            SUM(amount_paid) as paid_amount
        FROM invoices 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$this_month]);
    $month_stats = $stmt->fetch();
    
    // Last month stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(total_amount) as amount,
            SUM(amount_paid) as paid_amount
        FROM invoices 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$last_month]);
    $last_month_stats = $stmt->fetch();
    
    // This year stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(total_amount) as amount,
            SUM(amount_paid) as paid_amount
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
            SUM(total_amount) as total_amount,
            SUM(amount_paid) as paid_amount,
            SUM(balance_due) as due_amount
        FROM invoices
        GROUP BY payment_status
        ORDER BY count DESC
    ");
    $status_stats = $stmt->fetchAll();
    
    // Monthly trend (last 6 months)
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(invoice_date, '%Y-%m') as month,
            COUNT(*) as invoice_count,
            SUM(total_amount) as total_amount,
            SUM(amount_paid) as paid_amount
        FROM invoices
        WHERE invoice_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $monthly_trend = $stmt->fetchAll();
    
    // Get customers for filter dropdown
    $stmt = $db->query("
        SELECT DISTINCT u.id, u.full_name, u.email 
        FROM invoices i
        JOIN users u ON i.user_id = u.id
        ORDER BY u.full_name
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
        
        if ($stat['payment_status'] == 'overdue') {
            $summary_stats['overdue_amount'] += $stat['due_amount'];
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
    $monthly_trend = [];
}

$page_title = 'Invoice Management';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Invoice Management</h1>
                <p class="text-muted mb-0">Manage and track all invoices</p>
            </div>
            <div>
                <a href="create-invoice.php" class="btn btn-primary me-2">
                    <i class="fas fa-plus me-2"></i> Create Invoice
                </a>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-2"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportData('csv')"><i class="fas fa-file-csv me-2"></i> CSV</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportData('excel')"><i class="fas fa-file-excel me-2"></i> Excel</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportData('pdf')"><i class="fas fa-file-pdf me-2"></i> PDF</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted mb-1">Total Invoices</h6>
                                <h4 class="font-weight-bold mb-0"><?php echo number_format($summary_stats['total_invoices']); ?></h4>
                            </div>
                            <div class="icon-shape bg-primary font-3 text-white p-2 me-2" style="height: 40px;">
                                <i class="fas fa-file-invoice me-2"></i>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-success mr-2">All Time</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted mb-1">Total Amount</h6>
                                <h4 class="font-weight-bold mb-0 me-2"><?php echo formatCurrency($summary_stats['total_amount']); ?></h4>
                            </div>
                            <div class="icon-shape bg-info text-white font-1 p-2 " style="height:40px;">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-success mr-2">Total Revenue</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted mb-1">Paid Amount</h6>
                                <h4 class="font-weight-bold mb-0"><?php echo formatCurrency($summary_stats['paid_amount']); ?></h4>
                            </div>
                            <div class="icon-shape bg-success text-white font-1 p-2 " style="height:40px;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-success mr-2">Received</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted mb-1">Due Amount</h6>
                                <h4 class="font-weight-bold mb-0"><?php echo formatCurrency($summary_stats['due_amount']); ?></h4>
                            </div>
                            <div class="icon-shape bg-warning text-white font-1 p-2 " style="height:40px;">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-warning mr-2">Pending</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted mb-1">Overdue</h6>
                                <h4 class="font-weight-bold mb-0"><?php echo formatCurrency($summary_stats['overdue_amount']); ?></h4>
                            </div>
                            <div class="icon-shape bg-danger text-white font-1 p-2 " style="height:40px;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-danger mr-2">Past Due</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted mb-1">This Month</h6>
                                <h4 class="font-weight-bold mb-0"><?php echo formatCurrency($month_stats['amount'] ?? 0); ?></h4>
                            </div>
                            <div class="icon-shape bg-secondary text-white font-1 p-2 " style="height:40px;">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-info mr-2"><?php echo $month_stats['count'] ?? 0; ?> invoices</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Time Period Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-left-primary shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Today
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($today_stats['amount'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo $today_stats['count'] ?? 0; ?> invoices</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-sun fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-success shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Yesterday
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($yesterday_stats['amount'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo $yesterday_stats['count'] ?? 0; ?> invoices</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-info shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    This Month
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($month_stats['amount'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo $month_stats['count'] ?? 0; ?> invoices</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-warning shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    This Year
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($year_stats['amount'] ?? 0); ?>
                                </div>
                                <div class="mt-2 mb-0 text-muted text-xs">
                                    <span><?php echo $year_stats['count'] ?? 0; ?> invoices</span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
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
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="draft" <?php echo $filters['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent" <?php echo $filters['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="viewed" <?php echo $filters['status'] == 'viewed' ? 'selected' : ''; ?>>Viewed</option>
                            <option value="approved" <?php echo $filters['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="cancelled" <?php echo $filters['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="payment_status" class="form-select">
                            <option value="">Payment Status</option>
                            <option value="paid" <?php echo $filters['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="unpaid" <?php echo $filters['payment_status'] == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="partial" <?php echo $filters['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="overdue" <?php echo $filters['payment_status'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="refunded" <?php echo $filters['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="customer_id" class="form-select">
                            <option value="">All Customers</option>
                            <?php foreach($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo $filters['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['start_date']); ?>" 
                               placeholder="Start Date">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['end_date']); ?>" 
                               placeholder="End Date">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" name="min_amount" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['min_amount']); ?>" 
                               placeholder="Min Amount" step="0.01">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" name="max_amount" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['max_amount']); ?>" 
                               placeholder="Max Amount" step="0.01">
                    </div>
                    
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['search']); ?>" 
                               placeholder="Search invoice number, customer name, email or phone...">
                    </div>
                    
                    <div class="col-md-2">
                        <select name="month" class="form-select">
                            <option value="">Select Month</option>
                            <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $filters['month'] == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="year" class="form-select">
                            <option value="">Select Year</option>
                            <?php for($y=date('Y'); $y>=2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $filters['year'] == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 d-flex">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                        <a href="invoices.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row">
            <!-- Invoices Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Invoices (<?php echo number_format($total_records); ?>)
                        </h5>
                        <div>
                            <span class="me-3">
                                <i class="fas fa-circle text-success me-1"></i> Paid
                                <i class="fas fa-circle text-warning ms-3 me-1"></i> Partial
                                <i class="fas fa-circle text-danger ms-3 me-1"></i> Unpaid
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($invoices)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
                            <h5>No Invoices Found</h5>
                            <p class="text-muted">No invoices match your criteria</p>
                            <a href="create-invoice.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Create First Invoice
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th width="15%">Invoice #</th>
                                        <th width="20%">Customer</th>
                                        <th width="10%">Amount</th>
                                        <th width="10%">Date</th>
                                        <th width="10%">Due Date</th>
                                        <th width="15%">Status</th>
                                        <th width="15%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($invoices as $invoice): 
                                        $is_overdue = ($invoice['payment_status'] == 'unpaid' || $invoice['payment_status'] == 'partial') && 
                                                     strtotime($invoice['due_date']) < time();
                                    ?>
                                    <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                        <td>
                                            <input type="checkbox" class="invoice-checkbox" value="<?php echo $invoice['id']; ?>">
                                        </td>
                                        <td>
                                            <strong><?php echo $invoice['invoice_number']; ?></strong><br>
                                            <?php if($invoice['order_id']): ?>
                                            <small class="text-muted">Order: #<?php echo $invoice['order_id']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($invoice['full_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($invoice['email']); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo formatCurrency($invoice['total_amount']); ?></div>
                                            <small class="text-success">
                                                Paid: <?php echo formatCurrency($invoice['amount_paid']); ?>
                                            </small><br>
                                            <small class="text-danger">
                                                Due: <?php echo formatCurrency($invoice['balance_due']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                                            <?php if($is_overdue): ?>
                                            <br><small class="text-danger">Overdue</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="mb-1"><?php echo getInvoiceStatusBadge($invoice['status']); ?></div>
                                            <div><?php echo getPaymentStatusBadge($invoice['payment_status']); ?></div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-outline-primary" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="print-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-outline-info" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-outline-success">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php if($invoice['payment_status'] != 'paid'): ?>
                                                <button class="btn btn-outline-warning" onclick="recordPayment(<?php echo $invoice['id']; ?>)">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </button>
                                                <?php endif; ?>
                                                <a href="edit-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-white border-0">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm justify-content-center mb-0">
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Statistics & Quick Actions -->
            <div class="col-lg-4">
                <!-- Status Distribution -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0">Payment Status Distribution</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach($status_stats as $stat): 
                            $percentage = $summary_stats['total_invoices'] > 0 ? 
                                ($stat['count'] / $summary_stats['total_invoices']) * 100 : 0;
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <div class="status-dot bg-<?php 
                                    echo $stat['payment_status'] == 'paid' ? 'success' : 
                                    ($stat['payment_status'] == 'partial' ? 'warning' : 
                                    ($stat['payment_status'] == 'overdue' ? 'danger' : 'secondary')); 
                                ?>"></div>
                                <span class="ms-2"><?php echo ucfirst($stat['payment_status']); ?></span>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo $stat['count']; ?></div>
                                <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 5px;">
                            <div class="progress-bar bg-<?php 
                                echo $stat['payment_status'] == 'paid' ? 'success' : 
                                ($stat['payment_status'] == 'partial' ? 'warning' : 
                                ($stat['payment_status'] == 'overdue' ? 'danger' : 'secondary')); 
                            ?>" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary text-start" onclick="bulkSendInvoices()">
                                <i class="fas fa-paper-plane me-2"></i> Send Selected Invoices
                            </button>
                            <button class="btn btn-outline-success text-start" onclick="bulkMarkAsPaid()">
                                <i class="fas fa-check-circle me-2"></i> Mark Selected as Paid
                            </button>
                            <button class="btn btn-outline-warning text-start" onclick="sendReminders()">
                                <i class="fas fa-bell me-2"></i> Send Due Reminders
                            </button>
                            <button class="btn btn-outline-info text-start" onclick="generateReports()">
                                <i class="fas fa-chart-bar me-2"></i> Generate Reports
                            </button>
                            <button class="btn btn-outline-danger text-start" onclick="bulkDelete()">
                                <i class="fas fa-trash me-2"></i> Delete Selected
                            </button>
                        </div>
                        
                        <hr>
                        
                        <h6 class="mt-3 mb-2">Recent Activity</h6>
                        <div class="activity-feed">
                            <?php foreach($recent_activities as $activity): ?>
                            <div class="activity-item mb-2">
                                <div class="d-flex">
                                    <div class="activity-icon bg-<?php 
                                        echo $activity['payment_status'] == 'paid' ? 'success' : 
                                        ($activity['status'] == 'sent' ? 'info' : 'secondary');
                                    ?> text-white rounded-circle">
                                        <i class="fas fa-<?php 
                                            echo $activity['payment_status'] == 'paid' ? 'check' : 
                                            ($activity['status'] == 'sent' ? 'paper-plane' : 'file-invoice');
                                        ?>"></i>
                                    </div>
                                    <div class="ms-2">
                                        <div class="small"><?php echo $activity['activity']; ?></div>
                                        <div class="text-muted small">
                                            <?php echo $activity['invoice_number']; ?> • 
                                            <?php echo time_ago($activity['created_at']); ?>
                                        </div>
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

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Action</label>
                    <select class="form-select" id="bulkActionSelect">
                        <option value="">-- Select Action --</option>
                        <option value="send">Send Invoices</option>
                        <option value="mark_paid">Mark as Paid</option>
                        <option value="mark_sent">Mark as Sent</option>
                        <option value="mark_cancelled">Mark as Cancelled</option>
                        <option value="delete">Delete Invoices</option>
                        <option value="export">Export Selected</option>
                    </select>
                </div>
                <div id="actionDetails" style="display: none;">
                    <!-- Additional options will appear here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="executeBulkAction()">Execute</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Toggle select all checkboxes
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// Get selected invoice IDs
function getSelectedInvoices() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

// Bulk actions
function bulkActions() {
    const selected = getSelectedInvoices();
    if (selected.length === 0) {
        Swal.fire('Warning', 'Please select at least one invoice.', 'warning');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('bulkActionsModal'));
    modal.show();
}

// Bulk send invoices
function bulkSendInvoices() {
    const selected = getSelectedInvoices();
    if (selected.length === 0) {
        Swal.fire('Warning', 'Please select invoices to send.', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Send Invoices',
        text: `Send ${selected.length} selected invoice(s) to customers?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Send Now',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            sendBulkAction('send', selected);
        }
    });
}

// Bulk mark as paid
function bulkMarkAsPaid() {
    const selected = getSelectedInvoices();
    if (selected.length === 0) {
        Swal.fire('Warning', 'Please select invoices to mark as paid.', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Mark as Paid',
        text: `Mark ${selected.length} selected invoice(s) as paid?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Mark Paid',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            sendBulkAction('mark_paid', selected);
        }
    });
}

// Send due reminders
function sendReminders() {
    Swal.fire({
        title: 'Send Reminders',
        text: 'Send payment reminders for all due invoices?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Send Reminders',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/send-reminders.php')
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
    });
}

// Generate reports
function generateReports() {
    const params = new URLSearchParams(window.location.search);
    window.open('reports/invoices.php?' + params.toString(), '_blank');
}

// Bulk delete
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

// Record payment for single invoice
function recordPayment(invoiceId) {
    Swal.fire({
        title: 'Record Payment',
        html: `
            <div class="text-start">
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
                        <option value="paypal">PayPal</option>
                        <option value="check">Check</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Date</label>
                    <input type="date" id="paymentDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea id="paymentNotes" class="form-control" rows="2"></textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Record Payment',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const amount = document.getElementById('paymentAmount').value;
            if (!amount || amount <= 0) {
                Swal.showValidationMessage('Please enter a valid amount');
                return false;
            }
            return {
                amount: amount,
                method: document.getElementById('paymentMethod').value,
                date: document.getElementById('paymentDate').value,
                notes: document.getElementById('paymentNotes').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const data = result.value;
            fetch('ajax/record-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    invoice_id: invoiceId,
                    ...data
                })
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
    });
}

// Send bulk action request
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

// Export data
function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    params.set('export', '1');
    
    window.open('export-invoices.php?' + params.toString(), '_blank');
}

// Time ago helper function
function time_ago(datetime) {
    const date = new Date(datetime);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return minutes + ' minutes ago';
    
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours + ' hours ago';
    
    const days = Math.floor(hours / 24);
    if (days < 30) return days + ' days ago';
    
    const months = Math.floor(days / 30);
    if (months < 12) return months + ' months ago';
    
    const years = Math.floor(months / 12);
    return years + ' years ago';
}

// Update time ago for all timestamps
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.time-ago').forEach(element => {
        const datetime = element.getAttribute('data-time');
        if (datetime) {
            element.textContent = time_ago(datetime);
        }
    });
});
</script>

<?php 
// Time ago helper function for PHP
function time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

require_once '../includes/footer.php';
?>