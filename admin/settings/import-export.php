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
    
    // Get public settings count
    $stmt = $db->query("SELECT COUNT(*) as count FROM settings WHERE is_public = 1");
    $public_count = $stmt->fetch()['count'];
    
    // Get recent activity count (last 7 days)
    $stmt = $db->query("SELECT COUNT(*) as count FROM import_export_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recent_count = $stmt->fetch()['count'];
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $groups = [];
    $history = [];
    $total_settings = 0;
    $settings_by_group = [];
    $public_count = 0;
    $recent_count = 0;
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
    width: 100%;
    flex: 1;
    margin-left: 280px;
    padding: 2rem;
    background: var(--gray-100);
    transition: var(--transition);
    position: relative;
}

/* ===== RESPONSIVE BREAKPOINTS ===== */
/* Large Desktop (1200px+) */
@media (min-width: 1200px) {
    .main-content {
        margin-left: 280px;
        padding: 2rem;
    }
}

/* Desktop (992px - 1199px) */
@media (min-width: 992px) and (max-width: 1199px) {
    .main-content {
        margin-left: 250px;
        padding: 1.5rem;
    }
}

/* Tablet (768px - 991px) */
@media (min-width: 768px) and (max-width: 991px) {
    .main-content {
        margin-left: 0;
        padding: 1.2rem;
    }
}

/* Mobile (576px - 767px) */
@media (min-width: 576px) and (max-width: 767px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

/* Small Mobile (below 576px) */
@media (max-width: 575px) {
    .main-content {
        margin-left: 0;
        padding: 0.8rem;
    }
}

/* Extra Small Mobile (below 400px) */
@media (max-width: 400px) {
    .main-content {
        padding: 0.5rem;
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

/* Responsive Page Header */
@media (max-width: 767px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .page-header h1 i {
        font-size: 1.5rem;
    }
    
    .page-header p {
        font-size: 0.9rem;
    }
}

@media (max-width: 575px) {
    .page-header {
        padding: 1.2rem;
    }
    
    .page-header h1 {
        font-size: 1.3rem;
    }
}

@media (max-width: 400px) {
    .page-header {
        padding: 1rem;
    }
    
    .page-header h1 {
        font-size: 1.1rem;
    }
    
    .page-header .btn-outline-danger {
        padding: 0.3rem 0.6rem;
        font-size: 0.7rem;
    }
}

/* Stats Row - Grid Layout */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Responsive Stats Row */
@media (max-width: 991px) and (min-width: 576px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}

@media (max-width: 575px) {
    .stats-row {
        grid-template-columns: 1fr;
        gap: 0.8rem;
    }
}

/* Stat Card */
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

/* Responsive Stat Card */
@media (max-width: 1199px) and (min-width: 992px) {
    .stat-card {
        padding: 1.2rem;
        gap: 1rem;
    }
    
    .stat-card .stat-icon {
        width: 60px;
        height: 60px;
        font-size: 1.8rem;
    }
    
    .stat-card .stat-value {
        font-size: 1.6rem;
    }
}

@media (max-width: 991px) and (min-width: 768px) {
    .stat-card {
        padding: 1rem;
        gap: 0.8rem;
    }
    
    .stat-card .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .stat-card .stat-value {
        font-size: 1.4rem;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
    }
}

@media (max-width: 767px) and (min-width: 576px) {
    .stat-card {
        padding: 0.8rem;
        gap: 0.5rem;
        flex-direction: column;
        text-align: center;
    }
    
    .stat-card .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
        margin: 0 auto;
    }
    
    .stat-card .stat-value {
        font-size: 1.2rem;
    }
    
    .stat-card .stat-label {
        font-size: 0.7rem;
    }
    
    .stat-card .stat-footer {
        font-size: 0.65rem;
    }
}

@media (max-width: 575px) {
    .stat-card {
        padding: 0.8rem;
        gap: 0.8rem;
        flex-direction: row;
        text-align: left;
    }
    
    .stat-card .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
    }
    
    .stat-card .stat-value {
        font-size: 1.2rem;
    }
    
    .stat-card .stat-label {
        font-size: 0.7rem;
    }
}

@media (max-width: 400px) {
    .stat-card {
        padding: 0.6rem;
    }
    
    .stat-card .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
    }
    
    .stat-card .stat-value {
        font-size: 1rem;
    }
}

/* Action Cards Row */
.action-cards-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 991px) {
    .action-cards-row {
        gap: 1rem;
    }
}

@media (max-width: 767px) {
    .action-cards-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

/* Action Card */
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

/* Responsive Action Card */
@media (max-width: 1199px) and (min-width: 992px) {
    .action-card .card-header {
        padding: 1.2rem 1.5rem;
    }
    
    .action-card .card-body {
        padding: 1.5rem;
    }
}

@media (max-width: 991px) and (min-width: 768px) {
    .action-card .card-header {
        padding: 1rem 1.2rem;
    }
    
    .action-card .card-body {
        padding: 1.2rem;
    }
    
    .action-card .card-title {
        font-size: 1.1rem;
    }
    
    .action-card .card-subtitle {
        font-size: 0.75rem;
    }
}

@media (max-width: 767px) {
    .action-card .card-header {
        padding: 1rem;
        flex-direction: column;
        text-align: center;
    }
    
    .action-card .card-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
        margin-bottom: 0.5rem;
    }
    
    .action-card .card-body {
        padding: 1rem;
    }
    
    .action-card .card-title {
        font-size: 1rem;
    }
}

@media (max-width: 575px) {
    .action-card .card-header {
        padding: 0.8rem 1rem;
    }
    
    .action-card .card-body {
        padding: 1rem;
    }
}

@media (max-width: 400px) {
    .action-card .card-header {
        padding: 0.6rem 0.8rem;
    }
    
    .action-card .card-body {
        padding: 0.8rem;
    }
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

/* Responsive Form Elements */
@media (max-width: 991px) {
    .form-control, .form-select {
        padding: 0.6rem 0.8rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 767px) {
    .form-label {
        font-size: 0.85rem;
        margin-bottom: 0.3rem;
    }
    
    .form-label i {
        width: 16px;
        font-size: 0.9rem;
    }
    
    .form-control, .form-select {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .form-text {
        font-size: 0.75rem;
    }
}

@media (max-width: 575px) {
    .form-control, .form-select {
        padding: 0.45rem 0.7rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 400px) {
    .form-control, .form-select {
        padding: 0.4rem 0.6rem;
        font-size: 0.75rem;
    }
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

/* Responsive Settings Selector */
@media (max-width: 767px) {
    .settings-selector {
        max-height: 200px;
    }
    
    .settings-selector .setting-item {
        padding: 0.4rem 0.8rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 575px) {
    .settings-selector {
        max-height: 180px;
    }
    
    .settings-selector .setting-item {
        padding: 0.35rem 0.7rem;
        font-size: 0.8rem;
    }
    
    .settings-selector .setting-item .form-check-input {
        width: 1rem;
        height: 1rem;
    }
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

/* Responsive Option Card */
@media (max-width: 767px) {
    .option-card {
        padding: 0.8rem;
    }
    
    .option-card .form-check-label {
        font-size: 0.85rem;
    }
}

@media (max-width: 575px) {
    .option-card {
        padding: 0.7rem;
    }
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

/* Responsive History Card */
@media (max-width: 991px) {
    .history-card .card-header {
        padding: 1.2rem 1.5rem;
    }
}

@media (max-width: 767px) {
    .history-card .card-header {
        padding: 1rem 1.2rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .history-card .card-header h5 {
        font-size: 1rem;
    }
}

@media (max-width: 575px) {
    .history-card .card-header {
        padding: 0.8rem 1rem;
    }
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

/* Responsive History Table */
@media (max-width: 991px) {
    .history-table th,
    .history-table td {
        padding: 0.75rem 1rem;
    }
}

@media (max-width: 767px) {
    .history-table {
        min-width: 800px;
    }
    
    .history-table th,
    .history-table td {
        padding: 0.6rem 0.8rem;
        font-size: 0.8rem;
        white-space: nowrap;
    }
}

@media (max-width: 575px) {
    .history-table {
        min-width: 700px;
    }
    
    .history-table th,
    .history-table td {
        padding: 0.5rem 0.7rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 400px) {
    .history-table th,
    .history-table td {
        padding: 0.4rem 0.6rem;
        font-size: 0.7rem;
    }
}

/* Table Responsive Wrapper */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
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

/* Responsive Badges */
@media (max-width: 767px) {
    .type-badge, .status-badge {
        padding: 0.25rem 0.6rem;
        font-size: 0.7rem;
    }
    
    .type-badge i, .status-badge i {
        font-size: 0.55rem;
    }
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

/* Responsive Action Button */
@media (max-width: 991px) {
    .btn-action {
        padding: 0.8rem 1.5rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 767px) {
    .btn-action {
        padding: 0.7rem 1.2rem;
        font-size: 0.85rem;
    }
    
    .btn-action i {
        font-size: 0.9rem;
    }
}

@media (max-width: 575px) {
    .btn-action {
        padding: 0.6rem 1rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 400px) {
    .btn-action {
        padding: 0.5rem 0.8rem;
        font-size: 0.75rem;
    }
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

/* Responsive Empty State */
@media (max-width: 767px) {
    .empty-state {
        padding: 3rem 1.5rem;
    }
    
    .empty-state i {
        font-size: 3rem;
    }
    
    .empty-state h5 {
        font-size: 1.1rem;
    }
}

@media (max-width: 575px) {
    .empty-state {
        padding: 2rem 1rem;
    }
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

/* Responsive Modal */
@media (max-width: 991px) {
    .modal-dialog {
        max-width: 95%;
        margin: 1rem auto;
    }
    
    .modal-dialog.modal-xl {
        max-width: 95%;
    }
}

@media (max-width: 767px) {
    .modal-header {
        padding: 1rem 1.2rem;
    }
    
    .modal-header .modal-title {
        font-size: 1rem;
    }
    
    .modal-body {
        padding: 1.2rem;
    }
    
    .modal-footer {
        padding: 1rem 1.2rem;
    }
}

@media (max-width: 575px) {
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .modal-header {
        padding: 0.8rem 1rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-footer {
        padding: 0.8rem 1rem;
    }
    
    .modal-footer .btn {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }
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

/* Responsive Preview Table */
@media (max-width: 767px) {
    .preview-table {
        font-size: 0.8rem;
    }
    
    .preview-table th,
    .preview-table td {
        padding: 0.5rem 0.75rem;
    }
    
    .preview-table code {
        font-size: 0.75rem;
    }
}

@media (max-width: 575px) {
    .preview-table {
        font-size: 0.7rem;
    }
    
    .preview-table th,
    .preview-table td {
        padding: 0.4rem 0.6rem;
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

/* Responsive Toast */
@media (max-width: 767px) {
    .toast-notification {
        padding: 0.8rem 1.2rem;
        min-width: 250px;
    }
}

@media (max-width: 575px) {
    .toast-notification {
        top: 10px;
        right: 10px;
        left: 10px;
        min-width: auto;
        width: auto;
    }
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

/* Sidebar Toggle Button */
.sidebar-toggle {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-gradient);
    color: white;
    border: none;
    box-shadow: var(--shadow-lg);
    cursor: pointer;
    z-index: 1000;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    transition: var(--transition);
}

.sidebar-toggle:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-xl);
}

@media (max-width: 991px) {
    .sidebar-toggle {
        display: flex;
    }
}

@media (max-width: 575px) {
    .sidebar-toggle {
        width: 45px;
        height: 45px;
        font-size: 1rem;
        bottom: 15px;
        right: 15px;
    }
}

/* Sidebar */
.sidebar {
    transition: transform 0.3s ease;
}

@media (max-width: 991px) {
    .sidebar {
        transform: translateX(-100%);
        position: fixed;
        z-index: 1050;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
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

/* Print Styles */
@media print {
    .sidebar,
    .sidebar-toggle,
    .btn-action,
    .btn-outline-primary,
    .btn-outline-danger,
    .modal,
    .no-print {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
        padding: 0;
    }
    
    .stat-card {
        break-inside: avoid;
        border: 1px solid #ddd;
        box-shadow: none;
    }
    
    .history-table {
        break-inside: auto;
    }
    
    .history-table tr {
        break-inside: avoid;
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
    
    <!-- Sidebar Toggle Button for Mobile -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
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
                <div class="col-md-6 text-end">
                    <button class="btn btn-outline-danger" onclick="clearAllHistory()">
                        <i class="fas fa-trash-alt me-2"></i>Clear History
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="stats-row">
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
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                    <i class="fas fa-globe"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $public_count; ?></div>
                    <div class="stat-label">Public Settings</div>
                    <div class="stat-footer">
                        <i class="fas fa-eye me-1"></i> Visible to all users
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $recent_count; ?></div>
                    <div class="stat-label">Recent Activities</div>
                    <div class="stat-footer">
                        <i class="fas fa-clock me-1"></i> Last 7 days
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Export & Import Cards -->
        <div class="action-cards-row">
            <!-- Export Card -->
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
                            <select class="form-select" name="format" id="exportFormat">
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
                            <select class="form-select" name="group" id="exportGroup">
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
                            <div class="settings-selector" id="settingsList">
                                <!-- Will be loaded dynamically -->
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <span class="ms-2">Loading settings...</span>
                                </div>
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
            
            <!-- Import Card -->
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
                                   accept=".json,.csv,.xml,.zip" required onchange="validateImportFile(this)">
                            <div class="form-text" id="fileValidationMessage">
                                Supported formats: JSON, CSV, XML, ZIP
                            </div>
                        </div>
                        
                        <!-- Import Mode -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-code-branch"></i>
                                Import Mode
                            </label>
                            <select class="form-select" name="import_mode" id="importMode">
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
                            <select class="form-select" name="conflict_resolution" id="conflictResolution">
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
        
        <!-- Recent Activities -->
        <div class="history-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-history"></i>
                    Recent Import/Export Activities
                </h5>
                <div>
                    <button class="btn btn-outline-danger btn-sm me-2" onclick="clearHistory()">
                        <i class="fas fa-trash me-2"></i>Clear
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshHistory()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
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
                                <th>Actions</th>
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
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteHistoryItem(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                <button type="button" class="btn btn-primary" onclick="confirmImport()" id="confirmImportBtn">
                    <i class="fas fa-check me-2"></i>Confirm Import
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
<script>
let currentImportData = null;

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

// Toggle export options
function toggleExportOptions() {
    const scope = document.getElementById('exportScope').value;
    const groupSelect = document.getElementById('groupSelect');
    const settingsSelect = document.getElementById('settingsSelect');
    
    groupSelect.style.display = scope === 'group' ? 'block' : 'none';
    
    if (scope === 'selected') {
        settingsSelect.style.display = 'block';
        loadSettingsForExport();
    } else {
        settingsSelect.style.display = 'none';
    }
}

// Load settings for export selection
function loadSettingsForExport() {
    const settingsList = document.getElementById('settingsList');
    
    fetch('ajax/get-settings.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '';
            let currentGroup = '';
            
            data.settings.forEach(setting => {
                if (currentGroup !== setting.group) {
                    currentGroup = setting.group;
                    html += `<div class="setting-item bg-light fw-bold">${currentGroup.charAt(0).toUpperCase() + currentGroup.slice(1)}</div>`;
                }
                
                html += `
                    <div class="setting-item">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="settings[]" value="${setting.setting_key}"
                                   id="setting_${setting.setting_key}">
                            <label class="form-check-label" for="setting_${setting.setting_key}">
                                <code>${setting.setting_key}</code>
                                <small class="text-muted ms-2">(${setting.setting_type})</small>
                            </label>
                        </div>
                    </div>
                `;
            });
            
            settingsList.innerHTML = html;
        } else {
            settingsList.innerHTML = '<div class="alert alert-warning">Failed to load settings</div>';
        }
    })
    .catch(error => {
        settingsList.innerHTML = '<div class="alert alert-danger">Error loading settings</div>';
    });
}

// Validate import file
function validateImportFile(input) {
    const file = input.files[0];
    if (!file) return;
    
    const ext = file.name.split('.').pop().toLowerCase();
    
    fetch(`ajax/validate-import.php?ext=${ext}`)
    .then(response => response.json())
    .then(data => {
        const messageEl = document.getElementById('fileValidationMessage');
        if (data.valid) {
            messageEl.innerHTML = `<span class="text-success">✓ ${data.message}</span>`;
        } else {
            messageEl.innerHTML = `<span class="text-danger">✗ ${data.message}</span>`;
            input.value = ''; // Clear invalid file
        }
    });
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
            window.open(`ajax/export-settings.php?${params.toString()}`, '_blank');
            
            // Show success message
            showToast('success', 'Export started!');
            
            // Refresh history after a delay
            setTimeout(() => refreshHistory(), 2000);
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
    
    // Check if dry run is selected
    const isDryRun = formData.get('dry_run') === 'on';
    
    Swal.fire({
        title: 'Processing File...',
        text: 'Please wait while we analyze your file.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            
            fetch('ajax/preview-import.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    currentImportData = data;
                    showImportPreview(data, isDryRun);
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
        data.preview.forEach(setting => {
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
    `;
    
    preview.innerHTML = html;
    
    // Show confirm button only if not dry run
    const confirmBtn = document.getElementById('confirmImportBtn');
    confirmBtn.style.display = isDryRun ? 'none' : 'inline-block';
    
    $('#importPreviewModal').modal('show');
}

// Confirm import
function confirmImport() {
    if (!currentImportData) return;
    
    const form = document.getElementById('importForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Confirm Import',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>You are about to import <strong>${currentImportData.total_settings}</strong> settings.</p>
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
            showLoading();
            
            fetch('ajax/import-settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Import Complete!',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <p>${data.message}</p>
                                <div class="row g-2 text-start mt-3">
                                    <div class="col-6">New:</div>
                                    <div class="col-6 fw-bold text-success">${data.imported || 0}</div>
                                    <div class="col-6">Updated:</div>
                                    <div class="col-6 fw-bold text-warning">${data.updated || 0}</div>
                                    <div class="col-6">Skipped:</div>
                                    <div class="col-6 fw-bold text-muted">${data.skipped || 0}</div>
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
                hideLoading();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred during import.', 'error');
            });
        }
    });
}

// Clear all history
function clearAllHistory() {
    Swal.fire({
        title: 'Clear All History',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Are you sure you want to delete all import/export history?</p>
                <p class="text-muted small">A backup will be created automatically.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, clear all',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/clear-history.php')
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire('Cleared!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                Swal.fire('Error!', 'An error occurred', 'error');
            });
        }
    });
}

// Delete single history item
function deleteHistoryItem(id) {
    Swal.fire({
        title: 'Delete History Item',
        text: 'Are you sure you want to delete this history item?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('ajax/delete-history-item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_id: id })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                showToast('error', 'An error occurred');
            });
        }
    });
}

// Refresh history
function refreshHistory() {
    location.reload();
}

// Sidebar toggle for mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth <= 991) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.sidebar');
    if (window.innerWidth > 991) {
        sidebar.classList.remove('active');
    }
});

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