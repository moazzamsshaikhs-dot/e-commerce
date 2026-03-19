<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'Email Templates';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get all email templates
    $stmt = $db->query("SELECT * FROM email_templates ORDER BY id DESC");
    $templates = $stmt->fetchAll();
    
    // Get template categories
    $categories = [];
    foreach ($templates as $template) {
        $key_parts = explode('_', $template['template_key']);
        $category = $key_parts[0] ?? 'other';
        if (!in_array($category, $categories)) {
            $categories[] = $category;
        }
    }
    
    // Get email settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%'");
    $email_settings = $stmt->fetchAll();
    $settings = [];
    foreach ($email_settings as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Template statistics
    $total_templates = count($templates);
    $active_templates = 0;
    foreach ($templates as $t) {
        if ($t['is_active']) $active_templates++;
    }
    
} catch(PDOException $e) {
    $error = 'Error loading templates: ' . $e->getMessage();
    $templates = [];
    $categories = [];
    $settings = [];
    $total_templates = 0;
    $active_templates = 0;
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
    gap: 1.5rem;
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

/* Info Cards */
.info-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 1.5rem;
    transition: var(--transition);
    height: 100%;
    animation: slideIn 0.5s ease;
    animation-delay: 0.15s;
    animation-fill-mode: both;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.info-card .card-title {
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-card .card-title i {
    color: var(--primary);
}

.info-card .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px dashed var(--gray-200);
}

.info-card .info-item:last-child {
    border-bottom: none;
}

.info-card .info-label {
    color: var(--gray-600);
    font-size: 0.9rem;
}

.info-card .info-value {
    font-weight: 600;
    color: var(--gray-800);
}

/* Test Email Card */
.test-email-card {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(58, 12, 163, 0.05));
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-xl);
    padding: 1.5rem;
    transition: var(--transition);
    height: 100%;
    animation: slideIn 0.5s ease;
    animation-delay: 0.2s;
    animation-fill-mode: both;
}

.test-email-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-lg);
}

.test-email-card .card-title {
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.test-email-card .input-group {
    border-radius: var(--border-radius-lg);
    overflow: hidden;
}

.test-email-card .form-control {
    border: 2px solid var(--gray-200);
    border-right: none;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
}

.test-email-card .form-control:focus {
    border-color: var(--primary);
    box-shadow: none;
}

.test-email-card .btn-send {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 0 1.5rem;
    font-weight: 600;
    transition: var(--transition);
}

.test-email-card .btn-send:hover {
    transform: translateX(5px);
    box-shadow: -5px 0 15px rgba(67, 97, 238, 0.3);
}

/* Quick Actions Card */
.actions-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 1.5rem;
    transition: var(--transition);
    height: 100%;
    animation: slideIn 0.5s ease;
    animation-delay: 0.25s;
    animation-fill-mode: both;
}

.actions-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.actions-card .card-title {
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius-lg);
    transition: var(--transition);
    border: 1px solid var(--gray-200);
    background: white;
    width: 100%;
    text-align: left;
    margin-bottom: 0.5rem;
    cursor: pointer;
}

.action-btn:last-child {
    margin-bottom: 0;
}

.action-btn:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
    transform: translateX(5px);
    border-color: var(--primary);
}

.action-btn i {
    width: 32px;
    height: 32px;
    border-radius: var(--border-radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
}

.action-btn .content {
    flex: 1;
}

.action-btn .title {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.action-btn .subtitle {
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* Category Filter */
.category-filter {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    animation: slideIn 0.5s ease;
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filter-btn {
    padding: 0.6rem 1.2rem;
    border-radius: var(--border-radius-full);
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
    border: 2px solid var(--gray-200);
    background: white;
    color: var(--gray-700);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

.filter-btn.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

.filter-btn i {
    font-size: 0.8rem;
}

/* Templates Grid */
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.template-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    transition: var(--transition);
    animation: scaleIn 0.5s ease;
    animation-fill-mode: both;
    position: relative;
}

.template-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary);
}

.template-card .card-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.template-card .template-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.template-card .template-info {
    flex: 1;
    margin-left: 1rem;
}

.template-card .template-name {
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.template-card .template-key {
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
    color: var(--gray-500);
}

.template-card .template-badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.7rem;
    font-weight: 600;
    background: var(--gray-100);
    color: var(--gray-600);
}

.template-card .card-body {
    padding: 1.5rem;
}

.template-card .subject-preview {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    border-left: 3px solid var(--primary);
}

.template-card .subject-label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.template-card .subject-text {
    font-weight: 500;
    color: var(--gray-800);
}

.template-card .variables-section {
    margin-bottom: 1rem;
}

.template-card .variables-label {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.template-card .variable-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: rgba(76, 201, 240, 0.1);
    border-radius: var(--border-radius-sm);
    font-size: 0.7rem;
    color: var(--info-dark);
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
    border: 1px solid rgba(76, 201, 240, 0.3);
}

.template-card .card-footer {
    padding: 1rem 1.5rem;
    background: var(--gray-100);
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.template-card .date-info {
    font-size: 0.8rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.template-card .status-toggle {
    position: relative;
}

.template-card .form-switch {
    padding-left: 2.5em;
}

.template-card .form-switch .form-check-input {
    width: 2.5em;
    height: 1.5em;
    cursor: pointer;
}

.template-card .form-switch .form-check-input:checked {
    background-color: var(--success);
    border-color: var(--success);
}

/* Action Buttons Group */
.btn-group-custom {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.btn-group-custom .btn {
    flex: 1;
    padding: 0.6rem;
    border-radius: var(--border-radius-md);
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    border: 1px solid var(--gray-200);
    background: white;
    color: var(--gray-700);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
}

.btn-group-custom .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.btn-group-custom .btn-primary {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

.btn-group-custom .btn-primary:hover {
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.4);
}

.btn-group-custom .btn-info {
    background: linear-gradient(135deg, var(--info), var(--info-dark));
    color: white;
    border-color: transparent;
}

.btn-group-custom .btn-info:hover {
    box-shadow: 0 4px 15px rgba(76, 201, 240, 0.4);
}

.btn-group-custom .btn-danger {
    background: linear-gradient(135deg, var(--danger), var(--danger-dark));
    color: white;
    border-color: transparent;
}

.btn-group-custom .btn-danger:hover {
    box-shadow: 0 4px 15px rgba(239, 71, 111, 0.4);
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

/* Variables Table */
.variables-table {
    width: 100%;
    border-collapse: collapse;
}

.variables-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    background: var(--gray-100);
    border-bottom: 2px solid var(--gray-300);
    font-size: 0.85rem;
    text-transform: uppercase;
}

.variables-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-600);
}

.variables-table code {
    background: var(--gray-100);
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius-sm);
    color: var(--primary);
    font-weight: 600;
}

.variables-table tr:hover {
    background: var(--gray-100);
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
    
    .templates-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-buttons {
        justify-content: center;
    }
    
    .btn-group-custom {
        flex-direction: column;
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
                        <i class="fas fa-envelope-open-text"></i>
                        Email Templates
                    </h1>
                    <p class="mb-0">Create and manage email templates for your application</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group justify-content-md-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                            <i class="fas fa-plus-circle me-2"></i>New Template
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $total_templates; ?></div>
                        <div class="stat-label">Total Templates</div>
                        <div class="stat-footer">
                            <i class="fas fa-layer-group me-1"></i> Available templates
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $active_templates; ?></div>
                        <div class="stat-label">Active Templates</div>
                        <div class="stat-footer">
                            <i class="fas fa-percentage me-1"></i> 
                            <?php echo $total_templates > 0 ? round(($active_templates / $total_templates) * 100) : 0; ?>% active
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($categories); ?></div>
                        <div class="stat-label">Categories</div>
                        <div class="stat-footer">
                            <i class="fas fa-folder me-1"></i> Template groups
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuration Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="info-card">
                    <div class="card-title">
                        <i class="fas fa-cog text-primary"></i>
                        Email Configuration
                    </div>
                    <div class="info-item">
                        <span class="info-label">SMTP Status:</span>
                        <span class="info-value">
                            <span class="badge bg-<?php echo !empty($settings['smtp_host']) ? 'success' : 'danger'; ?>">
                                <?php echo !empty($settings['smtp_host']) ? 'Configured' : 'Not Configured'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">From Email:</span>
                        <span class="info-value"><?php echo $settings['from_email'] ?? 'Not set'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">From Name:</span>
                        <span class="info-value"><?php echo $settings['from_name'] ?? 'Not set'; ?></span>
                    </div>
                    <a href="settings-group.php?group=email" class="btn btn-outline-primary w-100 mt-3">
                        <i class="fas fa-cog me-2"></i>Configure Email
                    </a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="test-email-card">
                    <div class="card-title">
                        <i class="fas fa-paper-plane text-success"></i>
                        Test Email
                    </div>
                    <p class="text-muted small mb-3">Send a test email to verify your configuration</p>
                    <div class="input-group">
                        <input type="email" class="form-control" id="testEmail" 
                               placeholder="test@example.com" value="<?php echo $_SESSION['email'] ?? ''; ?>">
                        <button class="btn-send" onclick="sendTestEmail()">
                            <i class="fas fa-paper-plane me-2"></i>Send
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="actions-card">
                    <div class="card-title">
                        <i class="fas fa-bolt text-warning"></i>
                        Quick Actions
                    </div>
                    <button class="action-btn" onclick="exportTemplates()">
                        <i class="fas fa-download" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));"></i>
                        <div class="content">
                            <div class="title">Export Templates</div>
                            <div class="subtitle">Download all templates as JSON</div>
                        </div>
                    </button>
                    <button class="action-btn" onclick="resetTemplates()">
                        <i class="fas fa-undo" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));"></i>
                        <div class="content">
                            <div class="title">Reset to Default</div>
                            <div class="subtitle">Restore default templates</div>
                        </div>
                    </button>
                    <button class="action-btn" data-bs-toggle="modal" data-bs-target="#variablesModal">
                        <i class="fas fa-code" style="background: linear-gradient(135deg, var(--info), var(--info-dark));"></i>
                        <div class="content">
                            <div class="title">View Variables</div>
                            <div class="subtitle">See available template variables</div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Category Filter -->
        <div class="category-filter">
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterTemplates('all')">
                    <i class="fas fa-globe"></i>
                    All Templates
                </button>
                <?php foreach($categories as $category): ?>
                <button class="filter-btn" onclick="filterTemplates('<?php echo $category; ?>')">
                    <i class="fas fa-folder"></i>
                    <?php echo ucfirst($category); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Templates Grid -->
        <?php if (empty($templates)): ?>
        <div class="empty-state">
            <i class="fas fa-envelope-open-text"></i>
            <h5>No Email Templates</h5>
            <p class="text-muted">Create your first email template to get started</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                <i class="fas fa-plus-circle me-2"></i>Create First Template
            </button>
        </div>
        <?php else: ?>
        <div class="templates-grid" id="templatesContainer">
            <?php foreach($templates as $index => $template): 
                $category = explode('_', $template['template_key'])[0] ?? 'other';
                $variables = $template['variables'] ? json_decode($template['variables'], true) : [];
                
                // Determine icon color based on category
                $icon_colors = [
                    'welcome' => 'success',
                    'order' => 'primary',
                    'password' => 'warning',
                    'invoice' => 'info',
                    'notification' => 'secondary'
                ];
                $icon_color = $icon_colors[$category] ?? 'primary';
            ?>
            <div class="template-card" data-category="<?php echo $category; ?>" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <div class="template-icon" style="background: linear-gradient(135deg, var(--<?php echo $icon_color; ?>), var(--<?php echo $icon_color; ?>-dark));">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="template-info">
                            <div class="template-name">
                                <?php echo htmlspecialchars($template['name']); ?>
                                <span class="template-badge"><?php echo ucfirst($category); ?></span>
                            </div>
                            <div class="template-key"><?php echo $template['template_key']; ?></div>
                        </div>
                    </div>
                    <div class="status-toggle">
                        <div class="form-check form-switch">
                            <input class="form-check-input template-toggle" 
                                   type="checkbox" 
                                   data-id="<?php echo $template['id']; ?>"
                                   <?php echo $template['is_active'] ? 'checked' : ''; ?>
                                   onchange="toggleTemplate(this, <?php echo $template['id']; ?>)">
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="subject-preview">
                        <div class="subject-label">
                            <i class="fas fa-heading me-1"></i>
                            Subject Line
                        </div>
                        <div class="subject-text"><?php echo htmlspecialchars($template['subject']); ?></div>
                    </div>
                    
                    <?php if (!empty($variables)): ?>
                    <div class="variables-section">
                        <div class="variables-label">
                            <i class="fas fa-code"></i>
                            Available Variables
                        </div>
                        <div>
                            <?php foreach($variables as $var): ?>
                            <span class="variable-badge"><?php echo htmlspecialchars($var); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="btn-group-custom">
                        <button class="btn btn-primary" onclick="editTemplate(<?php echo $template['id']; ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-info" onclick="previewTemplate(<?php echo $template['id']; ?>)">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button class="btn btn-danger" onclick="deleteTemplate(<?php echo $template['id']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <div class="card-footer">
                    <span class="date-info">
                        <i class="far fa-clock"></i>
                        Updated: <?php echo date('M d, Y', strtotime($template['updated_at'])); ?>
                    </span>
                    <?php if (!empty($template['created_at'])): ?>
                    <span class="date-info">
                        <i class="far fa-calendar"></i>
                        Created: <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Create New Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addTemplateForm">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-key text-primary"></i>
                                Template Key <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="template_key" required 
                                   pattern="[a-z0-9_]+" placeholder="e.g., welcome_email, order_confirmation">
                            <div class="form-text">Lowercase with underscores only</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-tag text-success"></i>
                                Template Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="e.g., Welcome Email, Order Confirmation">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">
                                <i class="fas fa-heading text-warning"></i>
                                Email Subject <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="subject" required 
                                   placeholder="e.g., Welcome to {{site_name}}">
                            <div class="form-text">Use {{variable}} for dynamic content</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">
                                <i class="fas fa-envelope-open-text text-info"></i>
                                Email Content <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" name="content" rows="12" required 
                                      placeholder="HTML email content with variables like {{user_name}}"></textarea>
                            <div class="form-text">HTML format supported. Use {{variable}} for dynamic content</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">
                                <i class="fas fa-code text-primary"></i>
                                Variables (JSON array)
                            </label>
                            <textarea class="form-control" name="variables" rows="4" 
                                      placeholder='["site_name", "user_name", "user_email"]'></textarea>
                            <div class="form-text">List all variables used in the template</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">
                    <i class="fas fa-save me-2"></i>Save Template
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editTemplateContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Variables Modal -->
<div class="modal fade" id="variablesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-code me-2"></i>
                    Available Template Variables
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="variables-table">
                        <thead>
                            <tr>
                                <th>Variable</th>
                                <th>Description</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>{{site_name}}</code></td>
                                <td>Website name from settings</td>
                                <td>ShopEase Pro</td>
                            </tr>
                            <tr>
                                <td><code>{{site_url}}</code></td>
                                <td>Website URL</td>
                                <td>https://example.com</td>
                            </tr>
                            <tr>
                                <td><code>{{user_name}}</code></td>
                                <td>User's full name</td>
                                <td>John Doe</td>
                            </tr>
                            <tr>
                                <td><code>{{user_email}}</code></td>
                                <td>User's email address</td>
                                <td>john@example.com</td>
                            </tr>
                            <tr>
                                <td><code>{{order_number}}</code></td>
                                <td>Order number</td>
                                <td>ORD-2024-001</td>
                            </tr>
                            <tr>
                                <td><code>{{order_total}}</code></td>
                                <td>Order total amount</td>
                                <td>$99.99</td>
                            </tr>
                            <tr>
                                <td><code>{{reset_link}}</code></td>
                                <td>Password reset link</td>
                                <td>https://example.com/reset-password?token=xyz</td>
                            </tr>
                            <tr>
                                <td><code>{{current_date}}</code></td>
                                <td>Current date</td>
                                <td>January 15, 2024</td>
                            </tr>
                            <tr>
                                <td><code>{{current_year}}</code></td>
                                <td>Current year</td>
                                <td>2024</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Use double curly braces <code>{{variable_name}}</code> in your templates
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentEditTemplateId = null;

// Filter templates by category
function filterTemplates(category) {
    const cards = document.querySelectorAll('.template-card');
    const buttons = document.querySelectorAll('.filter-btn');
    
    buttons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.includes(category === 'all' ? 'All Templates' : category)) {
            btn.classList.add('active');
        }
    });
    
    cards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Toggle template active status
function toggleTemplate(checkbox, templateId) {
    const isActive = checkbox.checked ? 1 : 0;
    
    fetch('ajax/toggle-email-template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ template_id: templateId, is_active: isActive })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            checkbox.checked = !checkbox.checked;
            showToast('error', data.message);
        } else {
            showToast('success', 'Template status updated');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        checkbox.checked = !checkbox.checked;
        showToast('error', 'An error occurred');
    });
}

// Edit template
function editTemplate(templateId) {
    currentEditTemplateId = templateId;
    
    const modalContent = document.getElementById('editTemplateContent');
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    $('#editTemplateModal').modal('show');
    
    fetch(`ajax/get-email-template.php?id=${templateId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const template = data.template;
            const variables = template.variables ? JSON.parse(template.variables) : [];
            
            const variablesHtml = variables.map(v => `<span class="variable-badge">${v}</span>`).join('');
            
            modalContent.innerHTML = `
                <form id="editTemplateForm">
                    <input type="hidden" name="template_id" value="${template.id}">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-key text-primary"></i>
                                Template Key
                            </label>
                            <input type="text" class="form-control" value="${template.template_key}" readonly>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-tag text-success"></i>
                                Template Name
                            </label>
                            <input type="text" class="form-control" name="name" value="${template.name}" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">
                                <i class="fas fa-heading text-warning"></i>
                                Email Subject
                            </label>
                            <input type="text" class="form-control" name="subject" value="${template.subject}" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">
                                <i class="fas fa-envelope-open-text text-info"></i>
                                Email Content
                            </label>
                            <textarea class="form-control" name="content" rows="15" required>${template.content.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-code text-primary"></i>
                                Variables (JSON)
                            </label>
                            <textarea class="form-control" name="variables" rows="5">${template.variables || ''}</textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-list-ul text-info"></i>
                                Variable Preview
                            </label>
                            <div class="border rounded p-3 bg-light" style="min-height: 120px;">
                                ${variablesHtml || '<span class="text-muted">No variables defined</span>'}
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <div>
                            <button type="button" class="btn btn-info me-2" onclick="testTemplate(${template.id})">
                                <i class="fas fa-paper-plane me-2"></i>Send Test
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            `;
            
            // Add form submission handler
            document.getElementById('editTemplateForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveTemplateChanges();
            });
        } else {
            modalContent.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalContent.innerHTML = '<div class="alert alert-danger">Failed to load template.</div>';
    });
}

// Save new template
function saveTemplate() {
    const form = document.getElementById('addTemplateForm');
    const formData = new FormData(form);
    
    // Validate variables JSON
    const variables = formData.get('variables');
    if (variables) {
        try {
            JSON.parse(variables);
        } catch (e) {
            Swal.fire('Error!', 'Variables must be valid JSON.', 'error');
            return;
        }
    }
    
    Swal.fire({
        title: 'Saving...',
        text: 'Please wait while we save your template.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('ajax/save-email-template.php', {
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
            }).then(() => {
                $('#addTemplateModal').modal('hide');
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

// Save template changes
function saveTemplateChanges() {
    const form = document.getElementById('editTemplateForm');
    const formData = new FormData(form);
    
    // Validate variables JSON
    const variables = formData.get('variables');
    if (variables) {
        try {
            JSON.parse(variables);
        } catch (e) {
            Swal.fire('Error!', 'Variables must be valid JSON.', 'error');
            return;
        }
    }
    
    Swal.fire({
        title: 'Saving...',
        text: 'Please wait while we save your changes.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('ajax/save-email-template.php', {
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
            }).then(() => {
                $('#editTemplateModal').modal('hide');
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

// Preview template
function previewTemplate(templateId) {
    window.open(`preview-email.php?id=${templateId}`, '_blank');
}

// Test template
function testTemplate(templateId) {
    Swal.fire({
        title: 'Send Test Email',
        input: 'email',
        inputLabel: 'Enter email address',
        inputPlaceholder: 'test@example.com',
        inputValue: '<?php echo $_SESSION['email'] ?? ''; ?>',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Send Test',
        cancelButtonText: 'Cancel',
        preConfirm: (email) => {
            if (!email || !validateEmail(email)) {
                Swal.showValidationMessage('Please enter a valid email address');
                return false;
            }
            return email;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sending...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('ajax/send-test-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ template_id: templateId, email: result.value })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
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

// Send test email from configuration
function sendTestEmail() {
    const email = document.getElementById('testEmail').value;
    
    if (!email) {
        Swal.fire('Error!', 'Please enter an email address.', 'error');
        return;
    }
    
    if (!validateEmail(email)) {
        Swal.fire('Error!', 'Please enter a valid email address.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Sending Test Email...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('ajax/send-test-config-email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire('Success!', data.message, 'success');
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

// Delete template
function deleteTemplate(templateId) {
    Swal.fire({
        title: 'Delete Template',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Are you sure you want to delete this template?</p>
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
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('ajax/delete-email-template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ template_id: templateId })
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

// Export templates
function exportTemplates() {
    Swal.fire({
        title: 'Export Templates',
        text: 'Choose export format',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: ' JSON',
        denyButtonText: ' CSV',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3085d6',
        denyButtonColor: '#1cc88a'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open('ajax/export-email-templates.php?format=json', '_blank');
            showToast('success', 'Exporting as JSON...');
        } else if (result.isDenied) {
            window.open('ajax/export-email-templates.php?format=csv', '_blank');
            showToast('success', 'Exporting as CSV...');
        }
    });
}

// Reset templates to default
function resetTemplates() {
    Swal.fire({
        title: 'Reset Templates',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>Reset all templates to default values?</p>
                <p class="text-muted small">This will overwrite any custom templates you have created.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
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
            
            fetch('ajax/reset-email-templates.php')
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

// Validate email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
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
    // Add animation to template cards
    document.querySelectorAll('.template-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
    });
    
    // Initialize filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const category = this.textContent.includes('All') ? 'all' : 
                            this.textContent.trim().toLowerCase();
            filterTemplates(category);
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>