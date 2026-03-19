<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'Settings History';
require_once '../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filter_setting = isset($_GET['setting']) ? $_GET['setting'] : '';
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filter_setting)) {
        $where[] = "sh.setting_key LIKE ?";
        $params[] = "%$filter_setting%";
    }
    
    if (!empty($filter_user)) {
        $where[] = "sh.changed_by = ?";
        $params[] = $filter_user;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(sh.changed_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(sh.changed_at) <= ?";
        $params[] = $end_date;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM settings_history sh WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get history with user info
    $history_sql = "SELECT sh.*, u.full_name, u.email 
                    FROM settings_history sh 
                    LEFT JOIN users u ON sh.changed_by = u.id 
                    WHERE $where_sql 
                    ORDER BY sh.changed_at DESC 
                    LIMIT ? OFFSET ?";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($history_sql);
    $stmt->execute($all_params);
    $history = $stmt->fetchAll();
    
    // Get users for filter
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE user_type = 'admin' ORDER BY full_name");
    $users = $stmt->fetchAll();
    
    // Get unique setting keys
    $stmt = $db->query("SELECT DISTINCT setting_key FROM settings_history ORDER BY setting_key");
    $setting_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get statistics
    $today_stmt = $db->prepare("SELECT COUNT(*) FROM settings_history WHERE DATE(changed_at) = CURDATE()");
    $today_stmt->execute();
    $today_count = $today_stmt->fetchColumn();
    
    $most_changed_stmt = $db->query("SELECT setting_key, COUNT(*) as count FROM settings_history GROUP BY setting_key ORDER BY count DESC LIMIT 1");
    $most_changed = $most_changed_stmt->fetch();
    
} catch(PDOException $e) {
    $error = 'Error loading history: ' . $e->getMessage();
    $history = [];
    $total_records = 0;
    $users = [];
    $setting_keys = [];
    $today_count = 0;
    $most_changed = null;
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

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}
.btn-export.pdf {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.btn-export.pdf:hover {
    background: #c82333;
    border-color: #bd2130;
    transform: translateY(-2px);
}   
.filter-group label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-600);
    margin-bottom: 0.25rem;
    display: block;
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
    align-items: center;
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

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stat-card .stat-label {
    color: var(--gray-600);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.stat-card .stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--gray-800);
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.stat-card .stat-desc {
    color: var(--gray-500);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* History Card */
.history-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    animation: slideIn 0.5s ease;
}

.history-card .card-header {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.history-card .card-header h5 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.history-card .card-header h5 i {
    color: var(--primary);
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

/* History Table */
.history-table-container {
    overflow-x: auto;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
}

.history-table th {
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

.history-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-600);
    transition: var(--transition);
}

.history-table tbody tr {
    transition: var(--transition);
    animation: fadeIn 0.3s ease;
}

.history-table tbody tr:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
}

.history-table tbody tr:hover td {
    color: var(--gray-800);
}

/* History ID */
.history-id {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: var(--gray-200);
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
}

/* Setting Key */
.setting-key {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: var(--primary);
    background: rgba(67, 97, 238, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.85rem;
    display: inline-block;
}

/* Value Cell */
.value-cell {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.value-null {
    color: var(--gray-400);
    font-style: italic;
}

.value-preview {
    background: var(--gray-100);
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-sm);
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
}

/* User Cell */
.user-cell {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    color: var(--gray-800);
}

.user-email {
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

/* Date Cell */
.date-cell {
    white-space: nowrap;
}

.date-main {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.9rem;
}

.date-sub {
    font-size: 0.75rem;
    color: var(--gray-500);
}

/* Action Button */
.btn-view {
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

.btn-view:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
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
    padding: 1.25rem 1.5rem;
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
    font-weight: 600;
    font-size: 1.1rem;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
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
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid var(--gray-200);
    padding: 1rem 1.5rem;
}

/* Detail Cards */
.detail-card {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    border: 1px solid var(--gray-200);
    height: 100%;
}

.detail-label {
    font-size: 0.8rem;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.detail-value {
    font-weight: 600;
    color: var(--gray-800);
    word-break: break-word;
    background: white;
    padding: 0.5rem;
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-200);
}

.detail-value pre {
    margin: 0;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    white-space: pre-wrap;
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
    
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .filter-form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .btn-filter {
        flex: 1;
    }
    
    .history-table th,
    .history-table td {
        padding: 0.75rem;
    }
    
    .pagination-container {
        flex-direction: column;
        text-align: center;
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
                        <i class="fas fa-history"></i>
                        Settings History
                    </h1>
                    <p class="mb-0">Audit trail of all configuration changes</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-danger" onclick="clearHistory()">
                        <i class="fas fa-trash-alt me-2"></i>Clear History
                    </button>
                    <a href="settings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Filter Card -->
        <div class="filter-card">
            <div class="card-header">
                <i class="fas fa-filter"></i>
                <h5>Filter History</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label>Setting Key</label>
                        <input type="text" 
                               name="setting" 
                               class="form-control" 
                               placeholder="e.g., site_name"
                               value="<?php echo htmlspecialchars($filter_setting); ?>"
                               list="settingKeys">
                        <datalist id="settingKeys">
                            <?php foreach($setting_keys as $key): ?>
                            <option value="<?php echo $key; ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="filter-group">
                        <label>User</label>
                        <select name="user_id" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" 
                               name="start_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" 
                               name="end_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i>
                            Filter
                        </button>
                        <a href="settings-history.php" class="btn-reset" title="Reset Filters">
                            <i class="fas fa-redo-alt"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Total Changes</div>
                <div class="stat-value"><?php echo number_format($total_records); ?></div>
                <div class="stat-desc">
                    <i class="fas fa-calendar-alt"></i>
                    All time
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Today's Changes</div>
                <div class="stat-value"><?php echo number_format($today_count); ?></div>
                <div class="stat-desc">
                    <i class="fas fa-sun"></i>
                    <?php echo date('M d, Y'); ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Most Changed</div>
                <?php if ($most_changed): ?>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo $most_changed['count']; ?></div>
                <div class="stat-desc">
                    <i class="fas fa-key"></i>
                    <code><?php echo substr($most_changed['setting_key'], 0, 20) . (strlen($most_changed['setting_key']) > 20 ? '...' : ''); ?></code>
                </div>
                <?php else: ?>
                <div class="stat-value" style="font-size: 1.5rem;">0</div>
                <div class="stat-desc">No data</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- History Table -->
        <div class="history-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i>
                    Change History
                    <span class="badge bg-primary ms-2"><?php echo number_format($total_records); ?> Records</span>
                </h5>
                <div class="header-actions">
    <button class="btn-export" onclick="exportHistory('pdf')">
        <i class="fas fa-file-pdf"></i>
        PDF
    </button>
    <button class="btn-export" onclick="exportHistory('csv')">
        <i class="fas fa-file-csv"></i>
        CSV
    </button>
    <button class="btn-export" onclick="exportHistory('json')">
        <i class="fas fa-file-code"></i>
        JSON
    </button>
</div>
            </div>
            
            <?php if (empty($history)): ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <h5>No History Found</h5>
                <p class="text-muted">No setting changes match your filters</p>
                <a href="settings-history.php" class="btn btn-primary">
                    <i class="fas fa-redo-alt me-2"></i>Clear Filters
                </a>
            </div>
            <?php else: ?>
            <div class="history-table-container">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Setting</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                            <th>Changed By</th>
                            <th>Date & Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $index => $record): ?>
                        <tr style="animation-delay: <?php echo $index * 0.02; ?>s;">
                            <td>
                                <span class="history-id">#<?php echo $record['id']; ?></span>
                            </td>
                            <td>
                                <span class="setting-key">
                                    <i class="fas fa-cog"></i>
                                    <?php echo $record['setting_key']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="value-cell" title="<?php echo htmlspecialchars($record['old_value'] ?? ''); ?>">
                                    <?php if ($record['old_value'] !== null): ?>
                                    <span class="value-preview">
                                        <?php echo htmlspecialchars(substr($record['old_value'], 0, 50)) . (strlen($record['old_value']) > 50 ? '...' : ''); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="value-null">NULL</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="value-cell" title="<?php echo htmlspecialchars($record['new_value'] ?? ''); ?>">
                                    <?php if ($record['new_value'] !== null): ?>
                                    <span class="value-preview">
                                        <?php echo htmlspecialchars(substr($record['new_value'], 0, 50)) . (strlen($record['new_value']) > 50 ? '...' : ''); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="value-null">NULL</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($record['full_name']): ?>
                                <div class="user-cell">
                                    <span class="user-name">
                                        <i class="fas fa-user-circle me-1"></i>
                                        <?php echo htmlspecialchars($record['full_name']); ?>
                                    </span>
                                    <span class="user-email"><?php echo htmlspecialchars($record['email']); ?></span>
                                </div>
                                <?php else: ?>
                                <span class="system-badge">
                                    <i class="fas fa-robot me-1"></i>
                                    System
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="date-cell">
                                    <div class="date-main">
                                        <?php echo date('M d, Y', strtotime($record['changed_at'])); ?>
                                    </div>
                                    <div class="date-sub">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('h:i A', strtotime($record['changed_at'])); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <button class="btn-view" onclick="viewChangeDetails(<?php echo $record['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
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
    </main>
</div>

<!-- Change Details Modal -->
<div class="modal fade" id="changeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Change Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="changeDetailsContent">
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
                <button type="button" class="btn btn-warning" onclick="revertChange()">
                    <i class="fas fa-undo-alt me-2"></i>Revert Change
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentChangeId = null;

// View change details
function viewChangeDetails(changeId) {
    currentChangeId = changeId;
    
    const modalContent = document.getElementById('changeDetailsContent');
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    $('#changeDetailsModal').modal('show');
    
    fetch(`ajax/get-change-details.php?id=${changeId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const change = data.change;
            
            modalContent.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-hashtag text-primary"></i>
                                Change ID
                            </div>
                            <div class="detail-value">#${change.id}</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-cog text-success"></i>
                                Setting Key
                            </div>
                            <div class="detail-value"><code>${change.setting_key}</code></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-user-circle text-info"></i>
                                Changed By
                            </div>
                            <div class="detail-value">
                                ${change.full_name ? 
                                    `<div>${escapeHtml(change.full_name)}</div>
                                     <small class="text-muted">${escapeHtml(change.email || '')}</small>` : 
                                    '<span class="system-badge">System</span>'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-clock text-warning"></i>
                                Changed At
                            </div>
                            <div class="detail-value">
                                ${new Date(change.changed_at).toLocaleString()}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-code-branch text-primary"></i>
                                Old Value
                            </div>
                            <div class="detail-value">
                                <pre>${escapeHtml(change.old_value || 'NULL')}</pre>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-code-branch text-success"></i>
                                New Value
                            </div>
                            <div class="detail-value">
                                <pre>${escapeHtml(change.new_value || 'NULL')}</pre>
                            </div>
                        </div>
                    </div>
                    
                    ${change.ip_address ? `
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-network-wired text-info"></i>
                                IP Address
                            </div>
                            <div class="detail-value">${change.ip_address}</div>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${change.user_agent ? `
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-laptop text-secondary"></i>
                                User Agent
                            </div>
                            <div class="detail-value"><small>${escapeHtml(change.user_agent)}</small></div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            modalContent.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalContent.innerHTML = '<div class="alert alert-danger">Failed to load change details.</div>';
    });
}

// Revert change
function revertChange() {
    if (!currentChangeId) return;
    
    Swal.fire({
        title: 'Revert Change',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>Revert this setting to its previous value?</p>
                <p class="text-muted small">This will create a new history entry.</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, revert',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Reverting...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('ajax/revert-change.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ change_id: currentChangeId })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reverted!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#changeDetailsModal').modal('hide');
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

// Export history
function exportHistory(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    
    Swal.fire({
        title: 'Export History',
        text: `Export settings history as ${format.toUpperCase()}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Export'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`export-settings-history.php?${params.toString()}`, '_blank');
            Swal.fire('Success!', 'Export started.', 'success');
        }
    });
}

// Clear history
function clearHistory() {
    Swal.fire({
        title: 'Clear History',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Delete all settings change history?</p>
                <p class="text-muted small">This action cannot be undone!</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, clear all',
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
            
            fetch('ajax/clear-history.php')
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

// Escape HTML helper
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize animations
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.history-table tbody tr').forEach((row, index) => {
        row.style.animation = `fadeIn 0.3s ease ${index * 0.02}s forwards`;
        row.style.opacity = '0';
    });
});
// Export history
function exportHistory(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    
    let title = 'Export History';
    let text = `Export settings history as ${format.toUpperCase()}?`;
    
    if (format === 'pdf') {
        text = 'Generate PDF report of settings history?';
    }
    
    Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Export'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`ajax/export-settings-history.php?${params.toString()}`, '_blank');
            
            if (format !== 'pdf') {
                Swal.fire('Success!', 'Export started.', 'success');
            }
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>