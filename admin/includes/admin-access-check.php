<?php
// admin/includes/admin-access-check.php
// Special access check for system administrator

// Include config if not already included
if (!function_exists('getDB')) {
    require_once dirname(__DIR__, 2) . '/includes/config.php';
}

function isSystemAdministrator() {
    // Check if user is logged in and is admin
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        return false;
    }
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $admin_id = (int)$_SESSION['user_id'];
    
    try {
        $db = getDB();
        
        // Check if user is system administrator with business plan
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.subscription_plan, u.user_type,
                   COALESCE(asa.is_super_admin, 0) as is_super_admin
            FROM users u
            LEFT JOIN admin_system_access asa ON u.id = asa.admin_id
            WHERE u.id = ? AND u.user_type = 'admin' 
            AND u.username = 'system administrator' 
            AND u.subscription_plan = 'business'
        ");
        $stmt->execute([$admin_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            return true;
        }
        
        return false;
        
    } catch(Exception $e) {
        error_log("System admin check error: " . $e->getMessage());
        return false;
    }
}

function requireSystemAdmin() {
    if (!isSystemAdministrator()) {
        $_SESSION['error'] = 'Access denied. System administrator privileges required.';
        
        // Redirect based on where the request came from
        if (isset($_SERVER['HTTP_REFERER'])) {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        } else {
            header('Location: ' . SITE_URL . 'admin/dashboard.php');
        }
        exit();
    }
}
?>