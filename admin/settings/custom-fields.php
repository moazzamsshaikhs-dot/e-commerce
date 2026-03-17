<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'Custom Fields';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get custom fields
    $stmt = $db->query("SELECT cf.*, sg.name as group_name 
                        FROM custom_fields cf 
                        LEFT JOIN settings_groups sg ON cf.group_id = sg.id 
                        ORDER BY cf.sort_order, cf.name");
    $custom_fields = $stmt->fetchAll();
    
    // Get settings groups
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $groups = $stmt->fetchAll();
    
    // Get field types with icons
    $field_types = [
        'text' => ['name' => 'Text', 'icon' => 'fa-font'],
        'textarea' => ['name' => 'Textarea', 'icon' => 'fa-paragraph'],
        'number' => ['name' => 'Number', 'icon' => 'fa-hashtag'],
        'email' => ['name' => 'Email', 'icon' => 'fa-envelope'],
        'password' => ['name' => 'Password', 'icon' => 'fa-lock'],
        'url' => ['name' => 'URL', 'icon' => 'fa-link'],
        'color' => ['name' => 'Color', 'icon' => 'fa-palette'],
        'date' => ['name' => 'Date', 'icon' => 'fa-calendar'],
        'datetime' => ['name' => 'Date & Time', 'icon' => 'fa-calendar-check'],
        'time' => ['name' => 'Time', 'icon' => 'fa-clock'],
        'select' => ['name' => 'Dropdown', 'icon' => 'fa-caret-down'],
        'checkbox' => ['name' => 'Checkbox', 'icon' => 'fa-check-square'],
        'radio' => ['name' => 'Radio', 'icon' => 'fa-dot-circle'],
        'file' => ['name' => 'File', 'icon' => 'fa-file'],
        'image' => ['name' => 'Image', 'icon' => 'fa-image'],
        'editor' => ['name' => 'Rich Text Editor', 'icon' => 'fa-edit'],
        'json' => ['name' => 'JSON', 'icon' => 'fa-code']
    ];
    
} catch(PDOException $e) {
    $error = 'Error loading custom fields: ' . $e->getMessage();
    $custom_fields = [];
    $groups = [];
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

/* Fields Card */
.fields-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.fields-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.fields-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.fields-card .card-header h5 i {
    color: var(--primary);
}

.fields-card .card-header .header-actions {
    display: flex;
    gap: 0.75rem;
}

.fields-card .card-body {
    padding: 0;
}

/* Search and Filter Bar */
.search-filter-bar {
    display: flex;
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: var(--gray-100);
    border-bottom: 1px solid var(--gray-200);
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
}

.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    font-size: 0.95rem;
    transition: var(--transition);
}

.search-box input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 0.75rem 1.5rem;
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    background: white;
    color: var(--gray-700);
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.filter-btn.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

/* Fields Table */
.fields-table-container {
    overflow-x: auto;
}

.fields-table {
    width: 100%;
    border-collapse: collapse;
}

.fields-table th {
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

.fields-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-600);
    transition: var(--transition);
}

.fields-table tbody tr {
    transition: var(--transition);
    animation: fadeIn 0.3s ease;
}

.fields-table tbody tr:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
}

.fields-table tbody tr:hover td {
    color: var(--gray-800);
}

/* Field ID */
.field-id {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    background: var(--gray-200);
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
}

/* Field Name Cell */
.field-name-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.field-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    flex-shrink: 0;
}

.field-info {
    flex: 1;
}

.field-name {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.field-key {
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* Type Badge */
.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 1rem;
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
}

.type-badge i {
    color: var(--primary);
}

/* Group Badge */
.group-badge {
    padding: 0.35rem 1rem;
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    background: rgba(76, 201, 240, 0.1);
    color: var(--info-dark);
    border: 1px solid rgba(76, 201, 240, 0.3);
    display: inline-block;
}

/* Value Preview */
.value-preview {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    color: var(--gray-600);
    background: var(--gray-100);
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-md);
    display: inline-block;
}

/* Requirement Badge */
.requirement-badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.7rem;
    font-weight: 600;
}

.requirement-badge.required {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.requirement-badge.optional {
    background: rgba(108, 117, 125, 0.15);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}

/* Status Toggle */
.status-toggle {
    position: relative;
    display: inline-block;
}

.status-toggle .form-switch {
    padding-left: 2.5em;
}

.status-toggle .form-switch .form-check-input {
    width: 2.5em;
    height: 1.5em;
    cursor: pointer;
}

.status-toggle .form-switch .form-check-input:checked {
    background-color: var(--success);
    border-color: var(--success);
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

.action-btn.edit:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

.action-btn.view:hover {
    background: linear-gradient(135deg, var(--info), var(--info-dark));
    color: white;
    border-color: transparent;
}

.action-btn.delete:hover {
    background: linear-gradient(135deg, var(--danger), var(--danger-dark));
    color: white;
    border-color: transparent;
}

/* Stats Cards */
.stats-card {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stats-card .stats-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.stats-card .stats-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--border-radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.stats-card .stats-title {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.25rem;
}

.stats-card .stats-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
}

.stats-card .stats-progress {
    height: 6px;
    background: var(--gray-200);
    border-radius: var(--border-radius-full);
    overflow: hidden;
    margin-top: 0.75rem;
}

.stats-card .stats-progress-bar {
    height: 100%;
    background: var(--primary-gradient);
    border-radius: var(--border-radius-full);
    transition: width 1s ease;
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

.empty-state .btn {
    padding: 0.75rem 2rem;
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

/* Options Container */
.options-container {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    border: 2px dashed var(--gray-300);
    transition: var(--transition);
}

.options-container:hover {
    border-color: var(--primary);
    background: white;
}

.options-container textarea {
    background: white;
}

/* Detail Cards */
.detail-card {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    height: 100%;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.detail-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
    background: white;
}

.detail-card .detail-label {
    font-size: 0.8rem;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.detail-card .detail-value {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: var(--gray-800);
    background: white;
    padding: 0.5rem;
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-200);
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

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    padding: 2rem;
    border-top: 1px solid var(--gray-200);
}

.pagination-btn {
    padding: 0.5rem 1rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-md);
    background: white;
    color: var(--gray-700);
    transition: var(--transition);
    cursor: pointer;
    min-width: 40px;
}

.pagination-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(67, 97, 238, 0.05);
}

.pagination-btn.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .search-filter-bar {
        flex-direction: column;
        padding: 1rem;
    }
    
    .filter-buttons {
        justify-content: stretch;
    }
    
    .filter-btn {
        flex: 1;
    }
    
    .fields-table th,
    .fields-table td {
        padding: 0.75rem;
    }
    
    .field-name-cell {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .action-group {
        flex-wrap: wrap;
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
                    <h1><i class="fas fa-puzzle-piece me-3"></i>Custom Fields</h1>
                    <p class="mb-0">Create and manage custom settings fields for your application</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group justify-content-md-end">
                        <button class="btn btn-outline-primary" onclick="exportCustomFields()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button class="btn btn-outline-success" onclick="importCustomFields()">
                            <i class="fas fa-upload me-2"></i>Import
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldModal">
                            <i class="fas fa-plus-circle me-2"></i>Add Field
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Custom Fields Card -->
        <div class="fields-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i>
                    Custom Fields
                    <span class="badge bg-primary ms-2"><?php echo count($custom_fields); ?> Fields</span>
                </h5>
                <div class="header-actions">
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshList()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>
            
            <?php if (!empty($custom_fields)): ?>
            <div class="search-filter-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search fields..." onkeyup="filterFields()">
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterByGroup('all')" id="filterAll">
                        <i class="fas fa-globe"></i> All
                    </button>
                    <?php foreach($groups as $group): ?>
                    <button class="filter-btn" onclick="filterByGroup('<?php echo $group['id']; ?>')" id="filterGroup<?php echo $group['id']; ?>">
                        <i class="fas fa-folder"></i> <?php echo $group['name']; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-body">
                <?php if (empty($custom_fields)): ?>
                <div class="empty-state">
                    <i class="fas fa-puzzle-piece"></i>
                    <h5>No Custom Fields</h5>
                    <p class="text-muted">Create your first custom field to extend your settings</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Your First Field
                    </button>
                </div>
                <?php else: ?>
                <div class="fields-table-container">
                    <table class="fields-table" id="fieldsTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="25%">Field</th>
                                <th width="15%">Type</th>
                                <th width="15%">Group</th>
                                <th width="15%">Default Value</th>
                                <th width="10%">Required</th>
                                <th width="10%">Status</th>
                                <th width="15%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($custom_fields as $field): 
                                $field_type = $field_types[$field['field_type']] ?? ['name' => $field['field_type'], 'icon' => 'fa-question'];
                                $icon_colors = [
                                    'text' => 'primary', 'textarea' => 'info', 'number' => 'warning',
                                    'email' => 'danger', 'password' => 'dark', 'url' => 'info',
                                    'color' => 'success', 'date' => 'primary', 'datetime' => 'primary',
                                    'time' => 'primary', 'select' => 'warning', 'checkbox' => 'success',
                                    'radio' => 'success', 'file' => 'danger', 'image' => 'danger',
                                    'editor' => 'info', 'json' => 'secondary'
                                ];
                                $icon_color = $icon_colors[$field['field_type']] ?? 'secondary';
                            ?>
                            <tr data-id="<?php echo $field['id']; ?>" 
                                data-group="<?php echo $field['group_id'] ?? 0; ?>"
                                data-name="<?php echo strtolower($field['name']); ?>"
                                data-key="<?php echo strtolower($field['field_key']); ?>">
                                <td>
                                    <span class="field-id">#<?php echo $field['id']; ?></span>
                                </td>
                                <td>
                                    <div class="field-name-cell">
                                        <div class="field-icon" style="background: linear-gradient(135deg, var(--<?php echo $icon_color; ?>), var(--<?php echo $icon_color; ?>-dark));">
                                            <i class="fas <?php echo $field_type['icon']; ?>"></i>
                                        </div>
                                        <div class="field-info">
                                            <div class="field-name"><?php echo htmlspecialchars($field['name']); ?></div>
                                            <div class="field-key"><?php echo $field['field_key']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="type-badge">
                                        <i class="fas <?php echo $field_type['icon']; ?>"></i>
                                        <?php echo $field_type['name']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($field['group_name']): ?>
                                    <span class="group-badge">
                                        <i class="fas fa-folder me-1"></i>
                                        <?php echo $field['group_name']; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">General</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="value-preview" title="<?php echo htmlspecialchars($field['default_value'] ?? ''); ?>">
                                        <?php echo $field['default_value'] ? htmlspecialchars(substr($field['default_value'], 0, 30)) . (strlen($field['default_value']) > 30 ? '...' : '') : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="requirement-badge <?php echo $field['is_required'] ? 'required' : 'optional'; ?>">
                                        <i class="fas fa-<?php echo $field['is_required'] ? 'asterisk' : 'minus'; ?> me-1"></i>
                                        <?php echo $field['is_required'] ? 'Required' : 'Optional'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="status-toggle">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input field-toggle" 
                                                   type="checkbox" 
                                                   data-id="<?php echo $field['id']; ?>"
                                                   <?php echo $field['is_active'] ? 'checked' : ''; ?>
                                                   onchange="toggleField(this, <?php echo $field['id']; ?>)">
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="action-btn view" onclick="viewField(<?php echo $field['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" onclick="editField(<?php echo $field['id']; ?>)" title="Edit Field">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteField(<?php echo $field['id']; ?>)" title="Delete Field">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination" id="pagination"></div>
                
                <!-- Table Info -->
                <div class="text-center pb-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Showing <span id="showingCount"><?php echo count($custom_fields); ?></span> of <?php echo count($custom_fields); ?> fields
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Field Types Distribution -->
        <?php if (!empty($custom_fields)): ?>
        <div class="row g-4">
            <?php 
            $type_counts = [];
            foreach($custom_fields as $field) {
                $type = $field['field_type'];
                $type_counts[$type] = isset($type_counts[$type]) ? $type_counts[$type] + 1 : 1;
            }
            
            foreach($type_counts as $type => $count):
                $percentage = round(($count / count($custom_fields)) * 100);
                $type_info = $field_types[$type] ?? ['name' => $type, 'icon' => 'fa-question'];
                $icon_color = $icon_colors[$type] ?? 'secondary';
            ?>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-header">
                        <div class="stats-icon" style="background: linear-gradient(135deg, var(--<?php echo $icon_color; ?>), var(--<?php echo $icon_color; ?>-dark));">
                            <i class="fas <?php echo $type_info['icon']; ?>"></i>
                        </div>
                        <div>
                            <div class="stats-title"><?php echo $type_info['name']; ?></div>
                            <div class="stats-count"><?php echo $count; ?></div>
                        </div>
                    </div>
                    <div class="stats-progress">
                        <div class="stats-progress-bar" style="width: <?php echo $percentage; ?>%;"></div>
                    </div>
                    <div class="text-end mt-2">
                        <small class="text-muted"><?php echo $percentage; ?>% of total</small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Add Field Modal -->
<div class="modal fade" id="addFieldModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add Custom Field
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addFieldForm">
                    <div class="row g-4">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-tag text-primary"></i>
                                    Field Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="name" required 
                                       placeholder="e.g., Site Logo, Contact Email">
                                <div class="form-text">Display name for the field</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-key text-success"></i>
                                    Field Key <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="field_key" required 
                                       pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only"
                                       placeholder="e.g., site_logo, contact_email">
                                <div class="form-text">Unique identifier (lowercase, underscore)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-database text-warning"></i>
                                    Field Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="field_type" required id="fieldType"
                                        onchange="toggleFieldOptions()">
                                    <?php foreach($field_types as $key => $info): ?>
                                    <option value="<?php echo $key; ?>">
                                        <?php echo $info['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-layer-group text-info"></i>
                                    Settings Group
                                </label>
                                <select class="form-select" name="group_id">
                                    <option value="">General (No Group)</option>
                                    <?php foreach($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>">
                                        <?php echo $group['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-check-circle text-success"></i>
                                    Default Value
                                </label>
                                <input type="text" class="form-control" name="default_value" 
                                       id="defaultValue" placeholder="Default value">
                                <div class="form-text">Optional default value</div>
                            </div>
                            
                            <div class="mb-3" id="optionsContainer" style="display: none;">
                                <label class="form-label">
                                    <i class="fas fa-list-ul text-warning"></i>
                                    Options (for Select/Radio)
                                </label>
                                <div class="options-container">
                                    <textarea class="form-control" name="options" rows="4" 
                                              placeholder='["option1", "option2", "option3"]'></textarea>
                                </div>
                                <div class="form-text">Enter as JSON array</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-shield-alt text-danger"></i>
                                    Validation Rules
                                </label>
                                <input type="text" class="form-control" name="validation_rules" 
                                       placeholder="required|email|max:255">
                                <div class="form-text">Pipe-separated validation rules</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-question-circle text-info"></i>
                                    Help Text
                                </label>
                                <textarea class="form-control" name="help_text" rows="2" 
                                          placeholder="Help text to show below the field"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Field Options Row -->
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_required" id="isRequired">
                                <label class="form-check-label" for="isRequired">
                                    <i class="fas fa-asterisk text-danger me-1"></i>
                                    Required Field
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_public" id="isPublic">
                                <label class="form-check-label" for="isPublic">
                                    <i class="fas fa-globe text-info me-1"></i>
                                    Public Field
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-code text-primary"></i>
                                    CSS Class
                                </label>
                                <input type="text" class="form-control" name="css_class" 
                                       placeholder="form-control custom-class">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveField()">
                    <i class="fas fa-save me-2"></i>Save Field
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Field Modal -->
<div class="modal fade" id="viewFieldModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Field Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="fieldDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPage = 1;
let rowsPerPage = 10;
let currentGroupFilter = 'all';
let searchTerm = '';

// Toggle field options based on type
function toggleFieldOptions() {
    const type = document.getElementById('fieldType').value;
    const optionsContainer = document.getElementById('optionsContainer');
    const defaultValue = document.getElementById('defaultValue');
    
    // Show/hide options for select/radio types
    optionsContainer.style.display = (type === 'select' || type === 'radio') ? 'block' : 'none';
    
    // Update default value placeholder
    const placeholders = {
        'color': '#000000',
        'email': 'email@example.com',
        'url': 'https://example.com',
        'number': '123',
        'date': 'YYYY-MM-DD',
        'time': 'HH:MM',
        'datetime': 'YYYY-MM-DD HH:MM'
    };
    defaultValue.placeholder = placeholders[type] || 'Default value';
}

// Filter fields by group
function filterByGroup(groupId) {
    currentGroupFilter = groupId;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    if (groupId === 'all') {
        document.getElementById('filterAll').classList.add('active');
    } else {
        document.getElementById(`filterGroup${groupId}`).classList.add('active');
    }
    
    applyFilters();
}

// Filter fields by search
function filterFields() {
    searchTerm = document.getElementById('searchInput').value.toLowerCase();
    applyFilters();
}

// Apply all filters
function applyFilters() {
    const rows = document.querySelectorAll('#fieldsTable tbody tr');
    let visibleCount = 0;
    
    rows.forEach((row, index) => {
        const group = row.dataset.group;
        const name = row.dataset.name;
        const key = row.dataset.key;
        
        const matchesGroup = currentGroupFilter === 'all' || group === currentGroupFilter;
        const matchesSearch = searchTerm === '' || 
                            name.includes(searchTerm) || 
                            key.includes(searchTerm);
        
        if (matchesGroup && matchesSearch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('showingCount').textContent = visibleCount;
    setupPagination();
}

// Setup pagination
function setupPagination() {
    const rows = document.querySelectorAll('#fieldsTable tbody tr:not([style*="display: none"])');
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    
    if (totalPages <= 1) {
        document.getElementById('pagination').innerHTML = '';
        showPage(1);
        return;
    }
    
    let paginationHtml = '';
    
    // Previous button
    paginationHtml += `<button class="pagination-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            paginationHtml += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            paginationHtml += `<span class="pagination-btn">...</span>`;
        }
    }
    
    // Next button
    paginationHtml += `<button class="pagination-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    document.getElementById('pagination').innerHTML = paginationHtml;
    showPage(currentPage);
}

// Show specific page
function showPage(page) {
    const rows = document.querySelectorAll('#fieldsTable tbody tr:not([style*="display: none"])');
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    
    rows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
}

// Change page
function changePage(page) {
    currentPage = page;
    setupPagination();
}

// Toggle field active status
function toggleField(checkbox, fieldId) {
    const isActive = checkbox.checked ? 1 : 0;
    
    fetch('../ajax/settings/toggle-field.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field_id: fieldId, is_active: isActive })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            checkbox.checked = !checkbox.checked;
            showToast('error', data.message);
        } else {
            showToast('success', 'Field status updated');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        checkbox.checked = !checkbox.checked;
        showToast('error', 'An error occurred');
    });
}

// Save field
function saveField() {
    const form = document.getElementById('addFieldForm');
    const formData = new FormData(form);
    
    // Validate field key
    const fieldKey = formData.get('field_key');
    if (!fieldKey.match(/^[a-z0-9_]+$/)) {
        Swal.fire('Error!', 'Field key must contain only lowercase letters, numbers, and underscores.', 'error');
        return;
    }
    
    // Validate options if present
    const options = formData.get('options');
    if (options) {
        try {
            JSON.parse(options);
        } catch (e) {
            Swal.fire('Error!', 'Options must be valid JSON.', 'error');
            return;
        }
    }

    showLoading();
    
    fetch('../ajax/settings/save-field.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                $('#addFieldModal').modal('hide');
                location.reload();
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// View field
function viewField(fieldId) {
    const modalContent = document.getElementById('fieldDetailsContent');
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    $('#viewFieldModal').modal('show');
    
    fetch(`../ajax/settings/get-field.php?id=${fieldId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const field = data.field;
            const options = field.options ? JSON.parse(field.options) : null;
            
            let html = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-hashtag text-primary"></i>
                                Field ID
                            </div>
                            <div class="detail-value">#${field.id}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-tag text-success"></i>
                                Field Name
                            </div>
                            <div class="detail-value">${field.name}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-key text-warning"></i>
                                Field Key
                            </div>
                            <div class="detail-value">${field.field_key}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-database text-info"></i>
                                Field Type
                            </div>
                            <div class="detail-value">${field.field_type}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-layer-group text-primary"></i>
                                Group
                            </div>
                            <div class="detail-value">${field.group_name || 'General'}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-check-circle text-success"></i>
                                Required
                            </div>
                            <div class="detail-value">${field.is_required ? 'Yes' : 'No'}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-globe text-info"></i>
                                Public
                            </div>
                            <div class="detail-value">${field.is_public ? 'Yes' : 'No'}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-power-off text-warning"></i>
                                Status
                            </div>
                            <div class="detail-value">${field.is_active ? 'Active' : 'Inactive'}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-sort-amount-up text-primary"></i>
                                Sort Order
                            </div>
                            <div class="detail-value">${field.sort_order}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-check-circle text-success"></i>
                                Default Value
                            </div>
                            <div class="detail-value">${field.default_value || 'None'}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-shield-alt text-danger"></i>
                                Validation Rules
                            </div>
                            <div class="detail-value">${field.validation_rules || 'None'}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-question-circle text-info"></i>
                                Help Text
                            </div>
                            <div class="detail-value">${field.help_text || 'None'}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-code text-primary"></i>
                                CSS Class
                            </div>
                            <div class="detail-value">${field.css_class || 'None'}</div>
                        </div>
                    </div>
            `;
            
            if (options) {
                html += `
                    <div class="col-12">
                        <div class="detail-card">
                            <div class="detail-label">
                                <i class="fas fa-list-ul text-warning"></i>
                                Options
                            </div>
                            <pre class="detail-value" style="white-space: pre-wrap;">${JSON.stringify(options, null, 2)}</pre>
                        </div>
                    </div>
                `;
            }
            
            html += `</div>`;
            
            modalContent.innerHTML = html;
        } else {
            modalContent.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalContent.innerHTML = '<div class="alert alert-danger">Failed to load field details.</div>';
    });
}

// Edit field
function editField(fieldId) {
    // You can implement edit functionality here
    Swal.fire('Info', 'Edit functionality coming soon!', 'info');
}

// Delete field
function deleteField(fieldId) {
    Swal.fire({
        title: 'Delete Field',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Are you sure you want to delete this field?</p>
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
            
            fetch('../ajax/settings/delete-field.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ field_id: fieldId })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', 'Field deleted successfully');
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

// Export custom fields
function exportCustomFields() {
    Swal.fire({
        title: 'Export Custom Fields',
        text: 'Choose export format',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '📄 JSON',
        denyButtonText: '📋 CSV',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3085d6',
        denyButtonColor: '#1cc88a'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open('export-custom-fields.php?format=json', '_blank');
            showToast('success', 'Exporting as JSON...');
        } else if (result.isDenied) {
            window.open('export-custom-fields.php?format=csv', '_blank');
            showToast('success', 'Exporting as CSV...');
        }
    });
}

// Import custom fields
function importCustomFields() {
    Swal.fire({
        title: 'Import Custom Fields',
        html: `
            <div class="text-start">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Import fields from JSON file
                </div>
                <input type="file" class="form-control" id="importFile" accept=".json">
                <div class="form-check mt-3">
                    <input type="checkbox" class="form-check-input" id="overwriteExisting">
                    <label class="form-check-label">Overwrite existing fields</label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Import',
        preConfirm: () => {
            const file = document.getElementById('importFile').files[0];
            if (!file) {
                Swal.showValidationMessage('Please select a file');
                return false;
            }
            return { file, overwrite: document.getElementById('overwriteExisting').checked };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('file', result.value.file);
            formData.append('overwrite', result.value.overwrite);
            
            showLoading();
            
            fetch('../ajax/settings/import-custom-fields.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', 'Fields imported successfully');
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

// Refresh list
function refreshList() {
    showLoading();
    setTimeout(() => location.reload(), 500);
}

// Show loading spinner
function showLoading() {
    Swal.fire({
        title: 'Processing...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
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

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleFieldOptions();
    setupPagination();
    
    // Add animation to rows
    document.querySelectorAll('#fieldsTable tbody tr').forEach((row, index) => {
        row.style.animation = `fadeIn 0.3s ease ${index * 0.02}s forwards`;
        row.style.opacity = '0';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>