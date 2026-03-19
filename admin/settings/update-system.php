<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'System Updates';
require_once '../includes/header.php';

// Define current version
// define('CURRENT_VERSION', '1.0.0');

// Initialize variables
$update_available = false;
$latest_version = CURRENT_VERSION;
$release_date = '';
$release_notes = [];
$error = null;

try {
    $db = getDB();
    
    // ========== CHECK FOR UPDATES ==========
    // Check if there are any pending updates in system_updates table
    $stmt = $db->prepare("SELECT * FROM system_updates WHERE is_applied = 0 ORDER BY release_date DESC LIMIT 1");
    $stmt->execute();
    $pending_update = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pending_update) {
        $update_available = true;
        $latest_version = $pending_update['version'];
        $release_date = $pending_update['release_date'];
        $release_notes = explode("\n", $pending_update['changelog'] ?? '');
    } else {
        // Check remote update server (optional)
        $remote_update = checkRemoteUpdates();
        if ($remote_update && version_compare($remote_update['version'], CURRENT_VERSION, '>')) {
            $update_available = true;
            $latest_version = $remote_update['version'];
            $release_date = $remote_update['release_date'];
            $release_notes = $remote_update['notes'];
        }
    }
    
    // ========== GET UPDATE HISTORY ==========
    $stmt = $db->prepare("SELECT * FROM system_updates WHERE is_applied = 1 ORDER BY release_date DESC LIMIT 10");
    $stmt->execute();
    $update_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========== GET SYSTEM STATISTICS ==========
    // Get total users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total products
    $stmt = $db->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total orders
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total revenue
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'completed'");
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // ========== GET BACKUP INFORMATION ==========
    $last_backup = null;
    $backup_size = 0;
    $backup_count = 0;
    
    try {
        $stmt = $db->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 1");
        $last_backup = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(size), 0) as total_size FROM backup_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $backup_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $backup_count = $backup_stats['count'];
        $backup_size = $backup_stats['total_size'];
    } catch(Exception $e) {
        // Backup table might not exist
    }
    
    // ========== GET ACTIVE SESSIONS ==========
    $stmt = $db->query("SELECT COUNT(*) as total FROM user_sessions WHERE is_active = 1 AND login_time >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $active_sessions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // ========== GET PHP EXTENSIONS STATUS ==========
    $required_extensions = ['pdo', 'json', 'mysqli', 'curl', 'gd', 'zip', 'openssl'];
    $extensions_status = [];
    foreach ($required_extensions as $ext) {
        $extensions_status[$ext] = extension_loaded($ext);
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
    error_log("System updates error: " . $e->getMessage());
}

/**
 * Check remote update server for updates
 */
function checkRemoteUpdates() {
    // This would normally check your update server
    // For now, return null (no remote updates)
    return null;
    
    // Example implementation:
    $update_url = SITE_URL . 'check.php?version=' . CURRENT_VERSION;
    $response = @file_get_contents($update_url);
    if ($response) {
        return json_decode($response, true);
    }
    return null;
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// System requirements with real data
$requirements = [
    'PHP Version' => [
        'required' => '7.4',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.4', '>=')
    ],
    'MySQL Version' => [
        'required' => '5.7',
        'current' => $db->getAttribute(PDO::ATTR_SERVER_VERSION) ?? 'Unknown',
        'status' => version_compare($db->getAttribute(PDO::ATTR_SERVER_VERSION) ?? '0', '5.7', '>=')
    ],
    'PDO Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('pdo') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('pdo')
    ],
    'JSON Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('json')
    ],
    'cURL Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('curl')
    ],
    'GD Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('gd') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('gd')
    ],
    'Zip Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('zip') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('zip')
    ],
    'Write Permissions' => [
        'required' => 'Write',
        'current' => is_writable('../') ? 'Writable' : 'Not Writable',
        'status' => is_writable('../')
    ],
    'Upload Permissions' => [
        'required' => 'Write',
        'current' => is_writable('../uploads/') ? 'Writable' : 'Not Writable',
        'status' => is_writable('../uploads/')
    ]
];
?>

<style>
/* All existing CSS remains the same */
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

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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
    width: 60px;
    height: 60px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
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
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    color: var(--gray-600);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Version Card */
.version-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
    position: relative;
}

.version-card .card-body {
    padding: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.version-info h5 {
    font-size: 1.1rem;
    color: var(--gray-600);
    margin-bottom: 0.5rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.version-number {
    font-size: 2.5rem;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1.2;
    margin-bottom: 0.5rem;
}

.version-meta {
    color: var(--gray-500);
    font-size: 0.9rem;
}

.version-status {
    text-align: center;
}

.status-badge {
    display: inline-block;
    padding: 0.75rem 2rem;
    border-radius: var(--border-radius-full);
    font-weight: 700;
    font-size: 1.1rem;
    box-shadow: var(--shadow-lg);
}

.status-badge.success {
    background: var(--success-gradient);
    color: white;
}

.status-badge.warning {
    background: var(--warning-gradient);
    color: white;
}

.status-badge.danger {
    background: var(--danger-gradient);
    color: white;
}

/* Update Card */
.update-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 2px solid var(--warning);
    overflow: hidden;
    box-shadow: var(--shadow-xl);
    margin-bottom: 2rem;
    animation: pulse 2s infinite;
}

.update-card .card-header {
    padding: 1.25rem 2rem;
    background: linear-gradient(135deg, rgba(255, 183, 3, 0.1), rgba(247, 127, 0, 0.1));
    border-bottom: 1px solid rgba(255, 183, 3, 0.2);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.update-card .card-header i {
    color: var(--warning);
    font-size: 1.5rem;
}

.update-card .card-header h5 {
    font-weight: 700;
    color: var(--warning-dark);
    margin: 0;
    font-size: 1.25rem;
}

.update-card .card-body {
    padding: 2rem;
}

.update-grid {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 2rem;
    align-items: center;
}

.update-icon {
    width: 120px;
    height: 120px;
    border-radius: var(--border-radius-full);
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(58, 12, 163, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: var(--primary);
}

.update-details {
    flex: 1;
}

.update-version {
    font-size: 2rem;
    font-weight: 800;
    color: var(--gray-800);
    margin-bottom: 0.5rem;
}

.update-date {
    color: var(--gray-600);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.release-notes {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.release-notes h6 {
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.release-notes ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.release-notes li {
    padding: 0.5rem 0;
    border-bottom: 1px dashed var(--gray-300);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.release-notes li:last-child {
    border-bottom: none;
}

.release-notes li i {
    color: var(--success);
}

.update-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

/* Requirements Card */
.requirements-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.requirements-card .card-header {
    padding: 1.25rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.requirements-card .card-header i {
    color: var(--info);
    font-size: 1.25rem;
}

.requirements-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    font-size: 1.1rem;
}

.requirements-card .card-body {
    padding: 1.5rem 2rem;
}

.requirements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.requirement-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.requirement-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.requirement-info h6 {
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
}

.requirement-desc {
    font-size: 0.8rem;
    color: var(--gray-600);
}

.requirement-status {
    text-align: right;
}

.requirement-badge {
    display: inline-block;
    padding: 0.35rem 1rem;
    border-radius: var(--border-radius-full);
    font-weight: 600;
    font-size: 0.8rem;
}

.requirement-badge.pass {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.requirement-badge.fail {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

/* Backup Info Card */
.backup-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.backup-card .card-header {
    padding: 1.25rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.backup-card .card-header i {
    color: var(--info);
    font-size: 1.25rem;
}

.backup-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    font-size: 1.1rem;
}

.backup-card .card-body {
    padding: 1.5rem 2rem;
}

.backup-info {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.backup-item {
    flex: 1;
    min-width: 150px;
    text-align: center;
    padding: 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
}

.backup-item .backup-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.backup-item .backup-label {
    font-size: 0.8rem;
    color: var(--gray-600);
}

/* History Card */
.history-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.history-card .card-header {
    padding: 1.25rem 2rem;
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
    color: var(--warning);
}

.history-card .card-body {
    padding: 0;
}

.history-list {
    max-height: 300px;
    overflow-y: auto;
}

.history-item {
    padding: 1.25rem 2rem;
    border-bottom: 1px solid var(--gray-200);
    transition: var(--transition);
}

.history-item:last-child {
    border-bottom: none;
}

.history-item:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.history-version {
    font-weight: 700;
    color: var(--primary);
    font-size: 1.1rem;
}

.history-date {
    font-size: 0.85rem;
    color: var(--gray-500);
}

.history-description {
    color: var(--gray-600);
    font-size: 0.9rem;
}

/* Manual Update Card */
.manual-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    animation: slideIn 0.5s ease;
}

.manual-card .card-header {
    padding: 1.25rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.manual-card .card-header i {
    color: var(--success);
    font-size: 1.25rem;
}

.manual-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    font-size: 1.1rem;
}

.manual-card .card-body {
    padding: 2rem;
}

.upload-area {
    display: flex;
    gap: 1rem;
    align-items: center;
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 0.5rem;
}

.upload-area .form-control {
    flex: 1;
    border: 2px dashed var(--gray-300);
    background: white;
    padding: 1rem;
    border-radius: var(--border-radius-lg);
    cursor: pointer;
}

.upload-area .form-control:hover {
    border-color: var(--primary);
}

.upload-btn {
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--border-radius-lg);
    padding: 1rem 2rem;
    font-weight: 600;
    transition: var(--transition);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    white-space: nowrap;
}

.upload-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
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

/* Progress Bar */
.progress-lg {
    height: 20px;
    border-radius: var(--border-radius-full);
    background: var(--gray-200);
    overflow: hidden;
    margin: 1.5rem 0;
}

.progress-lg .progress-bar {
    background: var(--primary-gradient);
    font-size: 0.8rem;
    font-weight: 600;
}

.update-logs {
    background: var(--gray-900);
    color: var(--success);
    padding: 1rem;
    border-radius: var(--border-radius-lg);
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    max-height: 200px;
    overflow-y: auto;
    margin-top: 1rem;
}

.update-logs div {
    padding: 0.25rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.update-logs div:last-child {
    border-bottom: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

.empty-state h5 {
    font-size: 1.1rem;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.empty-state p {
    color: var(--gray-500);
    margin-bottom: 1rem;
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

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 183, 3, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(255, 183, 3, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(255, 183, 3, 0);
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
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .version-card .card-body {
        flex-direction: column;
        text-align: center;
    }
    
    .update-grid {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .update-icon {
        margin: 0 auto;
    }
    
    .update-actions {
        flex-direction: column;
    }
    
    .upload-area {
        flex-direction: column;
    }
    
    .upload-btn {
        width: 100%;
    }
    
    .requirements-grid {
        grid-template-columns: 1fr;
    }
    
    .backup-info {
        flex-direction: column;
    }
}

@media (max-width: 576px) {
    .stats-row {
        grid-template-columns: 1fr;
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
                        <i class="fas fa-sync-alt"></i>
                        System Updates
                    </h1>
                    <p class="mb-0">Keep your system up to date with the latest features and security patches</p>
                </div>
                <button class="btn btn-primary" onclick="checkForUpdates()">
                    <i class="fas fa-search me-2"></i>Check for Updates
                </button>
            </div>
        </div>
        
        <!-- System Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($total_products); ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>
        
        <!-- Current Version Card -->
        <div class="version-card">
            <div class="card-body">
                <div class="version-info">
                    <h5>Current Version</h5>
                    <div class="version-number"><?php echo CURRENT_VERSION; ?></div>
                    <div class="version-meta">
                        <i class="far fa-calendar-alt me-1"></i>
                        Last checked: <?php echo date('M d, Y H:i'); ?>
                    </div>
                </div>
                <div class="version-status">
                    <?php if ($update_available): ?>
                    <span class="status-badge warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Update Available
                    </span>
                    <?php else: ?>
                    <span class="status-badge success">
                        <i class="fas fa-check-circle me-2"></i>
                        Up to Date
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($update_available): ?>
        <!-- Update Available Card -->
        <div class="update-card">
            <div class="card-header">
                <i class="fas fa-exclamation-circle"></i>
                <h5>New Update Available!</h5>
            </div>
            <div class="card-body">
                <div class="update-grid">
                    <div class="update-icon">
                        <i class="fas fa-cloud-download-alt"></i>
                    </div>
                    <div class="update-details">
                        <div class="update-version">Version <?php echo $latest_version; ?></div>
                        <div class="update-date">
                            <i class="far fa-calendar-alt"></i>
                            Released on <?php echo date('F d, Y', strtotime($release_date)); ?>
                        </div>
                        
                        <?php if (!empty($release_notes)): ?>
                        <div class="release-notes">
                            <h6>
                                <i class="fas fa-clipboard-list"></i>
                                What's New
                            </h6>
                            <ul>
                                <?php foreach($release_notes as $note): ?>
                                <?php if(trim($note)): ?>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo htmlspecialchars(trim($note)); ?>
                                </li>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="update-actions">
                            <button class="btn btn-primary btn-lg flex-fill" onclick="startUpdate('<?php echo $latest_version; ?>')">
                                <i class="fas fa-download me-2"></i>
                                Update Now
                            </button>
                            <button class="btn btn-outline-primary btn-lg" onclick="viewChangelog('<?php echo $latest_version; ?>')">
                                <i class="fas fa-file-alt me-2"></i>
                                Changelog
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- System Requirements -->
        <div class="requirements-card">
            <div class="card-header">
                <i class="fas fa-clipboard-check"></i>
                <h5>System Requirements</h5>
            </div>
            <div class="card-body">
                <div class="requirements-grid">
                    <?php foreach($requirements as $name => $req): ?>
                    <div class="requirement-item">
                        <div class="requirement-info">
                            <h6><?php echo $name; ?></h6>
                            <div class="requirement-desc">
                                Required: <?php echo $req['required']; ?> | Current: <?php echo $req['current']; ?>
                            </div>
                        </div>
                        <div class="requirement-status">
                            <?php if ($req['status']): ?>
                            <span class="requirement-badge pass">
                                <i class="fas fa-check me-1"></i>
                                Pass
                            </span>
                            <?php else: ?>
                            <span class="requirement-badge fail">
                                <i class="fas fa-times me-1"></i>
                                Fail
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Backup Information -->
        <div class="backup-card">
            <div class="card-header">
                <i class="fas fa-database"></i>
                <h5>Backup Information</h5>
            </div>
            <div class="card-body">
                <div class="backup-info">
                    <div class="backup-item">
                        <div class="backup-value">
                            <?php echo $last_backup ? date('M d, Y', strtotime($last_backup['created_at'])) : 'Never'; ?>
                        </div>
                        <div class="backup-label">Last Backup</div>
                    </div>
                    <div class="backup-item">
                        <div class="backup-value"><?php echo $backup_count; ?></div>
                        <div class="backup-label">Backups (30 days)</div>
                    </div>
                    <div class="backup-item">
                        <div class="backup-value"><?php echo formatFileSize($backup_size); ?></div>
                        <div class="backup-label">Total Size</div>
                    </div>
                    <div class="backup-item">
                        <div class="backup-value"><?php echo $active_sessions; ?></div>
                        <div class="backup-label">Active Sessions</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Update History -->
        <div class="history-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-history"></i>
                    Update History
                </h5>
                <button class="btn btn-outline-primary btn-sm" onclick="loadUpdateHistory()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($update_history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h5>No Update History</h5>
                    <p class="text-muted">No previous updates have been recorded</p>
                </div>
                <?php else: ?>
                <div class="history-list">
                    <?php foreach($update_history as $update): ?>
                    <div class="history-item">
                        <div class="history-header">
                            <span class="history-version">Version <?php echo htmlspecialchars($update['version']); ?></span>
                            <span class="history-date">
                                <i class="far fa-calendar-alt me-1"></i>
                                <?php echo date('M d, Y', strtotime($update['release_date'])); ?>
                            </span>
                        </div>
                        <div class="history-description">
                            <?php echo nl2br(htmlspecialchars($update['description'] ?? '')); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Manual Update -->
        <div class="manual-card">
            <div class="card-header">
                <i class="fas fa-upload"></i>
                <h5>Manual Update</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Upload an update package manually if automatic update is not available</p>
                
                <form id="manualUpdateForm" enctype="multipart/form-data">
                    <div class="upload-area">
                        <input type="file" class="form-control" name="update_package" 
                               accept=".zip,.tar.gz" id="updatePackage" required>
                        <button type="button" class="upload-btn" onclick="uploadManualUpdate()">
                            <i class="fas fa-upload"></i>
                            Upload & Install
                        </button>
                    </div>
                    <div class="form-text mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Supported formats: ZIP, TAR.GZ (Max size: 100MB)
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- Update Progress Modal -->
<div class="modal fade" id="updateProgressModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sync-alt fa-spin me-2"></i>
                    System Update Progress
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="updateProgressContent">
                <div class="text-center py-4">
                    <i class="fas fa-sync-alt fa-spin fa-3x text-primary mb-3"></i>
                    <h5>Preparing Update...</h5>
                    <p class="text-muted">Please wait while the system is being updated</p>
                    <div class="progress-lg">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             style="width: 0%" id="updateProgressBar">0%</div>
                    </div>
                    <div id="updateLogs" class="update-logs">
                        <div><i class="fas fa-clock me-2"></i> Initializing update process...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Changelog Modal -->
<div class="modal fade" id="changelogModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Update Changelog
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="changelogContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let updateStep = 0;
let updateInterval;

// Check for updates
function checkForUpdates() {
    Swal.fire({
        title: 'Checking for Updates',
        text: 'Please wait while we check for updates...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            
            // Send AJAX request to check for updates
            fetch('ajax/check-updates.php')
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.update_available) {
                    Swal.fire({
                        title: 'Update Available!',
                        html: `
                            <div class="text-start">
                                <div class="alert alert-success">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Version ${data.version} is now available
                                </div>
                                <p><strong>Release Date:</strong> ${data.release_date}</p>
                                <p><strong>What's New:</strong></p>
                                <ul>
                                    ${data.notes.map(n => `<li>${n}</li>`).join('')}
                                </ul>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Update Now',
                        cancelButtonText: 'Later',
                        confirmButtonColor: '#1cc88a'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            startUpdate(data.version);
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
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Check Failed',
                    text: 'Could not check for updates. Please try again later.'
                });
            });
        }
    });
}

// Start update
function startUpdate(version) {
    $('#updateProgressModal').modal({
        backdrop: 'static',
        keyboard: false
    });
    $('#updateProgressModal').modal('show');
    
    updateStep = 0;
    document.getElementById('updateProgressBar').style.width = '0%';
    document.getElementById('updateProgressBar').innerHTML = '0%';
    document.getElementById('updateLogs').innerHTML = '<div><i class="fas fa-clock me-2"></i> Initializing update process...</div>';
    
    // Send AJAX request to start update
    fetch('ajax/start-update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ version: version })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Start polling for update status
            pollUpdateStatus();
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        $('#updateProgressModal').modal('hide');
        Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: error.message
        });
    });
}

// Poll update status
function pollUpdateStatus() {
    let statusInterval = setInterval(() => {
        fetch('ajax/update-status.php')
        .then(response => response.json())
        .then(data => {
            // Update progress bar
            document.getElementById('updateProgressBar').style.width = data.progress + '%';
            document.getElementById('updateProgressBar').innerHTML = data.progress + '%';
            
            // Update logs
            if (data.logs && data.logs.length) {
                const logsDiv = document.getElementById('updateLogs');
                data.logs.forEach(log => {
                    logsDiv.innerHTML += `<div><i class="fas fa-check-circle text-success me-2"></i> ${log}</div>`;
                });
                logsDiv.scrollTop = logsDiv.scrollHeight;
            }
            
            // Check if update is complete
            if (data.complete) {
                clearInterval(statusInterval);
                setTimeout(() => {
                    $('#updateProgressModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Update Successful!',
                        text: 'System has been updated successfully.',
                        confirmButtonText: 'Restart Now'
                    }).then(() => {
                        window.location.href = 'dashboard.php';
                    });
                }, 2000);
            }
            
            // Check if update failed
            if (data.failed) {
                clearInterval(statusInterval);
                $('#updateProgressModal').modal('hide');
                Swal.fire({
                    icon: 'error',
                    title: 'Update Failed',
                    text: data.error || 'An error occurred during update'
                });
            }
        })
        .catch(error => {
            console.error('Status check error:', error);
        });
    }, 2000);
}

// View changelog
function viewChangelog(version) {
    document.getElementById('changelogContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    fetch(`ajax/get-changelog.php?version=${version}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('changelogContent').innerHTML = data.html;
        } else {
            document.getElementById('changelogContent').innerHTML = `
                <div class="alert alert-danger">
                    Could not load changelog: ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('changelogContent').innerHTML = `
            <div class="alert alert-danger">
                Could not load changelog. Please try again later.
            </div>
        `;
    });
    
    $('#changelogModal').modal('show');
}

// Upload manual update
function uploadManualUpdate() {
    const fileInput = document.getElementById('updatePackage');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        Swal.fire('Error!', 'Please select an update package.', 'error');
        return;
    }
    
    const file = fileInput.files[0];
    const maxSize = 100 * 1024 * 1024; // 100MB
    
    if (file.size > maxSize) {
        Swal.fire('Error!', 'File size exceeds 100MB limit.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Manual Update',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>Upload and install update package?</p>
                <p class="small text-muted">File: ${file.name}</p>
                <p class="small text-muted">Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Upload & Install',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('update_package', file);
            
            Swal.fire({
                title: 'Uploading...',
                text: 'Please wait while the package is uploaded',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('ajax/upload-update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    startUpdate(data.version);
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'Upload failed. Please try again.', 'error');
            });
        }
    });
}

// Load update history
function loadUpdateHistory() {
    location.reload();
}

// Initialize animations
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to requirement items
    document.querySelectorAll('.requirement-item').forEach((item, index) => {
        item.style.animation = `slideIn 0.3s ease ${index * 0.05}s forwards`;
        item.style.opacity = '0';
    });
    
    // Add animation to stat cards
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        card.style.animation = `slideIn 0.3s ease ${index * 0.05}s forwards`;
        card.style.opacity = '0';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>