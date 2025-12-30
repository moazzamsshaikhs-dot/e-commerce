<?php
require_once 'includes/config.php';

// Log activity before destroying session
if (isset($_SESSION['user_id'])) {
    logUserActivity($_SESSION['user_id'], 'logout', 'User logged out');
    
    // End session in database
    if (isset($_SESSION['session_token'])) {
        endUserSession($_SESSION['session_token']);
    }
    
    // Send security alert
    sendSecurityAlert($_SESSION['user_id'], 'logout', 'User logged out at ' . date('Y-m-d H:i:s'));
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear remember me cookies
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}
if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, '/');
}

// Clear all other cookies
foreach ($_COOKIE as $key => $value) {
    setcookie($key, '', time() - 3600, '/');
}

// Redirect to login page with success message
$_SESSION['success'] = 'You have been logged out successfully.';
redirect('login.php');
?>