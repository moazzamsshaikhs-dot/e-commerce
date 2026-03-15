<?php
// Start output buffering at the VERY BEGINNING - THIS IS CRITICAL
ob_start();

require_once './includes/config.php';
require_once './includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'Admin Profile';

// Get admin data
try {
    $db = getDB();
    $admin_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        $_SESSION['error'] = 'Admin not found';
        redirect('logout.php');
        exit;
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading profile: ' . $e->getMessage();
    $admin = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'update_profile':
            updateAdminProfile($admin_id);
            break;
            
        case 'change_password':
            changeAdminPassword($admin_id);
            break;
            
        case 'upload_avatar':
            uploadAdminAvatar($admin_id);
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action';
            redirect('admin/profile.php');
            exit;
    }
}

/**
 * Update admin profile
 */
function updateAdminProfile($admin_id) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect('index.php');
        exit;
    }
    
    // Get form data
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    $country = sanitize($_POST['country'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $postal_code = sanitize($_POST['postal_code'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'other');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    
    // Social media
    $social_facebook = sanitize($_POST['social_facebook'] ?? '');
    $social_twitter = sanitize($_POST['social_twitter'] ?? '');
    $social_instagram = sanitize($_POST['social_instagram'] ?? '');
    $social_linkedin = sanitize($_POST['social_linkedin'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,20}$/', $phone)) {
        $errors[] = 'Invalid phone number';
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Check if email already exists for another user
            $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->execute([$email, $admin_id]);
            
            if ($check_stmt->fetch()) {
                $_SESSION['error'] = 'Email already exists for another user';
                redirect('profile.php');
                exit;
            }
            
            // Update admin profile
            $stmt = $db->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, address = ?, bio = ?, 
                    country = ?, city = ?, postal_code = ?, gender = ?, date_of_birth = ?,
                    social_facebook = ?, social_twitter = ?, social_instagram = ?, social_linkedin = ?,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            $stmt->execute([
                $full_name, $email, $phone, $address, $bio,
                $country, $city, $postal_code, $gender, $date_of_birth,
                $social_facebook, $social_twitter, $social_instagram, $social_linkedin,
                $admin_id
            ]);
            
            // Update session data
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            
            // Log activity
            if (function_exists('logUserActivity')) {
                logUserActivity($admin_id, 'profile_update', 'Updated admin profile');
            }
            
            // Send security alert
            if (function_exists('sendSecurityAlert')) {
                sendSecurityAlert($admin_id, 'profile_update', "Profile updated on " . date('Y-m-d H:i:s'));
            }
            
            $_SESSION['success'] = 'Profile updated successfully!';
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Failed to update profile: ' . $e->getMessage();
        }
    } else {
        $_SESSION['form_errors'] = $errors;
    }
    
    redirect('admin/profile.php');
    exit;
}

/**
 * Change admin password
 */
function changeAdminPassword($admin_id) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect('profile.php');
        exit;
    }
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = 'Current password is required';
    }
    
    if (empty($new_password)) {
        $errors[] = 'New password is required';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters';
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match';
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Get current password hash
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$admin_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $_SESSION['error'] = 'User not found';
                redirect('profile.php');
                exit;
            }
            
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $_SESSION['error'] = 'Current password is incorrect';
                redirect('profile.php');
                exit;
            }
            
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$new_password_hash, $admin_id]);
            
            // Log activity
            if (function_exists('logUserActivity')) {
                logUserActivity($admin_id, 'password_change', 'Changed admin password');
            }
            
            // Send security alert
            if (function_exists('sendSecurityAlert')) {
                sendSecurityAlert($admin_id, 'password_change');
            }
            
            $_SESSION['success'] = 'Password changed successfully!';
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Failed to change password: ' . $e->getMessage();
        }
    } else {
        $_SESSION['form_errors'] = $errors;
    }
    
    redirect('admin/profile.php');
    exit;
}

/**
 * Upload admin avatar
 */
function uploadAdminAvatar($admin_id) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect('index.php');
        exit;
    }
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            $_SESSION['error'] = 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp';
            redirect('profile.php');
            exit;
        }
        
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['error'] = 'File too large. Maximum 2MB allowed';
            redirect('profile.php');
            exit;
        }
        
        // Generate unique filename
        $filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
        $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/profiles/' . $filename;
        
        // Create directory if it doesn't exist
        $directory = dirname($upload_path);
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            try {
                $db = getDB();
                
                // Get old avatar to delete
                $stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ?");
                $stmt->execute([$admin_id]);
                $old_avatar = $stmt->fetchColumn();
                
                // Delete old avatar if not default
                if ($old_avatar && $old_avatar !== 'default.png' && $old_avatar !== 'default.jpg' && $old_avatar !== 'default.webp') {
                    $old_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/profiles/' . $old_avatar;
                    if (file_exists($old_path)) {
                        @unlink($old_path);
                    }
                }
                
                // Update database
                $stmt = $db->prepare("UPDATE users SET profile_pic = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$filename, $admin_id]);
                
                // Log activity
                if (function_exists('logUserActivity')) {
                    logUserActivity($admin_id, 'avatar_update', 'Uploaded new admin avatar');
                }
                
                $_SESSION['success'] = 'Profile picture updated successfully!';
                
            } catch(PDOException $e) {
                // Delete uploaded file if database update fails
                @unlink($upload_path);
                $_SESSION['error'] = 'Failed to update profile picture: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Failed to upload file';
        }
    } else {
        $_SESSION['error'] = 'No file uploaded or upload error';
    }
    
    redirect('admin/profile.php');
    exit;
}

// Log admin profile access
if (function_exists('logUserActivity')) {
    logUserActivity($_SESSION['user_id'], 'admin_profile_access', 'Accessed admin profile page');
}

// Helper function for date formatting
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

// NOW include the header - after all PHP logic is done
require_once './includes/header.php';
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

/* Global Styles */
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: var(--gray-100);
    color: var(--gray-800);
    line-height: 1.6;
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

/* Sidebar Toggle for Mobile */
.sidebar-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: var(--border-radius-full);
    background: var(--primary-gradient);
    color: white;
    border: none;
    box-shadow: var(--shadow-lg);
    cursor: pointer;
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    transition: var(--transition-bounce);
}

.sidebar-toggle:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-xl);
}

@media (max-width: 992px) {
    .sidebar-toggle {
        display: flex;
    }
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
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
    font-size: 1.75rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.25rem;
}

.page-header p {
    color: var(--gray-600);
    margin-bottom: 0;
    font-size: 0.95rem;
}

.page-header .btn {
    padding: 0.6rem 1.25rem;
    border-radius: var(--border-radius-full);
    font-weight: 500;
    transition: var(--transition);
}

/* Alert Styles */
.alert {
    border: none;
    border-radius: var(--border-radius-lg);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    box-shadow: var(--shadow-md);
    animation: slideIn 0.3s ease;
    position: relative;
    overflow: hidden;
}

.alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.alert-success {
    background: rgba(6, 214, 160, 0.1);
    color: var(--success-dark);
}

.alert-success::before {
    background: var(--success-gradient);
}

.alert-danger {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger-dark);
}

.alert-danger::before {
    background: var(--danger-gradient);
}

.alert-warning {
    background: rgba(255, 183, 3, 0.1);
    color: var(--warning-dark);
}

.alert-warning::before {
    background: var(--warning-gradient);
}

.alert-info {
    background: rgba(76, 201, 240, 0.1);
    color: var(--info-dark);
}

.alert-info::before {
    background: var(--info-gradient);
}

.alert i {
    font-size: 1.25rem;
    margin-right: 0.75rem;
}

.alert .btn-close {
    position: absolute;
    top: 50%;
    right: 1rem;
    transform: translateY(-50%);
    opacity: 0.5;
    transition: var(--transition);
}

.alert .btn-close:hover {
    opacity: 1;
}

/* Card Styles */
.card {
    border: none;
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    overflow: hidden;
    background: white;
    border: 1px solid var(--gray-200);
    position: relative;
}

.card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary-gradient);
    opacity: 0;
    transition: var(--transition);
}

.card:hover::before {
    opacity: 1;
}

.card-header {
    background: white;
    border-bottom: 1px solid var(--gray-200);
    padding: 1.25rem 1.5rem;
    font-weight: 600;
    color: var(--gray-800);
    position: relative;
}

.card-header:first-child {
    border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
}

.card-body {
    padding: 1.5rem;
}

/* Profile Card Specific */
.profile-card {
    text-align: center;
    position: relative;
}

.profile-card .avatar-wrapper {
    position: relative;
    width: 180px;
    height: 180px;
    margin: 0 auto 1.5rem;
}

.profile-card .avatar-wrapper img {
    width: 100%;
    height: 100%;
    border-radius: var(--border-radius-full);
    object-fit: cover;
    border: 4px solid white;
    box-shadow: var(--shadow-lg);
    transition: var(--transition);
}

.profile-card .avatar-wrapper:hover img {
    transform: scale(1.05);
}

.profile-card .avatar-wrapper .avatar-upload-btn {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 45px;
    height: 45px;
    border-radius: var(--border-radius-full);
    background: var(--primary-gradient);
    color: white;
    border: 3px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition-bounce);
    box-shadow: var(--shadow-md);
}

.profile-card .avatar-wrapper .avatar-upload-btn:hover {
    transform: scale(1.15) rotate(180deg);
    box-shadow: var(--shadow-lg);
}

.profile-card .avatar-wrapper .avatar-upload-btn i {
    font-size: 1.25rem;
}

.profile-card h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.profile-card .badge {
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius-full);
    font-weight: 500;
    font-size: 0.85rem;
    letter-spacing: 0.3px;
    margin-bottom: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-card .badge i {
    font-size: 1rem;
}

.profile-card .badge.bg-danger {
    background: linear-gradient(135deg, #ef476f, #d62828) !important;
}

.profile-card .stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin: 1.5rem 0;
}

.profile-card .stat-item {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    transition: var(--transition);
    border: 1px solid var(--gray-200);
}

.profile-card .stat-item:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
}

.profile-card .stat-item .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.profile-card .stat-item .stat-label {
    font-size: 0.85rem;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-card .contact-info {
    text-align: left;
    padding: 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
}

.profile-card .contact-info .info-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px dashed var(--gray-300);
}

.profile-card .contact-info .info-row:last-child {
    border-bottom: none;
}

.profile-card .contact-info .info-row i {
    width: 20px;
    color: var(--primary);
    font-size: 1rem;
}

.profile-card .contact-info .info-row span {
    color: var(--gray-700);
    font-size: 0.95rem;
}

.profile-card .member-since {
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
    font-size: 0.9rem;
    color: var(--gray-600);
}

/* Tabs Styling */
.nav-tabs {
    border-bottom: 2px solid var(--gray-200);
    gap: 0.5rem;
    padding: 0 0.5rem;
}

.nav-tabs .nav-link {
    border: none;
    padding: 1rem 1.5rem;
    color: var(--gray-600);
    font-weight: 600;
    font-size: 0.95rem;
    transition: var(--transition);
    position: relative;
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    margin-bottom: -2px;
}

.nav-tabs .nav-link i {
    margin-right: 0.75rem;
    font-size: 1rem;
}

.nav-tabs .nav-link:hover {
    color: var(--primary);
    background: rgba(67, 97, 238, 0.05);
}

.nav-tabs .nav-link.active {
    color: var(--primary);
    background: transparent;
    border-bottom: 3px solid var(--primary);
    font-weight: 700;
}

.nav-tabs .nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 3px;
    background: var(--primary-gradient);
    border-radius: var(--border-radius-full) var(--border-radius-full) 0 0;
}

/* Form Styling */
.form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.form-label i {
    margin-right: 0.5rem;
    color: var(--primary);
}

.form-control, .form-select {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: var(--transition);
    background: white;
    color: var(--gray-800);
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
    background-image: none;
}

.form-control.is-invalid:focus {
    box-shadow: 0 0 0 4px rgba(239, 71, 111, 0.1);
}

.form-text {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin-top: 0.35rem;
}

/* Password Strength Meter */
.password-strength-meter {
    margin-top: 0.5rem;
}

.password-strength-meter .progress {
    height: 8px;
    border-radius: var(--border-radius-full);
    background: var(--gray-200);
    overflow: hidden;
}

.password-strength-meter .progress-bar {
    transition: width 0.3s ease, background-color 0.3s ease;
    border-radius: var(--border-radius-full);
}

.password-strength-meter .strength-text {
    font-size: 0.85rem;
    margin-top: 0.25rem;
    font-weight: 600;
    transition: color 0.3s ease;
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
    justify-content: center;
    gap: 0.5rem;
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

.btn-danger {
    background: var(--danger-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(239, 71, 111, 0.3);
}

.btn-danger:hover {
    box-shadow: 0 6px 15px rgba(239, 71, 111, 0.4);
    transform: translateY(-2px);
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

.btn-outline-danger {
    background: transparent;
    border: 2px solid var(--danger);
    color: var(--danger);
}

.btn-outline-danger:hover {
    background: var(--danger-gradient);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(239, 71, 111, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
}

/* Activity List */
.activity-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    border-bottom: 1px solid var(--gray-200);
    transition: var(--transition);
    animation: slideIn 0.3s ease;
    animation-fill-mode: both;
    animation-delay: calc(var(--item-index) * 0.05s);
}

.activity-item:hover {
    background: var(--gray-100);
    transform: translateX(5px);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item .activity-icon {
    width: 45px;
    height: 45px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.activity-item .activity-icon.login {
    background: linear-gradient(135deg, #06d6a0, #0ca678);
}

.activity-item .activity-icon.logout {
    background: linear-gradient(135deg, #ffb703, #f77f00);
}

.activity-item .activity-icon.password_change {
    background: linear-gradient(135deg, #ef476f, #d62828);
}

.activity-item .activity-icon.profile_update {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
}

.activity-item .activity-icon.default {
    background: linear-gradient(135deg, #4cc9f0, #0096c7);
}

.activity-item .activity-content {
    flex: 1;
}

.activity-item .activity-title {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.activity-item .activity-title small {
    font-weight: normal;
    color: var(--gray-500);
    font-size: 0.85rem;
}

.activity-item .activity-description {
    color: var(--gray-600);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.activity-item .activity-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--gray-500);
}

.activity-item .activity-meta i {
    margin-right: 0.25rem;
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
    margin-bottom: 1rem;
}

.empty-state h5 {
    font-size: 1.25rem;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.empty-state p {
    color: var(--gray-600);
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

@keyframes scaleIn {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
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

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
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

/* Apply animations to elements */
.card {
    animation: slideIn 0.5s ease forwards;
    animation-delay: calc(var(--card-index, 0) * 0.1s);
}

.stat-item {
    animation: scaleIn 0.5s ease forwards;
    animation-delay: calc(var(--stat-index, 0) * 0.1s);
}

/* Loading shimmer effect */
.loading {
    background: linear-gradient(90deg, var(--gray-200) 25%, var(--gray-300) 50%, var(--gray-200) 75%);
    background-size: 1000px 100%;
    animation: shimmer 2s infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
    
    .page-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .page-header::before {
        width: 100%;
        height: 4px;
        top: 0;
        left: 0;
    }
    
    .profile-card .avatar-wrapper {
        width: 150px;
        height: 150px;
    }
    
    .profile-card .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .nav-tabs .nav-link {
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
    }
    
    .nav-tabs .nav-link i {
        margin-right: 0.25rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .activity-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .activity-item .activity-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
}

/* Dark mode support (optional) */
@media (prefers-color-scheme: dark) {
    .dark-mode {
        --gray-100: #1a1b1e;
        --gray-200: #2c2e33;
        --gray-300: #3d3f44;
        --gray-400: #4f5157;
        --gray-500: #61646b;
        --gray-600: #73777f;
        --gray-700: #868a94;
        --gray-800: #989da8;
        --gray-900: #abb0bd;
    }
}

/* Custom scrollbar */
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
    <?php include './includes/sidebar.php'; ?>
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-user-circle me-2"></i>Admin Profile</h1>
                <p class="text-muted mb-0">Manage your admin account and security settings</p>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a href="../logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['form_errors'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach($_SESSION['form_errors'] as $error): ?>
                <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['form_errors']); ?>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- Left Column: Profile Info -->
            <div class="col-lg-4">
                <div class="card profile-card h-100">
                    <div class="card-body">
                        <!-- Avatar -->
                        <div class="avatar-wrapper">
                            <?php 
                            $avatar_url = !empty($admin['profile_pic']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/profiles/' . $admin['profile_pic'])
                                ? SITE_URL . '/assets/images/profiles/' . htmlspecialchars($admin['profile_pic'])
                                : SITE_URL . '/assets/images/profiles/default.png';
                            ?>
                            <img src="<?php echo $avatar_url; ?>" 
                                 alt="<?php echo htmlspecialchars($admin['full_name'] ?? 'Admin'); ?>"
                                 id="profileAvatar">
                            
                            <button type="button" 
                                    class="avatar-upload-btn"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#avatarModal"
                                    title="Change Profile Picture">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        
                        <!-- Admin Info -->
                        <h4><?php echo htmlspecialchars($admin['full_name'] ?? 'Admin'); ?></h4>
                        
                        <div class="badge bg-danger">
                            <i class="fas fa-crown"></i>
                            System Administrator
                        </div>
                        
                        <!-- Stats -->
                        <div class="stats-grid">
                            <?php
                            try {
                                $db = getDB();
                                $user_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
                                $product_count = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
                                $order_count = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                            } catch(Exception $e) {
                                $user_count = $product_count = $order_count = 0;
                            }
                            ?>
                            <div class="stat-item" style="--stat-index: 0;">
                                <div class="stat-value"><?php echo number_format($user_count); ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>
                            <div class="stat-item" style="--stat-index: 1;">
                                <div class="stat-value"><?php echo number_format($product_count); ?></div>
                                <div class="stat-label">Products</div>
                            </div>
                            <div class="stat-item" style="--stat-index: 2;">
                                <div class="stat-value"><?php echo number_format($order_count); ?></div>
                                <div class="stat-label">Orders</div>
                            </div>
                            <div class="stat-item" style="--stat-index: 3;">
                                <div class="stat-value"><?php echo $admin['login_count'] ?? 0; ?></div>
                                <div class="stat-label">Logins</div>
                            </div>
                        </div>
                        
                        <!-- Contact Info -->
                        <div class="contact-info">
                            <div class="info-row">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></span>
                            </div>
                            <?php if (!empty($admin['phone'])): ?>
                            <div class="info-row">
                                <i class="fas fa-phone-alt"></i>
                                <span><?php echo htmlspecialchars($admin['phone']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($admin['country']) || !empty($admin['city'])): ?>
                            <div class="info-row">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($admin['city'] ?? ''); ?><?php echo !empty($admin['city']) && !empty($admin['country']) ? ', ' : ''; ?><?php echo htmlspecialchars($admin['country'] ?? ''); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($admin['date_of_birth'])): ?>
                            <div class="info-row">
                                <i class="fas fa-birthday-cake"></i>
                                <span><?php echo date('M d, Y', strtotime($admin['date_of_birth'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Member Since -->
                        <div class="member-since">
                            <i class="far fa-calendar-alt me-2"></i>
                            Member since <?php echo formatDateTime($admin['created_at'] ?? 'now', 'M d, Y'); ?>
                            <?php if (!empty($admin['last_login'])): ?>
                            <br>
                            <i class="far fa-clock me-2"></i>
                            Last login: <?php echo formatDateTime($admin['last_login']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Social Links -->
                        <?php if (!empty($admin['social_facebook']) || !empty($admin['social_twitter']) || !empty($admin['social_instagram']) || !empty($admin['social_linkedin'])): ?>
                        <div class="social-links mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-center gap-3">
                                <?php if (!empty($admin['social_facebook'])): ?>
                                <a href="<?php echo htmlspecialchars($admin['social_facebook']); ?>" target="_blank" class="btn btn-icon btn-outline-primary">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($admin['social_twitter'])): ?>
                                <a href="<?php echo htmlspecialchars($admin['social_twitter']); ?>" target="_blank" class="btn btn-icon btn-outline-info">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($admin['social_instagram'])): ?>
                                <a href="<?php echo htmlspecialchars($admin['social_instagram']); ?>" target="_blank" class="btn btn-icon btn-outline-danger">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($admin['social_linkedin'])): ?>
                                <a href="<?php echo htmlspecialchars($admin['social_linkedin']); ?>" target="_blank" class="btn btn-icon btn-outline-primary">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Tabs -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                    <i class="fas fa-user-edit"></i> Edit Profile
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-shield-alt"></i> Security
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                    <i class="fas fa-history"></i> Activity Log
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content" id="profileTabsContent">
                            <!-- Profile Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <form method="POST" action="" id="profileForm">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    
                                    <div class="row g-4">
                                        <!-- Personal Info -->
                                        <div class="col-md-6">
                                            <label for="full_name" class="form-label">
                                                <i class="fas fa-user"></i> Full Name *
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="full_name" 
                                                   name="full_name" 
                                                   value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>"
                                                   required
                                                   placeholder="Enter your full name">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">
                                                <i class="fas fa-envelope"></i> Email Address *
                                            </label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email" 
                                                   name="email" 
                                                   value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>"
                                                   required
                                                   placeholder="admin@example.com">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">
                                                <i class="fas fa-phone-alt"></i> Phone Number
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>"
                                                   placeholder="+1 234 567 8900">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="gender" class="form-label">
                                                <i class="fas fa-venus-mars"></i> Gender
                                            </label>
                                            <select class="form-select" id="gender" name="gender">
                                                <option value="male" <?php echo ($admin['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="female" <?php echo ($admin['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="other" <?php echo empty($admin['gender']) || ($admin['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="date_of_birth" class="form-label">
                                                <i class="fas fa-birthday-cake"></i> Date of Birth
                                            </label>
                                            <input type="date" 
                                                   class="form-control" 
                                                   id="date_of_birth" 
                                                   name="date_of_birth" 
                                                   value="<?php echo !empty($admin['date_of_birth']) ? htmlspecialchars($admin['date_of_birth']) : ''; ?>">
                                        </div>
                                        
                                        <!-- Location -->
                                        <div class="col-md-6">
                                            <label for="country" class="form-label">
                                                <i class="fas fa-globe"></i> Short Country name
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="country" 
                                                   name="country" 
                                                   value="<?php echo htmlspecialchars($admin['country'] ?? ''); ?>"
                                                   placeholder="United States">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="city" class="form-label">
                                                <i class="fas fa-city"></i> City
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="city" 
                                                   name="city" 
                                                   value="<?php echo htmlspecialchars($admin['city'] ?? ''); ?>"
                                                   placeholder="New York">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="postal_code" class="form-label">
                                                <i class="fas fa-mail-bulk"></i> Postal Code
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="postal_code" 
                                                   name="postal_code" 
                                                   value="<?php echo htmlspecialchars($admin['postal_code'] ?? ''); ?>"
                                                   placeholder="10001">
                                        </div>
                                        
                                        <!-- Address -->
                                        <div class="col-12">
                                            <label for="address" class="form-label">
                                                <i class="fas fa-map-marker-alt"></i> Address
                                            </label>
                                            <textarea class="form-control" 
                                                      id="address" 
                                                      name="address" 
                                                      rows="2"
                                                      placeholder="Street address, apartment, suite, etc."><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <!-- Bio -->
                                        <div class="col-12">
                                            <label for="bio" class="form-label">
                                                <i class="fas fa-align-left"></i> Bio / About
                                            </label>
                                            <textarea class="form-control" 
                                                      id="bio" 
                                                      name="bio" 
                                                      rows="3"
                                                      placeholder="Tell us a little about yourself..."><?php echo htmlspecialchars($admin['bio'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <!-- Social Media -->
                                        <div class="col-12">
                                            <h6 class="border-bottom pb-2 mb-3">
                                                <i class="fas fa-share-alt me-2"></i>Social Media Profiles
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="social_facebook" class="form-label">
                                                        <i class="fab fa-facebook text-primary"></i> Facebook
                                                    </label>
                                                    <input type="url" 
                                                           class="form-control" 
                                                           id="social_facebook" 
                                                           name="social_facebook" 
                                                           value="<?php echo htmlspecialchars($admin['social_facebook'] ?? ''); ?>"
                                                           placeholder="https://facebook.com/username">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="social_twitter" class="form-label">
                                                        <i class="fab fa-twitter text-info"></i> Twitter
                                                    </label>
                                                    <input type="url" 
                                                           class="form-control" 
                                                           id="social_twitter" 
                                                           name="social_twitter" 
                                                           value="<?php echo htmlspecialchars($admin['social_twitter'] ?? ''); ?>"
                                                           placeholder="https://twitter.com/username">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="social_instagram" class="form-label">
                                                        <i class="fab fa-instagram text-danger"></i> Instagram
                                                    </label>
                                                    <input type="url" 
                                                           class="form-control" 
                                                           id="social_instagram" 
                                                           name="social_instagram" 
                                                           value="<?php echo htmlspecialchars($admin['social_instagram'] ?? ''); ?>"
                                                           placeholder="https://instagram.com/username">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="social_linkedin" class="form-label">
                                                        <i class="fab fa-linkedin text-primary"></i> LinkedIn
                                                    </label>
                                                    <input type="url" 
                                                           class="form-control" 
                                                           id="social_linkedin" 
                                                           name="social_linkedin" 
                                                           value="<?php echo htmlspecialchars($admin['social_linkedin'] ?? ''); ?>"
                                                           placeholder="https://linkedin.com/in/username">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Form Actions -->
                                        <div class="col-12">
                                            <hr class="my-3">
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-save me-2"></i> Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <form method="POST" action="" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    
                                    <div class="row g-4">
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Security Tip:</strong> Use a strong, unique password that you don't use elsewhere.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="current_password" class="form-label">
                                                <i class="fas fa-lock"></i> Current Password *
                                            </label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="current_password" 
                                                   name="current_password" 
                                                   required
                                                   placeholder="Enter current password">
                                        </div>
                                        
                                        <div class="col-md-6"></div>
                                        
                                        <div class="col-md-6">
                                            <label for="new_password" class="form-label">
                                                <i class="fas fa-key"></i> New Password *
                                            </label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="new_password" 
                                                   name="new_password" 
                                                   required
                                                   minlength="6"
                                                   placeholder="Enter new password">
                                            <div class="form-text">Minimum 6 characters</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="confirm_password" class="form-label">
                                                <i class="fas fa-check-double"></i> Confirm New Password *
                                            </label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="confirm_password" 
                                                   name="confirm_password" 
                                                   required
                                                   minlength="6"
                                                   placeholder="Confirm new password">
                                        </div>
                                        
                                        <!-- Password Strength Meter -->
                                        <div class="col-12">
                                            <div class="password-strength-meter">
                                                <div class="progress">
                                                    <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
                                                </div>
                                                <div class="strength-text" id="passwordStrengthText">Enter a password</div>
                                            </div>
                                        </div>
                                        
                                        <!-- Security Tips -->
                                        <div class="col-12">
                                            <div class="alert alert-warning">
                                                <h6 class="alert-heading mb-2">
                                                    <i class="fas fa-shield-alt me-2"></i>Password Requirements
                                                </h6>
                                                <ul class="mb-0 small">
                                                    <li>At least 6 characters long</li>
                                                    <li>Mix of uppercase and lowercase letters</li>
                                                    <li>Include at least one number</li>
                                                    <li>Include at least one special character</li>
                                                    <li>Don't use common words or patterns</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <!-- Form Actions -->
                                        <div class="col-12">
                                            <hr class="my-3">
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-danger btn-lg">
                                                    <i class="fas fa-key me-2"></i> Change Password
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Activity Tab -->
                            <div class="tab-pane fade" id="activity" role="tabpanel">
                                <?php
                                try {
                                    $db = getDB();
                                    $stmt = $db->prepare("
                                        SELECT * FROM user_activities 
                                        WHERE user_id = ? 
                                        ORDER BY created_at DESC 
                                        LIMIT 20
                                    ");
                                    $stmt->execute([$admin_id]);
                                    $activities = $stmt->fetchAll();
                                    
                                    if (!empty($activities)): 
                                ?>
                                <div class="activity-list">
                                    <?php foreach($activities as $index => $activity): 
                                        $icon_class = 'default';
                                        $icon = 'fa-info-circle';
                                        
                                        switch($activity['activity_type']) {
                                            case 'login':
                                                $icon = 'fa-sign-in-alt';
                                                $icon_class = 'login';
                                                break;
                                            case 'logout':
                                                $icon = 'fa-sign-out-alt';
                                                $icon_class = 'logout';
                                                break;
                                            case 'password_change':
                                                $icon = 'fa-key';
                                                $icon_class = 'password_change';
                                                break;
                                            case 'profile_update':
                                                $icon = 'fa-user-edit';
                                                $icon_class = 'profile_update';
                                                break;
                                            case 'avatar_update':
                                                $icon = 'fa-camera';
                                                $icon_class = 'profile_update';
                                                break;
                                            case 'admin_profile_access':
                                                $icon = 'fa-eye';
                                                $icon_class = 'default';
                                                break;
                                        }
                                    ?>
                                    <div class="activity-item" style="--item-index: <?php echo $index; ?>">
                                        <div class="activity-icon <?php echo $icon_class; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['activity_type']))); ?>
                                                <small><?php echo formatDateTime($activity['created_at']); ?></small>
                                            </div>
                                            <div class="activity-description">
                                                <?php echo htmlspecialchars($activity['description'] ?? 'No description'); ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span><i class="fas fa-globe"></i> <?php echo htmlspecialchars($activity['ip_address'] ?? 'Unknown IP'); ?></span>
                                                <?php if (!empty($activity['user_agent'])): ?>
                                                <span><i class="fas fa-laptop"></i> <?php echo htmlspecialchars(substr($activity['user_agent'], 0, 50)) . '...'; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="admin-logs.php" class="btn btn-outline-primary btn-lg">
                                        <i class="fas fa-history me-2"></i> View Complete Activity Log
                                    </a>
                                </div>
                                
                                <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <h5>No Activity Yet</h5>
                                    <p class="text-muted">Your recent activities will appear here</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php } catch(Exception $e) { ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Unable to load activity logs: <?php echo $e->getMessage(); ?>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_avatar">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-camera me-2"></i>Update Profile Picture
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="avatar-preview mb-4">
                        <img src="<?php echo $avatar_url; ?>" 
                             id="previewAvatar"
                             class="rounded-circle border border-4 border-white shadow"
                             style="width: 200px; height: 200px; object-fit: cover;">
                    </div>
                    
                    <div class="upload-area p-4 border-2 border-dashed rounded-3 mb-3" 
                         style="border: 2px dashed var(--gray-300); cursor: pointer;"
                         onclick="document.getElementById('avatar').click()">
                        <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
                        <p class="mb-1">Click to upload or drag and drop</p>
                        <small class="text-muted">JPG, PNG, GIF, WEBP (Max 2MB)</small>
                    </div>
                    
                    <input type="file" 
                           class="d-none" 
                           id="avatar" 
                           name="avatar"
                           accept="image/*"
                           onchange="previewImage(this)">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                        <i class="fas fa-upload me-2"></i>Upload Picture
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Password strength checker
const passwordInput = document.getElementById('new_password');
const confirmInput = document.getElementById('confirm_password');
const strengthBar = document.getElementById('passwordStrength');
const strengthText = document.getElementById('passwordStrengthText');

if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let text = 'Very Weak';
        let color = '#ef476f';
        
        // Length check
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        
        // Complexity checks
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        // Update UI
        switch(strength) {
            case 0:
            case 1:
                text = 'Very Weak';
                color = '#ef476f';
                break;
            case 2:
                text = 'Weak';
                color = '#ffb703';
                break;
            case 3:
                text = 'Medium';
                color = '#f77f00';
                break;
            case 4:
                text = 'Strong';
                color = '#06d6a0';
                break;
            case 5:
                text = 'Very Strong';
                color = '#0ca678';
                break;
        }
        
        const percentage = (strength / 5) * 100;
        strengthBar.style.width = percentage + '%';
        strengthBar.style.backgroundColor = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
        
        // Check password match
        if (confirmInput && confirmInput.value !== '') {
            checkPasswordMatch();
        }
    });
}

// Password match checker
if (confirmInput) {
    confirmInput.addEventListener('input', checkPasswordMatch);
}

function checkPasswordMatch() {
    if (passwordInput && confirmInput) {
        if (passwordInput.value !== confirmInput.value) {
            confirmInput.style.borderColor = '#ef476f';
            confirmInput.classList.add('is-invalid');
        } else {
            confirmInput.style.borderColor = '#06d6a0';
            confirmInput.classList.remove('is-invalid');
        }
    }
}

// Avatar preview
function previewImage(input) {
    const preview = document.getElementById('previewAvatar');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            uploadBtn.disabled = false;
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Form validation
const profileForm = document.getElementById('profileForm');
if (profileForm) {
    profileForm.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        if (!isValidEmail(email)) {
            e.preventDefault();
            showToast('error', 'Please enter a valid email address');
            return false;
        }
    });
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Toast notification
function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
        ${message}
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

// Toggle sidebar on mobile
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Add loading animation to buttons on form submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }
    });
});

// Tooltips
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Popovers
const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
popoverTriggerList.map(function(popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
});
</script>

<style>
/* Additional styles for toast notifications */
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
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-success {
    background: linear-gradient(135deg, #06d6a0, #0ca678);
}

.toast-error {
    background: linear-gradient(135deg, #ef476f, #d62828);
}

.toast-warning {
    background: linear-gradient(135deg, #ffb703, #f77f00);
}

.toast-info {
    background: linear-gradient(135deg, #4cc9f0, #0096c7);
}

/* Button loading animation */
.btn.loading {
    position: relative;
    pointer-events: none;
    opacity: 0.8;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Border dashed utility */
.border-dashed {
    border-style: dashed !important;
}
</style>

<?php require_once './includes/footer.php'; ?>