<?php
// admin/includes/super-admin-check.php

/**
 * Check if current user is super admin
 * @return bool
 */
function isSuperAdmin() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        return false;
    }
    
    // Check if user is admin
    if ($_SESSION['user_type'] !== 'admin') {
        return false;
    }
    
    try {
        $db = getDB();
        
        // Check if user is system administrator with business plan
        $stmt = $db->prepare("
            SELECT id, username, subscription_plan 
            FROM users 
            WHERE id = ? AND user_type = 'admin' 
            AND username = 'system administrator' 
            AND subscription_plan = 'business'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Super admin check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Require super admin access
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        $_SESSION['error'] = 'Access denied. Super admin privileges required.';
        
        if (isset($_SERVER['HTTP_REFERER'])) {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        } else {
            header('Location: ' . SITE_URL . 'admin/dashboard.php');
        }
        exit();
    }
}

/**
 * Check if user has specific permission
 * @param string $permission
 * @return bool
 */
function hasPermission($permission) {
    if (!isSuperAdmin()) {
        return false;
    }
    
    // Super admin has all permissions
    return true;
}

// Helper function for time elapsed (agar use kar rahe ho)
function timeElapsedString($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}