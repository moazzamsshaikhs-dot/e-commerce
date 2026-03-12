<?php
/**
 * Admin Access Check
 * Ensures only admins can access certain pages
 */

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login to access this page.';
    redirect(SITE_URL . 'login.php');
    exit();
}

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin area only.';
    
    // Redirect based on user type
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor') {
        redirect(SITE_URL . 'admin/vendors/dashboard.php');
    } else {
        redirect(SITE_URL . 'user/dashboard.php');
    }
    exit();
}

// Optional: Check for specific admin permissions
// You can expand this based on your admin roles system

