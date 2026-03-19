<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'Email Logs';
require_once '../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$template_id = isset($_GET['template_id']) ? $_GET['template_id'] : '';
$recipient = isset($_GET['recipient']) ? $_GET['recipient'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

try {
    $db = getDB();
    
    // Create email_logs table if not exists
    $table_exists = $db->query("SHOW TABLES LIKE 'email_logs'")->fetch();
    if (!$table_exists) {
        $db->exec("CREATE TABLE IF NOT EXISTS email_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            template_key VARCHAR(100),
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255),
            subject VARCHAR(255),
            message TEXT,
            headers TEXT,
            status ENUM('sent', 'failed', 'pending', 'bounced') DEFAULT 'pending',
            error_message TEXT,
            sent_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            INDEX idx_email (recipient_email)
        )");
        
        // Insert sample data if table was just created
        $db->exec("INSERT INTO email_logs (template_key, recipient_email, recipient_name, subject, status, sent_at) VALUES
            ('welcome_email', 'test@example.com', 'Test User', 'Welcome to our site', 'sent', NOW()),
            ('order_confirmation', 'customer@example.com', 'John Doe', 'Order #1234 Confirmation', 'sent', DATE_SUB(NOW(), INTERVAL 1 DAY)),
            ('password_reset', 'user@example.com', NULL, 'Password Reset Request', 'failed', NULL)");
    }
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($template_id)) {
        $where[] = "template_key = ?";
        $params[] = $template_id;
    }
    
    if (!empty($recipient)) {
        $where[] = "recipient_email LIKE ?";
        $params[] = "%$recipient%";
    }
    
    if (!empty($status)) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $end_date;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM email_logs WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get email logs
    $logs_sql = "SELECT * FROM email_logs WHERE $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($logs_sql);
    $stmt->execute($all_params);
    $email_logs = $stmt->fetchAll();
    
    // Get email templates for filter
    $stmt = $db->query("SELECT DISTINCT template_key FROM email_logs WHERE template_key IS NOT NULL ORDER BY template_key");
    $templates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_emails,
        COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as sent,
        COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END), 0) as bounced
        FROM email_logs";
    $stats_result = $db->query($stats_sql)->fetch();
    
    $stats = [
        'total_emails' => (int)($stats_result['total_emails'] ?? 0),
        'sent' => (int)($stats_result['sent'] ?? 0),
        'failed' => (int)($stats_result['failed'] ?? 0),
        'pending' => (int)($stats_result['pending'] ?? 0),
        'bounced' => (int)($stats_result['bounced'] ?? 0)
    ];
    
    // Get today's emails
    $today_sql = "SELECT COUNT(*) as count FROM email_logs WHERE DATE(created_at) = CURDATE()";
    $today_result = $db->query($today_sql)->fetch();
    $today_emails = (int)($today_result['count'] ?? 0);
    
    // Get daily data for chart
    $daily_sql = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
        FROM email_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC";
    $daily_result = $db->query($daily_sql)->fetchAll();
    
    $chart_labels = [];
    $chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('M d', strtotime($date));
        $found = false;
        foreach ($daily_result as $row) {
            if ($row['date'] == $date) {
                $chart_data[] = (int)$row['count'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chart_data[] = 0;
        }
    }
    
} catch(PDOException $e) {
    $error = 'Error loading email logs: ' . $e->getMessage();
    $email_logs = [];
    $total_records = 0;
    $templates = [];
    $stats = ['total_emails' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0, 'bounced' => 0];
    $today_emails = 0;
    $chart_labels = [];
    $chart_data = [];
}
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --primary-light: #4895ef;
    --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
    
    --success: #06d6a0;
    --success-dark: #0ca678;
    --success-light: #80ffdb;
    --success-gradient: linear-gradient(135deg, #06d6a0, #0ca678);
    
    --warning: #ffb703;
    --warning-dark: #f77f00;
    --warning-light: #ffe066;
    --warning-gradient: linear-gradient(135deg, #ffb703, #f77f00);
    
    --danger: #ef476f;
    --danger-dark: #d62828;
    --danger-light: #ffafcc;
    --danger-gradient: linear-gradient(135deg, #ef476f, #d62828);
    
    --info: #4cc9f0;
    --info-dark: #0096c7;
    --info-light: #a2d6f9;
    --info-gradient: linear-gradient(135deg, #4cc9f0, #0096c7);
    
    --dark: #2b2d42;
    --dark-light: #4a4e69;
    --light: #f8f9fa;
    
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
    --shadow-2xl: 0 25px 50px rgba(0,0,0,0.2);
    
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    
    --border-radius-sm: 8px;
    --border-radius-md: 12px;
    --border-radius-lg: 16px;
    --border-radius-xl: 20px;
    --border-radius-2xl: 24px;
    --border-radius-full: 9999px;
}

/* Dashboard Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--gray-100);
    position: relative;
}

.main-content {
    flex: 1;
    margin-left: 280px;
    padding: 2rem;
    background: var(--gray-100);
    transition: var(--transition);
    position: relative;
}

@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
    animation: slideIn 0.5s ease;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary-gradient);
    border-radius: var(--border-radius-full);
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: var(--gray-600);
    font-size: 1rem;
    margin-bottom: 0;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: var(--transition);
    animation: slideIn 0.5s ease;
    animation-fill-mode: both;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    flex-shrink: 0;
}

.stat-card .stat-content {
    flex: 1;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.stat-card .stat-label {
    color: var(--gray-600);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.filter-card .card-body {
    padding: 1.5rem;
}

/* Bulk Actions */
.bulk-actions {
    margin-bottom: 1rem;
    animation: slideIn 0.3s ease;
}

.bulk-actions .card {
    background: linear-gradient(135deg, var(--gray-100), white);
    border: 1px solid var(--primary);
}

/* Logs Card */
.logs-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    animation: slideIn 0.5s ease;
}

.logs-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logs-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.logs-card .card-header h5 i {
    color: var(--info);
}

/* Status Badges */
.status-badge {
    padding: 0.35rem 1rem;
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge.sent {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.status-badge.failed {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.status-badge.pending {
    background: rgba(255, 183, 3, 0.15);
    color: var(--warning);
    border: 1px solid rgba(255, 183, 3, 0.3);
}

.status-badge.bounced {
    background: rgba(76, 201, 240, 0.15);
    color: var(--info);
    border: 1px solid rgba(76, 201, 240, 0.3);
}

/* Action Buttons */
.action-group {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.5rem;
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-200);
    background: white;
    color: var(--gray-600);
    transition: var(--transition);
    cursor: pointer;
    width: 36px;
    height: 36px;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.action-btn.view:hover {
    background: var(--primary-gradient);
    color: white;
}

.action-btn.resend:hover {
    background: var(--success-gradient);
    color: white;
}

/* Chart Cards */
.chart-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    height: 100%;
    animation: slideIn 0.5s ease;
}

.chart-card .card-header {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
}

.chart-card .card-body {
    padding: 1.5rem;
    height: 250px;
    position: relative;
}

/* Modal */
.modal-content {
    border: none;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-2xl);
}

.modal-header {
    background: var(--primary-gradient);
    color: white;
    border-bottom: none;
    padding: 1.5rem 2rem;
}

/* Email Details */
.email-detail-card {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid var(--gray-200);
}

.email-message {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
    max-height: 300px;
    overflow-y: auto;
    font-family: monospace;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-envelope-open-text me-3"></i>Email Logs</h1>
                    <p class="mb-0">Track and monitor all email communications</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group justify-content-md-end">
                        <button class="btn btn-outline-danger" onclick="clearEmailLogs()">
                            <i class="fas fa-trash-alt me-2"></i>Clear Logs
                        </button>
                        <button class="btn btn-outline-success" onclick="exportEmailLogs()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button class="btn btn-outline-info" onclick="testEmailConnection()">
                            <i class="fas fa-paper-plane me-2"></i>Test SMTP
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Emails -->
    <div class="col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value text-primary"><?php echo number_format($stats['total_emails']); ?></div>
                <div class="stat-label">Total Emails</div>
                <div class="stat-change">
                    <i class="fas fa-calendar me-1"></i> All time
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sent -->
    <div class="col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value text-success"><?php echo number_format($stats['sent']); ?></div>
                <div class="stat-label">Sent</div>
                <?php if ($stats['total_emails'] > 0): ?>
                <div class="stat-change">
                    <i class="fas fa-percentage me-1"></i> <?php echo round(($stats['sent'] / $stats['total_emails']) * 100); ?>%
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Failed -->
    <div class="col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger), var(--danger-dark));">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value text-danger"><?php echo number_format($stats['failed']); ?></div>
                <div class="stat-label">Failed</div>
                <?php if ($stats['total_emails'] > 0): ?>
                <div class="stat-change">
                    <i class="fas fa-percentage me-1"></i> <?php echo round(($stats['failed'] / $stats['total_emails']) * 100); ?>%
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Pending -->
    <div class="col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value text-warning"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-label">Pending</div>
                <?php if ($stats['total_emails'] > 0): ?>
                <div class="stat-change">
                    <i class="fas fa-percentage me-1"></i> <?php echo round(($stats['pending'] / $stats['total_emails']) * 100); ?>%
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bounced -->
    <div class="col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value text-info"><?php echo number_format($stats['bounced']); ?></div>
                <div class="stat-label">Bounced</div>
                <?php if ($stats['total_emails'] > 0): ?>
                <div class="stat-change">
                    <i class="fas fa-percentage me-1"></i> <?php echo round(($stats['bounced'] / $stats['total_emails']) * 100); ?>%
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Today -->
    <div class="col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--dark), var(--dark-light));">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($today_emails); ?></div>
                <div class="stat-label">Today</div>
                <div class="stat-change">
                    <i class="fas fa-sun me-1"></i> <?php echo date('M d'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
        <!-- Filters -->
        <div class="filter-card">
            <div class="card-body">
                <form method="GET" id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <select name="template_id" class="form-select">
                                <option value="">All Templates</option>
                                <?php foreach($templates as $template): ?>
                                <option value="<?php echo htmlspecialchars($template); ?>" 
                                    <?php echo $template_id == $template ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($template); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="sent" <?php echo $status == 'sent' ? 'selected' : ''; ?>>✅ Sent</option>
                                <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>❌ Failed</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                                <option value="bounced" <?php echo $status == 'bounced' ? 'selected' : ''; ?>>⚠️ Bounced</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <input type="text" name="recipient" class="form-control" 
                                   value="<?php echo htmlspecialchars($recipient); ?>" 
                                   placeholder="✉️ Search by email...">
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions" style="display: none;">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="fw-bold">
                            <i class="fas fa-check-circle text-primary me-2"></i>
                            <span id="selectedCount">0</span> items selected
                        </span>
                        <button class="btn btn-sm btn-success" onclick="bulkResend()">
                            <i class="fas fa-redo-alt me-2"></i>Resend Selected
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="bulkDelete()">
                            <i class="fas fa-trash me-2"></i>Delete Selected
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                            <i class="fas fa-times me-2"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Email Logs Table -->
        <div class="logs-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i>
                    Email Logs
                    <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?> Records</span>
                </h5>
                <div class="header-actions">
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshLogs()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($email_logs)): ?>
                <div class="empty-state p-5 text-center">
                    <i class="fas fa-envelope-open-text fa-4x text-muted mb-3"></i>
                    <h5>No Email Logs Found</h5>
                    <p class="text-muted">No email logs match your current filters</p>
                    <a href="email-logs.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Clear Filters
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="form-check-input">
                                </th>
                                <th>ID</th>
                                <th>Recipient</th>
                                <th>Template</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($email_logs as $index => $log): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="select-item form-check-input" 
                                           value="<?php echo $log['id']; ?>" onchange="updateSelectedCount()">
                                </td>
                                <td><span class="badge bg-secondary">#<?php echo $log['id']; ?></span></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($log['recipient_email']); ?></strong>
                                        <?php if (!empty($log['recipient_name'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($log['recipient_name']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($log['template_key'])): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($log['template_key']); ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Custom</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 250px;" class="text-truncate">
                                        <?php echo htmlspecialchars($log['subject']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $log['status']; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="action-btn view" onclick="viewEmail(<?php echo $log['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($log['status'] !== 'sent'): ?>
                                        <button class="action-btn resend" onclick="resendEmail(<?php echo $log['id']; ?>)">
                                            <i class="fas fa-redo-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container p-3 border-top">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row g-4 mt-4">
            <div class="col-md-6">
                <div class="chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie text-primary me-2"></i>Email Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="emailStatusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line text-success me-2"></i>Daily Email Volume</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="emailVolumeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- View Email Modal -->
<div class="modal fade" id="viewEmailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-envelope-open-text me-2"></i>Email Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="emailDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="resendModalBtn" onclick="resendCurrentEmail()">
                    <i class="fas fa-redo-alt me-2"></i>Resend Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Test SMTP Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="testEmailForm">
                    <div class="mb-3">
                        <label class="form-label">Test Email Address</label>
                        <input type="email" class="form-control" id="testEmail" 
                               value="<?php echo $_SESSION['email'] ?? ''; ?>" required>
                        <div class="form-text">A test email will be sent to this address</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendTestEmail()">
                    <i class="fas fa-paper-plane me-2"></i>Send Test
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentEmailId = null;
let selectedIds = [];

// View email details
function viewEmail(emailId) {
    currentEmailId = emailId;
    
    const modalContent = document.getElementById('emailDetailsContent');
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    $('#viewEmailModal').modal('show');
    
    fetch(`ajax/get-email-details.php?id=${emailId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const email = data.email;
            
            let html = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label text-muted small">Email ID</div>
                            <div class="email-detail-value fw-bold">#${email.id}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label text-muted small">Recipient</div>
                            <div class="email-detail-value">${escapeHtml(email.recipient_email)}</div>
                            ${email.recipient_name ? `<small>${escapeHtml(email.recipient_name)}</small>` : ''}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label text-muted small">Template</div>
                            <div class="email-detail-value"><code>${escapeHtml(email.template_key || 'Custom')}</code></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label text-muted small">Status</div>
                            <div class="email-detail-value">
                                <span class="status-badge ${email.status}">
                                    <i class="fas fa-circle"></i> ${escapeHtml(email.status || '')}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label text-muted small">Sent At</div>
                            <div class="email-detail-value">${email.sent_at ? new Date(email.sent_at).toLocaleString() : 'Not sent'}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label text-muted small">Created At</div>
                            <div class="email-detail-value">${new Date(email.created_at).toLocaleString()}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="email-detail-card">
                            <div class="email-detail-label text-muted small">Subject</div>
                            <div class="email-detail-value fw-bold">${escapeHtml(email.subject || '')}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="email-detail-label text-muted small">Message</div>
                        <div class="email-message">${escapeHtml(email.message || '')}</div>
                    </div>`;
            
            if (email.error_message) {
                html += `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <h6 class="mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Error Details</h6>
                            <pre class="mb-0"><code>${escapeHtml(email.error_message)}</code></pre>
                        </div>
                    </div>`;
            }
            
            html += `</div>`;
            modalContent.innerHTML = html;
            
            document.getElementById('resendModalBtn').style.display = 
                email.status === 'sent' ? 'none' : 'inline-block';
        } else {
            modalContent.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    })
    .catch(error => {
        modalContent.innerHTML = '<div class="alert alert-danger">Failed to load email details</div>';
    });
}

// Resend email
function resendEmail(emailId) {
    Swal.fire({
        title: 'Resend Email',
        text: 'Resend this email?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, resend'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/resend-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email_id: emailId })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                Swal.fire('Error!', 'An error occurred', 'error');
            });
        }
    });
}

// Resend current email from modal
function resendCurrentEmail() {
    if (currentEmailId) {
        $('#viewEmailModal').modal('hide');
        setTimeout(() => resendEmail(currentEmailId), 500);
    }
}

// Clear all logs
function clearEmailLogs() {
    Swal.fire({
        title: 'Clear Email Logs',
        html: '<p>Are you sure you want to delete all email logs?</p><p class="text-muted small">A backup will be created automatically.</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, clear all'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/clear-email-logs.php')
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    let message = data.message;
                    if (data.backup_created) {
                        message += `\nBackup size: ${data.backup_size}`;
                    }
                    Swal.fire('Cleared!', message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                Swal.fire('Error!', 'An error occurred', 'error');
            });
        }
    });
}

// Export logs
function exportEmailLogs() {
    const params = new URLSearchParams(window.location.search);
    
    Swal.fire({
        title: 'Export Email Logs',
        text: 'Choose export format',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '📄 JSON',
        denyButtonText: '📊 CSV',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`ajax/export-email-logs.php?${params.toString()}&format=json`, '_blank');
        } else if (result.isDenied) {
            window.open(`ajax/export-email-logs.php?${params.toString()}&format=csv`, '_blank');
        }
    });
}

// Test SMTP connection
function testEmailConnection() {
    $('#testEmailModal').modal('show');
}

function sendTestEmail() {
    const email = document.getElementById('testEmail').value;
    
    if (!email) {
        Swal.fire('Error!', 'Please enter an email address', 'error');
        return;
    }
    
    $('#testEmailModal').modal('hide');
    showLoading();
    
    fetch('ajax/test-smtp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            Swal.fire('Success!', data.message, 'success');
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        Swal.fire('Error!', 'An error occurred', 'error');
    });
}

// Bulk actions
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.select-item');
    const selectAll = document.getElementById('selectAll');
    
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    selectedIds = [];
    document.querySelectorAll('.select-item:checked').forEach(cb => {
        selectedIds.push(parseInt(cb.value));
    });
    
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedIds.length > 0) {
        bulkActions.style.display = 'block';
        selectedCount.textContent = selectedIds.length;
    } else {
        bulkActions.style.display = 'none';
    }
}

function clearSelection() {
    document.querySelectorAll('.select-item').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateSelectedCount();
}

function bulkResend() {
    if (selectedIds.length === 0) return;
    
    Swal.fire({
        title: 'Bulk Resend',
        text: `Resend ${selectedIds.length} email(s)?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, resend'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/bulk-resend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email_ids: selectedIds })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                Swal.fire('Error!', 'An error occurred', 'error');
            });
        }
    });
}

function bulkDelete() {
    if (selectedIds.length === 0) return;
    
    Swal.fire({
        title: 'Bulk Delete',
        text: `Delete ${selectedIds.length} email log(s)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/bulk-email-action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'delete',
                    ids: selectedIds 
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire('Deleted!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                Swal.fire('Error!', 'An error occurred', 'error');
            });
        }
    });
}

// Refresh logs
function refreshLogs() {
    location.reload();
}

// Show/hide loading
function showLoading() {
    Swal.fire({
        title: 'Processing...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
}

function hideLoading() {
    Swal.close();
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Status chart
    const statusCtx = document.getElementById('emailStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sent', 'Failed', 'Pending', 'Bounced'],
                datasets: [{
                    data: [
                        <?php echo $stats['sent']; ?>,
                        <?php echo $stats['failed']; ?>,
                        <?php echo $stats['pending']; ?>,
                        <?php echo $stats['bounced']; ?>
                    ],
                    backgroundColor: ['#06d6a0', '#ef476f', '#ffb703', '#4cc9f0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
    
    // Volume chart
    const volumeCtx = document.getElementById('emailVolumeChart');
    if (volumeCtx) {
        new Chart(volumeCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Emails',
                    data: <?php echo json_encode($chart_data); ?>,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>