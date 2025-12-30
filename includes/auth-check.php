<?php
// Authentication check file - include this in pages that require login

require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access this page.';
    
    // Store the requested page to redirect after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    redirect('login.php');
}

// Check user type for admin pages
if (basename(dirname($_SERVER['PHP_SELF'])) == 'admin' && !isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    redirect('user/dashboard.php');
}

// Check user type for user pages
if (basename(dirname($_SERVER['PHP_SELF'])) == 'user' && !isUser()) {
    $_SESSION['error'] = 'Access denied. User privileges required.';
    redirect('admin/dashboard.php');
}

// Check session timeout (30 minutes)
if (isset($_SESSION['login_time'])) {
    $session_duration = 30 * 60; // 30 minutes in seconds
    if (time() - $_SESSION['login_time'] > $session_duration) {
        session_destroy();
        $_SESSION['error'] = 'Session expired. Please login again.';
        redirect('login.php');
    }
    
    // Update session time on activity
    $_SESSION['login_time'] = time();
}
?>