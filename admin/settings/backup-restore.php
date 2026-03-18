<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'Database Backup & Restore';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get backup schedules
    $stmt = $db->query("SELECT * FROM backup_schedules ORDER BY id");
    $schedules = $stmt->fetchAll();
    
    // Get recent backups
    $backup_dir = '../backups/';
    $backups = [];
    
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && (strpos($file, '.sql') !== false || strpos($file, '.zip') !== false)) {
                $filepath = $backup_dir . $file;
                $backups[] = [
                    'name' => $file,
                    'size' => filesize($filepath),
                    'modified' => filemtime($filepath),
                    'type' => strpos($file, '.zip') !== false ? 'full' : 'database'
                ];
            }
        }
        
        // Sort by modified time (newest first)
        usort($backups, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
    }
    
    // Get database info
    $stmt = $db->query("SELECT 
        COUNT(*) as total_tables,
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()");
    $db_info = $stmt->fetch();
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $schedules = [];
    $backups = [];
    $db_info = ['total_tables' => 0, 'size_mb' => 0];
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
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }

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
    transform: scale(1.2) rotate(10deg);
}

.stat-card .stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.stat-card .stat-label {
    color: var(--gray-600);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .stat-footer {
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px dashed var(--gray-200);
    font-size: 0.85rem;
    color: var(--gray-500);
}

/* Quick Backup Cards */
.quick-backup-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 2rem;
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.quick-backup-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary);
}

.quick-backup-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary-gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.quick-backup-card:hover::before {
    transform: scaleX(1);
}

.quick-backup-card .card-icon {
    width: 80px;
    height: 80px;
    border-radius: var(--border-radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: white;
    transition: var(--transition-bounce);
}

.quick-backup-card:hover .card-icon {
    transform: scale(1.1) rotate(360deg);
}

.quick-backup-card .card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.5rem;
}

.quick-backup-card .card-text {
    color: var(--gray-600);
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}

.quick-backup-card .card-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.7rem;
    font-weight: 600;
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
}

/* Backups Card */
.backups-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.backups-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.backups-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.backups-card .card-header h5 i {
    color: var(--info);
}

.backups-card .card-body {
    padding: 0;
}

/* Backup Items */
.backup-item {
    display: flex;
    align-items: center;
    padding: 1rem 2rem;
    border-bottom: 1px solid var(--gray-200);
    transition: var(--transition);
    animation: slideIn 0.3s ease;
    animation-fill-mode: both;
}

.backup-item:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
}

.backup-item:last-child {
    border-bottom: none;
}

.backup-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-right: 1.5rem;
    flex-shrink: 0;
}

.backup-icon.database {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.backup-icon.full {
    background: linear-gradient(135deg, var(--success), var(--success-dark));
}

.backup-info {
    flex: 1;
}

.backup-name {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.backup-name .badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.7rem;
    font-weight: 600;
}

.backup-meta {
    display: flex;
    gap: 1.5rem;
    font-size: 0.85rem;
    color: var(--gray-600);
}

.backup-meta i {
    margin-right: 0.25rem;
    color: var(--primary);
}

.backup-actions {
    display: flex;
    gap: 0.5rem;
}

/* Schedule Card */
.schedule-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    animation: slideIn 0.5s ease;
}

.schedule-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.schedule-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.schedule-card .card-header h5 i {
    color: var(--warning);
}

.schedule-card .card-body {
    padding: 0;
}

/* Schedule Items */
.schedule-item {
    display: flex;
    align-items: center;
    padding: 1rem 2rem;
    border-bottom: 1px solid var(--gray-200);
    transition: var(--transition);
}

.schedule-item:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
}

.schedule-item:last-child {
    border-bottom: none;
}

.schedule-info {
    flex: 1;
}

.schedule-title {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.schedule-title .badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.7rem;
    font-weight: 600;
}

.schedule-details {
    display: flex;
    gap: 2rem;
    font-size: 0.85rem;
    color: var(--gray-600);
}

.schedule-details span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.schedule-details i {
    color: var(--primary);
    width: 16px;
}

.schedule-status {
    margin: 0 1.5rem;
}

.schedule-actions {
    display: flex;
    gap: 0.5rem;
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

.btn-success {
    background: var(--success-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(6, 214, 160, 0.3);
}

.btn-success:hover {
    box-shadow: 0 6px 15px rgba(6, 214, 160, 0.4);
    transform: translateY(-2px);
}

.btn-info {
    background: var(--info-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(76, 201, 240, 0.3);
}

.btn-info:hover {
    box-shadow: 0 6px 15px rgba(76, 201, 240, 0.4);
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

.btn-outline-success {
    background: transparent;
    border: 2px solid var(--success);
    color: var(--success);
}

.btn-outline-success:hover {
    background: var(--success-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(6, 214, 160, 0.3);
}

.btn-outline-danger {
    background: transparent;
    border: 2px solid var(--danger);
    color: var(--danger);
}

.btn-outline-danger:hover {
    background: var(--danger-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(239, 71, 111, 0.3);
}

.btn-outline-warning {
    background: transparent;
    border: 2px solid var(--warning);
    color: var(--warning);
}

.btn-outline-warning:hover {
    background: var(--warning-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(255, 183, 3, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.btn-icon {
    padding: 0.5rem;
    border-radius: var(--border-radius-md);
}

.btn-icon i {
    margin: 0;
    font-size: 1rem;
}

/* Form Switch */
.form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
    cursor: pointer;
}

.form-switch .form-check-input:checked {
    background-color: var(--success);
    border-color: var(--success);
}

/* Badge Styles */
.badge {
    padding: 0.35rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-weight: 600;
    font-size: 0.75rem;
}

.badge-database {
    background: rgba(67, 97, 238, 0.15);
    color: var(--primary);
}

.badge-full {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
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

@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
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
    
    .stat-card .stat-value {
        font-size: 2rem;
    }
    
    .backup-item,
    .schedule-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .backup-actions,
    .schedule-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .schedule-details {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .modal-body {
        padding: 1rem;
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
                    <h1><i class="fas fa-database me-3"></i>Backup & Restore</h1>
                    <p class="mb-0">Manage database backups, create new backups, and restore data</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group justify-content-md-end">
                        <button class="btn btn-primary" onclick="createBackup()">
                            <i class="fas fa-plus-circle me-2"></i>Create New Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Database Information Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($db_info['size_mb'] ?? 0, 2); ?> MB</div>
                    <div class="stat-label">Database Size</div>
                    <div class="stat-footer">
                        <i class="fas fa-hdd me-1"></i> Total storage used
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                        <i class="fas fa-table"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($db_info['total_tables'] ?? 0); ?></div>
                    <div class="stat-label">Total Tables</div>
                    <div class="stat-footer">
                        <i class="fas fa-layer-group me-1"></i> Database tables
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format(count($backups)); ?></div>
                    <div class="stat-label">Total Backups</div>
                    <div class="stat-footer">
                        <i class="fas fa-archive me-1"></i> Available backup files
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Backup Options -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="quick-backup-card" onclick="quickBackup('database')">
                    <div class="card-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                        <i class="fas fa-database"></i>
                    </div>
                    <h5 class="card-title">Database Only</h5>
                    <p class="card-text">Backup only database structure and data</p>
                    <span class="card-badge">Quick</span>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="quick-backup-card" onclick="quickBackup('files')">
                    <div class="card-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                        <i class="fas fa-folder"></i>
                    </div>
                    <h5 class="card-title">Files Only</h5>
                    <p class="card-text">Backup only application files and uploads</p>
                    <span class="card-badge">Quick</span>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="quick-backup-card" onclick="quickBackup('full')">
                    <div class="card-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                        <i class="fas fa-archive"></i>
                    </div>
                    <h5 class="card-title">Full Backup</h5>
                    <p class="card-text">Complete backup of database and files</p>
                    <span class="card-badge">Recommended</span>
                </div>
            </div>
        </div>
        
        <!-- Recent Backups -->
        <div class="backups-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-history"></i>
                    Recent Backups
                    <span class="badge bg-info ms-2"><?php echo count($backups); ?> Files</span>
                </h5>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshBackups()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($backups)): ?>
                <div class="empty-state">
                    <i class="fas fa-database"></i>
                    <h5>No Backups Found</h5>
                    <p class="text-muted">Create your first backup to protect your data</p>
                    <button class="btn btn-primary" onclick="createBackup()">
                        <i class="fas fa-plus-circle me-2"></i>Create Backup
                    </button>
                </div>
                <?php else: ?>
                    <?php foreach(array_slice($backups, 0, 5) as $backup): 
                        $size = formatBytes($backup['size']);
                        $date = date('M d, Y H:i', $backup['modified']);
                        $type_class = $backup['type'] == 'full' ? 'full' : 'database';
                        $type_icon = $backup['type'] == 'full' ? 'fa-archive' : 'fa-database';
                    ?>
                    <div class="backup-item" style="animation-delay: <?php echo $loop_index ?? 0 * 0.05; ?>s">
                        <div class="backup-icon <?php echo $type_class; ?>">
                            <i class="fas <?php echo $type_icon; ?>"></i>
                        </div>
                        <div class="backup-info">
                            <div class="backup-name">
                                <span><?php echo $backup['name']; ?></span>
                                <span class="badge badge-<?php echo $type_class; ?>">
                                    <?php echo ucfirst($backup['type']); ?>
                                </span>
                            </div>
                            <div class="backup-meta">
                                <span><i class="fas fa-file"></i> <?php echo $size; ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo $date; ?></span>
                            </div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-icon btn-outline-primary" 
                                    onclick="downloadBackup('<?php echo $backup['name']; ?>')"
                                    title="Download Backup">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-icon btn-outline-success" 
                                    onclick="restoreBackup('<?php echo $backup['name']; ?>')"
                                    title="Restore Backup">
                                <i class="fas fa-undo-alt"></i>
                            </button>
                            <button class="btn btn-icon btn-outline-danger" 
                                    onclick="deleteBackup('<?php echo $backup['name']; ?>')"
                                    title="Delete Backup">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($backups) > 5): ?>
                    <div class="text-center p-3 border-top">
                        <a href="#" class="btn btn-link text-primary">
                            View All Backups <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Backup Schedules -->
        <div class="schedule-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-clock"></i>
                    Backup Schedules
                </h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="fas fa-plus-circle me-2"></i>Add Schedule
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($schedules)): ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <h5>No Schedules</h5>
                    <p class="text-muted">Create automated backup schedules for regular backups</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Schedule
                    </button>
                </div>
                <?php else: ?>
                    <?php foreach($schedules as $schedule): 
                        $schedule_types = [
                            'daily' => ['icon' => 'fa-calendar-day', 'color' => 'primary'],
                            'weekly' => ['icon' => 'fa-calendar-week', 'color' => 'success'],
                            'monthly' => ['icon' => 'fa-calendar-alt', 'color' => 'warning']
                        ];
                        $type = $schedule_types[$schedule['schedule_type']] ?? ['icon' => 'fa-clock', 'color' => 'info'];
                    ?>
                    <div class="schedule-item">
                        <div class="schedule-info">
                            <div class="schedule-title">
                                <span>
                                    <i class="fas <?php echo $type['icon']; ?> me-2 text-<?php echo $type['color']; ?>"></i>
                                    <?php echo ucfirst($schedule['schedule_type']); ?> Backup
                                </span>
                                <span class="badge badge-<?php echo $schedule['backup_type']; ?>">
                                    <?php echo ucfirst($schedule['backup_type']); ?>
                                </span>
                            </div>
                            <div class="schedule-details">
                                <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($schedule['time'])); ?></span>
                                <span><i class="fas fa-calendar"></i> Keep <?php echo $schedule['keep_backups']; ?> days</span>
                                <span><i class="fas fa-play-circle"></i> Last: <?php echo $schedule['last_run'] ? date('M d, H:i', strtotime($schedule['last_run'])) : 'Never'; ?></span>
                                <span><i class="fas fa-hourglass-half"></i> Next: <?php echo $schedule['next_run'] ? date('M d, H:i', strtotime($schedule['next_run'])) : 'Not scheduled'; ?></span>
                            </div>
                        </div>
                        <div class="schedule-status">
                            <div class="form-check form-switch">
                                <input class="form-check-input schedule-toggle" 
                                       type="checkbox" 
                                       data-id="<?php echo $schedule['id']; ?>"
                                       <?php echo $schedule['is_active'] ? 'checked' : ''; ?>
                                       onchange="toggleSchedule(this, <?php echo $schedule['id']; ?>)">
                            </div>
                        </div>
                        <div class="schedule-actions">
                            <button class="btn btn-icon btn-outline-primary" 
                                    onclick="runSchedule(<?php echo $schedule['id']; ?>)"
                                    title="Run Now">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-icon btn-outline-warning" 
                                    onclick="editSchedule(<?php echo $schedule['id']; ?>)"
                                    title="Edit Schedule">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-outline-danger" 
                                    onclick="deleteSchedule(<?php echo $schedule['id']; ?>)"
                                    title="Delete Schedule">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add Backup Schedule
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addScheduleForm">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-clock text-primary"></i>
                            Schedule Type
                        </label>
                        <select class="form-select" name="schedule_type" required>
                            <option value="daily"> Daily - Once every day</option>
                            <option value="weekly"> Weekly - Once every week</option>
                            <option value="monthly"> Monthly - Once every month</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-database text-success"></i>
                            Backup Type
                        </label>
                        <select class="form-select" name="backup_type" required>
                            <option value="database"> Database Only</option>
                            <option value="files"> Files Only</option>
                            <option value="full"> Full Backup (Database + Files)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-hourglass-start text-info"></i>
                            Time
                        </label>
                        <input type="time" class="form-control" name="time" value="02:00" required>
                        <div class="form-text">When should the backup run?</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-calendar-times text-warning"></i>
                            Keep Backups For (days)
                        </label>
                        <input type="number" class="form-control" name="keep_backups" value="30" min="1" max="365" required>
                        <div class="form-text">How long to keep backup files</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveSchedule()">
                    <i class="fas fa-save me-2"></i>Save Schedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Restore Backup Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Restore Backup
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="restoreContent">
                    <!-- Content loaded via AJAX -->
                </div>
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
let currentBackupFile = null;

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

// Create backup
function createBackup() {
    Swal.fire({
        title: 'Create New Backup',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">Backup Type</label>
                    <select class="form-select" id="backupType">
                        <option value="database"> Database Only</option>
                        <option value="files">📁 Files Only</option>
                        <option value="full"> Full Backup (Database + Files)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Backup Name (Optional)</label>
                    <input type="text" class="form-control" id="backupName" 
                           value="backup_${new Date().toISOString().slice(0,10)}_${new Date().getHours()}${new Date().getMinutes()}">
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="compressBackup" checked>
                    <label class="form-check-label">Compress backup (ZIP)</label>
                </div>
                <div class="form-text mt-2">
                    <i class="fas fa-info-circle text-info me-1"></i>
                    Compressed backups take less space and download faster
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Create Backup',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            return {
                type: document.getElementById('backupType').value,
                name: document.getElementById('backupName').value,
                compress: document.getElementById('compressBackup').checked
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { type, name, compress } = result.value;
            
            Swal.fire({
                title: 'Creating Backup...',
                html: '<div class="spinner-border text-primary" role="status"></div><p class="mt-3">Please wait while backup is being created...</p>',
                allowOutsideClick: false,
                showConfirmButton: false
            });
            
            fetch('ajax/create-backup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, name, compress })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Backup Created!',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <p>${data.message}</p>
                                <div class="alert alert-info text-start mt-3">
                                    <p class="mb-1"><strong>File:</strong> ${data.filename}</p>
                                    <p class="mb-0"><strong>Size:</strong> ${data.size}</p>
                                </div>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Download',
                        cancelButtonText: 'Close',
                        confirmButtonColor: '#1cc88a'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open(`download-backup.php?file=${data.filename}`, '_blank');
                        }
                        setTimeout(() => location.reload(), 1000);
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred during backup.', 'error');
            });
        }
    });
}

// Quick backup
function quickBackup(type) {
    const typeNames = {
        'database': 'Database',
        'files': 'Files',
        'full': 'Full'
    };
    
    Swal.fire({
        title: `Quick ${typeNames[type]} Backup`,
        text: `Create a quick ${typeNames[type].toLowerCase()} backup?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, create backup',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/create-backup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, quick: true })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', 'Backup created successfully');
                    setTimeout(() => location.reload(), 1500);
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
    });
}

// Download backup
function downloadBackup(filename) {
    window.open(`download-backup.php?file=${filename}`, '_blank');
    showToast('success', 'Download started');
}

// Restore backup
function restoreBackup(filename) {
    currentBackupFile = filename;
    
    Swal.fire({
        title: 'Restore Backup',
        html: `
            <div class="text-start">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Restoring will overwrite current data. Proceed with caution!
                </div>
                <p>Restore from <strong>${filename}</strong>?</p>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="createBackupBefore" checked>
                    <label class="form-check-label">Create backup before restore</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="verifyOnly">
                    <label class="form-check-label">Verify only (dry run - no changes)</label>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Restore',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            return {
                createBackup: document.getElementById('createBackupBefore').checked,
                verifyOnly: document.getElementById('verifyOnly').checked
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { createBackup, verifyOnly } = result.value;
            
            showLoading();
            
            fetch('ajax/restore-backup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ filename, create_backup: createBackup, verify_only: verifyOnly })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Restore Complete!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        if (!verifyOnly) location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred during restore.', 'error');
            });
        }
    });
}

// Delete backup
function deleteBackup(filename) {
    Swal.fire({
        title: 'Delete Backup',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Delete backup file <strong>${filename}</strong>?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/delete-backup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ filename })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', 'Backup deleted successfully');
                    setTimeout(() => location.reload(), 1500);
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
    });
}

// Toggle schedule
function toggleSchedule(checkbox, scheduleId) {
    const isActive = checkbox.checked ? 1 : 0;
    
    fetch('ajax/toggle-schedule.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ schedule_id: scheduleId, is_active: isActive })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            checkbox.checked = !checkbox.checked;
            showToast('error', data.message);
        } else {
            showToast('success', 'Schedule updated');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        checkbox.checked = !checkbox.checked;
        showToast('error', 'An error occurred');
    });
}

// Save schedule
function saveSchedule() {
    const form = document.getElementById('addScheduleForm');
    const formData = new FormData(form);
    
    showLoading();
    
    fetch('ajax/save-schedule.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('success', 'Schedule saved successfully');
            $('#addScheduleModal').modal('hide');
            setTimeout(() => location.reload(), 1500);
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

// Run schedule
function runSchedule(scheduleId) {
    Swal.fire({
        title: 'Run Schedule Now',
        text: 'Execute this backup schedule immediately?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, run now',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/run-schedule.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ schedule_id: scheduleId })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', 'Schedule executed successfully');
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
    });
}

// Edit schedule
function editSchedule(scheduleId) {
    fetch(`ajax/get-schedule.php?id=${scheduleId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const schedule = data.schedule;
            
            Swal.fire({
                title: 'Edit Schedule',
                html: `
                    <div class="text-start">
                        <div class="mb-3">
                            <label class="form-label">Schedule Type</label>
                            <select class="form-select" id="editScheduleType">
                                <option value="daily" ${schedule.schedule_type === 'daily' ? 'selected' : ''}>Daily</option>
                                <option value="weekly" ${schedule.schedule_type === 'weekly' ? 'selected' : ''}>Weekly</option>
                                <option value="monthly" ${schedule.schedule_type === 'monthly' ? 'selected' : ''}>Monthly</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Backup Type</label>
                            <select class="form-select" id="editBackupType">
                                <option value="database" ${schedule.backup_type === 'database' ? 'selected' : ''}>Database</option>
                                <option value="files" ${schedule.backup_type === 'files' ? 'selected' : ''}>Files</option>
                                <option value="full" ${schedule.backup_type === 'full' ? 'selected' : ''}>Full</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" id="editTime" value="${schedule.time.slice(0,5)}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keep Backups (days)</label>
                            <input type="number" class="form-control" id="editKeepBackups" value="${schedule.keep_backups}" min="1" max="365">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="editIsActive" ${schedule.is_active ? 'checked' : ''}>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Save Changes',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    return {
                        schedule_type: document.getElementById('editScheduleType').value,
                        backup_type: document.getElementById('editBackupType').value,
                        time: document.getElementById('editTime').value,
                        keep_backups: document.getElementById('editKeepBackups').value,
                        is_active: document.getElementById('editIsActive').checked
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = result.value;
                    formData.schedule_id = scheduleId;
                    
                    showLoading();
                    
                    fetch('ajax/update-schedule.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showToast('success', 'Schedule updated successfully');
                            setTimeout(() => location.reload(), 1500);
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
            });
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Failed to load schedule');
    });
}

// Delete schedule
function deleteSchedule(scheduleId) {
    Swal.fire({
        title: 'Delete Schedule',
        text: 'Are you sure you want to delete this schedule?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/delete-schedule.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ schedule_id: scheduleId })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', 'Schedule deleted successfully');
                    setTimeout(() => location.reload(), 1500);
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
    });
}

// Refresh backups
function refreshBackups() {
    showLoading();
    setTimeout(() => location.reload(), 500);
}
</script>

<?php 
function formatBytes($bytes, $decimals = 2) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}
require_once '../includes/footer.php'; 
?>