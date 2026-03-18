<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

if (!isset($_GET['group']) || empty($_GET['group'])) {
    $_SESSION['error'] = 'Settings group is required.';
    redirect('admin/settings/settings.php');
}

$group_slug = $_GET['group'];

try {
    $db = getDB();
    
    // Get group information
    $stmt = $db->prepare("SELECT * FROM settings_groups WHERE slug = ?");
    $stmt->execute([$group_slug]);
    $group = $stmt->fetch();
    
    if (!$group) {
        $_SESSION['error'] = 'Settings group not found.';
        redirect('admin/settings/settings.php');
    }
    
    // Get settings for this group
    $stmt = $db->prepare("SELECT * FROM settings WHERE `group` = ? ORDER BY sort_order, setting_key");
    $stmt->execute([$group_slug]);
    $settings = $stmt->fetchAll();
    
    // Get all groups for navigation
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $all_groups = $stmt->fetchAll();
    
    // Get statistics
    $total_settings = count($settings);
    $required_count = 0;
    $public_count = 0;
    foreach ($settings as $s) {
        if ($s['is_required']) $required_count++;
        if ($s['is_public']) $public_count++;
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading settings: ' . $e->getMessage();
    redirect('settings.php');
}

$page_title = $group['name'] . ' Settings';
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

/* Group Navigation */
.group-nav {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.group-nav .nav-container {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.group-nav .nav-title {
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.group-nav .nav-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 1.5rem;
}

.nav-group-btn {
    padding: 0.6rem 1.2rem;
    border-radius: var(--border-radius-full);
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 2px solid var(--gray-200);
    background: white;
    color: var(--gray-700);
}

.nav-group-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

.nav-group-btn.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

.nav-group-btn i {
    font-size: 0.9rem;
}

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-pill {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    animation: slideIn 0.5s ease;
    animation-fill-mode: both;
}

.stat-pill:nth-child(1) { animation-delay: 0.05s; }
.stat-pill:nth-child(2) { animation-delay: 0.1s; }
.stat-pill:nth-child(3) { animation-delay: 0.15s; }
.stat-pill:nth-child(4) { animation-delay: 0.2s; }

.stat-pill:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stat-pill-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.stat-pill-content {
    flex: 1;
}

.stat-pill-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
    margin-bottom: 0.1rem;
}

.stat-pill-label {
    color: var(--gray-600);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Settings Grid */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

@media (max-width: 992px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}

/* Setting Card */
.setting-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    transition: var(--transition);
    animation: slideIn 0.5s ease;
    animation-fill-mode: both;
    height: 100%;
    position: relative;
}

.setting-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.setting-card .card-header {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.setting-card .card-title {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.setting-card .type-badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.7rem;
    font-weight: 600;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}

.setting-card .card-body {
    padding: 1.5rem;
}

.setting-card .help-text {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--gray-100);
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius-md);
    border-left: 3px solid var(--info);
}

.setting-card .help-text i {
    color: var(--info);
}

/* Form Controls */
.form-control, .form-select {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 0.6rem 1rem;
    font-size: 0.9rem;
    transition: var(--transition);
    width: 100%;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.form-control.font-monospace {
    font-family: 'Courier New', monospace;
}

/* Switch */
.form-switch {
    padding-left: 2.5em;
}

.form-switch .form-check-input {
    width: 2.5em;
    height: 1.5em;
    cursor: pointer;
    margin-left: -2.5em;
}

.form-switch .form-check-input:checked {
    background-color: var(--success);
    border-color: var(--success);
}

/* File Upload */
.file-upload-group {
    display: flex;
    gap: 0.5rem;
}

.file-upload-group .form-control {
    flex: 1;
}

.file-upload-group .btn-upload {
    width: 45px;
    height: 45px;
    border-radius: var(--border-radius-lg);
    background: var(--gray-100);
    border: 2px solid var(--gray-200);
    color: var(--gray-600);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
}

.file-upload-group .btn-upload:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

.file-preview {
    margin-top: 0.75rem;
    padding: 0.5rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-200);
}

.file-preview img {
    max-height: 80px;
    border-radius: var(--border-radius-md);
}

/* Validation Badge */
.validation-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: rgba(76, 201, 240, 0.1);
    border-radius: var(--border-radius-sm);
    font-size: 0.7rem;
    color: var(--info-dark);
    margin-top: 0.5rem;
}

/* Public Badge */
.public-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: rgba(6, 214, 160, 0.1);
    border-radius: var(--border-radius-sm);
    font-size: 0.7rem;
    color: var(--success-dark);
    margin-top: 0.5rem;
    margin-left: 0.5rem;
}

/* Card Footer */
.setting-card .card-footer {
    padding: 1rem 1.5rem;
    background: var(--gray-100);
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.setting-card .setting-meta {
    display: flex;
    gap: 1rem;
}

.setting-card .meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: var(--gray-600);
}

.setting-card .meta-item i {
    color: var(--primary);
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-lg);
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
}

/* Advanced Options */
.advanced-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-top: 2rem;
}

.advanced-card .card-header {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.advanced-card .card-header i {
    color: var(--warning);
    font-size: 1.2rem;
}

.advanced-card .card-header h5 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    font-size: 1rem;
}

.advanced-card .card-body {
    padding: 1.5rem;
}

.advanced-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 768px) {
    .advanced-grid {
        grid-template-columns: 1fr;
    }
}

.advanced-btn {
    padding: 1rem;
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
    background: white;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    width: 100%;
}

.advanced-btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.advanced-btn i {
    font-size: 1.5rem;
}

.advanced-btn span {
    font-weight: 600;
    font-size: 0.9rem;
}

.advanced-btn.export {
    border-color: var(--primary);
    color: var(--primary);
}

.advanced-btn.export:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

.advanced-btn.reset {
    border-color: var(--warning);
    color: var(--warning);
}

.advanced-btn.reset:hover {
    background: var(--warning-gradient);
    color: white;
    border-color: transparent;
}

.advanced-btn.delete {
    border-color: var(--danger);
    color: var(--danger);
}

.advanced-btn.delete:hover {
    background: var(--danger-gradient);
    color: white;
    border-color: transparent;
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
    padding: 1.25rem 1.5rem;
}

.modal-header .modal-title {
    font-weight: 600;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
    transition: var(--transition);
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

/* Progress Bar */
.progress {
    height: 0.5rem;
    border-radius: var(--border-radius-full);
    background: var(--gray-200);
}

.progress-bar {
    background: var(--primary-gradient);
    border-radius: var(--border-radius-full);
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: var(--border-radius-xl);
    border: 2px dashed var(--gray-300);
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

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 1rem;
    }
    
    .action-buttons {
        width: 100%;
    }
    
    .action-buttons button {
        flex: 1;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
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
                        <i class="<?php echo $group['icon']; ?>"></i>
                        <?php echo $group['name']; ?> Settings
                    </h1>
                    <p class="mb-0"><?php echo $group['description']; ?></p>
                </div>
                <div class="d-flex gap-2">
                    <a href="settings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Group Navigation -->
        <div class="group-nav">
            <div class="nav-container">
                <span class="nav-title">
                    <i class="fas fa-th-large me-2"></i>
                    Settings Groups
                </span>
                <span class="badge bg-primary"><?php echo count($all_groups); ?> Groups</span>
            </div>
            <div class="nav-links">
                <?php foreach($all_groups as $nav_group): ?>
                <a href="settings-group.php?group=<?php echo $nav_group['slug']; ?>" 
                   class="nav-group-btn <?php echo $nav_group['slug'] == $group_slug ? 'active' : ''; ?>">
                    <i class="<?php echo $nav_group['icon']; ?>"></i>
                    <?php echo $nav_group['name']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="stat-pill-content">
                    <div class="stat-pill-value"><?php echo $total_settings; ?></div>
                    <div class="stat-pill-label">Total Settings</div>
                </div>
            </div>
            
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                    <i class="fas fa-asterisk"></i>
                </div>
                <div class="stat-pill-content">
                    <div class="stat-pill-value"><?php echo $required_count; ?></div>
                    <div class="stat-pill-label">Required</div>
                </div>
            </div>
            
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-pill-content">
                    <div class="stat-pill-value"><?php echo $public_count; ?></div>
                    <div class="stat-pill-label">Public</div>
                </div>
            </div>
            
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-pill-content">
                    <div class="stat-pill-value"><?php echo count($settings); ?></div>
                    <div class="stat-pill-label">Active</div>
                </div>
            </div>
        </div>
        
        <!-- Settings Form -->
        <form id="settingsForm" method="POST" action="<?php echo SITE_URL; ?>admin/ajax/save-settings.php">
            <input type="hidden" name="group" value="<?php echo $group_slug; ?>">
            
            <?php if (empty($settings)): ?>
            <div class="empty-state">
                <i class="fas fa-cogs"></i>
                <h5>No Settings Found</h5>
                <p class="text-muted">This group doesn't have any settings yet.</p>
                <a href="add-setting.php?group=<?php echo $group_slug; ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add First Setting
                </a>
            </div>
            <?php else: ?>
            
            <!-- Settings Grid -->
            <div class="settings-grid">
                <?php foreach($settings as $index => $setting): ?>
                <div class="setting-card" style="animation-delay: <?php echo $index * 0.03; ?>s;">
                    <div class="card-header">
                        <h6 class="card-title">
                            <i class="fas fa-sliders-h text-primary"></i>
                            <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                        </h6>
                        <span class="type-badge">
                            <i class="fas fa-code me-1"></i>
                            <?php echo $setting['setting_type']; ?>
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($setting['help_text']): ?>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $setting['help_text']; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $field_id = 'setting_' . $setting['setting_key'];
                        $field_name = 'settings[' . $setting['setting_key'] . ']';
                        $field_value = htmlspecialchars($setting['setting_value'] ?? '');
                        $field_required = $setting['is_required'] ? 'required' : '';
                        
                        switch($setting['setting_type']):
                            case 'text':
                            case 'number':
                            case 'email':
                            case 'password':
                            case 'url':
                            case 'color':
                        ?>
                            <input type="<?php echo $setting['setting_type']; ?>" 
                                   class="form-control" 
                                   id="<?php echo $field_id; ?>"
                                   name="<?php echo $field_name; ?>"
                                   value="<?php echo $field_value; ?>"
                                   placeholder="Enter <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>"
                                   <?php echo $field_required; ?>>
                        
                        <?php break; case 'textarea': ?>
                            <textarea class="form-control" 
                                      id="<?php echo $field_id; ?>"
                                      name="<?php echo $field_name; ?>"
                                      rows="3"
                                      placeholder="Enter <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>"
                                      <?php echo $field_required; ?>><?php echo $field_value; ?></textarea>
                        
                        <?php break; case 'boolean': ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="<?php echo $field_id; ?>"
                                       name="<?php echo $field_name; ?>"
                                       value="1"
                                       <?php echo $field_value == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="<?php echo $field_id; ?>">
                                    Enable this setting
                                </label>
                            </div>
                        
                        <?php break; case 'select': 
                            $options = $setting['options'] ? json_decode($setting['options'], true) : [];
                        ?>
                            <select class="form-select" 
                                    id="<?php echo $field_id; ?>"
                                    name="<?php echo $field_name; ?>"
                                    <?php echo $field_required; ?>>
                                <option value="">-- Select Option --</option>
                                <?php foreach($options as $option): ?>
                                <option value="<?php echo $option; ?>" 
                                    <?php echo $field_value == $option ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($option); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        
                        <?php break; case 'json': ?>
                            <textarea class="form-control font-monospace" 
                                      id="<?php echo $field_id; ?>"
                                      name="<?php echo $field_name; ?>"
                                      rows="4"
                                      placeholder="Enter JSON data"
                                      <?php echo $field_required; ?>><?php 
                                if ($field_value) {
                                    $json = json_decode($field_value, true);
                                    echo json_encode($json, JSON_PRETTY_PRINT);
                                }
                            ?></textarea>
                        
                        <?php break; case 'file': ?>
                            <div class="file-upload-group">
                                <input type="text" 
                                       class="form-control" 
                                       id="<?php echo $field_id; ?>_display"
                                       value="<?php echo $field_value; ?>"
                                       readonly>
                                <button type="button" 
                                        class="btn-upload"
                                        onclick="uploadFile('<?php echo $field_id; ?>')"
                                        title="Upload File">
                                    <i class="fas fa-upload"></i>
                                </button>
                                <input type="hidden" 
                                       id="<?php echo $field_id; ?>"
                                       name="<?php echo $field_name; ?>"
                                       value="<?php echo $field_value; ?>">
                            </div>
                            
                            <?php if ($field_value && file_exists($_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/' . $field_value)): ?>
                            <div class="file-preview">
                                <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $field_value)): ?>
                                <img src="<?php echo SITE_URL; ?>uploads/<?php echo $field_value; ?>" 
                                     alt="Preview" class="img-fluid">
                                <?php else: ?>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-file fa-2x text-muted"></i>
                                    <span class="small"><?php echo $field_value; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        
                        <?php default: ?>
                            <input type="text" 
                                   class="form-control" 
                                   id="<?php echo $field_id; ?>"
                                   name="<?php echo $field_name; ?>"
                                   value="<?php echo $field_value; ?>"
                                   <?php echo $field_required; ?>>
                        
                        <?php endswitch; ?>
                        
                        <div class="d-flex align-items-center flex-wrap">
                            <?php if ($setting['validation_rules']): ?>
                            <span class="validation-badge">
                                <i class="fas fa-shield-alt"></i>
                                <?php echo $setting['validation_rules']; ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($setting['is_public']): ?>
                            <span class="public-badge">
                                <i class="fas fa-eye"></i>
                                Public
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="setting-meta">
                            <span class="meta-item" title="Sort Order">
                                <i class="fas fa-sort"></i>
                                <?php echo $setting['sort_order']; ?>
                            </span>
                            <span class="meta-item" title="Last Updated">
                                <i class="far fa-clock"></i>
                                <?php echo date('M d', strtotime($setting['updated_at'] ?? $setting['created_at'])); ?>
                            </span>
                        </div>
                        <a href="edit-field.php?id=<?php echo $setting['id']; ?>" class="meta-item" title="Edit Field">
                            <i class="fas fa-edit text-primary"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save All Changes
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                        <i class="fas fa-undo me-2"></i>Reset
                    </button>
                </div>
                <div>
                    <span class="text-muted me-3">
                        <i class="fas fa-cog me-1"></i>
                        <?php echo count($settings); ?> Settings
                    </span>
                    <a href="add-setting.php?group=<?php echo $group_slug; ?>" class="btn btn-outline-success">
                        <i class="fas fa-plus me-2"></i>Add New
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </form>
        
        <!-- Advanced Options -->
        <div class="advanced-card">
            <div class="card-header">
                <i class="fas fa-tools"></i>
                <h5>Advanced Options</h5>
            </div>
            <div class="card-body">
                <div class="advanced-grid">
                    <button class="advanced-btn export" onclick="exportGroupSettings()">
                        <i class="fas fa-download"></i>
                        <span>Export Group</span>
                        <small class="text-muted">Download as JSON</small>
                    </button>
                    
                    <button class="advanced-btn reset" onclick="resetGroupToDefault()">
                        <i class="fas fa-undo-alt"></i>
                        <span>Reset to Default</span>
                        <small class="text-muted">Restore defaults</small>
                    </button>
                    
                    <button class="advanced-btn delete" onclick="deleteGroupSettings()">
                        <i class="fas fa-trash"></i>
                        <span>Delete Group</span>
                        <small class="text-muted">Remove all settings</small>
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- File Upload Modal -->
<div class="modal fade" id="fileUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-upload me-2"></i>
                    Upload File
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="fileUploadForm">
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" class="form-control" id="fileInput" required>
                        <input type="hidden" id="targetField">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">File Type</label>
                        <select class="form-select" id="fileType">
                            <option value="image">🖼️ Image</option>
                            <option value="document">📄 Document</option>
                            <option value="other">📁 Other</option>
                        </select>
                    </div>
                    
                    <div class="progress d-none" id="uploadProgress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    
                    <div class="text-muted small mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Max file size: 2MB
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="processFileUpload()">
                    <i class="fas fa-upload me-2"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentUploadField = null;
let autoSaveTimeout;

// Save group settings
function saveGroupSettings() {
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Save Settings',
        text: 'Save all changes in this group?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Save Changes',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Saving...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred while saving.', 'error');
            });
        }
    });
}

// Reset form
function resetForm() {
    Swal.fire({
        title: 'Reset Form',
        text: 'Reset all fields to their current values?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Reset',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('settingsForm').reset();
            Swal.fire('Reset!', 'Form has been reset.', 'success');
        }
    });
}

// Upload file
function uploadFile(fieldId) {
    currentUploadField = fieldId;
    document.getElementById('targetField').value = fieldId;
    document.getElementById('fileInput').value = '';
    document.getElementById('uploadProgress').classList.add('d-none');
    
    $('#fileUploadModal').modal('show');
}

// Process file upload
function processFileUpload() {
    const fileInput = document.getElementById('fileInput');
    const fileType = document.getElementById('fileType').value;
    const progressBar = document.getElementById('uploadProgress');
    
    if (!fileInput.files[0]) {
        Swal.fire('Error!', 'Please select a file to upload.', 'error');
        return;
    }
    
    // Check file size (max 2MB)
    if (fileInput.files[0].size > 2 * 1024 * 1024) {
        Swal.fire('Error!', 'File size must be less than 2MB.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('type', fileType);
    formData.append('field', currentUploadField);
    
    progressBar.classList.remove('d-none');
    
    Swal.fire({
        title: 'Uploading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('../ajax/upload-setting-file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            // Update the hidden field
            document.getElementById(currentUploadField).value = data.filename;
            // Update the display field
            document.getElementById(currentUploadField + '_display').value = data.filename;
            
            Swal.fire('Success!', 'File uploaded successfully.', 'success');
            $('#fileUploadModal').modal('hide');
            
            // Reload after 1 second to show preview
            setTimeout(() => location.reload(), 1000);
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred during upload.', 'error');
    })
    .finally(() => {
        progressBar.classList.add('d-none');
    });
}

// Export group settings
function exportGroupSettings() {
    Swal.fire({
        title: 'Export Group Settings',
        text: 'Export all settings from this group?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Export',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`import-export.php?group=<?php echo $group_slug; ?>&format=json`, '_blank');
            Swal.fire('Success!', 'Export started.', 'success');
        }
    });
}

// Reset group to default
function resetGroupToDefault() {
    Swal.fire({
        title: 'Reset to Default',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>Reset all settings in this group to their default values?</p>
                <p class="text-muted small">This action cannot be undone!</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, reset',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Resetting...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../ajax/reset-settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group: '<?php echo $group_slug; ?>' })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reset!',
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

// Delete group settings
function deleteGroupSettings() {
    Swal.fire({
        title: 'Delete Group Settings',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Delete all settings in this group?</p>
                <p class="text-muted small">This action cannot be undone!</p>
            </div>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete all',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../ajax/delete-settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group: '<?php echo $group_slug; ?>' })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'settings.php';
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

// Form submission
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveGroupSettings();
});

// JSON formatting
document.querySelectorAll('textarea.font-monospace').forEach(textarea => {
    textarea.addEventListener('blur', function() {
        try {
            const json = JSON.parse(this.value);
            this.value = JSON.stringify(json, null, 2);
        } catch (e) {
            // Invalid JSON, leave as is
        }
    });
});

// Auto-save for important fields
document.querySelectorAll('#settingsForm input, #settingsForm select, #settingsForm textarea').forEach(element => {
    element.addEventListener('input', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            if (this.id.includes('site_') || this.id.includes('email_') || this.id.includes('payment_')) {
                const form = document.getElementById('settingsForm');
                const formData = new FormData();
                formData.append('settings[' + this.name.replace('settings[', '').replace(']', '') + ']', this.value);
                formData.append('group', '<?php echo $group_slug; ?>');
                
                fetch('../ajax/save-single-setting.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Auto-saved:', this.name);
                    }
                })
                .catch(error => console.error('Auto-save error:', error));
            }
        }, 2000);
    });
});

// Initialize animations
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.setting-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.03}s`;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>