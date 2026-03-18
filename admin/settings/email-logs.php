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
            status ENUM('sent', 'failed', 'pending', 'bounced') DEFAULT 'pending',
            error_message TEXT,
            sent_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    
    // Get statistics - handle NULL values properly
    $stats_sql = "SELECT 
        COUNT(*) as total_emails,
        COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as sent,
        COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END), 0) as bounced
        FROM email_logs";
    $stats_result = $db->query($stats_sql)->fetch();
    
    // Ensure stats array has all keys with proper defaults
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

.page-header .btn-group {
    display: flex;
    gap: 0.75rem;
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

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }
.stat-card:nth-child(5) { animation-delay: 0.25s; }
.stat-card:nth-child(6) { animation-delay: 0.3s; }

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

.stat-card .stat-change {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
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

.filter-card .form-control,
.filter-card .form-select {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 0.6rem 1rem;
    font-size: 0.9rem;
    transition: var(--transition);
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.filter-card .btn-filter {
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--border-radius-lg);
    padding: 0.6rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
    width: 100%;
}

.filter-card .btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
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

.logs-card .card-header .header-actions {
    display: flex;
    gap: 0.75rem;
}

.logs-card .card-body {
    padding: 0;
}

/* Logs Table */
.logs-table-container {
    overflow-x: auto;
}

.logs-table {
    width: 100%;
    border-collapse: collapse;
}

.logs-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    background: var(--gray-100);
    border-bottom: 2px solid var(--gray-300);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.logs-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-600);
    transition: var(--transition);
}

.logs-table tbody tr {
    transition: var(--transition);
    animation: fadeIn 0.3s ease;
}

.logs-table tbody tr:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
}

.logs-table tbody tr:hover td {
    color: var(--gray-800);
}

/* Email ID */
.email-id {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    background: var(--gray-200);
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
}

/* Recipient Info */
.recipient-info {
    display: flex;
    flex-direction: column;
}

.recipient-email {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.recipient-name {
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* Template Badge */
.template-badge {
    display: inline-block;
    padding: 0.35rem 1rem;
    background: rgba(76, 201, 240, 0.1);
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--info-dark);
    border: 1px solid rgba(76, 201, 240, 0.3);
}

.template-badge i {
    margin-right: 0.25rem;
    font-size: 0.7rem;
}

/* Subject */
.subject-cell {
    max-width: 250px;
}

.subject-preview {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 500;
    color: var(--gray-800);
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

.status-badge i {
    font-size: 0.6rem;
}

/* Date Cell */
.date-cell {
    white-space: nowrap;
}

.date-main {
    font-weight: 600;
    color: var(--gray-800);
}

.date-sub {
    font-size: 0.75rem;
    color: var(--gray-500);
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
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
    border-color: transparent;
}

.action-btn.resend:hover {
    background: linear-gradient(135deg, var(--success), var(--success-dark));
    color: white;
    border-color: transparent;
}

/* Pagination */
.pagination-container {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.pagination-info {
    color: var(--gray-600);
    font-size: 0.9rem;
}

.pagination-info i {
    margin-right: 0.25rem;
    color: var(--primary);
}

.pagination {
    display: flex;
    gap: 0.5rem;
    margin: 0;
}

.page-item {
    list-style: none;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 0.5rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-md);
    background: white;
    color: var(--gray-700);
    font-weight: 600;
    transition: var(--transition);
    text-decoration: none;
}

.page-link:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(67, 97, 238, 0.05);
    transform: translateY(-2px);
}

.page-item.active .page-link {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

.page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
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

.chart-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.chart-card .card-header h5 i {
    color: var(--primary);
}

.chart-card .card-body {
    padding: 1.5rem;
    height: 250px;
    position: relative;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 1.5rem;
}

.empty-state h5 {
    font-size: 1.25rem;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.empty-state p {
    color: var(--gray-500);
    margin-bottom: 1.5rem;
}

/* Modal Styles */
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
    position: relative;
    overflow: hidden;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

.modal-header .modal-title {
    font-weight: 700;
    font-size: 1.25rem;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
    transition: var(--transition);
    position: relative;
    z-index: 1;
}

.modal-header .btn-close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid var(--gray-200);
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

.email-detail-label {
    font-size: 0.8rem;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.email-detail-value {
    font-weight: 600;
    color: var(--gray-800);
    word-break: break-word;
}

.email-message {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
    max-height: 300px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    line-height: 1.6;
}

.error-details {
    background: rgba(239, 71, 111, 0.1);
    border-left: 4px solid var(--danger);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    margin-top: 1rem;
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

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .logs-table th,
    .logs-table td {
        padding: 0.75rem;
    }
    
    .action-group {
        flex-wrap: wrap;
    }
    
    .pagination-container {
        flex-direction: column;
        text-align: center;
    }
    
    .chart-card .card-body {
        height: 200px;
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: var(--border-radius-full);
    border: 2px solid var(--gray-100);
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
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
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_emails']); ?></div>
                        <div class="stat-label">Total Emails</div>
                        <div class="stat-change">
                            <i class="fas fa-calendar me-1"></i> All time
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value text-success"><?php echo number_format($stats['sent']); ?></div>
                        <div class="stat-label">Sent</div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up me-1"></i> 
                            <?php echo $stats['total_emails'] > 0 ? round(($stats['sent'] / $stats['total_emails']) * 100) : 0; ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger), var(--danger-dark));">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value text-danger"><?php echo number_format($stats['failed']); ?></div>
                        <div class="stat-label">Failed</div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-down me-1"></i>
                            <?php echo $stats['total_emails'] > 0 ? round(($stats['failed'] / $stats['total_emails']) * 100) : 0; ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value text-warning"><?php echo number_format($stats['pending']); ?></div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-change">
                            <i class="fas fa-clock me-1"></i> Awaiting
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value text-info"><?php echo number_format($stats['bounced']); ?></div>
                        <div class="stat-label">Bounced</div>
                        <div class="stat-change">
                            <i class="fas fa-undo me-1"></i> Returned
                        </div>
                    </div>
                </div>
            </div>
            
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
                                <option value="sent" <?php echo $status == 'sent' ? 'selected' : ''; ?>> Sent</option>
                                <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>> Failed</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>> Pending</option>
                                <option value="bounced" <?php echo $status == 'bounced' ? 'selected' : ''; ?>> Bounced</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <input type="text" name="recipient" class="form-control" 
                                   value="<?php echo htmlspecialchars($recipient); ?>" 
                                   placeholder="✉️ Search by email...">
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($start_date); ?>" 
                                   placeholder="Start Date">
                        </div>
                        
                        <div class="col-md-2">
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($end_date); ?>" 
                                   placeholder="End Date">
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-search me-2"></i>
                            </button>
                        </div>
                    </div>
                </form>
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
                <div class="empty-state">
                    <i class="fas fa-envelope-open-text"></i>
                    <h5>No Email Logs Found</h5>
                    <p class="text-muted">No email logs match your current filters</p>
                    <a href="email-logs.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Clear Filters
                    </a>
                </div>
                <?php else: ?>
                <div class="logs-table-container">
                    <table class="logs-table">
                        <thead>
                            <tr>
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
                            <tr style="animation-delay: <?php echo $index * 0.02; ?>s;">
                                <td>
                                    <span class="email-id">#<?php echo (int)$log['id']; ?></span>
                                </td>
                                <td>
                                    <div class="recipient-info">
                                        <span class="recipient-email"><?php echo htmlspecialchars($log['recipient_email']); ?></span>
                                        <?php if (!empty($log['recipient_name'])): ?>
                                        <span class="recipient-name">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($log['recipient_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($log['template_key'])): ?>
                                    <span class="template-badge">
                                        <i class="fas fa-code"></i>
                                        <?php echo htmlspecialchars($log['template_key']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="template-badge">
                                        <i class="fas fa-pen"></i>
                                        Custom
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="subject-cell">
                                        <span class="subject-preview" title="<?php echo htmlspecialchars($log['subject']); ?>">
                                            <?php echo htmlspecialchars($log['subject']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $log['status']; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <div class="date-main">
                                            <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                        </div>
                                        <div class="date-sub">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo date('h:i A', strtotime($log['created_at'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="action-btn view" 
                                                onclick="viewEmail(<?php echo (int)$log['id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($log['status'] !== 'sent'): ?>
                                        <button class="action-btn resend" 
                                                onclick="resendEmail(<?php echo (int)$log['id']; ?>)"
                                                title="Resend Email">
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
                <div class="pagination-container">
                    <div class="pagination-info">
                        <i class="fas fa-file-alt"></i>
                        Showing page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                    
                    <ul class="pagination">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): 
                        ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row g-4 mt-2">
            <div class="col-md-6">
                <div class="chart-card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-chart-pie" style="color: var(--primary);"></i>
                            Email Status Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="emailStatusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="chart-card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-chart-line" style="color: var(--success);"></i>
                            Daily Email Volume (Last 7 Days)
                        </h5>
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
<div class="modal fade" id="viewEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-envelope-open-text me-2"></i>
                    Email Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="emailDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="resendCurrentEmail()" id="resendModalBtn">
                    <i class="fas fa-redo-alt me-2"></i>Resend Email
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

// View email details
function viewEmail(emailId) {
    currentEmailId = parseInt(emailId);
    
    const modalContent = document.getElementById('emailDetailsContent');
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    $('#viewEmailModal').modal('show');
    
    fetch(`../ajax/settings/get-email-details.php?id=${emailId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const email = data.email;
            
            let html = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label">
                                <i class="fas fa-hashtag text-primary"></i>
                                Email ID
                            </div>
                            <div class="email-detail-value">#${email.id}</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label">
                                <i class="fas fa-envelope text-success"></i>
                                Recipient
                            </div>
                            <div class="email-detail-value">
                                <div>${escapeHtml(email.recipient_email || '')}</div>
                                ${email.recipient_name ? `<small class="text-muted">${escapeHtml(email.recipient_name)}</small>` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label">
                                <i class="fas fa-code text-warning"></i>
                                Template
                            </div>
                            <div class="email-detail-value">
                                <code>${escapeHtml(email.template_key || 'Custom')}</code>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label">
                                <i class="fas fa-tag text-info"></i>
                                Status
                            </div>
                            <div class="email-detail-value">
                                <span class="status-badge ${email.status}">
                                    <i class="fas fa-circle"></i>
                                    ${escapeHtml(email.status || '')}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label">
                                <i class="fas fa-clock text-primary"></i>
                                Sent At
                            </div>
                            <div class="email-detail-value">
                                ${email.sent_at ? new Date(email.sent_at).toLocaleString() : 'Not sent yet'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="email-detail-card">
                            <div class="email-detail-label">
                                <i class="fas fa-calendar text-success"></i>
                                Created At
                            </div>
                            <div class="email-detail-value">
                                ${new Date(email.created_at).toLocaleString()}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="email-detail-card">
                            <div class="email-detail-label">
                                <i class="fas fa-heading text-warning"></i>
                                Subject
                            </div>
                            <div class="email-detail-value">
                                ${escapeHtml(email.subject || '')}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="email-detail-label">
                            <i class="fas fa-envelope-open-text text-info"></i>
                            Message
                        </div>
                        <div class="email-message">
                            ${escapeHtml(email.message || '')}
                        </div>
                    </div>
            `;
            
            if (email.error_message) {
                html += `
                    <div class="col-12">
                        <div class="error-details">
                            <h6 class="mb-2">
                                <i class="fas fa-exclamation-triangle text-danger"></i>
                                Error Details
                            </h6>
                            <pre class="mb-0"><code>${escapeHtml(email.error_message)}</code></pre>
                        </div>
                    </div>
                `;
            }
            
            html += `</div>`;
            
            modalContent.innerHTML = html;
            
            // Show/hide resend button based on status
            const resendBtn = document.getElementById('resendModalBtn');
            if (email.status === 'sent') {
                resendBtn.style.display = 'none';
            } else {
                resendBtn.style.display = 'inline-block';
            }
        } else {
            modalContent.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalContent.innerHTML = '<div class="alert alert-danger">Failed to load email details.</div>';
    });
}

// Resend email
function resendEmail(emailId) {
    emailId = parseInt(emailId);
    
    Swal.fire({
        title: 'Resend Email',
        text: 'Resend this email?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, resend it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sending...',
                text: 'Please wait while we resend the email.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../ajax/settings/resend-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email_id: emailId })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Resent!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Resend current email from modal
function resendCurrentEmail() {
    if (currentEmailId) {
        $('#viewEmailModal').modal('hide');
        setTimeout(() => {
            resendEmail(currentEmailId);
        }, 500);
    }
}

// Clear email logs
function clearEmailLogs() {
    Swal.fire({
        title: 'Clear Email Logs',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Are you sure you want to delete all email logs?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, clear all logs',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Clearing...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../ajax/settings/clear-email-logs.php')
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cleared!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Export email logs
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
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3085d6',
        denyButtonColor: '#1cc88a'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`export-email-logs.php?${params.toString()}&format=json`, '_blank');
            showToast('success', 'Exporting as JSON...');
        } else if (result.isDenied) {
            window.open(`export-email-logs.php?${params.toString()}&format=csv`, '_blank');
            showToast('success', 'Exporting as CSV...');
        }
    });
}

// Refresh logs
function refreshLogs() {
    location.reload();
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show toast notification
function showToast(type, message) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: type,
        title: message
    });
}

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Status Distribution Chart
    const statusCtx = document.getElementById('emailStatusChart');
    if (statusCtx) {
        const sent = <?php echo (int)$stats['sent']; ?>;
        const failed = <?php echo (int)$stats['failed']; ?>;
        const pending = <?php echo (int)$stats['pending']; ?>;
        const bounced = <?php echo (int)$stats['bounced']; ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sent', 'Failed', 'Pending', 'Bounced'],
                datasets: [{
                    data: [sent, failed, pending, bounced],
                    backgroundColor: [
                        '#06d6a0',
                        '#ef476f',
                        '#ffb703',
                        '#4cc9f0'
                    ],
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
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Daily Volume Chart
    const volumeCtx = document.getElementById('emailVolumeChart');
    if (volumeCtx) {
        const labels = <?php echo json_encode($chart_labels); ?>;
        const data = <?php echo json_encode($chart_data); ?>;
        
        new Chart(volumeCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Emails Sent',
                    data: data,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#4361ee',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Emails: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
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
    }
    
    // Add animation to table rows
    document.querySelectorAll('.logs-table tbody tr').forEach((row, index) => {
        row.style.animation = `fadeIn 0.3s ease ${index * 0.02}s forwards`;
        row.style.opacity = '0';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>