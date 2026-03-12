<?php
/**
 * Vendor Access Check
 * Ensures only vendors can access certain pages
 */

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login to access this page.';
    redirect(SITE_URL . 'login.php');
    exit();
}

// Check if user is a vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor area only.';
    
    // Redirect based on user type
    if ($_SESSION['user_type'] === 'admin') {
        redirect(SITE_URL . 'admin/dashboard.php');
    } else {
        redirect(SITE_URL . 'user/dashboard.php');
    }
    exit();
}

// Check if vendor account is approved
$db = getDB();
$stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendorStatus = $stmt->fetchColumn();

if ($vendorStatus === 'pending') {
    $_SESSION['warning'] = 'Your vendor account is pending approval. Some features may be limited.';
} elseif ($vendorStatus === 'rejected') {
    $_SESSION['error'] = 'Your vendor application has been rejected. Please contact support.';
    redirect(SITE_URL . 'user/dashboard.php');
    exit();
} elseif ($vendorStatus === 'suspended') {
    $_SESSION['error'] = 'Your vendor account has been suspended. Please contact support.';
    redirect(SITE_URL . 'user/dashboard.php');
    exit();
}

