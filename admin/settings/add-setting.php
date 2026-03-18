<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$group = isset($_GET['group']) ? $_GET['group'] : 'general';

try {
    $db = getDB();
    
    // Get groups for dropdown
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $groups = $stmt->fetchAll();
    
    // Get categories
    $stmt = $db->query("SELECT DISTINCT category FROM settings WHERE category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get existing setting keys
    $stmt = $db->query("SELECT setting_key FROM settings");
    $existing_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading data: ' . $e->getMessage();
    redirect('settings.php');
}

$page_title = 'Add New Setting';
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

/* Form Card */
.form-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.form-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.form-card .card-header h5 i {
    color: var(--primary);
}

.form-card .card-body {
    padding: 2rem;
}

/* Form Sections */
.form-section {
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--gray-200);
}

.form-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title i {
    width: 32px;
    height: 32px;
    border-radius: var(--border-radius-md);
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

/* Form Labels */
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

.form-label .required {
    color: var(--danger);
    font-size: 1.2rem;
    line-height: 1;
}

/* Form Controls */
.form-control, .form-select {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: var(--transition);
    background: white;
}

.form-control:hover, .form-select:hover {
    border-color: var(--primary-light);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.form-control.is-invalid {
    border-color: var(--danger);
}

.form-control.is-invalid:focus {
    box-shadow: 0 0 0 4px rgba(239, 71, 111, 0.1);
}

.form-text {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin-top: 0.5rem;
    padding-left: 0.5rem;
    border-left: 2px solid var(--gray-300);
}

/* Input Groups */
.input-group {
    border-radius: var(--border-radius-lg);
    overflow: hidden;
}

.input-group .input-group-text {
    background: var(--gray-100);
    border: 2px solid var(--gray-200);
    border-right: none;
    color: var(--gray-600);
    padding: 0.75rem 1rem;
}

.input-group .form-control {
    border-left: none;
}

.input-group .form-control:focus + .input-group-text {
    border-color: var(--primary);
}

/* Checkbox and Radio */
.form-check {
    margin-bottom: 0.75rem;
}

.form-check-input {
    width: 1.2rem;
    height: 1.2rem;
    border: 2px solid var(--gray-300);
    transition: var(--transition);
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.form-check-input:focus {
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    border-color: var(--primary);
}

.form-check-label {
    color: var(--gray-700);
    font-weight: 500;
    margin-left: 0.5rem;
    cursor: pointer;
}

/* Preview Card */
.preview-card {
    background: var(--gray-100);
    border: 2px dashed var(--gray-300);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    transition: var(--transition);
}

.preview-card:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--gray-100), white);
}

.preview-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.preview-content {
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
}

/* Existing Keys Card */
.keys-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
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
    color: var(--info);
}

.keys-card .card-body {
    padding: 2rem;
}

/* Key Items */
.key-item {
    padding: 0.75rem 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
    margin-bottom: 0.75rem;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.key-item:hover {
    transform: translateX(5px);
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--gray-100), white);
}

.key-item i {
    color: var(--primary);
    font-size: 1rem;
    opacity: 0.5;
}

.key-item code {
    font-family: 'Courier New', monospace;
    color: var(--primary-dark);
    font-weight: 600;
    font-size: 0.9rem;
}

.key-item .badge {
    margin-left: auto;
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
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

.btn-secondary {
    background: var(--gray-600);
    color: white;
    box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
}

.btn-secondary:hover {
    background: var(--gray-700);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4);
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

.btn-outline-secondary {
    background: transparent;
    border: 2px solid var(--gray-300);
    color: var(--gray-700);
}

.btn-outline-secondary:hover {
    background: var(--gray-100);
    border-color: var(--gray-400);
    transform: translateY(-2px);
}

.btn-icon {
    padding: 0.75rem;
    border-radius: var(--border-radius-md);
}

.btn-icon i {
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
    
    .form-card .card-body {
        padding: 1.5rem;
    }
    
    .keys-card .card-body {
        padding: 1.5rem;
    }
    
    .section-title {
        font-size: 1rem;
    }
    
    .btn {
        padding: 0.6rem 1.2rem;
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
                    <h1><i class="fas fa-plus-circle me-3"></i>Add New Setting</h1>
                    <p class="mb-0">Create a new system configuration setting</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group justify-content-md-end">
                        <a href="settings.php" class="btn btn-outline-primary">
                            <i class="fas fa-cogs me-2"></i>Settings Dashboard
                        </a>
                        <a href="settings-group.php?group=<?php echo $group; ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Group
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Setting Form -->
        <div class="form-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-sliders-h"></i>
                    Setting Configuration
                </h5>
                <span class="badge bg-primary">New Setting</span>
            </div>
            <div class="card-body">
                <form id="addSettingForm" method="POST" action="<?php echo SITE_URL; ?>admin/settings/ajax/add-setting.php">
                    <!-- Basic Information Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Basic Information
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-key"></i>
                                        Setting Key <span class="required">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-code"></i></span>
                                        <input type="text" 
                                               class="form-control" 
                                               name="setting_key" 
                                               id="settingKey"
                                               required 
                                               pattern="[a-z0-9_]+" 
                                               title="Lowercase letters, numbers, and underscores only"
                                               placeholder="e.g., site_title, max_file_size">
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Use lowercase with underscores. Must be unique.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-tag"></i>
                                        Display Name <span class="required">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-font"></i></span>
                                        <input type="text" 
                                               class="form-control" 
                                               name="display_name" 
                                               id="displayName"
                                               required 
                                               placeholder="e.g., Site Title, Maximum File Size">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-layer-group"></i>
                                        Setting Group <span class="required">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-folder"></i></span>
                                        <select class="form-select" name="group" required>
                                            <option value="">Select Group</option>
                                            <?php foreach($groups as $group_item): ?>
                                            <option value="<?php echo $group_item['slug']; ?>" 
                                                    <?php echo $group_item['slug'] == $group ? 'selected' : ''; ?>>
                                                <?php echo $group_item['name']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-tags"></i>
                                        Category
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-list"></i></span>
                                        <input type="text" class="form-control" name="category" 
                                               list="categoryList" 
                                               placeholder="Enter or select category">
                                    </div>
                                    <datalist id="categoryList">
                                        <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category; ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        Optional: Categorize this setting
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Setting Configuration Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-cog"></i>
                            Setting Configuration
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-database"></i>
                                        Setting Type <span class="required">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-code-branch"></i></span>
                                        <select class="form-select" name="setting_type" required id="settingType"
                                                onchange="toggleTypeOptions()">
                                            <option value="text"> Text</option>
                                            <option value="textarea"> Textarea</option>
                                            <option value="number"> Number</option>
                                            <option value="email"> Email</option>
                                            <option value="password"> Password</option>
                                            <option value="url"> URL</option>
                                            <option value="color"> Color</option>
                                            <option value="boolean"> Boolean (Yes/No)</option>
                                            <option value="select"> Select (Dropdown)</option>
                                            <option value="json"> JSON</option>
                                            <option value="file"> File</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-check-circle"></i>
                                        Default Value
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-undo-alt"></i></span>
                                        <input type="text" class="form-control" name="default_value" 
                                               id="defaultValue" placeholder="Default value for this setting">
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Default value when setting is not configured
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Options for Select type -->
                            <div class="col-12" id="optionsContainer" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-list-ul"></i>
                                        Options (for Select type)
                                    </label>
                                    <textarea class="form-control" name="options" rows="4" 
                                              placeholder='Enter options as JSON:&#10;["option1", "option2", "option3"]&#10;&#10;Or as key-value:&#10;{"key1": "Value 1", "key2": "Value 2"}'></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-code me-1"></i>
                                        Enter as JSON array or object
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-shield-alt"></i>
                                        Validation Rules
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                                        <input type="text" class="form-control" name="validation_rules" 
                                               placeholder="e.g., required|email|max:255">
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Pipe-separated: required, email, url, numeric, min:5, max:100
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-sort-amount-up"></i>
                                        Sort Order
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-sort"></i></span>
                                        <input type="number" class="form-control" name="sort_order" value="0" min="0" step="1">
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Lower numbers appear first
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Options Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-tools"></i>
                            Advanced Options
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-question-circle"></i>
                                        Help Text
                                    </label>
                                    <textarea class="form-control" name="help_text" rows="3" 
                                              placeholder="Help text to show below the setting field"></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Shown to users when editing this setting
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">
                                            <i class="fas fa-sliders-h me-2"></i>
                                            Setting Options
                                        </h6>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="is_required" id="isRequired">
                                            <label class="form-check-label" for="isRequired">
                                                <i class="fas fa-exclamation-circle text-warning me-1"></i>
                                                Required Field
                                            </label>
                                            <div class="form-text ms-4">
                                                Users must provide a value for this setting
                                            </div>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_public" id="isPublic">
                                            <label class="form-check-label" for="isPublic">
                                                <i class="fas fa-globe text-info me-1"></i>
                                                Public Setting
                                            </label>
                                            <div class="form-text ms-4">
                                                Visible to non-admin users
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-eye"></i>
                            Live Preview
                        </div>
                        
                        <div class="preview-card">
                            <div class="preview-title">
                                <i class="fas fa-play"></i>
                                How this setting will appear
                            </div>
                            <div class="preview-content" id="settingPreview">
                                <p class="text-muted mb-0">Select a type to see preview</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="fas fa-undo-alt me-2"></i>Reset Form
                        </button>
                        <div class="btn-group">
                            <a href="settings.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Setting
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Existing Settings Keys -->
        <div class="keys-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-key"></i>
                    Existing Setting Keys
                </h5>
                <span class="badge bg-info"><?php echo count($existing_keys); ?> Keys</span>
            </div>
            <div class="card-body">
                <?php if (empty($existing_keys)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-database fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No settings exist yet. Create your first setting above.</p>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach($existing_keys as $key): ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="key-item" onclick="copyKey('<?php echo $key; ?>')" title="Click to copy">
                            <i class="fas fa-key"></i>
                            <code><?php echo $key; ?></code>
                            <span class="badge bg-light text-muted">exists</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($existing_keys)): ?>
            <div class="card-footer bg-light border-0 text-center py-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Click on any key to copy to clipboard
                </small>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Loading Spinner -->
<div class="spinner-overlay" id="loadingSpinner">
    <div class="spinner"></div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
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
        <button class="btn-close btn-close-white ms-auto" onclick="this.parentElement.remove()"></button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Copy key to clipboard
function copyKey(key) {
    navigator.clipboard.writeText(key).then(() => {
        showToast('success', `Copied "${key}" to clipboard`);
    }).catch(() => {
        showToast('error', 'Failed to copy');
    });
}

// Toggle type-specific options
function toggleTypeOptions() {
    const type = document.getElementById('settingType').value;
    const optionsContainer = document.getElementById('optionsContainer');
    const defaultValue = document.getElementById('defaultValue');
    
    // Show/hide options for select type
    if (type === 'select') {
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
    }
    
    // Update default value placeholder
    const placeholders = {
        'text': 'Enter text value',
        'textarea': 'Enter text content',
        'number': 'Enter number (e.g., 42)',
        'email': 'Enter email (e.g., user@example.com)',
        'password': 'Enter password',
        'url': 'Enter URL (e.g., https://example.com)',
        'color': 'Enter hex color (e.g., #4361ee)',
        'boolean': '1 for Yes, 0 for No',
        'select': 'Select default option',
        'json': 'Enter JSON value',
        'file': 'Enter filename'
    };
    
    defaultValue.placeholder = placeholders[type] || 'Enter default value';
    updatePreview();
}

// Update preview
function updatePreview() {
    const type = document.getElementById('settingType').value;
    const preview = document.getElementById('settingPreview');
    const displayName = document.getElementById('displayName').value || 'Setting Label';
    let html = '';
    
    // Create preview based on type
    switch(type) {
        case 'text':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <input type="text" class="form-control" placeholder="Enter ${displayName.toLowerCase()}">
                </div>
            `;
            break;
        case 'textarea':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <textarea class="form-control" rows="3" placeholder="Enter ${displayName.toLowerCase()}"></textarea>
                </div>
            `;
            break;
        case 'number':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <input type="number" class="form-control" placeholder="Enter number">
                </div>
            `;
            break;
        case 'email':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <input type="email" class="form-control" placeholder="Enter email">
                </div>
            `;
            break;
        case 'password':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <input type="password" class="form-control" placeholder="Enter password">
                </div>
            `;
            break;
        case 'url':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <input type="url" class="form-control" placeholder="https://example.com">
                </div>
            `;
            break;
        case 'color':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <input type="color" class="form-control" value="#4361ee">
                </div>
            `;
            break;
        case 'boolean':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox">
                        <label class="form-check-label">Enable/Disable</label>
                    </div>
                </div>
            `;
            break;
        case 'select':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <select class="form-select">
                        <option>Option 1</option>
                        <option>Option 2</option>
                        <option>Option 3</option>
                    </select>
                </div>
            `;
            break;
        case 'json':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <textarea class="form-control font-monospace" rows="3" placeholder='{"key": "value"}'></textarea>
                </div>
            `;
            break;
        case 'file':
            html = `
                <div class="w-100">
                    <label class="form-label fw-bold">${displayName}</label>
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="No file chosen" readonly>
                        <button class="btn btn-outline-primary" type="button">
                            <i class="fas fa-upload"></i> Choose File
                        </button>
                    </div>
                </div>
            `;
            break;
    }
    
    preview.innerHTML = html;
}

// Reset form
function resetForm() {
    Swal.fire({
        title: 'Reset Form',
        text: 'Clear all form fields?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, reset',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('addSettingForm').reset();
            toggleTypeOptions();
            updatePreview();
            Swal.fire('Reset!', 'Form has been cleared.', 'success');
        }
    });
}

// Validate setting key format
function validateSettingKey(key) {
    return /^[a-z0-9_]+$/.test(key);
}

// Form submission
document.getElementById('addSettingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get form data
    const formData = new FormData(this);
    const settingKey = formData.get('setting_key');
    const existingKeys = <?php echo json_encode($existing_keys); ?>;
    
    // Validate setting key format
    if (!validateSettingKey(settingKey)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Setting Key',
            text: 'Setting key must contain only lowercase letters, numbers, and underscores.',
            confirmButtonColor: '#3085d6'
        });
        return;
    }
    
    // Check if key already exists
    if (existingKeys.includes(settingKey)) {
        Swal.fire({
            icon: 'error',
            title: 'Duplicate Key',
            text: 'Setting key already exists. Please choose a different key.',
            confirmButtonColor: '#3085d6'
        });
        return;
    }
    
    // Validate options for select type
    const settingType = formData.get('setting_type');
    if (settingType === 'select') {
        const options = formData.get('options');
        if (options) {
            try {
                JSON.parse(options);
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid JSON',
                    text: 'Options must be valid JSON format.',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }
        }
    }
    
    // Confirm submission
    Swal.fire({
        title: 'Add Setting',
        text: 'Create new system setting?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, create setting',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = `settings-group.php?group=${data.group}`;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#3085d6'
                    });
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while adding the setting.',
                    confirmButtonColor: '#3085d6'
                });
            });
        }
    });
});

// Real-time validation for setting key
document.getElementById('settingKey').addEventListener('input', function() {
    const key = this.value;
    const isValid = validateSettingKey(key);
    const existingKeys = <?php echo json_encode($existing_keys); ?>;
    
    if (!isValid && key.length > 0) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
    
    // Check duplicate in real-time
    if (existingKeys.includes(key)) {
        this.classList.add('is-invalid');
    }
});

// Event listeners for real-time preview
document.getElementById('settingType').addEventListener('change', toggleTypeOptions);
document.getElementById('displayName').addEventListener('input', updatePreview);
document.querySelectorAll('#addSettingForm input, #addSettingForm select, #addSettingForm textarea').forEach(element => {
    element.addEventListener('input', updatePreview);
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleTypeOptions();
    updatePreview();
    
    // Add animation to key items
    document.querySelectorAll('.key-item').forEach((item, index) => {
        item.style.animation = `slideIn 0.3s ease ${index * 0.05}s forwards`;
        item.style.opacity = '0';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>