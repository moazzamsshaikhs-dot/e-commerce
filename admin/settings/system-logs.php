<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'System Logs';
require_once '../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$log_type = isset($_GET['log_type']) ? $_GET['log_type'] : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($log_type)) {
        $where[] = "activity_type = ?";
        $params[] = $log_type;
    }
    
    if (!empty($user_id)) {
        $where[] = "ua.user_id = ?";
        $params[] = $user_id;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(ua.created_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(ua.created_at) <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($search)) {
        $where[] = "(ua.description LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total 
                  FROM user_activities ua 
                  LEFT JOIN users u ON ua.user_id = u.id 
                  WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get logs with user info
    $logs_sql = "SELECT ua.*, u.full_name, u.username, u.email 
                 FROM user_activities ua 
                 LEFT JOIN users u ON ua.user_id = u.id 
                 WHERE $where_sql 
                 ORDER BY ua.created_at DESC 
                 LIMIT ? OFFSET ?";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($logs_sql);
    $stmt->execute($all_params);
    $logs = $stmt->fetchAll();
    
    // Get users for filter
    $stmt = $db->query("SELECT id, username, full_name FROM users ORDER BY username");
    $users = $stmt->fetchAll();
    
    // Get distinct activity types
    $stmt = $db->query("SELECT DISTINCT activity_type FROM user_activities WHERE activity_type != '' ORDER BY activity_type");
    $activity_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get today's logs count
    $stmt = $db->query("SELECT COUNT(*) as count FROM user_activities WHERE DATE(created_at) = CURDATE()");
    $today_logs = $stmt->fetch()['count'];
    
    // Get most active user
    $stmt = $db->query("SELECT u.username, COUNT(*) as count 
                        FROM user_activities ua 
                        JOIN users u ON ua.user_id = u.id 
                        GROUP BY ua.user_id 
                        ORDER BY count DESC 
                        LIMIT 1");
    $most_active = $stmt->fetch();
    
} catch(PDOException $e) {
    $error = 'Error loading logs: ' . $e->getMessage();
    $logs = [];
    $total_records = 0;
    $users = [];
    $activity_types = [];
    $today_logs = 0;
    $most_active = null;
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
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-header h1 i {
    font-size: 2rem;
    color: var(--primary);
    -webkit-text-fill-color: initial;
}

.page-header p {
    color: var(--gray-600);
    font-size: 1rem;
    margin-bottom: 0;
}

/* Stat Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 1.5rem;
    transition: var(--transition);
    animation: slideIn 0.5s ease;
    animation-fill-mode: both;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }

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
    line-height: 1.2;
    margin-bottom: 0.25rem;
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

.filter-card .card-header {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.filter-card .card-header i {
    color: var(--primary);
    font-size: 1.2rem;
}

.filter-card .card-header h5 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    font-size: 1rem;
}

.filter-card .card-body {
    padding: 1.5rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-600);
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.filter-group .form-control,
.filter-group .form-select {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-md);
    padding: 0.6rem 1rem;
    font-size: 0.9rem;
    width: 100%;
}

.filter-group .form-control:focus,
.filter-group .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-filter {
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--border-radius-md);
    padding: 0.6rem 1.5rem;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.btn-reset {
    background: white;
    color: var(--gray-600);
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-md);
    padding: 0.6rem 1rem;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
}

.btn-reset:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
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
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logs-card .card-header h5 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.logs-card .card-header h5 i {
    color: var(--info);
    font-size: 1.2rem;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-export {
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius-md);
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    border: 1px solid var(--gray-200);
    background: white;
    color: var(--gray-700);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-export:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

.btn-refresh {
    width: 36px;
    height: 36px;
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-200);
    background: white;
    color: var(--gray-600);
    transition: var(--transition);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-refresh:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: rotate(180deg);
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

/* Log ID */
.log-id {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: var(--gray-200);
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
}

/* Timestamp Cell */
.timestamp-cell {
    white-space: nowrap;
}

.timestamp-date {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.9rem;
}

.timestamp-time {
    font-size: 0.75rem;
    color: var(--gray-500);
}

/* User Cell */
.user-cell {
    display: flex;
    flex-direction: column;
}

.user-username {
    font-weight: 600;
    color: var(--gray-800);
}

.user-fullname {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.system-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: var(--gray-200);
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    color: var(--gray-600);
}

/* Activity Badge */
.activity-badge {
    padding: 0.35rem 1rem;
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.activity-badge.login {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.activity-badge.logout {
    background: rgba(255, 183, 3, 0.15);
    color: var(--warning);
    border: 1px solid rgba(255, 183, 3, 0.3);
}

.activity-badge.error {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.activity-badge.create {
    background: rgba(76, 201, 240, 0.15);
    color: var(--info);
    border: 1px solid rgba(76, 201, 240, 0.3);
}

.activity-badge.update {
    background: rgba(67, 97, 238, 0.15);
    color: var(--primary);
    border: 1px solid rgba(67, 97, 238, 0.3);
}

.activity-badge.delete {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.activity-badge.default {
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}

/* Description Cell */
.description-cell {
    max-width: 400px;
}

.description-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.9rem;
}

/* IP Address */
.ip-address {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    color: var(--gray-600);
}

/* Pagination */
.pagination-container {
    padding: 1rem 1.5rem;
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
    min-width: 36px;
    height: 36px;
    padding: 0 0.5rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-md);
    background: white;
    color: var(--gray-700);
    font-weight: 600;
    transition: var(--transition);
    text-decoration: none;
    font-size: 0.9rem;
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
}

/* Chart Card */
.chart-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-top: 2rem;
    animation: slideIn 0.5s ease;
}

.chart-card .card-header {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.chart-card .card-header i {
    color: var(--success);
    font-size: 1.2rem;
}

.chart-card .card-header h5 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    font-size: 1rem;
}

.chart-card .card-body {
    padding: 1.5rem;
}

/* Progress Bars */
.activity-item {
    margin-bottom: 1rem;
}

.activity-item .activity-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.activity-name {
    font-weight: 600;
    color: var(--gray-700);
}

.activity-count {
    color: var(--gray-600);
}

.progress {
    height: 8px;
    border-radius: var(--border-radius-full);
    background: var(--gray-200);
    overflow: hidden;
}

.progress-bar {
    background: var(--primary-gradient);
    border-radius: var(--border-radius-full);
    transition: width 0.6s ease;
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

/* Responsive */
@media (max-width: 992px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        margin-top: 0.5rem;
    }
    
    .btn-filter {
        flex: 1;
    }
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .logs-table th,
    .logs-table td {
        padding: 0.75rem;
    }
    
    .pagination-container {
        flex-direction: column;
        text-align: center;
    }
    
    .page-link {
        min-width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: var(--border-radius-full);
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>
                        <i class="fas fa-clipboard-list"></i>
                        System Logs
                    </h1>
                    <p class="mb-0">Audit trail of all system activities</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-danger" onclick="clearLogs()">
                        <i class="fas fa-trash-alt me-2"></i>Clear Logs
                    </button>
                    <button class="btn btn-primary" onclick="exportLogs()">
                        <i class="fas fa-download me-2"></i>Export Logs
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($total_records); ?></div>
                    <div class="stat-label">Total Logs</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($today_logs); ?></div>
                    <div class="stat-label">Today's Logs</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo count($activity_types); ?></div>
                    <div class="stat-label">Activity Types</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-content">
                    <?php if ($most_active): ?>
                    <div class="stat-value"><?php echo htmlspecialchars($most_active['username']); ?></div>
                    <div class="stat-label">Most Active</div>
                    <small class="text-muted"><?php echo $most_active['count']; ?> activities</small>
                    <?php else: ?>
                    <div class="stat-value">N/A</div>
                    <div class="stat-label">Most Active</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-card">
            <div class="card-header">
                <i class="fas fa-filter"></i>
                <h5>Filter Logs</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-grid">
                    <div class="filter-group">
                        <label>Activity Type</label>
                        <select name="log_type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach($activity_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $log_type == $type ? 'selected' : ''; ?>>
                                <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>User</label>
                        <select name="user_id" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search in logs...">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i>
                            Filter
                        </button>
                        <a href="system-logs.php" class="btn-reset" title="Reset Filters">
                            <i class="fas fa-redo-alt"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Logs Table -->
        <div class="logs-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i>
                    Activity Logs
                    <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?> Records</span>
                </h5>
                <div class="header-actions">
                    <button class="btn-export" onclick="exportLogs()">
                        <i class="fas fa-download"></i>
                        Export
                    </button>
                    <button class="btn-refresh" onclick="refreshLogs()" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            
            <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h5>No Logs Found</h5>
                <p class="text-muted">No activity logs match your filters</p>
                <a href="system-logs.php" class="btn btn-primary">
                    <i class="fas fa-redo-alt me-2"></i>Clear Filters
                </a>
            </div>
            <?php else: ?>
            <div class="logs-table-container">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Activity</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $index => $log): ?>
                        <tr style="animation-delay: <?php echo $index * 0.01; ?>s;">
                            <td>
                                <span class="log-id">#<?php echo $log['id']; ?></span>
                            </td>
                            <td>
                                <div class="timestamp-cell">
                                    <div class="timestamp-date">
                                        <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                    </div>
                                    <div class="timestamp-time">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('h:i:s A', strtotime($log['created_at'])); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($log['user_id']): ?>
                                <div class="user-cell">
                                    <span class="user-username">
                                        <i class="fas fa-user-circle me-1"></i>
                                        <?php echo htmlspecialchars($log['username']); ?>
                                    </span>
                                    <span class="user-fullname"><?php echo htmlspecialchars($log['full_name']); ?></span>
                                </div>
                                <?php else: ?>
                                <span class="system-badge">
                                    <i class="fas fa-robot me-1"></i>
                                    System
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $badge_class = 'default';
                                switch($log['activity_type']) {
                                    case 'login': $badge_class = 'login'; break;
                                    case 'logout': $badge_class = 'logout'; break;
                                    case 'error': $badge_class = 'error'; break;
                                    case 'create': $badge_class = 'create'; break;
                                    case 'update': $badge_class = 'update'; break;
                                    case 'delete': $badge_class = 'delete'; break;
                                }
                                ?>
                                <span class="activity-badge <?php echo $badge_class; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $log['activity_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="description-cell">
                                    <div class="description-text" title="<?php echo htmlspecialchars($log['description']); ?>">
                                        <?php echo htmlspecialchars($log['description']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="ip-address">
                                    <i class="fas fa-network-wired me-1"></i>
                                    <?php echo $log['ip_address'] ?? 'N/A'; ?>
                                </span>
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
                    <i class="fas fa-file-alt me-1"></i>
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
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
        
        <!-- Activity Distribution Chart -->
        <?php if (!empty($logs)): ?>
        <div class="chart-card">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i>
                <h5>Activity Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    try {
                        $stmt = $db->query("SELECT activity_type, COUNT(*) as count 
                                           FROM user_activities 
                                           WHERE activity_type != '' 
                                           GROUP BY activity_type 
                                           ORDER BY count DESC 
                                           LIMIT 10");
                        $activity_counts = $stmt->fetchAll();
                        
                        foreach($activity_counts as $activity):
                            $percentage = ($activity['count'] / $total_records) * 100;
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="activity-item">
                            <div class="activity-header">
                                <span class="activity-name"><?php echo ucwords(str_replace('_', ' ', $activity['activity_type'])); ?></span>
                                <span class="activity-count"><?php echo number_format($activity['count']); ?> (<?php echo round($percentage, 1); ?>%)</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php } catch(Exception $e) {} ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Clear logs
function clearLogs() {
    Swal.fire({
        title: 'Clear All Logs',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Delete all system activity logs?</p>
                <p class="text-muted small">This action cannot be undone!</p>
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
            
            fetch('ajax/clear-logs.php')
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

// Export logs - Three options version
function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    
    Swal.fire({
        title: 'Export Logs',
        html: `
            <div class="d-grid gap-2">
                <button class="btn btn-primary" id="export-json">
                    <i class="fas fa-file-code me-2"></i> Export as JSON
                </button>
                <button class="btn btn-success" id="export-csv">
                    <i class="fas fa-file-csv me-2"></i> Export as CSV
                </button>
                <button class="btn btn-danger" id="export-pdf">
                    <i class="fas fa-file-pdf me-2"></i> Export as PDF
                </button>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Cancel',
        didOpen: () => {
            document.getElementById('export-json').onclick = () => {
                Swal.close();
                window.open(`ajax/export-logs.php?${params.toString()}&format=json`, '_blank');
                Swal.fire('Success!', 'Exporting as JSON...', 'success');
            };
            document.getElementById('export-csv').onclick = () => {
                Swal.close();
                window.open(`ajax/export-logs.php?${params.toString()}&format=csv`, '_blank');
                Swal.fire('Success!', 'Exporting as CSV...', 'success');
            };
            document.getElementById('export-pdf').onclick = () => {
                Swal.close();
                window.open(`ajax/export-logs.php?${params.toString()}&format=pdf`, '_blank');
                Swal.fire('Success!', 'Exporting as PDF...', 'success');
            };
        }
    });
}

// Refresh logs
function refreshLogs() {
    location.reload();
}

// Initialize animations
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.logs-table tbody tr').forEach((row, index) => {
        row.style.animation = `fadeIn 0.3s ease ${index * 0.01}s forwards`;
        row.style.opacity = '0';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>