<?php
// Authentication check - include in pages requiring login

require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login to access this page.';
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}

// Session timeout check (last activity based)
if (isset($_SESSION['last_activity'])) {
    $session_duration = SESSION_TIMEOUT_MINUTES * 60;
    if (time() - $_SESSION['last_activity'] > $session_duration) {
        $token = $_SESSION['session_token'] ?? null;
        if ($token) endUserSession($token);
        session_destroy();
        session_start();
        $_SESSION['error'] = 'Session expired. Please login again.';
        header('Location: ' . SITE_URL . 'login.php');
        exit();
    }
}
$_SESSION['last_activity'] = time();
?>
