<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'API Configuration';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Create api_keys table if not exists
    $table_exists = $db->query("SHOW TABLES LIKE 'api_keys'")->fetch();
    if (!$table_exists) {
        $db->exec("CREATE TABLE api_keys (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            api_key VARCHAR(64) UNIQUE NOT NULL,
            api_secret VARCHAR(64) NOT NULL,
            user_id INT,
            permissions TEXT,
            rate_limit INT DEFAULT 100,
            requests_today INT DEFAULT 0,
            total_requests INT DEFAULT 0,
            last_used DATETIME,
            expires_at DATETIME,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");
        
        // Create api_logs table
        $db->exec("CREATE TABLE api_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            api_key_id INT,
            endpoint VARCHAR(255),
            method VARCHAR(10),
            status_code INT,
            response_time FLOAT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
        )");
    }
    
    // Get API keys
    $stmt = $db->query("SELECT ak.*, u.username, u.email 
                        FROM api_keys ak 
                        LEFT JOIN users u ON ak.user_id = u.id 
                        ORDER BY ak.created_at DESC");
    $api_keys = $stmt->fetchAll();
    
    // Get API statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_keys,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_keys,
        SUM(requests_today) as today_requests,
        SUM(total_requests) as total_requests
        FROM api_keys";
    $stats = $db->query($stats_sql)->fetch();
    
    // Get recent API logs
    $logs_sql = "SELECT al.*, ak.name as api_name 
                 FROM api_logs al 
                 JOIN api_keys ak ON al.api_key_id = ak.id 
                 ORDER BY al.created_at DESC 
                 LIMIT 10";
    $recent_logs = $db->query($logs_sql)->fetchAll();
    
    // Get users for dropdown
    $users = $db->query("SELECT id, username, email FROM users WHERE user_type = 'admin' OR user_type = 'user' ORDER BY username")->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error loading API settings: ' . $e->getMessage();
    $api_keys = [];
    $stats = ['total_keys' => 0, 'active_keys' => 0, 'today_requests' => 0, 'total_requests' => 0];
    $recent_logs = [];
    $users = [];
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
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stat-card .stat-icon {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 50px;
    height: 50px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    opacity: 0.2;
    transition: var(--transition);
}

.stat-card:hover .stat-icon {
    opacity: 0.3;
    transform: scale(1.2);
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    color: var(--gray-600);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .stat-trend {
    margin-top: 1rem;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stat-card .trend-up {
    color: var(--success);
}

.stat-card .trend-down {
    color: var(--danger);
}

/* API Keys Card */
.keys-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.keys-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.keys-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.keys-card .card-header h5 i {
    color: var(--primary);
}

.keys-card .card-body {
    padding: 2rem;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    border-radius: var(--border-radius-lg);
}

.api-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.5rem;
}

.api-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: var(--gray-100);
    border-radius: var(--border-radius-md);
}

.api-table td {
    padding: 1rem;
    background: white;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}

.api-table tbody tr {
    animation: slideIn 0.3s ease;
    animation-fill-mode: both;
}

.api-table tbody tr:hover td {
    transform: scale(1.01);
    box-shadow: var(--shadow-lg);
    background: linear-gradient(135deg, white, var(--gray-100));
}

.api-table .key-name {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.api-table .key-meta {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.api-table .key-value {
    font-family: 'Courier New', monospace;
    background: var(--gray-100);
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-sm);
    font-size: 0.9rem;
    color: var(--primary-dark);
}

.api-table .badge-user {
    background: var(--primary-light);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-full);
    font-size: 0.75rem;
}

.api-table .requests-cell {
    text-align: center;
}

.api-table .requests-count {
    font-weight: 600;
    color: var(--gray-800);
}

.api-table .requests-total {
    font-size: 0.75rem;
    color: var(--gray-500);
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

.status-badge.active {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.status-badge.inactive {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.status-badge i {
    font-size: 0.6rem;
}

/* Method Badges */
.method-badge {
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-sm);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.method-get {
    background: rgba(67, 97, 238, 0.15);
    color: var(--primary);
}

.method-post {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
}

.method-put {
    background: rgba(255, 183, 3, 0.15);
    color: var(--warning);
}

.method-delete {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
}

/* Button Styles */
.btn {
    border-radius: var(--border-radius-lg);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    font-size: 0.95rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
}

.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.5s, height 0.5s;
}

.btn:hover::before {
    width: 300px;
    height: 300px;
}

.btn:active {
    transform: scale(0.95);
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.btn-primary:hover {
    box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4);
    transform: translateY(-2px);
}

.btn-outline-primary {
    background: transparent;
    border: 2px solid var(--primary);
    color: var(--primary);
}

.btn-outline-primary:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.btn-icon {
    padding: 0.5rem;
    border-radius: var(--border-radius-md);
}

.btn-icon i {
    margin: 0;
    font-size: 1rem;
}

.action-group {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
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

.logs-table {
    width: 100%;
    border-collapse: collapse;
}

.logs-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: var(--gray-100);
    border-bottom: 2px solid var(--gray-200);
}

.logs-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-600);
}

.logs-table tr:hover td {
    background: var(--gray-100);
}

.logs-table .log-time {
    font-weight: 600;
    color: var(--gray-700);
}

.logs-table .log-date {
    font-size: 0.75rem;
    color: var(--gray-500);
}

/* Documentation Card */
.doc-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-top: 2rem;
    animation: slideIn 0.5s ease;
}

.doc-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
}

.doc-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.doc-card .card-header h5 i {
    color: var(--warning);
}

.doc-card .card-body {
    padding: 2rem;
}

.doc-item {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    height: 100%;
    transition: var(--transition);
    border: 1px solid var(--gray-200);
}

.doc-item:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
    background: white;
}

.doc-item h6 {
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.doc-item h6 i {
    color: var(--primary);
}

.doc-item code {
    display: block;
    padding: 0.75rem;
    background: var(--gray-800);
    color: var(--success);
    border-radius: var(--border-radius-md);
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.doc-item .small {
    color: var(--gray-500);
    margin-top: 0.5rem;
    display: block;
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

/* Form Styles */
.form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-control, .form-select {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.form-text {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

/* Permissions Grid */
.permissions-grid {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    max-height: 300px;
    overflow-y: auto;
    background: var(--gray-100);
}

.permission-group {
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-300);
}

.permission-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.permission-group-title {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    padding-left: 0.5rem;
}

.permission-item {
    display: inline-flex;
    align-items: center;
    margin-right: 1rem;
    margin-bottom: 0.5rem;
    padding: 0.25rem 0.5rem;
    background: white;
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-300);
    transition: var(--transition);
}

.permission-item:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, white, var(--gray-100));
}

.permission-item .form-check-input {
    margin-right: 0.25rem;
}

.permission-item .form-check-label {
    font-size: 0.9rem;
    font-weight: normal;
    margin: 0;
}

/* Alert Styles */
.alert {
    border: none;
    border-radius: var(--border-radius-lg);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideIn 0.3s ease;
}

.alert i {
    font-size: 1.25rem;
}

.alert-info {
    background: rgba(76, 201, 240, 0.1);
    color: var(--info-dark);
    border-left: 4px solid var(--info);
}

/* Toast Notifications */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius-lg);
    color: white;
    font-weight: 500;
    box-shadow: var(--shadow-xl);
    z-index: 9999;
    transform: translateX(120%);
    transition: transform 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 300px;
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-success {
    background: var(--success-gradient);
}

.toast-error {
    background: var(--danger-gradient);
}

.toast-warning {
    background: var(--warning-gradient);
}

.toast-info {
    background: var(--info-gradient);
}

/* Loading Spinner */
.spinner-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    display: none;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 3px solid var(--gray-200);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
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

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
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

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
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
        margin-bottom: 1rem;
    }
    
    .keys-card .card-body {
        padding: 1rem;
    }
    
    .api-table td {
        padding: 0.75rem;
    }
    
    .action-group {
        flex-wrap: wrap;
    }
    
    .logs-table td,
    .logs-table th {
        padding: 0.75rem;
    }
    
    .doc-item {
        margin-bottom: 1rem;
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
                    <h1><i class="fas fa-plug me-3"></i>API Configuration</h1>
                    <p class="mb-0">Manage API keys, monitor usage, and configure access</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group justify-content-md-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApiKeyModal">
                            <i class="fas fa-plus-circle me-2"></i>Generate API Key
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- API Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                <i class="fas fa-key"></i>
            </div>
            <div class="stat-value"><?php echo isset($stats['total_keys']) ? (int)$stats['total_keys'] : 0; ?></div>
            <div class="stat-label">Total API Keys</div>
            <div class="stat-trend">
                <span class="trend-up"><i class="fas fa-arrow-up"></i> <?php echo isset($stats['active_keys']) ? (int)$stats['active_keys'] : 0; ?> active</span>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo isset($stats['active_keys']) ? (int)$stats['active_keys'] : 0; ?></div>
            <div class="stat-label">Active Keys</div>
            <div class="stat-trend">
                <span class="trend-up"><i class="fas fa-percentage"></i> 
                <?php 
                $total = isset($stats['total_keys']) ? (int)$stats['total_keys'] : 0;
                $active = isset($stats['active_keys']) ? (int)$stats['active_keys'] : 0;
                echo $total > 0 ? round(($active / $total) * 100) : 0; 
                ?>% active
                </span>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value"><?php echo isset($stats['today_requests']) ? number_format((int)$stats['today_requests']) : '0'; ?></div>
            <div class="stat-label">Today's Requests</div>
            <div class="stat-trend">
                <span class="trend-up"><i class="fas fa-clock"></i> Last 24 hours</span>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                <i class="fas fa-globe"></i>
            </div>
            <div class="stat-value"><?php echo isset($stats['total_requests']) ? number_format((int)$stats['total_requests']) : '0'; ?></div>
            <div class="stat-label">Total Requests</div>
            <div class="stat-trend">
                <span class="trend-up"><i class="fas fa-calendar"></i> All time</span>
            </div>
        </div>
    </div>
</div>
        
        <!-- API Keys List -->
        <div class="keys-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-key"></i>
                    API Keys Management
                    <span class="badge bg-primary ms-2"><?php echo count($api_keys); ?> Keys</span>
                </h5>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshApiKeys()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($api_keys)): ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-key fa-4x text-muted opacity-25"></i>
                    </div>
                    <h5 class="text-muted mb-3">No API Keys Found</h5>
                    <p class="text-muted mb-4">Create your first API key to get started with API integrations</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApiKeyModal">
                        <i class="fas fa-plus-circle me-2"></i>Generate First API Key
                    </button>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="api-table">
                        <thead>
                            <tr>
                                <th>Key Details</th>
                                <th>API Credentials</th>
                                <th>User</th>
                                <th>Rate Limit</th>
                                <th>Usage</th>
                                <th>Last Used</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($api_keys as $key): ?>
                            <tr>
                                <td>
                                    <div class="key-name"><?php echo htmlspecialchars($key['name']); ?></div>
                                    <div class="key-meta">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?php echo date('M d, Y', strtotime($key['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="key-value"><?php echo substr($key['api_key'], 0, 8); ?>...<?php echo substr($key['api_key'], -4); ?></div>
                                    <button class="btn btn-sm btn-outline-primary btn-icon mt-1 copy-btn" 
                                            data-clipboard-text="<?php echo $key['api_key']; ?>"
                                            title="Copy API Key">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info btn-icon mt-1 copy-btn" 
                                            data-clipboard-text="<?php echo $key['api_secret']; ?>"
                                            title="Copy Secret">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                </td>
                                <td>
                                    <?php if ($key['username']): ?>
                                    <span class="badge-user">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($key['username']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted"><i class="fas fa-robot me-1"></i>System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo $key['rate_limit']; ?></span>
                                    <small class="text-muted d-block">req/hour</small>
                                </td>
                                <td class="requests-cell">
                                    <div class="requests-count"><?php echo number_format($key['requests_today']); ?></div>
                                    <div class="requests-total">total: <?php echo number_format($key['total_requests']); ?></div>
                                </td>
                                <td>
                                    <?php if ($key['last_used']): ?>
                                    <span class="fw-bold"><?php echo date('H:i', strtotime($key['last_used'])); ?></span>
                                    <small class="text-muted d-block"><?php echo date('M d', strtotime($key['last_used'])); ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $key['is_active'] ? 'active' : 'inactive'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo $key['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="btn btn-icon btn-outline-info" 
                                                onclick="viewApiKey(<?php echo $key['id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-icon btn-outline-warning" 
                                                onclick="editApiKey(<?php echo $key['id']; ?>)"
                                                title="Edit Key">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-icon btn-outline-danger" 
                                                onclick="deleteApiKey(<?php echo $key['id']; ?>)"
                                                title="Delete Key">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent API Activity -->
        <div class="logs-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-history"></i>
                    Recent API Activity
                </h5>
                <a href="api-logs.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-chart-bar me-2"></i>View All Logs
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_logs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-list fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No recent API activity</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>API Key</th>
                                <th>Endpoint</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Response Time</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_logs as $log): ?>
                            <tr>
                                <td>
                                    <span class="log-time"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                                    <span class="log-date d-block"><?php echo date('M d', strtotime($log['created_at'])); ?></span>
                                </td>
                                <td>
                                    <span class="badge-user">
                                        <?php echo htmlspecialchars($log['api_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($log['endpoint']); ?></code>
                                </td>
                                <td>
                                    <span class="method-badge method-<?php echo strtolower($log['method']); ?>">
                                        <?php echo $log['method']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status_color = 'success';
                                    if ($log['status_code'] >= 400 && $log['status_code'] < 500) $status_color = 'warning';
                                    if ($log['status_code'] >= 500) $status_color = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $status_color; ?>">
                                        <?php echo $log['status_code']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo round($log['response_time'], 2); ?> ms</span>
                                </td>
                                <td>
                                    <small><?php echo $log['ip_address']; ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- API Documentation -->
        <div class="doc-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-book"></i>
                    API Documentation
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="doc-item">
                            <h6>
                                <i class="fas fa-link"></i>
                                Base URL
                            </h6>
                            <code><?php echo SITE_URL; ?>api/v1/</code>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                All API endpoints are relative to this URL
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="doc-item">
                            <h6>
                                <i class="fas fa-shield-alt"></i>
                                Authentication
                            </h6>
                            <code>X-API-Key: your_api_key</code>
                            <code>X-API-Secret: your_api_secret</code>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                Include both headers in all requests
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="doc-item">
                            <h6>
                                <i class="fas fa-tachometer-alt"></i>
                                Rate Limiting
                            </h6>
                            <p class="mb-2">Default: <strong><?php echo $api_keys[0]['rate_limit'] ?? 100; ?> requests/hour</strong></p>
                            <code>X-RateLimit-Limit: 100</code>
                            <code>X-RateLimit-Remaining: 95</code>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                Check headers for current limits
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add API Key Modal -->
<div class="modal fade" id="addApiKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Generate New API Key
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addApiKeyForm">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-tag"></i>
                                Key Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="e.g., Mobile App, Webhook, Integration">
                            <div class="form-text">A descriptive name for this API key</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user"></i>
                                Assigned User
                            </label>
                            <select class="form-select" name="user_id">
                                <option value="">🔧 System (No user)</option>
                                <?php foreach($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    👤 <?php echo $user['username']; ?> (<?php echo $user['email']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optional: Assign to specific user</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-tachometer-alt"></i>
                                Rate Limit (requests/hour)
                            </label>
                            <input type="number" class="form-control" name="rate_limit" value="100" min="1" max="10000">
                            <div class="form-text">Maximum requests per hour</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-calendar-times"></i>
                                Expiration Date
                            </label>
                            <input type="date" class="form-control" name="expires_at">
                            <div class="form-text">Leave empty for no expiration</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">
                                <i class="fas fa-lock"></i>
                                Permissions
                            </label>
                            <div class="permissions-grid">
                                <!-- Basic Permissions -->
                                <div class="permission-group">
                                    <div class="permission-group-title">Basic Access</div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="read" id="permRead" checked>
                                        <label class="form-check-label" for="permRead">Read</label>
                                    </div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="write" id="permWrite">
                                        <label class="form-check-label" for="permWrite">Write</label>
                                    </div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="delete" id="permDelete">
                                        <label class="form-check-label" for="permDelete">Delete</label>
                                    </div>
                                </div>
                                
                                <!-- Users Module -->
                                <div class="permission-group">
                                    <div class="permission-group-title">Users Module</div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="users.read" id="permUsersRead">
                                        <label class="form-check-label" for="permUsersRead">Read Users</label>
                                    </div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="users.write" id="permUsersWrite">
                                        <label class="form-check-label" for="permUsersWrite">Write Users</label>
                                    </div>
                                </div>
                                
                                <!-- Products Module -->
                                <div class="permission-group">
                                    <div class="permission-group-title">Products Module</div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="products.read" id="permProductsRead">
                                        <label class="form-check-label" for="permProductsRead">Read Products</label>
                                    </div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="products.write" id="permProductsWrite">
                                        <label class="form-check-label" for="permProductsWrite">Write Products</label>
                                    </div>
                                </div>
                                
                                <!-- Orders Module -->
                                <div class="permission-group">
                                    <div class="permission-group-title">Orders Module</div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="orders.read" id="permOrdersRead">
                                        <label class="form-check-label" for="permOrdersRead">Read Orders</label>
                                    </div>
                                    <div class="permission-item">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="orders.write" id="permOrdersWrite">
                                        <label class="form-check-label" for="permOrdersWrite">Write Orders</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="generateApiKey()">
                    <i class="fas fa-key me-2"></i>Generate API Key
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View API Key Modal -->
<div class="modal fade" id="viewApiKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>
                    API Key Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="apiKeyDetailsContent">
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
                <button type="button" class="btn btn-danger" onclick="revokeApiKey()">
                    <i class="fas fa-ban me-2"></i>Revoke Key
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div class="spinner-overlay" id="loadingSpinner">
    <div class="spinner"></div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js"></script>
<script>
let currentApiKeyId = null;
let clipboard = null;

// Initialize clipboard
document.addEventListener('DOMContentLoaded', function() {
    clipboard = new ClipboardJS('.copy-btn');
    clipboard.on('success', function(e) {
        showToast('success', 'Copied to clipboard!');
        e.clearSelection();
    });
    
    // Add animation delays to stat cards
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

// Show/hide loading spinner
function showLoading() {
    document.getElementById('loadingSpinner').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingSpinner').style.display = 'none';
}

// Show toast notification
function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 
                           type === 'error' ? 'exclamation-circle' : 
                           type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Toggle API key
function toggleApiKey(checkbox, apiKeyId) {
    const isActive = checkbox.checked ? 1 : 0;
    
    fetch('ajax/toggle-api-key.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ api_key_id: apiKeyId, is_active: isActive })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            checkbox.checked = !checkbox.checked;
            showToast('error', data.message);
        } else {
            showToast('success', 'API key status updated');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        checkbox.checked = !checkbox.checked;
        showToast('error', 'An error occurred');
    });
}

// Generate API key
function generateApiKey() {
    const form = document.getElementById('addApiKeyForm');
    const formData = new FormData(form);
    
    // Get permissions
    const permissions = [];
    form.querySelectorAll('input[name="permissions[]"]:checked').forEach(cb => {
        permissions.push(cb.value);
    });
    
    showLoading();
    
    fetch('/ajax/generate-api-key.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: formData.get('name'),
            user_id: formData.get('user_id') || null,
            rate_limit: formData.get('rate_limit'),
            expires_at: formData.get('expires_at'),
            permissions: permissions
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            $('#addApiKeyModal').modal('hide');
            
            Swal.fire({
                title: 'API Key Generated!',
                html: `
                    <div class="text-center">
                        <i class="fas fa-key fa-4x text-success mb-3"></i>
                        <p><strong>${data.name}</strong></p>
                        <div class="alert alert-info text-start">
                            <p class="mb-2"><strong>API Key:</strong></p>
                            <code class="d-block p-2 bg-dark text-success rounded mb-3">${data.api_key}</code>
                            <p class="mb-2"><strong>API Secret:</strong></p>
                            <code class="d-block p-2 bg-dark text-success rounded mb-3">${data.api_secret}</code>
                            <div class="mt-3 text-center">
                                <button class="btn btn-sm btn-outline-primary me-2" onclick="copyToClipboard('${data.api_key}')">
                                    <i class="fas fa-copy me-1"></i> Copy Key
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('${data.api_secret}')">
                                    <i class="fas fa-copy me-1"></i> Copy Secret
                                </button>
                            </div>
                        </div>
                        <p class="text-danger mt-3"><i class="fas fa-exclamation-triangle me-2"></i>Save these credentials now! They won't be shown again.</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'I have saved the credentials',
                cancelButtonText: 'Show again',
                confirmButtonColor: '#1cc88a'
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('error', 'An error occurred');
    });
}

// View API key
function viewApiKey(apiKeyId) {
    currentApiKeyId = apiKeyId;
    
    fetch(`ajax/get-api-key.php?id=${apiKeyId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const apiKey = data.api_key;
            const permissions = apiKey.permissions ? JSON.parse(apiKey.permissions) : [];
            
            let html = `
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="fas fa-key text-primary fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">${apiKey.name}</h6>
                            <span class="badge bg-${apiKey.is_active ? 'success' : 'danger'}">
                                ${apiKey.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">API Key</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="${apiKey.api_key}" readonly>
                            <button class="btn btn-outline-primary copy-btn" data-clipboard-text="${apiKey.api_key}">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="text-muted small">Rate Limit</label>
                            <div class="fw-bold">${apiKey.rate_limit}/hour</div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small">User</label>
                            <div>${apiKey.username || 'System'}</div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small">Requests Today</label>
                            <div class="fw-bold">${apiKey.requests_today}</div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small">Total Requests</label>
                            <div class="fw-bold">${apiKey.total_requests}</div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small">Last Used</label>
                            <div>${apiKey.last_used ? new Date(apiKey.last_used).toLocaleString() : 'Never'}</div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small">Expires</label>
                            <div>${apiKey.expires_at ? new Date(apiKey.expires_at).toLocaleDateString() : 'Never'}</div>
                        </div>
                    </div>
            `;
            
            if (permissions.length > 0) {
                html += `
                    <div class="mb-3">
                        <label class="text-muted small">Permissions</label>
                        <div class="d-flex flex-wrap gap-1">
                            ${permissions.map(perm => `<span class="badge bg-primary">${perm}</span>`).join('')}
                        </div>
                    </div>
                `;
            }
            
            html += `</div>`;
            
            document.getElementById('apiKeyDetailsContent').innerHTML = html;
            $('#viewApiKeyModal').modal('show');
            
            // Re-initialize clipboard for new buttons
            new ClipboardJS('.copy-btn');
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Failed to load API key details');
    });
}

// Edit API key
function editApiKey(apiKeyId) {
    fetch(`ajax/get-api-key.php?id=${apiKeyId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const apiKey = data.api_key;
            
            Swal.fire({
                title: 'Edit API Key',
                html: `
                    <div class="text-start">
                        <div class="mb-3">
                            <label class="form-label">Key Name</label>
                            <input type="text" class="form-control" id="editName" value="${apiKey.name}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rate Limit</label>
                            <input type="number" class="form-control" id="editRateLimit" value="${apiKey.rate_limit}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiration Date</label>
                            <input type="date" class="form-control" id="editExpiresAt" 
                                   value="${apiKey.expires_at ? apiKey.expires_at.slice(0,10) : ''}">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="editIsActive" ${apiKey.is_active ? 'checked' : ''}>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Save Changes',
                preConfirm: () => {
                    return {
                        name: document.getElementById('editName').value,
                        rate_limit: document.getElementById('editRateLimit').value,
                        expires_at: document.getElementById('editExpiresAt').value,
                        is_active: document.getElementById('editIsActive').checked
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = result.value;
                    formData.api_key_id = apiKeyId;
                    
                    fetch('ajax/update-api-key.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('success', 'API key updated successfully');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('error', 'An error occurred');
                    });
                }
            });
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Failed to load API key');
    });
}

// Delete API key
function deleteApiKey(apiKeyId) {
    Swal.fire({
        title: 'Delete API Key',
        text: 'Are you sure you want to delete this API key? This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/delete-api-key.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ api_key_id: apiKeyId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'API key deleted successfully');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'An error occurred');
            });
        }
    });
}

// Revoke API key
function revokeApiKey() {
    if (!currentApiKeyId) return;
    
    Swal.fire({
        title: 'Revoke API Key',
        text: 'This will immediately disable the API key. You can re-enable it later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Revoke'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/revoke-api-key.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ api_key_id: currentApiKeyId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'API key revoked successfully');
                    $('#viewApiKeyModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'An error occurred');
            });
        }
    });
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('success', 'Copied to clipboard!');
    }).catch(() => {
        showToast('error', 'Failed to copy');
    });
}

// Refresh API keys
function refreshApiKeys() {
    showLoading();
    setTimeout(() => location.reload(), 500);
}
</script>

<?php require_once '../includes/footer.php'; ?>