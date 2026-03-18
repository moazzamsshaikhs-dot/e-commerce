<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'System Settings';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get all settings groups
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $settings_groups = $stmt->fetchAll();
    
    // Get settings count per group
    $settings_counts = [];
    foreach ($settings_groups as $group) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE `group` = ?");
        $stmt->execute([$group['slug']]);
        $settings_counts[$group['slug']] = $stmt->fetchColumn();
    }
    
    // Get recent changes
    $stmt = $db->query("SELECT sh.*, u.full_name 
                        FROM settings_history sh 
                        LEFT JOIN users u ON sh.changed_by = u.id 
                        ORDER BY sh.changed_at DESC 
                        LIMIT 10");
    $recent_changes = $stmt->fetchAll();
    
    // Get system information
    $system_info = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'mysql_version' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'max_input_vars' => ini_get('max_input_vars'),
        'post_max_size' => ini_get('post_max_size'),
    ];
    
} catch(PDOException $e) {
    $error = 'Error loading settings: ' . $e->getMessage();
    $settings_groups = [];
    $settings_counts = [];
    $recent_changes = [];
    $system_info = [];
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

/* Main Content */
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

/* Settings Group Cards */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.setting-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    transition: var(--transition);
    position: relative;
    animation: scaleIn 0.5s ease;
    animation-fill-mode: both;
    cursor: pointer;
    text-decoration: none;
    display: block;
    color: inherit;
}

.setting-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary);
}

.setting-card:hover .card-icon {
    transform: scale(1.1) rotate(5deg);
}

.setting-card .card-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.setting-card .card-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    transition: var(--transition-bounce);
}

.setting-card .card-icon.primary { background: var(--primary-gradient); }
.setting-card .card-icon.success { background: var(--success-gradient); }
.setting-card .card-icon.warning { background: var(--warning-gradient); }
.setting-card .card-icon.danger { background: var(--danger-gradient); }
.setting-card .card-icon.info { background: var(--info-gradient); }

.setting-card .card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.setting-card .card-subtitle {
    font-size: 0.85rem;
    color: var(--gray-600);
}

.setting-card .card-body {
    padding: 1.5rem;
}

.setting-card .settings-count {
    display: inline-block;
    padding: 0.35rem 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 1rem;
}

.setting-card .settings-count i {
    margin-right: 0.5rem;
    color: var(--primary);
}

.setting-card .card-description {
    color: var(--gray-600);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.setting-card .card-footer {
    padding: 1rem 1.5rem;
    background: var(--gray-100);
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.setting-card .manage-badge {
    background: var(--primary-gradient);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius-full);
    font-size: 0.85rem;
    font-weight: 600;
    transition: var(--transition);
}

.setting-card:hover .manage-badge {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

/* Action Buttons Grid */
.action-buttons-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin: 2rem 0;
    padding: 1.5rem;
    background: white;
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: var(--border-radius-full);
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
}

.action-btn i {
    font-size: 1rem;
}

.action-btn-primary {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.2);
}

.action-btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
    color: white;
}

.action-btn-success {
    background: var(--success-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(6, 214, 160, 0.2);
}

.action-btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(6, 214, 160, 0.3);
    color: white;
}

.action-btn-info {
    background: var(--info-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(76, 201, 240, 0.2);
}

.action-btn-info:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(76, 201, 240, 0.3);
    color: white;
}

.action-btn-warning {
    background: var(--warning-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(255, 183, 3, 0.2);
}

.action-btn-warning:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(255, 183, 3, 0.3);
    color: white;
}

.action-btn-outline {
    background: white;
    border: 1px solid var(--gray-300);
    color: var(--gray-700);
}

.action-btn-outline:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-3px);
}

/* System Info Card */
.system-info-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    height: 100%;
}

.system-info-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.system-info-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.system-info-header h5 i {
    color: var(--primary);
}

.system-info-body {
    padding: 1.5rem;
}

.info-table {
    width: 100%;
}

.info-table tr {
    border-bottom: 1px solid var(--gray-200);
}

.info-table tr:last-child {
    border-bottom: none;
}

.info-table td {
    padding: 1rem 0;
}

.info-table td:first-child {
    font-weight: 600;
    color: var(--gray-700);
    width: 40%;
}

.info-table td:last-child {
    color: var(--gray-600);
    font-family: 'Courier New', monospace;
}

/* Progress Bar */
.progress-custom {
    height: 20px;
    border-radius: var(--border-radius-full);
    background: var(--gray-200);
    overflow: hidden;
    margin: 1rem 0;
}

.progress-custom-bar {
    height: 100%;
    background: var(--success-gradient);
    border-radius: var(--border-radius-full);
    transition: width 1s ease;
    position: relative;
    overflow: hidden;
}

.progress-custom-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, 
        transparent, 
        rgba(255,255,255,0.3), 
        transparent
    );
    animation: shimmer 2s infinite;
}

/* Quick Actions Card */
.quick-actions-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    height: 100%;
}

.quick-actions-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
}

.quick-actions-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.quick-actions-header h5 i {
    color: var(--primary);
}

.quick-actions-body {
    padding: 1.5rem;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--border-radius-lg);
    transition: var(--transition);
    margin-bottom: 0.5rem;
    border: 1px solid var(--gray-200);
    background: white;
    width: 100%;
    text-align: left;
    cursor: pointer;
}

.quick-action-btn:hover {
    background: var(--gray-100);
    transform: translateX(5px);
    border-color: var(--primary);
}

.quick-action-btn i {
    width: 32px;
    height: 32px;
    border-radius: var(--border-radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
}

.quick-action-btn .content {
    flex: 1;
}

.quick-action-btn .title {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.quick-action-btn .subtitle {
    font-size: 0.8rem;
    color: var(--gray-600);
}

/* Maintenance Mode Switch */
.maintenance-card {
    background: linear-gradient(135deg, var(--gray-100), white);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    margin-top: 1.5rem;
    border: 1px solid var(--gray-200);
}

.maintenance-card h6 {
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.maintenance-card .form-switch {
    padding-left: 2.5em;
    margin-bottom: 0.5rem;
}

.maintenance-card .form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
    cursor: pointer;
}

.maintenance-card .form-switch .form-check-input:checked {
    background-color: var(--success);
    border-color: var(--success);
}

/* Recent Changes Table */
.recent-changes-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    margin-top: 2rem;
}

.recent-changes-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-changes-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.recent-changes-header h5 i {
    color: var(--primary);
}

.changes-table {
    width: 100%;
    border-collapse: collapse;
}

.changes-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    background: var(--gray-100);
    border-bottom: 1px solid var(--gray-300);
}

.changes-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-600);
}

.changes-table tr:hover {
    background: var(--gray-100);
}

.changes-table .setting-key {
    font-family: 'Courier New', monospace;
    color: var(--primary);
    font-weight: 600;
}

.changes-table .value-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.changes-table .badge {
    padding: 0.35rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
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

.form-check-input {
    width: 1.2rem;
    height: 1.2rem;
    border: 2px solid var(--gray-300);
    transition: var(--transition);
}

.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-xl);
    border: 2px dashed var(--gray-300);
}

.empty-state i {
    font-size: 4rem;
    color: var(--gray-400);
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

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
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

/* Apply animations to elements */
.setting-card {
    animation: scaleIn 0.5s ease forwards;
    animation-delay: calc(var(--card-index) * 0.05s);
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .settings-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons-grid {
        padding: 1rem;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .changes-table {
        font-size: 0.85rem;
    }
    
    .changes-table th,
    .changes-table td {
        padding: 0.75rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
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
    gap: 0.75rem;
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

@keyframes spin {
    to { transform: rotate(360deg); }
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
                    <h1><i class="fas fa-cogs me-3"></i>System Settings</h1>
                    <p class="mb-0">Manage your website configuration, preferences, and system parameters</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group justify-content-md-end">
                        <button class="btn btn-outline-primary" onclick="exportSettings()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importSettingsModal">
                            <i class="fas fa-upload me-2"></i>Import
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Groups Grid -->
        <div class="settings-grid">
            <?php foreach($settings_groups as $index => $group): ?>
            <a href="settings-group.php?group=<?php echo $group['slug']; ?>" 
               class="setting-card" 
               style="--card-index: <?php echo $index; ?>;">
                <div class="card-header">
                    <div class="card-icon <?php 
                        $icons = ['primary', 'success', 'warning', 'danger', 'info'];
                        echo $icons[$index % count($icons)];
                    ?>">
                        <i class="<?php echo $group['icon']; ?>"></i>
                    </div>
                    <div>
                        <h5 class="card-title"><?php echo htmlspecialchars($group['name']); ?></h5>
                        <span class="card-subtitle"><?php echo $settings_counts[$group['slug']] ?? 0; ?> settings</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="settings-count">
                        <i class="fas fa-sliders-h"></i>
                        <?php echo $settings_counts[$group['slug']] ?? 0; ?> Configuration Options
                    </div>
                    <p class="card-description"><?php echo htmlspecialchars($group['description']); ?></p>
                </div>
                <div class="card-footer">
                    <span class="text-muted small">
                        <i class="fas fa-clock me-1"></i>
                        Last updated: <?php echo isset($group['updated_at']) ? date('M d, Y', strtotime($group['updated_at'])) : 'N/A'; ?>
                    </span>
                    <span class="manage-badge">
                        Manage <i class="fas fa-arrow-right ms-2"></i>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Quick Action Buttons -->
        <div class="action-buttons-grid">
            <a href="add-setting.php" class="action-btn action-btn-primary">
                <i class="fas fa-plus-circle"></i>
                Add Setting
            </a>
            <a href="api-settings.php" class="action-btn action-btn-info">
                <i class="fas fa-plug"></i>
                API Settings
            </a>
            <a href="backup-restore.php" class="action-btn action-btn-success">
                <i class="fas fa-database"></i>
                Backup & Restore
            </a>
            <a href="cache-management.php" class="action-btn action-btn-warning">
                <i class="fas fa-bolt"></i>
                Cache Management
            </a>
            <a href="countries.php" class="action-btn action-btn-outline">
                <i class="fas fa-globe"></i>
                Countries
            </a>
            <a href="custom-fields.php" class="action-btn action-btn-outline">
                <i class="fas fa-puzzle-piece"></i>
                Custom Fields
            </a>
            <a href="email-logs.php" class="action-btn action-btn-outline">
                <i class="fas fa-envelope-open-text"></i>
                Email Logs
            </a>
            <a href="email-templates.php" class="action-btn action-btn-outline">
                <i class="fas fa-file-alt"></i>
                Email Templates
            </a>
            <a href="import-export.php" class="action-btn action-btn-outline">
                <i class="fas fa-exchange-alt"></i>
                Import/Export
            </a>
            <a href="seo-tools.php" class="action-btn action-btn-outline">
                <i class="fas fa-chart-line"></i>
                SEO Tools
            </a>
            <a href="settings-group.php" class="action-btn action-btn-outline">
                <i class="fas fa-layer-group"></i>
                Settings Groups
            </a>
            <a href="settings-history.php" class="action-btn action-btn-outline">
                <i class="fas fa-history"></i>
                Settings History
            </a>
            <a href="system-logs.php" class="action-btn action-btn-outline">
                <i class="fas fa-clipboard-list"></i>
                System Logs
            </a>
            <a href="update-system.php" class="action-btn action-btn-outline">
                <i class="fas fa-sync-alt"></i>
                System Updates
            </a>
        </div>

        <!-- System Information and Quick Actions Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="system-info-card">
                    <div class="system-info-header">
                        <h5>
                            <i class="fas fa-microchip"></i>
                            System Information
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshSystemInfo()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="system-info-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="info-table">
                                    <tr>
                                        <td><i class="fas fa-code me-2 text-primary"></i>PHP Version:</td>
                                        <td><span class="badge bg-primary"><?php echo $system_info['php_version']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-server me-2 text-success"></i>Server:</td>
                                        <td><?php echo $system_info['server_software']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-database me-2 text-info"></i>MySQL:</td>
                                        <td><?php echo $system_info['mysql_version']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-upload me-2 text-warning"></i>Upload Max:</td>
                                        <td><?php echo $system_info['upload_max_filesize']; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="info-table">
                                    <tr>
                                        <td><i class="fas fa-hourglass-half me-2 text-danger"></i>Max Execution:</td>
                                        <td><?php echo $system_info['max_execution_time']; ?>s</td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-memory me-2 text-purple"></i>Memory Limit:</td>
                                        <td><?php echo $system_info['memory_limit']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-input-text me-2 text-pink"></i>Max Input Vars:</td>
                                        <td><?php echo $system_info['max_input_vars']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fas fa-file-upload me-2 text-orange"></i>Post Max Size:</td>
                                        <td><?php echo $system_info['post_max_size']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Disk Space Usage -->
                        <div class="mt-4">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-hdd me-2 text-primary"></i>
                                Disk Space Usage
                            </h6>
                            <?php
                            $total_space = disk_total_space('.');
                            $free_space = disk_free_space('.');
                            $used_space = $total_space - $free_space;
                            $used_percent = round(($used_space / $total_space) * 100, 2);
                            ?>
                            <div class="progress-custom">
                                <div class="progress-custom-bar" style="width: <?php echo $used_percent; ?>%;">
                                    <?php echo $used_percent; ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span><i class="fas fa-chart-pie me-1"></i>Used: <?php echo formatBytes($used_space); ?></span>
                                <span><i class="fas fa-chart-line me-1"></i>Free: <?php echo formatBytes($free_space); ?></span>
                                <span><i class="fas fa-chart-bar me-1"></i>Total: <?php echo formatBytes($total_space); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="quick-actions-card">
                    <div class="quick-actions-header">
                        <h5>
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="quick-actions-body">
                        <button class="quick-action-btn" onclick="clearCache()">
                            <i class="fas fa-broom" style="background: linear-gradient(135deg, #ffb703, #f77f00);"></i>
                            <div class="content">
                                <div class="title">Clear Cache</div>
                                <div class="subtitle">Remove all cached data</div>
                            </div>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </button>
                        
                        <button class="quick-action-btn" onclick="backupDatabase()">
                            <i class="fas fa-database" style="background: linear-gradient(135deg, #06d6a0, #0ca678);"></i>
                            <div class="content">
                                <div class="title">Backup Database</div>
                                <div class="subtitle">Create database backup</div>
                            </div>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </button>
                        
                        <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#environmentModal">
                            <i class="fas fa-code" style="background: linear-gradient(135deg, #4cc9f0, #0096c7);"></i>
                            <div class="content">
                                <div class="title">View Environment</div>
                                <div class="subtitle">Check environment variables</div>
                            </div>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </button>
                        
                        <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#phpInfoModal">
                            <i class="fas fa-info-circle" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);"></i>
                            <div class="content">
                                <div class="title">PHP Info</div>
                                <div class="subtitle">View PHP configuration</div>
                            </div>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </button>
                        
                        <button class="quick-action-btn" onclick="checkUpdates()">
                            <i class="fas fa-sync-alt" style="background: linear-gradient(135deg, #ef476f, #d62828);"></i>
                            <div class="content">
                                <div class="title">Check Updates</div>
                                <div class="subtitle">Look for system updates</div>
                            </div>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </button>
                        
                        <!-- Maintenance Mode -->
                        <div class="maintenance-card">
                            <h6>
                                <i class="fas fa-shield-alt text-warning"></i>
                                Maintenance Mode
                            </h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="maintenanceSwitch" 
                                       onchange="toggleMaintenanceMode(this.checked)">
                                <label class="form-check-label fw-normal" for="maintenanceSwitch">
                                    Enable Maintenance Mode
                                </label>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                When enabled, only admins can access the site
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Changes -->
        <div class="recent-changes-card">
            <div class="recent-changes-header">
                <h5>
                    <i class="fas fa-history"></i>
                    Recent Changes
                </h5>
                <a href="settings-history.php" class="btn btn-sm btn-primary">
                    View All <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            <div class="table-responsive">
                <?php if (empty($recent_changes)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h5>No Recent Changes</h5>
                    <p class="text-muted">Setting changes will appear here</p>
                </div>
                <?php else: ?>
                <table class="changes-table">
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                            <th>Changed By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_changes as $change): ?>
                        <tr>
                            <td>
                                <span class="setting-key"><?php echo htmlspecialchars($change['setting_key']); ?></span>
                            </td>
                            <td class="value-cell">
                                <span class="badge bg-light text-dark" 
                                      title="<?php echo htmlspecialchars($change['old_value'] ?? 'NULL'); ?>">
                                    <?php echo substr($change['old_value'] ?? 'NULL', 0, 30); ?>
                                    <?php echo strlen($change['old_value'] ?? '') > 30 ? '...' : ''; ?>
                                </span>
                            </td>
                            <td class="value-cell">
                                <span class="badge bg-success" 
                                      title="<?php echo htmlspecialchars($change['new_value'] ?? 'NULL'); ?>">
                                    <?php echo substr($change['new_value'] ?? 'NULL', 0, 30); ?>
                                    <?php echo strlen($change['new_value'] ?? '') > 30 ? '...' : ''; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info text-white">
                                    <i class="fas fa-user-circle me-1"></i>
                                    <?php echo htmlspecialchars($change['full_name'] ?? 'System'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted small">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('M d, H:i', strtotime($change['changed_at'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Import Settings Modal -->
<div class="modal fade" id="importSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-upload me-2"></i>
                    Import Settings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="importSettingsForm">
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-file me-2 text-primary"></i>
                            Select File
                        </label>
                        <input type="file" class="form-control" name="settings_file" accept=".json,.csv" required>
                        <div class="form-text">Supported formats: JSON, CSV</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-code-branch me-2 text-success"></i>
                            Import Mode
                        </label>
                        <select class="form-select" name="import_mode">
                            <option value="merge">📦 Merge (Keep existing, add new)</option>
                            <option value="replace">🔄 Replace (Overwrite all)</option>
                            <option value="update">⚡ Update (Only update existing)</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="create_backup" id="createBackup" checked>
                        <label class="form-check-label" for="createBackup">
                            <i class="fas fa-shield-alt me-1 text-warning"></i>
                            Create backup before importing
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="importSettings()">
                    <i class="fas fa-upload me-2"></i>Import Settings
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Environment Modal -->
<div class="modal fade" id="environmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-code me-2"></i>
                    Environment Variables
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="bg-light p-4 rounded">
                    <pre style="margin: 0; font-family: 'Courier New', monospace;"><code><?php
                    $env_vars = [
                        'SITE_URL' => SITE_URL,
                        'DB_HOST' => DB_HOST,
                        'DB_NAME' => DB_NAME,
                        'DB_USER' => DB_USER,
                        'TIMEZONE' => date_default_timezone_get(),
                        'PHP_VERSION' => PHP_VERSION,
                        'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'],
                    ];
                    echo json_encode($env_vars, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    ?></code></pre>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    These are your current environment settings. Keep them secure.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- PHP Info Modal -->
<div class="modal fade" id="phpInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fab fa-php me-2"></i>
                    PHP Information
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe src="phpinfo.php" width="100%" height="500px" style="border: none;"></iframe>
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
<script>
// Show loading spinner
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

// Export settings
function exportSettings() {
    Swal.fire({
        title: 'Export Settings',
        text: 'Choose export format',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '📄 JSON',
        denyButtonText: '📊 CSV',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3085d6',
        denyButtonColor: '#1cc88a',
        cancelButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open('export-settings.php?format=json', '_blank');
            showToast('success', 'Settings exported as JSON');
        } else if (result.isDenied) {
            window.open('export-settings.php?format=csv', '_blank');
            showToast('success', 'Settings exported as CSV');
        }
    });
}

// Import settings
function importSettings() {
    const form = document.getElementById('importSettingsForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Import Settings',
        text: 'This will modify your system settings. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, import'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/import-settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', data.message);
                    $('#importSettingsModal').modal('hide');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'An error occurred during import.');
            });
        }
    });
}

// Clear cache
function clearCache() {
    Swal.fire({
        title: 'Clear Cache',
        text: 'This will remove all cached data. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Clear Cache'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/clear-cache.php')
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', data.message);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'An error occurred.');
            });
        }
    });
}

// Backup database
function backupDatabase() {
    Swal.fire({
        title: 'Backup Database',
        text: 'Create a backup of the database?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Create Backup'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/backup-database.php')
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        title: 'Backup Created!',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <p>${data.message}</p>
                                <p class="small text-muted">File: ${data.filename}</p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Download',
                        cancelButtonText: 'Close',
                        confirmButtonColor: '#3085d6'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open(`backup-download.php?file=${data.filename}`, '_blank');
                        }
                    });
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'An error occurred.');
            });
        }
    });
}

// Refresh system info
function refreshSystemInfo() {
    showLoading();
    setTimeout(() => location.reload(), 1000);
}

// Toggle maintenance mode
function toggleMaintenanceMode(enabled) {
    fetch('ajax/toggle-maintenance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ enabled: enabled })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const status = enabled ? 'enabled' : 'disabled';
            showToast('success', `Maintenance mode ${status} successfully`);
        } else {
            showToast('error', data.message);
            document.getElementById('maintenanceSwitch').checked = !enabled;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred.');
        document.getElementById('maintenanceSwitch').checked = !enabled;
    });
}

// Check for updates
function checkUpdates() {
    Swal.fire({
        title: 'Checking for Updates',
        html: '<div class="spinner-border text-primary" role="status"></div>',
        showConfirmButton: false,
        allowOutsideClick: false
    });
    
    fetch('ajax/check-updates.php')
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            if (data.update_available) {
                Swal.fire({
                    title: 'Update Available!',
                    html: `
                        <div class="text-start">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Current Version:</strong> ${data.current_version}<br>
                                <strong>Latest Version:</strong> ${data.latest_version}
                            </div>
                            <p><strong>Release Notes:</strong></p>
                            <div class="bg-light p-3 rounded small">${data.release_notes}</div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Update Now',
                    cancelButtonText: 'Later',
                    confirmButtonColor: '#1cc88a'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'update-system.php';
                    }
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Up to Date',
                    text: 'Your system is running the latest version.',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to check for updates.', 'error');
    });
}

// Initialize maintenance switch
document.addEventListener('DOMContentLoaded', function() {
    fetch('ajax/get-maintenance-status.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('maintenanceSwitch').checked = data.maintenance_mode;
        }
    })
    .catch(error => {
        console.error('Error loading maintenance status:', error);
    });
    
    // Add animation delays to cards
    document.querySelectorAll('.setting-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
    });
});

// Format bytes helper
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}
</script>

<?php 
// Helper function to format bytes
function formatBytes($bytes, $decimals = 2) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
    
    $i = floor(log($bytes) / log($k));
    
    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}

require_once '../includes/footer.php'; 
?>