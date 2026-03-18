<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'Import/Export Settings';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get all settings groups
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $groups = $stmt->fetchAll();
    
    // Get recent imports/exports
    $stmt = $db->query("SELECT * FROM import_export_logs ORDER BY created_at DESC LIMIT 10");
    $history = $stmt->fetchAll();
    
    // Get total settings count
    $stmt = $db->query("SELECT COUNT(*) as total FROM settings");
    $total_settings = $stmt->fetch()['total'];
    
    // Get settings by group
    $settings_by_group = [];
    foreach ($groups as $group) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE `group` = ?");
        $stmt->execute([$group['slug']]);
        $count = $stmt->fetch()['count'];
        $settings_by_group[$group['slug']] = $count;
    }
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $groups = [];
    $history = [];
    $total_settings = 0;
    $settings_by_group = [];
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
    width: 70px;
    height: 70px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
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

.stat-card .stat-footer {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Action Cards */
.action-card {
    background: white;
    border-radius: var(--border-radius-2xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    height: 100%;
    transition: var(--transition);
    animation: slideIn 0.5s ease;
    position: relative;
}

.action-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary);
}

.action-card.export {
    background: linear-gradient(135deg, white, rgba(67, 97, 238, 0.05));
}

.action-card.import {
    background: linear-gradient(135deg, white, rgba(6, 214, 160, 0.05));
}

.action-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.action-card .card-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.action-card .card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.action-card .card-subtitle {
    color: var(--gray-600);
    font-size: 0.85rem;
}

.action-card .card-body {
    padding: 2rem;
}

/* Form Elements */
.form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-label i {
    color: var(--primary);
    width: 20px;
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

/* Checkbox Group */
.checkbox-group {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    background: var(--gray-100);
}

.checkbox-item {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-bottom: 1px solid var(--gray-200);
}

.checkbox-item:last-child {
    border-bottom: none;
}

.checkbox-item:hover {
    background: rgba(67, 97, 238, 0.05);
    border-radius: var(--border-radius-md);
}

.checkbox-item .form-check-input {
    width: 1.2rem;
    height: 1.2rem;
    border: 2px solid var(--gray-300);
    margin-right: 0.75rem;
    cursor: pointer;
}

.checkbox-item .form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.checkbox-item .form-check-label {
    font-weight: 500;
    color: var(--gray-700);
    cursor: pointer;
}

.checkbox-item .badge {
    margin-left: auto;
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Settings Selector */
.settings-selector {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    max-height: 250px;
    overflow-y: auto;
    background: var(--gray-100);
}

.settings-selector .setting-item {
    padding: 0.5rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    transition: var(--transition);
}

.settings-selector .setting-item:hover {
    background: rgba(67, 97, 238, 0.05);
}

.settings-selector .setting-item:last-child {
    border-bottom: none;
}

/* Option Cards */
.option-card {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.option-card:hover {
    border-color: var(--primary);
    background: white;
}

/* History Card */
.history-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-top: 2rem;
    animation: slideIn 0.5s ease;
}

.history-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.history-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.history-card .card-header h5 i {
    color: var(--info);
}

.history-card .card-body {
    padding: 0;
}

/* History Table */
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
}

.history-table tbody tr:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
}

.history-table tbody tr:hover td {
    color: var(--gray-800);
}

/* Type Badge */
.type-badge {
    padding: 0.35rem 1rem;
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.type-badge.import {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.type-badge.export {
    background: rgba(67, 97, 238, 0.15);
    color: var(--primary);
    border: 1px solid rgba(67, 97, 238, 0.3);
}

/* Status Badge */
.status-badge {
    padding: 0.35rem 1rem;
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
}

.status-success {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.status-failed {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.status-pending {
    background: rgba(255, 183, 3, 0.15);
    color: var(--warning);
    border: 1px solid rgba(255, 183, 3, 0.3);
}

/* Action Button */
.btn-action {
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--border-radius-lg);
    padding: 1rem 2rem;
    font-weight: 600;
    font-size: 1rem;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    width: 100%;
    cursor: pointer;
}

.btn-action:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
}

.btn-action.import {
    background: var(--success-gradient);
}

.btn-action.import:hover {
    box-shadow: 0 8px 20px rgba(6, 214, 160, 0.4);
}

.btn-action i {
    font-size: 1.1rem;
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

/* Preview Table */
.preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.preview-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    background: var(--gray-100);
    border-bottom: 2px solid var(--gray-300);
}

.preview-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
}

.preview-table code {
    background: var(--gray-100);
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-sm);
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
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
        gap: 1rem;
    }
    
    .stat-card .stat-icon {
        margin: 0 auto;
    }
    
    .action-card .card-header {
        padding: 1rem;
    }
    
    .action-card .card-body {
        padding: 1rem;
    }
    
    .history-table th,
    .history-table td {
        padding: 0.75rem;
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
                    <h1>
                        <i class="fas fa-exchange-alt"></i>
                        Import/Export Settings
                    </h1>
                    <p class="mb-0">Backup, restore, and transfer system settings</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $total_settings; ?></div>
                        <div class="stat-label">Total Settings</div>
                        <div class="stat-footer">
                            <i class="fas fa-layer-group me-1"></i> System configurations
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($groups); ?></div>
                        <div class="stat-label">Settings Groups</div>
                        <div class="stat-footer">
                            <i class="fas fa-tags me-1"></i> <?php echo count($groups); ?> categories
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?php 
                            $public_count = 0;
                            try {
                                $stmt = $db->query("SELECT COUNT(*) as count FROM settings WHERE is_public = 1");
                                $public_count = $stmt->fetch()['count'];
                            } catch(Exception $e) {}
                            echo $public_count;
                            ?>
                        </div>
                        <div class="stat-label">Public Settings</div>
                        <div class="stat-footer">
                            <i class="fas fa-eye me-1"></i> Visible to all users
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?php 
                            $recent_count = 0;
                            try {
                                $stmt = $db->query("SELECT COUNT(*) as count FROM import_export_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                                $recent_count = $stmt->fetch()['count'];
                            } catch(Exception $e) {}
                            echo $recent_count;
                            ?>
                        </div>
                        <div class="stat-label">Recent Activities</div>
                        <div class="stat-footer">
                            <i class="fas fa-clock me-1"></i> Last 7 days
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Export & Import Cards -->
        <div class="row g-4 mb-4">
            <!-- Export Card -->
            <div class="col-md-6">
                <div class="action-card export">
                    <div class="card-header">
                        <div class="card-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                            <i class="fas fa-download"></i>
                        </div>
                        <div>
                            <div class="card-title">Export Settings</div>
                            <div class="card-subtitle">Backup your system configuration</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="exportForm">
                            <!-- Format Selection -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-file-code"></i>
                                    Export Format
                                </label>
                                <select class="form-select" name="format">
                                    <option value="json">📄 JSON (Recommended)</option>
                                    <option value="csv">📊 CSV</option>
                                    <option value="xml">📋 XML</option>
                                    <option value="php">🐘 PHP Array</option>
                                </select>
                            </div>
                            
                            <!-- Scope Selection -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-bullseye"></i>
                                    Export Scope
                                </label>
                                <select class="form-select" name="scope" id="exportScope" onchange="toggleExportOptions()">
                                    <option value="all">🌐 All Settings</option>
                                    <option value="group">📁 Specific Group</option>
                                    <option value="selected">✓ Selected Settings</option>
                                </select>
                            </div>
                            
                            <!-- Group Select -->
                            <div class="mb-4" id="groupSelect" style="display: none;">
                                <label class="form-label">
                                    <i class="fas fa-folder"></i>
                                    Select Group
                                </label>
                                <select class="form-select" name="group">
                                    <?php foreach($groups as $group): ?>
                                    <option value="<?php echo $group['slug']; ?>">
                                        <?php echo $group['name']; ?> (<?php echo $settings_by_group[$group['slug']] ?? 0; ?> settings)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Settings Select -->
                            <div class="mb-4" id="settingsSelect" style="display: none;">
                                <label class="form-label">
                                    <i class="fas fa-check-double"></i>
                                    Select Settings
                                </label>
                                <div class="settings-selector">
                                    <?php 
                                    try {
                                        $stmt = $db->query("SELECT setting_key, `group` FROM settings ORDER BY `group`, setting_key");
                                        $all_settings = $stmt->fetchAll();
                                        
                                        $current_group = '';
                                        foreach($all_settings as $setting):
                                            if ($current_group != $setting['group']) {
                                                $current_group = $setting['group'];
                                                echo '<div class="setting-item bg-light fw-bold">' . ucfirst($current_group) . '</div>';
                                            }
                                    ?>
                                    <div class="setting-item">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="settings[]" value="<?php echo $setting['setting_key']; ?>"
                                                   id="setting_<?php echo $setting['setting_key']; ?>">
                                            <label class="form-check-label" for="setting_<?php echo $setting['setting_key']; ?>">
                                                <code><?php echo $setting['setting_key']; ?></code>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; } catch(Exception $e) {} ?>
                                </div>
                            </div>
                            
                            <!-- Export Options -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-sliders-h"></i>
                                    Export Options
                                </label>
                                <div class="option-card">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="include_metadata" id="includeMetadata" checked>
                                        <label class="form-check-label" for="includeMetadata">
                                            Include metadata (type, validation, help text)
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="include_values" id="includeValues" checked>
                                        <label class="form-check-label" for="includeValues">
                                            Include current setting values
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="compress" id="compressExport">
                                        <label class="form-check-label" for="compressExport">
                                            Compress as ZIP file
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Export Button -->
                            <button type="button" class="btn-action" onclick="exportSettings()">
                                <i class="fas fa-download"></i>
                                Export Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Import Card -->
            <div class="col-md-6">
                <div class="action-card import">
                    <div class="card-header">
                        <div class="card-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                            <i class="fas fa-upload"></i>
                        </div>
                        <div>
                            <div class="card-title">Import Settings</div>
                            <div class="card-subtitle">Restore settings from backup</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="importForm" enctype="multipart/form-data">
                            <!-- File Upload -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-file-upload"></i>
                                    Import File
                                </label>
                                <input type="file" class="form-control" name="import_file" 
                                       accept=".json,.csv,.xml,.zip" required>
                                <div class="form-text">
                                    Supported formats: JSON, CSV, XML, ZIP
                                </div>
                            </div>
                            
                            <!-- Import Mode -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-code-branch"></i>
                                    Import Mode
                                </label>
                                <select class="form-select" name="import_mode">
                                    <option value="merge">🔄 Merge (Keep existing, add new)</option>
                                    <option value="replace">⚡ Replace (Overwrite all)</option>
                                    <option value="update">📝 Update (Only update existing)</option>
                                    <option value="skip">⏭️ Skip (Only add new)</option>
                                </select>
                                <div class="form-text">
                                    Choose how to handle existing settings
                                </div>
                            </div>
                            
                            <!-- Conflict Resolution -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Conflict Resolution
                                </label>
                                <select class="form-select" name="conflict_resolution">
                                    <option value="skip">⏭️ Skip conflicting settings</option>
                                    <option value="overwrite">📝 Overwrite conflicting settings</option>
                                    <option value="rename">✏️ Rename conflicting settings</option>
                                </select>
                            </div>
                            
                            <!-- Import Options -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-sliders-h"></i>
                                    Import Options
                                </label>
                                <div class="option-card">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="create_backup" id="createBackup" checked>
                                        <label class="form-check-label" for="createBackup">
                                            Create backup before import
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="dry_run" id="dryRun">
                                        <label class="form-check-label" for="dryRun">
                                            Dry run (preview without changes)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="preserve_ids" id="preserveIds">
                                        <label class="form-check-label" for="preserveIds">
                                            Preserve setting IDs (if available)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Import Button -->
                            <button type="button" class="btn-action import" onclick="importSettings()">
                                <i class="fas fa-upload"></i>
                                Import Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="history-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-history"></i>
                    Recent Import/Export Activities
                </h5>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshHistory()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h5>No Recent Activities</h5>
                    <p class="text-muted">Your import/export history will appear here</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>File</th>
                                <th>Settings</th>
                                <th>Mode</th>
                                <th>User</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $record): ?>
                            <tr>
                                <td>
                                    <span class="type-badge <?php echo $record['type']; ?>">
                                        <i class="fas fa-<?php echo $record['type'] == 'import' ? 'download' : 'upload'; ?>"></i>
                                        <?php echo ucfirst($record['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-file me-1 text-muted"></i>
                                        <?php echo $record['filename']; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo $record['settings_count'] ?? '0'; ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($record['import_mode'])): ?>
                                    <span class="badge bg-secondary"><?php echo $record['import_mode']; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    try {
                                        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                                        $stmt->execute([$record['user_id']]);
                                        $user = $stmt->fetch();
                                        echo $user ? 
                                            '<span class="badge bg-info"><i class="fas fa-user me-1"></i>' . $user['username'] . '</span>' : 
                                            '<span class="badge bg-secondary">System</span>';
                                    } catch(Exception $e) {
                                        echo '<span class="badge bg-secondary">System</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('M d, H:i', strtotime($record['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($record['status'] == 'success'): ?>
                                    <span class="status-badge status-success">
                                        <i class="fas fa-check-circle me-1"></i> Success
                                    </span>
                                    <?php elseif ($record['status'] == 'failed'): ?>
                                    <span class="status-badge status-failed">
                                        <i class="fas fa-times-circle me-1"></i> Failed
                                    </span>
                                    <?php else: ?>
                                    <span class="status-badge status-pending">
                                        <i class="fas fa-clock me-1"></i> Pending
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Import Preview Modal -->
<div class="modal fade" id="importPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>
                    Import Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="importPreviewContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmImport()">
                    <i class="fas fa-check me-2"></i>Confirm Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Toggle export options
function toggleExportOptions() {
    const scope = document.getElementById('exportScope').value;
    const groupSelect = document.getElementById('groupSelect');
    const settingsSelect = document.getElementById('settingsSelect');
    
    groupSelect.style.display = scope === 'group' ? 'block' : 'none';
    settingsSelect.style.display = scope === 'selected' ? 'block' : 'none';
}

// Export settings
function exportSettings() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    
    // Validate selected settings if scope is 'selected'
    const scope = formData.get('scope');
    if (scope === 'selected') {
        const selectedSettings = formData.getAll('settings[]');
        if (selectedSettings.length === 0) {
            Swal.fire('Error!', 'Please select at least one setting to export.', 'error');
            return;
        }
    }
    
    // Convert form data to query string
    const params = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
        if (key === 'settings[]') {
            params.append(key, value);
        } else {
            params.set(key, value);
        }
    }
    
    // Show export confirmation
    Swal.fire({
        title: 'Export Settings',
        text: 'Prepare your export file?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Export Now'
    }).then((result) => {
        if (result.isConfirmed) {
            // Open export in new window
            window.open(`export-settings.php?${params.toString()}`, '_blank');
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Export Started!',
                text: 'Your export file is being prepared.',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Import settings
function importSettings() {
    const form = document.getElementById('importForm');
    const formData = new FormData(form);
    const file = formData.get('import_file');
    
    if (!file || !file.name) {
        Swal.fire('Error!', 'Please select a file to import.', 'error');
        return;
    }
    
    // Validate file extension
    const validExtensions = ['.json', '.csv', '.xml', '.zip'];
    const fileExtension = file.name.slice(file.name.lastIndexOf('.')).toLowerCase();
    
    if (!validExtensions.includes(fileExtension)) {
        Swal.fire('Error!', 'Invalid file format. Please upload a JSON, CSV, XML, or ZIP file.', 'error');
        return;
    }
    
    // Check if dry run is selected
    const isDryRun = formData.get('dry_run') === 'on';
    
    Swal.fire({
        title: 'Processing File...',
        text: 'Please wait while we analyze your file.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            
            fetch('../ajax/settings/preview-import.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    if (isDryRun) {
                        showImportPreview(data, true);
                    } else {
                        showImportPreview(data, false);
                    }
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred while processing the file.', 'error');
            });
        }
    });
}

// Show import preview
function showImportPreview(data, isDryRun) {
    const preview = document.getElementById('importPreviewContent');
    const totalSettings = data.total_settings || 0;
    const newSettings = data.new_settings || 0;
    const existingSettings = data.existing_settings || 0;
    const conflicts = data.conflicts || 0;
    
    let html = `
        <div class="row g-4">
            <div class="col-md-6">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    ${isDryRun ? 'This is a preview. No changes will be made.' : 'Review the import details below.'}
                </div>
                
                <h6 class="mb-3">Import Summary</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>File:</strong></td>
                            <td>${data.filename}</td>
                        </tr>
                        <tr>
                            <td><strong>Format:</strong></td>
                            <td><span class="badge bg-primary">${data.format}</span></td>
                        </tr>
                        <tr>
                            <td><strong>Total Settings:</strong></td>
                            <td><span class="fw-bold">${totalSettings}</span></td>
                        </tr>
                        <tr>
                            <td><strong>New Settings:</strong></td>
                            <td><span class="badge bg-success">${newSettings}</span></td>
                        </tr>
                        <tr>
                            <td><strong>Existing Settings:</strong></td>
                            <td><span class="badge bg-warning">${existingSettings}</span></td>
                        </tr>
                        <tr>
                            <td><strong>Conflicts:</strong></td>
                            <td><span class="badge bg-danger">${conflicts}</span></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="col-md-6">
                <h6 class="mb-3">Groups Distribution</h6>
                <div class="list-group list-group-flush">
    `;
    
    if (data.groups && Object.keys(data.groups).length > 0) {
        for (const [group, count] of Object.entries(data.groups)) {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><i class="fas fa-folder me-2 text-primary"></i>${group}</span>
                    <span class="badge bg-primary rounded-pill">${count}</span>
                </div>
            `;
        }
    } else {
        html += `<div class="text-muted">No group information available</div>`;
    }
    
    html += `
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <h6 class="mb-3">Preview (First 10 Settings)</h6>
            <div class="table-responsive">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Setting Key</th>
                            <th>Group</th>
                            <th>Type</th>
                            <th>Value Preview</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    if (data.preview && data.preview.length > 0) {
        data.preview.slice(0, 10).forEach(setting => {
            let statusBadge = '<span class="badge bg-success">New</span>';
            if (setting.exists) {
                statusBadge = '<span class="badge bg-warning">Update</span>';
            }
            if (setting.conflict) {
                statusBadge = '<span class="badge bg-danger">Conflict</span>';
            }
            
            html += `
                <tr>
                    <td><code>${setting.key}</code></td>
                    <td>${setting.group}</td>
                    <td><span class="badge bg-info">${setting.type}</span></td>
                    <td><small>${setting.value_preview}</small></td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });
    }
    
    if (totalSettings > 10) {
        html += `
            <tr>
                <td colspan="5" class="text-center text-muted">
                    <i class="fas fa-ellipsis-h me-2"></i>
                    And ${totalSettings - 10} more settings...
                </td>
            </tr>
        `;
    }
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
        
        <input type="hidden" id="importData" value='${JSON.stringify(data)}'>
    `;
    
    preview.innerHTML = html;
    
    // Show confirm button only if not dry run
    const confirmBtn = document.querySelector('#importPreviewModal .btn-primary');
    if (confirmBtn) {
        confirmBtn.style.display = isDryRun ? 'none' : 'inline-block';
    }
    
    $('#importPreviewModal').modal('show');
}

// Confirm import
function confirmImport() {
    const importData = JSON.parse(document.getElementById('importData').value);
    const form = document.getElementById('importForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Confirm Import',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>You are about to import <strong>${importData.total_settings}</strong> settings.</p>
                <p class="text-muted small">This action may modify your system configuration.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, import now',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $('#importPreviewModal').modal('hide');
            
            Swal.fire({
                title: 'Importing...',
                text: 'Please wait while settings are being imported.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    fetch('../ajax/settings/import-settings.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Import Complete!',
                                html: `
                                    <div class="text-center">
                                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                        <p>${data.message}</p>
                                        <div class="row g-2 text-start mt-3">
                                            <div class="col-6">Total:</div>
                                            <div class="col-6 fw-bold">${data.total_imported || 0}</div>
                                            <div class="col-6">New:</div>
                                            <div class="col-6 fw-bold text-success">${data.new_settings || 0}</div>
                                            <div class="col-6">Updated:</div>
                                            <div class="col-6 fw-bold text-warning">${data.updated_settings || 0}</div>
                                            <div class="col-6">Skipped:</div>
                                            <div class="col-6 fw-bold text-muted">${data.skipped_settings || 0}</div>
                                        </div>
                                    </div>
                                `,
                                confirmButtonText: 'OK'
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
                        Swal.fire('Error!', 'An error occurred during import.', 'error');
                    });
                }
            });
        }
    });
}

// Refresh history
function refreshHistory() {
    location.reload();
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleExportOptions();
    
    // Add animation to stat cards
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>